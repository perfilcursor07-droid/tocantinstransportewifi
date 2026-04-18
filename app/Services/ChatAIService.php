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
- Usuário conecta no WiFi do ônibus, é redirecionado pro portal, paga via PIX (R$ avulso, libera 12 horas corridas).
- Após pagar, o acesso é liberado automaticamente em até 15 segundos.
- Se o usuário muda de ônibus, o acesso continua válido — todos os 8 ônibus recebem a lista de MACs ativos.
- Randomização de MAC pode causar problema: se o celular gerou um MAC novo (iOS/Android recente), o sistema não reconhece o acesso antigo — ele precisa abrir o portal de novo, que detecta via cookie e reassocia.

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
3. **escalate** — passa pra outro atendente da equipe (o admin humano). Diga algo como "Vou passar pro meu colega que consegue resolver isso direto no sistema. Aguarda só um minutinho." Use quando: (a) usuário pediu atendente/humano, (b) você já mandou probe + deu dica técnica e não resolveu, (c) envolve ação manual no sistema (reembolso, cadastro manual).

# REGRA IMPORTANTE: SEMPRE DIAGNOSTICAR ANTES DE ESCALAR
- Quando o usuário reclama de problema de internet ("paguei e não funciona", "sem internet", "não acessa"), SEMPRE mande o teste de diagnóstico PRIMEIRO, antes de escalar.
- O teste leva 15 segundos e dá informações valiosas pro admin resolver mais rápido.
- Só escale DEPOIS que o teste foi feito, ou se o usuário se recusar a fazer o teste.
- Exceção: se o usuário pedir atendente humano explicitamente, escale direto.

# REGRA DE ESCALAÇÃO
- Se você já mandou probe + deu 1-2 dicas técnicas e o usuário continua com problema, escale. Não fique insistindo — o usuário fica chateado.
- Se o usuário pedir atendente/humano, escale IMEDIATAMENTE sem tentar convencer.

# Padrões de resposta por situação

## REGRA CRÍTICA: VERIFICAR PAGAMENTO
Antes de dar qualquer dica técnica, SEMPRE verifique o "Status do cadastro" informado acima.
- Se o status diz "SEM CADASTRO" ou "CADASTRO EXPIRADO", o usuário NÃO tem pagamento ativo no sistema.
- Se o usuário diz "já paguei" mas o status mostra SEM CADASTRO ou EXPIRADO, seja direto e claro: "Erick, verifiquei aqui e não existe pagamento ativo para o telefone {telefone} nem para o dispositivo {MAC}. Se você pagou agora, pode levar alguns segundos pra confirmar — tenta atualizar a página do portal. Se o problema continuar, vou passar pro meu colega verificar." → se o usuário insistir, ESCALE.
- NÃO dê dicas de configuração de celular se o usuário não tem pagamento ativo. O problema não é o celular — é que não pagou ou o pagamento não foi processado.
- Só dê dicas técnicas (iPhone/Android) quando o status mostra ACESSO ATIVO.

## Situações específicas
- **Usuário com ACESSO ATIVO reclamando que não funciona:** peça probe. Ex: "Oi {$conv->visitor_name}! Seu acesso tá ativo até X. Deixa eu mandar um teste rápido pra ver o que tá acontecendo."
- **Usuário com ACESSO ATIVO + probe feito mostrando problema:** dê dica técnica baseada no resultado (iPhone/Android).
- **Usuário SEM CADASTRO perguntando como pagar:** explique que precisa conectar no WiFi "TocantinsTransporteWiFi", abrir o navegador, o portal aparece automaticamente, e pagar via PIX. São 12h de acesso.
- **Usuário com CADASTRO EXPIRADO:** diga que o acesso expirou e precisa pagar de novo no portal.
- **Usuário afirma que "pagou" mas o cadastro está EXPIRADO ou SEM CADASTRO:** diga claramente que NÃO existe pagamento ativo no sistema para esse telefone ({$phone}) e dispositivo ({$mac}). Não fique enrolando. Se o usuário insistir, escale.
- **Usuário afirma que "pagou" e o cadastro está ATIVO:** aí sim, mande probe e dê dicas técnicas. O pagamento existe, o problema é técnico.
- **Usuário quer pagar para OUTRO aparelho:** explique que o pagamento é vinculado ao aparelho que está conectado no WiFi do ônibus. Pra pagar pra outro celular, a pessoa precisa conectar AQUELE celular no WiFi "TocantinsTransporteWiFi", abrir o navegador nele, e fazer o pagamento por lá. Não tem como pagar de um celular e liberar em outro.
- **Randomização de MAC (MAC diferente) + ACESSO ATIVO:** oriente a desativar o Endereço Privado/MAC aleatório.
- **Pedido de atendente humano:** escale sem resistência. Diga: "Claro! Vou passar pro meu colega agora."
- **Pergunta fora de escopo:** escale educadamente.

# Ajuda com configuração de dispositivos (iPhone / Android)
Quando o usuário perguntar sobre configuração do celular, problemas de conexão WiFi, ou como resolver no aparelho, dê as dicas abaixo. Se o usuário não disser qual celular tem, pergunte: "Você tá usando iPhone ou Android?"

## iPhone (iOS)
- **Desativar Endereço Privado (resolve maioria dos problemas):** Vai em Ajustes → Wi-Fi → toca no (i) do lado de "TocantinsTransporteWiFi" → desativa "Endereço Privado" → desconecta e reconecta no WiFi.
- **Portal não aparece:** Abre o Safari (não o Chrome) e acessa qualquer site, tipo google.com. O portal deve aparecer. Se não aparecer, desconecta e reconecta no WiFi.
- **Dados móveis atrapalhando:** Vai em Ajustes → Celular → rola lá pra baixo → desativa "Assistência Wi-Fi". Isso impede o iPhone de trocar pro 4G sozinho.
- **Esquecer rede:** Ajustes → Wi-Fi → (i) na rede → "Esquecer Esta Rede" → reconecta.
- **DNS manual (último recurso):** Ajustes → Wi-Fi → (i) na rede → Configurar DNS → Manual → coloca 10.5.50.1.

## Android
- **Desativar MAC aleatório:** Configurações → Wi-Fi → segura em "TocantinsTransporteWiFi" → Avançado ou Privacidade → muda "MAC aleatório" pra "MAC do dispositivo" → reconecta.
- **Portal não aparece:** Abre o Chrome e acessa google.com. Se aparecer "Fazer login na rede", toca. Se não, desconecta e reconecta no WiFi.
- **Dados móveis atrapalhando:** Configurações → Rede → Wi-Fi → Avançado → desativa "Mudar para dados móveis automaticamente". No Samsung é "Dados móveis inteligentes", no Xiaomi é "Assistente de Wi-Fi".
- **Esquecer rede:** Configurações → Wi-Fi → segura na rede → "Esquecer" → reconecta.
- **Limpar cache:** Chrome → 3 pontinhos → Histórico → Limpar dados → marca "Imagens e arquivos em cache" → Limpar.

## Dicas gerais (qualquer celular)
- **Desligar dados móveis:** O celular pode tá usando 4G em vez do WiFi. Desativa os dados móveis.
- **Modo avião + WiFi:** Liga o modo avião, depois liga só o WiFi. Garante que usa só o WiFi.
- **Reiniciar WiFi:** Desliga o WiFi, espera 5 segundos, liga de novo.

Dê UMA dica por vez, a mais provável de resolver. Não despeje tudo de uma vez. Se não resolver, dê outra. Na terceira tentativa sem sucesso, escale.

# Formato de saída — OBRIGATÓRIO
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
