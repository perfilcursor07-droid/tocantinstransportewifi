import React from 'react';
import { AbsoluteFill, Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BRAND } from '../colors';
import { fontFamily } from '../font';

const PhoneMockup: React.FC<{ frame: number }> = ({ frame }) => {
  const screenOpacity = interpolate(frame, [20, 40], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const contentY = interpolate(frame, [30, 55], [20, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  return (
    <div style={{
      width: 320, height: 580,
      borderRadius: 44,
      background: '#1a1a1a',
      padding: 12,
      boxShadow: '0 30px 80px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.1)',
    }}>
      <div style={{ width: '100%', height: '100%', borderRadius: 36, background: 'white', overflow: 'hidden', position: 'relative' }}>
        {/* Status bar */}
        <div style={{ height: 36, background: BRAND.greenDeep, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ width: 80, height: 18, background: 'rgba(0,0,0,0.4)', borderRadius: 9 }} />
        </div>

        {/* Portal Content */}
        <div style={{ opacity: screenOpacity, transform: `translateY(${contentY}px)`, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
          {/* Header */}
          <div style={{ textAlign: 'center', paddingBottom: 10, borderBottom: '1px solid #eee' }}>
            <div style={{ fontSize: 16, fontWeight: 900, color: BRAND.ink, fontFamily }}>WiFi Tocantins</div>
            <div style={{ fontSize: 11, color: BRAND.muted, fontFamily }}>Conecte-se à Internet</div>
          </div>

          {/* Plan card 1 */}
          <div style={{ background: '#f8f8f8', borderRadius: 12, padding: 12, border: '1.5px solid #eee' }}>
            <div style={{ fontSize: 12, fontWeight: 700, color: BRAND.ink, fontFamily }}>1 hora de acesso</div>
            <div style={{ fontSize: 18, fontWeight: 900, color: BRAND.ink, fontFamily }}>R$5,99</div>
          </div>

          {/* Plan card 2 - selected */}
          <div style={{ background: '#E8F5E9', borderRadius: 12, padding: 12, border: `2px solid ${BRAND.green}`, position: 'relative' }}>
            <div style={{ position: 'absolute', top: -8, right: 10, background: BRAND.green, borderRadius: 8, paddingLeft: 8, paddingRight: 8, paddingTop: 2, paddingBottom: 2 }}>
              <span style={{ fontSize: 8, fontWeight: 900, color: 'white', fontFamily }}>MAIS ESCOLHIDO</span>
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: BRAND.ink, fontFamily }}>Viagem completa</div>
            <div style={{ fontSize: 18, fontWeight: 900, color: BRAND.greenDark, fontFamily }}>R$6,99</div>
          </div>

          {/* CTA Button */}
          <div style={{ background: `linear-gradient(135deg, ${BRAND.greenLight}, ${BRAND.greenDark})`, borderRadius: 12, paddingTop: 12, paddingBottom: 12, textAlign: 'center' }}>
            <div style={{ fontSize: 11, fontWeight: 900, color: 'white', fontFamily, letterSpacing: 0.3 }}>ACESSAR INTERNET AGORA</div>
          </div>
        </div>
      </div>
    </div>
  );
};

export const Step3Scene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const badgeScale = spring({ fps, frame, config: { damping: 12, stiffness: 200, mass: 0.5 }, durationInFrames: 30 });
  const phoneScale = spring({ fps, frame: Math.max(0, frame - 8), config: { damping: 14, stiffness: 130, mass: 0.9 }, durationInFrames: 45 });

  const title1Opacity = interpolate(frame, [25, 48], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title1Y = interpolate(frame, [25, 48], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const title2Opacity = interpolate(frame, [38, 60], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const title2Y = interpolate(frame, [38, 60], [40, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.out(Easing.cubic) });

  const descOpacity = interpolate(frame, [65, 85], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ background: 'white', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily, paddingLeft: 60, paddingRight: 60 }}>

      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />

      {/* Step Badge */}
      <div style={{ transform: `scale(${badgeScale})`, marginBottom: 44 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, background: BRAND.surface, borderRadius: 60, paddingLeft: 28, paddingRight: 36, paddingTop: 14, paddingBottom: 14 }}>
          <div style={{ width: 52, height: 52, borderRadius: '50%', background: BRAND.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ fontSize: 28, fontWeight: 900, color: 'white' }}>3</span>
          </div>
          <span style={{ fontSize: 28, fontWeight: 700, color: BRAND.muted, letterSpacing: 0.5 }}>PASSO 3 DE 4</span>
        </div>
      </div>

      {/* Phone Mockup */}
      <div style={{ transform: `scale(${phoneScale})`, marginBottom: 44 }}>
        <PhoneMockup frame={frame} />
      </div>

      {/* Title */}
      <div style={{ textAlign: 'center', marginBottom: 20 }}>
        <div style={{ opacity: title1Opacity, transform: `translateY(${title1Y}px)`, fontSize: 62, fontWeight: 900, color: BRAND.ink, lineHeight: 1.1, letterSpacing: -1.5 }}>
          A TELA DE LOGIN
        </div>
        <div style={{ opacity: title2Opacity, transform: `translateY(${title2Y}px)`, fontSize: 62, fontWeight: 900, color: BRAND.green, lineHeight: 1.1, letterSpacing: -1.5 }}>
          APARECE SOZINHA
        </div>
      </div>

      {/* Description */}
      <div style={{ opacity: descOpacity, textAlign: 'center' }}>
        <div style={{ fontSize: 30, fontWeight: 500, color: BRAND.muted, lineHeight: 1.5 }}>
          Pode demorar alguns segundos.
        </div>
        <div style={{ fontSize: 30, fontWeight: 500, color: BRAND.muted }}>
          Se não aparecer, abra o navegador.
        </div>
      </div>

      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 8, background: `linear-gradient(90deg, ${BRAND.greenDeep}, ${BRAND.greenLight})` }} />
    </AbsoluteFill>
  );
};
