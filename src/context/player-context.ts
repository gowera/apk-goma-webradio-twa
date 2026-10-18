import { createContext } from "react";

export type Track = {
  id: string;
  kind: "radio" | "podcast";
  title: string;
  subtitle: string;
  artwork: string | null;
  src: string;
};

export type Quality = "Auto" | "Normale" | "Haute";

export type PlayerState = {
  track: Track | null;
  playing: boolean;
  loading: boolean;
  volume: number;
  muted: boolean;
  progress: number;
  duration: number;
  quality: Quality;
  rate: number;
  queue: Track[];
  hasNext: boolean;
  hasPrev: boolean;
  setQueue: (tracks: Track[]) => void;
  playNext: () => void;
  playPrev: () => void;
  setRate: (r: number) => void;
  setQuality: (q: Quality) => void;
  play: (track: Track) => void;
  toggle: (track?: Track) => void;
  stop: () => void;
  seek: (seconds: number) => void;
  skip: (delta: number) => void;
  setVolume: (v: number) => void;
  toggleMute: () => void;
  setFavoriteHandler: (fn: ((track: Track) => void) | null) => void;
};

export const PlayerContext = createContext<PlayerState | null>(null);

