<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ConnectivityProbe;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAIService
{
    /**
     * IA tenta resolver antes de mandar pro humano.
     * Ações possíveis por turno: reply, request_probe, escalate.
     */

    public const ACTIONS = ['reply', 'request_probe', 'escalate'];

    public function isEnabled(): bool
    {
        return (bool) config('services.together.enabled')
            && (bool) config('services.together.api_key');
    }

    /**
     * Se admin já entrou na conversa, IA sai de cena.
     * Também respeita flag ai_disabled no metadata da conversa (se houver).
     * E limita número de turnos automáticos pra não ficar em loop.
     */
    public function shouldRespond(ChatConversation $conv): bool
    {
        if (!$this->isEnabled()) {
            Log::info('🤖 IA desabilitada (flag ou api_key ausente)', [
                'enabled' => config('services.together.enabled'),
                'has_key' => (bool) config('services.together.api_key'),
            ]);
            return false;
        }
        if ($conv->status === 'closed') {
            Log::info('🤖 Conversa fechada, IA não responde', ['conv' => $conv->id]);
            return false;
        }

        $humanAdminReplied = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'admin')
            ->whereNotNull('admin_id')
            ->exists();

        if ($humanAdminReplied) {
            Log::info('🤖 Humano já entrou na conversa, IA cede controle', ['conv' => $conv->id]);
            return false;
        }

        $aiTurns = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'admin')
            ->whereNull('admin_id')
            ->count();

        if ($aiTurns >= (int) config('services.together.max_turns', 6)) {
            Log::info('🤖 Limite de turnos atingido', ['conv' => $conv->id, 'turns' => $aiTurns]);
            return false;
        }

        Log::info('🤖 IA vai responder', ['conv' => $conv->id, 'ai_turns_so_far' => $aiTurns]);
        return true;
    }

    /**
     * Processa último turno do visitante e executa a ação escolhida.
     * Retorna a ChatMessage criada (ou null em falha total).
     */
    public function respond(ChatConversation $conv): ?ChatMessage
    {
        try {
            $messages = $this->buildMessages($conv);
            $decision = $this->callApi($messages);

            if (!$decision || !in_array($decision['action'] ?? null, self::ACTIONS, true)) {
                // Falhou: escala silenciosamente pra humano
                return $this->escalateFallback($conv, 'IA não retornou decisão válida');
            }

            return $this->executeAction($conv, $decision);
        } catch (\Throwable $e) {
            Log::warning('🤖 Falha na IA do chat', [
                'conversation_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);
            return $this->escalateFallback($conv, 'Erro na chamada da IA');
        }
    }

    // ---------- Contexto ----------

    private function buildSystemPrompt(ChatConversation $conv): string
    {
        $linked = $conv->linked_user;
        $now = now()->format('d/m/Y H:i');

        $userStatus = "SEM CADASTRO — visitante nunca pagou ou usa MAC diferente do cadastro.";
        if ($linked) {
            $isActive = in_array($linked->status, ['connected', 'active', 'temp_bypass'])
                && $linked->expires_at && $linked->expires_at->isFuture();

            $userStatus = $isActive
                ? "ACESSO ATIVO — pagou e expira em {$linked->expires_at->format('d/m H:i')} ({$linked->expires_at->diffForHumans()})."
                : "CADASTRO EXPIRADO — último acesso expirou {$linked->expires_at?->diffForHumans()}. Precisa pagar de novo.";

            if ($linked->mac_address && $conv->visitor_mac
                && strtoupper(trim($conv->visitor_mac)) !== strtoupper(trim($linked->mac_address))) {
                $userStatus .= " ATENÇÃO: MAC do chat ({$conv->visitor_mac}) é diferente do MAC do cadastro ({$linked->mac_address}) — pode ser randomização do celular.";
            }
        }

        $bus = $conv->linked_bus_name ?? 'desconhecido';
        $mac = $conv->visitor_mac ?: 'não capturado';
        $ip = $conv->visitor_ip ?: 'não capturado';
        $phone = $conv->visitor_phone ?: 'não informado';

        $pendingProbe = ConnectivityProbe::where('conversation_id', $conv->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        $lastProbe = ConnectivityProbe::where('conversation_id', $conv->id)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        $probeStatus = 'nenhum teste foi enviado ainda nesta conversa';
        if ($pendingProbe) {
            $probeStatus = '⚠️ JÁ EXISTE UM TESTE AGUARDANDO RESPOSTA — não peça outro. Oriente o usuário a clicar no botão que apareceu acima.';
        } elseif ($lastProbe) {
            $v = $lastProbe->verdict;
            $r = $lastProbe->results ?? [];
            $dl = isset($r['download_mbps']) ? number_format($r['download_mbps'], 1) . ' Mbps' : 'n/d';
            $lt = isset($r['latency_ms']) ? round($r['latency_ms']) . ' ms' : 'n/d';
            $probeStatus = "Último teste concluído: veredicto={$v}, download={$dl}, latência={$lt}, DNS=" . ($r['dns_ok'] ?? false ? 'OK' : 'falhou') . ", Google=" . ($r['google_ok'] ?? false ? 'OK' : 'falhou') . ". Você pode pedir um novo teste se necessário.";
        }

        return <<<PROMPT
Você é o assistente virtual da **Tocantins Transporte WiFi**, serviço de internet em ônibus via Starlink (8 ônibus rodando na região de Tocantins, Brasil). Você atende o chat de suporte antes de passar pro humano.

# Como o serviço funciona
- Usuário conecta no WiFi do ônibus, é redirecionado pro portal, paga via PIX (R$ avulso, libera 12 horas corridas).
- Após pagar, o acesso é liberado automaticamente em até 15 segundos.
- Se o usuário muda de ônibus, o acesso continua válido — todos os 8 ônibus recebem a lista de MACs ativos.
- Randomização de MAC pode causar problema: se o celular gerou um MAC novo (iOS/Android recente), o sistema não reconhece o acesso antigo — ele precisa abrir o portal de novo, que detecta via cookie e reassocia.

# Contexto deste visitante (IMPORTANTE)
- **Nome:** {$conv->visitor_name}
- **Telefone:** {$phone}
- **MAC do dispositivo atual:** {$mac}
- **IP atual:** {$ip}
- **Ônibus detectado:** {$bus}
- **Status do cadastro:** {$userStatus}
- **Status de teste de conexão:** {$probeStatus}
- **Horário agora:** {$now}

# Sua missão
Tentar resolver sozinho antes de escalar. Responder sempre em **português brasileiro**, tom amigável e direto, sem gírias exageradas. Respostas curtas (1-3 frases). Nada de emojis demais — no máximo 1 por mensagem.

# Ações disponíveis (você escolhe UMA por turno)
1. **reply** — respostar em texto normal. Use para cumprimentar, tirar dúvida simples, orientar, confirmar algo.
2. **request_probe** — pede pro usuário rodar um teste de conexão. O sistema já envia um link automático no chat com um botão. Use quando o usuário reclamar de lentidão, "não tem internet", "tá travando", OU quando tiver acesso ativo mas estiver reclamando de problema. Não pode pedir 2 probes na mesma conversa.
3. **escalate** — passa pra humano. Use quando: (a) usuário pediu atendente/humano, (b) é problema de cobrança/reembolso/reclamação, (c) você já tentou responder 2 vezes e ele continua confuso, (d) envolve coisa que exige ação humana (cadastro manual, ajuste de plano, problema legal).

# Padrões de resposta por situação
- **Usuário com ACESSO ATIVO reclamando que não funciona:** peça probe. Exemplo: "Seu acesso está ativo até X. Vou mandar um teste rápido pra entender o que tá acontecendo."
- **Usuário SEM CADASTRO perguntando como pagar:** explique que precisa abrir o portal do WiFi no navegador e pagar via PIX, 12h de acesso.
- **Usuário com CADASTRO EXPIRADO:** diga que o acesso já acabou, precisa pagar de novo no portal.
- **Usuário afirma que "pagou" mas o cadastro está expirado ou não existe:** escale para humano — pode ser pagamento recém-feito que não liberou, ou PIX em outro número. Não fique insistindo que ele não pagou.
- **ATENÇÃO: randomização de MAC:** se o MAC do chat difere do MAC do cadastro e o cara reclama que não funciona, peça pra ele fechar o WiFi e abrir de novo (ou conectar e desconectar), pra o portal detectar via cookie.
- **Pedido de atendente humano:** escale sem resistência, não tenta convencer.
- **Pergunta fora de escopo (fofoca, sobre outro serviço, jurídico):** escale.

# Formato de saída — OBRIGATÓRIO
Responda APENAS com um JSON válido, sem markdown, sem ```json, sem nenhum texto antes ou depois:
{"action":"reply","message":"texto que o usuário vai ler"}
ou
{"action":"request_probe","message":"Vou mandar um teste rápido pra entender seu sinal, aguenta 15 segundos só."}
ou
{"action":"escalate","message":"Vou chamar um atendente humano agora. Aguarde um instante que já te respondem."}

Não invente campos, não coloque explicação fora do JSON, não use "```". Apenas o JSON cru.
PROMPT;
    }

    private function buildMessages(ChatConversation $conv): array
    {
        $system = $this->buildSystemPrompt($conv);

        // Últimas 10 mensagens pra dar contexto
        $history = ChatMessage::where('conversation_id', $conv->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $arr = [['role' => 'system', 'content' => $system]];

        foreach ($history as $msg) {
            $type = $msg->type ?? 'text';
            if ($msg->sender_type === 'visitor') {
                $arr[] = ['role' => 'user', 'content' => (string) $msg->message];
            } else {
                // admin (humano ou IA) conta como assistant
                $prefix = '';
                if ($type === 'probe_request') $prefix = '[sistema: enviou probe] ';
                elseif ($type === 'probe_result') {
                    $verdict = $msg->metadata['verdict'] ?? 'n/d';
                    $prefix = "[resultado do teste: {$verdict}] ";
                }
                $arr[] = ['role' => 'assistant', 'content' => $prefix . (string) $msg->message];
            }
        }

        return $arr;
    }

    // ---------- Together.ai ----------

    private function callApi(array $messages): ?array
    {
        $http = Http::timeout((int) config('services.together.timeout', 15))
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.together.api_key'),
                'Content-Type' => 'application/json',
            ]);

        if (!config('services.together.verify_ssl', true)) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post(config('services.together.api_url'), [
                'model' => config('services.together.model'),
                'messages' => $messages,
                'max_tokens' => 400,
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            Log::warning('🤖 Together API falhou', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (!$content) return null;

        // Remove possíveis cercas de código que o modelo insista em colocar
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            Log::warning('🤖 JSON da IA inválido', ['raw' => substr($content, 0, 300)]);
            return null;
        }

        return $decoded;
    }

    // ---------- Execução das ações ----------

    private function executeAction(ChatConversation $conv, array $decision): ChatMessage
    {
        $action = $decision['action'];
        $text = trim((string) ($decision['message'] ?? ''));
        if ($text === '') $text = 'Estou processando, um instante.';
        if (mb_strlen($text) > 800) $text = mb_substr($text, 0, 800);

        return match ($action) {
            'request_probe' => $this->actionProbe($conv, $text),
            'escalate' => $this->actionEscalate($conv, $text, $decision['reason'] ?? null),
            default => $this->actionReply($conv, $text),
        };
    }

    private function actionReply(ChatConversation $conv, string $text): ChatMessage
    {
        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'admin',
            'admin_id' => null,
            'type' => 'text',
            'message' => $text,
            'metadata' => ['ai' => true, 'model' => config('services.together.model')],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        Log::info('🤖 IA respondeu', ['conversation_id' => $conv->id]);
        return $msg;
    }

    private function actionProbe(ChatConversation $conv, string $text): ChatMessage
    {
        // Só bloqueia se já existe probe PENDENTE (não expirado, não concluído).
        // Se o anterior foi completado ou expirou, pode mandar outro.
        $pendingProbe = ConnectivityProbe::where('conversation_id', $conv->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pendingProbe) {
            return $this->actionReply($conv, 'Já tem um teste aberto acima — clica no botão pra rodar, por favor.');
        }

        $probe = ConnectivityProbe::create([
            'token' => ConnectivityProbe::generateToken(),
            'conversation_id' => $conv->id,
            'created_by_admin_id' => null,
            'target_mac' => $conv->visitor_mac,
            'target_phone' => $conv->visitor_phone,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $probeUrl = route('diagnostico.show', ['token' => $probe->token]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'admin',
            'admin_id' => null,
            'type' => 'probe_request',
            'message' => $text ?: '🔍 Vou rodar um teste rápido. Toque no botão — leva uns 15 segundos.',
            'metadata' => [
                'ai' => true,
                'model' => config('services.together.model'),
                'probe_id' => $probe->id,
                'probe_token' => $probe->token,
                'probe_url' => $probeUrl,
                'expires_at' => $probe->expires_at->toIso8601String(),
            ],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        Log::info('🤖 IA pediu probe', ['conversation_id' => $conv->id, 'probe_id' => $probe->id]);
        return $msg;
    }

    private function actionEscalate(ChatConversation $conv, string $text, ?string $reason): ChatMessage
    {
        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'admin',
            'admin_id' => null,
            'type' => 'text',
            'message' => $text ?: 'Vou chamar um atendente humano agora. Aguarde um instante.',
            'metadata' => [
                'ai' => true,
                'model' => config('services.together.model'),
                'escalated' => true,
                'reason' => $reason,
            ],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'pending',
            'unread_count' => $conv->unread_count + 1,
        ]);

        // Avisa admin humano que tem caso pra pegar
        try {
            app(\App\Services\NtfyService::class)->send(
                "🤖→👤 IA escalou conversa",
                "{$conv->visitor_name} ({$conv->visitor_phone})\n\nMotivo: " . ($reason ?? 'IA sem resolução'),
                'high',
                ['robot', 'warning']
            );
        } catch (\Exception $e) {}

        Log::info('🤖 IA escalou pra humano', [
            'conversation_id' => $conv->id,
            'reason' => $reason,
        ]);

        return $msg;
    }

    private function escalateFallback(ChatConversation $conv, string $reason): ChatMessage
    {
        return $this->actionEscalate(
            $conv,
            'Vou chamar um atendente humano pra te ajudar. Aguarde um instante.',
            $reason
        );
    }
}
