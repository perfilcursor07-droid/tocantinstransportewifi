/**
 * Servidor WhatsApp com Baileys
 * WiFi Tocantins - Sistema de envio automático de mensagens
 *
 * 🧩 MULTI-SESSÃO:
 *   - sessão "main"   -> número de PIX / confirmação / suporte (transacional, NUNCA bloqueia)
 *   - sessão "review" -> número separado SÓ para disparo de avaliação (onde mora o risco de ban)
 *
 *   Um ban no número de avaliação NÃO afeta o PIX, pois são números/credenciais diferentes.
 *   Todos os endpoints aceitam ?session=review (ou body.session); o padrão é "main"
 *   para manter compatibilidade com o código que já existe.
 */

const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const axios = require('axios');
const pino = require('pino');
const {
    default: makeWASocket,
    DisconnectReason,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const fs = require('fs');
const path = require('path');

// Configurações
const PORT = process.env.PORT || 3001;
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:8000/api/whatsapp/webhook';

// Logger
const logger = pino({ level: 'info' });

// Express App
const app = express();
app.use(cors());
app.use(express.json());

// ====== Estado por sessão ======
// Cada sessão tem credenciais (pasta própria) e estado de conexão independentes.
const SESSION_CONFIG = {
    main:   { authFolder: path.join(__dirname, 'auth_info') },        // PIX / transacional (compat: pasta antiga)
    review: { authFolder: path.join(__dirname, 'auth_info_review') }, // disparo de avaliação
};

function createSessionState(name, authFolder) {
    return {
        name,
        authFolder,
        sock: null,
        qrCode: null,
        connectionStatus: 'disconnected',
        connectedPhone: null,
        isConnecting: false,
        // Anti-ban: cache de existência por número (sonda no máximo 1x por número)
        existsCache: new Map(),
    };
}

const sessions = {};
for (const [name, cfg] of Object.entries(SESSION_CONFIG)) {
    sessions[name] = createSessionState(name, cfg.authFolder);
}

/**
 * Resolve o nome da sessão a partir de query/body. Default = "main".
 * Aceita apelidos para evitar erro: "pix"->main, "avaliacao"/"avaliação"->review.
 */
function resolveSessionName(raw) {
    const v = String(raw || '').toLowerCase().trim();
    if (v === 'review' || v === 'avaliacao' || v === 'avaliação' || v === 'aval') return 'review';
    if (v === 'main' || v === 'pix' || v === '' || v === 'default') return 'main';
    return sessions[v] ? v : 'main';
}

function getSession(raw) {
    return sessions[resolveSessionName(raw)];
}

// ====== Anti-ban: presença humana ======
function _rand(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

async function numberExists(S, jid) {
    if (S.existsCache.has(jid)) return S.existsCache.get(jid);
    try {
        const [res] = await S.sock.onWhatsApp(jid);
        const val = res?.exists ? res.jid : false;
        S.existsCache.set(jid, val);
        return val;
    } catch (e) {
        return false;
    }
}

// Simula "online + digitando" antes de enviar — parece humano e reduz sinal de bot.
// É best-effort: se falhar, segue o envio normalmente.
async function humanizeBeforeSend(S, jid, message) {
    try {
        await S.sock.sendPresenceUpdate('available');
        await S.sock.sendPresenceUpdate('composing', jid);
        const typingMs = Math.min(5000, 1000 + String(message || '').length * 35);
        await new Promise(r => setTimeout(r, _rand(900, typingMs)));
        await S.sock.sendPresenceUpdate('paused', jid);
    } catch (_) { /* presença é opcional */ }
}

/**
 * Notificar Laravel sobre mudanças de status (sempre marca a sessão de origem)
 */
async function notifyLaravel(session, type, data) {
    try {
        await axios.post(LARAVEL_WEBHOOK_URL, { type, session, data }, {
            timeout: 5000,
            headers: { 'Content-Type': 'application/json' }
        });
    } catch (error) {
        logger.warn(`[${session}] Não foi possível notificar Laravel:`, error.message);
    }
}

/**
 * Iniciar conexão com WhatsApp para UMA sessão
 */
async function startConnection(sessionName = 'main') {
    const S = sessions[sessionName] || sessions.main;

    if (S.isConnecting) {
        logger.info(`[${S.name}] Já está conectando...`);
        return;
    }

    S.isConnecting = true;
    S.connectionStatus = 'connecting';

    try {
        // Criar pasta de autenticação se não existir
        if (!fs.existsSync(S.authFolder)) {
            fs.mkdirSync(S.authFolder, { recursive: true });
        }

        // Carregar estado de autenticação
        const { state, saveCreds } = await useMultiFileAuthState(S.authFolder);

        // Obter versão mais recente do Baileys
        const { version } = await fetchLatestBaileysVersion();

        logger.info(`[${S.name}] Usando Baileys versão: ${version.join('.')}`);

        // Criar socket
        const sock = makeWASocket({
            version,
            logger: pino({ level: 'silent' }),
            printQRInTerminal: true,
            markOnlineOnConnect: false, // não ficar "sempre online" (parece menos robô)
            syncFullHistory: false,     // não baixar histórico inteiro ao conectar
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
            },
            generateHighQualityLinkPreview: true,
            getMessage: async (key) => {
                return { conversation: '' };
            }
        });
        S.sock = sock;

        // Eventos de conexão
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            // QR Code recebido
            if (qr) {
                logger.info(`[${S.name}] QR Code recebido`);
                S.qrCode = await QRCode.toDataURL(qr);
                S.connectionStatus = 'waiting_scan';

                await notifyLaravel(S.name, 'qr', { qrcode: S.qrCode });
            }

            // Conexão estabelecida
            if (connection === 'open') {
                logger.info(`[${S.name}] ✅ Conectado ao WhatsApp!`);
                S.connectionStatus = 'connected';
                S.qrCode = null;
                S.isConnecting = false;

                // Obter número conectado
                const user = sock.user;
                if (user) {
                    S.connectedPhone = user.id.split(':')[0].replace('@s.whatsapp.net', '');
                    logger.info(`[${S.name}] Número conectado: ${S.connectedPhone}`);
                }

                await notifyLaravel(S.name, 'connection', {
                    status: 'connected',
                    phone: S.connectedPhone
                });
            }

            // Conexão fechada
            if (connection === 'close') {
                const shouldReconnect = (lastDisconnect?.error instanceof Boom)
                    ? lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut
                    : true;

                logger.info(`[${S.name}] Conexão fechada. Reconectar: ${shouldReconnect}`);

                if (lastDisconnect?.error?.output?.statusCode === DisconnectReason.loggedOut) {
                    // Usuário deslogou - limpar credenciais
                    logger.info(`[${S.name}] Usuário deslogou. Limpando credenciais...`);
                    S.connectionStatus = 'disconnected';
                    S.connectedPhone = null;
                    S.qrCode = null;
                    S.isConnecting = false;
                    S.existsCache.clear();

                    // Remover pasta de autenticação
                    if (fs.existsSync(S.authFolder)) {
                        fs.rmSync(S.authFolder, { recursive: true, force: true });
                    }

                    await notifyLaravel(S.name, 'connection', { status: 'disconnected' });
                } else if (shouldReconnect) {
                    S.connectionStatus = 'reconnecting';
                    S.isConnecting = false;

                    // Tentar reconectar após 3 segundos
                    setTimeout(() => startConnection(S.name), 3000);
                }
            }
        });

        // Salvar credenciais quando atualizadas
        sock.ev.on('creds.update', saveCreds);

        // Eventos de mensagens (para status de entrega)
        sock.ev.on('messages.update', async (updates) => {
            for (const update of updates) {
                if (update.update.status) {
                    const statusMap = {
                        2: 'sent',
                        3: 'delivered',
                        4: 'read'
                    };

                    const status = statusMap[update.update.status];
                    if (status) {
                        await notifyLaravel(S.name, 'message_status', {
                            messageId: update.key.id,
                            status: status
                        });
                    }
                }
            }
        });

        // Mensagens RECEBIDAS de usuários
        sock.ev.on('messages.upsert', async ({ messages, type }) => {
            // 🛡️ Só processar mensagens "notify" (em tempo real, não histórico/sync)
            if (type !== 'notify') return;

            for (const msg of messages) {
                // Ignorar mensagens enviadas por nós
                if (msg.key.fromMe) continue;

                // Ignorar mensagens de status / broadcast
                if (msg.key.remoteJid === 'status@broadcast') continue;

                // Ignorar grupos
                if (msg.key.remoteJid && msg.key.remoteJid.endsWith('@g.us')) continue;

                // 🛡️ Ignorar reações (emojis em outras mensagens)
                if (msg.message?.reactionMessage) continue;

                // 🛡️ Ignorar mensagens de protocolo/edição/sistema
                if (msg.message?.protocolMessage) continue;
                if (msg.message?.senderKeyDistributionMessage) continue;

                // 🛡️ Ignorar mensagens muito antigas (mais de 5 minutos)
                // Isso evita processar mensagens vindas em sync histórico ao reconectar
                const msgTimestamp = msg.messageTimestamp ? Number(msg.messageTimestamp) : 0;
                const nowSec = Math.floor(Date.now() / 1000);
                if (msgTimestamp && (nowSec - msgTimestamp) > 300) {
                    logger.info(`[${S.name}][MSG IN] Ignorando mensagem antiga (${nowSec - msgTimestamp}s atrás)`);
                    continue;
                }

                // Extrair texto da mensagem
                let text = '';
                if (msg.message?.conversation) {
                    text = msg.message.conversation;
                } else if (msg.message?.extendedTextMessage?.text) {
                    text = msg.message.extendedTextMessage.text;
                } else if (msg.message?.imageMessage?.caption) {
                    text = msg.message.imageMessage.caption;
                } else if (msg.message?.videoMessage?.caption) {
                    text = msg.message.videoMessage.caption;
                } else {
                    continue; // Sem texto, ignorar
                }

                if (!text || text.trim() === '') continue;

                // Extrair número real do remetente
                let phone = '';
                let lid = '';
                const remoteJid = msg.key.remoteJid || '';

                if (remoteJid.endsWith('@s.whatsapp.net')) {
                    phone = remoteJid.replace('@s.whatsapp.net', '');
                } else if (remoteJid.endsWith('@lid')) {
                    lid = remoteJid.replace('@lid', '');

                    const senderPn = msg.key.senderPn || msg.key.participantPn || msg.key.participant || '';
                    if (senderPn) {
                        phone = senderPn.replace('@s.whatsapp.net', '').replace('@lid', '');
                    }

                    logger.info(`[${S.name}][MSG IN DEBUG @lid] key=${JSON.stringify(msg.key)} pushName=${msg.pushName || ''}`);
                } else {
                    continue;
                }

                phone = (phone || '').replace(/[^\d]/g, '');
                const phoneToSend = (phone && phone.length >= 10) ? phone : '';

                logger.info(`[${S.name}][MSG IN] de ${phoneToSend || lid + '@lid'}: ${text.substring(0, 80)}`);

                await notifyLaravel(S.name, 'message_in', {
                    phone: phoneToSend,
                    lid: lid,
                    pushName: msg.pushName || '',
                    message: text,
                    messageId: msg.key.id,
                    timestamp: msgTimestamp || nowSec,
                });
            }
        });

    } catch (error) {
        logger.error(`[${S.name}] Erro ao conectar:`, error);
        S.connectionStatus = 'error';
        S.isConnecting = false;
    }
}

