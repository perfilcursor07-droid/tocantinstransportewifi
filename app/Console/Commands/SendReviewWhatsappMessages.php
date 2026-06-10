<?php

namespace App\Console\Commands;

use App\Models\ServiceReview;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Services\ServiceReviewWhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReviewWhatsappMessages extends Command
{
    protected $signature = 'reviews:send-whatsapp
                            {--date= : Data de referencia do lote no formato YYYY-MM-DD}
                            {--force : Forcar envio mesmo se o toggle estiver desabilitado}
                            {--batch=8 : Quantidade de mensagens por lote}
                            {--pause=30 : Pausa em minutos entre lotes}
                            {--max=200 : Teto de mensagens WhatsApp neste run (anti-ban)}
                            {--prefer-email : Para quem TEM email, enviar so por email (poupa o numero do WhatsApp)}
                            {--day-and-night : Ignorar a janela de horario comercial (08h-20h)}';

    protected $description = 'Envia links de avaliacao via WhatsApp e Email para passageiros (envio pausado para evitar ban)';

    public function handle(ServiceReviewWhatsappService $reviewWhatsappService): int
    {
        $whatsappEnabled = WhatsappSetting::isReviewAutoSendEnabled();
        $emailEnabled = WhatsappSetting::get('review_email_enabled', 'true') === 'true';
        // 🧩 Avaliação usa o número SEPARADO (sessão "review"), não o número de PIX.
        $whatsappConnected = WhatsappSetting::isReviewConnected();

        if (! $this->option('force') && ! $whatsappEnabled && ! $emailEnabled) {
            $this->info('Envio de avaliacao esta desabilitado (WhatsApp e Email). Use --force para ignorar.');
            return self::SUCCESS;
        }

        if ($whatsappEnabled && ! $whatsappConnected) {
            $this->warn('WhatsApp habilitado mas nao esta conectado. Enviando apenas por email.');
        }

        // 🛡️ ANTI-BAN: só dispara WhatsApp em horario comercial (08h-20h).
        // Disparo de madrugada gera bloqueio/denuncia e queima o numero.
        $hour = (int) now()->format('H');
        $withinBusinessHours = $this->option('day-and-night') || ($hour >= 8 && $hour < 20);
        if ($whatsappEnabled && $whatsappConnected && ! $withinBusinessHours) {
            $this->warn("Fora do horario comercial ({$hour}h). WhatsApp pausado; enviando apenas por email. Use --day-and-night para forcar.");
        }

        // 🛡️ ANTI-BAN (opcional): com --prefer-email, quem TEM email recebe so por email
        // (canal sem risco de ban) e o WhatsApp fica so para quem nao tem email.
        // Por padrao (sem a flag), envia WhatsApp para todos, como antes.
        $preferEmail = (bool) $this->option('prefer-email');
        $maxWhatsapp = max(0, (int) $this->option('max'));

        if (! $whatsappEnabled) {
            $this->info('WhatsApp desabilitado nas configuracoes.');
        }

        if (! $emailEnabled) {
            $this->info('Email desabilitado nas configuracoes.');
        }

        $batchDateOption = $this->option('date');

        try {
            $batchDate = $batchDateOption
                ? Carbon::createFromFormat('Y-m-d', $batchDateOption)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            $this->error('Data invalida. Use o formato YYYY-MM-DD.');
            return self::INVALID;
        }

        $window = ServiceReview::resolveBatchWindow($batchDate);
        $this->info(sprintf(
            'Buscando passageiros cadastrados entre %s e %s...',
            $window['start']->format('d/m/Y H:i'),
            $window['end']->format('d/m/Y H:i')
        ));

        $users = User::query()
            ->whereBetween('registered_at', [$window['start'], $window['end']])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', ['admin', 'manager']);
            })
            ->orderBy('registered_at')
            ->get()
            ->unique('phone'); // Evitar enviar para o mesmo número duas vezes

        $this->info("Encontrados {$users->count()} passageiros elegiveis (sem duplicatas de telefone).");

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $emailSent = 0;
        $sentPhones = [];

        $batchSize = (int) $this->option('batch');    // 8 por padrão
        $pauseMinutes = (int) $this->option('pause'); // 30 min entre lotes
        $messageCount = 0;

        $this->info("Estrategia anti-ban: {$batchSize} msgs por lote, pausa de {$pauseMinutes}min entre lotes.");

        foreach ($users as $user) {
            // Proteção contra duplicatas
            $cleanPhone = preg_replace('/\D/', '', $user->phone);
            if (in_array($cleanPhone, $sentPhones)) {
                $skipped++;
                continue;
            }

            // 🛑 Respeita descadastro (opt-out)
            if (\App\Models\WhatsappOptOut::isOptedOut($user->phone)) {
                $skipped++;
                continue;
            }

            $review = $reviewWhatsappService->prepareReviewForUser($user, $batchDate);

            if ($review->whatsapp_status === 'sent' && !$user->email) {
                $skipped++;
                continue;
            }

            // 🛡️ ANTI-BAN: decidir se este usuário deve receber WhatsApp.
            // Regra: só envia WhatsApp se (a) está dentro do horário comercial,
            // (b) ainda não atingiu o teto do run, e (c) o usuário NÃO tem email
            //     (quem tem email recebe pelo canal seguro) — salvo --whatsapp-with-email.
            $hasEmail = $emailEnabled && ! empty($user->email);
            $allowWhatsapp = $whatsappEnabled
                && $whatsappConnected
                && $withinBusinessHours
                && $sent < $maxWhatsapp
                && (! $preferEmail || ! $hasEmail);

            // Enviar por WhatsApp (se permitido e ainda não enviou)
            $whatsappOk = false;
            if ($allowWhatsapp && $review->whatsapp_status !== 'sent') {
                $result = $reviewWhatsappService->sendPreparedReview($review, $user->name ?: 'Passageiro');
                if ($result['success']) {
                    $sent++;
                    $whatsappOk = true;
                    $sentPhones[] = $cleanPhone;
                    $messageCount++;
                    $this->line("  ✓ WhatsApp enviado para " . ($review->phone ?: $user->phone) . " [{$messageCount}]");
                } else {
                    $failed++;
                    $this->error('  ✗ WhatsApp falhou para ' . ($review->phone ?: $user->phone));
                }
                // Delay entre mensagens individuais (15-25s, parece humano)
                sleep(rand(15, 25));

                // Pausa longa entre lotes para evitar ban
                if ($messageCount > 0 && $messageCount % $batchSize === 0) {
                    $this->warn("  ⏸️  Pausa de {$pauseMinutes} minutos apos {$messageCount} mensagens...");
                    sleep($pauseMinutes * 60);
                    $this->info("  ▶️  Retomando envio...");
                }
            } elseif ($review->whatsapp_status === 'sent') {
                $skipped++;
            }

            // Enviar por Email (se habilitado e tem email)
            if ($emailEnabled && $user->email) {
                try {
                    $link = $reviewWhatsappService->resolveReviewLink($review);
                    $displayName = $user->name ?: 'Passageiro';
                    \Illuminate\Support\Facades\Mail::send([], [], function ($m) use ($user, $displayName, $link) {
                        $m->to($user->email)
                          ->subject('Avalie sua viagem - WiFi Tocantins Transporte')
                          ->html(
                              '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #E5E5E5;overflow:hidden">'
                            . '<div style="background:linear-gradient(135deg,#007A28,#00A335);padding:24px;text-align:center">'
                            . '<p style="color:#fff;font-size:18px;font-weight:bold;margin:0">🚌 WiFi Tocantins Transporte</p>'
                            . '</div>'
                            . '<div style="padding:24px">'
                            . '<p style="color:#111;font-size:15px">Olá <strong>' . $displayName . '</strong>,</p>'
                            . '<p style="color:#333;font-size:14px">Como foi sua experiência com nosso WiFi? Sua opinião é muito importante!</p>'
                            . '<div style="text-align:center;margin:24px 0">'
                            . '<a href="' . $link . '" style="background:#00A335;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block">Avaliar agora</a>'
                            . '</div>'
                            . '<p style="color:#888;font-size:12px">Leva menos de 1 minuto.</p>'
                            . '</div>'
                            . '<div style="background:#F8F9FA;padding:16px;text-align:center;border-top:1px solid #E5E5E5">'
                            . '<p style="color:#888;font-size:10px;margin:0">© ' . date('Y') . ' Tocantins Transporte WiFi</p>'
                            . '</div></div>'
                          );
                    });
                    $emailSent++;
                    $this->line('  📧 Email enviado para ' . $user->email);
                } catch (\Exception $e) {
                    $this->error('  📧 Email falhou para ' . $user->email . ': ' . $e->getMessage());
                }
            }

            // Delay entre emails (2s para não sobrecarregar)
            if ($user->email) {
                usleep(2000000);
            }
        }

        $this->newLine();
        $this->info("Resumo: WhatsApp {$sent} enviados, {$failed} falhas, {$skipped} ignorados | Email {$emailSent} enviados.");

        Log::info('Avaliacao envio finalizado.', [
            'batch_date' => $window['batch_date'],
            'whatsapp_sent' => $sent,
            'whatsapp_failed' => $failed,
            'whatsapp_skipped' => $skipped,
            'email_sent' => $emailSent,
        ]);

        return self::SUCCESS;
    }
}