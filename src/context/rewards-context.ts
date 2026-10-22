import { createContext, useContext } from 'react';
import type { RewardRules, RewardUser } from '@/lib/rewards-api';
export type RewardsState = { user: RewardUser | null; rules: RewardRules | null; loading: boolean; error: string; refresh: () => Promise<void>; logout: () => Promise<void>; seconds: number };
export const RewardsContext = createContext<RewardsState | null>(null);
export function useRewards() { const value = useContext(RewardsContext); if (!value) throw new Error('Rewards provider missing'); return value; }
