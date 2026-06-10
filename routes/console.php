<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\DebugQrCode;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registrar comando de debug do QR Code
Artisan::command('debug:qrcode {--test-payment : Criar um pagamento de teste}', function () {
    $command = new DebugQrCode();
    $command->setLaravel($this->laravel);
    return $command->handle();
})->purpose('Debug da geração de QR Code PIX');

// Scheduler: Reconciliar pagamentos a cada minuto
// 1. Confirma PIX pago no PagBank cujo webhook se perdeu (consulta a API)
// 2. Reativa usuários com pagamento 'completed' mas sem acesso liberado
Schedule::command('payments:reconcile')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/payments-reconcile.log'));

// Scheduler: Lembrar clientes que geraram PIX e não pagaram após 15 minutos
// (substitui o antigo whatsapp:send-pending — agora libera 3 min de bypass + mensagem melhor)
Schedule::command('payments:send-unpaid-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/unpaid-reminders.log'));

// Scheduler: Envio do link de avaliacao diariamente as 08:05
// (precisa ser >= 08:00 porque o comando só dispara WhatsApp na janela 08h-20h)
Schedule::command('reviews:send-whatsapp')
    ->dailyAt('08:05')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reviews-whatsapp-send.log'));

// Scheduler: Gravar snapshot de saúde dos MikroTiks a cada 5 minutos
Schedule::command('bus:record-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
