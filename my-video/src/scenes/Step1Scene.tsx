import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const Step1Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const badgeScale = spring({ fps, frame, config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 30 });

  const iconScale = spring({ fps, frame: Math.max(0, frame - 10), config: { damping: 14, stiffness: 150, mass: 0.8 }, durationInFrames: 35 });

  const title1Opacity = interpolate(frame, [22, 44], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title1Y = interpolate(frame, [22, 44], [45, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const title2Opacity = interpolate(frame, [34, 56], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title2Y = interpolate(frame, [34, 56], [45, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const pillOpacity = interpolate(frame, [48, 65], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const pillScale = interpolate(frame, [48, 65], [0.75, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(2)) });

  const descOpacity = interpolate(frame, [68, 88], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const descY = interpolate(frame, [68, 88], [25, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  const tipOpacity = interpolate(frame, [90, 110], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: 'white', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 80, paddingRight: 80 }}>

      {/* Top accent bar */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Step Badge */}
      <div style={{ transform: `scale(${badgeScale})`, marginBottom: 52 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, background: BRAND.surface, borderRadius: 60, paddingLeft: 28, paddingRight: 36, paddingTop: 14, paddingBottom: 14 }}>
          <div style={{ width: 52, height: 52, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ fontSize: 28, fontWeight: 900, color: 'white' }}>1</span>
          </div>
          <span style={{ fontSize: 28, fontWeight: 700, color: BRAND.muted, letterSpacing: 0.5 }}>PASSO 1 DE 4</span>
        </div>
      </div>

      {/* Icon */}
      <div style={{ transform: `scale(${iconScale})`, marginBottom: 44 }}>
        <div style={{
          width: 180, height: 180, borderRadius: 48,
          background: '#FFF0F0',
          border: `3px solid ${BRAND.red}30`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 12px 48px rgba(211,47,47,0.12)',
        }}>
          {/* Cellular signal with X */}
          <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke={BRAND.red} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="1" y="14" width="4" height="6" rx="1" fill={BRAND.red} opacity={0.5} />
            <rect x="7" y="10" width="4" height="10" rx="1" fill={BRAND.red} opacity={0.5} />
            <rect x="13" y="5" width="4" height="15" rx="1" fill={BRAND.red} opacity={0.5} />
            <line x1="19" y1="1" x2="23" y2="5" strokeWidth="2.5" />
            <line x1="23" y1="1" x2="19" y2="5" strokeWidth="2.5" />
          </svg>
        </div>
      </div>

      {/* Title */}
      <div style={{ textAlign: 'center', marginBottom: 20 }}>
        <div style={{ opacity: title1Opacity, transform: `translateY(${title1Y}px)`, fontSize: 74, fontWeight: 900, color: BRAND.ink, lineHeight: 1.05, letterSpacing: -2 }}>
          DESATIVE OS
        </div>
        <div style={{ opacity: title2Opacity, transform: `translateY(${title2Y}px)`, fontSize: 74, fontWeight: 900, color: BRAND.ink, lineHeight: 1.05, letterSpacing: -2 }}>
          DADOS MÓVEIS
        </div>
      </div>

      {/* 4G/5G Pill */}
      <div style={{ opacity: pillOpacity, transform: `scale(${pillScale})`, marginBottom: 40 }}>
        <div style={{ display: 'flex', gap: 12 }}>
          {['4G', '5G'].map((label) => (
            <div key={label} style={{ background: BRAND.red, borderRadius: 40, paddingLeft: 24, paddingRight: 24, paddingTop: 10, paddingBottom: 10 }}>
              <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 1 }}>{label}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Description */}
      <div style={{ opacity: descOpacity, transform: `translateY(${descY}px)`, textAlign: 'center', paddingLeft: 20, paddingRight: 20, marginBottom: 32 }}>
        <div style={{ fontSize: 34, fontWeight: 500, color: BRAND.muted, lineHeight: 1.5 }}>
          Vá nas configurações do celular
        </div>
        <div style={{ fontSize: 34, fontWeight: 500, color: BRAND.muted, lineHeight: 1.5 }}>
          e desative o sinal de dados
        </div>
      </div>

      {/* Tip */}
      <div style={{ opacity: tipOpacity, background: '#E8F5E9', border: `1.5px solid ${BRAND.green}40`, borderRadius: 20, paddingLeft: 28, paddingRight: 28, paddingTop: 16, paddingBottom: 16, display: 'flex', alignItems: 'center', gap: 14 }}>
        <svg width="28" height="28" viewBox="0 0 24 24" fill={BRAND.green}>
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
        </svg>
        <span style={{ fontSize: 26, fontWeight: 600, color: BRAND.greenDark }}>
          Assim o celular usa apenas o WiFi
        </span>
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
