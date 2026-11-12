/** Near-silent looping audio that keeps Android from freezing the page while the radio reconnects. */
function quietWav(): string {
  const rate = 8000, n = rate;
  const buf = new ArrayBuffer(44 + n * 2);
  const v = new DataView(buf);
  const str = (o: number, t: string) => { for (let i = 0; i < t.length; i++) v.setUint8(o + i, t.charCodeAt(i)); };
  str(0, "RIFF"); v.setUint32(4, 36 + n * 2, true); str(8, "WAVE"); str(12, "fmt ");
  v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
  v.setUint32(24, rate, true); v.setUint32(28, rate * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true);
  str(36, "data"); v.setUint32(40, n * 2, true);
  // Inaudible ±1 dither: pure zeros may be treated as "not playing".
  for (let i = 0; i < n; i++) v.setInt16(44 + i * 2, i % 2 ? 1 : -1, true);
  let bin = ""; const bytes = new Uint8Array(buf);
  for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
  return "data:audio/wav;base64," + btoa(bin);
}

export function createKeepAlive() {
  let el: HTMLAudioElement | null = null;
  return {
    start() {
      if (typeof window === "undefined") return;
      if (!el) { el = new Audio(quietWav()); el.loop = true; el.volume = 0.01; }
      if (el.paused) void el.play().catch(() => {});
      if ("mediaSession" in navigator) navigator.mediaSession.playbackState = "playing";
    },
    stop() { el?.pause(); },
  };
}
