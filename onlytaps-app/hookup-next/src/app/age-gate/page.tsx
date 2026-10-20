import { headers } from 'next/headers';
import { ShieldAlert } from 'lucide-react';

export const metadata = { title: 'Age verification · Turnpage', robots: { index: false } };
export const dynamic = 'force-dynamic';

export default async function AgeGate() {
  // nginx passes the URL the visitor originally asked for; /age-verify re-validates it.
  const orig = (await headers()).get('x-original-uri') ?? '/';
  const next = /^\/(?!\/)[^\\\r\n]*$/.test(orig) ? orig : '/';

  return (
    <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-bg/95 p-5 backdrop-blur-xl">
      <div className="w-full max-w-md rounded-3xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl">
        <span className="mx-auto mb-5 grid size-16 place-items-center rounded-2xl text-white" style={{ backgroundImage: 'var(--grad)' }}>
          <ShieldAlert className="size-8" />
        </span>
        <h1 className="text-3xl font-extrabold tracking-tight">Are you 18 or older?</h1>
        <p className="mt-3 text-white/70">
          Turnpage contains adult content and is only for people who are at least 18 years old (or the age of majority where you live).
        </p>
        <form action="/age-verify" method="post" className="mt-7 flex flex-col gap-3">
          <input type="hidden" name="next" value={next} />
          <button type="submit" className="btn btn-primary w-full py-3.5">Yes, I’m 18 or older — enter</button>
          <a href="https://www.google.com" rel="noopener noreferrer" className="btn btn-secondary w-full py-3">No, take me out of here</a>
        </form>
        <p className="mt-5 text-xs text-white/45">
          By entering you confirm you are of legal age and agree to our <a href="/terms.php">Terms</a> and <a href="/privacy.php">Privacy Policy</a>.
        </p>
      </div>
    </div>
  );
}
