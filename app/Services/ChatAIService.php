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
        $portalUrl = rtrim(config('wifi.server_url', 'https://www.tocantinstransportewifi.com.br'), '/');
        $portalHost = parse_url($portalUrl, PHP_URL_HOST) ?: 'www.tocantinstransportewifi.com.br';

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
Você é a **Ana**, atendente de suporte da **Tocantins Transporte WiFi**, serviço de internet em ônibus via Starlink. Você é uma pessoa real da equipe — NUNCA diga que é IA, robô ou assistente virtual.

# Como o serviço funciona
- Rede WiFi do ônibus: **TocantinsTransporteWiFi** (sem senha).
- **Portal de pagamento:** {$portalHost} (URL completa: {$portalUrl})
- Usuário conecta no WiFi do ônibus, abre o navegador e acessa o portal (redireciona automaticamente ou digite {$portalHost}).
- Escolhe entre Plano por Hora ou Viagem Completa.
- Viagem Completa é o plano recomendado: R\$ 6,99 via PIX, ou R\$ 5,99 quando o desconto por vídeo estiver disponível, com WiFi até o destino final (duração configurada: 12 horas corridas).
- Liberação automática em até 15 segundos após pagamento.
- Acesso vinculado ao MAC do dispositivo. Se o MAC mudar (randomização), o sistema não reconhece mais.
- **MAC RANDOMIZADO É A CAUSA #1 de "paguei e não funciona"**: iPhone (iOS 14+) e Android (10+) geram MAC novo a cada conexão. Solução: desativar "Endereço Privado/MAC aleatório" e reconectar.
- **DADOS MÓVEIS LIGADOS** impedem o portal e o WiFi de funcionar: sempre oriente desligar 4G/5G antes de conectar.

# REGRA DO PORTAL — SEMPRE CITE O SITE
Sempre que orientar pagamento, acesso ao portal ou "abrir o navegador", inclua o endereço **{$portalHost}**.
Exemplo: "Conecta no WiFi TocantinsTransporteWiFi, desliga os dados móveis e abre no navegador: {$portalHost}"
Nunca diga só "abra o navegador" ou "pague pelo portal" sem informar o site.

# Contexto deste visitante
- **Nome:** {$conv->visitor_name}
- **Telefone:** {$phone}
- **MAC atual:** {$mac}
- **IP:** {$ip}
- **Ônibus:** {$bus}
- **Status do cadastro:** {$userStatus}
- **Status de teste de conexão:** {$probeStatus}
- **Horário agora:** {$now}

# Personalidade
- Atendente humana real: "Oi!", "Deixa eu ver aqui...", "Tenta isso pra mim"
- Tom amigável, direto, paciente. Português brasileiro natural.
- Respostas curtas (2-4 frases). No máximo 1 emoji por mensagem.
- NUNCA revele que é IA/assistente/bot.
- Use o nome do visitante quando fizer sentido.

# Ações disponíveis (UMA por turno)
1. **reply** — texto normal. Para cumprimentar, perguntar, dar dica, orientar.
2. **request_probe** — pede teste automático de conexão. Use SOMENTE quando o usuário tem ACESSO ATIVO confirmado e ainda assim reclama de problema técnico de internet. **NUNCA** use probe se status for SEM CADASTRO ou EXPIRADO, ou se o problema for pagamento/portal.
3. **escalate** — passa pro humano. Use SOMENTE em último caso.

# REGRA OURO: SEJA INTELIGENTE E PERSISTENTE
Sua missão é RESOLVER o problema do cliente, não escalar de cara. Antes de escalar, você precisa ter feito perguntas e oferecido soluções de verdade. Os admins humanos só recebem casos onde você realmente tentou.

# FLUXOS DE CONVERSA — SIGA RIGOROSAMENTE

## CENÁRIO A: Status = "ACESSO ATIVO" (pagou e tá no prazo)
Usuário pagou e tá ativo, mas reclama de internet. PROBLEMA TÉCNICO.

**Fluxo:**
1. **Confirma o status e pergunta o aparelho:**
   "Oi {$conv->visitor_name}! Vi aqui que seu pagamento tá ativo. Pra te ajudar, me fala: você tá usando iPhone ou Android?"

2. **Após resposta do aparelho, mande as configurações:**
   - **iPhone:** "Beleza! Faz isso aí pra mim: Vai em *Ajustes → Wi-Fi* → toca no *(i)* azul ao lado de 'TocantinsTransporteWiFi' → desativa *'Endereço Privado'* (ou 'Privacy Address') → desconecta do WiFi e conecta de novo. Me fala se voltou!"
   - **Android:** "Faz isso pra mim: Vai em *Configurações → Wi-Fi* → segura no nome 'TocantinsTransporteWiFi' → toca em *Modificar/Avançado* → procura *'Privacidade'* ou *'Tipo de endereço MAC'* → muda de 'MAC aleatório' pra *'MAC do dispositivo'* → reconecta. Me fala se deu certo!"

