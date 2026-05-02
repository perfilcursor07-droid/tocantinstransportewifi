import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

const PixLogo: React.FC = () => (
  <svg width="80" height="80" viewBox="0 0 100 100" fill="none">
    {/* PIX rhombus shape */}
    <path d="M50 5 L95 50 L50 95 L5 50 Z" fill="#32BCAD" />
    <path d="M50 28 L72 50 L50 72 L28 50 Z" fill="white" />
    <path d="M50 38 L62 50 L50 62 L38 50 Z" fill="#32BCAD" />
  </svg>
);

const QRCodePlaceholder: React.FC = () => {
  const size = 220;
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
    <div style={{ width: size, height: size, background: 'white', padding: 10, borderRadius: 16, boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
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

export const PixScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const logoScale = spring({ fps, frame, config: { damping: 13, stiffness: 170, mass: 0.6 }, durationInFrames: 38 });

  const titleOpacity = interpolate(frame, [20, 42], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [20, 42], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const qrOpacity = interpolate(frame, [40, 65], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const qrScale = interpolate(frame, [40, 65], [0.85, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });

  const tagsOpacity = interpolate(frame, [70, 90], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const descOpacity = interpolate(frame, [90, 110], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const descY = interpolate(frame, [90, 110], [25, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  return (
    <AbsoluteFill style={{ background: 'white', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 70, paddingRight: 70 }}>

      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* PIX Logo & Title */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 20, marginBottom: 36 }}>
        <div style={{ transform: `scale(${logoScale})` }}>
          <PixLogo />
        </div>
        <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)` }}>
          <div style={{ fontSize: 70, fontWeight: 900, color: BRAND.ink, letterSpacing: -2, lineHeight: 1.0 }}>PAGAMENTO</div>
          <div style={{ fontSize: 70, fontWeight: 900, color: '#32BCAD', letterSpacing: -2, lineHeight: 1.0 }}>VIA PIX</div>
        </div>
      </div>

      {/* QR Code */}
      <div style={{ opacity: qrOpacity, transform: `scale(${qrScale})`, marginBottom: 32 }}>
        <div style={{ padding: 12, background: '#F0FAFA', borderRadius: 24, border: `2.5px solid #32BCAD30` }}>
          <QRCodePlaceholder />
        </div>
      </div>

      {/* Tags */}
      <div style={{ opacity: tagsOpacity, display: 'flex', gap: 16, marginBottom: 32 }}>
        {[
          { emoji: '⚡', label: 'Rápido', color: '#F59E0B' },
          { emoji: '🔒', label: 'Seguro', color: BRAND.green },
          { emoji: '✅', label: 'Automático', color: '#32BCAD' },
        ].map(({ emoji, label, color }) => (
          <div key={label} style={{ background: `${color}15`, border: `1.5px solid ${color}30`, borderRadius: 20, paddingLeft: 20, paddingRight: 20, paddingTop: 10, paddingBottom: 10, display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 24 }}>{emoji}</span>
            <span style={{ fontSize: 24, fontWeight: 700, color }}>{label}</span>
          </div>
        ))}
      </div>

      {/* Instructions */}
      <div style={{ opacity: descOpacity, transform: `translateY(${descY}px)`, textAlign: 'center' }}>
        <div style={{ background: '#F0FAFA', border: `1.5px solid #32BCAD30`, borderRadius: 20, paddingLeft: 32, paddingRight: 32, paddingTop: 20, paddingBottom: 20 }}>
          <div style={{ fontSize: 30, fontWeight: 600, color: '#1a7a72', lineHeight: 1.55 }}>
            Aponte a câmera para o QR Code
          </div>
          <div style={{ fontSize: 30, fontWeight: 600, color: '#1a7a72', lineHeight: 1.55 }}>
            ou copie a chave PIX
          </div>
          <div style={{ fontSize: 27, fontWeight: 500, color: '#4a9a92', marginTop: 8 }}>
            Internet liberada automaticamente!
          </div>
        </div>
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
