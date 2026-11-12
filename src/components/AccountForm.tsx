import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate } from '@tanstack/react-router';
import { Screen } from '@/components/Screen';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LOGO_URL } from '@/lib/media';
import { rewardsRequest, saveToken, type RewardUser } from '@/lib/rewards-api';
import { useRewards } from '@/context/rewards-context';

type Mode = 'login' | 'signup' | 'forgot' | 'reset' | 'verify';
const titles = { login: 'Connexion', signup: 'Inscription', forgot: 'Mot de passe oublié', reset: 'Nouveau mot de passe', verify: 'Confirmation de votre email' };
export function AccountForm({ mode }: { mode: Mode }) {
  const { user } = useRewards();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [referral, setReferral] = useState('');
  const [token, setToken] = useState('');
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  useEffect(() => {
    const query = new URLSearchParams(window.location.search);
    setEmail(query.get('email') ?? '');
    setToken(query.get('token') ?? '');
    setReferral(query.get('ref') ?? window.localStorage.getItem('gwr-referral') ?? '');
    if (query.get('ref')) window.localStorage.setItem('gwr-referral', query.get('ref') ?? '');
  }, []);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setMessage('');
    if (['signup','reset'].includes(mode) && password !== confirm) { setError('Les mots de passe ne correspondent pas.'); return; }
    setBusy(true);
    try {
      if (mode === 'login') {
        const result = await rewardsRequest<{ token: string; user: RewardUser }>('login', { email, password });
        saveToken(result.token); setPassword(''); await navigate({ to: '/recompenses' });
      } else {
        const path = mode === 'signup' ? 'signup' : mode === 'forgot' ? 'forgot' : mode === 'reset' ? 'reset' : 'verify';
        const result = await rewardsRequest<{ message: string }>(path, { email, name, password, referral, consent, token });
        setMessage(result.message); setPassword(''); setConfirm('');
        if (mode === 'signup') window.localStorage.removeItem('gwr-referral');
        if (mode === 'verify' || mode === 'reset') window.history.replaceState(null, '', window.location.pathname);
      }
    } catch (e) { setError(e instanceof Error ? e.message : 'Opération impossible.'); }
    finally { setBusy(false); }
  };
  return <Screen title={titles[mode]} back>
    <div className="mx-auto max-w-md py-8">
      <img src={LOGO_URL} alt="GOMA WEBRADIO" className="mx-auto h-20 w-20 object-contain" />
      <h2 className="mt-5 text-center font-display text-2xl font-extrabold text-ink">{titles[mode]}</h2>
      {user && ['login','signup'].includes(mode) ? <div className="mt-6 text-center"><p className="text-ink">Vous êtes connecté en tant que {user.name}.</p><Button asChild className="mt-4"><Link to="/recompenses">Mon profil et club des auditeurs</Link></Button></div> : <form onSubmit={submit} className="mt-7 space-y-5">
        {mode === 'signup' && <Field label="Nom affiché"><Input value={name} onChange={e => setName(e.target.value)} autoComplete="nickname" required minLength={2} maxLength={80} /></Field>}
        <Field label="Adresse email"><Input type="email" value={email} onChange={e => setEmail(e.target.value)} autoComplete="email" required maxLength={254} /></Field>
        {['login','signup','reset'].includes(mode) && <Field label="Mot de passe"><Input aria-label="Mot de passe" type="password" value={password} onChange={e => setPassword(e.target.value)} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} required minLength={mode === 'login' ? 1 : 10} maxLength={200} />{mode !== 'login' && <span className="text-xs text-inkmute">10 caractères minimum</span>}</Field>}
        {['signup','reset'].includes(mode) && <Field label="Confirmer le mot de passe"><Input type="password" value={confirm} onChange={e => setConfirm(e.target.value)} autoComplete="new-password" required minLength={10} maxLength={200} /></Field>}
        {mode === 'signup' && <><Field label="Code de parrainage (facultatif)"><Input value={referral} onChange={e => setReferral(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g,''))} maxLength={4} /></Field><label className="flex items-start gap-3 text-sm text-inkmute"><input type="checkbox" checked={consent} onChange={e => setConsent(e.target.checked)} required className="mt-1 accent-brand" /><span>J’accepte les <Link to="/conditions" className="text-brand underline">conditions d’utilisation</Link> et la <a href="https://gomawebradio.com/politique-de-confidentialite" className="text-brand underline" target="_blank" rel="noreferrer">politique de confidentialité</a>.</span></label></>}
        {(mode === 'verify' || mode === 'reset') && !token && <p className="text-sm text-inkmute">Ouvrez le lien reçu par email pour continuer.</p>}
        {error && <p role="alert" className="text-sm text-blood">{error}</p>}
        {message && <p role="status" className="rounded-lg border border-line bg-panel2 p-4 text-sm text-ink">{message}</p>}
        <Button type="submit" disabled={busy || ((mode === 'verify' || mode === 'reset') && !token) || (!!message && ['verify','reset','signup'].includes(mode))} className="h-12 w-full bg-brand text-primary-foreground">
          <span className={`material-symbols-outlined ${busy ? 'animate-spin' : ''}`}>{busy ? 'progress_activity' : mode === 'signup' ? 'person_add' : mode === 'verify' ? 'verified_user' : 'login'}</span>
          {busy ? 'Veuillez patienter…' : mode === 'signup' ? 'Créer mon compte' : mode === 'login' ? 'Me connecter' : mode === 'forgot' ? 'Envoyer le lien' : mode === 'verify' ? 'Confirmer mon email' : 'Enregistrer le mot de passe'}
        </Button>
        <div className="flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm font-semibold text-brand">
          {mode !== 'login' && <Link to="/connexion">Se connecter</Link>}
          {mode === 'login' && <><Link to="/inscription">Créer un compte</Link><Link to="/mot-de-passe-oublie">Mot de passe oublié</Link></>}
        </div>
      </form>}
    </div>
  </Screen>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="block space-y-2 text-sm font-semibold text-ink"><span>{label}</span>{children}</label>; }
