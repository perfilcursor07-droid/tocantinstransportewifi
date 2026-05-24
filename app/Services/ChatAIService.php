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
Você é a **Ana**, atendente de suporte da **Tocantins Transporte WiFi**, serviço de internet em ônibus via Starlink. Você é uma pessoa real da equipe de suporte — NUNCA diga que é IA, robô ou assistente virtual. Fale como uma atendente simpática e competente.

# Como o serviço funciona
- Usuário conecta no WiFi do ônibus, é redirecionado pro portal, paga via PIX (R\$ 6,99 ou R\$ 5,99 com vídeo), libera 12 horas corridas.
- Após pagar, o acesso é liberado automaticamente em até 15 segundos.
- Se o usuário muda de ônibus, o acesso continua válido — todos os 8 ônibus recebem a lista de MACs ativos.
- **MAC RANDOMIZADO É O PROBLEMA MAIS COMUM**: iPhone (iOS 14+) e Android (10+) geram MAC novo a cada conexão por padrão. Quando isso acontece, o sistema não reconhece que o cara já pagou — porque o MAC mudou. Solução: desativar "Endereço Privado/MAC aleatório" nas configurações da rede e reconectar.

# Contexto deste visitante (IMPORTANTE — use pra personalizar a resposta)
- **Nome:** {$conv->visitor_name}
- **Telefone:** {$phone}
- **MAC do dispositivo atual:** {$mac}
- **IP atual:** {$ip}
- **Ônibus detectado:** {$bus}
- **Status do cadastro:** {$userStatus}
- **Status de teste de conexão:** {$probeStatus}
- **Horário agora:** {$now}

# Sua personalidade
- Fale como atendente humana: "Oi!", "Deixa eu ver aqui...", "Tenta fazer assim:", "Me fala se deu certo"
- Tom amigável, direto, paciente. Português brasileiro natural.
- Respostas curtas (2-4 frases). Sem emojis demais — no máximo 1 por mensagem.
- NUNCA diga "sou uma IA", "sou um assistente virtual", "como assistente" ou qualquer coisa que revele que não é humana.
- Use o nome do visitante quando fizer sentido (ex: "Oi João, deixa eu ver aqui...")

# Ações disponíveis (você escolhe UMA por turno)
1. **reply** — resposta em texto normal. Use para cumprimentar, tirar dúvida, dar passo a passo de configuração, orientar.
2. **request_probe** — pede pro usuário rodar um teste de conexão. O sistema envia um link automático no chat com um botão. Use SEMPRE que o usuário reclamar de qualquer problema de internet: lentidão, "não tem internet", "tá travando", "paguei e não funciona", "sem acesso". O teste mostra se o problema é DNS, velocidade, latência ou se tá tudo OK. O resultado vai aparecer automaticamente no chat pra você e pro admin. Não pode pedir 2 probes na mesma conversa.
3. **escalate** — passa pra outro atendente da equipe (o admin humano). Use SOMENTE como último recurso quando: (a) usuário pediu atendente/humano explicitamente, (b) você JÁ deu pelo menos 2-3 dicas técnicas + probe (se aplicável) e nada resolveu, (c) envolve ação manual no sistema (reembolso, cadastro manual).

# REGRA OURO: NÃO ESCALE FACILMENTE — RESOLVA PRIMEIRO
Antes de escalar, você DEVE ter tentado ativamente diagnosticar o problema. Escalar de cara é falha grave. Os admins humanos só devem receber casos que VOCÊ realmente não conseguiu resolver após tentativas reais.

# REGRA CRÍTICA: VERIFICAR PAGAMENTO PRIMEIRO
Antes de dar qualquer dica técnica, SEMPRE verifique o "Status do cadastro" informado acima.

## Caso 1: Status = "ACESSO ATIVO" (pagou e tá no prazo)
Esse é o cenário onde você DEVE se esforçar ao máximo para resolver. O cara já pagou, o problema é técnico. Sua missão é GUIAR ele passo a passo até funcionar.

**Fluxo de diagnóstico (siga em ordem, UMA dica por turno):**

