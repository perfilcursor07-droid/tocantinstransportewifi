<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyService
{
    protected string $serverUrl;
    protected string $topic;
    protected bool $enabled;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('services.ntfy.server_url', 'https://ntfy.sh'), '/');
        $this->topic = config('services.ntfy.topic', '');
        $this->enabled = config('services.ntfy.enabled', false);
    }

    /**
     * Envia uma notificação push via ntfy.sh
     */
    public function send(string $title, string $message, string $priority = 'default', array $tags = []): bool
    {
        if (!$this->enabled || empty($this->topic)) {
            Log::debug('Ntfy: notificação desabilitada ou tópico não configurado.');
            return false;
        }

        try {
            $headers = [
                'Title' => $title,
                'Priority' => $priority,
            ];

            if (!empty($tags)) {
                $headers['Tags'] = implode(',', $tags);
            }

            // IMPORTANTE: usar withBody() com text/plain para preservar quebras de linha reais.
            // Se passarmos a string direto em ->post(), o Laravel serializa como JSON e o "\n"
            // chega literal no app — quebrando o layout no celular.
            $response = Http::withHeaders($headers)
                ->withBody($message, 'text/plain; charset=utf-8')
                ->post("{$this->serverUrl}/{$this->topic}");

            if ($response->successful()) {
                Log::info("Ntfy: notificação enviada - {$title}");
                return true;
            }

            Log::warning("Ntfy: falha ao enviar notificação", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error("Ntfy: erro ao enviar notificação", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notificação específica de pagamento confirmado.
     * Layout otimizado para o app ntfy no celular:
     *  - Título curto e com valor em destaque (aparece na tela de bloqueio).
     *  - Corpo com uma informação por linha, sem caracteres supérfluos.
     */
    public function notifyPaymentCompleted(string $userName, string $amount, string $method, ?string $busName = null): bool
    {
        $title = "✅ Pagamento R$ {$amount}";

        $lines = [
            "👤 {$userName}",
            "💳 {$method}",
        ];

        if ($busName) {
            $lines[] = "🚌 Ônibus: {$busName}";
        }

        $lines[] = "🕐 " . now()->format('d/m/Y H:i');

        $message = implode("\n", $lines);

        return $this->send($title, $message, 'high', ['white_check_mark', 'moneybag']);
    }

    /**
     * Envia notificação no tópico Starlink (saúde dos MikroTiks).
     * Tópico separado para não poluir o de pagamentos.
     */
    public function sendStarlink(string $title, string $message, string $priority = 'default', array $tags = []): bool
    {
        $starlinkTopic = config('services.ntfy.topic_starlink', '');

        if (!$this->enabled || empty($starlinkTopic)) {
            return false;
        }

        try {
            $headers = [
                'Title' => $title,
                'Priority' => $priority,
            ];

            if (!empty($tags)) {
                $headers['Tags'] = implode(',', $tags);
            }

            $response = Http::withHeaders($headers)
                ->withBody($message, 'text/plain; charset=utf-8')
                ->post("{$this->serverUrl}/{$starlinkTopic}");

            if ($response->successful()) {
                Log::info("Ntfy Starlink: {$title}");
                return true;
            }

            Log::warning("Ntfy Starlink: falha", ['status' => $response->status()]);
            return false;
        } catch (\Throwable $e) {
            Log::error("Ntfy Starlink: erro", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Notifica que um ônibus ficou OFFLINE.
     */
    public function notifyBusOffline(string $busName, string $serial, ?string $ip = null, ?string $location = null): bool
    {
        $title = "🔴 OFFLINE: {$busName}";

        $lines = [
            "🚌 Carro: {$busName}",
            "🔑 Serial: {$serial}",
        ];

        if ($ip) {
            $lines[] = "🌐 IP: {$ip}";
        }
        if ($location) {
            $lines[] = "📍 {$location}";
        }

        $lines[] = "🕐 " . now()->format('d/m/Y H:i');
        $lines[] = "";
        $lines[] = "⚠️ Passageiros NÃO conseguem pagar/conectar neste ônibus.";

        return $this->sendStarlink($title, implode("\n", $lines), 'high', ['red_circle', 'warning']);
    }

    /**
     * Notifica que um ônibus VOLTOU online.
     */
    public function notifyBusOnline(string $busName, string $serial, int $offlineMinutes, ?int $latencyMs = null): bool
    {
        $title = "🟢 ONLINE: {$busName}";

        $durationText = $offlineMinutes >= 60
            ? floor($offlineMinutes / 60) . 'h' . ($offlineMinutes % 60 > 0 ? ($offlineMinutes % 60) . 'min' : '')
            : $offlineMinutes . 'min';

        $lines = [
            "🚌 Carro: {$busName}",
            "🔑 Serial: {$serial}",
            "⏱️ Ficou offline por: {$durationText}",
        ];

        if ($latencyMs !== null) {
            $quality = $latencyMs <= 100 ? 'Rápida' : ($latencyMs <= 300 ? 'Média' : 'Lenta');
            $lines[] = "📶 Latência: {$latencyMs}ms ({$quality})";
        }

        $lines[] = "🕐 " . now()->format('d/m/Y H:i');

        return $this->sendStarlink($title, implode("\n", $lines), 'default', ['green_circle', 'white_check_mark']);
    }

    /**
     * Notifica que a internet de um ônibus está LENTA.
     */
    public function notifyBusSlow(string $busName, string $serial, int $latencyMs): bool
    {
        $title = "🟡 LENTA: {$busName} ({$latencyMs}ms)";

        $lines = [
            "🚌 Carro: {$busName}",
            "🔑 Serial: {$serial}",
            "📶 Latência: {$latencyMs}ms",
            "⚠️ Passageiros podem estar com internet lenta.",
            "🕐 " . now()->format('d/m/Y H:i'),
        ];

        return $this->sendStarlink($title, implode("\n", $lines), 'default', ['yellow_circle', 'snail']);
    }
}
