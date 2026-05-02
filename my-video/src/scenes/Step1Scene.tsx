import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { heroGradient, BRAND } from '../colors';
import { fontFamily } from '../font';

export const Step1Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const badgeScale = spring({ fps, frame, config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 28 });
  const titleOpacity = interpolate(frame, [18, 38], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [18, 38], [36, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cardOpacity = interpolate(frame, [35, 58], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cardY = interpolate(frame, [35, 58], [28, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  // Cursor to TOCANTINSTRANSPORTEWIFI row
  const cursorOpacity = interpolate(frame, [90, 108], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const cursorLeft = interpolate(frame, [90, 132], [820, 390], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const cursorTop = interpolate(frame, [90, 132], [300, 1100], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });
  const fingerScale = frame >= 133 && frame < 153
    ? interpolate(frame, [133, 142, 153], [1, 0.55, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
    : 1;
  const cursorFade = interpolate(frame, [158, 174], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const isConnected = frame >= 148;
  const connectedOpacity = interpolate(frame, [148, 165], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const connectedScale = spring({ fps, frame: Math.max(0, frame - 148), config: { damping: 10, stiffness: 250 }, durationInFrames: 22 });
  const descOpacity = interpolate(frame, [175, 195], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const networks = [
    { name: 'WiFi-Casa_5G', bars: 2 },
    { name: 'CLARO_FIBRA', bars: 3 },
    { name: 'Vivo_Fibra_3F', bars: 1 },
    { name: 'TOCANTINSTRANSPORTEWIFI', bars: 4 },
    { name: 'TIM_NET_7C', bars: 2 },
  ];

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', top: -120, right: -120, width: 500, height: 500, borderRadius: '50%', background: 'rgba(255,255,255,0.06)' }} />
      <div style={{ position: 'absolute', bottom: -80, left: -80, width: 300, height: 300, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />

      {/* Step badge */}
      <div style={{ transform: `scale(${badgeScale})`, marginBottom: 40 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, background: 'rgba(255,255,255,0.18)', border: '2px solid rgba(255,255,255,0.3)', borderRadius: 60, paddingLeft: 24, paddingRight: 32, paddingTop: 12, paddingBottom: 12 }}>
          <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'rgba(255,255,255,0.28)', border: '2px solid white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ fontSize: 26, fontWeight: 900, color: 'white' }}>1</span>
          </div>
          <span style={{ fontSize: 26, fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>PASSO 1</span>
        </div>
      </div>

      {/* Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 38, paddingLeft: 64, paddingRight: 64 }}>
        <div style={{ fontSize: 56, fontWeight: 900, color: 'white', letterSpacing: -1.5, lineHeight: 1.08 }}>CONECTE-SE À REDE</div>
        <div style={{ fontSize: 56, fontWeight: 900, color: 'rgba(255,255,255,0.88)', letterSpacing: -1.5, lineHeight: 1.08 }}>WiFi DO ÔNIBUS</div>
        <div style={{ fontSize: 26, color: 'rgba(255,255,255,0.7)', marginTop: 12, fontWeight: 500 }}>
          Abra as configurações Wi-Fi do seu celular
        </div>
      </div>

      {/* WiFi settings mockup */}
      <div style={{ opacity: cardOpacity, transform: `translateY(${cardY}px)`, width: 820, background: 'white', borderRadius: 24, overflow: 'hidden', boxShadow: '0 24px 64px rgba(0,0,0,0.32)' }}>
        {/* Header bar */}
        <div style={{ background: '#F5F5F5', display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingLeft: 26, paddingRight: 26, paddingTop: 16, paddingBottom: 16, borderBottom: '1px solid #E5E5E5' }}>
          <span style={{ fontSize: 22, fontWeight: 700, color: '#222' }}>Configurações › Wi-Fi</span>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 19, color: '#555', fontWeight: 500 }}>Wi-Fi</span>
            <div style={{ width: 50, height: 26, borderRadius: 13, background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'flex-end', paddingRight: 2 }}>
              <div style={{ width: 20, height: 20, borderRadius: '50%', background: 'white', boxShadow: '0 1px 4px rgba(0,0,0,0.15)' }} />
            </div>
          </div>
        </div>

        {/* Section label */}
        <div style={{ fontSize: 16, fontWeight: 700, color: '#999', letterSpacing: 0.8, paddingLeft: 24, paddingRight: 24, paddingTop: 12, paddingBottom: 8 }}>
          REDES DISPONÍVEIS
        </div>

        {/* Network list */}
        {networks.map((net, i) => {
          const isTocantins = net.name === 'TOCANTINSTRANSPORTEWIFI';
          const isActive = isTocantins && frame >= 85;
          const showConnected = isTocantins && isConnected;
          return (
            <div key={net.name} style={{
              display: 'flex', alignItems: 'center', justifyContent: 'space-between',
              paddingTop: 14, paddingBottom: 14,
              paddingLeft: isActive ? 32 : 24, paddingRight: isActive ? 20 : 24,
              borderBottom: i < networks.length - 1 ? '1px solid #F2F2F2' : 'none',
              background: isActive ? `${BRAND.green}10` : 'transparent',
              borderRadius: isActive ? 0 : 0,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                <svg width="20" height="18" viewBox="0 0 24 20" fill="none">
                  {[1, 2, 3, 4].map(b => (
                    <rect key={b} x={b * 5 - 3} y={20 - b * 4} width="3.5" height={b * 4}
                      fill={b <= net.bars && isActive ? BRAND.green : b <= net.bars ? '#999' : '#DDD'} rx="1.5" />
                  ))}
                </svg>
                <div>
                  <div style={{ fontSize: isTocantins ? 21 : 19, fontWeight: isTocantins ? 800 : 500, color: isActive ? BRAND.green : '#333', letterSpacing: isTocantins ? -0.3 : 0 }}>
                    {net.name}
                  </div>
                  {isTocantins && (
                    <div style={{ fontSize: 15, color: isActive ? BRAND.greenDark : '#888', fontWeight: 500 }}>
                      WiFi do Ônibus Tocantins • Starlink
                    </div>
                  )}
                </div>
              </div>
              {showConnected ? (
                <div style={{ opacity: connectedOpacity, transform: `scale(${connectedScale})`, display: 'flex', alignItems: 'center', gap: 7 }}>
                  <span style={{ fontSize: 16, fontWeight: 700, color: BRAND.green }}>Conectado</span>
                  <div style={{ width: 21, height: 21, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  </div>
                </div>
              ) : (
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={isActive ? BRAND.green : '#CCC'} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              )}
            </div>
          );
        })}
      </div>

      {/* Tip */}
      <div style={{ opacity: descOpacity, marginTop: 28, display: 'flex', alignItems: 'center', gap: 12, background: 'rgba(255,255,255,0.15)', borderRadius: 16, paddingLeft: 24, paddingRight: 28, paddingTop: 14, paddingBottom: 14 }}>
        <span style={{ fontSize: 24 }}>💡</span>
        <span style={{ fontSize: 21, color: 'rgba(255,255,255,0.92)', fontWeight: 500 }}>A tela do portal abrirá automaticamente após conectar</span>
      </div>

      {/* Cursor */}
      <div style={{ position: 'absolute', left: cursorLeft, top: cursorTop, opacity: cursorOpacity * cursorFade, transform: `scale(${fingerScale})`, zIndex: 20, pointerEvents: 'none' }}>
        <div style={{ width: 74, height: 74, borderRadius: '50%', background: 'rgba(255,255,255,0.92)', border: `4px solid ${BRAND.green}`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 8px 24px rgba(0,0,0,0.3)', fontSize: 35 }}>
          👆
        </div>
      </div>
    </AbsoluteFill>
  );
};
