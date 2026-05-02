import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { heroGradient } from '../colors';
import { fontFamily } from '../font';

export const IntroScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const iconScale = spring({ fps, frame, config: { damping: 14, stiffness: 160, mass: 0.7 }, durationInFrames: 45 });

  const titleOpacity = interpolate(frame, [18, 42], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const titleY = interpolate(frame, [18, 42], [55, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const subtitleOpacity = interpolate(frame, [36, 58], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const subtitleY = interpolate(frame, [36, 58], [35, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const badgeOpacity = interpolate(frame, [55, 75], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const badgeScale = interpolate(frame, [55, 75], [0.7, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(2)) });

  const taglineOpacity = interpolate(frame, [72, 95], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: heroGradient, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, overflow: 'hidden' }}>
      {/* Decorative circles */}
      <div style={{ position: 'absolute', top: -120, right: -120, width: 480, height: 480, borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }} />
      <div style={{ position: 'absolute', bottom: 60, left: -100, width: 360, height: 360, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />
      <div style={{ position: 'absolute', bottom: -80, right: 80, width: 240, height: 240, borderRadius: '50%', background: 'rgba(255,255,255,0.03)' }} />

      {/* WiFi Icon */}
      <div style={{ transform: `scale(${iconScale})`, marginBottom: 52 }}>
        <div style={{
          width: 200, height: 200, borderRadius: 56,
          background: 'rgba(255,255,255,0.18)',
          border: '3px solid rgba(255,255,255,0.35)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 24px 80px rgba(0,0,0,0.25)',
        }}>
          <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M1.42 9a16 16 0 0 1 21.16 0" />
            <path d="M5 12.55a11 11 0 0 1 14.08 0" />
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
            <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
          </svg>
        </div>
      </div>

      {/* Brand Title */}
      <div style={{ opacity: titleOpacity, transform: `translateY(${titleY}px)`, textAlign: 'center', marginBottom: 24, paddingLeft: 60, paddingRight: 60 }}>
        <div style={{ fontSize: 86, fontWeight: 900, color: 'white', lineHeight: 1.0, letterSpacing: -3 }}>WiFi</div>
        <div style={{ fontSize: 86, fontWeight: 900, color: 'white', lineHeight: 1.0, letterSpacing: -3, marginBottom: 12 }}>Tocantins</div>
        <div style={{ fontSize: 40, fontWeight: 600, color: 'rgba(255,255,255,0.78)', letterSpacing: 6 }}>EXPRESS</div>
      </div>

      {/* Subtitle */}
      <div style={{ opacity: subtitleOpacity, transform: `translateY(${subtitleY}px)`, textAlign: 'center', marginBottom: 64, paddingLeft: 80, paddingRight: 80 }}>
        <div style={{ fontSize: 34, fontWeight: 500, color: 'rgba(255,255,255,0.72)', lineHeight: 1.45 }}>
          Internet de alta velocidade
        </div>
        <div style={{ fontSize: 34, fontWeight: 500, color: 'rgba(255,255,255,0.72)' }}>
          no seu ônibus
        </div>
      </div>

      {/* Starlink Badge */}
      <div style={{
        opacity: badgeOpacity, transform: `scale(${badgeScale})`,
        background: 'rgba(255,255,255,0.15)', border: '2px solid rgba(255,255,255,0.3)',
        borderRadius: 60, paddingLeft: 32, paddingRight: 32, paddingTop: 16, paddingBottom: 16,
        display: 'flex', alignItems: 'center', gap: 14,
        boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
        marginBottom: 40,
      }}>
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.5" strokeLinecap="round">
          <circle cx="12" cy="12" r="2.5" fill="white" stroke="none" />
          <path d="M20.2 20.2c2.04-2.03.02-7.36-4.5-11.9-4.54-4.52-9.87-6.54-11.9-4.5-2.04 2.03-.02 7.36 4.5 11.9 4.54 4.52 9.87 6.54 11.9 4.5z" />
          <path d="M15.7 15.7c4.52-4.54 6.54-9.87 4.5-11.9-2.03-2.04-7.36-.02-11.9 4.5-4.52 4.54-6.54 9.87-4.5 11.9 2.03 2.04 7.36.02 11.9-4.5z" />
        </svg>
        <span style={{ fontSize: 28, fontWeight: 700, color: 'rgba(255,255,255,0.92)' }}>Powered by Starlink</span>
      </div>

      {/* Tutorial tag */}
      <div style={{ opacity: taglineOpacity, display: 'flex', alignItems: 'center', gap: 10 }}>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="white" opacity={0.7}>
          <path d="M8 5v14l11-7z" />
        </svg>
        <span style={{ fontSize: 26, fontWeight: 600, color: 'rgba(255,255,255,0.70)', letterSpacing: 0.5 }}>Como usar • Passo a passo</span>
      </div>
    </AbsoluteFill>
  );
};
