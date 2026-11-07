<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history — force pushing, or rebasing/amending/squashing commits
> that are already pushed — as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->

- Keep radio reconnection intent in the browser-safe RadioRecovery controller, separate from audio events, so automatic pauses retry but explicit pauses and podcast switches cancel recovery.
- Define PlayerContext and its types in a separate non-component module so hot updates cannot replace the context identity while mounted providers remain active.
- Use the shared app-store URL for app sharing and the global dismissible review prompt so distribution links stay consistent.
- Keep rewards accounts, sessions, referrals, ledger, listening validation and gift reservations in the WordPress rewards plugin; the browser calls its REST API directly and stores only its session credential, so no parallel account or points backend is introduced.
- Dispatch reward summaries through a WordPress cron queue with unique user/week records and signed unsubscribe confirmation; this keeps delivery tracking and preferences on the same WordPress backend.
- Apply rewards API CORS in WordPress's response-serving filter using exact configured origins plus the app URL origin (including non-default ports), so preflights and errors share the same allowlist without weakening private endpoint authentication.
- Keep article activity timing in the browser-safe ArticleReading controller; only scroll activity with visible article content advances time, and visibility loss or suspended timers cancels activity to avoid counting sleep.
- WordPress publication newsletters use a per post/user queue table processed by a 5-minute cron with signed, confirmed unsubscribe, so each subscriber gets each article at most once.
- Keep friendships, private messages, read receipts and club posts/likes behind WordPress listener session checks; cursor pagination preserves history without exposing messages to non-friends.
- Preserve migrated referral codes as WordPress user aliases while publishing new app invitation links, so older shared invitations remain usable.
