import React from 'react';
import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

// 285 frames = 9.5s — audio 7.8s + 1.7s padding

/** Slide 1: Apresentação — fundo azul-verde gradiente com logo e slogan */
const AdSlide1: React.FC<{ progress: number; frame: number }> = ({ progress, frame }) => {
  const opacity = interpolate(progress, [0, 0.08, 0.28, 0.33], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleX = interpolate(progress, [0, 0.12], [-160, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const stars = Array.from({ length: 18 }, (_, i) => ({
    x: ((i * 137.5) % 100),
    y: ((i * 73.1) % 100),
    size: 1.5 + (i % 3),
    pulse: Math.sin((frame / 20) + i) * 0.4 + 0.6,
  }));
  return (
    <div style={{ position: 'absolute', inset: 0, opacity, background: 'linear-gradient(135deg, #0D2137 0%, #1B4332 50%, #0D2137 100%)' }}>
      {/* Starfield */}
      {stars.map((s, i) => (
        <div key={i} style={{ position: 'absolute', left: `${s.x}%`, top: `${s.y}%`, width: s.size, height: s.size, borderRadius: '50%', background: 'white', opacity: s.pulse * 0.7 }} />
      ))}
      {/* Tocantins skyline silhouette */}
      <svg style={{ position: 'absolute', bottom: 0, left: 0, right: 0, width: '100%', height: '38%', opacity: 0.25 }} viewBox="0 0 900 300" preserveAspectRatio="none">
        <path d="M0,300 L0,180 Q50,160 80,140 L100,100 L120,140 Q160,130 200,150 L250,80 L280,150 Q320,120 380,160 L420,200 L450,160 Q500,140 560,170 L600,120 L640,170 Q700,140 750,160 L800,180 Q840,160 880,140 L900,160 L900,300 Z" fill="rgba(255,255,255,1)" />
      </svg>
      <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 14 }}>
        <div style={{ transform: `translateX(${titleX}px)`, textAlign: 'center' }}>
          <div style={{ fontSize: 52, fontWeight: 900, color: 'white', letterSpacing: -1, lineHeight: 1.1, textShadow: '0 4px 20px rgba(0,0,0,0.6)' }}>
            WiFi Tocantins Express
          </div>
          <div style={{ fontSize: 26, color: 'rgba(255,255,255,0.85)', fontWeight: 500, marginTop: 8 }}>
            Internet Starlink no seu ônibus
          </div>
        </div>
        {/* Starlink badge */}
        <div style={{ background: 'rgba(255,255,255,0.15)', border: '1.5px solid rgba(255,255,255,0.3)', borderRadius: 14, paddingLeft: 20, paddingRight: 20, paddingTop: 8, paddingBottom: 8, display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#00E676', boxShadow: '0 0 8px #00E676' }} />
          <span style={{ fontSize: 20, fontWeight: 700, color: 'white' }}>Powered by Starlink</span>
        </div>
      </div>
    </div>
  );
};

/** Slide 2: Velocidade — fundo escuro com contador animado */
const AdSlide2: React.FC<{ progress: number; frame: number }> = ({ progress, frame }) => {
  const opacity = interpolate(progress, [0.30, 0.38, 0.60, 0.65], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const speedProgress = interpolate(progress, [0.32, 0.58], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });
  const speedMbps = Math.round(speedProgress * 120);
  const bars = [0.3, 0.5, 0.7, 0.85, 1.0];
  return (
    <div style={{ position: 'absolute', inset: 0, opacity, background: 'linear-gradient(160deg, #0a0e1a 0%, #0d1f0d 100%)' }}>
      <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 20 }}>
        {/* Speed dial */}
        <div style={{ position: 'relative' }}>
          <div style={{ width: 220, height: 220, borderRadius: '50%', border: `6px solid ${BRAND.green}30`, background: 'rgba(255,255,255,0.04)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', boxShadow: `0 0 40px ${BRAND.green}30` }}>
            <div style={{ fontSize: 72, fontWeight: 900, color: BRAND.green, lineHeight: 1, textShadow: `0 0 20px ${BRAND.green}80` }}>
              {speedMbps}
            </div>
            <div style={{ fontSize: 22, color: 'rgba(255,255,255,0.6)', fontWeight: 600 }}>Mbps</div>
          </div>
          {/* Rotating ring */}
          <div style={{ position: 'absolute', inset: -8, borderRadius: '50%', border: `3px solid transparent`, borderTop: `3px solid ${BRAND.green}`, transform: `rotate(${frame * 3}deg)` }} />
        </div>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 32, fontWeight: 900, color: 'white', letterSpacing: -0.5 }}>Alta velocidade garantida</div>
          <div style={{ fontSize: 20, color: 'rgba(255,255,255,0.65)', marginTop: 6, fontWeight: 500 }}>Streaming, redes sociais e muito mais</div>
        </div>
        {/* Signal bars */}
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 6, height: 40 }}>
          {bars.map((h, i) => (
            <div key={i} style={{
              width: 20, height: `${h * 100}%`,
              background: speedProgress > (i / bars.length)
                ? `linear-gradient(180deg, ${BRAND.greenLight}, ${BRAND.green})`
                : 'rgba(255,255,255,0.15)',
              borderRadius: 3,
              boxShadow: speedProgress > (i / bars.length) ? `0 0 8px ${BRAND.green}60` : 'none',
            }} />
          ))}
        </div>
      </div>
    </div>
  );
};

