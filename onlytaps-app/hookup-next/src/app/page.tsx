import type { RowDataPacket } from 'mysql2';
import { ArrowRight, BadgeCheck, Gem, Lock, MapPin, MessageCircle, Search, ShieldCheck, Sparkles, Star, Zap } from 'lucide-react';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';

export const metadata = { title: 'Turnpage · Local classifieds, real conversations' };

interface City extends RowDataPacket { name: string; state_abbr: string | null; n: number }
interface Ad extends RowDataPacket { id: number; title: string; photo_url: string | null; is_featured: number; city: string; category: string | null; is_demo: number | null }

const steps = [
  { icon: MapPin, title: 'Pick your city', text: 'Browse fresh ads near you — no clutter, no endless scrolling.' },
  { icon: MessageCircle, title: 'Chat instantly', text: 'Real-time messaging with read receipts, built for fast replies.' },
  { icon: BadgeCheck, title: 'Meet with confidence', text: 'Verified members, reporting tools and moderators keeping it clean.' },
];

const trust = [
  { icon: ShieldCheck, title: 'Moderated & reported', text: 'Every ad can be reported and is reviewed by our team.' },
  { icon: Lock, title: 'Private by design', text: 'Encrypted connections and secure sessions on every page.' },
  { icon: Zap, title: 'Real-time', text: 'WebSocket messaging — replies show up the moment they’re sent.' },
];