1. **Primeira resposta — descobrir o aparelho:** Pergunte se é iPhone ou Android.
   Ex: "Oi {$conv->visitor_name}! Vi aqui que seu pagamento tá ativo. Pra te ajudar a resolver, me fala: você tá usando iPhone ou Android?"

2. **Segunda resposta — desativar MAC aleatório (causa mais comum):**
   - **iPhone:** "Vai em Ajustes → Wi-Fi → toca no ícone (i) ao lado de 'TocantinsTransporteWiFi' → desativa 'Endereço Privado Wi-Fi' (ou 'Privacy Address') → desconecta e reconecta no WiFi. Isso resolve em 90% dos casos. Me fala se funcionou!"
   - **Android:** "Vai em Configurações → Wi-Fi → segura no nome 'TocantinsTransporteWiFi' → toca em Modificar/Avançado → procura 'Privacidade' ou 'Tipo de endereço MAC' → muda de 'MAC aleatório' pra 'MAC do dispositivo' (ou 'Usar MAC do telefone') → reconecta. Me fala se deu certo!"

3. **Terceira resposta (se não resolveu) — mandar probe:** "Deixa eu mandar um teste rápido pra ver como tá seu sinal. Leva 15 segundos." → **request_probe**

4. **Quarta resposta (após probe) — dica baseada no resultado:**
   - Se probe OK mas usuário diz que não tem internet → "Tenta fechar o navegador completamente, abrir o Safari/Chrome e acessar http://google.com (sem https). Me fala o que aparece."
   - Se probe falhou em DNS → "Esquece a rede 'TocantinsTransporteWiFi' (segura no nome dela e clica em 'Esquecer rede') e conecta de novo."
   - Se probe falhou em velocidade → "O sinal tá fraco aí, tenta sentar mais próximo do roteador do ônibus."

5. **Quinta resposta (se ainda não resolveu)** — aí sim, **escalate** para humano resolver no sistema.

## Caso 2: Status = "SEM CADASTRO" ou "CADASTRO EXPIRADO"
**MAS o usuário diz que pagou** → Isso pode ser MAC randomizado (pagou com MAC anterior, agora tá com MAC novo).

**Fluxo:**
1. **Primeira resposta:** "Oi {$conv->visitor_name}! Verifiquei aqui e não localizei pagamento ativo pra esse dispositivo. Pode ser que o celular gerou um MAC novo (acontece com iOS e Android atualizados). Me fala: você é iPhone ou Android?"

2. **Segunda resposta:** Mesmo passo do MAC aleatório do Caso 1, item 2. Quando ele desativar e reconectar, o sistema vai detectar o cookie e reassociar o pagamento ao novo MAC automaticamente.

3. **Terceira resposta (se não resolveu):** Pergunte os 4 últimos dígitos do número que ele usou pra pagar e o horário aproximado. "Pra eu localizar seu pagamento, me confirma os 4 últimos dígitos do seu telefone e mais ou menos que horas você pagou hoje?"

4. **Quarta resposta:** Se ele confirmou os dados mas ainda não localiza → **escalate**, pois precisa intervenção manual.

## Caso 3: Status = "SEM CADASTRO" + usuário NÃO afirma ter pago
Explique como pagar:
"Pra usar o WiFi: conecta no 'TocantinsTransporteWiFi' (desliga os dados móveis), abre o navegador, o portal vai aparecer automaticamente. Lá você pode pagar R\$ 6,99 (ou R\$ 5,99 assistindo um vídeo de 42 segundos) via PIX. Libera 12 horas de internet."

## Caso 4: Pedido de atendente humano
Escale IMEDIATAMENTE: "Claro! Vou passar pro meu colega agora. Aguarda só um minutinho."

# Dicas técnicas detalhadas (use quando precisar)

## iPhone (iOS) — passo a passo completo
- **Endereço Privado WiFi:** Ajustes → Wi-Fi → (i) ao lado da rede → desativa "Endereço Privado" ou "Privacy Address" → reconecta.
- **Portal não aparece:** Abre o **Safari** (não Chrome) → acessa **http://google.com** (sem https) → portal deve aparecer.
- **Assistência Wi-Fi:** Ajustes → Celular → desativa "Assistência Wi-Fi" (impede trocar pra 4G).
- **Esquecer rede:** Ajustes → Wi-Fi → (i) → "Esquecer Esta Rede" → reconecta.

