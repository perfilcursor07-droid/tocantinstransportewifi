"""
Gerador de narração com Google TTS (gTTS) — 100% gratuito
Gera os arquivos MP3 para cada cena do vídeo tutorial WiFi Tocantins
Uso: python scripts/generate-audio.py
"""

import os
import sys
import time

try:
    from gtts import gTTS
except ImportError:
    print("Instalando gTTS...")
    os.system("pip install gtts")
    from gtts import gTTS

ROOT   = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT    = os.path.join(ROOT, "public", "audio")

# Narração PT-BR para cada cena
NARRATIONS = [
    ("01-intro.mp3",
     "Bem-vindo ao WiFi Tocantins Express! "
     "Aproveite internet Starlink de alta velocidade durante toda a sua viagem de ônibus."),

    ("02-wifi.mp3",
     "Passo um: abra as configurações de WiFi do seu celular "
     "e conecte-se à rede Tocantins Transporte WiFi. Não é necessária senha!"),

    ("03-redirect.mp3",
     "Após conectar, o portal abrirá automaticamente no navegador. "
     "Caso não abra, escaneie o QR Code no banco da sua frente "
     "ou acesse o site tocantinstransportewifi ponto com ponto br."),

    ("04-plans.mp3",
     "Escolha o plano Viagem Completa por apenas seis reais e noventa e nove. "
     "WiFi liberado até o destino final, "
     "funcionando com Instagram, WhatsApp, YouTube, Netflix e muito mais!"),

    ("05-click.mp3",
     "Selecione o plano desejado e toque no botão verde: Acessar Internet Agora."),

    ("06-phone.mp3",
     "Uma janela abrirá pedindo seu número de telefone com DDD. "
     "Informe o seu número e toque em Gerar QR Code PIX para continuar."),

    ("07-discount.mp3",
     "Quer economizar? Assista um vídeo de quarenta e dois segundos "
     "e ganhe um real de desconto, pagando apenas cinco reais e noventa e nove!"),

    ("08-video.mp3",
     "Assista o vídeo até o final. "
     "Ao terminar, o desconto de um real é liberado automaticamente no seu plano."),

    ("09-pix.mp3",
     "Agora copie o código PIX. "
     "Ao copiar, um acesso temporário é liberado "
     "para você abrir o seu banco e efetuar o pagamento. "
     "Após confirmar, a internet é liberada automaticamente!"),

    ("10-outro.mp3",
     "Pronto! Boa viagem e boa conexão!"),
]

def main():
    os.makedirs(OUT, exist_ok=True)

    print(f"\nGoogle TTS - gerando {len(NARRATIONS)} arquivos de audio (PT-BR) ...\n")
    print(f"   Pasta: {OUT}\n")

    total_kb = 0

    for i, (fname, text) in enumerate(NARRATIONS, 1):
        dest = os.path.join(OUT, fname)

        if os.path.exists(dest):
            size = os.path.getsize(dest)
            print(f"PULANDO  {fname} ja existe ({size//1024} KB)")
            continue

        sys.stdout.write(f"[{i}/{len(NARRATIONS)}] Gerando {fname} ... ")
        sys.stdout.flush()

        try:
            tts = gTTS(text=text, lang="pt", tld="com.br", slow=False)
            tts.save(dest)
            size = os.path.getsize(dest)
            total_kb += size / 1024
            print(f"OK  {size//1024} KB")
        except Exception as e:
            print(f"ERRO: {e}")

        time.sleep(0.5)  # evitar rate-limit do Google

    print(f"\nConcluido! Total: ~{int(total_kb)} KB salvos em public/audio/\n")

if __name__ == "__main__":
    main()
