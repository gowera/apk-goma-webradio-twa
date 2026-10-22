import { createFileRoute } from '@tanstack/react-router';
import { AccountForm } from '@/components/AccountForm';
export const Route = createFileRoute('/connexion')({
  head: () => ({ meta: [
    { title: 'Connexion — GOMA WEBRADIO' },
    { name: 'description', content: 'Connectez-vous à votre compte auditeur GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Connexion — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Connectez-vous à votre compte auditeur GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'referrer', content: 'no-referrer' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: () => <AccountForm mode="login" />,
});
