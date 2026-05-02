import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

// Reuse same QR pattern
const QRCode: React.FC = () => {
  const size = 200;
  const cellSize = size / 20;
  const pattern = [
    [1,1,1,1,1,1,1,0,1,0,0,1,0,0,1,1,1,1,1,1,1],
    [1,0,0,0,0,0,1,0,1,1,0,0,1,0,1,0,0,0,0,0,1],
    [1,0,1,1,1,0,1,0,0,1,1,0,1,0,1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1,0,1,0,0,1,0,0,1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1,0,0,1,0,1,1,0,1,0,1,1,1,0,1],
    [1,0,0,0,0,0,1,0,1,1,0,0,0,0,1,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,1,1,1,1,1,1],
    [0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0],
    [1,1,0,1,0,1,1,1,0,0,1,1,0,1,1,0,1,0,0,1,0],
    [0,1,0,0,1,0,0,0,1,0,0,1,0,0,1,0,0,1,0,1,0],
    [1,0,1,1,0,1,0,1,0,1,1,0,1,1,0,1,0,1,1,0,1],
    [0,0,0,0,0,0,0,0,1,0,1,0,1,0,0,1,0,0,1,0,0],
    [1,1,1,1,1,1,1,0,0,1,0,1,0,1,1,0,0,1,0,1,0],
    [0,0,0,0,0,0,0,0,1,0,0,0,1,0,0,1,0,0,0,0,1],
    [1,1,1,1,1,1,1,0,1,1,0,1,0,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,0,1,0,0,1,1,0,1,0,1,0,0,0,0,0,1],
    [1,0,1,1,1,0,1,0,1,0,0,1,0,0,1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1,0,0,1,0,0,1,0,0,0,1,1,1,0,1],
    [1,0,0,0,0,0,1,0,1,1,1,0,1,1,1,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,0,0,0,1,0,0,0,1,1,1,1,1,1,1],
  ];
  return (
    <div style={{ width: size, height: size, background: 'white', padding: 8, borderRadius: 12 }}>
      {pattern.map((row, y) => (
        <div key={y} style={{ display: 'flex' }}>
          {row.map((cell, x) => (
            <div key={x} style={{ width: cellSize - 0.5, height: cellSize - 0.5, background: cell ? '#111' : 'white', margin: 0.25 }} />
          ))}
        </div>
      ))}
    </div>
  );
};

const PixLogo: React.FC = () => (
  <svg width="52" height="52" viewBox="0 0 100 100" fill="none">
    <path d="M50 5 L95 50 L50 95 L5 50 Z" fill="#32BCAD" />
    <path d="M50 28 L72 50 L50 72 L28 50 Z" fill="white" />
    <path d="M50 38 L62 50 L50 62 L38 50 Z" fill="#32BCAD" />
  </svg>
);

