import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { ListenerAvatar } from '@/components/ListenerAvatar';
import { useRewards } from '@/context/rewards-context';
import { rewardsRequest, uploadClubPhoto } from '@/lib/rewards-api';

export function ProfileEditor() {
  const { user, refresh } = useRewards();
  const [open, setOpen] = useState(false);
  const [values, setValues] = useState({ name: '', bio: '', city: '', avatar: '', cover: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  if (!user) return null;
  const photo = async (key: 'avatar' | 'cover', file?: File) => {
    if (!file) return; setBusy(true); setError('');
    try { const url = await uploadClubPhoto(file); setValues(v => ({ ...v, [key]: url })); }
    catch (e) { setError(e instanceof Error ? e.message : 'Photo non envoyée.'); }
    finally { setBusy(false); }
  };
  return <><Button variant="outline" onClick={() => { setValues({ name: user.name, bio: user.bio ?? '', city: user.city ?? '', avatar: user.avatar ?? '', cover: user.cover ?? '' }); setError(''); setOpen(true); }}><span className="material-symbols-outlined">edit</span>Modifier mon profil</Button>
    <Dialog open={open} onOpenChange={v => { if (!busy) setOpen(v); }}><DialogContent className="max-h-[85dvh] overflow-y-auto"><DialogTitle>Modifier mon profil</DialogTitle><DialogDescription>Mon profil auditeur</DialogDescription>
      <form className="space-y-4" onSubmit={async e => { e.preventDefault(); setBusy(true); setError(''); try { await rewardsRequest('profile', values); await refresh(); setOpen(false); } catch (err) { setError(err instanceof Error ? err.message : 'Modification impossible.'); } finally { setBusy(false); } }}>
        <div className="flex items-center gap-3"><ListenerAvatar name={values.name} avatar={values.avatar} className="h-16 w-16 text-2xl" /><label className="min-w-0 flex-1 text-sm">Photo de profil<Input aria-label="Photo de profil" type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} onChange={e => void photo('avatar', e.target.files?.[0])} className="mt-1" /></label></div>
        {values.cover && <img src={values.cover} alt="Couverture" className="h-24 w-full rounded-lg object-cover" />}
        <label className="block text-sm">Photo de couverture<Input aria-label="Photo de couverture" type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} onChange={e => void photo('cover', e.target.files?.[0])} className="mt-1" /></label>
        <label className="block text-sm">Nom affiché<Input value={values.name} minLength={2} maxLength={80} required onChange={e => setValues(v => ({ ...v, name: e.target.value }))} /></label>
        <label className="block text-sm">Ville<Input value={values.city} maxLength={60} onChange={e => setValues(v => ({ ...v, city: e.target.value }))} /></label>
        <label className="block text-sm">Bio<Textarea value={values.bio} maxLength={300} rows={3} onChange={e => setValues(v => ({ ...v, bio: e.target.value }))} /></label>
        {error && <p role="alert" className="text-sm text-blood">{error}</p>}
        <Button type="submit" disabled={busy} className="w-full">{busy ? 'Enregistrement…' : 'Enregistrer'}</Button>
      </form></DialogContent></Dialog></>;
}