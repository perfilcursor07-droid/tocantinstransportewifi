import "./index.css";
import { Composition } from "remotion";
import { WifiTocantinsTutorial } from "./Composition";

export const RemotionRoot: React.FC = () => {
  return (
    <>
      <Composition
        id="WifiTocantinsTutorial"
        component={WifiTocantinsTutorial}
        durationInFrames={2325}
        fps={30}
        width={1080}
        height={1920}
      />
    </>
  );
};
