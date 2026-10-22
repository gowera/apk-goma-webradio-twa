import { createFileRoute } from '@tanstack/react-router';
import { AccountForm } from '@/components/AccountForm';
export const Route = createFileRoute('/inscription')({
  head: () => ({ meta: [
    { title: 'Inscription — GOMA WEBRADIO' },
    { name: 'description', content: 'Créez votre compte auditeur GOMA WEBRADIO et rejoignez le programme de points.' },
    { property: 'og:title', content: 'Inscription — GOMA WEBRADIO' },
    { property: 'og:description', content: 'Créez votre compte auditeur GOMA WEBRADIO et rejoignez le programme de points.' },
    { property: 'og:type', content: 'website' },
    { name: 'twitter:card', content: 'summary' },
    { name: 'referrer', content: 'no-referrer' },
    { name: 'robots', content: 'noindex' },
  ] }),
  component: () => <AccountForm mode="signup" />,
});
