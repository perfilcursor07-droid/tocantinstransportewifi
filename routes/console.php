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

// Scheduler: Envio automático de mensagens WhatsApp a cada 5 minutos
Schedule::command('whatsapp:send-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/whatsapp-auto-send.log'));

// Scheduler: Envio do link de avaliacao diariamente as 07:00
Schedule::command('reviews:send-whatsapp')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reviews-whatsapp-send.log'));

// Scheduler: Gravar snapshot de saúde dos MikroTiks a cada 5 minutos
Schedule::command('bus:record-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Scheduler: Lembrar clientes que geraram PIX e não pagaram após 15 minutos
Schedule::command('payments:send-unpaid-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/unpaid-reminders.log'));
