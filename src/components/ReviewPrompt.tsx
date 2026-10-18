import { useEffect, useState } from "react";
import { Star } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { APP_STORE_URL } from "@/lib/app-store";

const KEY = "gw-review-prompt";

export function ReviewPrompt() {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(KEY) ?? "null");
      if (saved?.reviewed || (saved?.dismissedAt && Date.now() - saved.dismissedAt < 7 * 86400000)) return;
    } catch { /* Storage may be unavailable. */ }
    const timer = window.setTimeout(() => setOpen(true), 60000);
    return () => window.clearTimeout(timer);
  }, []);

  const close = (reviewed = false) => {
    try { localStorage.setItem(KEY, JSON.stringify({ reviewed, dismissedAt: Date.now() })); } catch { /* Optional preference. */ }
    setOpen(false);
  };

  return (
    <Dialog open={open} onOpenChange={(value) => { if (!value) close(); }}>
      <DialogContent className="w-[calc(100%-2rem)] max-w-sm rounded-lg border-line bg-panel text-ink shadow-lift">
        <Star className="h-10 w-10 fill-brand/15 text-brand" aria-hidden="true" />
        <DialogHeader>
          <DialogTitle className="font-display leading-snug tracking-normal">Vous aimez GOMA WEBRADIO ?</DialogTitle>
          <DialogDescription className="leading-relaxed text-inkmute">Partagez votre avis sur Google Play et soutenez votre radio.</DialogDescription>
        </DialogHeader>
        <DialogFooter className="gap-2 sm:space-x-0">
          <Button variant="outline" onClick={() => close()}>Plus tard</Button>
          <Button asChild className="bg-brand text-primary-foreground hover:bg-brand/90">
            <a href={APP_STORE_URL} target="_blank" rel="noreferrer" onClick={() => close(true)}><Star />Donner mon avis</a>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}