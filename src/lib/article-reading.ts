// A scroll keeps reading active briefly; delayed ticks never count device sleep.
export const READING_ACTIVITY_MS = 5000;
export class ArticleReading {
  private lastTick: number;
  private activeUntil = 0;
  private elapsed = 0;
  private visible = true;
  constructor(now: number) { this.lastTick = now; }
  scroll(now: number) {
    this.tick(now);
    if (this.visible) this.activeUntil = now + READING_ACTIVITY_MS;
  }
  pause(now: number) {
    this.tick(now);
    this.visible = false;
    this.activeUntil = 0;
  }
  resume(now: number) {
    this.visible = true;
    this.lastTick = now;
    this.activeUntil = 0;
  }
  tick(now: number) {
    const gap = now - this.lastTick;
    if (gap > 2000) this.activeUntil = 0;
    if (this.visible && gap > 0 && gap <= 2000) {
      this.elapsed += Math.max(0, Math.min(now, this.activeUntil) - this.lastTick);
    }
    this.lastTick = now;
    return { seconds: Math.floor(this.elapsed / 1000), active: this.visible && now < this.activeUntil };
  }
}