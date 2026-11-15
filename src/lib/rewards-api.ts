export const REWARDS_API = 'https://gomawebradio.com/wp-json/goma-app/v1';
const TOKEN_KEY = 'gwr-session';
export type RewardRules = { welcome: number; read: number; share_app: number; share_article: number; listen: number; referral: number; read_limit: number; share_article_limit: number; listen_limit: number };
export type RewardUser = { id: number; name: string; avatar?: string; cover?: string; bio?: string; city?: string; email: string; balance: number; code: string; invite_url: string; referrals: number; listen_seconds: number; ledger: { id: string; kind: string; points: number; created: string }[]; claims: { id: string; title: string; status: string; cost: number; created: string }[] };
export type Gift = { id: string; title: string; cost: string; stock: string };
export class RewardsError extends Error { constructor(message: string, public status: number) { super(message); } }
export function sessionToken() {
  try {
    const saved = window.localStorage.getItem(TOKEN_KEY);
    if (saved) return saved;
    const legacy = window.sessionStorage.getItem(TOKEN_KEY);
    if (legacy) { window.localStorage.setItem(TOKEN_KEY, legacy); window.sessionStorage.removeItem(TOKEN_KEY); }
    return legacy;
  } catch { return null; }
}
export function saveToken(token: string | null) {
  try { window.sessionStorage.removeItem(TOKEN_KEY); } catch { /* ignore */ }
  if (token) window.localStorage.setItem(TOKEN_KEY, token); else window.localStorage.removeItem(TOKEN_KEY);
  window.dispatchEvent(new Event('gwr-session-change'));
}
export async function rewardsRequest<T>(path: string, body?: unknown, keepalive = false): Promise<T> {
  const token = typeof window === 'undefined' ? null : sessionToken();
  let response: Response;
  try {
    response = await fetch(`${REWARDS_API}/${path}`, { method: body === undefined ? 'GET' : 'POST', headers: { ...(body === undefined ? {} : { 'Content-Type': 'application/json' }), ...(token ? { Authorization: `Bearer ${token}` } : {}) }, body: body === undefined ? undefined : JSON.stringify(body), credentials: 'omit', cache: 'no-store', signal: keepalive ? undefined : AbortSignal.timeout(15000), keepalive });
  } catch { throw new RewardsError('Impossible de joindre le programme de points. Réessayez lorsque la connexion sera disponible.', 0); }
  const data = await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401 && token && !['login','signup'].includes(path)) saveToken(null);
    throw new RewardsError(data?.message ?? (response.status === 404 ? 'Le programme de points attend l’activation du plugin WordPress.' : 'Opération impossible. Réessayez.'), response.status);
  }
  return data as T;
}
export function refreshRewards() { window.dispatchEvent(new Event('gwr-rewards-change')); }
export async function uploadClubPhoto(file: File): Promise<string> {
  if (file.size > 5 * 1024 * 1024) throw new Error('Photo trop lourde (5 Mo maximum).');
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error('Choisissez une photo JPG, PNG ou WebP.');
  const token = sessionToken();
  if (!token) throw new Error('Connectez-vous pour envoyer une photo.');
  const form = new FormData(); form.append('file', file);
  let response: Response;
  try { response = await fetch(`${REWARDS_API}/social/upload`, { method: 'POST', headers: { Authorization: `Bearer ${token}` }, body: form, credentials: 'omit', signal: AbortSignal.timeout(60000) }); }
  catch { throw new Error('Envoi interrompu. Réessayez lorsque la connexion revient.'); }
  const data = await response.json().catch(() => null);
  if (!response.ok || !data?.url) throw new Error(data?.message ?? 'Photo non envoyée.');
  return data.url;
}
export async function creditShare(kind: 'share_app' | 'share_article', postId?: number) {
  if (!sessionToken()) return;
  try {
    const result = await rewardsRequest<{ awarded: number }>('event', { kind, post_id: postId ?? 0 });
    refreshRewards();
    if (result.awarded > 0) window.dispatchEvent(new CustomEvent('gwr-notice', { detail: `+${result.awarded} points enregistrés` }));
  } catch (error) { window.dispatchEvent(new CustomEvent('gwr-notice', { detail: error instanceof Error ? error.message : 'Points non enregistrés.' })); }
}
