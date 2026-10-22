import { createFileRoute } from '@tanstack/react-router';
import { AccountForm } from '@/components/AccountForm';
export const Route = createFileRoute('/mot-de-passe-oublie')({
  head: () => ({ meta: [
    { title: 'Récupération du compte — GOMA WEBRADIO' },
    { name: 'description', content: 'Recevez un lien pour retrouver votre compte GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Récupération du compte — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Recevez un lien pour retrouver votre compte GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'referrer', content: 'no-referrer' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: () => <AccountForm mode="forgot" />,
});
