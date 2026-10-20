import type { Metadata, Viewport } from 'next';
import Script from 'next/script';
import { headers } from 'next/headers';
import UserMenu, { GuestMenu } from './UserMenu';
import { MapPin, Store, PlusCircle, MessageCircle, User, ClipboardList } from 'lucide-react';
import { getUser } from '@/lib/auth';
import './globals.css';

export const metadata: Metadata = { title: 'Turnpage - Local Classifieds' };
export const viewport: Viewport = { themeColor: '#0a0912' };
export const dynamic = 'force-dynamic';

const navLink = 'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-white/10 hover:text-white';
const ic = 'size-4';
const badge = 'ml-1 inline-grid min-w-5 place-items-center rounded-full bg-accent px-1.5 text-xs font-bold text-white';
const bottomItem = 'relative flex flex-1 flex-col items-center gap-0.5 py-2 text-[0.7rem] text-muted hover:text-white';

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const path = (await headers()).get('x-pathname');
  if (path === '/age-gate') {
    return (
      <html lang="en">
        <head><link rel="icon" href="/assets/images/favicon.png" /></head>
        <body>{children}</body>
      </html>
    );
  }
  const user = await getUser();
  const unread = user?.unread ?? 0;
  const onMessages = path === '/messages' || path === '/messages.php';
  return (
    <html lang="en">
      <head>
        <link rel="icon" href="/assets/images/favicon.png" />
      </head>
      <body className={user ? 'has-bottom-nav' : ''}>
        <header className="sticky top-0 z-40 border-b border-line bg-bg/80 backdrop-blur-xl">
          <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5">
            <a href="/" className="flex items-center gap-2 text-lg font-extrabold tracking-tight text-white hover:text-white">
              <span className="grid size-8 place-items-center rounded-lg text-base" style={{ backgroundImage: 'var(--grad)' }}><ClipboardList className="size-4.5" /></span>
              Turnpage
            </a>
            <div className="flex items-center gap-1 overflow-x-auto">
              <a href="/choose-location.php" className={navLink}><MapPin className={ic} /> Locations</a>
              <a href="/marketplace.php" className={navLink + ' hidden sm:inline-flex'}><Store className={ic} /> Marketplace</a>
              {user ? (
                <>
                  <a href="/create-listing.php" className={navLink + ' hidden md:inline-flex'}><PlusCircle className={ic} /> Post Ad</a>
                  <a href="/messages.php" className={navLink + ' hidden md:inline-flex'}>
                    <MessageCircle className={ic} /> Messages <span className={badge} data-unread-badge hidden={unread <= 0}>{unread}</span>
                  </a>
                  <UserMenu id={user.id} username={user.username} isAdmin={user.isAdmin} plan={user.plan} />
                </>
              ) : (
                <>
                  <GuestMenu />
                  <a href="/register-tos.php" className="btn btn-primary btn-sm ml-1">Sign up</a>
                </>
              )}
            </div>
          </nav>
        </header>

        <main>{children}</main>

        <footer className="mt-10 border-t border-line py-6 text-center text-sm text-muted">
          <p>
            &copy; {new Date().getFullYear()} Turnpage. All rights reserved. ·{' '}
            <a href="/terms.php">Terms</a> · <a href="/privacy.php">Privacy</a> · <a href="/safety.php">Safety</a> ·{' '}
            <a href="/report-abuse.php">Report Abuse</a> · <a href="/contact.php">Contact</a>
          </p>
        </footer>

        {user && (
          <>
            <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-bg/90 backdrop-blur-xl md:hidden">
              <div className="mx-auto flex max-w-lg">
                <a href="/choose-location.php" className={bottomItem}><MapPin className="size-5.5" />Locations</a>
                <a href="/create-listing.php" className={bottomItem}><PlusCircle className="size-5.5" />Post</a>
                <a href="/messages.php" className={bottomItem}>
                  <MessageCircle className="size-5.5" />Messages
                  <span className="absolute right-[22%] top-1 grid min-w-4 place-items-center rounded-full bg-accent px-1 text-[0.65rem] font-bold text-white" data-unread-badge hidden={unread <= 0}>{unread}</span>
                </a>
                <a href={`/profile.php?id=${user.id}`} className={bottomItem}><User className="size-5.5" />Profile</a>
              </div>
            </nav>
            <div id="toast-host" className="toast-host" aria-live="polite" />
            <Script id="rt-init" strategy="beforeInteractive">{`window.__UNREAD__=${unread};window.__ON_MESSAGES__=${onMessages};`}</Script>
            <Script src="/assets/js/realtime.js?v=2" strategy="afterInteractive" />
          </>
        )}
      </body>
    </html>
  );
}
