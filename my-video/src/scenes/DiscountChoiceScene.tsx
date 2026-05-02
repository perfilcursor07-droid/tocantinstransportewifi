import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
// colors used directly via hardcoded values in this scene
import { fontFamily } from '../font';

export const DiscountChoiceScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const overlayOpacity = interpolate(frame, [0, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const modalY = interpolate(frame, [5, 30], [300, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const modalOpacity = interpolate(frame, [5, 25], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Cards appear staggered
  const card1Opacity = interpolate(frame, [32, 52], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const card1Y = interpolate(frame, [32, 52], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const card2Opacity = interpolate(frame, [46, 66], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const card2Y = interpolate(frame, [46, 66], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  // ECONOMIZE badge bounce
  const badgeScale = spring({ fps, frame: Math.max(0, frame - 60), config: { damping: 9, stiffness: 280, mass: 0.35 }, durationInFrames: 24 });

  // Cursor to purple card (video option - bottom card)
  const cursorOpacity = interpolate(frame, [105, 120], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [105, 142], [870, 450], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [105, 142], [300, 1280], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 143 && frame < 163
    ? interpolate(frame, [143, 151, 163], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const card2Press = frame >= 143 && frame < 163
    ? interpolate(frame, [143, 151, 163], [1, 0.965, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const cursorFade = interpolate(frame, [165, 180], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Selection glow on purple card
  const isSelected = frame >= 152;
  const selectedOpacity = interpolate(frame, [152, 168], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const checkScale = spring({ fps, frame: Math.max(0, frame - 152), config: { damping: 10, stiffness: 250 }, durationInFrames: 22 });

  return (
    <AbsoluteFill style={{ background: '#F0F2F5', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>
      {/* Overlay */}
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)', opacity: overlayOpacity }} />

      {/* Modal container */}
      <div style={{ opacity: modalOpacity, transform: `translateY(${modalY}px)`, width: 840, borderRadius: 28, overflow: 'hidden', boxShadow: '0 32px 80px rgba(0,0,0,0.4)', position: 'relative' }}>

        {/* Dark green header */}
        <div style={{ background: `linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%)`, padding: '36px 36px 32px', textAlign: 'center' }}>
          <div style={{ fontSize: 48, fontWeight: 900, color: 'white', letterSpacing: -1, marginBottom: 10 }}>Quer pagar menos?</div>
          <div style={{ fontSize: 24, color: 'rgba(255,255,255,0.75)', fontWeight: 500 }}>Assista um vídeo curto e ganhe desconto</div>
        </div>

        {/* Cards area */}
        <div style={{ background: 'white', padding: '24px 28px 28px' }}>

          {/* Card 1: Skip option (white) */}
          <div style={{ opacity: card1Opacity, transform: `translateY(${card1Y}px)`, marginBottom: 16 }}>
            <div style={{ background: '#F9F9F9', borderRadius: 18, padding: '22px 24px', border: '1.5px solid #E8E8E8', display: 'flex', alignItems: 'center', gap: 18 }}>
              <div style={{ width: 52, height: 52, borderRadius: 14, background: '#EFEFEF', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#888" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="13 17 18 12 13 7" />
                  <polyline points="6 17 11 12 6 7" />
                </svg>
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 28, fontWeight: 700, color: '#333', lineHeight: 1.2 }}>Pular e pagar normal</div>
                <div style={{ fontSize: 20, color: '#999', fontWeight: 500 }}>Sem vídeo, sem desconto</div>
                <div style={{ fontSize: 22, fontWeight: 700, color: '#555', marginTop: 4 }}>Pagar R$6,99</div>
              </div>
            </div>
          </div>

          {/* Card 2: Video option (PURPLE gradient) */}
          <div style={{ opacity: card2Opacity, transform: `translateY(${card2Y}px) scale(${card2Press})`, position: 'relative' }}>
            {/* ECONOMIZE badge */}
            <div style={{ position: 'absolute', top: -12, right: 24, transform: `scale(${badgeScale})`, transformOrigin: 'top center', zIndex: 5 }}>
              <div style={{ background: '#F59E0B', borderRadius: '0 0 12px 12px', paddingLeft: 16, paddingRight: 16, paddingTop: 6, paddingBottom: 8 }}>
                <span style={{ fontSize: 18, fontWeight: 900, color: 'white', letterSpacing: 1 }}>ECONOMIZE</span>
              </div>
            </div>

            {/* Checkmark when selected */}
            {isSelected && (
              <div style={{ position: 'absolute', top: 16, left: 16, transform: `scale(${checkScale})`, opacity: selectedOpacity, zIndex: 5 }}>
                <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'rgba(255,255,255,0.9)', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 2px 10px rgba(0,0,0,0.2)' }}>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                </div>
              </div>
            )}

            <div style={{
              background: 'linear-gradient(135deg, #7C3AED 0%, #5B21B6 100%)',
              borderRadius: 18,
              padding: '24px 24px 24px',
              border: isSelected ? '2.5px solid rgba(255,255,255,0.4)' : '2.5px solid transparent',
              boxShadow: isSelected ? '0 8px 32px rgba(124,58,237,0.5)' : '0 6px 20px rgba(124,58,237,0.3)',
              display: 'flex', alignItems: 'center', gap: 18,
              position: 'relative', overflow: 'hidden',
            }}>
              {/* Shine overlay */}
              <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: '50%', background: 'linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 100%)', borderRadius: '18px 18px 0 0' }} />

              {/* Play button */}
              <div style={{ width: 60, height: 60, borderRadius: 16, background: 'rgba(255,255,255,0.18)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                  <polygon points="5 3 19 12 5 21 5 3" />
                </svg>
              </div>

              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 30, fontWeight: 800, color: 'white', lineHeight: 1.2 }}>Assistir vídeo (42s)</div>
                <div style={{ fontSize: 21, color: 'rgba(255,255,255,0.8)', fontWeight: 500 }}>Ganhe R$1,00 de desconto no plano</div>
                <div style={{ fontSize: 26, fontWeight: 900, color: '#FDE68A', marginTop: 6 }}>Pagar apenas R$5,99</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Cursor */}
      <div style={{ position: 'absolute', left: cursorLeft, top: cursorTop, opacity: cursorOpacity * cursorFade, transform: `scale(${fingerScale})`, zIndex: 20, pointerEvents: 'none' }}>
        <div style={{ width: 76, height: 76, borderRadius: '50%', background: 'rgba(255,255,255,0.92)', border: '4px solid #7C3AED', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 8px 24px rgba(0,0,0,0.3)', fontSize: 36 }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
