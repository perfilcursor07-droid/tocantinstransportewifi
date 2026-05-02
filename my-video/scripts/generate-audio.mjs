/**
 * Gerador de narração — ElevenLabs API
 * Gera os arquivos MP3 para cada cena do vídeo tutorial
 * Uso: node scripts/generate-audio.mjs
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');

const API_KEY  = 'sk_840e376878655186f6f1c262f8b1ba5f26bdf50c0c3ec592';
const VOICE_ID = '21m00Tcm4TlvDq8ikWAM'; // Rachel — voz gratuita, suporte multilíngue
const MODEL    = 'eleven_multilingual_v2';
const OUT_DIR  = path.join(ROOT, 'public', 'audio');

/** Texto de narração para cada cena (PT-BR) */
const NARRATIONS = [
  {
    file: '01-intro.mp3',
    text: 'Bem-vindo ao WiFi Tocantins Express! Aproveite internet Starlink de alta velocidade durante toda a sua viagem de ônibus.',
  },
  {
    file: '02-wifi.mp3',
    text: 'Passo um: abra as configurações de WiFi do seu celular e conecte-se à rede Tocantins Transporte WiFi. Não é necessária senha!',
  },
  {
    file: '03-redirect.mp3',
    text: 'Após conectar, o portal abrirá automaticamente no navegador. Caso não abra, escaneie o QR Code no banco da sua frente ou acesse o site tocantins transporte wifi ponto com ponto be erre.',
  },
  {
    file: '04-plans.mp3',
    text: 'Escolha o plano Viagem Completa por apenas seis reais e noventa e nove. WiFi liberado até o destino final, funcionando com Instagram, WhatsApp, YouTube, Netflix e muito mais.',
  },
  {
    file: '05-click.mp3',
    text: 'Selecione o plano desejado e toque no botão verde: Acessar Internet Agora.',
  },
  {
    file: '06-phone.mp3',
    text: 'Uma janela abrirá pedindo seu número de telefone com DDD. Informe seu número e toque em Gerar QR Code PIX para continuar.',
  },
  {
    file: '07-discount.mp3',
    text: 'Quer economizar? Assista um vídeo de quarenta e dois segundos e ganhe um real de desconto, pagando apenas cinco reais e noventa e nove!',
  },
  {
    file: '08-video.mp3',
    text: 'Assista o vídeo até o final. Ao terminar, o desconto de um real é liberado automaticamente no seu plano.',
  },
  {
    file: '09-pix.mp3',
    text: 'Agora copie o código PIX. Ao copiar, três minutos de internet grátis são liberados para você abrir o seu banco e efetuar o pagamento. Após confirmar, a internet é liberada automaticamente!',
  },
  {
    file: '10-outro.mp3',
    text: 'Pronto! Boa viagem e boa conexão!',
  },
];

/** Chama a API do ElevenLabs e retorna Buffer com o MP3 */
function callElevenLabs(text) {
  return new Promise((resolve, reject) => {
    const body = JSON.stringify({
      text,
      model_id: MODEL,
      voice_settings: {
        stability: 0.55,
        similarity_boost: 0.80,
        style: 0.0,
        use_speaker_boost: true,
      },
    });

    const options = {
      hostname: 'api.elevenlabs.io',
      path: `/v1/text-to-speech/${VOICE_ID}`,
      method: 'POST',
      headers: {
        'xi-api-key': API_KEY,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
        Accept: 'audio/mpeg',
      },
    };

    const req = https.request(options, (res) => {
      if (res.statusCode !== 200) {
        let err = '';
        res.on('data', (c) => (err += c));
        res.on('end', () => reject(new Error(`HTTP ${res.statusCode}: ${err}`)));
        return;
      }
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve(Buffer.concat(chunks)));
    });

    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

/** Aguarda ms milissegundos */
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
  if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

  console.log(`\n🎙  ElevenLabs — gerando ${NARRATIONS.length} arquivos de áudio\n`);
  console.log(`   Voz  : Rachel (${VOICE_ID})`);
  console.log(`   Model: ${MODEL}`);
  console.log(`   Pasta: ${OUT_DIR}\n`);

  let totalKB = 0;

  for (let i = 0; i < NARRATIONS.length; i++) {
    const { file, text } = NARRATIONS[i];
    const dest = path.join(OUT_DIR, file);

    if (fs.existsSync(dest)) {
      const size = fs.statSync(dest).size;
      console.log(`⏭  ${file} já existe (${(size / 1024).toFixed(1)} KB) — pulando`);
      continue;
    }

    process.stdout.write(`[${i + 1}/${NARRATIONS.length}] 🎤 Gerando ${file} ... `);

    try {
      const buf = await callElevenLabs(text);
      fs.writeFileSync(dest, buf);
      const kb = (buf.length / 1024).toFixed(1);
      totalKB += buf.length / 1024;
      console.log(`✅  ${kb} KB`);
    } catch (err) {
      console.log(`❌  ERRO: ${err.message}`);
    }

    // Pequena pausa entre requisições para evitar rate-limit
    if (i < NARRATIONS.length - 1) await sleep(600);
  }

  console.log(`\n✅  Concluído! Total gerado: ${totalKB.toFixed(0)} KB`);
  console.log(`\nPróximo passo: os arquivos já estão em public/audio/`);
  console.log(`A composição Remotion os carregará automaticamente.\n`);
}

main().catch((e) => {
  console.error('Erro fatal:', e);
  process.exit(1);
});
