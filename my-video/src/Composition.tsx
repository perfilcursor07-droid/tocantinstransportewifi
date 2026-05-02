import React from 'react';
import { AbsoluteFill } from 'remotion';
import { TransitionSeries, linearTiming } from '@remotion/transitions';
import { fade } from '@remotion/transitions/fade';
import { IntroScene } from './scenes/IntroScene';
import { Step1Scene } from './scenes/Step1Scene';
import { Step2Scene } from './scenes/Step2Scene';
import { Step3Scene } from './scenes/Step3Scene';
import { PlansScene } from './scenes/PlansScene';
import { Step4Scene } from './scenes/Step4Scene';
import { ClickScene } from './scenes/ClickScene';
import { DiscountChoiceScene } from './scenes/DiscountChoiceScene';
import { VideoAdScene } from './scenes/VideoAdScene';
import { PixFinalScene } from './scenes/PixFinalScene';
import { AppsScene } from './scenes/AppsScene';
import { OutroScene } from './scenes/OutroScene';

// 12 scenes + 11 transitions of 15f each
// Scenes: 210+270+270+210+330+210+120+210+180+270+150+60 = 2490
// Net: 2490 - 11*15 = 2490 - 165 = 2325 frames = 77.5 seconds @ 30fps

const TRANSITION = linearTiming({ durationInFrames: 15 });

export const WifiTocantinsTutorial: React.FC = () => {
  return (
    <AbsoluteFill>
      <TransitionSeries>
        <TransitionSeries.Sequence durationInFrames={210}>
          <IntroScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={270}>
          <Step1Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={270}>
          <Step2Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={210}>
          <Step3Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={330}>
          <PlansScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={210}>
          <Step4Scene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={120}>
          <ClickScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={210}>
          <DiscountChoiceScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={180}>
          <VideoAdScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={270}>
          <PixFinalScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={150}>
          <AppsScene />
        </TransitionSeries.Sequence>

        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={60}>
          <OutroScene />
        </TransitionSeries.Sequence>
      </TransitionSeries>
    </AbsoluteFill>
  );
};

export const MyComposition = WifiTocantinsTutorial;
