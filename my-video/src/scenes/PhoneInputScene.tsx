import React from 'react';
import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const PhoneInputScene: React.FC = () => {
  const frame = useCurrentFrame();

  // Background + overlay
  const bgOpacity = interpolate(frame, [0, 15], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Modal slides up
  const modalY = interpolate(frame, [8, 32], [200, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const modalOpacity = interpolate(frame, [8, 28], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Phone number typing animation
  const fullPhone = '(63) 9 8101-3050';
  const typingProgress = interpolate(frame, [50, 100], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });
  const typedChars = Math.floor(typingProgress * fullPhone.length);
  const displayPhone = fullPhone.slice(0, typedChars);
  const showCursor = frame >= 50 && typedChars < fullPhone.length && Math.floor(frame / 8) % 2 === 0;

  // Cursor to button
  const cursorOpacity = interpolate(frame, [108, 122], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [108, 145], [840, 370], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [108, 145], [380, 1250], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 146 && frame < 166
    ? interpolate(frame, [146, 154, 166], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const cursorFade = interpolate(frame, [168, 180], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Button press
  const btnPress = frame >= 146 && frame < 166
    ? interpolate(frame, [146, 154, 166], [1, 0.95, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;

  // Input field glow when typing
  const inputGlow = typedChars > 0 && typedChars < fullPhone.length;

  return (
    <AbsoluteFill style={{ background: '#F0F2F5', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>

      {/* Background portal (blurred) */}
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, #E8F5E9 0%, #F0F2F5 100%)', opacity: bgOpacity }} />
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.4)', opacity: bgOpacity * 0.5 }} />

      {/* Modal card */}
      <div style={{
        opacity: modalOpacity,
        transform: `translateY(${modalY}px)`,
        width: 820,
        background: 'white',
        borderRadius: 28,
        boxShadow: '0 32px 80px rgba(0,0,0,0.3)',
        overflow: 'hidden',
        position: 'relative',
      }}>
        {/* Modal header */}
        <div style={{ padding: '28px 32px 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ fontSize: 38, fontWeight: 900, color: BRAND.ink, letterSpacing: -0.5 }}>Acesso rápido</div>
          <div style={{ width: 40, height: 40, borderRadius: '50%', background: '#F0F0F0', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#888" strokeWidth="2.5" strokeLinecap="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </div>
        </div>

        {/* Modal content */}
        <div style={{ padding: '20px 32px 36px' }}>
          {/* Subtitle */}
          <div style={{ fontSize: 24, color: '#666', fontWeight: 500, marginBottom: 32, lineHeight: 1.4 }}>
            Informe seus dados para gerar o QR Code PIX.
          </div>

          {/* Phone input */}
          <div style={{ marginBottom: 28 }}>
            <div style={{ fontSize: 22, fontWeight: 600, color: '#444', marginBottom: 10 }}>
              Telefone com DDD
            </div>
            <div style={{
              border: `2.5px solid ${inputGlow ? BRAND.green : '#DDD'}`,
              borderRadius: 14,
              padding: '18px 20px',
              display: 'flex', alignItems: 'center', gap: 10,
              background: inputGlow ? `${BRAND.green}05` : 'white',
              boxShadow: inputGlow ? `0 0 0 4px ${BRAND.green}15` : 'none',
              transition: 'all 0.2s',
              minHeight: 64,
            }}>
              {displayPhone ? (
                <span style={{ fontSize: 30, fontWeight: 600, color: '#222', letterSpacing: 1 }}>
                  {displayPhone}
                  {showCursor && <span style={{ opacity: 0.7, color: BRAND.green }}>|</span>}
                </span>
              ) : (
                <span style={{ fontSize: 26, color: '#BBB', fontWeight: 400 }}>
                  (63) 9 8101-3050
                </span>
              )}
            </div>
          </div>

          {/* GERAR QR CODE PIX button */}
          <div style={{ transform: `scale(${btnPress})`, marginBottom: 20 }}>
            <div style={{
              background: `linear-gradient(135deg, ${BRAND.greenLight} 0%, ${BRAND.greenDark} 100%)`,
              borderRadius: 16,
              paddingTop: 26, paddingBottom: 26,
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14,
              boxShadow: `0 8px 28px ${BRAND.green}50`,
            }}>
              {/* QR icon */}
              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="3" height="3" rx="0.5" />
                <rect x="18" y="14" width="3" height="3" rx="0.5" />
                <rect x="14" y="18" width="3" height="3" rx="0.5" />
                <rect x="18" y="18" width="3" height="3" rx="0.5" />
              </svg>
              <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 0.3 }}>GERAR QR CODE PIX</span>
            </div>
          </div>

          {/* Footer */}
          <div style={{ textAlign: 'center', fontSize: 20, color: '#AAA', fontWeight: 500 }}>
            Pagamento seguro • Liberação automática
          </div>
        </div>
      </div>

      {/* Cursor */}
      <div style={{ position: 'absolute', left: cursorLeft, top: cursorTop, opacity: cursorOpacity * cursorFade, transform: `scale(${fingerScale})`, zIndex: 20, pointerEvents: 'none' }}>
        <div style={{ width: 76, height: 76, borderRadius: '50%', background: 'rgba(255,255,255,0.95)', border: `4px solid ${BRAND.green}`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 8px 24px rgba(0,0,0,0.3)', fontSize: 36 }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
