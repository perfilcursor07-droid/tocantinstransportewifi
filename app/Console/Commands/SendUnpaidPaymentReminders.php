<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappOptOut;
use App\Models\WhatsappSetting;
use App\Services\WhatsappClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lembrete automático para clientes que geraram QR Code PIX mas não pagaram.
 *
 * Fluxo:
 * 1. Busca pagamentos pendentes há pelo menos 15 minutos sem lembrete enviado
 * 2. Envia uma única atualização ao passageiro que autorizou esse contato
 * 3. Marca payment.unpaid_reminder_sent_at pra não enviar de novo
 *
 * IMPORTANTE: o PIX expira em poucos minutos, então este comando não envia
 * código PIX antigo. Ele orienta o passageiro a retornar ao portal para gerar
 * uma cobrança válida, se ainda desejar pagar.
 *
 * Esse comando NÃO afeta o fluxo de pagamento normal — só age depois de 15min
 * que o usuário gerou o QR Code e não pagou.
 */
class SendUnpaidPaymentReminders extends Command
{
    protected $signature = 'payments:send-unpaid-reminders';

    protected $description = 'Envia um lembrete WhatsApp autorizado para PIX pendente após 15 minutos';

    public function handle(): int
    {
        // Verificar se a feature está habilitada nas configs
        $enabled = \App\Models\SystemSetting::getValue('unpaid_reminder_enabled', '0');
        if (!$enabled) {
            $this->info('Lembretes de pagamento pendente desabilitados nas configurações.');
            return 0;
        }

        if (!WhatsappSetting::isConnected() || !WhatsappSetting::isAutoSendEnabled()) {
            $this->warn('Envio automático do WhatsApp está desabilitado ou desconectado. Pulando.');
            return 0;
        }

        // A interface administrativa não permite configurar menos de 15 min.
        // Um lembrete imediato tende a surpreender o passageiro e aumenta os
        // bloqueios/denúncias sem ajudar: o PIX original já pode ter expirado.
        $pendingMinutes = max(15, WhatsappSetting::getPendingMinutes());

        // Buscar pagamentos:
        // - Status pendente
        // - Criado há pelo menos 15 minutos
        // - Criado há menos de 6 horas (não enviar pra QRs muito antigos)
        // - Sem lembrete enviado ainda
        $payments = Payment::with('user')
            ->where('status', 'pending')
            ->whereNull('unpaid_reminder_sent_at')
            ->where('created_at', '<=', now()->subMinutes($pendingMinutes))
            ->where('created_at', '>=', now()->subHours(6))
            ->orderBy('created_at')
            ->limit(10) // Lote pequeno: somente notificações esperadas e autorizadas
            ->get();

        if ($payments->isEmpty()) {
            $this->info('Nenhum pagamento pendente para enviar lembrete.');
            return 0;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            /** @var Payment $payment */
            $user = $payment->user;

            // Validações básicas
            if (!$user) {
                $this->markReminderSent($payment, 'usuário não encontrado');
                $skipped++;
                continue;
            }

            if (!$user->phone || strlen(preg_replace('/[^\d]/', '', $user->phone)) < 10) {
                $this->markReminderSent($payment, 'sem telefone válido');
                $skipped++;
                continue;
            }

            // A política do WhatsApp exige consentimento explícito antes de um
            // contato iniciado pela empresa. Usuários antigos, sem registro de
            // consentimento, não recebem disparo automático.
            if (!$user->hasWhatsappPaymentOptIn()) {
                $this->markReminderSent($payment, 'sem autorização explícita para atualizações de pagamento');
                $skipped++;
                continue;
            }

            // 🛡️ Respeita descadastro (opt-out): quem pediu "PARAR" não recebe mais lembretes.
            if (WhatsappOptOut::isOptedOut($user->phone)) {
                $this->markReminderSent($payment, 'telefone descadastrado (opt-out)');
                $skipped++;
                continue;
            }

            if (!$user->mac_address) {
                $this->markReminderSent($payment, 'sem MAC');
                $skipped++;
                continue;
            }

            // Não enviar se o usuário já tem acesso ativo (pagou outro pagamento)
            if (in_array($user->status, ['connected', 'active']) && $user->expires_at && $user->expires_at->isFuture()) {
                $this->markReminderSent($payment, 'usuário já tem acesso ativo');
                $skipped++;
                continue;
            }

            // Não enviar mais de uma vez por dia para o mesmo telefone
            $cleanPhone = preg_replace('/[^\d]/', '', $user->phone);
            $cacheKey = 'unpaid_reminder_phone_' . $cleanPhone;
            if (Cache::has($cacheKey)) {
                $this->markReminderSent($payment, 'telefone já recebeu lembrete hoje');
                $skipped++;
                continue;
            }

            // Um único lembrete objetivo. Nunca enviamos o código PIX porque,
            // neste momento, ele pode estar vencido e gerar mais frustração.
            $ok = $this->sendReminderMessage($user, $payment);

            // 🛡️ SEMPRE marca como enviado, mesmo se falhar — evita reenvio infinito
            // pro mesmo número (ex: número inválido tentando a cada 5 min).
            $this->markReminderSent($payment);

            // 🛡️ Trava diária SEMPRE gravada (mesmo com falha no envio): antes,
            // falha no WhatsApp fazia o cron reprocessar o telefone sem parar.
            Cache::put($cacheKey, true, now()->addHours(24));

            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }

        }

        $this->info("Lembretes: {$sent} enviados, {$skipped} pulados, {$failed} falharam.");

        Log::info('💰 Lembretes de pagamento pendente processados', [
            'total' => $payments->count(),
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return 0;
    }

    /** Envia uma única atualização útil de pagamento pendente. */
    protected function sendReminderMessage(User $user, Payment $payment): bool
    {
        try {
            $phone = WhatsappMessage::formatPhone($user->phone);
            $name = $user->name ? trim(explode(' ', $user->name)[0]) : null;
            $amount = number_format((float) $payment->amount, 2, ',', '.');
            $greeting = $name ? "Oi {$name}!" : 'Oi!';

            $portalUrl = rtrim(config('app.url'), '/') . '/';
            $message = "{$greeting}\n\n"
                . "Você autorizou receber atualizações deste pagamento. O PIX de *R\$ {$amount}* ainda não foi confirmado.\n\n"
                . "Se ainda quiser conectar à internet, abra o portal e gere um novo PIX:\n{$portalUrl}\n\n"
                . "Se você já pagou, ignore esta mensagem — a confirmação é automática.\n\n"
                . "_Para parar estas mensagens, responda *PARAR*._";

            $whatsappMessage = WhatsappMessage::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'phone' => $phone,
                'message' => $message,
                'status' => 'pending',
            ]);

            $response = WhatsappClient::send($phone, $message, [], 15);

            if (!$response->successful()) {
                $whatsappMessage->markAsFailed($response->body());
                return false;
            }
            $whatsappMessage->markAsSent($response->json('messageId'));
            Log::info('📱 Lembrete de pagamento pendente autorizado enviado', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'phone' => $phone,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('❌ Erro ao enviar lembrete WhatsApp', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Marca o lembrete como enviado (ou pulado) para não tentar de novo
     */
    protected function markReminderSent(Payment $payment, ?string $reason = null): void
    {
        $payment->update(['unpaid_reminder_sent_at' => now()]);
    }
}