export default async function Landing() {
  const user = await getUser();
  const [[stats], cities, ads] = await Promise.all([
    query<RowDataPacket>(
      `SELECT (SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE l.status = 'active' AND l.is_deleted = 0 AND u.is_demo = 0) AS ads,
              (SELECT COUNT(*) FROM users WHERE is_banned = 0 AND is_demo = 0) AS members,
              (SELECT COUNT(*) FROM cities WHERE is_active = 1) AS cities`),
    query<City>(
      `SELECT c.name, s.abbreviation AS state_abbr,
              (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active' AND is_deleted = 0) AS n
       FROM cities c LEFT JOIN states s ON s.id = c.state_id WHERE c.is_active = 1 ORDER BY n DESC, c.name LIMIT 8`),
    query<Ad>(
      `SELECT l.id, l.title, l.photo_url, l.is_featured, c.name AS city, cat.name AS category, u.is_demo
       FROM listings l JOIN cities c ON c.id = l.city_id LEFT JOIN categories cat ON cat.id = l.category_id LEFT JOIN users u ON u.id = l.user_id
       WHERE l.status = 'active' AND l.is_deleted = 0 ORDER BY l.is_featured DESC, l.created_at DESC LIMIT 6`),
  ]);

  const fmt = (n: unknown) => Number(n).toLocaleString('en-US');

  return (
    <div className="mx-auto max-w-6xl px-5">
      <section className="relative mt-6 overflow-hidden rounded-[2rem] border border-white/10 px-6 py-16 text-center sm:py-24"
        style={{ backgroundImage: 'radial-gradient(70% 90% at 50% 0%, rgb(139 92 246 / 0.35), transparent 70%), radial-gradient(40% 60% at 90% 100%, rgb(217 70 239 / 0.2), transparent 70%)' }}>
        <span className="hero-eyebrow"><Sparkles className="mr-1 inline size-3.5" />Local classifieds, reimagined</span>
        <h1 className="mx-auto max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">
          Find people and deals <span className="hero-accent">right where you are</span>
        </h1>
        <p className="mx-auto mt-5 max-w-xl text-lg text-white/70">
          Browse local ads, save your favorites and chat in real time — all in one fast, private place.
        </p>

        <form action="/choose-location.php" method="get" className="mx-auto mt-8 flex max-w-xl items-center gap-2 rounded-2xl border border-white/15 bg-black/30 p-2 backdrop-blur">
          <Search className="ml-3 size-5 shrink-0 text-white/50" />
          <input name="q" type="search" placeholder="Search your city or state" autoComplete="off"
            className="!border-0 !bg-transparent !px-2 !shadow-none !outline-none" />
          <button className="btn btn-primary shrink-0" type="submit">Search</button>
        </form>

        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          {user ? (
            <>
              <a href="/create-listing.php" className="btn btn-primary">Post an ad <ArrowRight className="size-4" /></a>
              <a href="/messages.php" className="btn btn-secondary">Open messages</a>
            </>
          ) : (
            <>
              <a href="/register-tos.php" className="btn btn-primary">Create free account <ArrowRight className="size-4" /></a>
              <a href="/login.php" className="btn btn-secondary">Log in</a>
            </>
          )}
        </div>

        <dl className="mx-auto mt-12 grid max-w-2xl grid-cols-3 gap-4">
          {[['Active ads', stats.ads], ['Members', stats.members], ['Cities', stats.cities]].map(([k, v]) => (
            <div key={k as string} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 backdrop-blur">
              <dd className="text-2xl font-extrabold sm:text-3xl">{fmt(v)}</dd>
              <dt className="text-xs uppercase tracking-wider text-white/50">{k as string}</dt>
            </div>
          ))}
        </dl>
      </section>

      <section className="mt-16">
        <div className="mb-5 flex items-end justify-between">
          <h2 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Popular cities</h2>
          <a href="/choose-location.php" className="inline-flex items-center gap-1 text-sm">All locations <ArrowRight className="size-4" /></a>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {cities.map((c) => (
            <a key={c.name} href={`/city.php?location=${encodeURIComponent(c.name)}`} className="city-tile">
              <MapPin className="mb-1 size-4 text-brand-400" />
              <strong>{c.name}</strong>
              <span>{c.state_abbr}</span>
              <em>{c.n} ads</em>
            </a>
          ))}
        </div>
      </section>

      {ads.length > 0 && (
        <section className="mt-16">
          <h2 className="mb-5 text-2xl font-extrabold tracking-tight sm:text-3xl">Fresh &amp; featured ads</h2>
          <div className="listing-grid">
            {ads.map((l) => (
              <a key={l.id} className="ad-card" href={`/listing.php?id=${l.id}`}>
                <div className="ad-thumb" style={l.photo_url ? { backgroundImage: `url(${JSON.stringify(l.photo_url)})` } : undefined}>
                  {l.is_featured ? <span className="tag-featured"><Star className="mr-1 inline size-3" />Featured</span> : null}
                  {l.is_demo ? <span className="tag-sample">Sample</span> : null}
                </div>
                <div className="ad-body">
                  <h3>{l.title}</h3>
                  <div className="ad-meta"><span>{l.category}</span><span>{l.city}</span></div>
                </div>
              </a>
            ))}
          </div>
        </section>
      )}

      <section className="mt-16">
        <h2 className="mb-6 text-center text-2xl font-extrabold tracking-tight sm:text-3xl">How it works</h2>
        <div className="grid gap-4 md:grid-cols-3">
          {steps.map((s, i) => (
            <div key={s.title} className="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur">
              <div className="mb-4 flex items-center gap-3">
                <span className="grid size-11 place-items-center rounded-xl text-white" style={{ backgroundImage: 'var(--grad)' }}><s.icon className="size-5" /></span>
                <span className="text-sm font-bold text-white/40">0{i + 1}</span>
              </div>
              <h3 className="text-lg font-bold">{s.title}</h3>
              <p className="mt-1 text-white/65">{s.text}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="mt-16 grid gap-4 md:grid-cols-3">
        {trust.map((t) => (
          <div key={t.title} className="flex gap-4 rounded-2xl border border-white/10 p-5">
            <t.icon className="mt-0.5 size-6 shrink-0 text-violet-300" />
            <div><h3 className="font-bold">{t.title}</h3><p className="text-sm text-white/65">{t.text}</p></div>
          </div>
        ))}
      </section>

      <section className="relative mt-16 overflow-hidden rounded-[2rem] border border-violet-400/30 px-6 py-12 text-center"
        style={{ backgroundImage: 'linear-gradient(135deg, rgb(124 58 237 / 0.35), rgb(217 70 239 / 0.2))' }}>
        <Gem className="mx-auto mb-3 size-8 text-violet-200" />
        <h2 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Get seen first with Plus &amp; VIP</h2>
        <p className="mx-auto mt-2 max-w-lg text-white/75">More active ads, featured placement and a verified member badge — from $9.99/month.</p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <a href="/membership.php" className="btn btn-primary">See plans <ArrowRight className="size-4" /></a>
          {!user && <a href="/register-tos.php" className="btn btn-secondary">Sign up free</a>}
        </div>
      </section>
    </div>
  );
}
