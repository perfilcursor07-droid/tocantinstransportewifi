import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

// Realistic QR Code (same pattern)
const QRCode: React.FC<{ size?: number }> = ({ size = 210 }) => {
  const cell = size / 21;
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
    <div style={{ width: size, height: size, background: 'white', padding: 6, borderRadius: 10 }}>
      {pattern.map((row, y) => (
        <div key={y} style={{ display: 'flex' }}>
          {row.map((c, x) => (
            <div key={x} style={{ width: cell - 0.3, height: cell - 0.3, background: c ? '#111' : 'white', margin: 0.15 }} />
          ))}
        </div>
      ))}
    </div>
  );
};

export const PixFinalScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // 270 frames = 9s
  // Phases: show screen (0-120), cursor taps copy (105-160), 3-min internet (155-270)

  const sceneOpacity = interpolate(frame, [0, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const headerOpacity = interpolate(frame, [0, 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const qrOpacity = interpolate(frame, [20, 42], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const qrScale = interpolate(frame, [20, 45], [0.88, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.4)) });
  const codeOpacity = interpolate(frame, [40, 60], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const copyBtnOpacity = interpolate(frame, [55, 75], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const copyBtnYIn = interpolate(frame, [55, 75], [20, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const bottomOpacity = interpolate(frame, [70, 90], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Cursor to COPIAR CÓDIGO PIX button
  const cursorOpacity = interpolate(frame, [100, 115], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [100, 140], [870, 360], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [100, 140], [340, 1420], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 142 && frame < 162
    ? interpolate(frame, [142, 150, 162], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const copyBtnPress = frame >= 142 && frame < 162
    ? interpolate(frame, [142, 150, 162], [1, 0.94, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const cursorFade = interpolate(frame, [163, 178], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const isCopied = frame >= 152;
  const copiedOpacity = interpolate(frame, [152, 168], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const internetBannerScale = spring({ fps, frame: Math.max(0, frame - 152), config: { damping: 10, stiffness: 240, mass: 0.5 }, durationInFrames: 24 });

  // Timer countdown from 3:00
  const timerFrame = isCopied ? frame - 152 : 0;
  const totalSec = Math.max(0, 180 - Math.floor(timerFrame));
  const timerMin = Math.floor(totalSec / 60);
  const timerSec = totalSec % 60;
  const timerStr = `${timerMin.toString().padStart(2, '0')}:${timerSec.toString().padStart(2, '0')}`;

  const paidBtnOpacity = interpolate(frame, [188, 206], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const verifyOpacity = interpolate(frame, [205, 222], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: '#F0F2F5', display: 'flex', flexDirection: 'column', alignItems: 'center', fontFamily, overflow: 'hidden', opacity: sceneOpacity }}>

      {/* Main card */}
      <div style={{ width: '100%', maxWidth: 900, paddingLeft: 52, paddingRight: 52, paddingTop: 44 }}>

        {/* Header row - matches image 5 */}
        <div style={{ opacity: headerOpacity, background: 'white', borderRadius: 20, padding: '22px 28px', marginBottom: 14, boxShadow: '0 2px 14px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
            {/* PIX icon circle */}
            <div style={{ width: 48, height: 48, borderRadius: '50%', background: `${BRAND.green}18`, border: `2px solid ${BRAND.green}40`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke={BRAND.green} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="3" height="3" rx="0.5" />
                <rect x="18" y="14" width="3" height="3" rx="0.5" />
                <rect x="14" y="18" width="3" height="3" rx="0.5" />
                <rect x="18" y="18" width="3" height="3" rx="0.5" />
              </svg>
            </div>
            <span style={{ fontSize: 32, fontWeight: 800, color: BRAND.ink }}>Pagamento PIX</span>
          </div>
          <span style={{ fontSize: 38, fontWeight: 900, color: BRAND.ink, letterSpacing: -1 }}>
            {isCopied ? 'R$ 5,99' : 'R$ 6,99'}
          </span>
        </div>

        {/* Tab navigation - Copiar | Pagar | Conectar */}
        <div style={{ opacity: headerOpacity, background: 'white', borderRadius: 16, marginBottom: 14, boxShadow: '0 2px 10px rgba(0,0,0,0.06)', display: 'flex', overflow: 'hidden' }}>
          {[
            { label: 'Copiar', active: true },
            { label: 'Pagar', active: false },
            { label: 'Conectar', active: false },
          ].map(({ label, active }) => (
            <div key={label} style={{ flex: 1, paddingTop: 16, paddingBottom: 16, textAlign: 'center', borderBottom: active ? `3px solid ${BRAND.green}` : '3px solid transparent', position: 'relative' }}>
              <span style={{ fontSize: 22, fontWeight: active ? 700 : 500, color: active ? BRAND.green : '#999' }}>{label}</span>
            </div>
          ))}
        </div>

        {/* QR Code section */}
        <div style={{ opacity: qrOpacity, transform: `scale(${qrScale})`, background: 'white', borderRadius: 18, padding: '24px', marginBottom: 14, boxShadow: '0 2px 10px rgba(0,0,0,0.06)', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
          {/* Dashed border frame */}
          <div style={{ border: `2px dashed ${BRAND.green}60`, borderRadius: 16, padding: 14, marginBottom: 14 }}>
            <QRCode size={200} />
          </div>
          <span style={{ fontSize: 20, color: '#888', fontWeight: 500 }}>Escaneie com o app do banco</span>
        </div>

        {/* OR divider */}
        <div style={{ opacity: codeOpacity, display: 'flex', alignItems: 'center', gap: 16, marginBottom: 14 }}>
          <div style={{ flex: 1, height: 1, background: '#E5E5E5' }} />
          <span style={{ fontSize: 18, fontWeight: 700, color: '#AAA', letterSpacing: 1 }}>OU COPIE O CÓDIGO</span>
          <div style={{ flex: 1, height: 1, background: '#E5E5E5' }} />
        </div>

        {/* PIX code text area */}
        <div style={{ opacity: codeOpacity, background: 'white', borderRadius: 14, padding: '14px 18px', marginBottom: 14, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', border: '1.5px solid #E8E8E8', maxHeight: 100, overflow: 'hidden' }}>
          <div style={{ fontSize: 15, color: '#777', fontFamily: 'monospace', lineHeight: 1.6, wordBreak: 'break-all' }}>
            00020101021226830014br.gov.bcb.pix2561api.pagseguro.com/pix/v2/9D36C64F-A600-4013-B540-92D97E52766C27600016BR.COM.PAGSEGURO...
          </div>
        </div>

        {/* COPIAR CÓDIGO PIX button (BLUE) */}
        <div style={{ opacity: copyBtnOpacity, transform: `translateY(${copyBtnYIn}px) scale(${copyBtnPress})`, marginBottom: 16 }}>
          <div style={{
            background: isCopied
              ? `linear-gradient(135deg, ${BRAND.green} 0%, ${BRAND.greenDeep} 100%)`
              : 'linear-gradient(135deg, #1565C0 0%, #0D47A1 100%)',
            borderRadius: 14,
            paddingTop: 22, paddingBottom: 22,
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 12,
            boxShadow: isCopied ? `0 6px 22px ${BRAND.green}50` : '0 6px 22px rgba(21,101,192,0.4)',
          }}>
            {isCopied ? (
              <>
                <span style={{ fontSize: 24 }}>✅</span>
                <span style={{ fontSize: 26, fontWeight: 900, color: 'white' }}>CÓDIGO COPIADO!</span>
              </>
            ) : (
              <>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
                <span style={{ fontSize: 26, fontWeight: 900, color: 'white' }}>COPIAR CÓDIGO PIX</span>
              </>
            )}
          </div>
        </div>

        {/* 3-min internet banner */}
        {isCopied && (
          <div style={{ opacity: copiedOpacity, transform: `scale(${internetBannerScale})`, background: '#FFFBEB', border: '2px solid #F59E0B', borderRadius: 16, padding: '18px 22px', marginBottom: 14 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
              <span style={{ fontSize: 32 }}>⚡</span>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 24, fontWeight: 900, color: '#92400E' }}>3 minutos GRÁTIS liberados!</div>
                <div style={{ fontSize: 18, color: '#B45309', fontWeight: 500 }}>Abra o app do banco e efetue o pagamento</div>
              </div>
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 36, fontWeight: 900, color: '#D97706', fontVariantNumeric: 'tabular-nums', letterSpacing: -1 }}>{timerStr}</div>
                <div style={{ fontSize: 14, color: '#B45309', fontWeight: 600 }}>restantes</div>
              </div>
            </div>
          </div>
        )}

        {/* Bottom row: timer + JÁ PAGUEI */}
        <div style={{ opacity: bottomOpacity, display: 'flex', alignItems: 'center', gap: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: 'white', borderRadius: 12, paddingLeft: 18, paddingRight: 18, paddingTop: 16, paddingBottom: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.06)' }}>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#888" strokeWidth="2" strokeLinecap="round">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <span style={{ fontSize: 26, fontWeight: 700, color: '#666', fontVariantNumeric: 'tabular-nums' }}>
              {isCopied ? timerStr : '03:00'}
            </span>
          </div>
          <div style={{ opacity: paidBtnOpacity, flex: 1 }}>
            <div style={{ background: `linear-gradient(135deg, ${BRAND.green} 0%, ${BRAND.greenDeep} 100%)`, borderRadius: 12, paddingTop: 16, paddingBottom: 16, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, boxShadow: `0 6px 20px ${BRAND.green}40` }}>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <span style={{ fontSize: 26, fontWeight: 900, color: 'white' }}>JÁ PAGUEI</span>
            </div>
          </div>
        </div>

        {/* Auto-verify footer */}
        <div style={{ opacity: verifyOpacity, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, marginTop: 14 }}>
          <div style={{ display: 'flex', gap: 4 }}>
            {[0, 1, 2].map(i => (
              <div key={i} style={{ width: 8, height: 8, borderRadius: '50%', background: frame % 30 < 10 + i * 10 ? BRAND.green : '#CCC' }} />
            ))}
          </div>
          <span style={{ fontSize: 18, color: '#AAA', fontWeight: 500 }}>Verificando pagamento automaticamente</span>
        </div>

      </div>

      {/* Cursor */}
      <div style={{ position: 'absolute', left: cursorLeft, top: cursorTop, opacity: cursorOpacity * cursorFade, transform: `scale(${fingerScale})`, zIndex: 20, pointerEvents: 'none' }}>
        <div style={{ width: 76, height: 76, borderRadius: '50%', background: 'rgba(255,255,255,0.95)', border: '4px solid #1565C0', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 8px 24px rgba(0,0,0,0.3)', fontSize: 36 }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
