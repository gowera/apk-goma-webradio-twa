import { createFileRoute } from '@tanstack/react-router';
import { AccountForm } from '@/components/AccountForm';
export const Route = createFileRoute('/reset-password')({
  head: () => ({ meta: [
    { title: 'Nouveau mot de passe — GOMA WEBRADIO' },
    { name: 'description', content: 'Choisissez un nouveau mot de passe pour votre compte GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Nouveau mot de passe — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Choisissez un nouveau mot de passe pour votre compte GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'referrer', content: 'no-referrer' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: () => <AccountForm mode="reset" />,
});
