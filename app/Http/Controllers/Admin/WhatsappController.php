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

    // ==================================================================
    // 🧩 SESSÃO DE AVALIAÇÃO (número SEPARADO) — espelha os métodos acima,
    // mas conversa com a sessão "review" do servidor Baileys e grava as
    // chaves review_* no banco. Um ban aqui NÃO afeta o número de PIX.
    // ==================================================================

    /**
     * Obter QR Code para conectar o número de AVALIAÇÃO.
     */
    public function getReviewQrCode()
    {
        try {
            $response = Http::timeout(30)->get($this->baileysServerUrl . '/qrcode', ['session' => 'review']);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['qrcode'])) {
                    WhatsappSetting::setQrCodeFor('review', $data['qrcode']);
                    WhatsappSetting::updateConnectionStatusFor('review', 'waiting_scan');
                }

                return response()->json($data);
            }

            return response()->json(['error' => 'Erro ao obter QR Code'], 500);
        } catch (\Exception $e) {
            Log::error('Erro ao obter QR Code (review): ' . $e->getMessage());
            return response()->json([
                'error' => 'Servidor WhatsApp não está rodando. Execute: node whatsapp-server/server.js',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status da conexão do número de AVALIAÇÃO.
     */
    public function checkReviewStatus()
    {
        try {
            $response = Http::timeout(10)->get($this->baileysServerUrl . '/status', ['session' => 'review']);

            if ($response->successful()) {
                $data = $response->json();

                WhatsappSetting::updateConnectionStatusFor(
                    'review',
                    $data['status'] ?? 'disconnected',
                    $data['phone'] ?? null
                );

                return response()->json($data);
            }

            WhatsappSetting::updateConnectionStatusFor('review', 'disconnected');
            return response()->json(['status' => 'disconnected']);
        } catch (\Exception $e) {
            WhatsappSetting::updateConnectionStatusFor('review', 'disconnected');
            return response()->json([
                'status' => 'disconnected',
                'error' => 'Servidor não disponível'
            ]);
        }
    }

    /**
     * Desconectar o número de AVALIAÇÃO.
     */
    public function disconnectReview()
    {
        try {
            Http::timeout(10)->post($this->baileysServerUrl . '/disconnect', ['session' => 'review']);

            WhatsappSetting::updateConnectionStatusFor('review', 'disconnected');
            WhatsappSetting::setQrCodeFor('review', null);

            return response()->json(['success' => true, 'message' => 'Número de avaliação desconectado com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao desconectar'], 500);
        }
    }

    /**
     * Página para escanear o QR Code do número de AVALIAÇÃO (sessão separada).
     * Página autocontida — não depende do layout do admin.
     */
    public function reviewConnect()
    {
        $qrUrl = route('admin.whatsapp.review.qrcode');
        $statusUrl = route('admin.whatsapp.review.status');
        $disconnectUrl = route('admin.whatsapp.review.disconnect');
        $csrf = csrf_token();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}">
<title>Conectar WhatsApp de Avaliação</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:24px;display:flex;justify-content:center}
  .card{background:#1e293b;border:1px solid #334155;border-radius:16px;max-width:420px;width:100%;padding:24px;text-align:center}
  h1{font-size:18px;margin:0 0 4px}
  p.sub{color:#94a3b8;font-size:13px;margin:0 0 20px}
  .qr{background:#fff;border-radius:12px;padding:16px;display:inline-block;min-height:240px;min-width:240px;display:flex;align-items:center;justify-content:center}
  .qr img{width:240px;height:240px}
  .status{margin-top:18px;font-size:14px;font-weight:bold}
  .ok{color:#22c55e}.warn{color:#f59e0b}.err{color:#ef4444}
  .badge{display:inline-block;background:#0ea5e9;color:#fff;border-radius:999px;padding:4px 12px;font-size:12px;margin-bottom:16px}
  button{margin-top:18px;background:#ef4444;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-size:13px;cursor:pointer}
  .hint{margin-top:16px;font-size:12px;color:#64748b;line-height:1.5}
</style>
</head>
<body>
<div class="card">
  <div class="badge">📊 Número de AVALIAÇÃO</div>
  <h1>Conectar segundo número</h1>
  <p class="sub">Este número envia SÓ as mensagens de avaliação. Use um chip DIFERENTE do número de PIX.</p>
  <div class="qr" id="qrBox"><span style="color:#000">Carregando…</span></div>
  <div class="status warn" id="status">Aguardando QR Code…</div>
  <button id="btnDisconnect" style="display:none">Desconectar este número</button>
  <div class="hint">No celular do número de avaliação: WhatsApp → Aparelhos conectados → Conectar um aparelho → escaneie o QR acima.</div>
</div>
<script>
const QR_URL='{$qrUrl}', STATUS_URL='{$statusUrl}', DISCONNECT_URL='{$disconnectUrl}';
const token=document.querySelector('meta[name=csrf-token]').content;
const qrBox=document.getElementById('qrBox'), statusEl=document.getElementById('status'), btn=document.getElementById('btnDisconnect');
let connected=false;

function setStatus(text,cls){statusEl.textContent=text;statusEl.className='status '+cls;}

async function loadQr(){
  if(connected)return;
  try{
    const r=await fetch(QR_URL,{headers:{'Accept':'application/json'}});
    const d=await r.json();
    if(d.status==='connected'){onConnected(d.phone);return;}
    if(d.qrcode){qrBox.innerHTML='<img src="'+d.qrcode+'" alt="QR">';setStatus('Escaneie o QR Code com o WhatsApp do número de avaliação','warn');}
    else if(d.error){setStatus(d.error,'err');}
  }catch(e){setStatus('Servidor WhatsApp offline. Inicie: node whatsapp-server/server.js','err');}
}

async function checkStatus(){
  try{
    const r=await fetch(STATUS_URL,{headers:{'Accept':'application/json'}});
    const d=await r.json();
    if(d.status==='connected'){onConnected(d.phone);}
    else if(connected){connected=false;btn.style.display='none';loadQr();}
  }catch(e){}
}

function onConnected(phone){
  connected=true;
  qrBox.innerHTML='<span style="color:#000;font-size:40px">✅</span>';
  setStatus('Conectado'+(phone?(' — '+phone):''),'ok');
  btn.style.display='inline-block';
}

btn.addEventListener('click',async()=>{
  if(!confirm('Desconectar o número de avaliação?'))return;
  await fetch(DISCONNECT_URL,{method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json'}});
  connected=false;btn.style.display='none';qrBox.innerHTML='<span style="color:#000">Carregando…</span>';loadQr();
});

loadQr();
setInterval(()=>{connected?checkStatus():loadQr();},4000);
setInterval(checkStatus,4000);
</script>
</body>
</html>
HTML;

        return response($html);
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
        // 🧩 Sessão de origem: "main" (PIX/transacional) ou "review" (avaliação).
        // Sem o campo (servidor antigo) assume "main" para manter compatibilidade.
        $session = $request->input('session', 'main');

        Log::info('WhatsApp Webhook', ['type' => $type, 'session' => $session, 'data' => $data]);

        switch ($type) {
            case 'connection':
                WhatsappSetting::updateConnectionStatusFor(
                    $session,
                    $data['status'] ?? 'disconnected',
                    $data['phone'] ?? null
                );
                break;

            case 'qr':
                WhatsappSetting::setQrCodeFor($session, $data['qrcode'] ?? null);
                WhatsappSetting::updateConnectionStatusFor($session, 'waiting_scan');
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
                $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : null;
                
                if ($messageText !== '') {
                    try {
                        $handled = app(\App\Services\ServiceReviewBotService::class)
                            ->handleIncomingMessage($phone, $messageText, $lid, $pushName, $timestamp);
                        
                        Log::info('📩 WhatsApp mensagem recebida', [
                            'session' => $session,
                            'phone' => $phone,
                            'lid' => $lid,
                            'pushName' => $pushName,
                            'timestamp' => $timestamp,
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
