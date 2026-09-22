<?php

namespace App\Services;

use App\Models\IntervalAccessDay;
use App\Models\Payment;
use App\Models\Session;
use App\Models\SystemSetting;
use App\Models\TempBypassLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IntervalPlanService
{
    /** Complete a checkout already started on the device, even if its portal closed. */
    public function activatePaidCheckout(Payment $payment): array
    {
        if (! self::isInterval($payment) || ! $payment->user) {
            return $this->result('none', 'Nenhum intervalo disponível.');
        }

        return DB::transaction(function () use ($payment) {
            $user = User::whereKey($payment->user_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'completed' || ! self::isInterval($payment) || ! $payment->paid_at) {
                return $this->result('none', 'Pagamento ainda não confirmado.');
            }

            $start = $payment->paid_at->copy();
            $interval = $payment->payment_data['interval'];
            $expires = $start->copy()->addHours($interval['hours_per_day']);

            // A repeated webhook/reconciliation can restore a day, never renew it
            // or consume tomorrow's entitlement. Future bookings wait for the portal.
            if ($start->isFuture() || ! $expires->isFuture()
                || $start->toDateString() < $interval['start']
                || $start->toDateString() > $interval['end']
                || IntervalAccessDay::where('payment_id', $payment->id)->exists()
                || IntervalAccessDay::where('user_id', $user->id)->where('access_date', $start->toDateString())->exists()
                || (in_array($user->status, ['connected', 'active']) && $user->expires_at?->isFuture())) {
                return $this->access($user);
            }

            // The approved bypass is evidence of this device's recent checkout.
            // Do not infer first access from payment alone or a background DHCP lease.
            $startedCheckout = TempBypassLog::where('payment_id', $payment->id)
                ->where('user_id', $user->id)->where('mac_address', $user->mac_address)
                ->where('was_denied', false)
                ->where('created_at', '<=', $start)
                ->where('created_at', '>=', $start->copy()->subMinutes(15))->exists();
            if (! $startedCheckout) {
                return $this->access($user);
            }

            $day = $this->startDay($user, $payment, $start);
            return $this->result('active', 'Pagamento confirmado. Sua diária está ativa.', $day);
        });
    }

    public static function settings(): array
    {
        return [
            'enabled' => (bool) SystemSetting::getValue('plan_interval_enabled', '0'),
            'max_days' => max(2, (int) SystemSetting::getValue('plan_interval_max_days', '30')),
            'price_12h' => (float) SystemSetting::getValue('plan_interval_price_12h', '6.99'),
            'today' => now()->toDateString(),
            'tomorrow' => now()->addDay()->toDateString(),
        ];
    }

    public static function isInterval(Payment $payment): bool
    {
        return data_get($payment->payment_data, 'plan_type') === 'interval';
    }

    public function quote(array $input): array
    {
        $settings = self::settings();
        if (! $settings['enabled']) {
            throw ValidationException::withMessages(['plan_type' => 'O plano por intervalo está indisponível.']);
        }
        $data = Validator::make($input, [
            'interval_start' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addYear()->toDateString(),
            'interval_end' => 'required|date_format:Y-m-d|after_or_equal:interval_start',
            'interval_hours' => 'required|integer|in:12,24',
        ])->validate();
        $start = CarbonImmutable::parse($data['interval_start']);
        $end = CarbonImmutable::parse($data['interval_end']);
        $days = (int) $start->diffInDays($end) + 1;
        if ($days < 2 || $days > $settings['max_days']) {
            throw ValidationException::withMessages(['interval_end' => "Escolha de 2 a {$settings['max_days']} dias. Para um único dia, escolha Viagem completa."]);
        }
        $baseCents = (int) round($settings['price_12h'] * 100);
        $dailyCents = $baseCents * ((int) $data['interval_hours'] / 12);

        return [
            'plan_type' => 'interval',
            'plan_name' => 'Plano por intervalo',
            'plan_suffix' => "/ {$days} dia(s)",
            'duration_hours' => (int) $data['interval_hours'],
            'interval' => [
                'start' => $data['interval_start'],
                'end' => $data['interval_end'],
                'days' => $days,
                'hours_per_day' => (int) $data['interval_hours'],
                'base_price_cents' => $baseCents,
                'daily_price_cents' => (int) $dailyCents,
                'total_cents' => (int) ($dailyCents * $days),
                'timezone' => config('app.timezone'),
            ],
        ];
    }

    /** Later days require a foreground portal request; background healing only restores access. */
    public function access(User $user, bool $startNewDay = false): array
    {
        if (! Payment::where('user_id', $user->id)->where('status', 'completed')
            ->where('payment_data->plan_type', 'interval')->exists()) {
            return $this->result('none', 'Nenhum intervalo disponível.');
        }
        return DB::transaction(function () use ($user, $startNewDay) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $today = $now->toDateString();
            $day = IntervalAccessDay::where('user_id', $user->id)
                ->where('expires_at', '>', $now)
                ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
                ->orderByDesc('expires_at')->first();
            if ($day) {
                $this->restoreDay($lockedUser, $day);
                return $this->result('active', 'Sua diária está ativa. Liberação em até 30 segundos.', $day);
            }

            $payment = Payment::where('user_id', $user->id)->where('status', 'completed')
                ->where('payment_data->plan_type', 'interval')
                ->where('payment_data->interval->end', '>=', $today)
                ->orderBy('payment_data->interval->start')->orderBy('id')->first();
            if (! $payment) {
                return $this->result('none', 'Nenhum intervalo disponível.');
            }
            $interval = $payment->payment_data['interval'];
            if ($interval['start'] > $today) {
                $date = CarbonImmutable::parse($interval['start'])->format('d/m/Y');
                return $this->result('scheduled', "Plano pago. Disponível a partir de {$date}.");
            }
            if (in_array($lockedUser->status, ['connected', 'active']) && $lockedUser->expires_at?->isFuture()) {
                return $this->result('existing_access', 'Você já possui acesso ativo. Sua próxima diária ainda não começou.');
            }
            if (IntervalAccessDay::where('user_id', $user->id)->where('access_date', $today)->exists()) {
                return $this->result('used', $interval['end'] > $today
                    ? 'A diária de hoje já terminou. A próxima fica disponível amanhã.'
                    : 'A última diária deste intervalo já terminou.');
            }
            if (! $startNewDay) {
                return $this->result('ready', 'Diária disponível. Abra o portal conectado ao Wi-Fi do ônibus.');
            }

            $day = $this->startDay($lockedUser, $payment, $now);

            return $this->result('active', 'Sua diária começou. Liberação em até 30 segundos.', $day);
        });
    }

    private function startDay(User $user, Payment $payment, \Carbon\CarbonInterface $start): IntervalAccessDay
    {
        $day = IntervalAccessDay::create([
            'user_id' => $user->id, 'payment_id' => $payment->id,
            'access_date' => $start->toDateString(), 'started_at' => $start,
            'expires_at' => $start->copy()->addHours($payment->payment_data['interval']['hours_per_day']),
        ]);
        Session::create([
            'user_id' => $user->id, 'payment_id' => $payment->id,
            'started_at' => $start, 'session_status' => 'active',
        ]);
        $this->restoreDay($user, $day);
        return $day;
    }

    private function restoreDay(User $user, IntervalAccessDay $day): void
    {
        // A separate valid purchase must not be shortened by an interval reconnect.
        if (in_array($user->status, ['connected', 'active']) && $user->expires_at?->gte($day->expires_at)) {
            return;
        }
        $user->update(['status' => 'connected', 'connected_at' => $day->started_at, 'expires_at' => $day->expires_at]);
        Cache::forget('mikrotik_sync_lists_all');
    }

    private function result(string $state, string $message, ?IntervalAccessDay $day = null): array
    {
        return ['state' => $state, 'message' => $message, 'expires_at' => $day?->expires_at->toISOString()];
    }
}