/**
 * Verificar se número existe no WhatsApp
 */
async function checkNumberExists(S, phone) {
    if (!S.sock || S.connectionStatus !== 'connected') {
        return false;
    }

    try {
        const jid = phone.includes('@') ? phone : `${phone}@s.whatsapp.net`;
        const [result] = await S.sock.onWhatsApp(jid);
        return result?.exists || false;
    } catch (error) {
        logger.error(`[${S.name}] Erro ao verificar número ${phone}:`, error);
        return false;
    }
}

/**
 * Enviar mensagem
 */
async function sendMessage(S, phone, message, skipCheck = false, priority = false) {
    if (!S.sock || S.connectionStatus !== 'connected') {
        throw new Error(`WhatsApp (${S.name}) não está conectado`);
    }

    logger.info(`[${S.name}][SEND] Iniciando envio para: ${phone}${priority ? ' (prioritário)' : ''}`);

    let jid = phone.includes('@') ? phone : `${phone}@s.whatsapp.net`;

    try {
        // Confere existência no máximo 1x por número (cache) — evita sondar números em massa
        if (!skipCheck) {
            const resolved = await numberExists(S, jid);
            if (!resolved) {
                throw new Error(`Número ${phone} não possui WhatsApp`);
            }
            jid = resolved; // usa o JID no formato correto retornado pelo WhatsApp
        }

        // Mensagens transacionais (PIX/confirmação) saem na hora, sem delay de "digitando".
        // Só humanizamos os disparos em massa (avaliação), que é onde mora o risco de ban.
        if (!priority) {
            await humanizeBeforeSend(S, jid, message);
        }

        const sendResult = await S.sock.sendMessage(jid, { text: message });
        logger.info(`[${S.name}][SEND] ✅ Mensagem enviada para ${phone} - ID: ${sendResult.key.id}`);

        return {
            success: true,
            messageId: sendResult.key.id,
            jid
        };
    } catch (error) {
        logger.error(`[${S.name}][SEND] ❌ Erro: ${error.message}`);
        throw error;
    }
}