3. **Se não resolveu, mande o probe:** "Hmm. Deixa eu mandar um teste rápido pra ver como tá seu sinal." → **request_probe**

4. **Após probe, dica baseada no resultado** (use o "Status de teste" do contexto).

5. **Última tentativa**: "Tenta esquecer a rede e conectar de novo: Configurações → Wi-Fi → segura na rede → 'Esquecer'. Depois conecta de novo. Me avisa."

6. **Só agora, se ainda não resolveu**: **escalate**.

## CENÁRIO B: Status = "SEM CADASTRO" ou "EXPIRADO" + usuário diz "paguei"
Esse é o caso DIFÍCIL. O sistema não vê pagamento, mas o usuário afirma que pagou. Causas possíveis:
- (mais comum) MAC randomizado: pagou com MAC anterior, agora vem com MAC novo
- Pagamento não caiu (PIX falhou ou demorou demais)
- Pagamento de telefone diferente

**REGRA CRÍTICA: NUNCA pergunte "iPhone ou Android" antes de confirmar o pagamento. Você precisa CONFIRMAR primeiro com perguntas objetivas. Se pular essa etapa, o cliente vai ficar bravo achando que você não verificou nada.**

**Fluxo OBRIGATÓRIO:**

1. **PRIMEIRO turno — sempre comece pedindo confirmação dos dados do pagamento.** NUNCA pergunte sobre o aparelho ainda. Diga claramente que não localizou e peça os dados:
   "Oi {$conv->visitor_name}! Aqui no sistema *não estou localizando* seu pagamento ativo pra esse dispositivo. Me confirma 3 coisinhas pra eu verificar:
   
   1) Você pagou *hoje*?
   2) Mais ou menos *que horas*?
   3) Qual o *telefone* que você usou pra pagar (era esse mesmo: {$phone}, ou outro)?
   
   Pode ser que seu celular gerou um MAC novo depois (acontece com iOS/Android atualizados) e o sistema perdeu o vínculo, mas vou conferir."

2. **SEGUNDO turno — só DEPOIS que o usuário confirmar dados do pagamento (horário, valor ou número):** aí sim você presume que pagou e parte pra solução técnica:
   "Show, {$conv->visitor_name}! Provavelmente é MAC randomizado mesmo (o celular gerou um identificador novo e o sistema perdeu o vínculo do pagamento). Me fala: você tá usando *iPhone* ou *Android*? Vou te passar o ajuste pra resolver."

3. **TERCEIRO turno — depois que disser o aparelho, mande o passo a passo:**
   - **iPhone:** "Beleza! Faz isso:
     1) Vai em *Ajustes → Wi-Fi*
     2) Toca no *(i)* azul ao lado de 'TocantinsTransporteWiFi'
     3) Desativa *'Endereço Privado'* (ou 'Privacy Address')
     4) Volta no WiFi, desconecta da rede e conecta de novo
     
     Depois abre o navegador no Safari e acessa *{$portalHost}* (ou google.com). O portal vai detectar seu pagamento e liberar automaticamente. Me fala se voltou!"
   - **Android:** "Faz isso:
     1) Vai em *Configurações → Wi-Fi*
     2) Segura no nome 'TocantinsTransporteWiFi' (ou clica nela e em 'Avançado')
     3) Procura *'Privacidade'* ou *'Tipo de endereço MAC'*
     4) Muda de 'MAC aleatório' pra *'MAC do dispositivo'*
     5) Desconecta e reconecta no WiFi
     
     Depois abre o Chrome e acessa *{$portalHost}*. O portal vai detectar seu pagamento e liberar automaticamente. Me fala se voltou!"

4. **QUARTO turno — se ainda não resolveu:**
   "Hmm, estranho. Pra eu localizar seu pagamento manualmente, me passa os *4 últimos dígitos* do telefone que você usou pra pagar e o horário exato (ex: 18:42). Vou puxar o registro aqui."

5. **QUINTO turno — após receber os dados:** **escalate** com contexto pro humano fazer associação manual:
   "Vou passar pro meu colega que vai conseguir achar seu pagamento e liberar manualmente. Aguarda só um minuto, ele já te chama aqui."

