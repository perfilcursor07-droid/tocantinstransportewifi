import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const DiscountChoiceScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const titleOpacity = interpolate(frame, [0, 22], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [0, 22], [-40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  // Card 1: video option (slides in from left)
  const card1X = interpolate(frame, [15, 50], [-600, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const card1Opacity = interpolate(frame, [15, 45], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Card 2: skip option (slides in from right)
  const card2X = interpolate(frame, [32, 65], [600, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const card2Opacity = interpolate(frame, [32, 62], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // ECONOMIZE badge bounce
  const badgeScale = spring({ fps, frame: Math.max(0, frame - 55), config: { damping: 9, stiffness: 260, mass: 0.4 }, durationInFrames: 28 });

  // Cursor movement to card 1 (video option)
  const cursorOpacity = interpolate(frame, [98, 114], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [98, 135], [870, 445], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [98, 135], [280, 870], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  // Tap animation
  const fingerScale = frame >= 136 && frame < 156
    ? interpolate(frame, [136, 144, 156], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const card1Press = frame >= 136 && frame < 156
    ? interpolate(frame, [136, 144, 156], [1, 0.965, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;

  // Selection state
  const isSelected = frame >= 145;
  const selectedOpacity = interpolate(frame, [145, 162], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const checkScale = spring({ fps, frame: Math.max(0, frame - 145), config: { damping: 11, stiffness: 240, mass: 0.4 }, durationInFrames: 24 });

  // Cursor fade out after selection
  const cursorFadeOut = interpolate(frame, [158, 175], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: '#F2F5F8', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 56, paddingRight: 56, overflow: 'hidden' }}>

      {/* Header */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 52 }}>
        <div style={{ fontSize: 66, fontWeight: 900, color: BRAND.ink, letterSpacing: -2, lineHeight: 1.05 }}>
          Quer pagar menos? 🎉
        </div>
        <div style={{ fontSize: 30, fontWeight: 500, color: '#777', marginTop: 12 }}>
          Assista um vídeo curto e ganhe desconto
        </div>
      </div>

      {/* Card 1 — Watch video (RECOMMENDED) */}
      <div style={{
        opacity: card1Opacity,
        transform: `translateX(${card1X}px) scale(${card1Press})`,
        width: '100%',
        background: 'white',
        borderRadius: 28,
        border: `3px solid ${isSelected ? BRAND.green : BRAND.border}`,
        boxShadow: isSelected
          ? `0 16px 48px rgba(0,163,53,0.22), 0 0 0 1px ${BRAND.green}40`
          : '0 4px 20px rgba(0,0,0,0.08)',
        padding: '34px 40px',
        marginBottom: 24,
        position: 'relative',
        overflow: 'visible',
      }}>
        {/* ECONOMIZE badge */}
        <div style={{ position: 'absolute', top: -2, right: 36, transform: `scale(${badgeScale})`, transformOrigin: 'top center' }}>
          <div style={{ background: BRAND.green, borderRadius: '0 0 14px 14px', paddingLeft: 18, paddingRight: 18, paddingTop: 7, paddingBottom: 10 }}>
            <span style={{ fontSize: 20, fontWeight: 900, color: 'white', letterSpacing: 1.5 }}>ECONOMIZE</span>
          </div>
        </div>

        {/* Checkmark when selected */}
        {isSelected && (
          <div style={{ position: 'absolute', top: 20, left: 20, transform: `scale(${checkScale})`, opacity: selectedOpacity }}>
            <div style={{ width: 46, height: 46, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(0,163,53,0.4)' }}>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
          </div>
        )}

        <div style={{ paddingTop: 4 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 18, marginBottom: 18 }}>
            <div style={{ width: 60, height: 60, borderRadius: 16, background: `${BRAND.green}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 30 }}>
              ▶️
            </div>
            <div>
              <div style={{ fontSize: 36, fontWeight: 800, color: BRAND.ink, lineHeight: 1.2 }}>Assistir vídeo (42s)</div>
              <div style={{ fontSize: 24, color: '#777', fontWeight: 500 }}>Ganhe R$1,00 de desconto no plano</div>
            </div>
          </div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 14 }}>
            <span style={{ fontSize: 26, color: BRAND.muted, textDecoration: 'line-through', fontWeight: 600 }}>R$6,99</span>
            <span style={{ fontSize: 52, fontWeight: 900, color: BRAND.green, letterSpacing: -1 }}>R$5,99</span>
            <div style={{ background: `${BRAND.green}18`, border: `1.5px solid ${BRAND.green}40`, borderRadius: 10, paddingLeft: 12, paddingRight: 12, paddingTop: 5, paddingBottom: 5 }}>
              <span style={{ fontSize: 22, fontWeight: 800, color: BRAND.green }}>-R$1,00</span>
            </div>
          </div>
        </div>
      </div>

      {/* Card 2 — Skip */}
      <div style={{
        opacity: card2Opacity,
        transform: `translateX(${card2X}px)`,
        width: '100%',
        background: 'white',
        borderRadius: 24,
        border: `2px solid ${BRAND.border}`,
        boxShadow: '0 2px 12px rgba(0,0,0,0.06)',
        padding: '28px 40px',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
          <div style={{ width: 56, height: 56, borderRadius: 14, background: '#F0F0F0', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28 }}>
            ⏭️
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 32, fontWeight: 700, color: BRAND.ink }}>Pular e pagar normal</div>
            <div style={{ fontSize: 22, color: BRAND.muted, fontWeight: 500 }}>Sem vídeo, sem desconto</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: 40, fontWeight: 900, color: BRAND.ink }}>R$6,99</div>
          </div>
        </div>
      </div>

      {/* Cursor */}
      <div style={{
        position: 'absolute',
        left: cursorLeft,
        top: cursorTop,
        opacity: cursorOpacity * cursorFadeOut,
        transform: `scale(${fingerScale})`,
        zIndex: 20,
        pointerEvents: 'none',
      }}>
        <div style={{
          width: 80, height: 80, borderRadius: '50%',
          background: 'rgba(255,255,255,0.92)',
          border: `4px solid ${BRAND.green}`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 10px 28px rgba(0,0,0,0.3)',
          fontSize: 38,
        }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
