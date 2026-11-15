import { SmartImage } from '@/components/SmartImage';
export function ListenerAvatar({ name, avatar, className = 'h-11 w-11 text-lg' }: { name: string; avatar?: string; className?: string }) {
  return <span className={`flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-panel2 font-display font-extrabold text-brand ${className}`}>
    {avatar ? <SmartImage src={avatar} alt={name} className="h-full w-full object-cover" /> : name.trim().slice(0, 1).toUpperCase()}
  </span>;
}