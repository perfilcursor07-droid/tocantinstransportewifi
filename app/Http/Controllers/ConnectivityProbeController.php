<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ConnectivityProbe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConnectivityProbeController extends Controller
{
    /**
     * Admin gera um probe a partir de uma conversa do chat.
     * Cria o registro, injeta mensagem do tipo 'probe_request' na conversa
     * com o link pro usuário clicar.
     */
    public function createFromChat(Request $request, int $conversationId)
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        $probe = ConnectivityProbe::create([
            'token' => ConnectivityProbe::generateToken(),
            'conversation_id' => $conversation->id,
            'created_by_admin_id' => Auth::id(),
            'target_mac' => $conversation->visitor_mac,
            'target_phone' => $conversation->visitor_phone,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $probeUrl = route('diagnostico.show', ['token' => $probe->token]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'admin_id' => Auth::id(),
            'type' => 'probe_request',
            'message' => "🔍 Solicitei um teste da sua conexão. Toque no botão abaixo — leva uns 15 segundos e me ajuda a entender o que está acontecendo.",
            'metadata' => [
                'probe_id' => $probe->id,
                'probe_token' => $probe->token,
                'probe_url' => $probeUrl,
                'expires_at' => $probe->expires_at->toIso8601String(),
            ],
            'is_read' => true,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'active',
            'admin_id' => Auth::id(),
        ]);

        Log::info('📡 Probe de diagnóstico criado', [
            'probe_id' => $probe->id,
            'conversation_id' => $conversation->id,
            'admin_id' => Auth::id(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'probe' => $probe,
                'message' => $message->load('admin'),
                'probe_url' => $probeUrl,
            ]);
        }

        return back()->with('success', 'Teste de conexão solicitado no chat.');
    }

    /**
     * Página pública que o usuário abre via link no chat.
     * Roda os 5 testes via JS e submete em /api/diagnostico/{token}/report.
     */
    public function show(string $token)
    {
        $probe = ConnectivityProbe::where('token', $token)->first();

        if (!$probe) {
            return response()->view('diagnostico.invalid', ['reason' => 'not_found'], 404);
        }

        if ($probe->isExpired()) {
            return response()->view('diagnostico.invalid', ['reason' => 'expired'], 410);
        }

        if ($probe->isCompleted()) {
            return response()->view('diagnostico.done', ['probe' => $probe]);
        }

        return view('diagnostico.show', ['probe' => $probe]);
    }

    /**
     * Endpoint que recebe o arquivo de download usado no teste de velocidade.
     * Devolve 512KB de dados estáticos (binário com entropia pra não comprimir).
     */
    public function downloadPayload()
    {
        $size = 512 * 1024; // 512KB
        $data = random_bytes($size);
        return response($data, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Probe-Payload' => 'download',
        ]);
    }

    /**
     * Ping ultra-leve pra medir latência.
     */
    public function ping(Request $request)
    {
        return response()->json(['pong' => true, 't' => microtime(true)]);
    }

    /**
     * Submissão dos resultados do teste (público, autenticado pelo token).
     * Grava resultados, injeta mensagem 'probe_result' na conversa, conclui probe.
     */
    public function report(Request $request, string $token)
    {
        $probe = ConnectivityProbe::where('token', $token)->first();

        if (!$probe) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if ($probe->isExpired()) {
            return response()->json(['success' => false, 'error' => 'expired'], 410);
        }

        if ($probe->isCompleted()) {
            return response()->json(['success' => false, 'error' => 'already_completed'], 409);
        }

        $validated = $request->validate([
            'dns_ok' => 'required|boolean',
            'google_ok' => 'required|boolean',
            'laravel_ok' => 'required|boolean',
            'download_mbps' => 'nullable|numeric|min:0|max:10000',
            'download_ms' => 'nullable|numeric|min:0|max:120000',
            'latency_ms' => 'nullable|numeric|min:0|max:60000',
            'latency_samples' => 'nullable|array',
            'client_ts' => 'nullable|numeric',
            'screen' => 'nullable|string|max:30',
            'connection_type' => 'nullable|string|max:40',
        ]);

        $probe->update([
            'status' => 'completed',
            'results' => $validated,
            'client_ip' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'completed_at' => now(),
        ]);

        // Injeta mensagem na conversa (se tiver)
        if ($probe->conversation_id) {
            $verdict = $probe->verdict;
            $labels = [
                'excellent' => ['label' => 'Conexão excelente', 'icon' => '✅'],
                'good' => ['label' => 'Conexão aceitável', 'icon' => '✅'],
                'poor' => ['label' => 'Conexão ruim', 'icon' => '⚠️'],
                'failed' => ['label' => 'Sem internet detectada', 'icon' => '❌'],
            ];
            $info = $labels[$verdict] ?? $labels['failed'];

            ChatMessage::create([
                'conversation_id' => $probe->conversation_id,
                'sender_type' => 'visitor',
                'type' => 'probe_result',
                'message' => "{$info['icon']} Teste concluído — {$info['label']}",
                'metadata' => [
                    'probe_id' => $probe->id,
                    'probe_token' => $probe->token,
                    'verdict' => $verdict,
                    'results' => $validated,
                    'client_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
                'is_read' => false,
            ]);

            $conversation = ChatConversation::find($probe->conversation_id);
            if ($conversation) {
                $conversation->update([
                    'last_message_at' => now(),
                    'unread_count' => \DB::raw('unread_count + 1'),
                ]);

                // 🤖 IA responde automaticamente ao resultado do teste
                // Assim o usuário não precisa falar "fiz o teste e agora?"
                try {
                    $ai = app(\App\Services\ChatAIService::class);
                    if ($ai->shouldRespond($conversation)) {
                        $ai->respond($conversation);
                    }
                } catch (\Exception $e) {
                    Log::warning('IA não respondeu ao probe result', ['error' => $e->getMessage()]);
                }
            }
        }

        Log::info('📡 Probe de diagnóstico concluído', [
            'probe_id' => $probe->id,
            'verdict' => $probe->verdict,
            'results' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'verdict' => $probe->verdict,
        ]);
    }
}
