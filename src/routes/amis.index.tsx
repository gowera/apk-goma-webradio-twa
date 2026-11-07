import { createFileRoute, Link } from '@tanstack/react-router';
import { useCallback, useEffect, useState } from 'react';
import { Screen } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useRewards } from '@/context/rewards-context';
import { friendLists, removeFriend, requestFriend, respondFriend, searchListeners, type FriendLists, type Person } from '@/lib/social-api';

export const Route = createFileRoute('/amis/')({
  head: () => ({ meta: [
    { title: 'Mes amis — GOMA WEBRADIO' },
    { name: 'description', content: 'Retrouvez les auditeurs GOMA WEBRADIO, ajoutez-les comme amis et discutez ensemble.' },
    { property: 'og:title', content: 'Mes amis — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Ajoutez des auditeurs GOMA WEBRADIO comme amis et échangez des messages.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: FriendsPage,
});

const Avatar = ({ name }: { name: string }) => <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-panel2 font-display text-lg font-extrabold text-brand">{name.trim().slice(0, 1).toUpperCase()}</span>;

function FriendsPage() {
  const { user, loading } = useRewards();
  const [lists, setLists] = useState<FriendLists | null>(null);
  const [q, setQ] = useState('');
  const [results, setResults] = useState<Person[]>([]);
  const [message, setMessage] = useState('');
  const load = useCallback(async () => { try { setLists(await friendLists()); setMessage(''); } catch (e) { setMessage(e instanceof Error ? e.message : 'Amis indisponibles.'); } }, []);
  useEffect(() => { if (!user) return; void load(); const t = window.setInterval(() => void load(), 15000); return () => window.clearInterval(t); }, [user?.id, load]);
  useEffect(() => {
    if (!user || q.trim().length < 2) { setResults([]); return; }
    let current = true;
    const t = window.setTimeout(() => { searchListeners(q.trim()).then(r => { if (current) setResults(r); }).catch(e => { if (current) setMessage(e.message); }); }, 350);
    return () => { current = false; window.clearTimeout(t); };
  }, [q, user?.id]);
  const act = async (fn: () => Promise<unknown>) => { setMessage(''); try { await fn(); await load(); if (q.trim().length >= 2) setResults(await searchListeners(q.trim())); } catch (e) { setMessage(e instanceof Error ? e.message : 'Opération impossible.'); } };

  if (!user) return <Screen title="Mes amis" back><div className="py-10 text-center"><span className="material-symbols-outlined text-5xl text-brand">group</span><p className="mt-3 text-sm text-inkmute">{loading ? 'Chargement…' : 'Connectez-vous pour retrouver vos amis auditeurs.'}</p>{!loading && <Button asChild className="mt-5 bg-brand text-primary-foreground"><Link to="/connexion">Se connecter</Link></Button>}</div></Screen>;

  const action = (p: Person) => p.relation === 'none' ? <Button size="sm" className="bg-brand text-primary-foreground" onClick={() => void act(() => requestFriend(p.id))}>Ajouter</Button>
    : p.relation === 'incoming' ? <div className="flex gap-2"><Button size="sm" className="bg-brand text-primary-foreground" onClick={() => void act(() => respondFriend(p.id, true))}>Accepter</Button><Button size="sm" variant="outline" onClick={() => void act(() => respondFriend(p.id, false))}>Refuser</Button></div>
    : p.relation === 'outgoing' ? <Button size="sm" variant="outline" onClick={() => void act(() => removeFriend(p.id))}>Annuler</Button>
    : <Button size="sm" variant="outline" asChild><Link to="/amis/$id" params={{ id: String(p.id) }}>Message</Link></Button>;
  const row = (p: Person) => <li key={p.id} className="flex items-center gap-3 py-3"><Avatar name={p.name} /><p className="min-w-0 flex-1 truncate font-semibold text-ink">{p.name}</p>{action(p)}</li>;

  return <Screen title="Mes amis" back>
    <div className="space-y-7 pt-5">
      <label className="block"><span className="text-sm font-bold text-ink">Trouver des auditeurs</span><Input className="mt-2" value={q} onChange={e => setQ(e.target.value)} placeholder="Nom de l’auditeur" /></label>
      {message && <p role="alert" className="text-sm text-blood">{message}</p>}
      {results.length > 0 && <ul className="divide-y divide-line">{results.map(row)}</ul>}
      {q.trim().length >= 2 && results.length === 0 && <p className="text-sm text-inkmute">Aucun auditeur trouvé.</p>}
      {!!lists?.incoming.length && <section><h2 className="font-display text-lg font-extrabold text-ink">Demandes reçues</h2><ul className="divide-y divide-line">{lists.incoming.map(row)}</ul></section>}
      <section><h2 className="font-display text-lg font-extrabold text-ink">Discussions</h2>
        {!lists ? <p className="mt-2 text-sm text-inkmute">Chargement…</p> : lists.friends.length === 0 ? <p className="mt-2 text-sm text-inkmute">Ajoutez des amis pour commencer à discuter.</p> :
          <ul className="divide-y divide-line">{lists.friends.map(p => <li key={p.id}><Link to="/amis/$id" params={{ id: String(p.id) }} className="flex items-center gap-3 py-3"><Avatar name={p.name} /><span className="min-w-0 flex-1"><span className="block truncate font-semibold text-ink">{p.name}</span><span className="block truncate text-sm text-inkmute">{p.last?.body ?? 'Dites bonjour !'}</span></span>{!!p.unread && <span className="rounded-full bg-blood px-2 py-0.5 text-xs font-bold text-primary-foreground">{p.unread}</span>}</Link></li>)}</ul>}
      </section>
      {!!lists?.outgoing.length && <section><h2 className="font-display text-lg font-extrabold text-ink">Demandes envoyées</h2><ul className="divide-y divide-line">{lists.outgoing.map(row)}</ul></section>}
    </div>
  </Screen>;
}
