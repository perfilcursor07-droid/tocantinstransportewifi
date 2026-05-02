"""
Gera musica de fundo estilo tutorial (alegre, leve) em WAV.
Progressao: C - G - Am - F  |  118 BPM  |  ~110 segundos
Vozes: melodia marimba + pad de acordes + baixo
"""
import struct, wave, math, os
import numpy as np

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT  = os.path.join(ROOT, "public", "audio", "background.wav")
SR   = 44100
BPM  = 118
BEAT = 60.0 / BPM          # 0.508s por beat
BAR  = BEAT * 4            # 4 beats por compasso

# ---------- utilitarios ----------

def midi_to_freq(midi):
    return 440.0 * (2.0 ** ((midi - 69) / 12.0))

# Notas MIDI: C4=60, D4=62, E4=64, F4=65, G4=67, A4=69, B4=71, C5=72
NOTE = {n: midi_to_freq(m) for n, m in {
    'C2':36,'G2':43,
    'C3':48,'F3':53,'G3':55,'A3':57,
    'C4':60,'D4':62,'E4':64,'F4':65,'G4':67,'A4':69,'B4':71,
    'C5':72,'D5':74,'E5':76,'F5':77,'G5':79,'A5':81,
    'C6':84,
    'REST':0,
}.items()}

def adsr(n, sr, a=0.008, d=0.04, s=0.70, r=0.09):
    """Envelope ADSR — retorna array de n amostras."""
    env = np.ones(n) * s
    ai = min(int(sr * a), n)
    di = min(int(sr * d), n - ai)
    ri = min(int(sr * r), n)
    env[:ai]              = np.linspace(0, 1, ai)
    env[ai:ai+di]         = np.linspace(1, s, di)
    env[max(0,n-ri):]     = np.linspace(s, 0, min(ri, n))
    return env

def marimba(freq, dur, vol=0.55):
    """Marimba: sine + 2a harmonica com decay rapido."""
    if freq == 0:
        return np.zeros(int(SR * dur))
    n = int(SR * dur)
    t = np.linspace(0, dur, n, False)
    # marimba tem decay rapido
    decay_env = np.exp(-5.0 * t / dur)
    sig  = np.sin(2*np.pi * freq * t) * 0.80
    sig += np.sin(2*np.pi * freq * 2 * t) * 0.25 * np.exp(-8*t/dur)
    sig += np.sin(2*np.pi * freq * 4 * t) * 0.06
    return sig * adsr(n, SR, a=0.004, d=0.06, s=0.0, r=0.08) * decay_env * vol

def pad(freqs, dur, vol=0.20):
    """Pad suave: sine com ataque lento."""
    n = int(SR * dur)
    t = np.linspace(0, dur, n, False)
    sig = np.zeros(n)
    for f, w in freqs:
        if f > 0:
            sig += np.sin(2*np.pi * f * t) * w
            sig += np.sin(2*np.pi * f * 2 * t) * w * 0.15
    return sig * adsr(n, SR, a=0.06, d=0.10, s=0.75, r=0.12) * vol

def bass(freq, dur, vol=0.30):
    """Baixo: sine com ligeiro overdrive."""
    if freq == 0:
        return np.zeros(int(SR * dur))
    n = int(SR * dur)
    t = np.linspace(0, dur, n, False)
    sig = np.sin(2*np.pi * freq * t)
    sig += np.sin(2*np.pi * freq * 2 * t) * 0.18
    # soft clip
    sig = np.tanh(sig * 1.5) / 1.5
    return sig * adsr(n, SR, a=0.01, d=0.05, s=0.7, r=0.10) * vol

def kick(dur=0.18, vol=0.40):
    n = int(SR * dur)
    t = np.linspace(0, dur, n, False)
    freq_env = 180 * np.exp(-30 * t) + 60
    sig = np.sin(2*np.pi * np.cumsum(freq_env) / SR)
    env = np.exp(-20 * t)
    return sig * env * vol

def hihat(dur=0.04, vol=0.12):
    n = int(SR * dur)
    noise = np.random.randn(n) * 0.5
    # bandpass simples: remove graves
    from numpy.fft import rfft, irfft
    F = rfft(noise)
    freqs = np.fft.rfftfreq(n, 1/SR)
    F[freqs < 5000] = 0
    F[freqs > 12000] *= 0.3
    sig = irfft(F, n)
    env = np.exp(-80 * np.linspace(0, dur, n))
    return sig * env * vol

def snare(dur=0.12, vol=0.20):
    n = int(SR * dur)
    t = np.linspace(0, dur, n, False)
    tone  = np.sin(2*np.pi * 200 * t) * np.exp(-20 * t)
    noise = np.random.randn(n) * np.exp(-30 * t)
    return (tone * 0.5 + noise * 0.5) * vol

def mix_at(buf, offset, sig):
    if offset >= len(buf):
        return
    end = min(offset + len(sig), len(buf))
    chunk = end - offset
    buf[offset:end] += sig[:chunk]

# ---------- composicao ----------

TOTAL_SEC = 112
N = int(SR * TOTAL_SEC)
out = np.zeros(N)

