import { expect, test } from 'bun:test';
import { shouldInviteReader } from './reading-invitation';
test('a signed-out reader finishing an actively read article is invited', () => {
  expect(shouldInviteReader(false, 30, 700, 844)).toBe(true);
});
test('signed-in readers do not receive the registration invitation', () => {
  expect(shouldInviteReader(true, 30, 700, 844)).toBe(false);
});
test('simply jumping to the end does not count as a full reading', () => {
  expect(shouldInviteReader(false, 0, 700, 844)).toBe(false);
});
test('the invitation waits until the article end is visible', () => {
  expect(shouldInviteReader(false, 30, 1200, 844)).toBe(false);
});