import { useEffect, useState } from "react";
import { Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { commentsQuery } from "@/lib/queries";
import { relativeDate } from "@/lib/format";
import { CardListSkeleton, ErrorRetry } from "@/components/Async";

const BLOCKED_AUTHORS_KEY = "gw-comment-blocked-authors";

export function ArticleComments({ postId, articleUrl }: { postId: number; articleUrl: string }) {
  const commentsQ = useQuery(commentsQuery(postId));
  const [blockedAuthors, setBlockedAuthors] = useState<string[]>([]);

  useEffect(() => {
    try {
      const stored = JSON.parse(localStorage.getItem(BLOCKED_AUTHORS_KEY) ?? "[]");
      if (Array.isArray(stored)) {
        setBlockedAuthors(stored.filter((author): author is string => typeof author === "string"));
      }
    } catch {
      setBlockedAuthors([]);
    }
  }, []);

  const comments = commentsQ.data ?? [];
  const visibleComments = comments.filter((comment) => !blockedAuthors.includes(comment.author));

  const blockAuthor = (author: string) => {
    const next = [...blockedAuthors, author];
    setBlockedAuthors(next);
    localStorage.setItem(BLOCKED_AUTHORS_KEY, JSON.stringify(next));
  };

  const unblockAuthor = (author: string) => {
    const next = blockedAuthors.filter((blocked) => blocked !== author);
    setBlockedAuthors(next);
    localStorage.setItem(BLOCKED_AUTHORS_KEY, JSON.stringify(next));
  };

  return (
    <section className="mt-10">
      <h2 className="font-display text-lg font-extrabold text-ink">
        Commentaires {comments.length > 0 && <span className="text-inkmute">({comments.length})</span>}
      </h2>

      <div className="mt-3 rounded-xl border border-line bg-panel p-3 text-sm text-inkmute">
        <p>La publication de commentaires est réservée aux membres connectés sur le site.</p>
        <p className="mt-2 text-xs">
          Selon la politique du site, l’e-mail peut aussi servir à la newsletter. Les commentaires sont modérés.
        </p>
        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs font-semibold">
          <a href={articleUrl} className="underline">Se connecter et commenter sur le site</a>
          <a href="https://gomawebradio.com/politique-de-confidentialite" className="underline">Confidentialité</a>
          <Link to="/conditions" className="underline">Conditions</Link>
        </div>
      </div>

      <div className="mt-4">
        {commentsQ.isPending ? (
          <CardListSkeleton rows={2} />
        ) : commentsQ.isError ? (
          <ErrorRetry
            message="Commentaires indisponibles."
            onRetry={() => void commentsQ.refetch()}
            busy={commentsQ.isFetching}
          />
        ) : visibleComments.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-line p-5 text-center text-sm text-inkmute">
            {comments.length > 0
              ? "Les commentaires des auteurs bloqués sont masqués."
              : "Aucun commentaire pour l'instant. Soyez le premier à réagir."}
          </p>
        ) : (
          <ul className="space-y-3">
            {visibleComments.map((c) => (
              <li
                key={c.id}
                className={
                  "rounded-2xl border border-line bg-panel p-3 shadow-soft " +
                  (c.parent ? "ml-6" : "")
                }
              >
                <div className="flex items-center gap-2">
                  <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand/15 text-xs font-bold text-brand">
                    {c.author.slice(0, 1).toUpperCase()}
                  </span>
                  <p className="truncate text-sm font-bold text-ink">{c.author}</p>
                  <span className="ml-auto shrink-0 text-[11px] text-inkmute">{relativeDate(c.date)}</span>
                </div>
                <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink/90">{c.content}</p>
                <div className="mt-3 flex gap-4 text-xs font-semibold text-inkmute">
                  <a
                    href={`mailto:contact@gomawebradio.com?subject=${encodeURIComponent("Signalement de commentaire")}&body=${encodeURIComponent(`Article : ${postId}\nCommentaire : ${c.id}\nAuteur : ${c.author}\n\n${c.content}`)}`}
                    className="underline"
                  >
                    Signaler
                  </a>
                  <button type="button" onClick={() => blockAuthor(c.author)} className="underline">
                    Bloquer cet auteur
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
        {blockedAuthors.length > 0 && (
          <div className="mt-3 flex flex-wrap gap-2 text-xs text-inkmute">
            <span>Auteurs bloqués sur cet appareil :</span>
            {blockedAuthors.map((author) => (
              <button key={author} type="button" onClick={() => unblockAuthor(author)} className="underline">
                Débloquer {author}
              </button>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
