<?php

namespace Tests\Feature;

use App\Http\Controllers\IntervalAccessController;
use App\Http\Controllers\MikrotikApiController;
use App\Http\Controllers\PaymentController;
use App\Models\Bus;
use App\Models\IntervalAccessDay;
use App\Models\MikrotikMacReport;
use App\Models\Payment;
use App\Models\Session;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IntervalPlanService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\IntervalTestDatabase;

class IntervalPlanTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        IntervalTestDatabase::create();
        (new \ReflectionProperty(SystemSetting::class, 'runtimeCache'))->setValue(null, []);
        Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00', 'America/Araguaina'));
        config(['app.timezone' => 'America/Araguaina', 'wifi.pix.key' => 'test@example.test',
            'wifi.pix.merchant_name' => 'TESTE', 'wifi.pix.merchant_city' => 'PALMAS']);
        Http::preventStrayRequests();
        SystemSetting::setValue('plan_interval_enabled', '1');
        SystemSetting::setValue('plan_interval_max_days', '30');
        SystemSetting::setValue('plan_interval_price_12h', '6.99');
        SystemSetting::setValue('plan_interval_price_24h', '13.98');
        SystemSetting::setValue('pix_gateway', 'manual');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quote(int $hours = 24, string $start = '2026-09-10', string $end = '2026-09-12'): array
    {
        return app(IntervalPlanService::class)->quote(['interval_start' => $start, 'interval_end' => $end, 'interval_hours' => $hours]);
    }

    private function purchase(int $hours = 24, string $start = '2026-09-10', string $end = '2026-09-12'): Payment
    {
        $user = User::create(['name' => 'Teste', 'mac_address' => 'D6:DE:C4:66:F2:84', 'ip_address' => '10.5.50.249']);
        $quote = $this->quote($hours, $start, $end);
        return Payment::create(['user_id' => $user->id, 'amount' => $quote['interval']['total_cents'] / 100,
            'payment_type' => 'pix', 'status' => 'completed', 'paid_at' => now(), 'payment_data' => $quote]);
    }

    public function test_quote_counts_both_dates_and_uses_integer_cents(): void
    {
        $this->assertSame(4194, $this->quote()['interval']['total_cents']);
        $this->assertSame(2796, $this->quote(24, '2026-09-10', '2026-09-11')['interval']['total_cents']);
        $this->assertSame(1398, $this->quote(24, '2026-09-10', '2026-09-10')['interval']['total_cents']);
    }

    public function test_invalid_intervals_and_disabled_sales_are_rejected(): void
    {
        foreach ([['2026-09-09', '2026-09-10', 24], ['2026-09-12', '2026-09-10', 24],
            ['2026-09-10', '2026-11-10', 24], ['2026-09-10', '2026-09-11', 12],
            ['2026-09-10', '2026-09-11', 13], ['2026-02-30', '2026-09-11', 24]] as [$start, $end, $hours]) {
            try { $this->quote($hours, $start, $end); $this->fail('Invalid interval accepted'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        SystemSetting::setValue('plan_interval_enabled', '0');
        $this->expectException(ValidationException::class);
        $this->quote();
    }

    public function test_pix_uses_server_quote_even_if_client_changes_amount_or_duration(): void
    {
        $user = User::create(['name' => 'Teste', 'mac_address' => 'D6:DE:C4:66:F2:84', 'ip_address' => '10.5.50.249']);
        $request = Request::create('/api/payment/pix/generate-qr', 'POST', [
            'user_id' => $user->id, 'mac_address' => $user->mac_address, 'ip_address' => $user->ip_address,
            'amount' => 0.05, 'plan_duration' => 9999, 'plan_type' => 'interval',
            'interval_start' => '2026-09-10', 'interval_end' => '2026-09-10', 'interval_hours' => 24,
        ]);
        $response = app(PaymentController::class)->generatePixQRCode($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $payment = Payment::firstOrFail();
        $this->assertSame('13.98', $payment->amount);
        $this->assertSame(24, $payment->payment_data['duration_hours']);
        $this->assertSame('13.98', $response->getData(true)['qr_code']['amount']);
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_standard_pix_keeps_existing_price_duration_and_activation(): void
    {
        $user = User::create(['name' => 'Teste', 'mac_address' => 'D6:DE:C4:66:F2:84', 'ip_address' => '10.5.50.249']);
        $request = Request::create('/', 'POST', ['user_id' => $user->id, 'mac_address' => $user->mac_address,
            'ip_address' => $user->ip_address, 'amount' => 6.99, 'plan_duration' => 12]);
        $response = app(PaymentController::class)->generatePixQRCode($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $payment = Payment::firstOrFail();
        $this->assertSame('6.99', $payment->amount);
        $this->assertFalse(IntervalPlanService::isInterval($payment));
        app(PaymentController::class)->activateUserAccess($payment);
        $this->assertSame('2026-09-10 20:00:00', $user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, Session::count());
    }

    public function test_payment_confirmation_starts_first_day_without_bypass(): void
    {
        $payment = $this->purchase();
        app(PaymentController::class)->activateUserAccess($payment);
        app(PaymentController::class)->activateUserAccess($payment);
        (new \ReflectionMethod(MikrotikApiController::class, 'autoHealPaidUsers'))->invoke(app(MikrotikApiController::class));
        $this->assertSame('active', app(IntervalPlanService::class)->access($payment->user)['state']);
        $this->assertSame(1, Session::count());
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
    }

    private function checkoutBypass(Payment $payment, bool $denied = false, int $minutesBeforePayment = 1): void
    {
        \App\Models\TempBypassLog::forceCreate([
            'user_id' => $payment->user_id, 'payment_id' => $payment->id,
            'mac_address' => $payment->user->mac_address, 'was_denied' => $denied,
            'created_at' => $payment->paid_at->copy()->subMinutes($minutesBeforePayment),
            'expires_at' => $payment->paid_at->copy()->subMinutes($minutesBeforePayment)->addMinutes(3),
        ]);
        $payment->user->update(['status' => 'temp_bypass', 'expires_at' => now()->addMinutes(2)]);
    }

    public function test_confirmation_promotes_checkout_bypass_without_browser_or_dhcp_and_does_not_restart(): void
    {
        $payment = $this->purchase(24, '2026-09-10', '2026-09-11');
        $this->checkoutBypass($payment);
        $controller = app(PaymentController::class);
        $controller->activateUserAccess($payment);
        $this->assertSame('connected', $payment->user->fresh()->status);
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-10 10:00:00');
        $controller->activateUserAccess($payment);
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        Carbon::setTestNow('2026-09-11 09:00:00');
        $controller->activateUserAccess($payment);
        $this->assertSame(1, IntervalAccessDay::count());
        $this->assertSame(1, Session::count());
        // Tomorrow is only consumed by a new foreground access, for a full 24h.
        $this->assertSame('active', app(IntervalPlanService::class)->access($payment->user, true)['state']);
        $this->assertSame('2026-09-12 09:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(2, IntervalAccessDay::count());
    }

    public function test_reconciliation_recovers_expired_bypass_from_payment_time_without_giving_extra_hours(): void
    {
        $payment = $this->purchase();
        // Bank confirmation can arrive a few minutes after the temporary access ended.
        $this->checkoutBypass($payment, false, 6);
        $payment->user->update(['status' => 'expired', 'expires_at' => now()->subMinutes(3)]);
        Carbon::setTestNow('2026-09-10 10:00:00');
        $command = app(\App\Console\Commands\ReconcilePayments::class);
        (new \ReflectionMethod($command, 'reconcileCompletedWithoutAccess'))->invoke($command, 24);
        $this->assertSame('connected', $payment->user->fresh()->status);
        $this->assertSame('2026-09-10 08:00:00', $payment->user->fresh()->connected_at->toDateTimeString());
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        (new \ReflectionMethod($command, 'reconcileCompletedWithoutAccess'))->invoke($command, 24);
        $this->assertSame(1, IntervalAccessDay::count());
    }

    public function test_mikrotik_healing_recovers_24h_checkout_without_starting_next_day(): void
    {
        $payment = $this->purchase(24);
        $this->checkoutBypass($payment);
        $payment->user->update(['status' => 'expired']);
        Carbon::setTestNow('2026-09-10 09:00:00');
        (new \ReflectionMethod(MikrotikApiController::class, 'autoHealPaidUsers'))->invoke(app(MikrotikApiController::class));
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-11 09:00:00');
        app(IntervalPlanService::class)->activatePaidCheckout($payment);
        $this->assertSame(1, IntervalAccessDay::count());
    }

    public function test_confirmation_preserves_future_booking_and_existing_manual_access(): void
    {
        $payment = $this->purchase(24, '2026-09-11', '2026-09-12');
        $this->checkoutBypass($payment);
        $this->assertSame('scheduled', app(IntervalPlanService::class)->activatePaidCheckout($payment)['state']);
        Carbon::setTestNow('2026-09-11 08:00:00');
        app(IntervalPlanService::class)->activatePaidCheckout($payment);
        $this->assertSame(0, IntervalAccessDay::count());
        $payment->user->update(['status' => 'connected', 'expires_at' => now()->addHours(12)]);
        app(IntervalPlanService::class)->activatePaidCheckout($payment);
        $this->assertSame('2026-09-11 20:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_checkout_recovery_preserves_manual_release_on_the_payment_date(): void
    {
        $payment = $this->purchase();
        $this->checkoutBypass($payment);
        $payment->user->update(['status' => 'connected', 'expires_at' => now()->addHours(14)]);
        app(IntervalPlanService::class)->activatePaidCheckout($payment);
        $this->assertSame('2026-09-10 22:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_unconfirmed_payments_never_start_a_day_even_with_bypass(): void
    {
        $payment = $this->purchase();
        $plans = app(IntervalPlanService::class);
        $this->checkoutBypass($payment);
        foreach (['pending', 'failed', 'cancelled', 'refunded'] as $status) {
            $payment->updateQuietly(['status' => $status]);
            $plans->activatePaidCheckout($payment);
            $this->assertSame(0, IntervalAccessDay::count());
        }
    }

    public static function webhookPlans(): array
    {
        return [
            'interval_24h' => [true, 24, 'approved'],
            'interval_without_bypass' => [true, 24, 'none'],
            'interval_bypass_older_than_15_minutes' => [true, 24, 'old'],
            'interval_bypass_denied' => [true, 24, 'denied'],
            'interval_changed_mac' => [true, 24, 'changed_mac'],
            'standard_699' => [false, 12, 'approved'],
            'standard_699_without_bypass' => [false, 12, 'none'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('webhookPlans')]
    public function test_actual_pagbank_webhook_persists_access_and_sync_releases_mac(bool $interval, int $hours, string $bypass): void
    {
        config(['logging.channels.pagbank' => config('logging.channels.null')]);
        $payment = $this->purchase($interval ? $hours : 24);
        if ($bypass !== 'none') {
            $this->checkoutBypass($payment, $bypass === 'denied', $bypass === 'old' ? 20 : 1);
        }
        if ($bypass === 'changed_mac') {
            $payment->user->update(['mac_address' => '02:12:34:56:78:90']);
        }
        $data = $interval ? $payment->payment_data : ['duration_hours' => 12, 'plan_name' => 'Viagem completa'];
        $payment->updateQuietly(['status' => 'pending', 'paid_at' => null,
            'transaction_id' => 'TEST_WEBHOOK', 'gateway_payment_id' => 'ORDE_TEST',
            'amount' => $interval ? $payment->amount : 6.99, 'payment_data' => $data]);
        $request = Request::create('/api/payment/webhook/pagbank', 'POST', [
            'id' => 'ORDE_TEST', 'reference_id' => 'TEST_WEBHOOK',
            'charges' => [['id' => 'CHAR_TEST', 'status' => 'PAID',
                'paid_at' => '2026-09-10T08:00:00.000-03:00',
                'amount' => ['value' => (int) round($payment->amount * 100)],
                'payment_method' => ['type' => 'PIX']]],
        ]);
        $controller = app(PaymentController::class);
        $response = $controller->pagbankWebhook($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('connected', $payment->user->fresh()->status);
        $expected = now()->addHours($hours)->toDateTimeString();
        $this->assertSame($expected, $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame($interval ? 1 : 0, IntervalAccessDay::count());
        $this->assertSame(1, Session::count());
        Carbon::setTestNow(now()->addMinutes(4));
        $this->assertSame(200, $controller->pagbankWebhook($request)->getStatusCode());
        $this->assertSame($expected, $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, Session::count());
        Cache::put('auto_heal_last_run', now(), 300);
        $sync = app(MikrotikApiController::class)->checkPaidUsersLite(Request::create('/', 'GET', [
            'token' => config('wifi.mikrotik_sync_token', 'mikrotik-sync-2024'),
        ]))->getContent();
        $this->assertStringContainsString('L:'.$payment->user->mac_address, $sync);
        $this->assertStringNotContainsString('R:'.$payment->user->mac_address, $sync);
    }

    public function test_status_poll_recovers_already_completed_interval_without_bypass(): void
    {
        $payment = $this->purchase();
        $payment->user->update(['status' => 'expired', 'expires_at' => now()->addMinutes(3)]);
        Carbon::setTestNow('2026-09-10 08:10:00');
        $response = app(PaymentController::class)->checkPixStatus(Request::create('/', 'GET', ['payment_id' => $payment->id]));
        $this->assertSame('completed', $response->getData(true)['payment']['status']);
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, IntervalAccessDay::count());
    }

    public function test_diagnostic_reports_active_policy_without_consuming_a_day(): void
    {
        $payment = $this->purchase(24, '2026-09-10', '2026-09-11');
        $code = \Illuminate\Support\Facades\Artisan::call('interval:diagnose', ['payment' => $payment->id]);
        $this->assertSame(0, $code);
        $data = json_decode(\Illuminate\Support\Facades\Artisan::output(), true);
        $this->assertSame('payment-confirmation-v2', $data['policy']);
        $this->assertSame($payment->id, $data['payment_id']);
        $this->assertSame([], $data['days']);
        $this->assertSame(0, IntervalAccessDay::count());
        $this->assertSame(0, Session::count());
    }

    public function test_daily_window_survives_midnight_and_reconnect_without_renewal(): void
    {
        $payment = $this->purchase(24, '2026-09-10', '2026-09-11');
        $plans = app(IntervalPlanService::class);
        Carbon::setTestNow('2026-09-10 21:00:00');
        $first = $plans->access($payment->user, true);
        Carbon::setTestNow('2026-09-11 07:00:00');
        $this->assertSame($first['expires_at'], $plans->access($payment->user, true)['expires_at']);
        $this->assertSame(1, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-11 22:00:00');
        $plans->access($payment->user, true);
        $this->assertSame('2026-09-12 22:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(2, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-12 23:00:00');
        $this->assertSame('none', $plans->access($payment->user, true)['state']);
        $this->assertSame(2, IntervalAccessDay::count());
    }

    public function test_future_purchase_24h_last_day_and_no_unused_carryover(): void
    {
        $payment = $this->purchase(24, '2026-09-11', '2026-09-12');
        $plans = app(IntervalPlanService::class);
        $this->assertSame('scheduled', $plans->access($payment->user, true)['state']);
        Carbon::setTestNow('2026-09-12 22:00:00');
        $plans->access($payment->user, true);
        $this->assertSame('2026-09-13 22:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-13 22:00:00');
        $this->assertSame('none', $plans->access($payment->user, true)['state']);
    }

    public function test_disabled_sales_preserve_paid_entitlement_and_snapshot(): void
    {
        $payment = $this->purchase();
        SystemSetting::setValue('plan_interval_enabled', '0');
        SystemSetting::setValue('plan_interval_price_24h', '99.99');
        $this->assertSame('active', app(IntervalPlanService::class)->access($payment->user, true)['state']);
        $this->assertSame('41.94', $payment->fresh()->amount);
        $this->assertSame(1398, $payment->fresh()->payment_data['interval']['base_price_cents']);
    }

    public function test_pending_and_refunded_payments_never_start_access(): void
    {
        $payment = $this->purchase();
        foreach (['pending', 'refunded', 'failed', 'cancelled'] as $status) {
            $payment->updateQuietly(['status' => $status]);
            $this->assertSame('none', app(IntervalPlanService::class)->access($payment->user, true)['state']);
        }
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_foreground_request_needs_matching_recent_bus_and_mac(): void
    {
        $payment = $this->purchase();
        $plans = app(IntervalPlanService::class);
        $controller = app(IntervalAccessController::class);
        Bus::create(['mikrotik_serial' => 'BUS1', 'name' => 'Teste', 'last_public_ip' => '203.0.113.10', 'last_sync_at' => now()]);
        $report = MikrotikMacReport::create(['mac_address' => $payment->user->mac_address,
            'ip_address' => $payment->user->ip_address, 'mikrotik_id' => 'BUS1', 'last_seen' => now()->subMinutes(10)]);
        $request = Request::create('/', 'POST', ['mac_address' => $payment->user->mac_address,
            'ip_address' => $payment->user->ip_address], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $this->assertSame('ready', $controller->connect($request, $plans)->getData(true)['state']);
        $report->update(['last_seen' => now()]);
        $outside = clone $request;
        $outside->server->set('REMOTE_ADDR', '198.51.100.5');
        $this->assertSame('ready', $controller->connect($outside, $plans)->getData(true)['state']);
        $this->assertSame('active', $controller->connect($request, $plans)->getData(true)['state']);
        $controller->connect($request, $plans);
        $this->assertSame(1, IntervalAccessDay::count());
    }

    public function test_paid_interval_replaces_three_minute_bypass_without_dhcp_report_and_preserves_both_days(): void
    {
        Carbon::setTestNow('2026-09-22 07:45:26');
        $payment = $this->purchase(24, '2026-09-22', '2026-09-23');
        $payment->user->update(['last_mikrotik_id' => 'BUS1', 'status' => 'temp_bypass',
            'expires_at' => '2026-09-22 07:47:38']);
        $bus = Bus::create(['mikrotik_serial' => 'BUS1', 'name' => 'Teste',
            'last_public_ip' => '203.0.113.10', 'last_sync_at' => now()]);
        $request = Request::create('/', 'POST', ['mac_address' => $payment->user->mac_address,
            'ip_address' => $payment->user->ip_address], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $controller = app(IntervalAccessController::class);
        $plans = app(IntervalPlanService::class);
        $this->assertSame(0, MikrotikMacReport::count());
        $this->assertSame('active', $controller->connect($request, $plans)->getData(true)['state']);
        $this->assertSame('2026-09-23 07:45:26', $payment->user->fresh()->expires_at->toDateTimeString());
        Carbon::setTestNow('2026-09-22 09:17:00');
        $this->assertSame('active', $controller->connect($request, $plans)->getData(true)['state']);
        $this->assertSame('2026-09-23 07:45:26', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, IntervalAccessDay::count());
        Carbon::setTestNow('2026-09-22 20:00:00');
        $this->assertSame('active', $controller->connect($request, $plans)->getData(true)['state']);
        Carbon::setTestNow('2026-09-23 08:00:00');
        $bus->update(['last_sync_at' => now()]);
        $this->assertSame('active', $controller->connect($request, $plans)->getData(true)['state']);
        $this->assertSame('2026-09-24 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(2, IntervalAccessDay::count());
        $this->assertSame(2, Session::count());
        Carbon::setTestNow('2026-09-24 08:00:01');
        $this->assertSame('none', $controller->connect($request, $plans)->getData(true)['state']);
        $this->assertSame('27.96', $payment->fresh()->amount);
    }

    public function test_expired_bypass_can_start_paid_day_when_passenger_returns_to_portal(): void
    {
        $payment = $this->purchase();
        $payment->user->update(['last_mikrotik_id' => 'BUS1', 'status' => 'expired',
            'expires_at' => now()->subMinutes(2)]);
        Bus::create(['mikrotik_serial' => 'BUS1', 'name' => 'Teste',
            'last_public_ip' => '203.0.113.10', 'last_sync_at' => now()]);
        $request = Request::create('/', 'POST', ['mac_address' => $payment->user->mac_address,
            'ip_address' => $payment->user->ip_address], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $this->assertSame('active', app(IntervalAccessController::class)->connect($request,
            app(IntervalPlanService::class))->getData(true)['state']);
        $this->assertSame('connected', $payment->user->fresh()->status);
        $this->assertSame('2026-09-11 08:00:00', $payment->user->fresh()->expires_at->toDateTimeString());
    }

    public function test_missing_report_fallback_requires_matching_device_and_recent_same_bus(): void
    {
        $payment = $this->purchase();
        $payment->user->update(['last_mikrotik_id' => 'BUS1']);
        $bus = Bus::create(['mikrotik_serial' => 'BUS1', 'name' => 'Teste',
            'last_public_ip' => '203.0.113.10', 'last_sync_at' => now()]);
        $connect = function ($publicIp, $deviceIp) use ($payment) {
            return app(IntervalAccessController::class)->connect(Request::create('/', 'POST', [
                'mac_address' => $payment->user->mac_address, 'ip_address' => $deviceIp,
            ], [], [], ['REMOTE_ADDR' => $publicIp]), app(IntervalPlanService::class))->getData(true)['state'];
        };
        $this->assertSame('ready', $connect('198.51.100.5', $payment->user->ip_address));
        $this->assertSame('ready', $connect('203.0.113.10', '10.5.50.100'));
        $this->assertSame('ready', $connect('203.0.113.10', null));
        $bus->update(['last_sync_at' => now()->subMinutes(4)]);
        $this->assertSame('ready', $connect('203.0.113.10', $payment->user->ip_address));
        $bus->update(['last_sync_at' => now()]);
        $payment->user->update(['last_mikrotik_id' => 'BUS2']);
        $this->assertSame('ready', $connect('203.0.113.10', $payment->user->ip_address));
        $payment->user->update(['last_mikrotik_id' => 'BUS1']);
        MikrotikMacReport::create(['mac_address' => $payment->user->mac_address,
            'ip_address' => '10.5.50.100', 'mikrotik_id' => 'BUS1', 'last_seen' => now()]);
        $this->assertSame('ready', $connect('203.0.113.10', $payment->user->ip_address));
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_existing_mikrotik_sync_releases_then_removes_daily_mac(): void
    {
        $payment = $this->purchase();
        app(IntervalPlanService::class)->access($payment->user, true);
        $request = Request::create('/', 'GET', ['token' => config('wifi.mikrotik_sync_token', 'mikrotik-sync-2024')]);
        $controller = app(MikrotikApiController::class);
        Cache::put('auto_heal_last_run', now(), 300);
        $this->assertStringContainsString('L:'.$payment->user->mac_address, $controller->checkPaidUsersLite($request)->getContent());
        Carbon::setTestNow('2026-09-11 08:00:01');
        Cache::forget('mikrotik_sync_lists_all');
        $response = $controller->checkPaidUsersLite($request)->getContent();
        $this->assertStringContainsString('R:'.$payment->user->mac_address, $response);
        $this->assertStringNotContainsString('L:'.$payment->user->mac_address, $response);
    }

    public function test_overlapping_paid_interval_is_not_charged_again(): void
    {
        $payment = $this->purchase();
        $response = app(PaymentController::class)->generatePixQRCode(Request::create('/', 'POST', [
            'user_id' => $payment->user_id, 'mac_address' => $payment->user->mac_address,
            'ip_address' => $payment->user->ip_address, 'plan_type' => 'interval',
            'interval_start' => '2026-09-11', 'interval_end' => '2026-09-13', 'interval_hours' => 24,
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1, Payment::count());
    }

    public function test_longer_existing_access_is_not_shortened_or_consumed(): void
    {
        $payment = $this->purchase();
        $payment->user->update(['status' => 'connected', 'expires_at' => now()->addHours(24)]);
        $this->assertSame('existing_access', app(IntervalPlanService::class)->access($payment->user, true)['state']);
        $this->assertSame(0, IntervalAccessDay::count());
    }

    public function test_admin_can_save_interval_settings_and_portal_defaults_to_two_days(): void
    {
        $request = Request::create('/', 'PUT', [
            'wifi_price' => '5.99', 'wifi_price_full' => '6.99', 'pix_gateway' => 'pagbank',
            'session_duration' => 12, 'session_duration_short' => 1, 'video_discount_amount' => 1,
            'plan_interval_enabled' => '1', 'plan_interval_price_24h' => '15.00', 'plan_interval_max_days' => 15,
        ]);
        $request->setLaravelSession(app('session')->driver());
        app(\App\Http\Controllers\Admin\SettingsController::class)->update($request);
        $settings = IntervalPlanService::settings();
        $this->assertSame(15.0, $settings['price_24h']);
        $this->assertSame(15, $settings['max_days']);
        $html = view('portal.partials.interval-plan', ['interval_plan' => $settings])->render();
        $this->assertStringContainsString('value="2026-09-10"', $html);
        $this->assertStringContainsString('R$15,00', $html);
        $settings['enabled'] = false;
        $this->assertStringNotContainsString('id="interval-plan-option"', view('portal.partials.interval-plan', ['interval_plan' => $settings])->render());
    }
}
