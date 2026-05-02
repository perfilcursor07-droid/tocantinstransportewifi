import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { heroGradient } from '../colors';
import { fontFamily } from '../font';

export const Step4Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const badgeScale = spring({ fps, frame, config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 30 });

  const title1Opacity = interpolate(frame, [20, 42], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title1Y = interpolate(frame, [20, 42], [50, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const title2Opacity = interpolate(frame, [34, 56], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title2Y = interpolate(frame, [34, 56], [50, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const btnOpacity = interpolate(frame, [56, 76], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const btnScale = interpolate(frame, [56, 76], [0.85, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.8)) });

  // Pulsing animation for button
  const pulseProgress = (Math.cos((frame / 50) * Math.PI * 2) + 1) / 2;
  const pulseScale = 1 + pulseProgress * 0.035;
  const pulseGlow = interpolate(pulseProgress, [0, 1], [0.35, 0.6]);

  const arrowOpacity = interpolate(frame, [80, 100], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const arrowBounce = interpolate(Math.sin((frame / 20) * Math.PI), [-1, 1], [0, 12]);

  const descOpacity = interpolate(frame, [95, 115], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 70, paddingRight: 70, overflow: 'hidden' }}>

      {/* Background decorations */}
      <div style={{ position: 'absolute', top: -80, right: -80, width: 350, height: 350, borderRadius: '50%', background: 'rgba(255,255,255,0.06)' }} />
      <div style={{ position: 'absolute', bottom: -60, left: -60, width: 280, height: 280, borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }} />

      {/* Step Badge */}
      <div style={{ transform: `scale(${badgeScale})`, marginBottom: 56 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, background: 'rgba(255,255,255,0.18)', border: '2px solid rgba(255,255,255,0.3)', borderRadius: 60, paddingLeft: 28, paddingRight: 36, paddingTop: 14, paddingBottom: 14 }}>
          <div style={{ width: 52, height: 52, borderRadius: '50%', background: 'rgba(255,255,255,0.25)', border: '2px solid white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ fontSize: 28, fontWeight: 900, color: 'white' }}>4</span>
          </div>
          <span style={{ fontSize: 28, fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: 0.5 }}>PASSO 4 DE 4</span>
          <span style={{ fontSize: 24, fontWeight: 800, color: 'rgba(255,255,255,0.8)' }}>✓✓✓</span>
        </div>
      </div>

      {/* Title */}
      <div style={{ textAlign: 'center', marginBottom: 52 }}>
        <div style={{ opacity: title1Opacity, transform: `translateY(${title1Y}px)`, fontSize: 70, fontWeight: 900, color: 'white', lineHeight: 1.05, letterSpacing: -2 }}>
          TOQUE NO
        </div>
        <div style={{ opacity: title2Opacity, transform: `translateY(${title2Y}px)`, fontSize: 70, fontWeight: 900, color: 'rgba(255,255,255,0.9)', lineHeight: 1.05, letterSpacing: -2 }}>
          BOTÃO VERDE
        </div>
      </div>

      {/* Arrow indicator */}
      <div style={{ opacity: arrowOpacity, transform: `translateY(${arrowBounce}px)`, marginBottom: 20 }}>
        <svg width="44" height="44" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)">
          <path d="M7 10l5 5 5-5z" />
        </svg>
      </div>

      {/* CTA Button - Pulsing */}
      <div style={{ opacity: btnOpacity, transform: `scale(${btnScale})`, marginBottom: 40, width: '100%' }}>
        <div style={{
          transform: `scale(${pulseScale})`,
          background: 'linear-gradient(135deg, #00C040 0%, #007A28 100%)',
          borderRadius: 24,
          paddingTop: 28, paddingBottom: 28,
          display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 16,
          boxShadow: `0 8px 32px rgba(0,163,53,${pulseGlow}), 0 0 0 4px rgba(255,255,255,0.15)`,
          border: '2px solid rgba(255,255,255,0.25)',
        }}>
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M1.42 9a16 16 0 0 1 21.16 0" />
            <path d="M5 12.55a11 11 0 0 1 14.08 0" />
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
            <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
          </svg>
          <span style={{ fontSize: 34, fontWeight: 900, color: 'white', letterSpacing: 0.5 }}>ACESSAR INTERNET AGORA</span>
        </div>
      </div>

      {/* Description */}
      <div style={{ opacity: descOpacity, textAlign: 'center' }}>
        <div style={{ fontSize: 30, fontWeight: 500, color: 'rgba(255,255,255,0.75)', lineHeight: 1.5 }}>
          Escolha seu plano e toque
        </div>
        <div style={{ fontSize: 30, fontWeight: 500, color: 'rgba(255,255,255,0.75)' }}>
          no botão verde
        </div>
      </div>
    </AbsoluteFill>
  );
};
