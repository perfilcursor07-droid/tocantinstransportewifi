<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalDashboardController;
use App\Http\Controllers\PortalAuthController;
use App\Http\Controllers\SupportDiagnosticController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\ServiceReviewController as AdminServiceReviewController;
use App\Http\Controllers\ServiceReviewController;
use App\Http\Controllers\Admin\WhatsappController;
use App\Http\Controllers\DriverVoucherController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\Admin\DriverRequestController as AdminDriverRequestController;
use App\Http\Controllers\ConnectivityProbeController;

// Página principal do portal cativo
Route::get('/', [PortalController::class, 'index'])->name('portal.index');
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('portal.dashboard');
    Route::post('/dashboard/payments/regenerate', [PortalDashboardController::class, 'regeneratePix'])->name('portal.dashboard.payments.regenerate');
});

Route::get('/entrar', [PortalAuthController::class, 'showLogin'])->name('portal.login');
Route::post('/entrar', [PortalAuthController::class, 'login'])->name('portal.login.submit');
Route::post('/sair', [PortalAuthController::class, 'logout'])->middleware('auth')->name('portal.logout');

Route::get('/suporte/diagnostico', [SupportDiagnosticController::class, 'show'])->name('support.diagnostics');
Route::post('/suporte/diagnostico/consultar', [SupportDiagnosticController::class, 'lookup'])->name('support.diagnostics.lookup');

// 📡 Probe de diagnóstico — link público enviado pelo admin via chat
Route::get('/diagnostico/{token}', [ConnectivityProbeController::class, 'show'])->name('diagnostico.show');
Route::post('/api/diagnostico/{token}/report', [ConnectivityProbeController::class, 'report'])->name('diagnostico.report');
Route::get('/api/diagnostico/ping', [ConnectivityProbeController::class, 'ping'])->name('diagnostico.ping');
Route::get('/api/diagnostico/download', [ConnectivityProbeController::class, 'downloadPayload'])->name('diagnostico.download');

// Rotas de Vouchers para Motoristas (Não requer autenticação)
Route::prefix('voucher')->name('voucher.')->group(function () {
    Route::get('/ativar', [DriverVoucherController::class, 'showActivate'])->name('activate');
    Route::post('/buscar', [DriverVoucherController::class, 'searchVoucher'])->name('search');
    Route::get('/buscar', fn() => redirect()->route('voucher.activate')); // Redireciona GET para página de ativação
    Route::post('/ativar', [DriverVoucherController::class, 'activate'])->name('activate.submit');
    Route::get('/status', [DriverVoucherController::class, 'showStatus'])->name('status');
    Route::post('/status', [DriverVoucherController::class, 'checkStatus'])->name('status.check');
    Route::post('/desconectar', [DriverVoucherController::class, 'disconnect'])->name('disconnect');
});

// Rota alternativa para /portal (redireciona para raiz)
Route::get('/portal', function () {
    return redirect('/');
})->name('portal.redirect');

// Página pública de reativação de acesso
Route::get('/reativar', function () {
    return view('portal.reativar');
})->name('portal.reativar');

Route::get('/avaliacao/{token}', [ServiceReviewController::class, 'show'])->name('reviews.show');
Route::post('/avaliacao/{token}', [ServiceReviewController::class, 'store'])->name('reviews.store');

// Cadastro publico de motoristas (nao precisa estar na rede)
Route::get('/cadastro-motorista', [DriverRequestController::class, 'create'])->name('driver-request.create');
Route::post('/cadastro-motorista', [DriverRequestController::class, 'store'])->name('driver-request.store');
Route::get('/cadastro-motorista/enviado', [DriverRequestController::class, 'success'])->name('driver-request.success');

// Rotas de Autenticação
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/create-admin', [AuthController::class, 'createAdmin'])->name('create.admin');

// Página de acesso administrativo
Route::get('/admin-access', function () {
    return view('admin-access');
})->name('admin.access');

