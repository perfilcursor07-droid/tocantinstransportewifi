import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const VideoAdScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // Total duration: 180 frames = 6s
  // Progress: 0% → 100% over frames 35 → 165
  const progress = interpolate(frame, [35, 165], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.inOut(Easing.quad) });
  const progressPercent = Math.round(progress * 100);

  // Timer: 42s → 0s over same window
  const secondsLeft = Math.ceil(42 * (1 - progress));

  const headerOpacity = interpolate(frame, [0, 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const playerOpacity = interpolate(frame, [10, 32], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const playerScale = interpolate(frame, [10, 35], [0.92, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.3)) });

  // Skip button appears (but user can't use it - tutorial shows it staying)
  const skipOpacity = interpolate(frame, [50, 68], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // "Desconto aplicado!" badge at the end
  const discountOpacity = interpolate(frame, [168, 180], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const discountScale = spring({ fps, frame: Math.max(0, frame - 168), config: { damping: 10, stiffness: 280, mass: 0.4 }, durationInFrames: 20 });

  // Mock video content — animated blocks simulating a playing ad
  const videoFrame = frame - 35;
  const scanLine = videoFrame > 0 ? (videoFrame * 8) % 640 : 0;

  return (
    <AbsoluteFill style={{ background: '#0A0A0A', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>

      {/* Header */}
      <div style={{ opacity: headerOpacity, position: 'absolute', top: 80, left: 0, right: 0, paddingLeft: 60, paddingRight: 60, display: 'flex', alignItems: 'center', gap: 18 }}>
        <div style={{ width: 12, height: 12, borderRadius: '50%', background: '#FF3B30', boxShadow: '0 0 8px #FF3B30' }} />
        <span style={{ fontSize: 28, fontWeight: 700, color: 'rgba(255,255,255,0.8)', letterSpacing: 1 }}>
          ASSISTINDO ANÚNCIO
        </span>
        <div style={{ flex: 1 }} />
        <div style={{ opacity: skipOpacity, background: 'rgba(255,255,255,0.12)', border: '1.5px solid rgba(255,255,255,0.2)', borderRadius: 10, paddingLeft: 18, paddingRight: 18, paddingTop: 8, paddingBottom: 8 }}>
          <span style={{ fontSize: 22, color: 'rgba(255,255,255,0.5)', fontWeight: 600 }}>Sem pular —</span>
          <span style={{ fontSize: 22, color: 'rgba(255,255,255,0.7)', fontWeight: 700 }}> {secondsLeft}s</span>
        </div>
      </div>

      {/* Video player */}
      <div style={{ opacity: playerOpacity, transform: `scale(${playerScale})`, width: 920, position: 'relative' }}>

        {/* Video frame */}
        <div style={{ width: '100%', height: 540, borderRadius: 20, overflow: 'hidden', background: '#111', position: 'relative', border: '2px solid rgba(255,255,255,0.08)' }}>
          {/* Mock ad video content */}
          <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(135deg, #1a2a1a 0%, #0d1f0d 100%)' }} />

          {/* Animated content elements */}
          <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 20 }}>
            {/* WiFi logo mockup */}
            <div style={{ opacity: Math.min(1, progress * 3), transform: `scale(${0.8 + progress * 0.2})` }}>
              <div style={{ width: 90, height: 90, borderRadius: '50%', background: `linear-gradient(135deg, ${BRAND.greenLight}, ${BRAND.greenDeep})`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: `0 8px 24px ${BRAND.green}60` }}>
                <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                  <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                  <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                  <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
                </svg>
              </div>
            </div>
            <div style={{ opacity: Math.min(1, progress * 2), textAlign: 'center' }}>
              <div style={{ fontSize: 30, fontWeight: 800, color: 'white', letterSpacing: -0.5 }}>WiFi Tocantins Express</div>
              <div style={{ fontSize: 20, color: 'rgba(255,255,255,0.65)', marginTop: 6 }}>Starlink • Alta velocidade</div>
            </div>
            {/* Animated bars */}
            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end', height: 60, opacity: Math.min(1, progress * 4) }}>
              {[0.4, 0.65, 0.9, 0.75, 0.55, 0.8, 0.45].map((h, i) => {
                const barH = h + Math.sin((frame / 12 + i) * Math.PI) * 0.15;
                return (
                  <div key={i} style={{ width: 14, height: 60 * barH, borderRadius: 4, background: `linear-gradient(to top, ${BRAND.greenDeep}, ${BRAND.greenLight})`, opacity: 0.85 }} />
                );
              })}
            </div>
          </div>

          {/* Scan line effect */}
          {videoFrame > 0 && progress < 0.98 && (
            <div style={{ position: 'absolute', top: scanLine % 520, left: 0, right: 0, height: 2, background: 'linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent)', pointerEvents: 'none' }} />
          )}

          {/* Ad overlay badge */}
          <div style={{ position: 'absolute', top: 16, left: 16, background: 'rgba(0,0,0,0.65)', borderRadius: 8, paddingLeft: 12, paddingRight: 12, paddingTop: 5, paddingBottom: 5 }}>
            <span style={{ fontSize: 18, color: 'rgba(255,255,255,0.7)', fontWeight: 600, letterSpacing: 1 }}>ANÚNCIO</span>
          </div>
        </div>

        {/* Progress bar */}
        <div style={{ marginTop: 20, position: 'relative' }}>
          <div style={{ height: 10, background: 'rgba(255,255,255,0.12)', borderRadius: 5, overflow: 'hidden' }}>
            <div style={{
              height: '100%',
              width: `${progressPercent}%`,
              background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})`,
              borderRadius: 5,
              transition: 'none',
              boxShadow: `0 0 12px ${BRAND.green}80`,
            }} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 10 }}>
            <span style={{ fontSize: 22, color: 'rgba(255,255,255,0.55)', fontWeight: 500 }}>
              {progressPercent}% concluído
            </span>
            <span style={{ fontSize: 22, color: 'rgba(255,255,255,0.55)', fontWeight: 500 }}>
              {secondsLeft > 0 ? `${secondsLeft}s restantes` : 'Concluído!'}
            </span>
          </div>
        </div>

        {/* Discount info below player */}
        <div style={{ marginTop: 28, background: 'rgba(0,163,53,0.12)', border: `1.5px solid ${BRAND.green}35`, borderRadius: 16, paddingLeft: 30, paddingRight: 30, paddingTop: 18, paddingBottom: 18, display: 'flex', alignItems: 'center', gap: 16 }}>
          <span style={{ fontSize: 28 }}>🎁</span>
          <div>
            <div style={{ fontSize: 24, fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>Recompensa ao finalizar</div>
            <div style={{ fontSize: 21, color: BRAND.greenLight, fontWeight: 600 }}>R$1,00 de desconto — pague apenas R$5,99</div>
          </div>
        </div>
      </div>

      {/* Desconto aplicado! overlay at the end */}
      {frame >= 168 && (
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.6)', opacity: discountOpacity }}>
          <div style={{ transform: `scale(${discountScale})`, background: `linear-gradient(135deg, ${BRAND.greenDeep}, ${BRAND.greenLight})`, borderRadius: 32, paddingLeft: 64, paddingRight: 64, paddingTop: 48, paddingBottom: 48, textAlign: 'center', boxShadow: `0 24px 64px rgba(0,163,53,0.5)` }}>
            <div style={{ fontSize: 72, marginBottom: 12 }}>✅</div>
            <div style={{ fontSize: 52, fontWeight: 900, color: 'white', letterSpacing: -1.5 }}>Desconto aplicado!</div>
            <div style={{ fontSize: 34, color: 'rgba(255,255,255,0.85)', marginTop: 10, fontWeight: 500 }}>
              Seu plano agora é <strong>R$5,99</strong>
            </div>
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};
