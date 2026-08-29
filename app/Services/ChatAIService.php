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
     * Ações: reply, request_probe, request_receipt, request_mac, escalate.
     */

    public const ACTIONS = ['reply', 'request_probe', 'request_receipt', 'request_mac', 'escalate'];

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

        if ($aiTurns >= (int) config('services.together.max_turns', 10)) {
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
            if ($this->visitorTypedMacAfterRequest($conv)) {
                $mac = self::extractMacAddress($this->lastVisitorMessage($conv));
                return $this->actionEscalate(
                    $conv,
                    "Recebi o MAC {$mac}. Vou passar pro meu colega liberar esse aparelho. Aguarda só um minutinho!",
                    'MAC digitado: ' . $mac
                );
            }

            // Respondeu iOS/iPhone/Android → pede o MAC na hora (não escala, não chama a API)
            if ($this->shouldRequestMacAfterDeviceAnswer($conv)) {
                $device = $this->visitorDeviceFromHistory($conv) ?? 'both';
                Log::info('🤖 Visitante informou o aparelho — pedindo MAC', [
                    'conversation_id' => $conv->id,
                    'device' => $device,
                ]);
                return $this->executeAction($conv, [
                    'action' => 'request_mac',
                    'message' => $this->macInstructionsMessage($device),
                ]);
            }

            $messages = $this->buildMessages($conv);
            $decision = $this->callApi($messages);

            if (!$decision || !in_array($decision['action'] ?? null, self::ACTIONS, true)) {
                $decision = $this->fallbackDecision($conv);
            }

            $decision = $this->guardDeviceAnswerRequestMac($conv, $decision);
            $decision = $this->guardPaidWithoutReceipt($conv, $decision);
            $decision = $this->guardPaymentHelpWithoutEscalate($conv, $decision);
            $decision = $this->guardConfusionWithoutEscalate($conv, $decision);
            $decision = $this->guardAccessIssueAskMac($conv, $decision);
            $decision = $this->guardNoRepeatPaymentSteps($conv, $decision);

            return $this->executeAction($conv, $decision);
        } catch (\Throwable $e) {
            Log::warning('🤖 Falha na IA do chat', [
                'conversation_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);

            return $this->executeAction($conv, $this->fallbackDecision($conv, 'Erro na chamada da IA'));
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
- **Portal de pagamento:** {$portalHost} (URL completa: {$portalUrl} ou www.tocantinstransportewifi.com.br)
- Forma correta de pagar: o celular precisa abrir o **captive portal** (janelinha automática) ou o site www.tocantinstransportewifi.com.br **já conectado no WiFi do ônibus com dados móveis desligados**.
- Escolhe entre Plano por Hora ou Viagem Completa.
- Viagem Completa é o plano recomendado: R\$ 6,99 via PIX, ou R\$ 5,99 quando o desconto por vídeo estiver disponível, com WiFi até o destino final (duração configurada: 12 horas corridas).
- Liberação automática em até 15 segundos após pagamento.
- Acesso vinculado ao MAC do dispositivo. Se o MAC mudar (randomização), o sistema não reconhece mais.
- **MAC RANDOMIZADO É A CAUSA #1 de "paguei e não funciona"**: iPhone (iOS 14+) e Android (10+) geram MAC novo a cada conexão. Solução: desativar "Endereço Privado/MAC aleatório" e reconectar.
- **DADOS MÓVEIS LIGADOS** impedem o portal e o WiFi de funcionar: sempre oriente desligar 4G/5G antes de conectar.

# REGRA DO PORTAL — SEMPRE CITE O SITE
Sempre que orientar pagamento, acesso ao portal ou "abrir o navegador", inclua **www.tocantinstransportewifi.com.br** (ou {$portalHost}).
Nunca diga só "abra o navegador" ou "pague pelo portal" sem informar o site.

# REGRA CRÍTICA — ESQUECER A REDE
- **ANTES de pagar / SEM CADASTRO / EXPIRADO:** PODE pedir pra *esquecer a rede* TocantinsTransporteWiFi e conectar de novo — isso ajuda a abrir o captive portal automaticamente.
- **DEPOIS de pagar / ACESSO ATIVO:** NÃO peça pra esquecer a rede (isso gera MAC novo e quebra o vínculo do pagamento). Nesse caso oriente só desligar/ligar o WiFi ou desativar endereço privado.

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
- Respostas curtas (2-4 frases), EXCETO no passo a passo (aí pode listar os 4 passos).
- No máximo 1 emoji por mensagem.
- NUNCA revele que é IA/assistente/bot.
- Use o nome do visitante quando fizer sentido.

# FORMATO DO TEXTO QUE O USUÁRIO LÊ (campo "message") — OBRIGATÓRIO
- NUNCA use asteriscos (*), markdown, **negrito**, _itálico_ ou cercas ```.
- Use texto puro, fácil de ler no celular.
- Em listas/passo a passo: cada item em UMA LINHA NOVA, com quebra de linha real (\n), nunca tudo numa linha só.
- Exemplo certo de passo a passo (copie o estilo):
Beleza! Faz assim:

1) Esquece a rede TocantinsTransporteWiFi no celular e conecta de novo (com os dados móveis desligados). Veja se abre sozinha a página pra pagar.

2) Se não abrir: leia o QR Code do ônibus ou abra o navegador em www.tocantinstransportewifi.com.br (no WiFi do ônibus, 4G desligado).

3) Escolha o plano, pague no PIX e aguarde até 15 segundos — libera sozinho.

4) Se nada disso funcionar, peça ajuda ao motorista.

Me fala em qual passo você parou!

# Ações disponíveis (UMA por turno)
1. **reply** — texto normal. Para cumprimentar, perguntar, dar dica, orientar.
2. **request_probe** — pede teste automático de conexão. Use SOMENTE quando o usuário tem ACESSO ATIVO confirmado e ainda assim reclama de problema técnico de internet. **NUNCA** use probe se status for SEM CADASTRO ou EXPIRADO, ou se o problema for pagamento/portal.
3. **request_receipt** — abre no chat um botão pra o usuário *enviar o comprovante do PIX* (foto/print). Use SOMENTE quando o status for SEM CADASTRO ou EXPIRADO e o usuário *insistiu* que já pagou (ex.: "paguei", "já paguei", "paguei hoje", "tem 2 horas que paguei"). NÃO use se ele ainda não afirmou que pagou.
4. **request_mac** — abre no chat um botão pra o usuário *enviar foto do MAC* (ou escrever o MAC). Use quando ele insiste que não consegue acessar / pagou e não libera. ANTES de usar, pergunte se é iOS (iPhone) ou Android. No turno em que ele responder o aparelho, use request_mac. NUNCA escalate nesse turno.
5. **escalate** — passa pro humano. Use SOMENTE em último caso (depois do comprovante, depois da foto do MAC, ou se o usuário pediu atendente).

# REGRA OURO: SEJA INTELIGENTE E PERSISTENTE
Sua missão é RESOLVER o problema do cliente, não escalar de cara. Antes de escalar, você precisa ter feito perguntas e oferecido soluções de verdade. Os admins humanos só recebem casos onde você realmente tentou.

# FLUXOS DE CONVERSA — SIGA RIGOROSAMENTE

## CENÁRIO A: Status = "ACESSO ATIVO" (pagou e tá no prazo)
Usuário pagou e tá ativo, mas reclama de internet. PROBLEMA TÉCNICO.

**Fluxo:**
1. **Confirma o status e pergunta o aparelho:**
   "Oi {$conv->visitor_name}! Vi aqui que seu pagamento tá ativo. Pra te ajudar, me fala: você está usando iOS (iPhone) ou Android?"

2. **Quando ele responder iOS / iPhone / Android:** use **request_mac** na hora (passo a passo pra achar o MAC daquele aparelho + botão de foto). NUNCA escalate nesse turno. NUNCA peça só pra desativar endereço privado e parar.

3. **Se não resolveu, mande o probe:** "Hmm. Deixa eu mandar um teste rápido pra ver como tá seu sinal." → **request_probe**

4. **Após probe, dica baseada no resultado** (use o "Status de teste" do contexto).

5. **Última tentativa (SEM esquecer a rede):** "Desliga o WiFi do celular por 5 segundos, liga de novo e reconecta no TocantinsTransporteWiFi (não esquece a rede). Me avisa."

6. **Só agora, se ainda não resolveu**: **escalate**.

## CENÁRIO B: Status = "SEM CADASTRO" ou "EXPIRADO" + usuário diz claramente "eu paguei" / "já paguei"
O sistema não vê pagamento ativo, mas o usuário *afirma* que pagou.

**REGRA:** Só use este cenário se o usuário *afirmar* que já pagou. Se ele só disser "wifi", "conexão", "não consigo" — use o CENÁRIO C.
**NUNCA escale só porque ele falou o horário.** Depois que ele insistir que pagou, peça o *comprovante* com **request_receipt**.

**Fluxo:**
1. **PRIMEIRO turno (quando ele diz "paguei" / "sem internet mas paguei"):**
   Use **reply**:
   "Oi {$conv->visitor_name}! Aqui não aparece pagamento ativo pra esse aparelho/telefone. Você já pagou o PIX hoje? Se sim, me fala o horário aproximado."

2. **SEGUNDO turno (ele confirma horário / insiste que pagou):**
   Use **request_receipt** (obrigatório nesta etapa — NÃO escalate ainda):
   message: "Show! Pra eu localizar seu pagamento, me manda o comprovante do PIX (foto ou print da tela do banco). Use o botão abaixo pra enviar."

3. **Depois que o comprovante chegar:** o sistema já avisa o atendente humano. Se o usuário continuar falando sem enviar, lembre de usar o botão. Só **escalate** se ele pedir atendente ou se já enviou o comprovante e ainda precisa de humano.

4. **Se disser que NÃO pagou / se confundiu:** use o **PASSO A PASSO DE PAGAMENTO**.

## CENÁRIO C: Status = "SEM CADASTRO" ou "EXPIRADO" + usuário NÃO afirma ter pago
(Casos tipo "Wifi", "quero wifi", "Conexão do celular", "Não estou conseguindo", "sem internet", "como pago".)
**NUNCA use request_probe.**

**Primeiro turno — se ainda NÃO mandou o passo a passo nesta conversa:**
action: **reply** com o **PASSO A PASSO DE PAGAMENTO** (pode perguntar antes OU já mandar direto se ele pediu "como pago").

**Se o usuário confirmar** (ex.: "sim", "quero sim", "pode", "manda") e você ainda NÃO mandou o passo a passo:
action: **reply** com o **PASSO A PASSO DE PAGAMENTO**. Não escalate.

**Se o usuário NÃO ENTENDEU um passo** (ex.: "não entendi", "como desligo o 4G", "como continuo pro pagamento", "explica melhor"):
action: **reply**. EXPLIQUE aquele passo com calma. **PROIBIDO escalate.** Dúvida não é falha.

Use o **COMO DESLIGAR O 4G** quando a dúvida for dados móveis / 4G / continuar pro pagamento.

**DEPOIS que o passo a passo JÁ FOI ENVIADO nesta conversa:**
Se ele insiste que não consegue acessar (tentou e não abre / nada deu certo):
1. Se você AINDA NÃO sabe se é iOS (iPhone) ou Android: use **reply** perguntando: "Beleza. Pra te ajudar, você está usando iOS (iPhone) ou Android?"
2. Se ele JÁ disse iOS, iPhone ou Android: use **request_mac** com o passo pra achar o MAC daquele aparelho. NÃO escalate ainda.
3. Só **escalate** depois que ele mandar a foto/MAC, ou se pedir atendente.

"Não entendi" / "como faço" / "explica" NÃO é motivo pra escalar.

## PASSO A PASSO DE PAGAMENTO (use sempre que for orientar a conectar/pagar)
Mande EXATAMENTE neste estilo (texto puro, cada passo em linha nova, SEM asteriscos):

Beleza! Faz assim:

1) Esquece a rede TocantinsTransporteWiFi no celular e conecta de novo (com os dados móveis desligados). Veja se abre sozinha a página pra pagar.

2) Se não abrir: leia o QR Code do ônibus ou abra o navegador em www.tocantinstransportewifi.com.br (no WiFi do ônibus, 4G desligado).

3) Escolha o plano, pague no PIX e aguarde até 15 segundos — libera sozinho.

4) Se nada disso funcionar, peça ajuda ao motorista.

Me fala em qual passo você parou!

## COMO DESLIGAR O 4G (use quando o usuário não entender esse passo)
Mande neste estilo, texto puro, SEM asteriscos:

Tranquilo! O 4G é a internet do chip. Pra pagar no WiFi do ônibus, ele precisa estar desligado.

iPhone: Ajustes → Celular (ou Dados Celulares) → desliga o botão no topo.

Android: desliza a tela de cima pra baixo e toca no ícone Dados móveis / 4G até apagar.

Depois fica só no WiFi TocantinsTransporteWiFi, abre o navegador em www.tocantinstransportewifi.com.br, escolhe o plano e paga no PIX.

Me fala se é iPhone ou Android que eu te guiando no detalhe!

## CENÁRIO H: Insiste que não consegue acessar (possível MAC diferente)
Às vezes o pagamento liberou outro MAC (endereço privado / MAC aleatório). Antes de passar pro humano:

1. Pergunte: você está usando iOS (iPhone) ou Android?
2. No turno SEGUINTE, quando ele responder iOS/iPhone/Android, use **request_mac** (obrigatório). NUNCA escalate nesse turno.

iPhone: Ajustes → Wi-Fi → toca no (i) azul ao lado de TocantinsTransporteWiFi. O Endereço Wi-Fi / MAC aparece aí. Manda uma foto dessa tela ou escreve o código (tipo AA:BB:CC:DD:EE:FF).

Android: Configurações → Wi-Fi → toca na rede TocantinsTransporteWiFi (ou em detalhes / i). Procura Endereço MAC ou MAC do dispositivo. Manda foto ou escreve o código.

NÃO use request_mac sem saber se é iOS (iPhone) ou Android. Quando ele responder o aparelho, request_mac é obrigatório.

## CENÁRIO F: Portal não abre / "não consigo pagar" / "não consigo conectar"
- Se o usuário está com DÚVIDA (não entendeu / como faz): explique o passo. Não escalate.
- Se o passo a passo **ainda não** foi enviado nesta conversa: mande o **PASSO A PASSO DE PAGAMENTO**.
- Se o passo a passo **já foi** enviado e ele afirma que JÁ TENTOU e não funciona: pergunte iPhone/Android e depois **request_mac**. Só escalate depois da foto/MAC.

**Se portal abre mas trava no PIX/cadastro:**
"Qual parte trava? É na hora de gerar o PIX, na tela de cadastro ou depois de pagar? Me descreve o que aparece que eu te guio."

**Se não está no ônibus:**
"O pagamento só funciona dentro do ônibus, conectado no TocantinsTransporteWiFi. Quando estiver a bordo, me chama que te passo o passo a passo."

## CENÁRIO G: Após resultado do teste de conexão (mensagem "Teste concluído")
Interprete os dados e responda de forma **específica**.

**Se "Sem pagamento ativo" (com ou sem dados móveis):**
use o **PASSO A PASSO DE PAGAMENTO** (lembre de desligar o 4G no passo 1).

**Se "Pagamento ativo" + conexão ruim:**
"Seu pagamento tá ativo, mas o sinal tá fraco. Me fala: você está usando iOS (iPhone) ou Android?" Depois da resposta, **request_mac**. *(NÃO peça pra esquecer a rede neste caso.)*

**Se "Pagamento ativo" + conexão boa:**
"O teste mostrou que tá tudo certo com pagamento e conexão! Se ainda não navega, desliga e liga o WiFi (sem esquecer a rede) ou fecha e abre o navegador. Funcionou?"

## CENÁRIO D: Pediu atendente humano
Só escale se o usuário pediu EXPLICITAMENTE atendente/humano (ex.: "quero falar com atendente", "chama um humano").
"sim", "quero sim", "pode", "manda o passo a passo" NÃO é pedido de atendente — nesse caso mande o PASSO A PASSO.
Se pediu atendente de verdade: "Claro, {$conv->visitor_name}! Já vou passar pro meu colega. Aguarda só um minutinho."

## CENÁRIO E: Quer pagar pra outro celular
"O pagamento fica vinculado ao celular conectado no WiFi do ônibus. Pra liberar outro aparelho, a pessoa precisa conectar AQUELE celular no TocantinsTransporteWiFi, abrir o navegador, acessar {$portalHost} e pagar por lá. Não tem como pagar de um e liberar em outro, infelizmente."

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
{"action":"request_receipt","message":"Pra eu localizar seu pagamento, me manda o comprovante do PIX (foto ou print). Use o botão abaixo."}
ou
{"action":"request_mac","message":"Pra liberar o aparelho certo, me manda o MAC da rede. Use o botão abaixo pra enviar a foto da tela ou escreva o código."}
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
                if ($type === 'receipt_upload') {
                    $content .= ' [enviou comprovante PIX]';
                } elseif ($type === 'mac_upload') {
                    $content .= ' [enviou foto do MAC]';
                } elseif ($type === 'mac_text' && !empty($msg->metadata['mac_address'])) {
                    $content .= ' [MAC digitado: ' . $msg->metadata['mac_address'] . ']';
                }
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
                } elseif ($type === 'receipt_request') {
                    $prefix = '[sistema: pediu comprovante PIX] ';
                } elseif ($type === 'mac_request') {
                    $prefix = '[sistema: pediu foto/MAC do aparelho] ';
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
        $text = $this->sanitizeUserFacingMessage((string) ($decision['message'] ?? ''));
        if ($text === '') $text = 'Estou processando, um instante.';
        if (mb_strlen($text) > 900) $text = mb_substr($text, 0, 900);

        return match ($action) {
            'request_probe' => $this->actionProbe($conv, $text),
            'request_receipt' => $this->actionRequestReceipt($conv, $text),
            'request_mac' => $this->actionRequestMac($conv, $text),
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
            return $this->actionReply(
                $conv,
                "Antes do teste, preciso que você tenha pagamento ativo. Faz assim: esquece a rede TocantinsTransporteWiFi e conecta de novo (dados móveis desligados). Se a página de pagamento não abrir sozinha, leia o QR Code do ônibus ou entre em www.tocantinstransportewifi.com.br. Se nada funcionar, peça ajuda ao motorista."
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

    /**
     * Abre no chat o cartão pra o visitante enviar comprovante do PIX.
     * Só faz sentido quando o usuário insiste que pagou e o sistema não vê o pagamento.
     */
    private function actionRequestReceipt(ChatConversation $conv, string $text): ChatMessage
    {
        // Evita spam: se já pediu comprovante nos últimos 10 min e ainda não enviou, só relembra
        $recentRequest = ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'receipt_request')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        $alreadyUploaded = ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'receipt_upload')
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();

        if ($alreadyUploaded) {
            return $this->actionEscalate(
                $conv,
                'Já recebi seu comprovante. Vou passar pro meu colega conferir e liberar se estiver tudo certo. Aguarda só um minutinho!',
                'comprovante já enviado'
            );
        }

        if ($recentRequest) {
            return $this->actionReply(
                $conv,
                'O botão pra enviar o comprovante do PIX já está na conversa acima. Manda a foto/print por lá que eu passo pro meu colega conferir.'
            );
        }

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'admin',
            'admin_id' => null,
            'type' => 'receipt_request',
            'message' => $text ?: 'Pra eu localizar seu pagamento, me manda o comprovante do PIX (foto ou print). Use o botão abaixo.',
            'metadata' => [
                'ai' => true,
                'ai_name' => 'Ana',
                'model' => config('services.together.model'),
                'receipt_requested' => true,
            ],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        Log::info('🤖 IA pediu comprovante PIX', ['conversation_id' => $conv->id]);
        return $msg;
    }

    /**
     * Abre no chat o cartão pra o visitante enviar foto do MAC (ou escrever o código).
     */
    private function actionRequestMac(ChatConversation $conv, string $text): ChatMessage
    {
        if ($this->alreadyCollectedMac($conv)) {
            $mac = $this->collectedMacAddress($conv);
            $suffix = $mac ? " ({$mac})" : '';
            return $this->actionEscalate(
                $conv,
                "Já recebi o MAC{$suffix}. Vou passar pro meu colega liberar esse aparelho. Aguarda só um minutinho!",
                'MAC já enviado'
            );
        }

        $device = $this->visitorDeviceFromHistory($conv);
        if ($device === null) {
            return $this->actionReply(
                $conv,
                'Beleza. Às vezes o WiFi libera outro aparelho. Pra te ajudar no passo certo, você está usando iOS (iPhone) ou Android?'
            );
        }

        $recentRequest = ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'mac_request')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentRequest) {
            return $this->actionReply(
                $conv,
                'O botão pra enviar a foto do MAC já está na conversa acima. Manda a foto da tela ou escreve o código (tipo AA:BB:CC:DD:EE:FF) que eu passo pro meu colega liberar.'
            );
        }

        $instructions = $this->macInstructionsMessage($device);
        $looksComplete = mb_strlen($text) >= 80 && preg_match('/ajustes|configura/iu', $text);
        $cardText = $looksComplete ? $text : $instructions;

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'admin',
            'admin_id' => null,
            'type' => 'mac_request',
            'message' => $cardText,
            'metadata' => [
                'ai' => true,
                'ai_name' => 'Ana',
                'model' => config('services.together.model'),
                'mac_requested' => true,
                'device' => $device,
            ],
            'is_read' => true,
        ]);

        $conv->update([
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        Log::info('🤖 IA pediu MAC do aparelho', [
            'conversation_id' => $conv->id,
            'device' => $device,
        ]);
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

    /**
     * Limpa markdown/asteriscos e organiza listas numeradas pra leitura no celular.
     */
    private function sanitizeUserFacingMessage(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Remove cercas de código se o modelo insistir
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;

        // Separa passos "1) ... 2) ..." que vieram numa linha só
        $text = preg_replace('/\s+(\d+\))\s+/u', "\n\n$1 ", $text) ?? $text;

        // Remove ênfase markdown **x** / *x* (mantém o texto)
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*\n]+)\*/u', '$1', $text) ?? $text;

        // Asteriscos soltos restantes
        $text = str_replace('*', '', $text);

        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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

    /**
     * Se o usuário insiste que pagou, status sem acesso ativo e a IA tenta escalar
     * (ou pedir probe) sem ter comprovante, força request_receipt.
     */
    private function guardPaidWithoutReceipt(ChatConversation $conv, array $decision): array
    {
        $action = $decision['action'] ?? 'reply';
        if (!in_array($action, ['escalate', 'request_probe'], true)) {
            return $decision;
        }

        if ($this->visitorHasActiveAccess($conv)) {
            return $decision;
        }

        if ($this->visitorAskedForHuman($conv)) {
            return $decision;
        }

        if (!$this->visitorClaimsPaid($conv)) {
            return $decision;
        }

        $alreadyUploaded = ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'receipt_upload')
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        if ($alreadyUploaded) {
            return $decision;
        }

        Log::info('🤖 Guard: forçando request_receipt (insistiu que pagou, sem comprovante)', [
            'conversation_id' => $conv->id,
            'was_action' => $action,
        ]);

        return [
            'action' => 'request_receipt',
            'message' => 'Pra eu localizar seu pagamento, me manda o comprovante do PIX (foto ou print). Use o botão abaixo pra enviar.',
        ];
    }

    /**
     * Usuário sem pagamento pediu só orientação (wifi / passo a passo / "sim").
     * A IA NÃO deve escalar cedo — manda o passo a passo UMA vez.
     * Se já mandou e o usuário diz que não funcionou, deixa escalar.
     */
    private function guardPaymentHelpWithoutEscalate(ChatConversation $conv, array $decision): array
    {
        $action = $decision['action'] ?? 'reply';
        if ($action !== 'escalate' && $action !== 'request_probe') {
            return $decision;
        }

        if ($this->visitorHasActiveAccess($conv)) {
            return $decision;
        }

        if ($this->visitorAskedForHuman($conv)) {
            return $decision;
        }

        // Já mandou o passo a passo e o usuário diz que falhou → pode escalar
        if ($this->alreadySentPaymentSteps($conv) && $this->visitorSaysStepsFailed($conv)) {
            return $decision;
        }

        // Já mandou o passo a passo (mesmo sem "falhou" explícito) e tenta escalar cedo
        // só bloqueia se for a primeira confirmação ("sim"/"quero") — aí manda os passos
        if ($this->alreadySentPaymentSteps($conv)) {
            // Se não reclamou de falha, ainda pode ser cedo demais pra escalar
            // em "sim/ok" após oferta — mas se já mandou passos, deixa a IA decidir escalate
            // Apenas bloqueia escalate prematuro quando ainda NÃO mandou os passos
            return $decision;
        }

        // Se insiste que pagou, o outro guard já trata (comprovante)
        if ($this->visitorClaimsPaid($conv)) {
            return $decision;
        }

        Log::info('🤖 Guard: forçando passo a passo (pedido de informação, sem escalar)', [
            'conversation_id' => $conv->id,
            'was_action' => $action,
        ]);

        return [
            'action' => 'reply',
            'message' => $this->paymentStepsMessage(),
        ];
    }

    /**
     * Se o visitante acabou de dizer iOS/iPhone/Android, obriga request_mac.
     */
    private function guardDeviceAnswerRequestMac(ChatConversation $conv, array $decision): array
    {
        if (!$this->shouldRequestMacAfterDeviceAnswer($conv)) {
            return $decision;
        }

        $device = $this->visitorDeviceFromHistory($conv) ?? 'both';

        Log::info('🤖 Guard: resposta do aparelho — forçando request_mac', [
            'conversation_id' => $conv->id,
            'device' => $device,
            'was_action' => $decision['action'] ?? 'reply',
        ]);

        return [
            'action' => 'request_mac',
            'message' => $this->macInstructionsMessage($device),
        ];
    }

    /**
     * Insiste que não acessa: pergunta iPhone/Android e pede MAC (foto ou código)
     * antes de passar pro humano — o MAC liberado costuma ser outro.
     */
    private function guardAccessIssueAskMac(ChatConversation $conv, array $decision): array
    {
        $action = $decision['action'] ?? 'reply';

        if ($action === 'request_receipt' || $action === 'request_mac') {
            return $decision;
        }

        if ($this->visitorAskedForHuman($conv)) {
            return $decision;
        }

        if ($this->alreadyCollectedMac($conv)) {
            return $decision;
        }

        // "paguei" sem comprovante NÃO bloqueia o MAC se o pagamento já está ativo
        // ou se ele acabou de dizer iOS/Android (fluxo do aparelho)
        $receiptPending = $this->visitorClaimsPaid($conv)
            && !$this->alreadyUploadedReceipt($conv)
            && !$this->visitorHasActiveAccess($conv)
            && !$this->visitorJustAnsweredDevice($conv)
            && !$this->alreadyAskedDevice($conv);
        if ($receiptPending) {
            return $decision;
        }

        $insists = $this->visitorSaysStepsFailed($conv);
        $alreadyHelped = $this->alreadySentPaymentSteps($conv)
            || $this->visitorHasActiveAccess($conv)
            || $this->alreadyAskedDevice($conv);

        $iaTriedToEscalate = in_array($action, ['escalate', 'request_probe'], true)
            && ($this->alreadySentPaymentSteps($conv) || $this->visitorHasActiveAccess($conv));

        if (!(($insists && $alreadyHelped) || $iaTriedToEscalate)) {
            return $decision;
        }

        $next = $this->nextMacCollectionDecision($conv);
        if (!$next) {
            return $decision;
        }

        Log::info('🤖 Guard: pedindo MAC (iPhone/Android) em vez de escalar', [
            'conversation_id' => $conv->id,
            'was_action' => $action,
            'next_action' => $next['action'],
        ]);

        return $next;
    }

    /**
     * Dúvida ("não entendi", "como desligo o 4G") não escala — explica o passo.
     */
    private function guardConfusionWithoutEscalate(ChatConversation $conv, array $decision): array
    {
        if (!$this->visitorNeedsGuidedHelp($conv)) {
            return $decision;
        }

        if ($this->visitorAskedForHuman($conv)) {
            return $decision;
        }

        $action = $decision['action'] ?? 'reply';
        if ($action !== 'escalate' && $action !== 'request_probe') {
            return $decision;
        }

        Log::info('🤖 Guard: dúvida do usuário — explicando em vez de escalar', [
            'conversation_id' => $conv->id,
            'was_action' => $action,
        ]);

        return [
            'action' => 'reply',
            'message' => $this->howToDisableMobileDataMessage(),
        ];
    }

    /**
     * Nunca repetir o mesmo passo a passo quando o usuário já tentou e falhou.
     */
    private function guardNoRepeatPaymentSteps(ChatConversation $conv, array $decision): array
    {
        if ($this->visitorHasActiveAccess($conv)) {
            return $decision;
        }

        if ($this->visitorNeedsGuidedHelp($conv)) {
            return $decision;
        }

        if (!$this->alreadySentPaymentSteps($conv)) {
            return $decision;
        }

        if (!$this->visitorSaysStepsFailed($conv)) {
            return $decision;
        }

        // Insistiu que pagou → comprovante tem prioridade
        if ($this->visitorClaimsPaid($conv)) {
            $alreadyUploaded = ChatMessage::where('conversation_id', $conv->id)
                ->where('type', 'receipt_upload')
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();

            if (!$alreadyUploaded) {
                return [
                    'action' => 'request_receipt',
                    'message' => 'Pra eu localizar seu pagamento, me manda o comprovante do PIX (foto ou print). Use o botão abaixo pra enviar.',
                ];
            }
        }

        if (in_array($decision['action'] ?? 'reply', ['request_mac', 'request_receipt'], true)) {
            return $decision;
        }

        if (!$this->alreadyCollectedMac($conv)) {
            $next = $this->nextMacCollectionDecision($conv);
            if ($next) {
                Log::info('🤖 Guard: passos falharam — pedindo MAC antes de escalar', [
                    'conversation_id' => $conv->id,
                    'was_action' => $decision['action'] ?? 'reply',
                ]);
                return $next;
            }
        }

        $action = $decision['action'] ?? 'reply';
        $msg = mb_strtolower((string) ($decision['message'] ?? ''));
        $looksLikeSteps = str_contains($msg, '1)') && (
            str_contains($msg, 'esquece a rede')
            || str_contains($msg, 'tocantinstransportewifi')
            || str_contains($msg, 'passo')
        );

        if ($action === 'escalate' && !$looksLikeSteps) {
            return $decision;
        }

        Log::info('🤖 Guard: passos já falharam e MAC já coletado — escalando', [
            'conversation_id' => $conv->id,
            'was_action' => $action,
        ]);

        return [
            'action' => 'escalate',
            'message' => 'Poxa, desculpa que não resolveu por aqui. Vou passar pro meu colega que consegue te ajudar direto. Aguarda só um minutinho!',
            'reason' => 'passo a passo já enviado e usuário disse que não funcionou',
        ];
    }

    private function fallbackDecision(ChatConversation $conv, ?string $reason = null): array
    {
        if ($this->visitorAskedForHuman($conv)) {
            return [
                'action' => 'escalate',
                'message' => 'Claro! Vou passar pro meu colega. Aguarda só um minutinho!',
                'reason' => $reason ?? 'pediu atendente',
            ];
        }

        if ($this->shouldRequestMacAfterDeviceAnswer($conv)) {
            $device = $this->visitorDeviceFromHistory($conv) ?? 'both';
            return [
                'action' => 'request_mac',
                'message' => $this->macInstructionsMessage($device),
            ];
        }

        if ($this->visitorNeedsGuidedHelp($conv)) {
            return [
                'action' => 'reply',
                'message' => $this->howToDisableMobileDataMessage(),
            ];
        }

        if ($this->alreadyAskedDevice($conv) && !$this->alreadyCollectedMac($conv)) {
            return $this->nextMacCollectionDecision($conv) ?? [
                'action' => 'reply',
                'message' => 'Pra te ajudar no passo certo, você está usando iOS (iPhone) ou Android?',
            ];
        }

        if (!$this->visitorHasActiveAccess($conv)) {
            if ($this->alreadySentPaymentSteps($conv) && $this->visitorSaysStepsFailed($conv) && !$this->visitorNeedsGuidedHelp($conv)) {
                if (!$this->alreadyCollectedMac($conv)) {
                    return $this->nextMacCollectionDecision($conv) ?? [
                        'action' => 'reply',
                        'message' => 'Beleza. Às vezes o WiFi libera outro aparelho. Pra te ajudar, você está usando iOS (iPhone) ou Android?',
                    ];
                }

                return [
                    'action' => 'escalate',
                    'message' => 'Poxa, desculpa que não resolveu por aqui. Vou passar pro meu colega que consegue te ajudar direto. Aguarda só um minutinho!',
                    'reason' => $reason ?? 'passos falharam / fallback',
                ];
            }

            if (!$this->alreadySentPaymentSteps($conv) && !$this->visitorClaimsPaid($conv)) {
                return [
                    'action' => 'reply',
                    'message' => $this->paymentStepsMessage(),
                ];
            }
        }

        return [
            'action' => 'escalate',
            'message' => 'Vou passar pro meu colega que consegue te ajudar melhor. Aguarda só um minutinho!',
            'reason' => $reason ?? 'IA não retornou decisão válida',
        ];
    }

    private function howToDisableMobileDataMessage(): string
    {
        return "Tranquilo! O 4G é a internet do chip. Pra pagar no WiFi do ônibus, ele precisa estar desligado.\n\n"
            . "iPhone: Ajustes → Celular (ou Dados Celulares) → desliga o botão no topo.\n\n"
            . "Android: desliza a tela de cima pra baixo e toca no ícone Dados móveis / 4G até apagar.\n\n"
            . "Depois fica só no WiFi TocantinsTransporteWiFi, abre o navegador em www.tocantinstransportewifi.com.br, escolhe o plano e paga no PIX.\n\n"
            . "Me fala se é iOS (iPhone) ou Android que eu te guiando no detalhe!";
    }

    private function paymentStepsMessage(): string
    {
        return "Beleza! Faz assim:\n\n"
            . "1) Esquece a rede TocantinsTransporteWiFi no celular e conecta de novo (com os dados móveis desligados). Veja se abre sozinha a página pra pagar.\n\n"
            . "2) Se não abrir: leia o QR Code do ônibus ou abra o navegador em www.tocantinstransportewifi.com.br (no WiFi do ônibus, 4G desligado).\n\n"
            . "3) Escolha o plano, pague no PIX e aguarde até 15 segundos — libera sozinho.\n\n"
            . "4) Se nada disso funcionar, peça ajuda ao motorista.\n\n"
            . "Me fala em qual passo você parou!";
    }

    private function alreadySentPaymentSteps(ChatConversation $conv): bool
    {
        $adminMsgs = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'admin')
            ->whereNull('admin_id')
            ->orderByDesc('id')
            ->limit(12)
            ->pluck('message');

        foreach ($adminMsgs as $msg) {
            $t = mb_strtolower((string) $msg);
            if (
                str_contains($t, '1)')
                && str_contains($t, 'esquece a rede')
                && str_contains($t, 'tocantinstransportewifi')
            ) {
                return true;
            }
        }

        return false;
    }

    private function visitorSaysStepsFailed(ChatConversation $conv): bool
    {
        $last = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->value('message');

        if (!$last) {
            return false;
        }

        $normalized = mb_strtolower($last);

        if ($this->visitorNeedsGuidedHelp($conv)) {
            return false;
        }

        return (bool) preg_match(
            '/(n[aã]o\s*abre|nao\s*abre|n[aã]o\s*funcion|nao\s*funcion|nada\s*deu\s*certo|nada\s*funciona|fiz\s*(isso\s*)?tudo|tentei|n[aã]o\s*deu|nao\s*deu|mesmo\s*assim|continua\s*sem|ainda\s*n[aã]o|ainda\s*nao|sem\s*p[aá]gina|n[aã]o\s*aparec|nao\s*aparec|n[aã]o\s*consig|nao\s*consig|n[aã]o\s*conect|nao\s*conect|n[aã]o\s*libera|nao\s*libera|sem\s*acesso)/u',
            $normalized
        );
    }

    private function visitorNeedsGuidedHelp(ChatConversation $conv): bool
    {
        $last = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->value('message');

        if (!$last) {
            return false;
        }

        $normalized = mb_strtolower($last);

        return (bool) preg_match(
            '/(n[aã]o\s*entendi|nao\s*entendi|como\s*(desligo|desligar|fa[cç]o|pago|abro|entro|continuo|continuar|continar)|me\s*explica|explica\s*(melhor|de\s*novo)|o\s*que\s*[eé]\s*(o\s*)?4g|dados\s*m[oó]veis|desligar\s*(o\s*)?4g)/u',
            $normalized
        );
    }

    private function visitorClaimsPaid(ChatConversation $conv): bool
    {
        $texts = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('message')
            ->implode(' ');

        $normalized = mb_strtolower($texts);

        return (bool) preg_match(
            '/\b(paguei|ja\s*paguei|já\s*paguei|fiz\s*o\s*pix|paguei\s*o\s*pix|pagamento\s*feito|enviei\s*o\s*comprovante)\b/u',
            $normalized
        );
    }

    private function visitorAskedForHuman(ChatConversation $conv): bool
    {
        $last = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->value('message');

        if (!$last) {
            return false;
        }

        $normalized = mb_strtolower($last);

        return (bool) preg_match(
            '/\b(atendente|humano|pessoa\s*real|falar\s*com\s*(alguém|alguem|atendente)|quero\s*atendente)\b/u',
            $normalized
        );
    }

    /**
     * Acabou de responder iOS/iPhone/Android e ainda não pedimos/recebemos o MAC.
     */
    private function shouldRequestMacAfterDeviceAnswer(ChatConversation $conv): bool
    {
        if ($this->alreadyCollectedMac($conv) || $this->alreadyRequestedMac($conv)) {
            return false;
        }

        if ($this->visitorAskedForHuman($conv)) {
            return false;
        }

        return $this->visitorJustAnsweredDevice($conv);
    }

    private function visitorJustAnsweredDevice(ChatConversation $conv): bool
    {
        $last = mb_strtolower(trim((string) $this->lastVisitorMessage($conv)));
        if ($last === '') {
            return false;
        }

        $mentionsDevice = (bool) preg_match(
            '/\b(iphone|ios|android|samsung|xiaomi|motorola|moto|redmi|galaxy)\b/u',
            $last
        );

        if (!$mentionsDevice) {
            return false;
        }

        if ($this->alreadyAskedDevice($conv)) {
            return true;
        }

        // Resposta curta do tipo "é iphone" / "android" mesmo se a pergunta não casou 100%
        return mb_strlen($last) <= 80;
    }

    /**
     * Próximo passo da coleta de MAC: perguntar aparelho, pedir foto, ou lembrar o botão.
     */
    private function nextMacCollectionDecision(ChatConversation $conv): ?array
    {
        if ($this->alreadyCollectedMac($conv)) {
            return null;
        }

        $device = $this->visitorDeviceFromHistory($conv);

        if ($device === null) {
            return [
                'action' => 'reply',
                'message' => 'Beleza. Às vezes o WiFi libera outro aparelho. Pra te ajudar no passo certo, você está usando iOS (iPhone) ou Android?',
            ];
        }

        if ($this->alreadyRequestedMac($conv)) {
            return [
                'action' => 'reply',
                'message' => 'O botão pra enviar a foto do MAC já está na conversa acima. Manda a foto da tela ou escreve o código (tipo AA:BB:CC:DD:EE:FF) que eu passo pro meu colega liberar.',
            ];
        }

        return [
            'action' => 'request_mac',
            'message' => $this->macInstructionsMessage($device),
        ];
    }

    private function macInstructionsMessage(string $device): string
    {
        if ($device === 'ios') {
            return "Beleza! Às vezes o pagamento libera outro aparelho. Preciso do MAC da rede deste iPhone.\n\n"
                . "1) Abre Ajustes → Wi-Fi\n\n"
                . "2) Toca no (i) azul ao lado de TocantinsTransporteWiFi\n\n"
                . "3) Procura Endereço Wi-Fi ou Endereço MAC\n\n"
                . "Manda uma foto dessa tela pelo botão abaixo, ou escreve o código aqui (tipo AA:BB:CC:DD:EE:FF).";
        }

        if ($device === 'android') {
            return "Beleza! Às vezes o pagamento libera outro aparelho. Preciso do MAC da rede deste Android.\n\n"
                . "1) Abre Configurações → Wi-Fi\n\n"
                . "2) Toca na rede TocantinsTransporteWiFi (ou em detalhes / i)\n\n"
                . "3) Procura Endereço MAC ou MAC do dispositivo\n\n"
                . "Manda uma foto dessa tela pelo botão abaixo, ou escreve o código aqui (tipo AA:BB:CC:DD:EE:FF).";
        }

        return "Beleza! Às vezes o pagamento libera outro aparelho. Preciso do MAC da rede.\n\n"
            . "iPhone: Ajustes → Wi-Fi → toca no (i) azul ao lado de TocantinsTransporteWiFi → Endereço Wi-Fi / MAC.\n\n"
            . "Android: Configurações → Wi-Fi → toca na rede TocantinsTransporteWiFi (ou detalhes) → Endereço MAC.\n\n"
            . "Manda uma foto dessa tela pelo botão abaixo, ou escreve o código aqui (tipo AA:BB:CC:DD:EE:FF).";
    }

    /**
     * ios | android | both | null
     */
    private function visitorDeviceFromHistory(ChatConversation $conv): ?string
    {
        $texts = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->limit(12)
            ->pluck('message')
            ->implode(' ');

        $normalized = mb_strtolower($texts);
        $ios = (bool) preg_match('/\b(iphone|ios|apple)\b/u', $normalized);
        $android = (bool) preg_match('/\b(android|samsung|xiaomi|motorola|moto|redmi|galaxy)\b/u', $normalized);

        if ($ios && !$android) {
            return 'ios';
        }
        if ($android && !$ios) {
            return 'android';
        }

        $last = mb_strtolower((string) $this->lastVisitorMessage($conv));
        if ($this->alreadyAskedDevice($conv) && preg_match('/n[aã]o\s*sei|nao\s*sei/u', $last)) {
            return 'both';
        }

        return null;
    }

    private function alreadyAskedDevice(ChatConversation $conv): bool
    {
        $adminMsgs = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'admin')
            ->orderByDesc('id')
            ->limit(12)
            ->pluck('message');

        foreach ($adminMsgs as $msg) {
            $t = mb_strtolower((string) $msg);
            if (preg_match('/(ios|iphone).{0,40}(android)|(android).{0,40}(ios|iphone)/u', $t)) {
                return true;
            }
        }

        return false;
    }

    private function alreadyRequestedMac(ChatConversation $conv): bool
    {
        return ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'mac_request')
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();
    }

    private function alreadyUploadedReceipt(ChatConversation $conv): bool
    {
        return ChatMessage::where('conversation_id', $conv->id)
            ->where('type', 'receipt_upload')
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();
    }

    private function alreadyCollectedMac(ChatConversation $conv): bool
    {
        $uploaded = ChatMessage::where('conversation_id', $conv->id)
            ->whereIn('type', ['mac_upload', 'mac_text'])
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        return $uploaded || $this->visitorTypedMacAfterRequest($conv);
    }

    private function collectedMacAddress(ChatConversation $conv): ?string
    {
        $typed = self::extractMacAddress($this->lastVisitorMessage($conv));
        if ($typed) {
            return $typed;
        }

        $msg = ChatMessage::where('conversation_id', $conv->id)
            ->whereIn('type', ['mac_upload', 'mac_text'])
            ->orderByDesc('id')
            ->first();

        return $msg->metadata['mac_address'] ?? null;
    }

    private function visitorTypedMacAfterRequest(ChatConversation $conv): bool
    {
        if (!$this->alreadyRequestedMac($conv)) {
            return false;
        }

        return self::extractMacAddress($this->lastVisitorMessage($conv)) !== null;
    }

    private function lastVisitorMessage(ChatConversation $conv): ?string
    {
        return ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->value('message');
    }

    public static function extractMacAddress(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        if (preg_match('/\b(?:[0-9A-Fa-f]{2}[:\-\.]){5}[0-9A-Fa-f]{2}\b/', $text, $m)) {
            $raw = preg_replace('/[^0-9A-Fa-f]/', '', $m[0]);
            return strtoupper(implode(':', str_split($raw, 2)));
        }

        if (preg_match('/\b[0-9A-Fa-f]{12}\b/', $text, $m)) {
            return strtoupper(implode(':', str_split($m[0], 2)));
        }

        return null;
    }
}
