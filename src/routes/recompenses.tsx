import { createFileRoute, Link } from '@tanstack/react-router';
import { useEffect, useState } from 'react';
import { Screen, SectionHeader } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useRewards } from '@/context/rewards-context';
import { rewardsRequest, creditShare, type Gift } from '@/lib/rewards-api';
import { shareContent } from '@/lib/format';
import { LIVE_TRACK, usePlayer } from '@/context/player';
export const Route = createFileRoute('/recompenses')({
  head: () => ({ meta: [
    { title: 'Mes points et cadeaux — GOMA WEBRADIO' },
    { name: 'description', content: 'Vos points, invitations et cadeaux du programme auditeurs GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Mes points et cadeaux — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Parrainages, écoute en direct et cadeaux GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary' }, { name: 'robots', content: 'noindex' },
  ] }), component: Rewards,
});
const names: Record<string,string> = { read: 'Article lu', share_app: 'Application partagée', share_article: 'Article partagé', listen: 'Heure de radio', referral: 'Parrainage qualifié', gift: 'Cadeau réservé', refund: 'Remboursement', admin: 'Ajustement' };
const statuses: Record<string,string> = { pending: 'En attente de remise', fulfilled: 'Cadeau remis', cancelled: 'Annulée et remboursée' };
function Rewards() {
  const { user, rules, loading, error, refresh, logout, seconds } = useRewards();
  const { toggle, track, playing } = usePlayer();
  const [gifts, setGifts] = useState<Gift[]>([]);
  const [leaders, setLeaders] = useState<{ name: string; referrals: string }[]>([]);
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState('');
  const [selected, setSelected] = useState<Gift | null>(null);
  const [requestKey, setRequestKey] = useState('');
  const load = async () => {
    try { const [g,l] = await Promise.all([rewardsRequest<Gift[]>('gifts'), rewardsRequest<{ name: string; referrals: string }[]>('leaderboard')]); setGifts(g); setLeaders(l); }
    catch (e) { setMessage(e instanceof Error ? e.message : 'Cadeaux indisponibles.'); }
  };
  useEffect(() => { if (user) { setName(user.name); void load(); } }, [user?.id]);
  const claim = async () => {
    if (!selected) return;
    setBusy(true); setMessage('');
    try { const result = await rewardsRequest<{ id: string }>('claim', { gift_id: selected.id, request_key: requestKey }); setSelected(null); setMessage(`Demande #${result.id} enregistrée. GOMA WEBRADIO vous contactera par email pour la remise.`); await refresh(); await load(); }
    catch (e) { setMessage(e instanceof Error ? e.message : 'Réservation impossible.'); }
    finally { setBusy(false); }
  };
  const run = async (fn: () => Promise<void>) => { setBusy(true); setMessage(''); try { await fn(); } catch (e) { setMessage(e instanceof Error ? e.message : 'Opération impossible.'); } finally { setBusy(false); } };
  return <Screen title="Mes points et cadeaux" back>
    {!user ? <div className="py-10 text-center"><span className="material-symbols-outlined text-5xl text-brand">redeem</span><h2 className="mt-4 font-display text-2xl font-extrabold text-ink">Le club des auditeurs</h2><p className="mt-3 text-sm text-inkmute">Votre compte, vos invitations et vos cadeaux GOMA WEBRADIO.</p>{loading && <p className="mt-3 text-sm text-inkmute">Chargement…</p>}{error && <p role="alert" className="mt-3 text-sm text-blood">{error}</p>}<div className="mt-6 flex flex-wrap justify-center gap-3"><Button asChild className="bg-brand text-primary-foreground"><Link to="/inscription">Créer un compte</Link></Button><Button asChild variant="outline"><Link to="/connexion">Se connecter</Link></Button></div></div> : <div className="space-y-7 pt-6">
      <section className="border-b border-line pb-6"><p className="text-sm font-semibold text-inkmute">Bonjour {user.name}</p><div className="mt-2 flex items-baseline gap-2"><span className="font-display text-5xl font-extrabold text-brand">{user.balance}</span><span className="font-semibold text-ink">points</span></div><p className="mt-2 text-sm text-inkmute">{user.referrals} parrainage{user.referrals > 1 ? 's' : ''} qualifié{user.referrals > 1 ? 's' : ''}</p><Button variant="ghost" onClick={() => void refresh()} disabled={loading} className="mt-2"><span className="material-symbols-outlined">refresh</span>Actualiser</Button></section>
      {(message || error) && <p role="status" className="rounded-lg border border-line bg-panel2 p-4 text-sm text-ink">{message || error}</p>}
      <section><SectionHeader title="Inviter mes proches" /><p className="break-all rounded-lg border border-line bg-panel px-3 py-3 font-mono text-sm text-ink">{user.code}</p><Button className="mt-3 w-full bg-brand text-primary-foreground" onClick={() => void run(async () => { const result = await shareContent({ title: 'Rejoignez GOMA WEBRADIO', text: `Mon code de parrainage : ${user.code}`, url: user.invite_url }); if (result === 'shared') { await creditShare('share_app'); setMessage('Invitation partagée.'); } else setMessage(result === 'copied' ? 'Invitation copiée. Les points ne sont accordés qu’après un partage validé par le téléphone.' : 'Partage annulé ou indisponible.'); })} disabled={busy}><span className="material-symbols-outlined">share</span>Partager mon invitation</Button><p className="mt-2 text-xs text-inkmute">Parrainage qualifié après confirmation de l’email et une première action récompensée. Le téléchargement Google Play n’est pas vérifiable automatiquement.</p></section>
      <section><SectionHeader title="Écoute en direct" /><div className="flex items-center justify-between gap-3"><span className="font-display text-2xl font-bold text-ink">{Math.floor((seconds % 3600) / 60)} / 60 min</span><Button onClick={() => toggle(LIVE_TRACK)} className="bg-blood text-primary-foreground"><span className="material-symbols-outlined">{track?.kind === 'radio' && playing ? 'pause' : 'play_arrow'}</span>{track?.kind === 'radio' && playing ? 'Pause' : 'Écouter'}</Button></div><progress value={seconds % 3600} max={3600} aria-label="Progression de l’heure de radio" className="mt-3 h-2 w-full accent-brand" /><p className="mt-2 text-xs text-inkmute">{rules?.listen ?? '—'} points par heure complète. Rien avant 60 minutes. Une pause annule l’heure incomplète. Les interruptions longues peuvent réinitialiser le suivi ; les heures déjà créditées restent acquises.</p></section>
      <section><SectionHeader title="Mes cadeaux" />{gifts.length === 0 && <p className="text-sm text-inkmute">Aucun cadeau disponible actuellement.</p>}<div className="grid grid-cols-1 gap-3 sm:grid-cols-2">{gifts.map(g => <article key={g.id} className="rounded-lg border border-line bg-panel p-4"><span className="material-symbols-outlined text-3xl text-brand">{g.title.toLowerCase().includes('shirt') ? 'apparel' : 'redeem'}</span><h3 className="mt-2 font-bold text-ink">{g.title}</h3><p className="mt-1 text-sm text-inkmute">{g.cost} points · {Number(g.stock) > 0 ? `${g.stock} disponible(s)` : 'Épuisé'}</p><Button variant="outline" className="mt-4 w-full" disabled={busy || Number(g.stock) < 1 || user.balance < Number(g.cost)} onClick={() => { setSelected(g); setRequestKey(crypto.randomUUID()); }}>Réserver</Button></article>)}</div></section>
      {user.claims.length > 0 && <section><SectionHeader title="Mes demandes" /><ul className="divide-y divide-line">{user.claims.map(c => <li key={c.id} className="py-3"><p className="text-sm font-semibold text-ink">#{c.id} · {c.title}</p><p className="text-xs text-inkmute">{statuses[c.status] ?? c.status} · {c.cost} points</p></li>)}</ul></section>}
      <section><SectionHeader title="Barème" /><dl className="divide-y divide-line text-sm">{[['Article lu',rules?.read],['Application partagée',rules?.share_app],['Article partagé',rules?.share_article],['Heure complète de radio',rules?.listen],...(rules?.referral ? [['Parrainage qualifié',rules.referral]] : [])].map(([label,value]) => <div key={String(label)} className="flex justify-between gap-3 py-3"><dt className="text-ink">{label}</dt><dd className="font-bold text-brand">{value ?? '—'} pts</dd></div>)}</dl><p className="mt-2 text-xs text-inkmute">Lecture : 30 secondes minimum, une fois par article, {rules?.read_limit ?? '—'} articles/jour. Partage app : une fois/jour. Partage article : une fois par article/jour, {rules?.share_article_limit ?? '—'} maximum. Radio : {rules?.listen_limit ?? '—'} heures récompensées/jour. Les partages sont déclaratifs, sans preuve de publication sociale.</p></section>
      <section><SectionHeader title="Classement des parrains" />{leaders.length ? <ol className="divide-y divide-line">{leaders.map((l,i) => <li key={`${i}-${l.name}`} className="flex items-center justify-between gap-3 py-3 text-sm"><span className="text-ink">{i + 1}. {l.name}</span><span className="font-bold text-brand">{l.referrals}</span></li>)}</ol> : <p className="text-sm text-inkmute">Les premiers parrainages qualifiés apparaîtront ici.</p>}</section>
      <section><SectionHeader title="Historique des points" />{user.ledger.length ? <ul className="divide-y divide-line">{user.ledger.map(l => <li key={l.id} className="flex items-center justify-between gap-3 py-3"><div><p className="text-sm font-semibold text-ink">{names[l.kind] ?? l.kind}</p><p className="text-xs text-inkmute">{new Date(`${l.created.replace(' ','T')}Z`).toLocaleString('fr-FR')}</p></div><span className={`font-bold ${l.points < 0 ? 'text-blood' : 'text-brand'}`}>{Number(l.points) > 0 ? '+' : ''}{l.points}</span></li>)}</ul> : <p className="text-sm text-inkmute">Aucun point enregistré.</p>}</section>
      <section className="border-t border-line pt-5"><SectionHeader title="Mon compte" /><form onSubmit={e => { e.preventDefault(); void run(async () => { await rewardsRequest('profile', { name }); await refresh(); setMessage('Nom mis à jour.'); }); }} className="flex flex-wrap gap-2"><label className="min-w-0 flex-1 text-sm text-ink">Nom affiché<Input value={name} onChange={e => setName(e.target.value)} minLength={2} maxLength={80} required className="mt-2" /></label><Button type="submit" disabled={busy} className="mt-7">Enregistrer</Button></form><p className="mt-2 text-sm text-inkmute">{user.email}</p><div className="mt-4 flex flex-wrap gap-3"><Button variant="outline" asChild><Link to="/mot-de-passe-oublie">Changer mon mot de passe</Link></Button><Button variant="ghost" onClick={() => void run(logout)} disabled={busy}><span className="material-symbols-outlined">logout</span>Déconnexion</Button></div></section>
    </div>}
    {selected && <div className="fixed inset-0 z-[60] flex items-center justify-center bg-ink/60 p-4"><div role="dialog" aria-modal="true" aria-labelledby="gift-confirm" className="w-full max-w-md rounded-lg bg-panel p-6 shadow-lift"><h2 id="gift-confirm" className="font-display text-xl font-bold text-ink">Réserver {selected.title} ?</h2><p className="mt-3 text-sm text-inkmute">{selected.cost} points seront débités. La remise est validée par GOMA WEBRADIO. Une annulation par l’équipe rembourse les points.</p>{message && <p role="alert" className="mt-3 text-sm text-blood">{message}</p>}<div className="mt-5 flex flex-wrap gap-3"><Button onClick={() => void claim()} disabled={busy}>Confirmer</Button><Button variant="outline" onClick={() => setSelected(null)} disabled={busy}>Annuler</Button></div></div></div>}
  </Screen>;
}
