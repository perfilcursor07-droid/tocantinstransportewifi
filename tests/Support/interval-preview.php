<?php

// Local UI fixture: memory-only database, no gateway or router requests.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Tests\Support\IntervalTestDatabase::create();
$request = \Illuminate\Http\Request::capture();
$app->instance('request', $request);
$session = $app->make('session')->driver();
$session->start();
$request->setLaravelSession($session);
$app->make('view')->share('errors', new \Illuminate\Support\ViewErrorBag());
\App\Models\SystemSetting::setValue('plan_interval_enabled', '1');
\App\Models\SystemSetting::setValue('pagbank_token', 'local-preview-only');

if (str_starts_with($path, '/api/')) {
    header('Content-Type: application/json');
    echo json_encode(match (true) {
        $path === '/api/interval/connect' => ['state' => 'none'],
        $path === '/api/connection-check' => ['on_hotspot' => true, 'connected' => true],
        str_starts_with($path, '/api/user/check-mac/') => ['exists' => false],
        default => ['success' => false, 'message' => 'Prévia local sem pagamentos.'],
    });
    exit;
}
if ($path === '/admin/settings') {
    $admin = \App\Models\User::create(['name' => 'Administrador local', 'role' => 'admin']);
    $app->make('auth')->guard()->setUser($admin);
    echo app(\App\Http\Controllers\Admin\SettingsController::class)->index()->render();
    exit;
}
if ($path !== '/') {
    http_response_code(404);
    exit;
}
echo view('portal.index', [
    'company_name' => 'WiFi Tocantins Express', 'price' => 6.99, 'original_price' => 12.90,
    'discount_percentage' => 46, 'savings' => 5.91, 'session_duration' => 12,
    'session_duration_short' => 1, 'wifi_price_full' => 6.99, 'wifi_price_short' => 5.99,
    'plan_short_enabled' => false, 'plan_full_enabled' => true,
    'interval_plan' => \App\Services\IntervalPlanService::settings(),
    'video_discount_enabled' => false, 'video_discount_amount' => 1,
    'on_hotspot' => true, 'connected_user' => null, 'client_info' => [],
    'review_average' => 4.8, 'review_count' => 10, 'passengers_30d' => 100,
])->render();
