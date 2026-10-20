import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';

export default async function Dashboard() {
  const [s] = await query<RowDataPacket>(
    `SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL 7 DAY) AS new_users,
            (SELECT COUNT(*) FROM listings WHERE is_deleted = 0 AND status = 'active') AS listings,
            (SELECT COUNT(*) FROM listings WHERE is_deleted = 0 AND created_at > NOW() - INTERVAL 7 DAY) AS new_listings,
            (SELECT COUNT(*) FROM reports WHERE status = 'pending') AS reports,
            (SELECT COUNT(*) FROM messages WHERE created_at > NOW() - INTERVAL 1 DAY) AS messages,
            (SELECT COUNT(*) FROM users WHERE is_banned = 1) AS banned`);
  const recent = await query<RowDataPacket>('SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT 8');
  const stats: [string, number, string?][] = [
    ['Users', s.users], ['New users (7d)', s.new_users], ['Active listings', s.listings],
    ['New listings (7d)', s.new_listings], ['Pending reports', s.reports, '/admin/reports.php'],
    ['Messages (24h)', s.messages], ['Banned users', s.banned],
  ];
  return (
    <>
      <h1 className="page-title">Admin dashboard</h1>
      <div className="stat-grid">
        {stats.map(([label, n, href]) => {
          const inner = <><strong>{Number(n)}</strong><span className="muted">{label}</span></>;
          return href ? <a key={label} className="stat" href={href}>{inner}</a> : <div key={label} className="stat">{inner}</div>;
        })}
      </div>
      <section className="card-block">
        <h2>Newest members</h2>
        <ul className="plain">
          {recent.map((u) => (
            <li key={u.id}><a href={`/admin/users.php?q=${encodeURIComponent(u.username)}`}>{u.username}</a>{' '}
              <span className="muted">{new Date(u.created_at).toLocaleString('en-US', { timeZone: 'UTC' })} UTC</span></li>
          ))}
        </ul>
      </section>
    </>
  );
}
