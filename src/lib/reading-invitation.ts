export function shouldInviteReader(signedIn: boolean, seconds: number, contentBottom: number, viewportHeight: number) {
  return !signedIn && seconds >= 30 && contentBottom <= viewportHeight && contentBottom > 0;
}