<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatApiController extends Controller
{
    public function __construct(private ChatAIService $ai) {}

    /**
     * Dispara a IA para responder à última mensagem do visitante.
     * Se ela conseguir responder/escalar, retorna a mensagem criada pra devolver no payload.
     * Silencioso em falha: quem chama continua o fluxo normal (notificação ntfy, etc).
     */
    private function maybeRunAI(ChatConversation $conv): ?ChatMessage
    {
        \Log::info('🤖 maybeRunAI chamado', ['conv' => $conv->id]);
        $conv->refresh();
        if (!$this->ai->shouldRespond($conv)) return null;
        $result = $this->ai->respond($conv);
        \Log::info('🤖 maybeRunAI resultado', ['conv' => $conv->id, 'created_msg_id' => $result?->id]);
        return $result;
    }

    public function startConversation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'message' => 'required|string|max:2000',
        ]);

        // Verificar se já existe conversa ativa com esse telefone (evita duplicatas)
        $conversation = ChatConversation::where('visitor_phone', $request->phone)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        $sessionId = $conversation ? $conversation->session_id : Str::uuid()->toString();

        if (!$conversation) {
            $conversation = ChatConversation::create([
                'visitor_name' => $request->name,
                'visitor_phone' => $request->phone,
                'visitor_email' => $request->email,
                'visitor_ip' => $request->ip(),
                'visitor_mac' => $request->mac ?? null,
                'session_id' => $sessionId,
                'status' => 'pending',
                'last_message_at' => now(),
                'unread_count' => 0,
            ]);
        } else {
            // Atualizar MAC e IP se a conversa já existia (pode ter mudado)
            $updateData = ['visitor_ip' => $request->ip()];
            if ($request->mac) {
                $updateData['visitor_mac'] = $request->mac;
            }
            $conversation->update($updateData);
        }

        // Verificar se já existe essa mensagem (evita duplicatas no retry)
        $existingMessage = ChatMessage::where('conversation_id', $conversation->id)
            ->where('message', $request->message)
            ->where('sender_type', 'visitor')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        $aiReply = null;

        if (!$existingMessage) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'visitor',
                'message' => $request->message,
                'is_read' => false,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'unread_count' => $conversation->unread_count + 1,
            ]);

            // 🤖 IA tenta responder antes do humano
            $aiReply = $this->maybeRunAI($conversation);

            // Só notifica ntfy se IA não respondeu (escalate já tem ntfy próprio)
            if (!$aiReply) {
                try {
                    app(\App\Services\NtfyService::class)->send(
                        "Nova mensagem no chat",
                        "{$request->name}\n{$request->phone}\n\n{$request->message}",
                        'high',
                        ['speech_balloon', 'incoming_envelope']
                    );
                } catch (\Exception $e) {}
            }
        } else {
            $message = $existingMessage;
        }

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'session_id' => $sessionId,
            'message' => $message,
            'ai_reply' => $aiReply,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        $conversation = ChatConversation::where('session_id', $request->session_id)->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversa não encontrada. Inicie uma nova conversa.',
            ], 404);
        }

        // Verificar se conversa foi encerrada
        if ($conversation->status === 'closed') {
            return response()->json([
                'success' => false,
                'closed' => true,
                'error' => 'Esta conversa foi encerrada.',
            ]);
        }

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'visitor',
            'message' => $request->message,
            'is_read' => false,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        // 🤖 IA tenta responder antes do humano
        $aiReply = $this->maybeRunAI($conversation);

        if (!$aiReply) {
            try {
                $visitorName = $conversation->visitor_name ?: 'Visitante';
                $visitorPhone = $conversation->visitor_phone ?: '';
                app(\App\Services\NtfyService::class)->send(
                    "Mensagem de {$visitorName}",
                    "{$visitorPhone}\n\n{$request->message}",
                    'high',
                    ['speech_balloon']
                );
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'ai_reply' => $aiReply,
        ]);
    }

    public function getMessages(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $conversation = ChatConversation::where('session_id', $request->session_id)->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversa não encontrada.',
            ], 404);
        }

        $messages = $conversation->messages()
            ->with('admin:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        // Marcar mensagens do admin como lidas
        $conversation->messages()
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'status' => $conversation->status,
        ]);
    }

    public function checkNewMessages(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'last_id' => 'nullable|integer',
        ]);

        $conversation = ChatConversation::where('session_id', $request->session_id)->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'has_new' => false,
            ]);
        }

        // Verificar se conversa foi encerrada
        if ($conversation->status === 'closed') {
            return response()->json([
                'success' => true,
                'closed' => true,
                'has_new' => false,
                'messages' => [],
            ]);
        }

        $query = $conversation->messages()
            ->where('sender_type', 'admin')
            ->with('admin:id,name');

        if ($request->last_id) {
            $query->where('id', '>', $request->last_id);
        }

        $newMessages = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'closed' => false,
            'has_new' => $newMessages->count() > 0,
            'messages' => $newMessages,
            'status' => $conversation->status,
        ]);
    }
}