/**
 * Enviar documento
 */
async function sendDocument(S, phone, documentUrl, fileName, caption = '', skipCheck = false) {
    if (!S.sock || S.connectionStatus !== 'connected') {
        throw new Error(`WhatsApp (${S.name}) não está conectado`);
    }

    logger.info(`[${S.name}][DOC] Iniciando envio de documento para: ${phone}`);

    const payload = {
        document: { url: documentUrl },
        mimetype: 'application/pdf',
        fileName: fileName || 'documento.pdf',
        caption: caption || ''
    };

    let jid = phone.includes('@') ? phone : `${phone}@s.whatsapp.net`;

    try {
        if (!skipCheck) {
            const resolved = await numberExists(S, jid);
            if (!resolved) {
                throw new Error(`Número ${phone} não possui WhatsApp`);
            }
            jid = resolved;
        }

        await humanizeBeforeSend(S, jid, caption);

        const sendResult = await S.sock.sendMessage(jid, payload);
        logger.info(`[${S.name}][DOC] ✅ Documento enviado para ${phone} - ID: ${sendResult.key.id}`);

        return {
            success: true,
            messageId: sendResult.key.id,
            jid
        };
    } catch (error) {
        logger.error(`[${S.name}][DOC] ❌ Erro: ${error.message}`);
        throw error;
    }
}