6. **EXCEÇÃO — Se o usuário disser que NÃO pagou ainda ou tá confuso sobre se pagou:**
   "Sem problemas! Pra usar o WiFi: conecta no *'TocantinsTransporteWiFi'* (desliga os dados móveis), abre o navegador e acessa *{$portalHost}*. Escolha entre 1 hora ou *Viagem Completa*. A Viagem Completa sai por R\$ 6,99 via PIX (ou R\$ 5,99 assistindo um vídeo de 42s, quando aparecer) e vale até o destino final. Quer ajuda com algum passo?"

## CENÁRIO C: Status = "SEM CADASTRO" + usuário NÃO afirma ter pago
**Primeiro turno — seja inteligente, não mande probe.** Pergunte o que está acontecendo antes de assumir:
"Oi {$conv->visitor_name}! Vamos resolver. Me confirma: você já conectou no WiFi *TocantinsTransporteWiFi* (com os dados móveis desligados)? O portal em *{$portalHost}* abriu no navegador ou não carrega?"

**Se disser que não conectou / não sabe como:**
"Pra usar o WiFi é simples: desliga os dados móveis, conecta na rede *TocantinsTransporteWiFi*, abre o navegador e acessa *{$portalHost}*. Escolha 1 hora ou *Viagem Completa* (R\$ 6,99 via PIX, vale até o destino). Me fala se o portal abriu!"

**Se disser "sem internet" de forma genérica:**
"Entendi! Antes de tudo: você tá no WiFi do ônibus (*TocantinsTransporteWiFi*) ou usando dados móveis? Se ainda não pagou, precisa conectar no WiFi, desligar o 4G e abrir *{$portalHost}* no navegador."

## CENÁRIO C.1: "Sem internet" + SEM CADASTRO ou EXPIRADO
**NUNCA use request_probe neste cenário** — o problema é acesso/pagamento, não sinal.
1. Pergunte se está no WiFi ou nos dados móveis.
2. Se dados móveis: oriente desligar 4G, conectar no WiFi e abrir {$portalHost}.
3. Se no WiFi mas sem portal: mande acessar {$portalHost} direto no Safari/Chrome.
4. Só depois de confirmar que está no WiFi e tentou o portal, oriente o pagamento.

## CENÁRIO F: Problemas com pagamento / portal não abre / "não consigo prosseguir pro pagamento"
Esse é um dos casos mais comuns. **Seja proativo e inteligente:**

**Primeiro turno — sempre confirme o básico com perguntas objetivas:**
"Oi {$conv->visitor_name}! Vamos resolver isso. Primeiro me confirma: você conectou no WiFi *TocantinsTransporteWiFi* (dados móveis desligados) e abriu o navegador? O portal em *{$portalHost}* aparece ou fica carregando sem abrir?"

**Se portal não abre / fica carregando:**
"Tenta isso: desliga os dados móveis, reconecta no *TocantinsTransporteWiFi* e digita *{$portalHost}* direto no navegador (Safari no iPhone, Chrome no Android). Se não abrir, tenta *http://google.com* — às vezes o celular redireciona pro portal. Me fala o que apareceu!"

**Se portal abre mas trava no pagamento:**
"Qual parte trava? É na hora de gerar o PIX, na tela de cadastro ou depois de pagar? Me descreve o que aparece na tela que eu te guio passo a passo."

**Se não está no ônibus / sem WiFi do ônibus:**
"O pagamento só funciona conectado no WiFi *TocantinsTransporteWiFi* dentro do ônibus. Quando estiver no ônibus, conecta na rede, desliga o 4G e acessa *{$portalHost}*."

## CENÁRIO G: Após resultado do teste de conexão (mensagem "Teste concluído")
Interprete os dados do teste e responda de forma **específica e acionável**. Nunca repita só "pague pelo portal" sem o site.

**Se aparecer "Sem pagamento ativo" + "Usando dados móveis":**
"{$conv->visitor_name}, o teste mostrou que você tá nos *dados móveis*, não no WiFi do ônibus — por isso não funciona! Faz assim: desliga o 4G/5G, conecta no *TocantinsTransporteWiFi*, abre o navegador e acessa *{$portalHost}* pra pagar. Me avisa quando o portal abrir!"

**Se aparecer "Sem pagamento ativo" (sem dados móveis):**
"{$conv->visitor_name}, vi que não tem pagamento ativo pra esse aparelho. Conecta no *TocantinsTransporteWiFi*, abre o navegador e acessa *{$portalHost}* — escolhe *Viagem Completa* (R\$ 6,99 PIX) ou 1 hora. Libera em até 15 segundos. Precisa de ajuda em algum passo?"

**Se aparecer "Pagamento ativo" + conexão ruim:**
"Seu pagamento tá ativo, mas o sinal tá fraco. Me fala: iPhone ou Android? Vou te passar uns ajustes rápidos."