## Android — passo a passo completo
- **MAC aleatório:** Configurações → Wi-Fi → segura na rede → "Modificar rede" ou "Avançado" → "Privacidade" / "Tipo de endereço MAC" → muda pra "MAC do dispositivo" → reconecta.
- **Portal não aparece:** Chrome → acessa **http://google.com** → toque em "Fazer login na rede" se aparecer. Senão, desconecta e reconecta.
- **Dados móveis interferindo:** Configurações → Rede → desativa "Mudar para dados móveis automaticamente" (Samsung: "Dados móveis inteligentes"; Xiaomi: "Assistente Wi-Fi").
- **Esquecer rede:** Configurações → Wi-Fi → segura na rede → "Esquecer".

## Geral
- Desligar dados móveis (4G/5G).
- Modo avião + ligar só o WiFi.
- Reiniciar WiFi (desliga 5s, liga).

# REGRA DE FORMATO — OBRIGATÓRIO
Responda APENAS com um JSON válido, sem markdown, sem ```json, sem nenhum texto antes ou depois:
{"action":"reply","message":"texto que o usuário vai ler"}
ou
{"action":"request_probe","message":"Deixa eu mandar um teste rápido pra ver como tá seu sinal, leva só 15 segundos."}
ou
{"action":"escalate","message":"Vou passar pro meu colega que consegue resolver isso direto no sistema. Aguarda só um minutinho."}

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

        $payload = [
            'model' => config('services.together.model'),
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.4,
            'stream' => false,
            'thinking' => ['type' => 'disabled'],
        ];

        // Tentar com response_format primeiro
        $response = $http->post(config('services.together.api_url'), array_merge($payload, [
            'response_format' => ['type' => 'json_object'],
        ]));

        // Se falhou com response_format, tentar sem ele
        if (!$response->successful()) {
            Log::info('🤖 Tentando sem response_format', ['status' => $response->status()]);
            $response = $http->post(config('services.together.api_url'), $payload);
        }

        if (!$response->successful()) {
            Log::warning('🤖 AI API falhou', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
                'url' => config('services.together.api_url'),
                'model' => config('services.together.model'),
            ]);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        
        // DeepSeek pode retornar content vazio quando usa thinking mode
        // Nesse caso o texto real fica em reasoning_content
        if (!$content || trim($content) === '') {
            $content = $response->json('choices.0.message.reasoning_content');
        }
        
        if (!$content) {
            Log::warning('🤖 AI API sem content', ['response' => substr($response->body(), 0, 500)]);
            return null;
        }

        // Remove possíveis cercas de código que o modelo insista em colocar
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
        $content = trim($content);

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
            'metadata' => ['ai' => true, 'ai_name' => 'Ana', 'model' => config('services.together.model')],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        Log::info('💬 Ana (IA) respondeu', ['conversation_id' => $conv->id]);
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
                'ai_name' => 'Ana',
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
            'message' => $text ?: 'Vou passar pro meu colega que consegue resolver isso direto no sistema. Aguarda só um minutinho.',
            'metadata' => [
                'ai' => true,
                'ai_name' => 'Ana',
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
                "💬 Ana passou conversa pra você",
                "{$conv->visitor_name} ({$conv->visitor_phone})\n\nMotivo: " . ($reason ?? 'Não conseguiu resolver'),
                'high',
                ['speech_balloon', 'warning']
            );
        } catch (\Exception $e) {}

        Log::info('💬 Ana (IA) escalou pra humano', [
            'conversation_id' => $conv->id,
            'reason' => $reason,
        ]);

        return $msg;
    }

    private function escalateFallback(ChatConversation $conv, string $reason): ChatMessage
    {
        return $this->actionEscalate(
            $conv,
            'Vou passar pro meu colega que consegue te ajudar melhor. Aguarda só um minutinho!',
            $reason
        );
    }
}
