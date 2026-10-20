import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { categoryAction } from '../actions';
import ConfirmButton from '@/components/ConfirmButton';

export default async function Categories() {
  const rows = await query<RowDataPacket>(
    `SELECT c.id, c.name, c.slug, (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id AND l.is_deleted = 0) AS n
     FROM categories c ORDER BY c.display_order, c.name`);
  return (
    <>
      <h1 className="page-title">Categories</h1>
      <form action={categoryAction} className="search-row">
        <input type="hidden" name="op" value="add" />
        <input name="name" placeholder="New category name" maxLength={100} required />
        <button className="btn btn-primary" type="submit">Add</button>
      </form>
      <div className="manage-list">
        {rows.map((c) => (
          <div key={c.id} className="manage-row">
            <form action={categoryAction} className="search-row" style={{ flex: 1 }}>
              <input type="hidden" name="id" value={c.id} />
              <input type="hidden" name="op" value="rename" />
              <input name="name" defaultValue={c.name} maxLength={100} required />
              <button className="btn btn-secondary btn-sm" type="submit">Rename</button>
            </form>
            <span className="muted">/{c.slug} · {Number(c.n)} ads</span>
            {Number(c.n) === 0 && (
              <form action={categoryAction}>
                <input type="hidden" name="id" value={c.id} />
                <input type="hidden" name="op" value="delete" />
                <ConfirmButton message={`Delete "${c.name}"?`}>Delete</ConfirmButton>
              </form>
            )}
          </div>
        ))}
      </div>
    </>
  );
}