/**
 * Desconectar UMA sessão
 */
async function disconnect(S) {
    if (S.sock) {
        try { await S.sock.logout(); } catch (_) {}
        S.sock = null;
    }

    S.connectionStatus = 'disconnected';
    S.connectedPhone = null;
    S.qrCode = null;
    S.isConnecting = false;
    S.existsCache.clear();

    // Limpar credenciais
    if (fs.existsSync(S.authFolder)) {
        fs.rmSync(S.authFolder, { recursive: true, force: true });
    }

    await notifyLaravel(S.name, 'connection', { status: 'disconnected' });
}

// ==================== ROTAS API ====================

// Status da conexão (?session=main|review)
app.get('/status', (req, res) => {
    const S = getSession(req.query.session);
    res.json({
        session: S.name,
        status: S.connectionStatus,
        phone: S.connectedPhone,
        isConnecting: S.isConnecting
    });
});

// Obter QR Code (?session=main|review)
app.get('/qrcode', async (req, res) => {
    const S = getSession(req.query.session);

    // Se já está conectado, retornar status
    if (S.connectionStatus === 'connected') {
        return res.json({
            session: S.name,
            status: 'connected',
            phone: S.connectedPhone,
            message: 'Já está conectado'
        });
    }

    // Se não está conectando, iniciar conexão
    if (!S.isConnecting && S.connectionStatus !== 'waiting_scan') {
        startConnection(S.name);
    }

    // Aguardar QR Code (máximo 30 segundos)
    let attempts = 0;
    const maxAttempts = 30;

    const checkQR = () => {
        return new Promise((resolve) => {
            const interval = setInterval(() => {
                attempts++;

                if (S.qrCode) {
                    clearInterval(interval);
                    resolve({ session: S.name, qrcode: S.qrCode, status: 'waiting_scan' });
                } else if (S.connectionStatus === 'connected') {
                    clearInterval(interval);
                    resolve({ session: S.name, status: 'connected', phone: S.connectedPhone });
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    resolve({ session: S.name, error: 'Timeout ao aguardar QR Code', status: S.connectionStatus });
                }
            }, 1000);
        });
    };

    const result = await checkQR();
    res.json(result);
});

