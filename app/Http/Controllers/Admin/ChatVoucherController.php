<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatVoucherController extends Controller
{
    /**
     * Admin cria um voucher de cortesia de 12h direto pela conversa do chat.
     * Gera código único, aplica os dados do visitante e injeta mensagem na conversa.
     */
    public function createFromChat(Request $request, int $conversationId)
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        $phone = preg_replace('/\D/', '', $conversation->visitor_phone ?? '');
        $name = $conversation->visitor_name ?: 'Cliente';

        do {
            $code = $this->generateVoucherCode();
        } while (Voucher::where('code', $code)->exists());

        $voucher = Voucher::create([
            'code' => $code,
            'driver_name' => $name,
            'driver_document' => null,
            'driver_phone' => $phone,
            'daily_hours' => 12,
            'daily_hours_used' => 0,
            'activation_interval_hours' => 12,
            'expires_at' => now()->addDay(),
            'voucher_type' => 'limited',
            'description' => 'Cortesia — gerado no chat (' . ($conversation->visitor_email ?: 'sem email') . ')',
            'is_active' => true,
        ]);

        $activateUrl = rtrim(config('app.url', 'https://www.tocantinstransportewifi.com.br'), '/') . '/voucher/ativar';

        $text = "🎁 Voucher de cortesia gerado! Código: {$code} — vale 12 horas de internet.";

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'admin_id' => Auth::id(),
            'type' => 'voucher_offer',
            'message' => $text,
            'metadata' => [
                'voucher_id' => $voucher->id,
                'voucher_code' => $code,
                'voucher_hours' => 12,
                'activate_url' => $activateUrl,
                'expires_at' => $voucher->expires_at->toIso8601String(),
            ],
            'is_read' => true,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'active',
            'admin_id' => Auth::id(),
        ]);

        Log::info('🎁 Voucher gerado via chat', [
            'voucher_id' => $voucher->id,
            'code' => $code,
            'conversation_id' => $conversation->id,
            'admin_id' => Auth::id(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'voucher' => $voucher,
                'message' => $message->load('admin'),
                'activate_url' => $activateUrl,
            ]);
        }

        return back()->with('success', "Voucher {$code} criado e enviado no chat.");
    }

    private function generateVoucherCode(): string
    {
        return 'WIFI-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
    }
}
