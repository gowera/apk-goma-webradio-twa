import { useEffect, useState } from 'react';
import { Link } from '@tanstack/react-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ListenerAvatar } from '@/components/ListenerAvatar';
import { postComments, addComment, deleteComment, timeAgo, type ClubComment } from '@/lib/social-api';
export function ClubComments({ postId, onCount }: { postId: number; onCount: (delta: number) => void }) {
  const [rows, setRows] = useState<ClubComment[]>([]);
  const [text, setText] = useState(''); const [busy, setBusy] = useState(false); const [error, setError] = useState('');
  const load = async () => { try { setRows(await postComments(postId)); setError(''); } catch (e) { setError(e instanceof Error ? e.message : 'Commentaires indisponibles.'); } };
  useEffect(() => { void load(); }, [postId]);
  return <div className="mt-3 border-t border-line pt-3">
    {rows.map(c => <div key={c.id} className="flex gap-2 py-2"><ListenerAvatar name={c.author.name} avatar={c.author.avatar} className="h-8 w-8 text-sm" /><div className="min-w-0 flex-1"><Link to="/auditeurs/$id" params={{ id: String(c.author.id) }} className="text-sm font-bold text-ink">{c.author.name}</Link><p className="whitespace-pre-wrap break-words text-sm text-ink">{c.body}</p><p className="text-xs text-inkmute">{timeAgo(c.created)}</p></div>{c.mine && <Button size="icon" variant="ghost" aria-label="Supprimer mon commentaire" disabled={busy} onClick={async () => { setBusy(true); try { await deleteComment(c.id); setRows(r => r.filter(x => x.id !== c.id)); onCount(-1); } catch { setError('Suppression impossible.'); } finally { setBusy(false); } }}><span className="material-symbols-outlined">delete</span></Button>}</div>)}
    <form className="mt-2 flex gap-2" onSubmit={async e => { e.preventDefault(); if (!text.trim() || busy) return; setBusy(true); try { const c = await addComment(postId, text.trim()); setRows(r => [...r, c]); setText(''); setError(''); onCount(1); } catch (err) { setError(err instanceof Error ? err.message : 'Commentaire non envoyé.'); } finally { setBusy(false); } }}><Input aria-label="Votre commentaire" placeholder="Votre commentaire…" value={text} maxLength={300} onChange={e => setText(e.target.value)} /><Button type="submit" size="icon" aria-label="Envoyer le commentaire" disabled={busy || !text.trim()}><span className="material-symbols-outlined">send</span></Button></form>
    {error && <p role="alert" className="mt-2 text-sm text-blood">{error}<Button variant="link" onClick={() => void load()}>Réessayer</Button></p>}
  </div>;
}