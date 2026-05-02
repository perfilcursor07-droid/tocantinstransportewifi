import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { heroGradient, BRAND } from '../colors';
import { fontFamily } from '../font';

// Mini QR code SVG for the bus seat
const MiniQR: React.FC = () => {
  const size = 160;
  const cell = size / 10;
  const pattern = [
    [1,1,1,0,1,0,1,1,1],
    [1,0,1,0,0,0,1,0,1],
    [1,0,1,0,1,0,1,0,1],
    [1,0,1,0,1,1,1,0,1],
    [1,1,1,0,0,1,0,0,0],
    [0,1,0,1,0,1,1,1,0],
    [1,1,1,0,1,0,1,0,1],
    [1,0,1,0,0,1,0,1,1],
    [1,1,1,1,1,0,1,1,1],
  ];
  return (
    <div style={{ width: size, height: size, background: 'white', padding: 8, borderRadius: 10, boxShadow: '0 4px 16px rgba(0,0,0,0.15)' }}>
      {pattern.map((row, y) => (
        <div key={y} style={{ display: 'flex' }}>
          {row.map((c, x) => (
            <div key={x} style={{ width: cell - 0.5, height: cell - 0.5, background: c ? '#111' : 'white', margin: 0.25 }} />
          ))}
        </div>
      ))}
    </div>
  );
};

