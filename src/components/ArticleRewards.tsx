import { useEffect, useState } from 'react';
import { useRewards } from '@/context/rewards-context';
import { rewardsRequest, refreshRewards } from '@/lib/rewards-api';
import { ArticleReading } from '@/lib/article-reading';
import { shouldInviteReader } from '@/lib/reading-invitation';
import { Link } from '@tanstack/react-router';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';

export function ArticleRewards({ postId }: { postId: number }) {
  const { user, rules, loading } = useRewards();
  const [invitation, setInvitation] = useState(false);
  const [progress, setProgress] = useState({ seconds: 0, active: false });
  const [credited, setCredited] = useState(false);
  const [error, setError] = useState('');
  useEffect(() => {
    setProgress({ seconds: 0, active: false }); setCredited(false); setError('');
    const reading = new ArticleReading(performance.now());
    let disposed = false;
    let token: string | null = null;
    let sent = false;
    let retryAt = 0;
    let invited = false;
    const start = () => {
      if (!user || disposed) return;
      sent = true;
      void rewardsRequest<{ token: string }>('read', { action: 'start', post_id: postId }).then(r => { token = r.token; setError(''); }).catch(e => { if (!disposed) setError(e instanceof Error ? e.message : 'Points indisponibles.'); retryAt = performance.now() + 15000; }).finally(() => { sent = false; });
    };
    start();
    const scroll = () => {
      const content = document.querySelector('[data-reading-content]');
      const rect = content?.getBoundingClientRect();
      if (!rect || rect.bottom <= 0 || rect.top >= window.innerHeight || document.visibilityState !== 'visible' || !document.hasFocus()) return;
      reading.scroll(performance.now());
      setProgress(reading.tick(performance.now()));
    };
    const pause = () => { reading.pause(performance.now()); setProgress(reading.tick(performance.now())); };
    const resume = () => { if (document.visibilityState === 'visible') { reading.resume(performance.now()); setProgress(reading.tick(performance.now())); } };
    const visibility = () => { if (document.visibilityState === 'visible') resume(); else pause(); };
    if (document.visibilityState !== 'visible') pause();
    const timer = window.setInterval(() => {
      if (disposed) return;
      const now = performance.now();
      const state = reading.tick(now);
      setProgress(state);
      const bottom = document.querySelector('[data-reading-content]')?.getBoundingClientRect().bottom;
      if (!invited && !loading && document.visibilityState === 'visible' && document.hasFocus() && bottom !== undefined && shouldInviteReader(Boolean(user), state.seconds, bottom, window.innerHeight)) {
        invited = true; reading.pause(now); setInvitation(true);
      }
      if (!user || sent || now < retryAt) return;
      if (!token) { start(); return; }
      if (state.seconds < 30) return;
      sent = true;
      void rewardsRequest<{ awarded: number }>('read', { action: 'finish', post_id: postId, token }).then(r => {
        if (disposed) return;
        setCredited(true); setError('');
        if (r.awarded > 0) { refreshRewards(); window.dispatchEvent(new CustomEvent('gwr-notice', { detail: `+${r.awarded} points pour cet article` })); }
      }).catch(e => { sent = false; retryAt = performance.now() + 15000; if (!disposed) setError(e instanceof Error ? e.message : 'Points non enregistrés.'); });
    }, 1000);
    window.addEventListener('scroll', scroll, { passive: true });
    window.addEventListener('blur', pause);
    window.addEventListener('focus', resume);
    window.addEventListener('pagehide', pause);
    window.addEventListener('pageshow', resume);
    document.addEventListener('visibilitychange', visibility);
    return () => {
      disposed = true; window.clearInterval(timer);
      window.removeEventListener('scroll', scroll); window.removeEventListener('blur', pause); window.removeEventListener('focus', resume);
      window.removeEventListener('pagehide', pause); window.removeEventListener('pageshow', resume); document.removeEventListener('visibilitychange', visibility);
    };
  }, [postId, user?.id, loading]);
  return <><div className="sticky top-16 z-20 my-4 border-y border-line bg-paper/95 py-3 backdrop-blur" aria-label="Suivi de lecture">
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span className="flex items-center gap-2 font-semibold text-ink"><span className={`material-symbols-outlined ${progress.active ? 'text-brand' : 'text-inkmute'}`}>{progress.active ? 'timer' : 'pause_circle'}</span>Lecture {progress.active ? 'en cours' : 'en pause'}</span>
      <span className="font-mono font-bold tabular-nums text-ink">{String(Math.floor(progress.seconds / 60)).padStart(2, '0')}:{String(progress.seconds % 60).padStart(2, '0')}</span>
    </div>
    {user && <><progress max={30} value={Math.min(progress.seconds, 30)} className="mt-2 block h-1 w-full accent-brand" aria-label="Lecture récompensée" /><p className="mt-1 text-xs text-inkmute">{credited ? 'Lecture validée' : `${Math.min(progress.seconds, 30)} / 30 s · ${rules?.read ?? '—'} points`}</p></>}
    {error && <p role="alert" className="mt-2 text-xs text-blood">{error}</p>}
  </div><Dialog open={invitation && !user} onOpenChange={setInvitation}><DialogContent className="w-[calc(100%-2rem)] max-w-md rounded-lg border-line bg-panel text-ink">
    <span className="material-symbols-outlined text-4xl text-brand">redeem</span>
    <DialogTitle>Ne manquez pas vos points !</DialogTitle>
    <DialogDescription className="text-inkmute">Vous venez de lire cet article sans être connecté. Inscrivez-vous au programme de récompenses GOMA WEBRADIO pour gagner des points lors de vos prochaines lectures et avancer vers vos cadeaux.</DialogDescription>
    <Button asChild className="bg-brand text-primary-foreground"><Link to="/inscription">Rejoindre le programme</Link></Button>
    <Button asChild variant="outline"><Link to="/connexion">J’ai déjà un compte</Link></Button>
    <Button variant="ghost" onClick={() => setInvitation(false)}>Plus tard</Button>
  </DialogContent></Dialog></>;
}
