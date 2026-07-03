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
 * 1. Busca pagamentos pendentes há 5+ minutos sem lembrete enviado
 * 2. Envia 2 mensagens WhatsApp: (a) aviso de pagamento não identificado +
 *    (b) o código PIX copia-e-cola separado (chega pelo 4G)
 * 3. Marca payment.unpaid_reminder_sent_at pra não enviar de novo
 *
 * IMPORTANTE: este comando NÃO libera bypass/WiFi. A internet temporária de
 * 3 min só é concedida quando o usuário copia o código PIX no portal.
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

            // ⚠️ NÃO libera mais bypass automático aqui: a internet temporária de
            // 3 min só é liberada quando o usuário clica em "copiar código PIX"
            // no portal. O lembrete é apenas a mensagem WhatsApp (chega pelo 4G).

            // Envia mensagem (2 partes: aviso + código PIX)
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

            // Pequena pausa randomizada pra não parecer disparo automático (anti-ban)
            sleep(rand(6, 12));
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

            // 🔀 Variações de texto (anti-ban): o WhatsApp detecta mensagens idênticas
            // enviadas em massa. Sorteamos uma de 3 versões com o mesmo sentido.
            $variations = [
                "{$greeting} 👋\n\n"
                    . "Vi aqui que você gerou um PIX de *R\$ {$amount}* mas o pagamento *ainda não foi identificado*.\n\n"
                    . "🟢 *Liberei um acesso temporário* pra você conseguir finalizar o pagamento agora.\n\n"
                    . "👇 *Copie o código PIX na próxima mensagem* e cole no app do seu banco. Assim que pagar, sua internet é liberada pelo tempo do plano escolhido.\n\n"
                    . "Qualquer dúvida, é só responder por aqui que eu te ajudo. 💚",

                "{$greeting} 😊\n\n"
                    . "Seu PIX de *R\$ {$amount}* ficou pendente — o pagamento ainda não caiu por aqui.\n\n"
                    . "🟢 Já *liberei alguns minutos de acesso* pra você concluir sem pressa.\n\n"
                    . "👇 É só *copiar o código PIX da próxima mensagem* e pagar no seu banco. A internet libera automaticamente após o pagamento.\n\n"
                    . "Precisando de ajuda, responde aqui. 💚",

                "{$greeting} 🚀\n\n"
                    . "Notei que seu pagamento de *R\$ {$amount}* via PIX ainda não foi confirmado.\n\n"
                    . "🟢 *Soltei um acesso rápido* pra você finalizar agora mesmo.\n\n"
                    . "👇 *Copie o código da próxima mensagem* e cole no app do banco. Pagou, liberou! 🎉\n\n"
                    . "Estou por aqui se precisar. 💚",
            ];

            // Rodapé de descadastro (opt-out) — exigência anti-denúncia para envios proativos.
            $optOutFooter = "\n\n_Não quer mais receber? Responda *PARAR*._";

            $message1 = $variations[array_rand($variations)] . $optOutFooter;

            $msg1 = WhatsappMessage::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'phone' => $phone,
                'message' => $message1,
                'status' => 'pending',
            ]);

            $resp1 = WhatsappClient::send($phone, $message1, [], 15);

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

            $resp2 = WhatsappClient::send($phone, $pixCode, [], 15);

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
