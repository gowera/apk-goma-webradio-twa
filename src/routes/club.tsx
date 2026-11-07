import { createFileRoute, Link } from '@tanstack/react-router';
import { useCallback, useEffect, useState } from 'react';
import { Screen } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { useRewards } from '@/context/rewards-context';
import { clubFeed, clubLike, clubPost, clubUnpost, friendLists, requestFriend, timeAgo, type Club, type ClubPost, type Person } from '@/lib/social-api';

export const Route = createFileRoute('/club')({
  head: () => ({ meta: [
    { title: 'Le club des auditeurs — GOMA WEBRADIO' },
    { name: 'description', content: 'Le mur, les membres et les meilleurs auditeurs du club GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Le club des auditeurs — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Partagez, discutez et retrouvez les auditeurs de GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: ClubPage,
});

const Avatar = ({ name, size = 'h-11 w-11 text-lg' }: { name: string; size?: string }) => <span aria-hidden="true" className={`flex shrink-0 items-center justify-center rounded-full bg-panel2 font-display font-extrabold text-brand ${size}`}>{name.trim().slice(0, 1).toUpperCase()}</span>;

function ClubPage() {
  const { user, loading } = useRewards();
  const [club, setClub] = useState<Club | null>(null);
  const [unread, setUnread] = useState(0);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [more, setMore] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
  const [pending, setPending] = useState<number[]>([]);
  const load = useCallback(async () => {
    try { const [c, f] = await Promise.all([clubFeed(), friendLists()]); setClub(prev => prev && prev.posts.length > c.posts.length ? { ...c, posts: [...c.posts, ...prev.posts.filter(p => !c.posts.some(x => x.id === p.id) && p.id < (c.posts.at(-1)?.id ?? 0))] } : c); setUnread(f.friends.reduce((n, p) => n + (p.unread ?? 0), 0) + f.incoming.length); setError(''); }
    catch (e) { setError(e instanceof Error ? e.message : 'Club indisponible.'); }
  }, []);
  useEffect(() => { if (!user) return; void load(); const t = window.setInterval(() => { if (document.visibilityState === 'visible') void load(); }, 20000); return () => window.clearInterval(t); }, [user?.id, load]);

  if (!user) return <Screen title="Le club" back><div className="py-10 text-center"><span className="material-symbols-outlined text-5xl text-brand">diversity_3</span><h2 className="mt-3 font-display text-2xl font-extrabold text-ink">Le club des auditeurs</h2><p className="mt-2 text-sm text-inkmute">{loading ? 'Chargement…' : 'Rejoignez la communauté GOMA WEBRADIO : mur, amis, messages et classement.'}</p>{!loading && <div className="mt-5 flex justify-center gap-3"><Button asChild className="bg-brand text-primary-foreground"><Link to="/inscription">Rejoindre le club</Link></Button><Button asChild variant="outline"><Link to="/connexion">Se connecter</Link></Button></div>}</div></Screen>;

  const publish = async (e: React.FormEvent) => { e.preventDefault(); const body = text.trim(); if (!body || busy) return; setBusy(true); try { const p = await clubPost(body); setClub(c => c && { ...c, posts: [p, ...c.posts] }); setText(''); setError(''); } catch (err) { setError(err instanceof Error ? err.message : 'Publication impossible.'); } finally { setBusy(false); } };
  const patch = (id: number, fn: (p: ClubPost) => ClubPost | null) => setClub(c => c && { ...c, posts: c.posts.flatMap(p => p.id === id ? (fn(p) ?? []) : [p]) });
  const like = async (p: ClubPost) => { if (pending.includes(p.id)) return; setPending(ids => [...ids, p.id]); try { const r = await clubLike(p.id); patch(p.id, x => ({ ...x, ...r })); } catch (e) { setError(e instanceof Error ? e.message : 'Action impossible.'); } finally { setPending(ids => ids.filter(id => id !== p.id)); } };
  const remove = async (p: ClubPost) => { if (!window.confirm('Supprimer cette publication ?')) return; try { await clubUnpost(p.id); patch(p.id, () => null); } catch { setError('Suppression impossible.'); } };
  const loadMore = async () => { const last = club?.posts.at(-1); if (!last || moreBusy) return; setMoreBusy(true); try { const c = await clubFeed(last.id); if (c.posts.length < 20) setMore(false); setClub(prev => prev && { ...prev, posts: [...prev.posts, ...c.posts.filter(p => !prev.posts.some(x => x.id === p.id))] }); setError(''); } catch (e) { setError(e instanceof Error ? e.message : 'Club indisponible.'); } finally { setMoreBusy(false); } };
  const add = async (p: Person) => { try { const r = await requestFriend(p.id); setClub(c => c && { ...c, newest: c.newest.map(x => x.id === p.id ? { ...x, relation: r.relation } : x) }); } catch (e) { setError(e instanceof Error ? e.message : 'Demande impossible.'); } };

  return <Screen title="Le club" back>
    <div className="space-y-7 pt-0">
      <section className="-mx-4 relative overflow-hidden bg-brand-deep px-4 py-6 text-primary-foreground">
        <img src="/play-bg.webp" alt="" className="absolute inset-0 h-full w-full object-cover opacity-40" />
        <div className="relative"><div className="flex items-center gap-3"><img src="/logo.png" alt="" className="h-12 w-12 object-contain" /><div><h1 className="font-display text-2xl font-extrabold">Le club des auditeurs</h1><p className="text-xs">Fasi ya ndule na infos za palais</p></div></div>
          <div className="mt-5 flex items-center justify-between gap-3"><p className="text-sm"><b className="font-display text-xl">{club?.members ?? '—'}</b> membres</p>
            <Button asChild variant="secondary" className="relative"><Link to="/amis"><span className="material-symbols-outlined">forum</span>Amis et messages{unread > 0 && <span className="absolute -right-2 -top-2 rounded-full bg-blood px-2 py-0.5 text-xs font-bold text-primary-foreground">{unread}</span>}</Link></Button></div></div>
      </section>

      {!!club?.newest.length && <section><h2 className="font-display text-lg font-extrabold text-ink">Nouveaux membres</h2>
        <ul className="-mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-2">{club.newest.map(p => <li key={p.id} className="flex w-28 shrink-0 flex-col items-center rounded-xl border border-line bg-panel p-3 text-center"><Avatar name={p.name} size="h-14 w-14 text-2xl" /><p className="mt-2 w-full truncate text-sm font-semibold text-ink">{p.name}</p>
          {p.relation === 'none' ? <Button size="sm" className="mt-2 h-8 w-full bg-brand text-primary-foreground" onClick={() => void add(p)}>Ajouter</Button> : p.relation === 'friend' ? <Button size="sm" variant="outline" className="mt-2 h-8 w-full" asChild><Link to="/amis/$id" params={{ id: String(p.id) }}>Message</Link></Button> : <span className="mt-3 text-xs text-inkmute">{p.relation === 'outgoing' ? 'Demande envoyée' : 'Vous invite'}</span>}</li>)}</ul></section>}

      <section>
        <h2 className="font-display text-lg font-extrabold text-ink">Le mur du club</h2>
        <form onSubmit={publish} className="mt-3 flex gap-3 rounded-xl border border-line bg-panel p-3"><Avatar name={user.name} /><div className="flex-1"><textarea value={text} onChange={e => setText(e.target.value)} maxLength={500} rows={2} placeholder="Quoi de neuf sur les ondes ?" aria-label="Votre publication" className="w-full resize-none bg-transparent text-sm text-ink outline-none" /><div className="flex items-center justify-between"><span className="text-xs text-inkmute">{text.length}/500</span><Button type="submit" size="sm" disabled={busy || !text.trim()} className="bg-brand text-primary-foreground">Publier</Button></div></div></form>
        {error && <div role="alert" className="mt-3 text-sm text-blood">{error} <Button variant="link" onClick={() => void load()}>Réessayer</Button></div>}
        {!club ? <div className="mt-4 space-y-3">{[0, 1, 2].map(i => <div key={i} className="h-24 animate-pulse rounded-xl bg-panel2" />)}</div>
          : club.posts.length === 0 ? <p className="mt-4 text-sm text-inkmute">Soyez le premier à publier dans le club !</p>
          : <ul className="mt-4 space-y-3">{club.posts.map(p => <li key={p.id} className="rounded-xl border border-line bg-panel p-4">
            <div className="flex items-center gap-3"><Avatar name={p.author.name} /><div className="min-w-0 flex-1"><p className="truncate font-semibold text-ink">{p.author.name}</p><p className="text-xs text-inkmute">{timeAgo(p.created)}</p></div>{p.mine && <Button variant="ghost" size="icon" aria-label="Supprimer" className="text-inkmute" onClick={() => void remove(p)}><span className="material-symbols-outlined">delete</span></Button>}</div>
            <p className="mt-3 whitespace-pre-wrap break-words text-sm text-ink">{p.body}</p>
            <div className="mt-3 flex items-center gap-4 border-t border-line pt-2 text-sm"><Button variant="ghost" disabled={pending.includes(p.id)} aria-pressed={p.liked} onClick={() => void like(p)} className={`flex items-center gap-1 ${p.liked ? 'font-bold text-blood' : 'text-inkmute'}`}><span className="material-symbols-outlined">favorite</span>{p.likes || ''} J’aime</Button>
              {!p.mine && p.author.relation === 'friend' && <Link to="/amis/$id" params={{ id: String(p.author.id) }} className="flex items-center gap-1 text-inkmute"><span className="material-symbols-outlined">chat</span>Message</Link>}</div>
          </li>)}</ul>}
        {club && club.posts.length >= 20 && more && <Button variant="outline" disabled={moreBusy} className="mt-4 w-full" onClick={() => void loadMore()}>Voir plus</Button>}
      </section>

      {!!club?.top.length && <section><h2 className="font-display text-lg font-extrabold text-ink">Meilleurs auditeurs</h2>
        <ol className="mt-2 divide-y divide-line">{club.top.map((p, i) => <li key={p.id} className="flex items-center gap-3 py-3"><span className={`w-6 text-center font-display font-extrabold ${i < 3 ? 'text-blood' : 'text-inkmute'}`}>{i + 1}</span><Avatar name={p.name} /><span className="min-w-0 flex-1 truncate font-semibold text-ink">{p.name}{p.id === user.id && ' (vous)'}</span><span className="font-bold text-brand">{p.points} pts</span></li>)}</ol></section>}
    </div>
  </Screen>;
}
