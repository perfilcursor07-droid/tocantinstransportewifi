import { loadFont } from '@remotion/google-fonts/Inter';

export const { fontFamily } = loadFont('normal', {
  weights: ['400', '600', '700', '800', '900'],
  subsets: ['latin'],
});
