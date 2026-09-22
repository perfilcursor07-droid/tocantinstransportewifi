<?php

namespace App\Console\Commands;

use App\Models\IntervalAccessDay;
use App\Models\Payment;
use App\Services\IntervalPlanService;
use Illuminate\Console\Command;

class DiagnoseIntervalPayment extends Command
{
    protected $signature = 'interval:diagnose {payment : ID do pagamento}';

    protected $description = 'Mostra a versão da regra e o acesso de um intervalo, sem alterar dados ou chamar o gateway';

    public function handle(): int
    {
        $payment = Payment::with('user')->find($this->argument('payment'));
        if (! $payment || ! IntervalPlanService::isInterval($payment)) {
            $this->error('Pagamento por intervalo não encontrado.');
            return self::FAILURE;
        }

        $user = $payment->user;
        $this->line(json_encode([
            'policy' => defined(IntervalPlanService::class.'::FIRST_DAY_POLICY')
                ? IntervalPlanService::FIRST_DAY_POLICY : 'legacy',
            'server_time' => now()->toIso8601String(),
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'interval' => data_get($payment->payment_data, 'interval'),
            'user_status' => $user?->status,
            'has_mac' => filled($user?->mac_address),
            'connected_at' => $user?->connected_at?->toIso8601String(),
            'expires_at' => $user?->expires_at?->toIso8601String(),
            'days' => IntervalAccessDay::where('payment_id', $payment->id)
                ->orderBy('access_date')->get(['access_date', 'started_at', 'expires_at'])->toArray(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
