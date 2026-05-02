import React from 'react';
import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

// App icons matching image 1: Instagram, WhatsApp, YouTube, Facebook, TikTok, Netflix
const AppIcon: React.FC<{ emoji: string; bg: string; border: string }> = ({ emoji, bg, border }) => (
  <div style={{ width: 62, height: 62, borderRadius: 16, background: bg, border: `1.5px solid ${border}`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 30, boxShadow: '0 2px 8px rgba(0,0,0,0.08)' }}>
    {emoji}
  </div>
);

export const PlansScene: React.FC = () => {
  const frame = useCurrentFrame();

  const containerOpacity = interpolate(frame, [0, 22], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const containerY = interpolate(frame, [0, 22], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const planCardOpacity = interpolate(frame, [22, 44], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const planCardScale = interpolate(frame, [22, 44], [0.95, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.3)) });

  const appsOpacity = interpolate(frame, [50, 70], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const btnOpacity = interpolate(frame, [65, 84], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const btnScale = interpolate(frame, [65, 84], [0.9, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.back(1.5)) });

  const trustOpacity = interpolate(frame, [85, 104], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const accordionOpacity = interpolate(frame, [105, 124], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Button pulsing
  const pulseScale = 1 + Math.sin((frame / 28) * Math.PI * 2) * 0.018;

  return (
    <AbsoluteFill style={{ background: '#F0F2F5', display: 'flex', flexDirection: 'column', alignItems: 'center', fontFamily, overflow: 'hidden' }}>

      {/* Scrollable content */}
      <div style={{ opacity: containerOpacity, transform: `translateY(${containerY}px)`, width: '100%', maxWidth: 900, paddingLeft: 56, paddingRight: 56, paddingTop: 60, paddingBottom: 40 }}>

        {/* Header card */}
        <div style={{ background: 'white', borderRadius: 24, padding: '28px 32px 24px', marginBottom: 16, boxShadow: '0 2px 12px rgba(0,0,0,0.07)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 4 }}>
            <div style={{ width: 42, height: 42, borderRadius: 12, background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
              </svg>
            </div>
            <div>
              <div style={{ fontSize: 30, fontWeight: 800, color: BRAND.ink, lineHeight: 1 }}>Escolha seu plano</div>
              <div style={{ fontSize: 20, color: '#888', fontWeight: 500 }}>Selecione e conecte-se</div>
            </div>
          </div>
        </div>

        {/* Plan card - Viagem completa (matching image 1) */}
        <div style={{ opacity: planCardOpacity, transform: `scale(${planCardScale})`, marginBottom: 20 }}>
          <div style={{ background: 'white', borderRadius: 20, padding: '20px 24px', border: `2.5px solid ${BRAND.green}`, boxShadow: `0 4px 20px ${BRAND.green}20`, position: 'relative', overflow: 'visible' }}>
            {/* MAIS ESCOLHIDO badge */}
            <div style={{ position: 'absolute', top: -14, right: 24, background: BRAND.green, borderRadius: 20, paddingLeft: 16, paddingRight: 16, paddingTop: 5, paddingBottom: 5 }}>
              <span style={{ fontSize: 18, fontWeight: 900, color: 'white', letterSpacing: 0.5 }}>MAIS ESCOLHIDO</span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                {/* Radio selected */}
                <div style={{ width: 26, height: 26, borderRadius: '50%', border: `2.5px solid ${BRAND.green}`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <div style={{ width: 14, height: 14, borderRadius: '50%', background: BRAND.green }} />
                </div>
                <div>
                  <div style={{ fontSize: 28, fontWeight: 800, color: BRAND.ink, lineHeight: 1.1 }}>Viagem completa</div>
                  <div style={{ fontSize: 20, fontWeight: 600, color: BRAND.green }}>WiFi até o destino final</div>
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'flex-end', marginBottom: 2 }}>
                  <span style={{ fontSize: 20, color: '#AAA', textDecoration: 'line-through', fontWeight: 600 }}>R$20,97</span>
                  <div style={{ background: '#D32F2F', borderRadius: 6, paddingLeft: 7, paddingRight: 7, paddingTop: 3, paddingBottom: 3 }}>
                    <span style={{ fontSize: 15, fontWeight: 900, color: 'white' }}>-71%</span>
                  </div>
                </div>
                <div style={{ fontSize: 44, fontWeight: 900, color: BRAND.green, letterSpacing: -2, lineHeight: 1 }}>R$6,99</div>
              </div>
            </div>
          </div>
        </div>

        {/* Apps section */}
        <div style={{ opacity: appsOpacity, background: 'white', borderRadius: 20, padding: '22px 24px', marginBottom: 18, boxShadow: '0 2px 10px rgba(0,0,0,0.06)' }}>
          <div style={{ fontSize: 18, fontWeight: 700, color: '#888', textAlign: 'center', marginBottom: 16, letterSpacing: 0.6 }}>
            FUNCIONA COM TODOS OS APPS
          </div>
          <div style={{ display: 'flex', gap: 14, justifyContent: 'center' }}>
            <AppIcon emoji="📸" bg="#FCE4EC" border="#E1306C30" />
            <AppIcon emoji="💬" bg="#E8F5E9" border="#25D36630" />
            <AppIcon emoji="▶️" bg="#FFEBEE" border="#FF000030" />
            <AppIcon emoji="👤" bg="#E3F2FD" border="#1877F230" />
            <AppIcon emoji="🎵" bg="#F5F5F5" border="#01010130" />
            <AppIcon emoji="🎬" bg="#FFEBEE" border="#E5091430" />
          </div>
        </div>

        {/* ACESSAR INTERNET AGORA button */}
        <div style={{ opacity: btnOpacity, marginBottom: 16 }}>
          <div style={{
            transform: `scale(${btnScale * pulseScale})`,
            background: `linear-gradient(135deg, ${BRAND.greenLight} 0%, ${BRAND.greenDark} 100%)`,
            borderRadius: 16,
            paddingTop: 26, paddingBottom: 26,
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14,
            boxShadow: `0 8px 28px ${BRAND.green}55`,
          }}>
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M1.42 9a16 16 0 0 1 21.16 0" />
              <path d="M5 12.55a11 11 0 0 1 14.08 0" />
              <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
              <circle cx="12" cy="20" r="1.5" fill="white" stroke="none" />
            </svg>
            <span style={{ fontSize: 30, fontWeight: 900, color: 'white', letterSpacing: 0.3 }}>ACESSAR INTERNET AGORA</span>
          </div>
        </div>

        {/* Trust badges */}
        <div style={{ opacity: trustOpacity, display: 'flex', justifyContent: 'space-around', marginBottom: 18 }}>
          {[
            { icon: '🔒', label: 'Pagamento seguro' },
            { icon: '⚡', label: 'PIX instantâneo' },
            { icon: '✅', label: 'Liberação automática' },
          ].map(({ icon, label }) => (
            <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ fontSize: 18 }}>{icon}</span>
              <span style={{ fontSize: 19, fontWeight: 600, color: '#777' }}>{label}</span>
            </div>
          ))}
        </div>

        {/* Accordion rows */}
        <div style={{ opacity: accordionOpacity }}>
          {[
            { icon: '▶️', title: 'Como se conectar?', sub: 'Assista o passo a passo', bg: '#FFF9F0', border: '#F59E0B20' },
            { icon: '🎫', title: 'Motorista? Ative seu voucher', sub: 'Acesso gratuito com código', bg: '#F5F0FF', border: '#7C3AED20' },
          ].map(({ icon, title, sub, bg, border }) => (
            <div key={title} style={{ background: 'white', borderRadius: 16, padding: '20px 24px', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', border: `1.5px solid #F0F0F0` }}>
              <div style={{ width: 48, height: 48, borderRadius: 12, background: bg, border: `1.5px solid ${border}`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 24 }}>
                {icon}
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 24, fontWeight: 700, color: BRAND.ink }}>{title}</div>
                <div style={{ fontSize: 18, color: '#888', fontWeight: 500 }}>{sub}</div>
              </div>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#CCC" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="9 18 15 12 9 6" />
              </svg>
            </div>
          ))}
        </div>

      </div>
    </AbsoluteFill>
  );
};
