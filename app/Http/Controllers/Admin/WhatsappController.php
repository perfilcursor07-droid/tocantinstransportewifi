<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsappController extends Controller
{
    /**
     * URL do servidor Node.js Baileys
     */
    protected $baileysServerUrl;

    public function __construct()
    {
        $this->baileysServerUrl = env('BAILEYS_SERVER_URL', 'http://localhost:3001');
    }

    /**
     * Página principal do módulo WhatsApp
     */
    public function index()
    {
        $stats = [
            'total_messages' => WhatsappMessage::count(),
            'sent_messages' => WhatsappMessage::where('status', 'sent')->count(),
            'failed_messages' => WhatsappMessage::where('status', 'failed')->count(),
            'pending_messages' => WhatsappMessage::where('status', 'pending')->count(),
            'today_messages' => WhatsappMessage::whereDate('created_at', today())->count(),
        ];

        $recentMessages = WhatsappMessage::with(['user', 'payment'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $settings = [
            'is_connected' => WhatsappSetting::isConnected(),
            'connection_status' => WhatsappSetting::getConnectionStatus(),
            'connected_phone' => WhatsappSetting::getConnectedPhone(),
            'auto_send_enabled' => WhatsappSetting::isAutoSendEnabled(),
            'pending_minutes' => WhatsappSetting::getPendingMinutes(),
            'message_template' => WhatsappSetting::getMessageTemplate(),
            'qr_code' => WhatsappSetting::getQrCode(),
        ];

        // Buscar pagamentos pendentes há mais de X minutos
        // Excluir usuários que já pagaram (têm pagamento completed nas últimas 24h)
        $pendingMinutes = $settings['pending_minutes'];
        
        // IDs de usuários que já pagaram nas últimas 24 horas
        $paidUserIds = Payment::where('status', 'completed')
            ->where('paid_at', '>=', Carbon::now()->subHours(24))
            ->pluck('user_id')
            ->unique()
            ->toArray();
        
        $pendingPayments = Payment::where('status', 'pending')
            ->where('created_at', '<=', Carbon::now()->subMinutes($pendingMinutes))
            ->whereDate('created_at', Carbon::today()) // Apenas pagamentos do dia
            ->whereNotIn('user_id', $paidUserIds) // Excluir quem já pagou
            ->whereHas('user', function($q) {
                $q->whereNotNull('phone')
                  ->where('phone', '!=', ''); // Garantir que tem telefone válido
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id'); // Mostrar apenas 1 pagamento por usuário

        return view('admin.whatsapp.index', compact('stats', 'recentMessages', 'settings', 'pendingPayments'));
    }

    /**
     * Página de mensagens
     */
    public function messages(Request $request)
    {
        $query = WhatsappMessage::with(['user', 'payment']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $messages = $query->orderBy('created_at', 'desc')->paginate(50);

        return view('admin.whatsapp.messages', compact('messages'));
    }

    /**
     * Página de configurações
     */
    public function settings()
    {
        $settings = [
            'auto_send_enabled' => WhatsappSetting::isAutoSendEnabled(),
            'pending_minutes' => WhatsappSetting::getPendingMinutes(),
            'message_template' => WhatsappSetting::getMessageTemplate(),
        ];

        return view('admin.whatsapp.settings', compact('settings'));
    }

    /**
     * Atualizar configurações
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'pending_minutes' => 'required|integer|min:1|max:1440',
            'message_template' => 'required|string|max:1000',
            'auto_send_enabled' => 'nullable|boolean',
        ]);

        WhatsappSetting::set('pending_minutes', $request->pending_minutes);
        WhatsappSetting::set('message_template', $request->message_template);
        WhatsappSetting::set('auto_send_enabled', $request->has('auto_send_enabled') ? 'true' : 'false');

        return redirect()->back()->with('success', 'Configurações atualizadas com sucesso!');
    }

    /**
     * Obter QR Code para conexão
     */
    public function getQrCode()
    {
        try {
            $response = Http::timeout(30)->get($this->baileysServerUrl . '/qrcode');
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['qrcode'])) {
                    WhatsappSetting::set('last_qr_code', $data['qrcode']);
                    WhatsappSetting::set('connection_status', 'waiting_scan');
                }
                
                return response()->json($data);
            }
            
            return response()->json(['error' => 'Erro ao obter QR Code'], 500);
        } catch (\Exception $e) {
            Log::error('Erro ao obter QR Code: ' . $e->getMessage());
            return response()->json([
                'error' => 'Servidor WhatsApp não está rodando. Execute: node whatsapp-server/server.js',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status da conexão
     */
    public function checkStatus()
    {
        try {
            $response = Http::timeout(10)->get($this->baileysServerUrl . '/status');
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Atualizar status no banco
                WhatsappSetting::updateConnectionStatus(
                    $data['status'] ?? 'disconnected',
                    $data['phone'] ?? null
                );
                
                return response()->json($data);
            }
            
            WhatsappSetting::updateConnectionStatus('disconnected');
            return response()->json(['status' => 'disconnected']);
        } catch (\Exception $e) {
            WhatsappSetting::updateConnectionStatus('disconnected');
            return response()->json([
                'status' => 'disconnected',
                'error' => 'Servidor não disponível'
            ]);
        }
    }

    /**
     * Desconectar WhatsApp
     */
    public function disconnect()
    {
        try {
            $response = Http::timeout(10)->post($this->baileysServerUrl . '/disconnect');
            
            WhatsappSetting::updateConnectionStatus('disconnected');
            WhatsappSetting::set('last_qr_code', null);
            
            return response()->json(['success' => true, 'message' => 'Desconectado com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao desconectar'], 500);
        }
    }

    /**
     * Enviar mensagem para um número específico
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
            'payment_id' => 'nullable|exists:payments,id',
        ]);

        $phone = WhatsappMessage::formatPhone($request->phone);
        
        // Criar registro da mensagem
        $whatsappMessage = WhatsappMessage::create([
            'user_id' => $request->user_id,
            'payment_id' => $request->payment_id,
            'phone' => $phone,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(30)->post($this->baileysServerUrl . '/send', [
                'phone' => $phone,
                'message' => $request->message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $whatsappMessage->markAsSent($data['messageId'] ?? null);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Mensagem enviada com sucesso!',
                    'data' => $whatsappMessage->fresh()
                ]);
            }

            $whatsappMessage->markAsFailed($response->body());
            return response()->json(['error' => 'Falha ao enviar mensagem'], 500);
        } catch (\Exception $e) {
            $whatsappMessage->markAsFailed($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar mensagens para todos os pagamentos pendentes
     */
    public function sendToPendingPayments()
    {
        if (!WhatsappSetting::isConnected()) {
            return response()->json(['error' => 'WhatsApp não está conectado'], 400);
        }

        $pendingMinutes = WhatsappSetting::getPendingMinutes();
        $messageTemplate = WhatsappSetting::getMessageTemplate();

        // IDs de usuários que já pagaram nas últimas 24 horas
        $paidUserIds = Payment::where('status', 'completed')
            ->where('paid_at', '>=', Carbon::now()->subHours(24))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Buscar pagamentos pendentes do dia (excluindo quem já pagou)
        $pendingPayments = Payment::where('status', 'pending')
            ->where('created_at', '<=', Carbon::now()->subMinutes($pendingMinutes))
            ->whereDate('created_at', Carbon::today()) // Apenas pagamentos do dia
            ->whereNotIn('user_id', $paidUserIds) // Excluir quem já pagou
            ->whereHas('user', function($q) {
                $q->whereNotNull('phone')
                  ->where('phone', '!=', '');
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id'); // Apenas 1 por usuário

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingPayments as $payment) {
            // Verificar se já enviou mensagem para este USUÁRIO nas últimas 24 horas
            $alreadySent = WhatsappMessage::where('user_id', $payment->user_id)
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->exists();

            if ($alreadySent) {
                $skipped++;
                continue;
            }

            $phone = WhatsappMessage::formatPhone($payment->user->phone);
            
            // Personalizar mensagem com dados do usuário
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
                } else {
                    $whatsappMessage->markAsFailed($response->body());
                    $failed++;
                }

                // Pequeno delay entre mensagens para evitar bloqueio
                usleep(500000); // 0.5 segundos
            } catch (\Exception $e) {
                $whatsappMessage->markAsFailed($e->getMessage());
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $pendingPayments->count(),
        ]);
    }

    /**
     * Reenviar mensagem com falha
     */
    public function resendMessage($id)
    {
        $message = WhatsappMessage::findOrFail($id);

        if (!WhatsappSetting::isConnected()) {
            return response()->json(['error' => 'WhatsApp não está conectado'], 400);
        }

        try {
            $response = Http::timeout(30)->post($this->baileysServerUrl . '/send', [
                'phone' => $message->phone,
                'message' => $message->message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $message->markAsSent($data['messageId'] ?? null);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Mensagem reenviada com sucesso!'
                ]);
            }

            return response()->json(['error' => 'Falha ao reenviar mensagem'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook para receber atualizações do servidor Baileys
     */
    public function webhook(Request $request)
    {
        $type = $request->input('type');
        $data = $request->input('data');

        Log::info('WhatsApp Webhook', ['type' => $type, 'data' => $data]);

        switch ($type) {
            case 'connection':
                WhatsappSetting::updateConnectionStatus(
                    $data['status'] ?? 'disconnected',
                    $data['phone'] ?? null
                );
                break;

            case 'qr':
                WhatsappSetting::set('last_qr_code', $data['qrcode'] ?? null);
                WhatsappSetting::set('connection_status', 'waiting_scan');
                break;

            case 'message_status':
                if (isset($data['messageId'])) {
                    $message = WhatsappMessage::where('message_id', $data['messageId'])->first();
                    if ($message) {
                        if ($data['status'] === 'delivered') {
                            $message->update([
                                'status' => 'delivered',
                                'delivered_at' => now()
                            ]);
                        } elseif ($data['status'] === 'read') {
                            $message->update([
                                'status' => 'read',
                                'read_at' => now()
                            ]);
                        }
                    }
                }
                break;

            case 'message_in':
                $phone = $data['phone'] ?? '';
                $messageText = $data['message'] ?? '';
                $lid = $data['lid'] ?? null;
                $pushName = $data['pushName'] ?? null;
                
                if ($messageText !== '') {
                    try {
                        $handled = app(\App\Services\ServiceReviewBotService::class)
                            ->handleIncomingMessage($phone, $messageText, $lid, $pushName);
                        
                        Log::info('📩 WhatsApp mensagem recebida', [
                            'phone' => $phone,
                            'lid' => $lid,
                            'pushName' => $pushName,
                            'handled_by_review_bot' => $handled,
                            'message_preview' => mb_substr($messageText, 0, 80),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('❌ Erro ao processar mensagem recebida', [
                            'phone' => $phone,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                break;
        }

        return response()->json(['success' => true]);
    }
}
