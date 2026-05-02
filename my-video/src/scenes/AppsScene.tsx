import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

const AppIcon: React.FC<{ name: string; color: string; bg: string; delay: number; frame: number; fps: number; letter: string; }> = ({
  name, color, bg, delay, frame, fps, letter,
}) => {
  const scale = spring({ fps, frame: Math.max(0, frame - delay), config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 28 });
  const opacity = interpolate(frame, [delay, delay + 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <div style={{ opacity, transform: `scale(${scale})`, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
      <div style={{
        width: 120, height: 120, borderRadius: 32,
        background: bg,
        border: `2px solid ${color}30`,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        boxShadow: `0 8px 24px ${color}20`,
      }}>
        <span style={{ fontSize: 38, fontWeight: 900, color, fontFamily }}>{letter}</span>
      </div>
      <span style={{ fontSize: 22, fontWeight: 600, color: BRAND.muted, fontFamily }}>{name}</span>
    </div>
  );
};

export const AppsScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const titleOpacity = interpolate(frame, [0, 22], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [0, 22], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const subtitleOpacity = interpolate(frame, [14, 35], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const apps = [
    { name: 'Instagram', letter: 'Ig', color: '#E1306C', bg: '#FFF0F5', delay: 30 },
    { name: 'YouTube', letter: 'Yt', color: '#FF0000', bg: '#FFF5F5', delay: 38 },
    { name: 'Netflix', letter: 'Nf', color: '#E50914', bg: '#FFF5F5', delay: 46 },
    { name: 'WhatsApp', letter: 'Wa', color: '#25D366', bg: '#F0FFF5', delay: 54 },
    { name: 'Facebook', letter: 'Fb', color: '#1877F2', bg: '#F0F5FF', delay: 62 },
    { name: 'TikTok', letter: 'Tt', color: '#010101', bg: '#F5F5F5', delay: 70 },
  ];

  const footerOpacity = interpolate(frame, [95, 115], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: BRAND.surface, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 70, paddingRight: 70 }}>

      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 10 }}>
        <div style={{ fontSize: 68, fontWeight: 900, color: BRAND.ink, lineHeight: 1.05, letterSpacing: -2 }}>APROVEITE SEUS</div>
      </div>
      <div style={{ opacity: subtitleOpacity, textAlign: 'center', marginBottom: 56 }}>
        <div style={{ fontSize: 68, fontWeight: 900, color: BRAND.green, lineHeight: 1.05, letterSpacing: -2 }}>APPS FAVORITOS</div>
      </div>

      {/* Apps Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 32, marginBottom: 52, width: '100%' }}>
        {apps.map((app) => (
          <div key={app.name} style={{ display: 'flex', justifyContent: 'center' }}>
            <AppIcon {...app} frame={frame} fps={fps} />
          </div>
        ))}
      </div>

      {/* Footer tagline */}
      <div style={{ opacity: footerOpacity, background: 'white', borderRadius: 24, paddingLeft: 36, paddingRight: 36, paddingTop: 20, paddingBottom: 20, boxShadow: '0 4px 20px rgba(0,0,0,0.05)', border: `1.5px solid ${BRAND.border}` }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 30, fontWeight: 700, color: BRAND.greenDark }}>
            Internet de alta velocidade
          </div>
          <div style={{ fontSize: 26, fontWeight: 500, color: BRAND.muted, marginTop: 4 }}>
            via Starlink no seu ônibus 🚌
          </div>
        </div>
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
