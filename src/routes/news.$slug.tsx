import { createFileRoute, redirect } from "@tanstack/react-router";

// Deep links coming from gomawebradio.com/news/<slug> open the article in the app.
export const Route = createFileRoute("/news/$slug")({
  head: ({ params }) => ({ meta: [
    { title: `${params.slug} — Actualité GOMA WEBRADIO` },
    { name: "description", content: "Lire cette actualité de GOMA WEBRADIO dans l’application." },
    { property: "og:title", content: `${params.slug} — Actualité GOMA WEBRADIO` },
    { property: "og:description", content: "Actualités de Goma et du Nord-Kivu." },
    { property: "og:type", content: "article" },
    { name: "twitter:card", content: "summary_large_image" },
  ] }),
  beforeLoad: ({ params }) => {
    throw redirect({ to: "/articles/$slug", params: { slug: params.slug }, replace: true });
  },
});
