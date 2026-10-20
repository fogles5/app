import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { listingAction } from '../actions';

const PER = 50;

export default async function Listings({ searchParams }: { searchParams: Promise<{ q?: string; page?: string; show?: string }> }) {
  const sp = await searchParams;
  const q = (sp.q ?? '').trim().slice(0, 80);
  const removed = sp.show === 'removed';
  const page = Math.max(1, parseInt(sp.page ?? '1', 10) || 1);
  const where = `WHERE l.is_deleted = ? ${q ? 'AND (l.title LIKE ? OR u.username LIKE ?)' : ''}`;
  const p: unknown[] = [removed ? 1 : 0, ...(q ? [`%${q}%`, `%${q}%`] : [])];
  const from = `FROM listings l LEFT JOIN users u ON l.user_id = u.id LEFT JOIN cities ct ON l.city_id = ct.id ${where}`;
  const [{ n }] = await query<RowDataPacket>(`SELECT COUNT(*) AS n ${from}`, p);
  const rows = await query<RowDataPacket>(
    `SELECT l.id, l.title, l.created_at, l.views, u.username, ct.name AS city ${from} ORDER BY l.id DESC LIMIT ? OFFSET ?`,
    [...p, PER, (page - 1) * PER]);
  const pages = Math.max(1, Math.ceil(Number(n) / PER));
  const href = (pg: number) => `/admin/listings.php?${new URLSearchParams({ ...(q && { q }), ...(removed && { show: 'removed' }), page: String(pg) })}`;

  return (
    <>
      <h1 className="page-title">Listings <span className="muted">({Number(n)})</span></h1>
      <div className="chips">
        <a className={'chip' + (!removed ? ' on' : '')} href="/admin/listings.php">Active</a>
        <a className={'chip' + (removed ? ' on' : '')} href="/admin/listings.php?show=removed">Removed</a>
      </div>
      <form className="search-row" action="/admin/listings.php" method="get">
        {removed && <input type="hidden" name="show" value="removed" />}
        <input type="search" name="q" defaultValue={q} placeholder="Title or username" />
        <button className="btn btn-primary" type="submit">Search</button>
      </form>
      <div className="table-wrap">
        <table className="admin-table">
          <thead><tr><th>ID</th><th>Title</th><th>User</th><th>City</th><th>Views</th><th></th></tr></thead>
          <tbody>
            {rows.map((l) => (
              <tr key={l.id}>
                <td>{l.id}</td>
                <td><a href={`/listing.php?id=${l.id}`}>{l.title}</a></td>
                <td>{l.username}</td><td>{l.city}</td><td>{Number(l.views)}</td>
                <td>
                  <form action={listingAction}>
                    <input type="hidden" name="id" value={l.id} />
                    <input type="hidden" name="op" value={removed ? 'restore' : 'remove'} />
                    <button className="btn btn-secondary btn-sm" type="submit">{removed ? 'Restore' : 'Remove'}</button>
                  </form>
                </td>
              </tr>
            ))}
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
