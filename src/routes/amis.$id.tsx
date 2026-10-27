import { createFileRoute, Link } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';
import { Screen } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { useRewards } from '@/context/rewards-context';
import { conversation, removeFriend, sendMessage, type ChatMessage } from '@/lib/social-api';

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
  const lastId = useRef(0);
  const end = useRef<HTMLDivElement>(null);
  const merge = (items: ChatMessage[]) => { if (!items.length) return; lastId.current = Math.max(lastId.current, ...items.map(m => m.id)); setMessages(prev => { const seen = new Set(prev.map(m => m.id)); return [...prev, ...items.filter(m => !seen.has(m.id))]; }); };
  useEffect(() => {
    if (!user) return;
    lastId.current = 0; setMessages([]);
    let stop = false;
    const poll = () => conversation(id, lastId.current).then(r => { if (stop) return; setName(r.friend.name); merge(r.messages); setError(''); }).catch(e => { if (!stop) setError(e instanceof Error ? e.message : 'Discussion indisponible.'); });
    void poll();
    const t = window.setInterval(() => { if (document.visibilityState === 'visible') void poll(); }, 5000);
    return () => { stop = true; window.clearInterval(t); };
  }, [id, user?.id]);
  useEffect(() => { end.current?.scrollIntoView({ block: 'end' }); }, [messages.length]);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); const body = text.trim(); if (!body || busy) return;
    setBusy(true);
    try { merge([await sendMessage(id, body)]); setText(''); setError(''); } catch (err) { setError(err instanceof Error ? err.message : 'Message non envoyé.'); } finally { setBusy(false); }
  };
  if (!user) return <Screen title="Discussion" back><p className="py-10 text-center text-sm text-inkmute">Connectez-vous pour discuter. <Link to="/connexion" className="font-bold text-brand">Se connecter</Link></p></Screen>;
  return <Screen title={name} back>
    <div className="flex min-h-[60vh] flex-col pt-4">
      <div className="mb-3 flex justify-end"><Button variant="ghost" size="sm" onClick={async () => { if (window.confirm(`Retirer ${name} de vos amis ?`)) { await removeFriend(id).catch(() => null); window.history.back(); } }}>Retirer des amis</Button></div>
      <ol className="flex flex-1 flex-col gap-2" aria-live="polite">
        {messages.length === 0 && !error && <li className="py-8 text-center text-sm text-inkmute">Aucun message. Écrivez le premier !</li>}
        {messages.map(m => <li key={m.id} className={`max-w-[80%] whitespace-pre-wrap break-words rounded-2xl px-4 py-2 text-sm ${m.mine ? 'self-end bg-brand text-primary-foreground' : 'self-start bg-panel2 text-ink'}`}>{m.body}</li>)}
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
