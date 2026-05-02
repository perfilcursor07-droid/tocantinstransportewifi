import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { heroGradient } from '../colors';
import { fontFamily } from '../font';

export const OutroScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const iconScale = spring({ fps, frame, config: { damping: 14, stiffness: 180, mass: 0.6 }, durationInFrames: 30 });

  const titleOpacity = interpolate(frame, [14, 32], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [14, 32], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const brandOpacity = interpolate(frame, [28, 46], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const brandY = interpolate(frame, [28, 46], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>

      <div style={{ position: 'absolute', top: -100, right: -100, width: 400, height: 400, borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }} />
      <div style={{ position: 'absolute', bottom: -60, left: -60, width: 300, height: 300, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />

      {/* WiFi icon */}
      <div style={{ transform: `scale(${iconScale})`, marginBottom: 40 }}>
        <div style={{ width: 160, height: 160, borderRadius: 44, background: 'rgba(255,255,255,0.18)', border: '3px solid rgba(255,255,255,0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <svg width="88" height="88" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.8" strokeLinecap="round">
            <path d="M1.42 9a16 16 0 0 1 21.16 0" />
            <path d="M5 12.55a11 11 0 0 1 14.08 0" />
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
            <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
          </svg>
        </div>
      </div>

      {/* "Boa viagem!" */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 20 }}>
        <div style={{ fontSize: 90, fontWeight: 900, color: 'white', lineHeight: 1.0, letterSpacing: -3 }}>Boa viagem!</div>
      </div>

      {/* Brand */}
      <div style={{ opacity: brandOpacity, transform: `translateY(${brandY}px)`, textAlign: 'center' }}>
        <div style={{ fontSize: 34, fontWeight: 600, color: 'rgba(255,255,255,0.80)' }}>WiFi Tocantins Express</div>
        <div style={{ fontSize: 26, fontWeight: 500, color: 'rgba(255,255,255,0.60)', marginTop: 8 }}>Internet via Starlink</div>
      </div>
    </AbsoluteFill>
  );
};
