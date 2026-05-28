<?php

namespace App\Services;

use App\Models\ServiceReview;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServiceReviewBotService
{
    protected string $baileysServerUrl;

    public function __construct()
    {
        $this->baileysServerUrl = env('BAILEYS_SERVER_URL', 'http://localhost:3001');
    }

    /**
     * Processa mensagem recebida via WhatsApp.
     * Retorna true se a mensagem foi tratada como resposta de avaliação.
     */
    public function handleIncomingMessage(string $phone, string $message, ?string $lid = null, ?string $pushName = null, ?int $timestamp = null): bool
    {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        $cleanLid = preg_replace('/[^\d]/', '', (string) $lid);
        $message = trim($message);

        if ($message === '') {
            return false;
        }

        // 🛡️ Ignorar mensagens muito curtas ou que claramente não são respostas
        // (mas permitir "1", "2", "3", "4", "5" e palavras curtas válidas)
        if (mb_strlen($message) > 500) {
            return false;
        }

        // Buscar review com bot_state ativo PELO TELEFONE (estrito)
        $review = null;
        if ($cleanPhone !== '') {
            $review = $this->findActiveReview($cleanPhone);
        }

        // Fallback: tentar pelo LID se já gravamos antes
        if (!$review && $cleanLid !== '') {
            $review = ServiceReview::whereIn('bot_state', ['awaiting_rating', 'awaiting_reason'])
                ->where('lid', $cleanLid)
                ->orderByDesc('bot_last_interaction_at')
                ->orderByDesc('id')
                ->first();
        }

        // 🛡️ REMOVIDO o "Fallback 2" agressivo que pegava qualquer review pendente
        // Era o que causava o bug de "o usuário não respondeu mas o bot achou que sim"
        // Se não conseguimos identificar o telefone/LID, NÃO processamos.

        if (!$review) {
            return false;
        }

        // 🛡️ Se review já foi finalizado, NÃO processar de novo
        if ($review->submitted_at !== null || $review->bot_state === 'completed') {
            Log::warning('🛡️ ServiceReviewBot: review já finalizado, ignorando mensagem', [
                'review_id' => $review->id,
                'state' => $review->bot_state,
                'submitted_at' => $review->submitted_at?->toIso8601String(),
            ]);
            return false;
        }

        // 🛡️ VALIDAÇÃO: o telefone do review tem que bater com o telefone da mensagem
        // (últimos 8 dígitos — ignora código país e dígito 9 variável)
        if ($cleanPhone !== '' && $review->phone) {
            $reviewPhone = preg_replace('/[^\d]/', '', $review->phone);
            $last8Incoming = substr($cleanPhone, -8);
            $last8Review = substr($reviewPhone, -8);
            if ($last8Incoming !== $last8Review) {
                Log::warning('🛡️ ServiceReviewBot: telefone da mensagem não bate com o do review', [
                    'review_id' => $review->id,
                    'review_phone' => $reviewPhone,
                    'message_phone' => $cleanPhone,
                    'last8_incoming' => $last8Incoming,
                    'last8_review' => $last8Review,
                ]);
                return false;
            }
        }

        // 🛡️ Se temos timestamp da mensagem, validar que é POSTERIOR ao envio do review
        // (evita processar mensagens antigas que o Baileys pode mandar em sync histórico)
        if ($timestamp && $review->invited_at) {
            $messageTime = \Carbon\Carbon::createFromTimestamp($timestamp);
            if ($messageTime->lt($review->invited_at->copy()->subMinutes(1))) {
                Log::warning('🛡️ ServiceReviewBot: mensagem com timestamp ANTERIOR ao envio do review — ignorando', [
                    'review_id' => $review->id,
                    'invited_at' => $review->invited_at->toIso8601String(),
                    'message_time' => $messageTime->toIso8601String(),
                ]);
                return false;
            }
        }

        // Considera abandono após 6 horas sem resposta
        if ($review->bot_last_interaction_at && $review->bot_last_interaction_at->lt(now()->subHours(6))) {
            $review->update(['bot_state' => 'completed']);
            return false;
        }

        Log::info('🤖 ServiceReviewBot: mensagem recebida', [
            'review_id' => $review->id,
            'state' => $review->bot_state,
            'phone' => $cleanPhone,
            'lid' => $cleanLid,
            'message' => mb_substr($message, 0, 100),
            'timestamp' => $timestamp,
        ]);

        return match ($review->bot_state) {
            'awaiting_rating' => $this->handleRatingAnswer($review, $message),
            'awaiting_reason' => $this->handleReasonAnswer($review, $message),
            default => false,
        };
    }

    /**
     * Busca review aguardando resposta para esse telefone
     */
    protected function findActiveReview(string $cleanPhone): ?ServiceReview
    {
        // Strip código de país se presente
        $withoutCountry = $cleanPhone;
        if (str_starts_with($cleanPhone, '55') && strlen($cleanPhone) >= 12) {
            $withoutCountry = substr($cleanPhone, 2); // Remove 55
        }

        // Tentar com o telefone exato primeiro, depois variações
        $candidates = [
            $cleanPhone,             // 556281015484
            $withoutCountry,         // 6281015484
            '55' . $withoutCountry,  // 556281015484
            '55' . '9' . substr($withoutCountry, 2), // 55 + 9 + 81015484 (se DDD sem 9)
            substr($withoutCountry, 0, 2) . '9' . substr($withoutCountry, 2), // DDD + 9 + resto
        ];

        // Últimos 8 dígitos (ignora código país + DDD + possível dígito 9 extra)
        // Isso cobre o caso "62981015484" (banco) vs "556281015484" (incoming)
        $last8 = strlen($cleanPhone) >= 8 ? substr($cleanPhone, -8) : '';

        $candidates = array_values(array_unique(array_filter($candidates)));

        return ServiceReview::whereIn('bot_state', ['awaiting_rating', 'awaiting_reason'])
            ->where(function ($q) use ($candidates, $last8) {
                foreach ($candidates as $c) {
                    $q->orWhere('phone', $c);
                }
                // Busca pelos últimos 8 dígitos (mais flexível que 9)
                if ($last8) {
                    $q->orWhere('phone', 'LIKE', '%' . $last8);
                }
            })
            ->orderByDesc('bot_last_interaction_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Processa nota recebida (1-5)
     */
    protected function handleRatingAnswer(ServiceReview $review, string $message): bool
    {
        $rating = $this->extractRating($message);

        if ($rating === null) {
            // Não entendeu — pede de novo
            $this->sendMessage(
                $review->phone,
                "Não consegui entender 😅\n\nResponda apenas com um número de *1 a 5* pra avaliar a viagem:\n\n5 = Excelente\n4 = Boa\n3 = Regular\n2 = Ruim\n1 = Péssima"
            );
            $review->update(['bot_last_interaction_at' => now()]);
            return true;
        }

        $review->update([
            'rating' => $rating,
            'bot_last_interaction_at' => now(),
        ]);

        if ($rating < 4) {
            // Nota baixa — perguntar motivo
            $review->update(['bot_state' => 'awaiting_reason']);
            $this->sendMessage(
                $review->phone,
                "Obrigado pela nota *{$rating}* ⭐\n\nMe conta o que aconteceu pra gente melhorar? Pode escrever em uma mensagem só."
            );
        } else {
            // Nota alta — finalizar
            $review->update([
                'bot_state' => 'completed',
                'submitted_at' => now(),
            ]);
            $stars = str_repeat('⭐', $rating);
            $this->sendMessage(
                $review->phone,
                "Show! Avaliação *{$stars}* registrada 💚\n\nObrigado por viajar com a Tocantins Transporte! Boa viagem da próxima vez 🚌✨"
            );
        }

        return true;
    }

    /**
     * Processa motivo recebido (texto livre)
     */
    protected function handleReasonAnswer(ServiceReview $review, string $message): bool
    {
        // Limitar tamanho do motivo
        $reason = mb_substr(trim($message), 0, 1000);

        $review->update([
            'reason' => $reason,
            'bot_state' => 'completed',
            'submitted_at' => now(),
            'bot_last_interaction_at' => now(),
        ]);

        $this->sendMessage(
            $review->phone,
            "Recebido 💚\n\nObrigado pelo retorno! Vamos analisar com cuidado e trabalhar para melhorar. Boa viagem da próxima vez 🚌"
        );

        return true;
    }

    /**
     * Extrai nota (1-5) de uma mensagem livre
     */
    protected function extractRating(string $message): ?int
    {
        // Remover acentos e normalizar
        $msg = mb_strtolower($message);

        // Procurar dígito 1-5 isolado ou no início
        if (preg_match('/(?:^|\s|\D)([1-5])(?:\s|$|\.|,|⭐|estrela)/u', ' ' . $msg . ' ', $matches)) {
            return (int) $matches[1];
        }

        // Contar emojis de estrela
        $stars = mb_substr_count($msg, '⭐') + mb_substr_count($msg, '★');
        if ($stars >= 1 && $stars <= 5) {
            return $stars;
        }

        // Palavras → nota
        $wordsMap = [
            'cinco' => 5, 'excelente' => 5, 'otimo' => 5, 'ótimo' => 5, 'perfeita' => 5, 'perfeito' => 5,
            'quatro' => 4, 'boa' => 4, 'bom' => 4,
            'tres' => 3, 'três' => 3, 'regular' => 3, 'normal' => 3, 'media' => 3, 'média' => 3,
            'dois' => 2, 'ruim' => 2,
            'uma' => 1, 'um' => 1, 'pessima' => 1, 'péssima' => 1, 'horrivel' => 1, 'horrível' => 1,
        ];

        foreach ($wordsMap as $word => $rating) {
            if (str_contains($msg, $word)) {
                return $rating;
            }
        }

        // Apenas dígito puro
        if (preg_match('/^\s*([1-5])\s*$/', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Envia mensagem inicial pedindo avaliação (substitui o link)
     */
    public function startReviewConversation(ServiceReview $review, ?string $recipientName = null): array
    {
        // 🧩 Avaliação usa o número SEPARADO (sessão "review"), nunca o número de PIX.
        if (!WhatsappSetting::isReviewConnected()) {
            return [
                'success' => false,
                'error' => 'WhatsApp de avaliação não está conectado.',
            ];
        }

        $phone = WhatsappMessage::formatPhone($review->phone ?: $review->user?->phone);
        $digits = preg_replace('/[^\d]/', '', (string) $phone);

        if (strlen($digits) < 12) {
            $review->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error_message' => 'Telefone inválido.',
            ]);
            return ['success' => false, 'error' => 'Telefone inválido.'];
        }

        $name = trim((string) ($recipientName ?: $review->user?->name ?: 'Passageiro'));
        $name = $name !== '' ? $name : 'Passageiro';

        $message = $this->buildInitialMessage($name);

        $whatsappMessage = WhatsappMessage::create([
            'user_id' => $review->user_id,
            'phone' => $phone,
            'message' => $message,
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(30)->post($this->baileysServerUrl . '/send', [
                'phone' => $phone,
                'message' => $message,
                'session' => 'review', // 🧩 disparo de avaliação sai pelo número separado
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $whatsappMessage->markAsSent($data['messageId'] ?? null);

                $review->update([
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'whatsapp_status' => 'sent',
                    'whatsapp_error_message' => null,
                    'invited_at' => now(),
                    'bot_state' => 'awaiting_rating',
                    'bot_last_interaction_at' => now(),
                ]);

                return [
                    'success' => true,
                    'review' => $review->fresh(),
                    'whatsapp_message' => $whatsappMessage->fresh(),
                ];
            }

            $errorMessage = $response->body();
            $whatsappMessage->markAsFailed($errorMessage);
            $review->update([
                'whatsapp_message_id' => $whatsappMessage->id,
                'whatsapp_status' => 'failed',
                'whatsapp_error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'review' => $review->fresh(),
            ];
        } catch (\Throwable $e) {
            $whatsappMessage->markAsFailed($e->getMessage());
            $review->update([
                'whatsapp_message_id' => $whatsappMessage->id,
                'whatsapp_status' => 'failed',
                'whatsapp_error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'review' => $review->fresh(),
            ];
        }
    }

    /**
     * Monta a mensagem inicial — curta e persuasiva, sem link
     */
    protected function buildInitialMessage(string $name): string
    {
        $template = WhatsappSetting::getReviewMessageTemplate();

        return strtr($template, [
            '{nome}' => $name,
            '{data_viagem}' => now()->format('d/m/Y'),
        ]);
    }

    /**
     * Envia mensagem direta via Baileys
     */
    protected function sendMessage(string $phone, string $message): bool
    {
        try {
            $formatted = WhatsappMessage::formatPhone($phone);

            WhatsappMessage::create([
                'phone' => $formatted,
                'message' => $message,
                'status' => 'pending',
            ]);

            $response = Http::timeout(15)->post($this->baileysServerUrl . '/send', [
                'phone' => $formatted,
                'message' => $message,
                'session' => 'review', // 🧩 conversa de avaliação sai pelo número separado
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('🤖 ServiceReviewBot: falha ao enviar mensagem', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
