import { createFileRoute, Link } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';
import { Screen } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { useRewards } from '@/context/rewards-context';
import { conversation, removeFriend, sendMessage, utcDate, type ChatMessage } from '@/lib/social-api';

export const Route = createFileRoute('/amis/$id')({
  head: () => ({ meta: [
    { title: 'Discussion — GOMA WEBRADIO' },
    { name: 'description', content: 'Discussion privée entre amis auditeurs GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Discussion — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Discussion privée entre amis auditeurs GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: ChatPage,
});

function ChatPage() {
  const id = Number(Route.useParams().id);
  const { user } = useRewards();
  const [name, setName] = useState('Discussion');
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [text, setText] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [hasOlder, setHasOlder] = useState(false);
  const [historyBusy, setHistoryBusy] = useState(false);
  const initial = useRef(true);
  const lastId = useRef(0);
  const end = useRef<HTMLDivElement>(null);
  const merge = (items: ChatMessage[], seenIds: number[] = []) => { if (items.length) lastId.current = Math.max(lastId.current, ...items.map(m => m.id)); setMessages(prev => { const all = new Map(prev.map(m => [m.id, m])); items.forEach(m => all.set(m.id, m)); return [...all.values()].map(m => seenIds.includes(m.id) ? { ...m, seen: true } : m).sort((a, b) => a.id - b.id); }); };
  useEffect(() => {
    if (!user) return;
    lastId.current = 0; initial.current = true; setMessages([]); setText(''); setHasOlder(false);
    let stop = false;
    let polling = false;
    const poll = async () => {
      if (polling) return;
      polling = true;
      try {
        let more = true;
        while (more && !stop) {
          const r = await conversation(id, initial.current ? 0 : lastId.current);
          if (stop) return;
          setName(r.friend.name); merge(r.messages, r.seen_ids); setError('');
          if (initial.current) { setHasOlder(r.has_more); initial.current = false; more = false; }
          else more = r.has_more && r.messages.length > 0;
        }
      } catch (e) { if (!stop) setError(e instanceof Error ? e.message : 'Discussion indisponible.'); }
      finally { polling = false; }
    };
    void poll();
    const t = window.setInterval(() => { if (document.visibilityState === 'visible') void poll(); }, 5000);
    return () => { stop = true; window.clearInterval(t); };
  }, [id, user?.id]);
  useEffect(() => { if (!historyBusy) end.current?.scrollIntoView({ block: 'end' }); }, [messages.at(-1)?.id]);
  const older = async () => {
    if (historyBusy || !messages.length) return;
    setHistoryBusy(true);
    try { const r = await conversation(id, 0, messages[0].id); merge(r.messages, r.seen_ids); setHasOlder(r.has_more); setError(''); }
    catch (e) { setError(e instanceof Error ? e.message : 'Historique indisponible.'); }
    finally { setHistoryBusy(false); }
  };
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); const body = text.trim(); if (!body || busy) return;
    setBusy(true);
    try { merge([await sendMessage(id, body)]); setText(''); setError(''); } catch (err) { setError(err instanceof Error ? err.message : 'Message non envoyé.'); } finally { setBusy(false); }
  };
  if (!user) return <Screen title="Discussion" back><p className="py-10 text-center text-sm text-inkmute">Connectez-vous pour discuter. <Link to="/connexion" className="font-bold text-brand">Se connecter</Link></p></Screen>;
  return <Screen title={name} back>
    <div className="flex min-h-[60vh] flex-col pt-4">
      <div className="mb-3 flex flex-wrap items-center gap-3 border-b border-line pb-3"><span aria-hidden="true" className="flex h-11 w-11 items-center justify-center rounded-full bg-panel2 font-display text-lg font-extrabold text-brand">{name.trim().slice(0, 1).toUpperCase()}</span><span className="min-w-0 flex-1"><span className="block truncate font-bold text-ink">{name}</span><span className="block text-xs text-inkmute">Ami du club des auditeurs</span></span><Button variant="ghost" size="sm" onClick={async () => { if (window.confirm(`Retirer ${name} de vos amis ?`)) { try { await removeFriend(id); window.history.back(); } catch (e) { setError(e instanceof Error ? e.message : 'Suppression impossible.'); } } }}>Retirer des amis</Button></div>
      {hasOlder && <Button variant="outline" disabled={historyBusy} className="mb-4 self-center" onClick={() => void older()}>Messages précédents</Button>}
      <ol className="flex flex-1 flex-col gap-1" aria-live="polite">
        {messages.length === 0 && !error && <li className="py-8 text-center text-sm text-inkmute">Aucun message. Écrivez le premier !</li>}
        {messages.map((m, i) => { const d = utcDate(m.created); const day = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }); const prevDay = i ? utcDate(messages[i - 1].created).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) : ''; const last = i === messages.length - 1 || messages[i + 1].mine !== m.mine; return <li key={m.id} className={`flex flex-col ${m.mine ? 'items-end' : 'items-start'}`}>
          {day !== prevDay && <span className="my-3 self-center rounded-full bg-panel2 px-3 py-1 text-xs capitalize text-inkmute">{day}</span>}
          <span className={`max-w-[80%] whitespace-pre-wrap break-words px-4 py-2 text-sm ${m.mine ? 'rounded-2xl rounded-br-md bg-brand text-primary-foreground' : 'rounded-2xl rounded-bl-md bg-panel2 text-ink'}`}>{m.body}</span>
          {last && <span className="mt-1 flex items-center gap-1 px-1 text-[11px] text-inkmute">{d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}{m.mine && <span className="material-symbols-outlined text-sm" title={m.seen ? 'Vu' : 'Envoyé'}>{m.seen ? 'done_all' : 'done'}</span>}{m.mine && m.seen && 'Vu'}</span>}
        </li>; })}
      </ol>
      <div ref={end} />
      {error && <p role="alert" className="mt-3 text-sm text-blood">{error}</p>}
      <form onSubmit={submit} className="sticky bottom-24 mt-4 flex gap-2 border-t border-line bg-paper py-3">
        <textarea value={text} onChange={e => setText(e.target.value)} maxLength={1000} rows={1} placeholder="Votre message" aria-label="Votre message" className="min-h-10 flex-1 resize-none rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink" onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.currentTarget.form?.requestSubmit(); } }} />
        <Button type="submit" size="icon" aria-label="Envoyer" disabled={busy || !text.trim()} className="rounded-full bg-brand text-primary-foreground"><span className="material-symbols-outlined">send</span></Button>
      </form>
    </div>
  </Screen>;
}
