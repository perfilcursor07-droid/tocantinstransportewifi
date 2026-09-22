<?php

namespace Tests\Feature;

use App\Http\Controllers\MikrotikApiController;
use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\TempBypassLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\IntervalTestDatabase;

class TempBypassTest extends TestCase
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
        Carbon::setTestNow('2026-09-22 10:00:00');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function purchase(bool $interval = false): Payment
    {
        $user = User::create(['mac_address' => '02:12:34:56:78:90', 'ip_address' => '10.5.50.10']);
        $data = ['duration_hours' => 12];
        if ($interval) {
            $data += ['plan_type' => 'interval', 'interval' => ['start' => '2026-09-22',
                'end' => '2026-09-23', 'hours_per_day' => 12, 'days' => 2]];
        }
        return Payment::create(['user_id' => $user->id, 'amount' => $interval ? 13.98 : 6.99,
            'payment_type' => 'pix', 'status' => 'pending', 'payment_data' => $data]);
    }

    private function bypass(Payment $payment): array
    {
        $response = app(PaymentController::class)->activateTempBypass(
            Request::create('/', 'POST', ['payment_id' => $payment->id]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        return $response->getData(true);
    }

    public function test_expired_connected_flag_does_not_pretend_to_have_internet(): void
    {
        $payment = $this->purchase();
        $payment->user->update(['status' => 'connected', 'expires_at' => now()->subMinute()]);
        $result = $this->bypass($payment);
        $this->assertTrue($result['success']);
        $this->assertSame(180, $result['expires_in']);
        $this->assertSame('temp_bypass', $payment->user->fresh()->status);
        $this->assertSame('2026-09-22 10:03:00', $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, TempBypassLog::where('was_denied', false)->count());
    }

    public function test_retry_on_second_allowance_reuses_expiry_then_enforces_limit(): void
    {
        $payment = $this->purchase();
        Cache::put('bypass_mac_'.$payment->user->mac_address, 1, 3600);
        $this->bypass($payment);
        Carbon::setTestNow(now()->addMinute());
        $retry = $this->bypass($payment);
        $this->assertTrue($retry['success']);
        $this->assertTrue($retry['already_bypassed']);
        $this->assertSame(0, $retry['bypasses_remaining']);
        $this->assertSame(120, $retry['expires_in']);
        $this->assertSame(1, TempBypassLog::count());
        $this->assertSame(2, Cache::get('bypass_mac_'.$payment->user->mac_address));
        Carbon::setTestNow('2026-09-22 10:04:00');
        $this->assertTrue($this->bypass($payment)['limit_reached']);
        $this->assertSame('2026-09-22 10:03:00', $payment->user->fresh()->expires_at->toDateTimeString());
    }

    public function test_admin_block_is_preserved(): void
    {
        $payment = $this->purchase();
        Cache::put('bypass_blocked_'.$payment->user->mac_address, ['blocked_by' => 1], 3600);
        $this->assertTrue($this->bypass($payment)['blocked']);
        $this->assertNull($payment->user->fresh()->expires_at);
        $this->assertTrue(TempBypassLog::first()->was_denied);
    }

    public static function plans(): array
    {
        return ['standard_699' => [false], 'interval_1398' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('plans')]
    public function test_bypass_releases_mac_and_confirmation_replaces_it_without_late_downgrade(bool $interval): void
    {
        $payment = $this->purchase($interval);
        Cache::put('mikrotik_sync_lists_all', [[], []], 8);
        $this->assertSame(180, $this->bypass($payment)['expires_in']);
        $this->assertFalse(Cache::has('mikrotik_sync_lists_all'));
        Cache::put('auto_heal_last_run', now(), 300);
        $sync = app(MikrotikApiController::class)->checkPaidUsersLite(Request::create('/', 'GET', [
            'token' => config('wifi.mikrotik_sync_token', 'mikrotik-sync-2024'),
        ]))->getContent();
        $this->assertStringContainsString('L:'.$payment->user->mac_address, $sync);
        Carbon::setTestNow('2026-09-22 10:01:00');
        $payment->updateQuietly(['status' => 'completed', 'paid_at' => now()]);
        app(PaymentController::class)->activateUserAccess($payment);
        $expiry = $payment->user->fresh()->expires_at->toDateTimeString();
        $this->assertSame('2026-09-22 22:01:00', $expiry);
        $this->assertTrue($this->bypass($payment)['already_connected']);
        $this->assertSame($expiry, $payment->user->fresh()->expires_at->toDateTimeString());
        $this->assertSame('connected', $payment->user->fresh()->status);
    }

    public function test_unpaid_bypass_expires_and_mac_is_removed(): void
    {
        $payment = $this->purchase();
        $this->bypass($payment);
        Carbon::setTestNow('2026-09-22 10:03:01');
        Cache::put('auto_heal_last_run', now(), 300);
        $sync = app(MikrotikApiController::class)->checkPaidUsersLite(Request::create('/', 'GET', [
            'token' => config('wifi.mikrotik_sync_token', 'mikrotik-sync-2024'),
        ]))->getContent();
        $this->assertStringContainsString('R:'.$payment->user->mac_address, $sync);
        $this->assertStringNotContainsString('L:'.$payment->user->mac_address, $sync);
    }
}
