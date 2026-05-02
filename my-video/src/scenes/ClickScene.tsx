import React from 'react';
import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from 'remotion';
import { heroGradient, BRAND } from '../colors';
import { fontFamily } from '../font';

export const ClickScene: React.FC = () => {
  const frame = useCurrentFrame();

  const titleOpacity = interpolate(frame, [0, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [0, 18], [-30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const cardOpacity = interpolate(frame, [8, 28], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cardScale = interpolate(frame, [8, 32], [0.88, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });

  // Button pulse before click, then press animation
  const btnPulse = frame < 60
    ? 1 + Math.sin((frame / 18) * Math.PI) * 0.025
    : 1;
  const btnPress = frame >= 60 && frame < 82
    ? interpolate(frame, [60, 70, 82], [1, 0.93, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.inOut(Easing.quad) })
    : btnPulse;

  // Cursor: starts top-right, moves to button center
  const cursorOpacity = interpolate(frame, [22, 38], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [22, 60], [860, 475], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [22, 60], [340, 1065], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 60 && frame < 80
    ? interpolate(frame, [60, 68, 80], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;

  // Ripple after click
  const rippleOpacity = interpolate(frame, [68, 110], [0.85, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const rippleScale = interpolate(frame, [68, 110], [0.5, 3.0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  // White flash on success
  const flashOpacity = interpolate(frame, [98, 112, 120], [0, 0.4, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', top: -120, right: -120, width: 480, height: 480, borderRadius: '50%', background: 'rgba(255,255,255,0.06)' }} />
      <div style={{ position: 'absolute', bottom: -80, left: -80, width: 320, height: 320, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />

      {/* Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 64, paddingLeft: 60, paddingRight: 60 }}>
        <div style={{ fontSize: 62, fontWeight: 900, color: 'white', letterSpacing: -1.5, lineHeight: 1.1 }}>TOQUE NO</div>
        <div style={{ fontSize: 62, fontWeight: 900, color: 'rgba(255,255,255,0.85)', letterSpacing: -1.5, lineHeight: 1.1 }}>BOTÃO VERDE</div>
        <div style={{ fontSize: 30, color: 'rgba(255,255,255,0.65)', fontWeight: 500, marginTop: 14 }}>
          Conforme mostrado abaixo
        </div>
      </div>

      {/* Portal card */}
      <div style={{ opacity: cardOpacity, transform: `scale(${cardScale})`, width: 840, background: 'white', borderRadius: 32, padding: '36px 48px', boxShadow: '0 32px 80px rgba(0,0,0,0.35)' }}>
        {/* Trust bar */}
        <div style={{ display: 'flex', justifyContent: 'space-around', marginBottom: 26 }}>
          {['🔒 Seguro', '⚡ PIX', '✅ Automático'].map(t => (
            <span key={t} style={{ fontSize: 22, color: '#777', fontWeight: 500 }}>{t}</span>
          ))}
        </div>

        {/* Green button */}
        <div style={{
          transform: `scale(${btnPress})`,
          background: 'linear-gradient(135deg, #00C040 0%, #007A28 100%)',
          borderRadius: 18,
          paddingTop: 30, paddingBottom: 30,
          display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14,
          boxShadow: '0 10px 36px rgba(0,163,53,0.55)',
          position: 'relative', overflow: 'visible',
        }}>
          {frame >= 68 && (
            <div style={{
              position: 'absolute', inset: 0, borderRadius: 18,
              border: '4px solid rgba(0,200,64,0.65)',
              opacity: rippleOpacity,
              transform: `scale(${rippleScale})`,
            }} />
          )}
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M1.42 9a16 16 0 0 1 21.16 0" />
            <path d="M5 12.55a11 11 0 0 1 14.08 0" />
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
            <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
          </svg>
          <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 0.4 }}>ACESSAR INTERNET AGORA</span>
        </div>
      </div>

      {/* Animated finger cursor */}
      <div style={{
        position: 'absolute',
        left: cursorLeft,
        top: cursorTop,
        opacity: cursorOpacity,
        transform: `scale(${fingerScale})`,
        zIndex: 20,
        pointerEvents: 'none',
      }}>
        <div style={{
          width: 80, height: 80, borderRadius: '50%',
          background: 'rgba(255,255,255,0.92)',
          border: `4px solid ${BRAND.green}`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 10px 28px rgba(0,0,0,0.35), 0 0 0 10px rgba(255,255,255,0.15)',
          fontSize: 38,
        }}>
          👆
        </div>
      </div>

      {/* Success flash */}
      <div style={{ position: 'absolute', inset: 0, background: 'white', opacity: flashOpacity, pointerEvents: 'none' }} />
    </AbsoluteFill>
  );
};
