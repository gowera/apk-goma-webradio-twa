import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { RewardsContext } from '@/context/rewards-context';
import { rewardsRequest, saveToken, sessionToken, refreshRewards, type RewardUser, type RewardRules } from '@/lib/rewards-api';
import { usePlayer } from '@/context/player';
import { Button } from '@/components/ui/button';

export function RewardsProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<RewardUser | null>(null);
  const [rules, setRules] = useState<RewardRules | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [seconds, setSeconds] = useState(0);
  const [notice, setNotice] = useState('');
  const { playing, loading: buffering, track } = usePlayer();
  const playback = useRef(false);
  const tickBusy = useRef(false);
  const listen = useRef<string | null>(null);
  const elapsed = useRef(0);
  const generation = useRef(0);
  playback.current = playing && !buffering && track?.kind === 'radio';
  elapsed.current = seconds;
  const refresh = useCallback(async () => {
    const token = sessionToken();
    setLoading(true);
    try {
      if (token) {
        const result = await rewardsRequest<RewardUser>('me');
        if (sessionToken() === token) { setUser(result); setError(''); }
      } else { setUser(null); setError(''); }
    } catch (e) { setError(e instanceof Error ? e.message : 'Compte indisponible.'); }
    finally { setLoading(false); }
  }, []);
  const logout = useCallback(async () => {
    await rewardsRequest('logout', {});
    saveToken(null); setUser(null);
  }, []);
  useEffect(() => {
    void refresh();
    void rewardsRequest<{ rules: RewardRules }>('config').then(c => setRules(c.rules)).catch(() => {});
    const changed = () => { void refresh(); };
    const show = (event: Event) => setNotice((event as CustomEvent<string>).detail);
    window.addEventListener('gwr-session-change', changed);
    window.addEventListener('gwr-rewards-change', changed);
    window.addEventListener('gwr-notice', show);
    return () => { window.removeEventListener('gwr-session-change', changed); window.removeEventListener('gwr-rewards-change', changed); window.removeEventListener('gwr-notice', show); };
  }, [refresh]);
  useEffect(() => {
    if (!notice) return;
    const timer = window.setTimeout(() => setNotice(''), 8000);
    return () => window.clearTimeout(timer);
  }, [notice]);
  useEffect(() => {
    const stop = (warn: boolean, closing = false) => {
      generation.current += 1;
      const token = listen.current;
      listen.current = null;
      if (warn && token && elapsed.current % 3600 > 0) setNotice('Avant 1 heure complète, cette écoute ne rapporte aucun point. La progression de l’heure en cours sera perdue.');
      setSeconds(0);
      if (token && sessionToken()) void rewardsRequest('listen', { action: 'stop', token }, closing).catch(() => {});
    };
    const interrupted = () => stop(true);
    const closing = () => stop(false, true);
    const beforeClose = (event: BeforeUnloadEvent) => {
      if (listen.current && elapsed.current % 3600 > 0) { event.preventDefault(); event.returnValue = ''; }
    };
    const tick = async () => {
      if (!user || !sessionToken() || !playback.current || tickBusy.current) return;
      tickBusy.current = true;
      const epoch = generation.current;
      try {
        if (!listen.current) {
          const result = await rewardsRequest<{ token: string; seconds: number }>('listen', { action: 'start' });
          if (epoch !== generation.current) { void rewardsRequest('listen', { action: 'stop', token: result.token }).catch(() => {}); return; }
          listen.current = result.token; setSeconds(result.seconds);
        } else {
          const result = await rewardsRequest<{ seconds: number; awarded: number; token: string }>('listen', { action: 'tick', token: listen.current });
          if (epoch !== generation.current) return;
          listen.current = result.token;
          setSeconds(result.seconds);
          if (result.awarded > 0) { setNotice(`+${result.awarded} points pour votre heure d’écoute`); refreshRewards(); }
        }
      } catch (e) {
        if (e instanceof Error && 'status' in e && (e as { status: number }).status === 409) listen.current = null;
        if (epoch === generation.current) setError(e instanceof Error ? e.message : 'Suivi de l’écoute interrompu.');
      } finally { tickBusy.current = false; }
    };
    void tick();
    const timer = window.setInterval(() => void tick(), 30000);
    window.addEventListener('gwr-listen-stop', interrupted);
    window.addEventListener('pagehide', closing);
    window.addEventListener('beforeunload', beforeClose);
    return () => { window.clearInterval(timer); stop(false); window.removeEventListener('gwr-listen-stop', interrupted); window.removeEventListener('pagehide', closing); window.removeEventListener('beforeunload', beforeClose); };
  }, [user?.id]);
  return <RewardsContext.Provider value={{ user, rules, loading, error, refresh, logout, seconds }}>
    {children}
    {notice && <div role="status" className="fixed bottom-44 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 items-center gap-3 rounded-lg border border-line bg-panel p-4 text-sm text-ink shadow-lift"><span className="flex-1">{notice}</span><Button variant="ghost" size="icon" aria-label="Fermer le message" onClick={() => setNotice('')}><span className="material-symbols-outlined">close</span></Button></div>}
  </RewardsContext.Provider>;
}
