import React from 'react';
import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from 'remotion';
import { heroGradient, BRAND } from '../colors';
import { fontFamily } from '../font';

export const Step4Scene: React.FC = () => {
  const frame = useCurrentFrame();

  const titleOpacity = interpolate(frame, [0, 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [0, 20], [-30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const cardOpacity = interpolate(frame, [10, 32], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cardScale = interpolate(frame, [10, 35], [0.9, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.4)) });

  // Button pulses then gets clicked
  const pulseScale = frame < 60 ? 1 + Math.sin((frame / 20) * Math.PI) * 0.025 : 1;
  const btnPress = frame >= 60 && frame < 82
    ? interpolate(frame, [60, 70, 82], [1, 0.93, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.inOut(Easing.quad) })
    : pulseScale;

  // Arrow bounce
  const arrowBounce = interpolate(Math.sin((frame / 20) * Math.PI), [-1, 1], [0, 14]);
  const arrowOpacity = interpolate(frame, [30, 48], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Cursor
  const cursorOpacity = interpolate(frame, [32, 46], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [32, 62], [860, 475], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [32, 62], [350, 1075], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 62 && frame < 82
    ? interpolate(frame, [62, 70, 82], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const cursorFade = interpolate(frame, [84, 100], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Ripple after click
  const rippleOpacity = interpolate(frame, [70, 110], [0.8, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const rippleScale = interpolate(frame, [70, 110], [0.5, 2.8], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  const descOpacity = interpolate(frame, [92, 112], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 64, paddingRight: 64, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', top: -100, right: -100, width: 420, height: 420, borderRadius: '50%', background: 'rgba(255,255,255,0.06)' }} />
      <div style={{ position: 'absolute', bottom: -70, left: -70, width: 280, height: 280, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />

      {/* Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 56 }}>
        <div style={{ fontSize: 66, fontWeight: 900, color: 'white', letterSpacing: -2, lineHeight: 1.05 }}>TOQUE EM</div>
        <div style={{ fontSize: 66, fontWeight: 900, color: 'rgba(255,255,255,0.88)', letterSpacing: -2, lineHeight: 1.05 }}>ACESSAR INTERNET</div>
        <div style={{ fontSize: 66, fontWeight: 900, color: 'rgba(255,255,255,0.88)', letterSpacing: -2, lineHeight: 1.05 }}>AGORA</div>
      </div>

      {/* Arrow */}
      <div style={{ opacity: arrowOpacity, transform: `translateY(${arrowBounce}px)`, marginBottom: 16 }}>
        <svg width="48" height="48" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)">
          <path d="M7 10l5 5 5-5z" />
        </svg>
      </div>

      {/* Portal card with button */}
      <div style={{ opacity: cardOpacity, transform: `scale(${cardScale})`, width: '100%' }}>
        <div style={{ background: 'white', borderRadius: 28, padding: '32px 40px', boxShadow: '0 28px 72px rgba(0,0,0,0.32)' }}>
          {/* Trust row */}
          <div style={{ display: 'flex', justifyContent: 'space-around', marginBottom: 24 }}>
            {['🔒 Pagamento seguro', '⚡ PIX instantâneo', '✅ Liberação automática'].map(t => (
              <span key={t} style={{ fontSize: 19, color: '#888', fontWeight: 500 }}>{t}</span>
            ))}
          </div>
          {/* Button */}
          <div style={{
            transform: `scale(${btnPress})`,
            background: `linear-gradient(135deg, ${BRAND.greenLight} 0%, ${BRAND.greenDark} 100%)`,
            borderRadius: 18,
            paddingTop: 28, paddingBottom: 28,
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14,
            boxShadow: `0 10px 36px ${BRAND.green}55`,
            position: 'relative', overflow: 'visible',
          }}>
            {frame >= 70 && (
              <div style={{ position: 'absolute', inset: 0, borderRadius: 18, border: `4px solid ${BRAND.greenLight}90`, opacity: rippleOpacity, transform: `scale(${rippleScale})` }} />
            )}
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M1.42 9a16 16 0 0 1 21.16 0" />
              <path d="M5 12.55a11 11 0 0 1 14.08 0" />
              <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
              <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
            </svg>
            <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 0.3 }}>ACESSAR INTERNET AGORA</span>
          </div>
        </div>
      </div>

      <div style={{ opacity: descOpacity, marginTop: 32, textAlign: 'center' }}>
        <div style={{ fontSize: 28, fontWeight: 500, color: 'rgba(255,255,255,0.8)' }}>Em seguida será solicitado seu telefone</div>
      </div>

      {/* Cursor */}
      <div style={{ position: 'absolute', left: cursorLeft, top: cursorTop, opacity: cursorOpacity * cursorFade, transform: `scale(${fingerScale})`, zIndex: 20, pointerEvents: 'none' }}>
        <div style={{ width: 76, height: 76, borderRadius: '50%', background: 'rgba(255,255,255,0.92)', border: `4px solid ${BRAND.green}`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 8px 24px rgba(0,0,0,0.3)', fontSize: 36 }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
