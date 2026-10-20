import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { userAction } from '../actions';

const PER = 50;

function Op({ id, op, label }: { id: number; op: string; label: string }) {
  return (
    <form action={userAction}>
      <input type="hidden" name="id" value={id} />
      <input type="hidden" name="op" value={op} />
      <button className="btn btn-secondary btn-sm" type="submit">{label}</button>
    </form>
  );
}

export default async function Users({ searchParams }: { searchParams: Promise<{ q?: string; page?: string }> }) {
  const sp = await searchParams;
  const q = (sp.q ?? '').trim().slice(0, 80);
  const page = Math.max(1, parseInt(sp.page ?? '1', 10) || 1);
  const where = q ? 'WHERE username LIKE ? OR email LIKE ?' : '';
  const p = q ? [`%${q}%`, `%${q}%`] : [];
  const [{ n }] = await query<RowDataPacket>(`SELECT COUNT(*) AS n FROM users ${where}`, p);
  const rows = await query<RowDataPacket>(
    `SELECT id, username, email, created_at, last_seen, verified, is_banned, is_suspended, is_admin, role, email_verified, (is_premium = 1 AND premium_expires > NOW()) AS is_premium
     FROM users ${where} ORDER BY id DESC LIMIT ? OFFSET ?`, [...p, PER, (page - 1) * PER]);
  const pages = Math.max(1, Math.ceil(Number(n) / PER));
  const href = (pg: number) => `/admin/users.php?${new URLSearchParams({ ...(q && { q }), page: String(pg) })}`;

  return (
    <>
      <h1 className="page-title">Users <span className="muted">({Number(n)})</span></h1>
      <form className="search-row" action="/admin/users.php" method="get">
        <input type="search" name="q" defaultValue={q} placeholder="Username or email" />
        <button className="btn btn-primary" type="submit">Search</button>
      </form>
      <div className="table-wrap">
        <table className="admin-table">
          <thead><tr><th>ID</th><th>User</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
          <tbody>
            {rows.map((u) => {
              const admin = !!u.is_admin || u.role === 'admin';
              return (
                <tr key={u.id}>
                  <td>{u.id}</td>
                  <td><a href={`/profile.php?id=${u.id}`}>{u.username}</a><br /><span className="muted">{u.email}</span></td>
                  <td>
                    {admin && <span className="chip on">admin</span>}{u.verified ? ' ✔️' : ''}{u.is_premium ? ' 💎 member' : ''}
                    {u.is_banned ? ' 🚫 banned' : ''}{u.is_suspended ? ' ⏸ suspended' : ''}{!u.email_verified ? ' ✉️ unverified' : ''}
                  </td>
                  <td className="muted">{new Date(u.created_at).toLocaleDateString('en-US', { timeZone: 'UTC' })}</td>
                  <td className="row">
                    {admin ? <span className="muted">—</span> : <>
                      {u.is_banned ? <Op id={u.id} op="unban" label="Unban" /> : <Op id={u.id} op="ban" label="Ban" />}
                      {u.is_suspended ? <Op id={u.id} op="unsuspend" label="Unsuspend" /> : <Op id={u.id} op="suspend" label="Suspend 7d" />}
                      {u.verified ? <Op id={u.id} op="unverify" label="Unverify" /> : <Op id={u.id} op="verify" label="Verify" />}
                      <Op id={u.id} op="grant_plus" label="Plus 30d" />
                      <Op id={u.id} op="grant_vip" label="VIP 30d" />
                      {u.is_premium ? <Op id={u.id} op="revoke_plan" label="Revoke plan" /> : null}
                    </>}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="pager">
        {page > 1 && <a className="btn btn-secondary" href={href(page - 1)}>‹ Prev</a>}
        <span className="muted">Page {page} of {pages}</span>
        {page < pages && <a className="btn btn-secondary" href={href(page + 1)}>Next ›</a>}
      </div>
    </>
  );
}
