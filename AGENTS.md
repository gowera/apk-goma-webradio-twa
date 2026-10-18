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
- Use the shared app-store URL for app sharing and the global dismissible review prompt so distribution links stay consistent.
