import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const Step2Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const badgeScale = spring({ fps, frame, config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 30 });
  const iconScale = spring({ fps, frame: Math.max(0, frame - 10), config: { damping: 13, stiffness: 140, mass: 0.8 }, durationInFrames: 40 });

  const titleOpacity = interpolate(frame, [22, 44], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [22, 44], [45, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const cardOpacity = interpolate(frame, [50, 72], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cardY = interpolate(frame, [50, 72], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cardScale = interpolate(frame, [50, 72], [0.92, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });

  const descOpacity = interpolate(frame, [80, 100], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const descY = interpolate(frame, [80, 100], [25, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  const arrowOpacity = interpolate(frame, [100, 120], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Animated WiFi waves
  const waveFrame = frame;
  const wave1 = interpolate(Math.sin((waveFrame * Math.PI) / 30), [-1, 1], [0.75, 1]);
  const wave2 = interpolate(Math.sin((waveFrame * Math.PI) / 30 + (Math.PI * 2) / 3), [-1, 1], [0.75, 1]);
  const wave3 = interpolate(Math.sin((waveFrame * Math.PI) / 30 + (Math.PI * 4) / 3), [-1, 1], [0.75, 1]);

  return (
    <AbsoluteFill style={{ background: BRAND.surface, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 70, paddingRight: 70 }}>

      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Step Badge */}
      <div style={{ transform: `scale(${badgeScale})`, marginBottom: 52 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, background: 'white', borderRadius: 60, paddingLeft: 28, paddingRight: 36, paddingTop: 14, paddingBottom: 14, boxShadow: '0 2px 12px rgba(0,0,0,0.06)' }}>
          <div style={{ width: 52, height: 52, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ fontSize: 28, fontWeight: 900, color: 'white' }}>2</span>
          </div>
          <span style={{ fontSize: 28, fontWeight: 700, color: BRAND.muted, letterSpacing: 0.5 }}>PASSO 2 DE 4</span>
        </div>
      </div>

      {/* Animated WiFi Icon */}
      <div style={{ transform: `scale(${iconScale})`, marginBottom: 44, position: 'relative' }}>
        <div style={{
          width: 180, height: 180, borderRadius: 48,
          background: '#E8F5E9',
          border: `3px solid ${BRAND.green}30`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 12px 48px rgba(0,163,53,0.12)',
        }}>
          <svg width="100" height="100" viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
            <path d="M1.42 9a16 16 0 0 1 21.16 0" stroke={BRAND.green} strokeWidth="2" opacity={wave1} />
            <path d="M5 12.55a11 11 0 0 1 14.08 0" stroke={BRAND.green} strokeWidth="2" opacity={wave2} />
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0" stroke={BRAND.green} strokeWidth="2" opacity={wave3} />
            <circle cx="12" cy="20" r="1.5" fill={BRAND.green} />
          </svg>
        </div>
      </div>

      {/* Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 36 }}>
        <div style={{ fontSize: 68, fontWeight: 900, color: BRAND.ink, lineHeight: 1.05, letterSpacing: -2 }}>CONECTE AO</div>
        <div style={{ fontSize: 68, fontWeight: 900, color: BRAND.green, lineHeight: 1.05, letterSpacing: -2 }}>WiFi</div>
      </div>

      {/* Network Name Card */}
      <div style={{ opacity: cardOpacity, transform: `translateY(${cardY}px) scale(${cardScale})`, width: '100%', marginBottom: 36 }}>
        <div style={{ background: 'white', borderRadius: 28, padding: 32, boxShadow: `0 0 0 3px ${BRAND.green}30, 0 8px 32px rgba(0,163,53,0.10)`, border: `2px solid ${BRAND.green}` }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 18, marginBottom: 14 }}>
            <div style={{ width: 48, height: 48, borderRadius: 14, background: '#E8F5E9', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="28" height="28" viewBox="0 0 24 24" fill={BRAND.green}>
                <path d="M1.42 9a16 16 0 0 1 21.16 0" stroke={BRAND.green} fill="none" strokeWidth="2" strokeLinecap="round" />
                <path d="M5 12.55a11 11 0 0 1 14.08 0" stroke={BRAND.green} fill="none" strokeWidth="2" strokeLinecap="round" />
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0" stroke={BRAND.green} fill="none" strokeWidth="2" strokeLinecap="round" />
                <circle cx="12" cy="20" r="1.5" fill={BRAND.green} />
              </svg>
            </div>
            <span style={{ fontSize: 22, fontWeight: 600, color: BRAND.muted }}>Nome da rede:</span>
          </div>
          <div style={{ fontSize: 38, fontWeight: 900, color: BRAND.ink, letterSpacing: -0.5, lineHeight: 1.2 }}>
            TocantinsTransporteWiFi
          </div>
        </div>
      </div>

      {/* Description */}
      <div style={{ opacity: descOpacity, transform: `translateY(${descY}px)`, textAlign: 'center', marginBottom: 28 }}>
        <div style={{ fontSize: 32, fontWeight: 500, color: BRAND.muted, lineHeight: 1.5 }}>
          Encontre nas configurações de WiFi
        </div>
        <div style={{ fontSize: 32, fontWeight: 500, color: BRAND.muted }}>
          do seu celular
        </div>
      </div>

      {/* Arrow hint */}
      <div style={{ opacity: arrowOpacity, display: 'flex', alignItems: 'center', gap: 12, background: `${BRAND.green}12`, borderRadius: 20, paddingLeft: 24, paddingRight: 24, paddingTop: 12, paddingBottom: 12 }}>
        <svg width="24" height="24" viewBox="0 0 24 24" fill={BRAND.green}>
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
        </svg>
        <span style={{ fontSize: 27, fontWeight: 700, color: BRAND.greenDark }}>Sem senha necessária</span>
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
