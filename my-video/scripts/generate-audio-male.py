"""
Gerador de voz masculina PT-BR com Edge TTS (Microsoft, gratuito)
Voz: pt-BR-AntonioNeural (masculino, natural)
Uso: python scripts/generate-audio-male.py
"""
import asyncio
import os
import sys
import json

try:
    import edge_tts
except ImportError:
    os.system("pip install edge-tts")
    import edge_tts

try:
    from mutagen.mp3 import MP3
    HAS_MUTAGEN = True
except ImportError:
    os.system("pip install mutagen")
    from mutagen.mp3 import MP3
    HAS_MUTAGEN = True

ROOT    = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = os.path.join(ROOT, "public", "audio")
VOICE   = "pt-BR-AntonioNeural"  # masculino, natural

NARRATIONS = [
    ("01-intro.mp3",
     "Bem-vindo ao WiFi Tocantins Express! "
     "Aproveite internet Starlink de alta velocidade durante toda a sua viagem de onibus."),

    ("02-wifi.mp3",
     "Passo um: abra as configuracoes de WiFi do seu celular "
     "e conecte-se a rede Tocantins Transporte WiFi. Nao e necessaria senha!"),

    ("03-redirect.mp3",
     "Apos conectar, o portal abrira automaticamente no navegador. "
     "Caso nao abra, escaneie o QR Code no banco da sua frente "
     "ou acesse o site tocantins transporte wifi ponto com ponto be erre."),

    ("04-plans.mp3",
     "Escolha o plano Viagem Completa por apenas seis reais e noventa e nove. "
     "WiFi liberado ate o destino final, "
     "funcionando com Instagram, WhatsApp, YouTube, Netflix e muito mais!"),

    ("05-click.mp3",
     "Selecione o plano desejado e toque no botao verde: Acessar Internet Agora."),

    ("06-phone.mp3",
     "Uma janela abrira pedindo seu numero de telefone com DDD. "
     "Informe o seu numero e toque em Gerar QR Code PIX para continuar."),

    ("07-discount.mp3",
     "Quer economizar? Assista um video de quarenta e dois segundos "
     "e ganhe um real de desconto, pagando apenas cinco reais e noventa e nove!"),

    ("08-video.mp3",
     "Assista o video ate o final. "
     "Ao terminar, o desconto de um real e liberado automaticamente no seu plano."),

    ("09-pix.mp3",
     "Agora copie o codigo PIX. "
     "Ao copiar, tres minutos de internet gratis sao liberados "
     "para voce abrir o seu banco e efetuar o pagamento. "
     "Apos confirmar, a internet e liberada automaticamente!"),

    ("10-outro.mp3",
     "Pronto! Boa viagem e boa conexao!"),
]

async def generate_all():
    os.makedirs(OUT_DIR, exist_ok=True)
    print(f"Edge TTS - voz: {VOICE}")
    print(f"Pasta: {OUT_DIR}\n")

    durations = {}

    for fname, text in NARRATIONS:
        dest = os.path.join(OUT_DIR, fname)
        print(f"Gerando {fname} ...", end=" ", flush=True)
        try:
            communicate = edge_tts.Communicate(text, VOICE)
            await communicate.save(dest)
            dur = MP3(dest).info.length
            durations[fname] = round(dur, 3)
            print(f"OK  {os.path.getsize(dest)//1024} KB  {dur:.1f}s")
        except Exception as e:
            print(f"ERRO: {e}")

    timings_path = os.path.join(OUT_DIR, "timings.json")
    with open(timings_path, "w") as f:
        json.dump(durations, f, indent=2)
    print(f"\nTimings salvos em {timings_path}")

    fps = 30
    padding = 45  # 1.5s de respiro apos o audio
    print("\nDuracoes calculadas por cena (arredondado para multiplo de 15):")
    total_net = 0
    scene_durations = []
    for i, (fname, _) in enumerate(NARRATIONS):
        dur = durations.get(fname, 5.0)
        raw_frames = int(dur * fps) + padding
        frames = ((raw_frames + 14) // 15) * 15
        scene_durations.append(frames)
        print(f"  [{i+1:02d}] {fname}: audio={dur:.1f}s  ->  {frames}f ({frames/fps:.1f}s)")
        total_net += frames

    transitions = 9
    transition_frames = 15
    net = total_net - transitions * transition_frames
    print(f"\nTotal bruto:  {total_net}f")
    print(f"Total liquido (apos transicoes): {net}f = {net/fps:.1f}s")
    print(f"\nDurations array: {scene_durations}")

asyncio.run(generate_all())
