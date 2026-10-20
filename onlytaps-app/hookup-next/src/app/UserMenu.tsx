'use client';

import { Heart, LogIn, LogOut, MapPin, MessageCircle, Settings, ShieldCheck, Store, User, UserPlus, ClipboardList, Bot, Gem, ChevronDown, Menu } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const item = 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink/90 transition hover:bg-white/10 hover:text-white';

function useMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);
  return { open, setOpen, ref };
}

export function GuestMenu() {
  const { open, setOpen, ref } = useMenu();
  return (
    <div ref={ref} className="relative">
      <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-sm font-medium transition hover:bg-white/10">
        <Menu className="size-4" aria-hidden /> Menu
      </button>
      {open && (
        <div role="menu" className="menu-pop absolute right-0 z-50 mt-2 w-56 rounded-xl border border-white/10 bg-[#14102a]/95 p-1.5 shadow-2xl backdrop-blur-xl">
          <a role="menuitem" href="/login.php" className={item}><LogIn className="size-4 opacity-70" /> Log in</a>
          <a role="menuitem" href="/register-tos.php" className={item}><UserPlus className="size-4 opacity-70" /> Sign up</a>
          <div className="my-1 border-t border-white/10" />
          <a role="menuitem" href="/choose-location.php" className={item}><MapPin className="size-4 opacity-70" /> Locations</a>
          <a role="menuitem" href="/marketplace.php" className={item}><Store className="size-4 opacity-70" /> Marketplace</a>
          <a role="menuitem" href="/ai-profiles" className={item}><Bot className="size-4 opacity-70" /> AI Profiles</a>
          <a role="menuitem" href="/membership.php" className={item}><Gem className="size-4 opacity-70" /> Membership</a>
        </div>
      )}
    </div>
  );
}

export default function UserMenu({ id, username, isAdmin, plan }: { id: number; username: string; isAdmin: boolean; plan: string }) {
  const { open, setOpen, ref } = useMenu();

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 py-1.5 pl-1.5 pr-3 text-sm font-medium transition hover:bg-white/10"
      >
        <span className="grid size-7 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-xs font-bold text-white">
          {username.slice(0, 1).toUpperCase()}
        </span>
        <span className="max-w-24 truncate">{username}</span>
        {plan !== 'free' && <span className="rounded-full bg-violet-500/30 px-1.5 text-[10px] font-bold uppercase text-violet-100">{plan}</span>}
        <ChevronDown aria-hidden className={'size-3.5 transition ' + (open ? 'rotate-180' : '')} />
      </button>
      {open && (
        <div role="menu" className="menu-pop absolute right-0 z-50 mt-2 w-56 rounded-xl border border-white/10 bg-[#14102a]/95 p-1.5 shadow-2xl backdrop-blur-xl">
          <a role="menuitem" href={`/profile.php?id=${id}`} className={item}><User className="size-4 opacity-70" /> My profile</a>
          <a role="menuitem" href="/my-listings.php" className={item}><ClipboardList className="size-4 opacity-70" /> My Ads</a>
          <a role="menuitem" href="/favorites.php" className={item}><Heart className="size-4 opacity-70" /> Favorites</a>
          <a role="menuitem" href="/messages.php" className={item}><MessageCircle className="size-4 opacity-70" /> Messages</a>
          <a role="menuitem" href="/ai-profiles" className={item}><Bot className="size-4 opacity-70" /> AI Profiles</a>
          <a role="menuitem" href="/membership.php" className={item}><Gem className="size-4 opacity-70" /> Membership</a>
          <a role="menuitem" href="/edit-profile.php" className={item}><Settings className="size-4 opacity-70" /> Edit profile</a>
          {isAdmin && <a role="menuitem" href="/admin/dashboard.php" className={item}><ShieldCheck className="size-4 opacity-70" /> Admin</a>}
          <div className="my-1 border-t border-white/10" />
          <a role="menuitem" href="/logout.php" className={item + ' text-rose-300'}><LogOut className="size-4" /> Log out</a>
        </div>
      )}
    </div>
  );
}