// Verificar se número tem WhatsApp (?session=main|review)
app.get('/check/:phone', async (req, res) => {
    const { phone } = req.params;
    const S = getSession(req.query.session);

    if (!phone) {
        return res.status(400).json({ error: 'Phone é obrigatório' });
    }

    try {
        const exists = await checkNumberExists(S, phone);
        res.json({ session: S.name, phone, exists });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Enviar mensagem (body.session = main|review, default main)
app.post('/send', async (req, res) => {
    const { phone, message, skipCheck, priority, session } = req.body;
    const S = getSession(session);

    if (!phone || !message) {
        return res.status(400).json({ error: 'Phone e message são obrigatórios' });
    }

    try {
        const result = await sendMessage(S, phone, message, skipCheck, priority);
        res.json({ session: S.name, ...result });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Enviar documento (body.session = main|review, default main)
app.post('/send-document', async (req, res) => {
    const { phone, documentUrl, fileName, caption, skipCheck, session } = req.body;
    const S = getSession(session);

    if (!phone || !documentUrl) {
        return res.status(400).json({ error: 'Phone e documentUrl são obrigatórios' });
    }

    try {
        const result = await sendDocument(S, phone, documentUrl, fileName, caption, skipCheck);
        res.json({ session: S.name, ...result });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Desconectar (body.session ou ?session = main|review, default main)
app.post('/disconnect', async (req, res) => {
    const S = getSession(req.body?.session ?? req.query.session);
    try {
        await disconnect(S);
        res.json({ session: S.name, success: true, message: 'Desconectado com sucesso' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Reconectar (body.session ou ?session = main|review, default main)
app.post('/reconnect', async (req, res) => {
    const S = getSession(req.body?.session ?? req.query.session);
    try {
        if (S.sock) {
            try { await S.sock.end(); } catch (_) {}
            S.sock = null;
        }
        S.isConnecting = false;
        startConnection(S.name);
        res.json({ session: S.name, success: true, message: 'Reconectando...' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Health check (mostra as duas sessões)
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        uptime: process.uptime(),
        sessions: Object.fromEntries(
            Object.values(sessions).map(S => [S.name, {
                status: S.connectionStatus,
                phone: S.connectedPhone,
            }])
        )
    });
});

// ==================== INICIAR SERVIDOR ====================

app.listen(PORT, () => {
    logger.info(`🚀 Servidor WhatsApp rodando na porta ${PORT}`);
    logger.info(`📱 Acesse http://localhost:${PORT}/status para verificar status`);

    // Tentar conectar automaticamente cada sessão que já tenha credenciais salvas
    for (const S of Object.values(sessions)) {
        if (fs.existsSync(path.join(S.authFolder, 'creds.json'))) {
            logger.info(`[${S.name}] Credenciais encontradas. Conectando automaticamente...`);
            startConnection(S.name);
        } else {
            logger.info(`[${S.name}] Nenhuma credencial encontrada. Aguardando solicitação de QR Code...`);
        }
    }
});

// Tratamento de erros não capturados
process.on('uncaughtException', (error) => {
    logger.error('Erro não capturado:', error);
});

process.on('unhandledRejection', (error) => {
    logger.error('Promise rejeitada:', error);
});

// Graceful shutdown
process.on('SIGINT', async () => {
    logger.info('Encerrando servidor...');
    for (const S of Object.values(sessions)) {
        if (S.sock) {
            try { await S.sock.end(); } catch (_) {}
        }
    }
    process.exit(0);
});