// Painel Administrativo (Protegido por autenticação)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin.access'])->group(function () {
    // Rota raiz redireciona para o primeiro modulo disponivel
    Route::get('/', function () {
        return redirect(auth()->user()->getHomeRoute());
    });
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard')->middleware('module:dashboard');
    Route::get('/revenue-report', [AdminController::class, 'revenueReport'])->name('revenue-report')->middleware('module:dashboard');
    Route::get('/connection-logs', [AdminController::class, 'connectionLogs'])->name('connection-logs')->middleware('module:dashboard');
    Route::get('/api/stats', [AdminController::class, 'apiStats'])->name('api.stats')->middleware('module:dashboard');
    Route::post('/export', [AdminController::class, 'exportReport'])->name('export')->middleware('module:dashboard');
    
    // Rotas de Relatórios
    Route::middleware(['module:reports'])->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
        Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    });
    
    // Rotas de Vouchers
    Route::middleware(['module:vouchers'])->group(function () {
        Route::get('/vouchers', [AdminVoucherController::class, 'index'])->name('vouchers.index');
        Route::get('/vouchers/create', [AdminVoucherController::class, 'create'])->name('vouchers.create');
        Route::post('/vouchers', [AdminVoucherController::class, 'store'])->name('vouchers.store');
        Route::get('/vouchers/{voucher}/edit', [AdminVoucherController::class, 'edit'])->name('vouchers.edit');
        Route::put('/vouchers/{voucher}', [AdminVoucherController::class, 'update'])->name('vouchers.update');
        Route::post('/vouchers/{voucher}/toggle', [AdminVoucherController::class, 'toggleStatus'])->name('vouchers.toggle');
        Route::post('/vouchers/{voucher}/reset', [AdminVoucherController::class, 'resetDaily'])->name('vouchers.reset');
        Route::delete('/vouchers/{voucher}', [AdminVoucherController::class, 'destroy'])->name('vouchers.destroy');
    });
    
    // Rotas do Chat (Atendimento Online)
    Route::middleware(['module:chat'])->prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ChatController::class, 'index'])->name('index');
        Route::get('/unread-count', [App\Http\Controllers\Admin\ChatController::class, 'getUnreadCount'])->name('unread');
        Route::get('/{id}', [App\Http\Controllers\Admin\ChatController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::post('/{id}/reply', [App\Http\Controllers\Admin\ChatController::class, 'reply'])->name('reply')->where('id', '[0-9]+');
        Route::post('/{id}/close', [App\Http\Controllers\Admin\ChatController::class, 'close'])->name('close')->where('id', '[0-9]+');
        Route::delete('/{id}', [App\Http\Controllers\Admin\ChatController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        Route::get('/{id}/messages', [App\Http\Controllers\Admin\ChatController::class, 'getMessages'])->name('messages')->where('id', '[0-9]+');
        // 📡 Admin gera link de teste de conexão a partir do chat
        Route::post('/{id}/probe', [ConnectivityProbeController::class, 'createFromChat'])->name('probe.create')->where('id', '[0-9]+');
        // 🎁 Admin gera voucher de cortesia de 12h a partir do chat
        Route::post('/{id}/voucher', [App\Http\Controllers\Admin\ChatVoucherController::class, 'createFromChat'])->name('voucher.create')->where('id', '[0-9]+');
    });

    Route::middleware(['module:reviews'])->prefix('avaliacoes')->name('reviews.')->group(function () {
        Route::get('/', [AdminServiceReviewController::class, 'index'])->name('index');
        Route::get('/configuracoes', [AdminServiceReviewController::class, 'settings'])->name('settings');
    });
    
    // Rotas APENAS para Administradores
    Route::middleware(['admin.only'])->group(function () {
        // Configuracoes e teste de avaliacoes (somente admin pode editar)
        Route::put('/avaliacoes/configuracoes', [AdminServiceReviewController::class, 'updateSettings'])->name('reviews.settings.update');
        Route::post('/avaliacoes/enviar-teste', [AdminServiceReviewController::class, 'sendTest'])->name('reviews.send-test');

        // Edicao e exclusao de avaliacoes (somente admin)
        Route::put('/avaliacoes/lote/editar', [AdminServiceReviewController::class, 'bulkUpdate'])->name('reviews.bulk-update');
        Route::put('/avaliacoes/{review}', [AdminServiceReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/avaliacoes/lote/excluir', [AdminServiceReviewController::class, 'bulkDestroy'])->name('reviews.bulk-destroy');
        Route::delete('/avaliacoes/{review}', [AdminServiceReviewController::class, 'destroy'])->name('reviews.destroy');

        // Exclusão de registro de relatório (pagamento + usuário vinculado)
        Route::delete('/reports/payments', [ReportsController::class, 'destroyPaymentRecords'])
            ->name('reports.payments.bulk-destroy');
        Route::delete('/reports/payments/{payment}', [ReportsController::class, 'destroyPaymentRecord'])
            ->name('reports.payments.destroy');

        // Dispositivos
        Route::get('/devices', [AdminController::class, 'devices'])->name('devices');

        // Rotas do WhatsApp
        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/', [WhatsappController::class, 'index'])->name('index');
            Route::get('/messages', [WhatsappController::class, 'messages'])->name('messages');
            Route::get('/settings', [WhatsappController::class, 'settings'])->name('settings');
            Route::put('/settings', [WhatsappController::class, 'updateSettings'])->name('settings.update');
            Route::get('/qrcode', [WhatsappController::class, 'getQrCode'])->name('qrcode');
            Route::get('/status', [WhatsappController::class, 'checkStatus'])->name('status');
            Route::post('/disconnect', [WhatsappController::class, 'disconnect'])->name('disconnect');
            Route::post('/send', [WhatsappController::class, 'sendMessage'])->name('send');
            Route::post('/send-pending', [WhatsappController::class, 'sendToPendingPayments'])->name('send-pending');
            Route::post('/resend/{id}', [WhatsappController::class, 'resendMessage'])->name('resend');
        });

        // Pedidos de motoristas
        Route::get('/pedidos-motoristas', [AdminDriverRequestController::class, 'index'])->name('driver-requests.index');
        Route::patch('/pedidos-motoristas/{driverRequest}/aprovar', [AdminDriverRequestController::class, 'approve'])->name('driver-requests.approve');
        Route::patch('/pedidos-motoristas/{driverRequest}/rejeitar', [AdminDriverRequestController::class, 'reject'])->name('driver-requests.reject');

        // Gerenciamento de Usuários
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/users/{id}', [AdminController::class, 'getUserDetails'])->name('users.details');
        Route::post('/users/{id}/disconnect', [AdminController::class, 'disconnectUser'])->name('users.disconnect');
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('users.delete');
        
        // Configurações do Sistema
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        
        // Configurações de API
        Route::get('/api', [AdminController::class, 'apiSettings'])->name('api');
        Route::post('/api/gateway', [AdminController::class, 'updateGateway'])->name('api.update-gateway');
        
        // 🩺 Dashboard de saúde dos 8 MikroTiks (verde/amarelo/vermelho por ônibus)
        Route::get('/mikrotik/saude', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'health'])->name('mikrotik.health');
        Route::get('/mikrotik/saude/json', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'healthJson'])->name('mikrotik.health.json');

        // Painel Remoto Mikrotik
        Route::prefix('mikrotik/remote')->name('mikrotik.remote.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'index'])->name('index');
            Route::get('/status', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'getStatus'])->name('status');
            Route::post('/sync', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'syncNow'])->name('sync');
            Route::post('/liberate', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'liberateMac'])->name('liberate');
            Route::post('/block', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'blockMac'])->name('block');
            Route::post('/edit-expiration', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'editExpiration'])->name('edit-expiration');
            Route::get('/logs', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'getLogs'])->name('logs');
            Route::get('/bypass-logs', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'getBypassLogs'])->name('bypass-logs');
            Route::post('/reset-bypass', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'resetBypass'])->name('reset-bypass');
            Route::post('/block-bypass', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'blockBypass'])->name('block-bypass');
            Route::post('/unblock-bypass', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'unblockBypass'])->name('unblock-bypass');
            Route::get('/buses', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'getBuses'])->name('buses');
            Route::post('/buses/update', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'updateBus'])->name('buses.update');
            Route::post('/buses/locations', [App\Http\Controllers\Admin\MikrotikRemoteController::class, 'updateBusLocations'])->name('buses.locations');
        });
    });
});

