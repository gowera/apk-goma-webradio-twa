import { createFileRoute } from "@tanstack/react-router";
import { Screen } from "@/components/Screen";

export const Route = createFileRoute("/conditions")({
  component: Conditions,
  head: () => ({
    meta: [
      { property: "og:title", content: "Conditions d’utilisation — GOMA WEBRADIO" },
      { property: "og:description", content: "Conditions de publication et de modération des commentaires Goma Webradio." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
      { title: "Conditions d’utilisation — GOMA WEBRADIO" },
      { name: "description", content: "Conditions de publication et de modération des commentaires Goma Webradio." },
    ],
  }),
});

function Conditions() {
  return (
    <Screen title="Conditions d’utilisation" back>
      <article className="mt-5 space-y-4 text-sm leading-relaxed text-ink">
        <p>
          En utilisant GOMA WEBRADIO, vous vous engagez à respecter la loi et les droits des autres utilisateurs.
          Ces règles s’appliquent notamment aux commentaires publiés dans l’application.
        </p>
        <h2 className="pt-2 font-display text-lg font-extrabold">Commentaires</h2>
        <p>
          Ne publiez pas de contenu illégal, menaçant, haineux, trompeur, répétitif, portant atteinte à la vie privée
          ou aux droits d’autrui. Vous devez être autorisé à partager les textes et éléments que vous soumettez.
        </p>
        <h2 className="pt-2 font-display text-lg font-extrabold">Modération et signalement</h2>
        <p>
          Les commentaires peuvent être examinés avant publication et retirés s’ils enfreignent ces règles. Utilisez
          « Signaler » sous un commentaire ou écrivez à{" "}
          <a href="mailto:contact@gomawebradio.com" className="underline">contact@gomawebradio.com</a>.
          Le blocage d’un auteur masque ses commentaires sur cet appareil.
        </p>
        <h2 className="pt-2 font-display text-lg font-extrabold">Données personnelles</h2>
        <p>
          Les informations envoyées avec un commentaire sont traitées selon la{" "}
          <a href="https://gomawebradio.com/politique-de-confidentialite" className="underline">
            politique de confidentialité
          </a>
          . Consultez-la avant de publier.
        </p>
        <p>
          Pour demander l’accès, la rectification ou l’effacement de vos données, écrivez à{" "}
          <a href="mailto:contact@gomawebradio.com?subject=Demande%20relative%20%C3%A0%20mes%20donn%C3%A9es" className="underline">
            contact@gomawebradio.com
          </a>.
        </p>
      </article>
    </Screen>
  );
}