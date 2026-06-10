<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente centralizado para o servidor Baileys (Node).
 *
 * Centraliza:
 *  - A URL base (BAILEYS_SERVER_URL)
 *  - A API key obrigatória (BAILEYS_API_KEY) enviada no header X-API-Key,
 *    para que ninguém que alcance a porta 3001 consiga disparar mensagens.
 *
 * Use sempre este client em vez de chamar Http::post('.../send') direto.
 */
class WhatsappClient
{
    /**
     * URL base do servidor Baileys (sem barra final).
     */
    public static function baseUrl(): string
    {
        return rtrim(env('BAILEYS_SERVER_URL', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/');
    }

    /**
     * API key configurada (ou null se ainda não foi definida).
     */
    public static function apiKey(): ?string
    {
        $key = env('BAILEYS_API_KEY');

        return ($key !== null && $key !== '') ? (string) $key : null;
    }

    /**
     * Devolve uma requisição HTTP já configurada com timeout e API key.
     */
    public static function http(int $timeout = 30): PendingRequest
    {
        $request = Http::timeout($timeout)->acceptJson();

        if ($key = self::apiKey()) {
            $request = $request->withHeaders(['X-API-Key' => $key]);
        }

        return $request;
    }

    /**
     * Envia uma mensagem de texto.
     *
     * @param array<string,mixed> $options ex.: ['session' => 'review', 'priority' => true, 'skipCheck' => true]
     */
    public static function send(string $phone, string $message, array $options = [], int $timeout = 30)
    {
        $payload = array_merge([
            'phone' => $phone,
            'message' => $message,
        ], $options);

        return self::http($timeout)->post(self::baseUrl() . '/send', $payload);
    }

    /**
     * Envia um documento.
     *
     * @param array<string,mixed> $options
     */
    public static function sendDocument(string $phone, string $documentUrl, ?string $fileName = null, string $caption = '', array $options = [], int $timeout = 60)
    {
        $payload = array_merge([
            'phone' => $phone,
            'documentUrl' => $documentUrl,
            'fileName' => $fileName,
            'caption' => $caption,
        ], $options);

        return self::http($timeout)->post(self::baseUrl() . '/send-document', $payload);
    }
}