/** Slide 3: Planos — card do plano com CTA */
const AdSlide3: React.FC<{ progress: number }> = ({ progress }) => {
  const opacity = interpolate(progress, [0.62, 0.70, 0.95, 1.0], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const scaleIn = interpolate(progress, [0.62, 0.72], [0.85, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });
  return (
    <div style={{ position: 'absolute', inset: 0, opacity, background: 'linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%)' }}>
      <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 18 }}>
        <div style={{ fontSize: 28, fontWeight: 700, color: 'rgba(255,255,255,0.85)' }}>Conecte-se agora</div>
        <div style={{ transform: `scale(${scaleIn})`, background: 'white', borderRadius: 24, padding: '24px 44px', textAlign: 'center', boxShadow: '0 20px 60px rgba(0,0,0,0.35)' }}>
          <div style={{ fontSize: 20, color: '#888', fontWeight: 600, marginBottom: 4 }}>Viagem completa</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, justifyContent: 'center', marginBottom: 4 }}>
            <span style={{ fontSize: 20, color: '#BBB', textDecoration: 'line-through', fontWeight: 500 }}>R$24,47</span>
            <span style={{ background: '#D32F2F', color: 'white', fontWeight: 900, fontSize: 14, borderRadius: 6, padding: '2px 7px' }}>-71%</span>
          </div>
          <div style={{ fontSize: 58, fontWeight: 900, color: BRAND.green, lineHeight: 1, letterSpacing: -2 }}>R$6,99</div>
          <div style={{ fontSize: 18, color: '#999', fontWeight: 500, marginTop: 6 }}>WiFi até o destino final</div>
        </div>
        <div style={{ display: 'flex', gap: 20 }}>
          {['Instagram', 'WhatsApp', 'YouTube'].map((app) => (
            <div key={app} style={{ background: 'rgba(255,255,255,0.15)', borderRadius: 10, paddingLeft: 14, paddingRight: 14, paddingTop: 7, paddingBottom: 7 }}>
              <span style={{ fontSize: 17, fontWeight: 700, color: 'white' }}>{app}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export const VideoAdScene: React.FC = () => {
  const frame = useCurrentFrame();

  // 285f = 9.5s — progress bar covers full video duration
  const progress = interpolate(frame, [20, 270], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.linear });
  const progressPct = Math.round(progress * 100);
  const secondsLeft = Math.max(0, Math.ceil(42 * (1 - progress)));

  const sceneOpacity = interpolate(frame, [0, 14], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const discountOpacity = interpolate(frame, [262, 278], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const togglePulse = Math.sin(frame / 15) * 0.05 + 1;

  return (
    <AbsoluteFill style={{ background: '#0D0D0D', display: 'flex', flexDirection: 'column', fontFamily, overflow: 'hidden', opacity: sceneOpacity }}>

      {/* === TOP BAR === */}
      <div style={{ background: `linear-gradient(90deg, ${BRAND.greenDeep} 0%, ${BRAND.green} 100%)`, display: 'flex', alignItems: 'center', gap: 14, paddingLeft: 28, paddingRight: 28, paddingTop: 22, paddingBottom: 22, flexShrink: 0 }}>
        <div style={{ width: 44, height: 44, borderRadius: '50%', background: 'rgba(255,255,255,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3" /></svg>
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 24, fontWeight: 800, color: 'white', lineHeight: 1.1 }}>Assista até o final</div>
          <div style={{ fontSize: 18, color: 'rgba(255,255,255,0.8)', fontWeight: 500 }}>Desconto liberado ao terminar</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: 'rgba(255,255,255,0.15)', border: '1.5px solid rgba(255,255,255,0.25)', borderRadius: 10, paddingLeft: 16, paddingRight: 16, paddingTop: 8, paddingBottom: 8 }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
            <polygon points="13 19 22 12 13 5 13 19" /><line x1="2" y1="19" x2="2" y2="5" />
          </svg>
          <span style={{ fontSize: 20, fontWeight: 700, color: 'rgba(255,255,255,0.7)' }}>Pular</span>
        </div>
      </div>

      {/* === PROGRESS BAR === */}
      <div style={{ background: '#1A1A1A', paddingLeft: 28, paddingRight: 28, paddingTop: 14, paddingBottom: 14, display: 'flex', alignItems: 'center', gap: 16, flexShrink: 0 }}>
        <div style={{ transform: `scale(${togglePulse})`, width: 52, height: 28, borderRadius: 14, background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'flex-end', paddingRight: 3, boxShadow: `0 0 12px ${BRAND.green}60`, flexShrink: 0 }}>
          <div style={{ width: 22, height: 22, borderRadius: '50%', background: 'white', boxShadow: '0 1px 6px rgba(0,0,0,0.25)' }} />
        </div>
        <div style={{ flex: 1, height: 8, background: 'rgba(255,255,255,0.12)', borderRadius: 4, overflow: 'hidden' }}>
          <div style={{ height: '100%', width: `${progressPct}%`, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})`, borderRadius: 4, boxShadow: `0 0 8px ${BRAND.green}80` }} />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexShrink: 0 }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" strokeWidth="2" strokeLinecap="round">
            <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
          </svg>
          <span style={{ fontSize: 22, fontWeight: 700, color: 'rgba(255,255,255,0.8)', fontVariantNumeric: 'tabular-nums' }}>
            0:{secondsLeft.toString().padStart(2, '0')}
          </span>
        </div>
      </div>

      {/* === VIDEO CONTENT === */}
      <div style={{ flex: 1, position: 'relative', overflow: 'hidden' }}>
        <AdSlide1 progress={progress} frame={frame} />
        <AdSlide2 progress={progress} frame={frame} />
        <AdSlide3 progress={progress} />

        {/* Logo overlay top-right */}
        <div style={{ position: 'absolute', top: 20, right: 20, zIndex: 10 }}>
          <div style={{ background: 'rgba(0,0,0,0.55)', borderRadius: 14, padding: '10px 14px', display: 'flex', alignItems: 'center', gap: 8 }}>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
              </svg>
            </div>
            <span style={{ fontSize: 16, fontWeight: 700, color: 'white', letterSpacing: 0.3 }}>Tocantins WiFi</span>
          </div>
        </div>
      </div>

      {/* === DESCONTO APLICADO === */}
      {frame >= 260 && (
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.70)', opacity: discountOpacity }}>
          <div style={{ background: `linear-gradient(135deg, ${BRAND.greenDeep} 0%, ${BRAND.green} 100%)`, borderRadius: 30, paddingLeft: 60, paddingRight: 60, paddingTop: 48, paddingBottom: 48, textAlign: 'center', boxShadow: `0 24px 64px ${BRAND.green}60` }}>
            <div style={{ fontSize: 70, marginBottom: 14 }}>✅</div>
            <div style={{ fontSize: 48, fontWeight: 900, color: 'white', letterSpacing: -1.5 }}>Desconto aplicado!</div>
            <div style={{ fontSize: 30, color: 'rgba(255,255,255,0.85)', marginTop: 10, fontWeight: 500 }}>
              Plano: <strong>R$5,99</strong> — economizou R$1,00
            </div>
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};
