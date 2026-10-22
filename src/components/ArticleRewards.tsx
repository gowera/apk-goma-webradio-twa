import { useEffect } from 'react';
import { useRewards } from '@/context/rewards-context';
import { rewardsRequest, refreshRewards } from '@/lib/rewards-api';

export function ArticleRewards({ postId }: { postId: number }) {
  const { user } = useRewards();
  useEffect(() => {
    if (!user) return;
    let disposed = false;
    let visibleSeconds = 0;
    let token: string | null = null;
    let sent = false;
    void rewardsRequest<{ token: string }>('read', { action: 'start', post_id: postId }).then(r => { token = r.token; }).catch(() => {});
    const timer = window.setInterval(() => {
      if (document.visibilityState !== 'visible' || !token || disposed || sent) return;
      visibleSeconds += 1;
      if (visibleSeconds < 30) return;
      sent = true;
      void rewardsRequest<{ awarded: number }>('read', { action: 'finish', post_id: postId, token }).then(r => {
        if (r.awarded > 0) { refreshRewards(); window.dispatchEvent(new CustomEvent('gwr-notice', { detail: `+${r.awarded} points pour cet article` })); }
      }).catch(() => { sent = false; });
    }, 1000);
    return () => { disposed = true; window.clearInterval(timer); };
  }, [postId, user?.id]);
  return null;
}
