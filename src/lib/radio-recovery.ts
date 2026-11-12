type RadioAudio = Pick<HTMLAudioElement, "src" | "load" | "play">;

/** Keeps listener intent separate from the browser's temporary playback state. */
export class RadioRecovery {
  private active = false;
  private timer: ReturnType<typeof setTimeout> | undefined;
  private attempts = 0;
  private generation = 0;
  private stream = "";
  private recovering = false;

  constructor(
    private audio: RadioAudio,
    private onRecovering: () => void,
    private keepAlive?: { start(): void; stop(): void },
  ) {}

  isActive() { return this.active; }

  start(stream: string) {
    this.cancel();
    this.active = true;
    this.stream = stream;
    this.onRecovering();
    this.attempt(false);
  }

  cancel() {
    this.active = false;
    this.recovering = false;
    this.generation++;
    this.clearTimer();
    this.attempts = 0;
    this.keepAlive?.stop();
  }

  playing() {
    if (!this.active) return;
    this.clearTimer();
    this.recovering = false;
    this.attempts = 0;
    this.keepAlive?.stop();
  }

  interrupted(stalled = false) {
    if (!this.active) return;
    this.recovering = true;
    // Keep the page audible so a sleeping phone does not freeze retry timers.
    this.keepAlive?.start();
    this.onRecovering();
    if (this.timer !== undefined && stalled) return;
    this.clearTimer();
    const delay = stalled ? 10000 : Math.min(1000 * 2 ** Math.min(this.attempts, 3), 8000);
    this.timer = setTimeout(() => {
      this.timer = undefined;
      this.attempt(true);
    }, delay);
  }

  online() {
    if (!this.active || !this.recovering) return;
    this.clearTimer();
    this.attempt(true);
  }

  private attempt(reload: boolean) {
    if (!this.active) return;
    const generation = ++this.generation;
    this.attempts++;
    if (reload) {
      const url = new URL(this.stream);
      url.searchParams.set("t", String(Date.now()));
      this.audio.src = url.href;
      this.audio.load();
    }
    // Also recover when play() hangs without emitting an error.
    this.interrupted(true);
    void this.audio.play().catch(() => {
      if (this.active && generation === this.generation) this.interrupted();
    });
  }

  private clearTimer() {
    if (this.timer !== undefined) clearTimeout(this.timer);
    this.timer = undefined;
  }
}