export const PixFinalScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // Total: 270 frames = 9s
  // Phase 1: show payment screen (0-130)
  // Phase 2: cursor taps COPIAR PIX button (110-155)
  // Phase 3: 3-min internet + timer appears (155-270)

  const headerOpacity = interpolate(frame, [0, 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const headerY = interpolate(frame, [0, 20], [-35, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const discountBadgeScale = spring({ fps, frame: Math.max(0, frame - 12), config: { damping: 10, stiffness: 260, mass: 0.4 }, durationInFrames: 24 });

  const qrOpacity = interpolate(frame, [25, 48], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const qrScale = interpolate(frame, [25, 50], [0.85, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.4)) });

  const codeAreaOpacity = interpolate(frame, [45, 68], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const copyBtnOpacity = interpolate(frame, [60, 82], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const copyBtnScale = interpolate(frame, [60, 82], [0.88, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });

  // Cursor to COPIAR button
  const cursorOpacity = interpolate(frame, [105, 120], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [105, 145], [870, 380], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [105, 145], [300, 1440], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 146 && frame < 166
    ? interpolate(frame, [146, 154, 166], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const copyBtnPress = frame >= 146 && frame < 166
    ? interpolate(frame, [146, 154, 166], [1, 0.94, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;

  // After copy: 3-min internet banner
  const isCopied = frame >= 154;
  const copiedOpacity = interpolate(frame, [154, 172], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const copiedScale = spring({ fps, frame: Math.max(0, frame - 154), config: { damping: 10, stiffness: 240, mass: 0.5 }, durationInFrames: 25 });

  // Cursor fade out
  const cursorFade = interpolate(frame, [166, 180], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Countdown timer (starts at 2:58 after copy)
  const timerFrame = isCopied ? frame - 154 : 0;
  const totalSecondsLeft = Math.max(0, 178 - Math.floor(timerFrame * 30 / fps));
  const timerMin = Math.floor(totalSecondsLeft / 60);
  const timerSec = totalSecondsLeft % 60;
  const timerStr = `${timerMin.toString().padStart(2, '0')}:${timerSec.toString().padStart(2, '0')}`;

  // "JÁ PAGUEI" button
  const paidBtnOpacity = interpolate(frame, [190, 210], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const paidBtnScale = interpolate(frame, [190, 210], [0.9, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.4)) });

  return (
    <AbsoluteFill style={{ background: 'white', display: 'flex', flexDirection: 'column', alignItems: 'center', fontFamily, overflow: 'hidden' }}>

      {/* Top accent bar */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 6, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Header */}
      <div style={{ opacity: headerOpacity, transform: `translateY(${headerY}px)`, paddingTop: 56, paddingBottom: 24, width: '100%', paddingLeft: 60, paddingRight: 60, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <PixLogo />
          <div>
            <div style={{ fontSize: 42, fontWeight: 900, color: BRAND.ink, letterSpacing: -1, lineHeight: 1.0 }}>Pagamento PIX</div>
            <div style={{ fontSize: 22, color: '#777', fontWeight: 500 }}>Escaneie ou copie o código</div>
          </div>
        </div>
        {/* Discount badge */}
        <div style={{ transform: `scale(${discountBadgeScale})`, transformOrigin: 'right center' }}>
          <div style={{ background: `linear-gradient(135deg, ${BRAND.green}, ${BRAND.greenDeep})`, borderRadius: 16, paddingLeft: 20, paddingRight: 20, paddingTop: 10, paddingBottom: 10, textAlign: 'center', boxShadow: `0 6px 20px ${BRAND.green}40` }}>
            <div style={{ fontSize: 16, fontWeight: 700, color: 'rgba(255,255,255,0.85)', letterSpacing: 0.5 }}>DESCONTO</div>
            <div style={{ fontSize: 26, fontWeight: 900, color: 'white', letterSpacing: -0.5 }}>-R$1,00</div>
          </div>
        </div>
      </div>

      {/* Amount */}
      <div style={{ opacity: headerOpacity, display: 'flex', alignItems: 'baseline', gap: 16, marginBottom: 20 }}>
        <span style={{ fontSize: 28, color: BRAND.muted, textDecoration: 'line-through', fontWeight: 600 }}>R$6,99</span>
        <span style={{ fontSize: 72, fontWeight: 900, color: BRAND.ink, letterSpacing: -2 }}>R$5,99</span>
      </div>

      {/* QR Code */}
      <div style={{ opacity: qrOpacity, transform: `scale(${qrScale})`, marginBottom: 20 }}>
        <div style={{ padding: 16, background: '#F0FAFA', borderRadius: 24, border: `2px solid #32BCAD30` }}>
          <QRCode />
        </div>
        <div style={{ textAlign: 'center', marginTop: 12, fontSize: 22, color: '#555', fontWeight: 500 }}>
          Escaneie com o app do banco
        </div>
      </div>

      {/* PIX code area */}
      <div style={{ opacity: codeAreaOpacity, width: '100%', paddingLeft: 56, paddingRight: 56, marginBottom: 20 }}>
        <div style={{ background: '#F8F9FA', border: '1.5px solid #E0E0E0', borderRadius: 14, padding: '14px 20px', display: 'flex', alignItems: 'center', gap: 12 }}>
          <div style={{ flex: 1, overflow: 'hidden' }}>
            <div style={{ fontSize: 14, color: '#999', fontWeight: 600, marginBottom: 4, letterSpacing: 0.5 }}>OU COPIE O CÓDIGO</div>
            <div style={{ fontSize: 16, color: '#555', fontFamily: 'monospace', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              00020101021226830014br.gov.bcb.pix2561api.pagse...
            </div>
          </div>
        </div>
      </div>

      {/* COPIAR CÓDIGO PIX button */}
      <div style={{ opacity: copyBtnOpacity, transform: `scale(${copyBtnScale * copyBtnPress})`, width: '100%', paddingLeft: 56, paddingRight: 56, marginBottom: 16 }}>
        <div style={{
          background: isCopied
            ? `linear-gradient(135deg, ${BRAND.green}, ${BRAND.greenDeep})`
            : `linear-gradient(135deg, ${BRAND.pixTeal}, #1a9990)`,
          borderRadius: 18,
          paddingTop: 26, paddingBottom: 26,
          display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14,
          boxShadow: isCopied ? `0 8px 28px ${BRAND.green}50` : '0 8px 28px rgba(50,188,173,0.4)',
          position: 'relative', overflow: 'hidden',
        }}>
          {isCopied ? (
            <>
              <span style={{ fontSize: 28 }}>✅</span>
              <span style={{ fontSize: 30, fontWeight: 900, color: 'white' }}>CÓDIGO COPIADO!</span>
            </>
          ) : (
            <>
              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
              </svg>
              <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 0.3 }}>COPIAR CÓDIGO PIX</span>
            </>
          )}
        </div>
      </div>

      {/* 3-min internet banner — appears after copy */}
      {isCopied && (
        <div style={{ opacity: copiedOpacity, transform: `scale(${copiedScale})`, width: '100%', paddingLeft: 56, paddingRight: 56, marginBottom: 16 }}>
          <div style={{ background: `linear-gradient(135deg, #FFF8E1, #FFF3CD)`, border: `2px solid #F59E0B`, borderRadius: 20, padding: '22px 30px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 10 }}>
              <span style={{ fontSize: 36 }}>⚡</span>
              <div>
                <div style={{ fontSize: 28, fontWeight: 900, color: '#92400E', letterSpacing: -0.5 }}>3 minutos GRÁTIS liberados!</div>
                <div style={{ fontSize: 21, color: '#B45309', fontWeight: 500 }}>Acesse o app do banco e efetue o pagamento</div>
              </div>
              <div style={{ marginLeft: 'auto', textAlign: 'center' }}>
                <div style={{ fontSize: 42, fontWeight: 900, color: '#D97706', letterSpacing: -1, fontVariantNumeric: 'tabular-nums' }}>{timerStr}</div>
                <div style={{ fontSize: 16, color: '#B45309', fontWeight: 600 }}>restantes</div>
              </div>
            </div>
            <div style={{ borderTop: '1.5px solid #F59E0B40', paddingTop: 10, fontSize: 20, color: '#92400E', fontWeight: 500 }}>
              ✓ Após o pagamento confirmado, o acesso completo é liberado automaticamente
            </div>
          </div>
        </div>
      )}

      {/* JÁ PAGUEI */}
      {isCopied && (
        <div style={{ opacity: paidBtnOpacity, transform: `scale(${paidBtnScale})`, width: '100%', paddingLeft: 56, paddingRight: 56 }}>
          <div style={{ border: `2.5px solid ${BRAND.green}`, borderRadius: 16, paddingTop: 22, paddingBottom: 22, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4, background: `${BRAND.green}08` }}>
            <span style={{ fontSize: 28, fontWeight: 900, color: BRAND.green, letterSpacing: 0.3 }}>JÁ PAGUEI</span>
            <span style={{ fontSize: 18, color: '#777', fontWeight: 500 }}>Verificando pagamento automaticamente</span>
          </div>
        </div>
      )}

      {/* Cursor */}
      <div style={{
        position: 'absolute',
        left: cursorLeft,
        top: cursorTop,
        opacity: cursorOpacity * cursorFade,
        transform: `scale(${fingerScale})`,
        zIndex: 20,
        pointerEvents: 'none',
      }}>
        <div style={{
          width: 80, height: 80, borderRadius: '50%',
          background: 'rgba(255,255,255,0.92)',
          border: `4px solid ${BRAND.pixTeal}`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 10px 28px rgba(0,0,0,0.3)',
          fontSize: 38,
        }}>
          👆
        </div>
      </div>

      {/* Bottom bar */}
      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 6, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
