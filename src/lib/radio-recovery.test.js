import { afterEach, expect, jest, test } from "bun:test";
import { RadioRecovery } from "./radio-recovery";
import { APP_STORE_URL } from "./app-store";

afterEach(() => jest.useRealTimers());

function fixture() {
  jest.useFakeTimers();
  const audio = { src: "https://stream.zeno.fm/4d61wprrp7zuv", load: jest.fn(), play: jest.fn(() => Promise.resolve()) };
  const recovery = new RadioRecovery(audio, jest.fn());
  recovery.start(audio.src);
  recovery.playing();
  return { audio, recovery };
}

test("Home sharing uses the exact Google Play app link", () => {
  expect(APP_STORE_URL).toBe("https://play.google.com/store/apps/details?id=com.gomawebradio.app.twa&pcampaignid=web_share");
});

test("automatic radio interruptions retry indefinitely even when the server remains unavailable", async () => {
  const { audio, recovery } = fixture();
  audio.play.mockImplementation(() => Promise.reject(new Error("unavailable")));
  recovery.interrupted();
  for (let i = 0; i < 12; i++) {
    jest.advanceTimersByTime(30000);
    await Promise.resolve();
  }
  expect(audio.play.mock.calls.length).toBeGreaterThanOrEqual(13);
  expect(recovery.isActive()).toBe(true);
  expect(audio.src).toContain("4d61wprrp7zuv?t=");
  recovery.cancel();
});

test("explicit pause or podcast switch cancels pending retries", () => {
  const { audio, recovery } = fixture();
  recovery.interrupted();
  recovery.cancel();
  jest.advanceTimersByTime(120000);
  recovery.online();
  expect(audio.play).toHaveBeenCalledTimes(1);
  expect(recovery.isActive()).toBe(false);
});

test("a stalled stream retries without needing another Play click", () => {
  const { audio, recovery } = fixture();
  recovery.interrupted(true);
  jest.advanceTimersByTime(15000);
  expect(audio.play).toHaveBeenCalledTimes(2);
  recovery.cancel();
});

test("connectivity restoration immediately resumes a pending retry", () => {
  const { audio, recovery } = fixture();
  recovery.interrupted();
  recovery.online();
  expect(audio.play).toHaveBeenCalledTimes(2);
  recovery.cancel();
});
test("a cut keeps the phone awake and retries within seconds", () => {
  jest.useFakeTimers();
  const audio = { src: "https://s.example/radio", load: jest.fn(), play: jest.fn(() => Promise.resolve()) };
  const keep = { start: jest.fn(), stop: jest.fn() };
  const recovery = new RadioRecovery(audio, jest.fn(), keep);
  recovery.start(audio.src);
  recovery.playing();
  recovery.interrupted();
  expect(keep.start).toHaveBeenCalled();
  jest.advanceTimersByTime(1000);
  expect(audio.load).toHaveBeenCalledTimes(1);
  recovery.playing();
  expect(keep.stop).toHaveBeenCalled();
  recovery.cancel();
  jest.useRealTimers();
});
