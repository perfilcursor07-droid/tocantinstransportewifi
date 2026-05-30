<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\TempBypassLog;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lembrete automático para clientes que geraram QR Code PIX mas não pagaram.
 *
 * Fluxo:
 * 1. Busca pagamentos pendentes há 5+ minutos sem lembrete enviado
 * 2. Para cada um, libera 3 minutos de bypass no MikroTik (pra ele conseguir
 *    abrir o WhatsApp e o portal)
 * 3. Envia 2 mensagens WhatsApp: (a) aviso de pagamento não identificado +
 *    (b) o código PIX copia-e-cola separado
 * 4. Marca payment.unpaid_reminder_sent_at pra não enviar de novo
 *
 * Esse comando NÃO afeta o fluxo de pagamento normal — só age depois de 5min
 * que o usuário gerou o QR Code e não pagou.
 */
class SendUnpaidPaymentReminders extends Command
{
    protected $signature = 'payments:send-unpaid-reminders';

    protected $description = 'Envia lembretes WhatsApp para clientes que geraram PIX e não pagaram após 5 minutos';

    public function handle(): int
    {
        // Verificar se a feature está habilitada nas configs
        $enabled = \App\Models\SystemSetting::getValue('unpaid_reminder_enabled', '1');
        if (!$enabled) {
            $this->info('Lembretes de pagamento pendente desabilitados nas configurações.');
            return 0;
        }

        if (!WhatsappSetting::isConnected()) {
            $this->warn('WhatsApp não conectado. Pulando.');
            return 0;
        }

        // Buscar pagamentos:
        // - Status pendente
        // - Criado há mais de 5 minutos
        // - Criado há menos de 6 horas (não enviar pra QRs muito antigos)
        // - Sem lembrete enviado ainda
        $payments = Payment::with('user')
            ->where('status', 'pending')
            ->whereNull('unpaid_reminder_sent_at')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where('created_at', '>=', now()->subHours(6))
            ->orderBy('created_at')
            ->limit(50) // Processar em lote pra não travar
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

            // 1. Libera 3 minutos de bypass para o cara abrir o WhatsApp/portal
            $this->grantTempBypass($user, $payment);

            // 2. Envia mensagem (2 partes: aviso + código PIX)
            $ok = $this->sendReminderMessage($user, $payment);

            // 🛡️ SEMPRE marca como enviado, mesmo se falhar — evita reenvio infinito
            // pro mesmo número (ex: número inválido tentando a cada 5 min).
            $this->markReminderSent($payment);

            if ($ok) {
                Cache::put($cacheKey, true, now()->addHours(24));
                $sent++;
            } else {
                $failed++;
            }

            // Pequena pausa pra não floodar
            usleep(500000); // 0.5s
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

    /**
     * Libera bypass temporário de 3 minutos para o usuário poder abrir o WhatsApp e o portal.
     * Não conta no limite de bypasses regulares (não persiste em cache de bypass count).
     */
    protected function grantTempBypass(User $user, Payment $payment): void
    {
        try {
            // Não rebaixar quem já está conectado
            if (in_array($user->status, ['connected', 'active'])) {
                return;
            }

            $expiresAt = now()->addMinutes(3);
            $user->update([
                'status' => 'temp_bypass',
                'expires_at' => $expiresAt,
            ]);

            // Registrar no log de bypass
            TempBypassLog::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'mac_address' => $user->mac_address,
                'phone' => $user->phone,
                'ip_address' => $user->ip_address,
                'bypass_number' => 0,
                'expires_at' => $expiresAt,
                'was_denied' => false,
                'deny_reason' => 'unpaid_reminder_15min',
            ]);

            // Forçar próximo sync do MikroTik a pegar esse bypass
            Cache::forget('mikrotik_sync_lists_all');

            Log::info('🏦 Bypass de 3min liberado para lembrete de pagamento pendente', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'mac' => $user->mac_address,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Erro ao liberar bypass para lembrete', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia o lembrete via WhatsApp em 2 mensagens separadas:
     *  1) Aviso de pagamento não identificado + instrução
     *  2) O código PIX (copia e cola) sozinho, fácil de copiar
     */
    protected function sendReminderMessage(User $user, Payment $payment): bool
    {
        try {
            $phone = WhatsappMessage::formatPhone($user->phone);
            $name = $user->name ? trim(explode(' ', $user->name)[0]) : null;
            $amount = number_format((float) $payment->amount, 2, ',', '.');
            $pixCode = $payment->pix_emv_string;

            // Sem código PIX não dá pra ajudar — pula
            if (!$pixCode) {
                Log::warning('⚠️ Lembrete sem código PIX disponível', ['payment_id' => $payment->id]);
                return false;
            }

            $greeting = $name ? "Oi {$name}!" : 'Oi!';

            // ----- MENSAGEM 1: aviso -----
            $message1 = "{$greeting} 👋\n\n"
                . "Vi aqui que você gerou um PIX de *R\$ {$amount}* mas o pagamento *ainda não foi identificado*.\n\n"
                . "🟢 *Liberei sua internet por 3 minutos* pra você conseguir finalizar o pagamento agora.\n\n"
                . "👇 *Copie o código PIX na próxima mensagem* e cole no app do seu banco. Assim que pagar, sua internet é liberada por 12 horas automaticamente.\n\n"
                . "Qualquer dúvida, é só responder por aqui que eu te ajudo. 💚";

            $baileysUrl = env('BAILEYS_SERVER_URL', 'http://localhost:3001');

            $msg1 = WhatsappMessage::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'phone' => $phone,
                'message' => $message1,
                'status' => 'pending',
            ]);

            $resp1 = Http::timeout(15)->post($baileysUrl . '/send', [
                'phone' => $phone,
                'message' => $message1,
            ]);

            if (!$resp1->successful()) {
                $msg1->markAsFailed($resp1->body());
                return false;
            }
            $msg1->markAsSent($resp1->json('messageId'));

            // Pequena pausa pra manter a ordem das mensagens
            sleep(2);

            // ----- MENSAGEM 2: só o código PIX (fácil de copiar) -----
            $msg2 = WhatsappMessage::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'phone' => $phone,
                'message' => $pixCode,
                'status' => 'pending',
            ]);

            $resp2 = Http::timeout(15)->post($baileysUrl . '/send', [
                'phone' => $phone,
                'message' => $pixCode,
            ]);

            if ($resp2->successful()) {
                $msg2->markAsSent($resp2->json('messageId'));
                Log::info('📱 Lembrete de pagamento pendente enviado (2 msgs)', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'phone' => $phone,
                ]);
                return true;
            }

            $msg2->markAsFailed($resp2->body());
            // A msg 1 já foi, mas o código falhou — considera falha parcial
            return false;
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