export const Step2Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // Phase 1: Auto-redirect (0–110f)
  // Phase 2: QR code alternative (110–210f)
  const phase2 = frame >= 110;

  const phase1Opacity = interpolate(frame, [0, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const phase1FadeOut = interpolate(frame, [95, 110], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const phase2Opacity = interpolate(frame, [115, 132], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Browser bar typing animation (phase 1)
  const urlProgress = interpolate(frame, [30, 70], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const fullUrl = 'www.tocantinstransportewifi.com.br';
  const urlText = fullUrl.slice(0, Math.floor(urlProgress * fullUrl.length));

  // Portal loading bar (phase 1)
  const loadProgress = interpolate(frame, [55, 95], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  // Check icon for auto-redirect
  const checkScale = spring({ fps, frame: Math.max(0, frame - 60), config: { damping: 10, stiffness: 260 }, durationInFrames: 22 });

  // Phase 2 - QR elements
  const qrScale = spring({ fps, frame: Math.max(0, frame - 118), config: { damping: 11, stiffness: 200, mass: 0.6 }, durationInFrames: 28 });
  const scanLineY = ((frame - 118) * 5) % 160;
  const phoneBeamOpacity = interpolate(frame, [148, 165], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', top: -100, right: -100, width: 450, height: 450, borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }} />
      <div style={{ position: 'absolute', bottom: -80, left: -80, width: 280, height: 280, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />

      {/* === PHASE 1: Auto-redirect === */}
      {!phase2 && (
        <div style={{ opacity: phase1Opacity * phase1FadeOut, width: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', paddingLeft: 60, paddingRight: 60 }}>
          {/* Step badge */}
          <div style={{ marginBottom: 36, display: 'flex', alignItems: 'center', gap: 14, background: 'rgba(255,255,255,0.18)', border: '2px solid rgba(255,255,255,0.3)', borderRadius: 60, paddingLeft: 24, paddingRight: 32, paddingTop: 12, paddingBottom: 12 }}>
            <div style={{ width: 46, height: 46, borderRadius: '50%', background: 'rgba(255,255,255,0.28)', border: '2px solid white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <span style={{ fontSize: 24, fontWeight: 900, color: 'white' }}>2</span>
            </div>
            <span style={{ fontSize: 25, fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>PASSO 2</span>
          </div>

          <div style={{ textAlign: 'center', marginBottom: 44 }}>
            <div style={{ fontSize: 56, fontWeight: 900, color: 'white', letterSpacing: -1.5, lineHeight: 1.08 }}>REDIRECIONADO</div>
            <div style={{ fontSize: 56, fontWeight: 900, color: 'rgba(255,255,255,0.85)', letterSpacing: -1.5, lineHeight: 1.08 }}>AUTOMATICAMENTE</div>
            <div style={{ fontSize: 26, color: 'rgba(255,255,255,0.7)', marginTop: 14, fontWeight: 500 }}>O portal abrirá no navegador do seu celular</div>
          </div>

          {/* Phone browser mockup */}
          <div style={{ width: 820, background: 'white', borderRadius: 24, overflow: 'hidden', boxShadow: '0 24px 64px rgba(0,0,0,0.3)' }}>
            {/* Browser chrome */}
            <div style={{ background: '#F5F5F5', padding: '14px 18px', borderBottom: '1px solid #E5E5E5', display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ display: 'flex', gap: 7 }}>
                {['#FF5F56', '#FFBD2E', '#27C93F'].map(c => (
                  <div key={c} style={{ width: 14, height: 14, borderRadius: '50%', background: c }} />
                ))}
              </div>
              {/* URL bar */}
              <div style={{ flex: 1, background: 'white', border: '1.5px solid #DDD', borderRadius: 8, paddingLeft: 14, paddingRight: 14, paddingTop: 8, paddingBottom: 8, display: 'flex', alignItems: 'center', gap: 8 }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={BRAND.green} strokeWidth="2.5" strokeLinecap="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <span style={{ fontSize: 17, color: frame >= 65 ? '#333' : '#999', fontFamily: 'monospace', letterSpacing: 0 }}>
                  {urlText || 'Abrindo portal...'}
                </span>
                {urlProgress >= 1 && (
                  <div style={{ marginLeft: 'auto', transform: `scale(${checkScale})` }}>
                    <div style={{ width: 18, height: 18, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                  </div>
                )}
              </div>
            </div>
            {/* Loading bar */}
            <div style={{ height: 4, background: '#F0F0F0' }}>
              <div style={{ height: '100%', width: `${loadProgress * 100}%`, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})`, borderRadius: 2 }} />
            </div>
            {/* Page content preview */}
            <div style={{ padding: '20px 24px', display: 'flex', alignItems: 'center', gap: 16 }}>
              <div style={{ width: 40, height: 40, borderRadius: 10, background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                  <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                  <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                  <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
                </svg>
              </div>
              <div>
                <div style={{ fontSize: 18, fontWeight: 700, color: '#222' }}>WiFi Tocantins Express</div>
                <div style={{ fontSize: 14, color: '#888' }}>tocantinstransportewifi.com.br</div>
              </div>
              <div style={{ marginLeft: 'auto', fontSize: 15, fontWeight: 600, color: BRAND.green }}>
                {loadProgress >= 0.9 ? '✓ Portal carregado' : 'Carregando...'}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* === PHASE 2: QR Code alternative === */}
      {phase2 && (
        <div style={{ opacity: phase2Opacity, width: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', paddingLeft: 60, paddingRight: 60 }}>
          <div style={{ textAlign: 'center', marginBottom: 36 }}>
            <div style={{ fontSize: 48, fontWeight: 900, color: 'white', letterSpacing: -1.5, lineHeight: 1.1 }}>NÃO REDIRECIONOU?</div>
            <div style={{ fontSize: 34, fontWeight: 600, color: 'rgba(255,255,255,0.85)', marginTop: 10 }}>Aponte o celular para o QR Code</div>
            <div style={{ fontSize: 26, fontWeight: 500, color: 'rgba(255,255,255,0.7)', marginTop: 8 }}>Disponível no banco da sua frente no ônibus</div>
          </div>

          {/* Bus seat card with QR */}
          <div style={{ display: 'flex', gap: 48, alignItems: 'center', background: 'rgba(255,255,255,0.12)', borderRadius: 28, paddingLeft: 56, paddingRight: 56, paddingTop: 44, paddingBottom: 44, border: '2px solid rgba(255,255,255,0.22)' }}>
            {/* QR code with scan animation */}
            <div style={{ transform: `scale(${qrScale})`, position: 'relative' }}>
              <MiniQR />
              {/* Scan line */}
              {frame >= 118 && (
                <div style={{ position: 'absolute', left: 8, right: 8, top: 8 + (scanLineY % 144), height: 2, background: `${BRAND.greenLight}CC`, borderRadius: 1, boxShadow: `0 0 8px ${BRAND.greenLight}` }} />
              )}
              {/* Corner brackets */}
              {[
                { top: 0, left: 0, borderTop: '3px solid', borderLeft: '3px solid' },
                { top: 0, right: 0, borderTop: '3px solid', borderRight: '3px solid' },
                { bottom: 0, left: 0, borderBottom: '3px solid', borderLeft: '3px solid' },
                { bottom: 0, right: 0, borderBottom: '3px solid', borderRight: '3px solid' },
              ].map((style, i) => (
                <div key={i} style={{ position: 'absolute', width: 22, height: 22, borderColor: BRAND.greenLight, ...style }} />
              ))}
            </div>

            {/* Instructions */}
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 30, fontWeight: 800, color: 'white', marginBottom: 16, lineHeight: 1.3 }}>
                📱 Aponte a câmera
              </div>
              {[
                'QR Code no banco da frente',
                'Será redirecionado ao portal',
                'Escolha seu plano e conecte',
              ].map((step, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                  <div style={{ width: 28, height: 28, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                    <span style={{ fontSize: 14, fontWeight: 800, color: 'white' }}>{i + 1}</span>
                  </div>
                  <span style={{ fontSize: 24, color: 'rgba(255,255,255,0.88)', fontWeight: 500 }}>{step}</span>
                </div>
              ))}
            </div>
          </div>

          {/* URL tip */}
          <div style={{ opacity: phoneBeamOpacity, marginTop: 28, display: 'flex', alignItems: 'center', gap: 12, background: 'rgba(255,255,255,0.15)', borderRadius: 14, paddingLeft: 22, paddingRight: 26, paddingTop: 12, paddingBottom: 12 }}>
            <span style={{ fontSize: 22 }}>🌐</span>
            <span style={{ fontSize: 21, color: 'rgba(255,255,255,0.92)', fontWeight: 500 }}>
              Ou acesse: <strong>www.tocantinstransportewifi.com.br</strong>
            </span>
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};
