<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappPendingMessages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'whatsapp:send-pending {--force : Forçar envio mesmo se auto_send estiver desabilitado}';

    /**
     * The console command description.
     */
    protected $description = 'Envia mensagens WhatsApp automaticamente para pagamentos pendentes';

    /**
     * URL do servidor Baileys
     */
    protected $baileysServerUrl;

    public function __construct()
    {
        parent::__construct();
        $this->baileysServerUrl = env('BAILEYS_SERVER_URL', 'http://localhost:3001');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ⚠️ DEPRECATED: substituído por payments:send-unpaid-reminders
        // (que tem mensagem melhor + libera 3min de bypass).
        // Retorna sem fazer nada para evitar enviar a mensagem antiga.
        $this->warn('Comando deprecated. Use payments:send-unpaid-reminders.');
        Log::info('⚠️ whatsapp:send-pending foi chamado mas está deprecated — use payments:send-unpaid-reminders');
        return 0;

        $this->info("Buscando pagamentos pendentes há mais de {$pendingMinutes} minutos...");

        // IDs de usuários que já pagaram nas últimas 24 horas
        $paidUserIds = Payment::where('status', 'completed')
            ->where('paid_at', '>=', Carbon::now()->subHours(24))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Buscar pagamentos pendentes do dia
        $pendingPayments = Payment::where('status', 'pending')
            ->where('created_at', '<=', Carbon::now()->subMinutes($pendingMinutes))
            ->whereDate('created_at', Carbon::today())
            ->whereNotIn('user_id', $paidUserIds)
            ->whereHas('user', function($q) {
                $q->whereNotNull('phone')
                  ->where('phone', '!=', '');
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id');

        $this->info("Encontrados {$pendingPayments->count()} pagamentos pendentes.");

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingPayments as $payment) {
            // Verificar se já enviou mensagem para este usuário nas últimas 24 horas
            $alreadySent = WhatsappMessage::where('user_id', $payment->user_id)
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->exists();

            if ($alreadySent) {
                $skipped++;
                continue;
            }

            $phone = WhatsappMessage::formatPhone($payment->user->phone);
            
            // Personalizar mensagem
            $message = str_replace(
                ['{nome}', '{valor}', '{telefone}'],
                [$payment->user->name ?? 'Cliente', number_format($payment->amount, 2, ',', '.'), $payment->user->phone],
                $messageTemplate
            );

            // Criar registro
            $whatsappMessage = WhatsappMessage::create([
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'phone' => $phone,
                'message' => $message,
                'status' => 'pending',
            ]);

            try {
                $response = Http::timeout(30)->post($this->baileysServerUrl . '/send', [
                    'phone' => $phone,
                    'message' => $message,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $whatsappMessage->markAsSent($data['messageId'] ?? null);
                    $sent++;
                    $this->line("  ✓ Enviado para {$phone}");
                } else {
                    $whatsappMessage->markAsFailed($response->body());
                    $failed++;
                    $this->error("  ✗ Falha ao enviar para {$phone}");
                }

                // Delay entre mensagens
                usleep(500000); // 0.5 segundos
            } catch (\Exception $e) {
                $whatsappMessage->markAsFailed($e->getMessage());
                $failed++;
                $this->error("  ✗ Erro: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Resumo: {$sent} enviadas, {$failed} falhas, {$skipped} ignoradas (já enviadas)");

        Log::info("WhatsApp Auto-Send: {$sent} enviadas, {$failed} falhas, {$skipped} ignoradas");

        return 0;
    }
}
