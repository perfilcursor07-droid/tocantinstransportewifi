<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $conversations = ChatConversation::with(['messages' => function($q) {
            $q->latest()->limit(1);
        }])
        ->orderByDesc('last_message_at')
        ->paginate(20);

        $stats = [
            'total' => ChatConversation::count(),
            'active' => ChatConversation::where('status', 'active')->count(),
            'pending' => ChatConversation::where('status', 'pending')->count(),
            'unread' => ChatConversation::where('unread_count', '>', 0)->count(),
        ];

        return view('admin.chat.index', compact('conversations', 'stats'));
    }

    public function show($id)
    {
        $conversation = ChatConversation::with(['messages.admin'])->findOrFail($id);
        
        // Marcar mensagens como lidas
        $conversation->messages()
            ->where('sender_type', 'visitor')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        $conversation->update(['unread_count' => 0]);

        return view('admin.chat.show', compact('conversation'));
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $conversation = ChatConversation::findOrFail($id);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'admin_id' => Auth::id(),
            'message' => $request->message,
            'is_read' => true,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'active',
            'admin_id' => Auth::id(),
        ]);

        if ($request->ajax() || $request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $message->load('admin'),
            ]);
        }

        return back()->with('success', 'Mensagem enviada!');
    }

    public function close($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $conversation->update(['status' => 'closed']);

        return back()->with('success', 'Conversa encerrada!');
    }

    public function destroy($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $conversation->delete();

        return redirect()->route('admin.chat.index')->with('success', 'Conversa excluída!');
    }

    /**
     * Exclui o cadastro do usuário vinculado (mesmo de /admin/users)
     * para permitir cadastrar de novo.
     */
    public function deleteLinkedUser($id)
    {
        if (Auth::user()?->role !== 'admin') {
            return back()->with('error', 'Apenas administradores podem excluir o cadastro do usuário.');
        }

        $conversation = ChatConversation::findOrFail($id);
        $user = $conversation->linked_user;

        if (!$user) {
            return back()->with('error', 'Nenhum cadastro encontrado para este visitante.');
        }

        if (in_array($user->role, ['admin', 'manager'], true)) {
            return back()->with('error', 'Não é possível excluir usuários administrativos.');
        }

        $user->sessions()->where('session_status', 'active')->update([
            'session_status' => 'ended',
            'ended_at' => now(),
        ]);

        $user->delete();

        return back()->with('success', 'Cadastro excluído. O passageiro pode se cadastrar de novo no portal.');
    }

    public function getMessages($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $messages = $conversation->messages()
            ->with('admin')
            ->orderBy('created_at', 'asc')
            ->get();

        // Marcar como lidas
        $conversation->messages()
            ->where('sender_type', 'visitor')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'conversation' => $conversation,
        ]);
    }

    public function getUnreadCount()
    {
        $count = ChatConversation::where('unread_count', '>', 0)->count();
        
        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }
}
