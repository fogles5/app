import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { reportAction } from '../actions';

export default async function Reports({ searchParams }: { searchParams: Promise<{ show?: string }> }) {
  const all = (await searchParams).show === 'all';
  const rows = await query<RowDataPacket>(
    `SELECT r.id, r.reason, r.status, r.created_at, r.listing_id, l.title, l.is_deleted, u.username AS reporter
     FROM reports r LEFT JOIN listings l ON r.listing_id = l.id LEFT JOIN users u ON r.reporter_id = u.id
     ${all ? '' : "WHERE r.status = 'pending'"} ORDER BY r.id DESC LIMIT 100`);

  return (
    <>
      <h1 className="page-title">Reports</h1>
      <div className="chips">
        <a className={'chip' + (!all ? ' on' : '')} href="/admin/reports.php">Pending</a>
        <a className={'chip' + (all ? ' on' : '')} href="/admin/reports.php?show=all">All</a>
      </div>
      {rows.length === 0 && <p className="muted">No reports.</p>}
      <div className="manage-list">
        {rows.map((r) => (
          <div key={r.id} className="manage-row">
            <div className="manage-info">
              <a href={`/listing.php?id=${r.listing_id}`}><strong>{r.title ?? `Listing #${r.listing_id}`}</strong></a>
              <span>{r.reason}</span>
              <span className="muted">
                by {r.reporter ?? 'unknown'} · {new Date(r.created_at).toLocaleString('en-US', { timeZone: 'UTC' })} UTC · {r.status}
                {r.is_deleted ? ' · listing removed' : ''}
              </span>
            </div>
            {r.status === 'pending' && (
              <div className="row">
                <form action={reportAction}><input type="hidden" name="id" value={r.id} /><input type="hidden" name="op" value="dismiss" />
                  <button className="btn btn-secondary btn-sm" type="submit">Dismiss</button></form>
                <form action={reportAction}><input type="hidden" name="id" value={r.id} /><input type="hidden" name="op" value="takedown" />
                  <button className="btn btn-primary btn-sm" type="submit">Remove listing</button></form>
              </div>
            )}
          </div>
        ))}
      </div>
    </>
  );
}
