import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

export const PlansScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const headingScale = spring({ fps, frame, config: { damping: 14, stiffness: 160, mass: 0.7 }, durationInFrames: 35 });

  const card1Opacity = interpolate(frame, [28, 52], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const card1X = interpolate(frame, [28, 52], [-80, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const card2Opacity = interpolate(frame, [44, 68], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const card2X = interpolate(frame, [44, 68], [80, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const appsOpacity = interpolate(frame, [80, 105], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const appsY = interpolate(frame, [80, 105], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.quad) });

  const featuresOpacity = interpolate(frame, [105, 130], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const apps = [
    { name: 'Instagram', color: '#E1306C', bg: '#FFF0F5' },
    { name: 'YouTube', color: '#FF0000', bg: '#FFF5F5' },
    { name: 'Netflix', color: '#E50914', bg: '#FFF5F5' },
    { name: 'WhatsApp', color: '#25D366', bg: '#F0FFF5' },
    { name: 'Facebook', color: '#1877F2', bg: '#F0F5FF' },
    { name: 'TikTok', color: '#010101', bg: '#F5F5F5' },
  ];

  return (
    <AbsoluteFill style={{ background: BRAND.surface, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 64, paddingRight: 64 }}>

      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Heading */}
      <div style={{ transform: `scale(${headingScale})`, textAlign: 'center', marginBottom: 44 }}>
        <div style={{ fontSize: 70, fontWeight: 900, color: BRAND.ink, lineHeight: 1.0, letterSpacing: -2 }}>ESCOLHA</div>
        <div style={{ fontSize: 70, fontWeight: 900, color: BRAND.green, lineHeight: 1.0, letterSpacing: -2 }}>SEU PLANO</div>
      </div>

      {/* Plan Card 1 - 1 hora */}
      <div style={{ opacity: card1Opacity, transform: `translateX(${card1X}px)`, width: '100%', marginBottom: 20 }}>
        <div style={{ background: 'white', borderRadius: 28, padding: 32, border: `2px solid ${BRAND.border}`, boxShadow: '0 4px 20px rgba(0,0,0,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div>
            <div style={{ fontSize: 36, fontWeight: 800, color: BRAND.ink, marginBottom: 6 }}>1 hora de acesso</div>
            <div style={{ fontSize: 26, fontWeight: 500, color: BRAND.muted }}>Ideal para uso rápido</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: 46, fontWeight: 900, color: BRAND.ink, letterSpacing: -1 }}>R$5,99</div>
            <div style={{ fontSize: 22, fontWeight: 500, color: BRAND.muted }}>/ hora</div>
          </div>
        </div>
      </div>

      {/* Plan Card 2 - Viagem completa */}
      <div style={{ opacity: card2Opacity, transform: `translateX(${card2X}px)`, width: '100%', marginBottom: 36 }}>
        <div style={{ position: 'relative' }}>
          {/* Most chosen badge */}
          <div style={{ position: 'absolute', top: -16, left: 32, background: `linear-gradient(90deg, ${BRAND.green}, ${BRAND.greenDark})`, borderRadius: 20, paddingLeft: 20, paddingRight: 20, paddingTop: 6, paddingBottom: 6, zIndex: 1, boxShadow: '0 4px 12px rgba(0,163,53,0.3)' }}>
            <span style={{ fontSize: 20, fontWeight: 900, color: 'white', letterSpacing: 0.5 }}>⭐ MAIS ESCOLHIDO</span>
          </div>
          <div style={{ background: 'linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%)', borderRadius: 28, padding: 32, paddingTop: 40, border: `2.5px solid ${BRAND.green}`, boxShadow: `0 0 0 4px ${BRAND.green}15, 0 8px 32px rgba(0,163,53,0.12)`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div>
              <div style={{ fontSize: 38, fontWeight: 900, color: BRAND.ink, marginBottom: 6 }}>Viagem completa</div>
              <div style={{ fontSize: 26, fontWeight: 600, color: BRAND.greenDark }}>WiFi até o destino final</div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, justifyContent: 'flex-end', marginBottom: 4 }}>
                <span style={{ fontSize: 24, fontWeight: 600, color: '#aaa', textDecoration: 'line-through' }}>R$9,99</span>
                <span style={{ fontSize: 18, fontWeight: 900, color: 'white', background: BRAND.red, borderRadius: 8, paddingLeft: 8, paddingRight: 8, paddingTop: 3, paddingBottom: 3 }}>-30%</span>
              </div>
              <div style={{ fontSize: 54, fontWeight: 900, color: BRAND.greenDark, letterSpacing: -2, lineHeight: 1 }}>R$6,99</div>
              <div style={{ fontSize: 22, fontWeight: 500, color: BRAND.greenDark }}>/ viagem</div>
            </div>
          </div>
        </div>
      </div>

      {/* App icons */}
      <div style={{ opacity: appsOpacity, transform: `translateY(${appsY}px)`, display: 'flex', gap: 16, marginBottom: 24, flexWrap: 'wrap', justifyContent: 'center' }}>
        {apps.map((app) => (
          <div key={app.name} style={{ width: 72, height: 72, borderRadius: 20, background: app.bg, border: `1.5px solid ${app.color}20`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 2px 8px rgba(0,0,0,0.06)' }}>
            <span style={{ fontSize: 14, fontWeight: 700, color: app.color, textAlign: 'center', lineHeight: 1.2 }}>{app.name.substring(0, 2)}</span>
          </div>
        ))}
      </div>

      {/* Features */}
      <div style={{ opacity: featuresOpacity, display: 'flex', gap: 24, justifyContent: 'center' }}>
        {[
          { icon: '🔒', label: 'Pagamento seguro' },
          { icon: '⚡', label: 'PIX instantâneo' },
          { icon: '✅', label: 'Liberação automática' },
        ].map(({ icon, label }) => (
          <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 22 }}>{icon}</span>
            <span style={{ fontSize: 22, fontWeight: 600, color: BRAND.muted }}>{label}</span>
          </div>
        ))}
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
