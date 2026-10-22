import { createFileRoute } from '@tanstack/react-router';
import { AccountForm } from '@/components/AccountForm';
export const Route = createFileRoute('/verification')({
  head: () => ({ meta: [
    { title: 'Confirmation du compte — GOMA WEBRADIO' },
    { name: 'description', content: 'Confirmez votre adresse email pour votre compte GOMA WEBRADIO.' },
    { property: 'og:title', content: 'Confirmation du compte — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Confirmez votre adresse email pour votre compte GOMA WEBRADIO.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'referrer', content: 'no-referrer' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: () => <AccountForm mode="verify" />,
});