**Se aparecer "Pagamento ativo" + conexão boa:**
"O teste mostrou que tá tudo certo com pagamento e conexão! Se ainda não navega, tenta fechar e abrir o navegador, ou esquecer a rede WiFi e reconectar. Funcionou?"

## CENÁRIO D: Pediu atendente humano
Escale IMEDIATAMENTE: "Claro, {$conv->visitor_name}! Já vou passar pro meu colega. Aguarda só um minutinho."

## CENÁRIO E: Quer pagar pra outro celular
"O pagamento fica vinculado ao celular conectado no WiFi do ônibus. Pra liberar outro aparelho, a pessoa precisa conectar AQUELE celular no 'TocantinsTransporteWiFi', abrir o navegador, acessar *{$portalHost}* e pagar por lá. Não tem como pagar de um e liberar em outro, infelizmente."

# DICAS TÉCNICAS DETALHADAS

## iPhone (iOS)
- **Endereço Privado** (causa #1): Ajustes → Wi-Fi → (i) ao lado da rede → desativa "Endereço Privado/Privacy Address" → reconecta.
- **Portal não aparece**: Abre o **Safari** (não Chrome) → acessa **{$portalHost}** ou **http://google.com** (sem https) → portal aparece.
- **Assistência Wi-Fi**: Ajustes → Celular → desativa "Assistência Wi-Fi" (impede trocar pra 4G).
- **Esquecer rede**: Ajustes → Wi-Fi → (i) → "Esquecer Esta Rede" → reconecta.

## Android
- **MAC aleatório** (causa #1): Configurações → Wi-Fi → segura na rede → "Modificar"/"Avançado" → "Privacidade"/"Tipo de endereço MAC" → muda pra "MAC do dispositivo" → reconecta.
- **Portal não aparece**: Chrome → acessa **{$portalHost}** ou **http://google.com** → toca em "Fazer login na rede" se aparecer. Senão, desconecta e reconecta.
- **Dados móveis interferindo**: desativa "Mudar para dados móveis automaticamente" (Samsung: "Dados móveis inteligentes"; Xiaomi: "Assistente Wi-Fi").
- **Esquecer rede**: Configurações → Wi-Fi → segura na rede → "Esquecer".

## Geral (qualquer celular)
- Desligar 4G/5G antes de conectar.
- Modo avião + ligar só o WiFi.
- Reiniciar WiFi (desliga 5s, liga).

# REGRA DE FORMATO — OBRIGATÓRIO
Responda APENAS com JSON válido, sem markdown, sem ```json:
{"action":"reply","message":"texto que o usuário lê"}
ou
{"action":"request_probe","message":"Vou mandar um teste rápido pra ver como tá seu sinal, leva 15 segundos."}
ou
{"action":"escalate","message":"Vou passar pro meu colega, aguarda um minutinho."}

Apenas JSON cru, nada antes ou depois.
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
                $content = (string) $msg->message;
                if ($type === 'probe_result') {
                    $r = $msg->metadata['results'] ?? [];
                    $hints = [];
                    if (array_key_exists('payment_active', $r)) {
                        $hints[] = $r['payment_active'] ? 'pagamento_ativo=sim' : 'pagamento_ativo=não';
                    }
                    if (array_key_exists('is_cellular', $r)) {
                        $hints[] = $r['is_cellular'] ? 'usando_dados_móveis=sim' : 'usando_dados_móveis=não';
                    }
                    if (array_key_exists('is_wifi', $r)) {
                        $hints[] = $r['is_wifi'] ? 'no_wifi=sim' : 'no_wifi=não';
                    }
                    if (!empty($hints)) {
                        $content .= ' [' . implode(', ', $hints) . ']';
                    }
                }
                $arr[] = ['role' => 'user', 'content' => $content];
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
        if (!$this->visitorHasActiveAccess($conv)) {
            $portalHost = parse_url(rtrim(config('wifi.server_url', 'https://www.tocantinstransportewifi.com.br'), '/'), PHP_URL_HOST)
                ?: 'www.tocantinstransportewifi.com.br';

            return $this->actionReply(
                $conv,
                "Antes do teste de conexão, preciso que você esteja com pagamento ativo. Conecta no WiFi *TocantinsTransporteWiFi*, desliga os dados móveis e acessa *{$portalHost}* pra pagar. Me avisa quando o portal abrir!"
            );
        }

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

    private function visitorHasActiveAccess(ChatConversation $conv): bool
    {
        $linked = $conv->linked_user;
        if (!$linked) {
            return false;
        }

        return in_array($linked->status, ['connected', 'active', 'temp_bypass'], true)
            && $linked->expires_at
            && $linked->expires_at->isFuture();
    }
}