# Acordes  (C - G - Am - F) em 2 compassos cada, 4 beats por compasso
# Pad: um acorde por compasso
CHORD_SEQ = [
    # C major
    [(NOTE['C3'],0.6),(NOTE['E4'],0.5),(NOTE['G4'],0.5),(NOTE['C5'],0.4)],
    [(NOTE['C3'],0.6),(NOTE['E4'],0.5),(NOTE['G4'],0.5),(NOTE['C5'],0.4)],
    # G major
    [(NOTE['G2'],0.6),(NOTE['D4'],0.5),(NOTE['G4'],0.5),(NOTE['B4'],0.4)],
    [(NOTE['G2'],0.6),(NOTE['D4'],0.5),(NOTE['G4'],0.5),(NOTE['B4'],0.4)],
    # Am
    [(NOTE['A3'],0.6),(NOTE['C4'],0.5),(NOTE['E4'],0.5),(NOTE['A4'],0.4)],
    [(NOTE['A3'],0.6),(NOTE['C4'],0.5),(NOTE['E4'],0.5),(NOTE['A4'],0.4)],
    # F major
    [(NOTE['F3'],0.6),(NOTE['F4'],0.5),(NOTE['A4'],0.5),(NOTE['C5'],0.4)],
    [(NOTE['F3'],0.6),(NOTE['F4'],0.5),(NOTE['A4'],0.5),(NOTE['C5'],0.4)],
]

BASS_SEQ = ['C3','C3','G2','G2','A3','A3','F3','F3']

# Melodia (8 compassos = 1 loop)
# Cada beat = BEAT segundos, melodia em colcheias (BEAT/2)
Q = BEAT      # quarter note
E = BEAT/2    # eighth note
H = BEAT*2    # half note

MELODY = [
    # Compasso 1 (C)
    ('E5',E),('G5',E),('E5',E),('C5',E),('D5',E),('E5',E),('G5',Q),
    # Compasso 2 (C)
    ('E5',E),('D5',E),('C5',E),('D5',E),('E5',Q),('C5',H),
    # Compasso 3 (G)
    ('D5',E),('B4',E),('D5',E),('G5',E),('F5',E),('E5',E),('D5',Q),
    # Compasso 4 (G)
    ('B4',E),('D5',E),('G5',E),('F5',E),('E5',Q),('D5',H),
    # Compasso 5 (Am)
    ('E5',E),('A5',E),('G5',E),('E5',E),('F5',E),('E5',E),('C5',Q),
    # Compasso 6 (Am)
    ('A4',E),('C5',E),('E5',E),('G5',E),('A5',Q),('E5',H),
    # Compasso 7 (F)
    ('F5',E),('A5',E),('G5',E),('F5',E),('E5',E),('D5',E),('C5',Q),
    # Compasso 8 (F)
    ('D5',E),('E5',E),('F5',E),('E5',E),('D5',Q),('C5',H),
]

LOOP_BARS  = 8
BAR_FRAMES = int(SR * BAR)
LOOP_FRAMES = BAR_FRAMES * LOOP_BARS

n_loops = math.ceil(TOTAL_SEC / (BAR * LOOP_BARS)) + 1

for loop_i in range(n_loops):
    loop_start = loop_i * LOOP_FRAMES

    # --- Pad de acordes ---
    for bar_i, chord_notes in enumerate(CHORD_SEQ):
        bar_start = loop_start + bar_i * BAR_FRAMES
        sig = pad(chord_notes, BAR * 0.95)
        mix_at(out, bar_start, sig)

    # --- Baixo (por compasso, 1 nota no beat 1) ---
    for bar_i, bass_note in enumerate(BASS_SEQ):
        bar_start = loop_start + bar_i * BAR_FRAMES
        sig = bass(NOTE[bass_note], BAR * 0.80)
        mix_at(out, bar_start, sig)

    # --- Melodia ---
    pos = loop_start
    for note_name, dur in MELODY:
        sig = marimba(NOTE[note_name], dur)
        mix_at(out, pos, sig)
        pos += int(SR * dur)

    # --- Ritmo: kick + snare + hihat ---
    for bar_i in range(LOOP_BARS):
        bar_start = loop_start + bar_i * BAR_FRAMES
        beat_f = int(SR * BEAT)
        # Kick: beat 1 e 3
        mix_at(out, bar_start,           kick())
        mix_at(out, bar_start + beat_f*2, kick())
        # Snare: beat 2 e 4
        mix_at(out, bar_start + beat_f,   snare())
        mix_at(out, bar_start + beat_f*3, snare())
        # Hi-hat: em cada 8a nota
        for hh_i in range(8):
            mix_at(out, bar_start + int(SR * BEAT/2 * hh_i), hihat())

# --- Fade in / out ---
fade_in  = int(SR * 1.5)
fade_out = int(SR * 3.0)
out[:fade_in] *= np.linspace(0, 1, fade_in)
out[-fade_out:] *= np.linspace(1, 0, fade_out)

# --- Normalizar e salvar ---
peak = np.max(np.abs(out))
if peak > 0:
    out = out / peak * 0.90

# Volume geral de 22% (discreta mas audivel no fundo)
out *= 0.22

pcm = np.clip(out * 32767, -32767, 32767).astype(np.int16)

with wave.open(OUT, 'w') as wf:
    wf.setnchannels(1)
    wf.setsampwidth(2)
    wf.setframerate(SR)
    wf.writeframes(struct.pack(f'<{len(pcm)}h', *pcm))

size_kb = os.path.getsize(OUT) // 1024
print(f"Musica tutorial gerada: {OUT}  ({size_kb} KB, {TOTAL_SEC}s)")
print(f"BPM: {BPM} | Progressao: C-G-Am-F | Vozes: melodia+pad+baixo+bateria")
