<?php

namespace App\Services;

use App\Models\ServiceReview;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ServiceReviewWhatsappService
{
    protected string $baileysServerUrl;

    public function __construct()
    {
        $this->baileysServerUrl = env('BAILEYS_SERVER_URL', 'http://localhost:3001');
    }

    public function findUserByPhone(?string $phone): ?User
    {
        $cleanPhone = preg_replace('/[^\d]/', '', (string) $phone);

        if ($cleanPhone === '') {
            return null;
        }

        $user = User::where('phone', $cleanPhone)
            ->orderByDesc('updated_at')
            ->first();

        if (! $user) {
            $user = User::where('phone', '55' . $cleanPhone)
                ->orderByDesc('updated_at')
                ->first();
        }

        if (! $user) {
            $user = User::where('phone', 'LIKE', '%' . substr($cleanPhone, -9))
                ->orderByDesc('updated_at')
                ->first();
        }

        return $user;
    }

    public function prepareReviewForUser(User $user, Carbon|string|null $batchDate = null): ServiceReview
    {
        $window = ServiceReview::resolveBatchWindow($batchDate);

        $review = ServiceReview::firstOrNew([
            'batch_date' => $window['batch_date'],
            'user_id' => $user->id,
        ]);

        if (! $review->exists) {
            $review->token = (string) Str::uuid();
        }

        $review->fill([
            'phone' => $user->phone,
            'registration_at' => $user->registered_at,
        ]);

        $review->save();

        return $review;
    }

    public function sendManualTest(string $phone, ?string $recipientName = null, Carbon|string|null $batchDate = null, ?string $email = null): array
    {
        $user = $this->findUserByPhone($phone);

        if ($user) {
            $review = $this->prepareReviewForUser($user, $batchDate);
            $displayName = $user->name ?: ($recipientName ?: 'Passageiro');
            // Se não passou email mas o user tem, usar o do user
            if (!$email && $user->email) {
                $email = $user->email;
            }
        } else {
            $window = ServiceReview::resolveBatchWindow($batchDate);
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);

            $review = ServiceReview::firstOrNew([
                'batch_date' => $window['batch_date'],
                'user_id' => null,
                'phone' => $cleanPhone,
            ]);

            if (! $review->exists) {
                $review->token = (string) Str::uuid();
            }

            $review->fill([
                'registration_at' => now(),
            ]);
            $review->save();

            $displayName = trim((string) $recipientName) !== '' ? trim((string) $recipientName) : 'Passageiro';
        }

        // Resetar bot_state para permitir refazer o teste
        $review->update([
            'bot_state' => null,
            'rating' => null,
            'reason' => null,
            'submitted_at' => null,
        ]);

        // Iniciar conversa com bot (em vez de mandar link)
        $botService = app(\App\Services\ServiceReviewBotService::class);
        $result = $botService->startReviewConversation($review, $displayName);
        $result['matched_user'] = $user;

        // Enviar por email (mantém com link, pois e-mail não tem fluxo conversacional)
        if ($email) {
            try {
                $link = $this->resolveReviewLink($review);
                \Illuminate\Support\Facades\Mail::send([], [], function ($m) use ($email, $displayName, $link) {
                    $m->to($email)
                      ->subject('Avalie sua viagem - WiFi Tocantins Transporte')
                      ->html(
                          '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #E5E5E5;overflow:hidden">'
                        . '<div style="background:linear-gradient(135deg,#007A28,#00A335);padding:24px;text-align:center">'
                        . '<p style="color:#fff;font-size:18px;font-weight:bold;margin:0">🚌 WiFi Tocantins Transporte</p>'
                        . '</div>'
                        . '<div style="padding:24px">'
                        . '<p style="color:#111;font-size:15px">Olá <strong>' . $displayName . '</strong>,</p>'
                        . '<p style="color:#333;font-size:14px">Como foi sua experiência com nosso WiFi? Sua opinião é muito importante!</p>'
                        . '<div style="text-align:center;margin:24px 0">'
                        . '<a href="' . $link . '" style="background:#00A335;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block">Avaliar agora</a>'
                        . '</div>'
                        . '<p style="color:#888;font-size:12px">Leva menos de 1 minuto.</p>'
                        . '</div>'
                        . '<div style="background:#F8F9FA;padding:16px;text-align:center;border-top:1px solid #E5E5E5">'
                        . '<p style="color:#888;font-size:10px;margin:0">© ' . date('Y') . ' Tocantins Transporte WiFi</p>'
                        . '</div></div>'
                      );
                });
                $result['email_sent'] = true;
            } catch (\Exception $e) {
                $result['email_sent'] = false;
                $result['email_error'] = $e->getMessage();
            }
        }

        return $result;
    }

    public function sendPreparedReview(ServiceReview $review, ?string $recipientName = null): array
    {
        // 🧩 Avaliação usa o número SEPARADO (sessão "review"), não o número de PIX.
        if (! WhatsappSetting::isReviewConnected()) {
            return [
                'success' => false,
                'error' => 'WhatsApp de avaliacao nao esta conectado.',
                'review' => $review,
                'link' => $this->resolveReviewLink($review),
            ];
        }

        // Usar bot service para iniciar conversa interativa
        return app(\App\Services\ServiceReviewBotService::class)
            ->startReviewConversation($review, $recipientName);
    }

    public function resolveReviewLink(ServiceReview $review): string
    {
        return route('reviews.show', $review->token);
    }

    protected function buildMessage(ServiceReview $review, ?string $recipientName = null): string
    {
        $name = trim((string) ($recipientName ?: $review->user?->name ?: 'Passageiro'));

        return strtr(WhatsappSetting::getReviewMessageTemplate(), [
            '{nome}' => $name !== '' ? $name : 'Passageiro',
            '{telefone}' => $review->phone ?: ($review->user?->phone ?: '-'),
            '{link}' => $this->resolveReviewLink($review),
            '{data_viagem}' => optional($review->batch_date)->format('d/m/Y') ?: now()->format('d/m/Y'),
        ]);
    }
}