import React from 'react';
import { AbsoluteFill, Audio, Sequence, staticFile } from 'remotion';
import { TransitionSeries, linearTiming } from '@remotion/transitions';
import { fade } from '@remotion/transitions/fade';
import { IntroScene } from './scenes/IntroScene';
import { Step1Scene } from './scenes/Step1Scene';
import { Step2Scene } from './scenes/Step2Scene';
import { PlansScene } from './scenes/PlansScene';
import { Step4Scene } from './scenes/Step4Scene';
import { PhoneInputScene } from './scenes/PhoneInputScene';
import { DiscountChoiceScene } from './scenes/DiscountChoiceScene';
import { VideoAdScene } from './scenes/VideoAdScene';
import { PixFinalScene } from './scenes/PixFinalScene';
import { OutroScene } from './scenes/OutroScene';

// Scene durations calculated from actual audio lengths (edge-tts male voice) + 1.5s padding
// Audio durations: 9.3 9.3 12.9 13.4 5.6 9.1 9.2 7.8 14.4 4.3 seconds
// Scene frames (rounded up to multiple of 15):
//   330 330 435 450 225 330 330 285 480 180
// Transitions: 9 × 15f = 135f
// Total net: 3375 - 135 = 3240f = 108.0s
//
// Scene start frames in global timeline:
//   Intro:0  WiFi:315  Redirect:630  Plans:1050  Click:1485
//   Phone:1695  Discount:2010  VideoAd:2325  PIX:2595  Outro:3060

const TRANSITION = linearTiming({ durationInFrames: 15 });

// Narration: starts at scene start, duration covers scene (audio ends before scene does)
const NARRATIONS = [
  { file: 'audio/01-intro.mp3',    from: 0,    duration: 330 },
  { file: 'audio/02-wifi.mp3',     from: 315,  duration: 330 },
  { file: 'audio/03-redirect.mp3', from: 630,  duration: 435 },
  { file: 'audio/04-plans.mp3',    from: 1050, duration: 450 },
  { file: 'audio/05-click.mp3',    from: 1485, duration: 225 },
  { file: 'audio/06-phone.mp3',    from: 1695, duration: 330 },
  { file: 'audio/07-discount.mp3', from: 2010, duration: 330 },
  { file: 'audio/08-video.mp3',    from: 2325, duration: 285 },
  { file: 'audio/09-pix.mp3',      from: 2595, duration: 480 },
  { file: 'audio/10-outro.mp3',    from: 3060, duration: 180 },
];

export const WifiTocantinsTutorial: React.FC = () => {
  return (
    <AbsoluteFill>
      {/* ── Background music (low volume, full duration) ── */}
      <Sequence from={0} durationInFrames={3240}>
        <Audio src={staticFile('audio/background.wav')} volume={0.08} />
      </Sequence>

      {/* ── Narration tracks (volume 1, synced to each scene) ── */}
      {NARRATIONS.map(({ file, from, duration }) => (
        <Sequence key={file} from={from} durationInFrames={duration}>
          <Audio src={staticFile(file)} volume={1} />
        </Sequence>
      ))}

      {/* ── Video scenes ── */}
      <TransitionSeries>
        <TransitionSeries.Sequence durationInFrames={330}>
          <IntroScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={330}>
          <Step1Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={435}>
          <Step2Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={450}>
          <PlansScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={225}>
          <Step4Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={330}>
          <PhoneInputScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={330}>
          <DiscountChoiceScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={285}>
          <VideoAdScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={480}>
          <PixFinalScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={180}>
          <OutroScene />
        </TransitionSeries.Sequence>
      </TransitionSeries>
    </AbsoluteFill>
  );
};

export const MyComposition = WifiTocantinsTutorial;
