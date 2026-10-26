import { expect, test } from 'bun:test';
import { ArticleReading } from './article-reading';

test('an open article without scrolling earns no reading time', () => {
  const reading = new ArticleReading(0);
  for (let t = 1000; t <= 30000; t += 1000) reading.tick(t);
  expect(reading.tick(31000).seconds).toBe(0);
});
test('scrolling activates time and inactivity pauses it', () => {
  const reading = new ArticleReading(0);
  reading.scroll(0);
  for (let t = 1000; t <= 10000; t += 1000) reading.tick(t);
  expect(reading.tick(11000)).toEqual({ seconds: 5, active: false });
  reading.scroll(11000);
  expect(reading.tick(12000)).toEqual({ seconds: 6, active: true });
});
test('phone sleep or an inactive page pauses reading until a new scroll', () => {
  const reading = new ArticleReading(0);
  reading.scroll(0);
  reading.tick(1000);
  reading.pause(1000);
  reading.tick(60000);
  reading.resume(60000);
  expect(reading.tick(61000)).toEqual({ seconds: 1, active: false });
  reading.scroll(61000);
  expect(reading.tick(62000).seconds).toBe(2);
});
test('a suspended timer never counts sleep even if visibility is unchanged', () => {
  const reading = new ArticleReading(0);
  reading.scroll(0);
  reading.tick(1000);
  expect(reading.tick(60000)).toEqual({ seconds: 1, active: false });
});
test('30 seconds of scrolling records 30 seconds of active reading', () => {
  const reading = new ArticleReading(0);
  for (let t = 0; t < 30000; t += 1000) {
    reading.scroll(t);
    reading.tick(t + 1000);
  }
  expect(reading.tick(30000).seconds).toBe(30);
});