import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';

interface U extends RowDataPacket {
  id: number; username: string; display_name: string | null; avatar: string | null;
  verified: number; is_online: number; is_premium: number | null;
}

export const metadata = { title: 'Marketplace · Turnpage' };

function Card({ u }: { u: U }) {
  const name = u.display_name || u.username;
  const avatar = u.avatar && !/^[a-z]+:/i.test(u.avatar) ? '/' + u.avatar.replace(/^\/+/, '') : null;
  return (
    <a className="member-card" href={`/profile.php?id=${u.id}`}>
      <div className="avatar avatar-lg" style={{ ['--h' as string]: (u.id * 47) % 360 }}>
        {avatar ? <img src={avatar} alt="" /> : name.charAt(0).toUpperCase()}
      </div>
      <strong>{name} {u.verified || u.is_premium ? '✔️' : ''}{u.is_premium ? '💎' : ''}</strong>
      <span className={'muted' + (u.is_online ? ' online' : '')}>{u.is_online ? '● Online' : `@${u.username}`}</span>
    </a>
  );
}

export default async function Marketplace({ searchParams }: { searchParams: Promise<{ q?: string }> }) {
  const q = ((await searchParams).q ?? '').trim().slice(0, 60);
  const like = `%${q}%`;
  const where = `is_banned = 0 AND is_suspended = 0 ${q ? 'AND (username LIKE ? OR display_name LIKE ?)' : ''}`;
  const p = q ? [like, like] : [];
  const cols = `id, username, display_name, avatar, verified, (is_premium = 1 AND premium_expires > NOW()) AS is_premium,
                (last_seen IS NOT NULL AND last_seen > NOW() - INTERVAL 15 MINUTE) AS is_online`;
  const featured = await query<U>(`SELECT ${cols} FROM users WHERE ${where} AND verified = 1 ORDER BY last_seen DESC LIMIT 12`, p);
  const members = await query<U>(`SELECT ${cols} FROM users WHERE ${where} AND verified = 0 ORDER BY last_seen DESC LIMIT 100`, p);

  return (
    <div className="page">
      <header className="city-head">
        <h1 className="page-title">🛍️ Marketplace</h1>
        <form className="search-row" action="/marketplace.php" method="get">
          <input type="search" name="q" defaultValue={q} placeholder="Search members" autoComplete="off" />
          <button className="btn btn-primary" type="submit">Search</button>
        </form>
      </header>

      {featured.length > 0 && (
        <section className="card-block">
          <h2>⭐ Verified members</h2>
          <div className="member-grid">{featured.map((u) => <Card key={u.id} u={u} />)}</div>
        </section>
      )}
      <section className="card-block">
        <h2>Members</h2>
        {members.length === 0 && featured.length === 0
          ? <p className="muted">No members found.</p>
          : <div className="member-grid">{members.map((u) => <Card key={u.id} u={u} />)}</div>}
      </section>
    </div>
  );
}
