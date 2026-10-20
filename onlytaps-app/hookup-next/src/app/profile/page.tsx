import { redirect, notFound } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { BadgeCheck, Briefcase, Calendar, Cigarette, Gem, GraduationCap, Heart, Languages, MapPin, MessageCircle, Pencil, Ruler, Wine, Crown } from 'lucide-react';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { getMembership } from '@/lib/membership';

interface P extends RowDataPacket {
  id: number; username: string; display_name: string | null; avatar: string | null; about_me: string | null;
  created_at: string; is_online: number | null; verified: number; show_online: number | null; is_demo: number;
}
interface L extends RowDataPacket { id: number; title: string; category_name: string | null; city_name: string | null; state_abbr: string | null; photo_url: string | null; is_featured: number }

export const metadata = { title: 'Profile · Turnpage' };

const pretty = (v: unknown) => String(v).replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
const list = (v: unknown): string[] => {
  try { const a = typeof v === 'string' ? JSON.parse(v) : v; return Array.isArray(a) ? a.map(String).filter(Boolean) : []; } catch { return []; }
};

export default async function Profile({ searchParams }: { searchParams: Promise<{ id?: string }> }) {
  const viewer = await getUser();
  const idRaw = (await searchParams).id;
  if (!idRaw) redirect(viewer ? `/profile.php?id=${viewer.id}` : '/login.php');
  const id = parseInt(idRaw, 10) || 0;

  const rows = await query<P>(
    `SELECT u.id, u.username, u.display_name, u.avatar, u.about_me, u.created_at, u.verified, u.is_demo,
            (u.last_seen IS NOT NULL AND u.last_seen > NOW() - INTERVAL 15 MINUTE) AS is_online,
            COALESCE(up.show_online_status, 1) AS show_online
     FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ? AND u.is_banned = 0 LIMIT 1`, [id]);
  const p = rows[0];
  if (!p) notFound();

  const [extra] = await query<RowDataPacket>('SELECT * FROM user_profiles WHERE user_id = ?', [id]);
  const membership = await getMembership(id);
  const listings = await query<L>(
    `SELECT l.id, l.title, l.photo_url, l.is_featured, c.name AS category_name, ct.name AS city_name, s.abbreviation AS state_abbr
     FROM listings l LEFT JOIN categories c ON l.category_id = c.id
     LEFT JOIN cities ct ON l.city_id = ct.id LEFT JOIN states s ON ct.state_id = s.id
     WHERE l.user_id = ? AND l.status = 'active' AND l.is_deleted = 0 ORDER BY l.is_featured DESC, l.created_at DESC LIMIT 12`, [id]);

  const own = viewer?.id === p.id;
  const name = p.display_name || p.username;
  const hue = (p.id * 47) % 360;
  const avatar = p.avatar && !/^[a-z]+:/i.test(p.avatar) ? '/' + p.avatar.replace(/^\/+/, '') : null;
  const showOnline = !!p.show_online;

  const details: [React.ReactNode, string, string][] = extra ? ([
    [<Ruler key="h" className="size-4" />, 'Height', extra.height ? `${extra.height} cm` : ''],
    [<Heart key="r" className="size-4" />, 'Relationship', extra.relationship_status ? pretty(extra.relationship_status) : ''],
    [<Briefcase key="o" className="size-4" />, 'Work', extra.occupation ?? ''],
    [<GraduationCap key="e" className="size-4" />, 'Education', extra.education ? pretty(extra.education) : ''],
    [<Cigarette key="s" className="size-4" />, 'Smoking', extra.smoking ? pretty(extra.smoking) : ''],
    [<Wine key="d" className="size-4" />, 'Drinking', extra.drinking ? pretty(extra.drinking) : ''],
  ] as [React.ReactNode, string, string][]).filter((d) => d[2]) : [];
  const interests = list(extra?.interests);
  const languages = list(extra?.languages);

  return (
    <div className="mx-auto max-w-4xl px-5 py-6">
      <section className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
        <div className="h-36 sm:h-44" style={{ backgroundImage: `linear-gradient(135deg, hsl(${hue} 70% 42%), hsl(${(hue + 70) % 360} 70% 28%))` }} />
        <div className="px-6 pb-6">
          <div className="-mt-14 flex flex-wrap items-end gap-4">
            <div className="grid size-28 shrink-0 place-items-center overflow-hidden rounded-full bg-surface-2 text-5xl font-extrabold text-white ring-4 ring-bg"
              style={avatar ? undefined : { backgroundImage: `linear-gradient(135deg, hsl(${hue} 80% 55%), hsl(${(hue + 50) % 360} 80% 45%))` }}>
              {avatar ? <img src={avatar} alt="" className="size-full object-cover" /> : name.charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1 pb-1">
              <h1 className="flex flex-wrap items-center gap-2 text-2xl font-extrabold sm:text-3xl">
                <span className="truncate">{name}</span>
                {(p.verified || membership.paid) && <span title="Verified"><BadgeCheck className="size-6 text-sky-400" /></span>}
                {membership.paid && (
                  <span className="inline-flex items-center gap-1 rounded-full bg-violet-500/30 px-2.5 py-0.5 text-xs font-bold uppercase text-violet-100">
                    {membership.plan.slug === 'vip' ? <Crown className="size-3.5 text-amber-300" /> : <Gem className="size-3.5" />}{membership.plan.name}
                  </span>
                )}
              </h1>
              <p className="text-sm text-white/60">
                @{p.username}
                {showOnline && <> · <span className={p.is_online ? 'font-semibold text-green-400' : ''}>{p.is_online ? '● Online now' : 'Offline'}</span></>}
              </p>
            </div>
            <div className="flex gap-2 pb-1">
              {own ? (
                <a className="btn btn-secondary" href="/edit-profile.php"><Pencil className="size-4" />Edit profile</a>
              ) : p.is_demo ? (
                <span className="rounded-full bg-sky-400/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-sky-300">Sample profile · fictional</span>
              ) : viewer ? (
                <a className="btn btn-primary" href={`/messages.php?with=${p.id}`}><MessageCircle className="size-4" />Message</a>
              ) : (
                <a className="btn btn-primary" href="/login.php">Log in to message</a>
              )}
            </div>
          </div>

          <dl className="mt-6 grid grid-cols-3 gap-3 text-center">
            {[
              ['Active ads', String(listings.length)],
              ['Member since', new Date(p.created_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric', timeZone: 'UTC' })],
              ['Plan', membership.plan.name],
            ].map(([k, v]) => (
              <div key={k} className="rounded-2xl border border-white/10 bg-black/20 px-2 py-3">
                <dd className="text-lg font-extrabold">{v}</dd>
                <dt className="text-[11px] uppercase tracking-wider text-white/45">{k}</dt>
              </div>
            ))}
          </dl>
        </div>
      </section>

      {(p.about_me || interests.length > 0) && (
        <section className="mt-5 rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 className="mb-2 text-lg font-bold">About</h2>
          {p.about_me && <p className="whitespace-pre-wrap break-words leading-7 text-white/85">{p.about_me}</p>}
          {interests.length > 0 && (
            <div className="mt-4 flex flex-wrap gap-2">
              {interests.map((i) => <span key={i} className="rounded-full border border-violet-400/30 bg-violet-500/15 px-3 py-1 text-sm text-violet-100">{i}</span>)}
            </div>
          )}
        </section>
      )}

      {(details.length > 0 || languages.length > 0) && (
        <section className="mt-5 rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 className="mb-3 text-lg font-bold">Details</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            {details.map(([icon, k, v]) => (
              <div key={k} className="flex items-center gap-3 rounded-xl bg-black/20 px-3.5 py-2.5">
                <span className="text-violet-300">{icon}</span>
                <span className="text-sm text-white/50">{k}</span>
                <span className="ml-auto text-sm font-medium">{v}</span>
              </div>
            ))}
            {languages.length > 0 && (
              <div className="flex items-center gap-3 rounded-xl bg-black/20 px-3.5 py-2.5">
                <span className="text-violet-300"><Languages className="size-4" /></span>
                <span className="text-sm text-white/50">Languages</span>
                <span className="ml-auto text-sm font-medium">{languages.join(', ')}</span>
              </div>
            )}
          </div>
        </section>
      )}

      <section className="mt-5 rounded-3xl border border-white/10 bg-white/5 p-6">
        <h2 className="mb-4 flex items-center gap-2 text-lg font-bold"><Calendar className="size-5 text-violet-300" />Active ads ({listings.length})</h2>
        <div className="listing-grid">
          {listings.map((l) => (
            <a key={l.id} className="ad-card" href={`/listing.php?id=${l.id}`}>
              <div className="ad-thumb" style={l.photo_url ? { backgroundImage: `url(${JSON.stringify(l.photo_url)})` } : undefined}>
                {l.is_featured ? <span className="tag-featured">★ Featured</span> : null}
              </div>
              <div className="ad-body"><h3>{l.title}</h3>
                <div className="ad-meta">
                  <span>{l.category_name}</span>
                  <span className="inline-flex items-center gap-1"><MapPin className="size-3" />{l.city_name}{l.state_abbr ? `, ${l.state_abbr}` : ''}</span>
                </div>
              </div>
            </a>
          ))}
          {listings.length === 0 && <p className="muted">No active ads.</p>}
        </div>
      </section>
    </div>
  );
}
