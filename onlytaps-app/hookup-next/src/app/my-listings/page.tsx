import { redirect } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { deleteListing } from '../listing-actions';
import ConfirmButton from '@/components/ConfirmButton';

interface Row extends RowDataPacket {
  id: number; title: string; photo_url: string | null; status: string; views: number;
  created_at: string; expires_at: string | null; category_name: string | null; city_name: string | null;
}

export const metadata = { title: 'My listings · Turnpage' };

export default async function MyListings() {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/my-listings.php');

  const rows = await query<Row>(
    `SELECT l.id, l.title, l.photo_url, l.status, l.views, l.created_at, l.expires_at,
            c.name AS category_name, ct.name AS city_name
     FROM listings l LEFT JOIN categories c ON l.category_id = c.id LEFT JOIN cities ct ON l.city_id = ct.id
     WHERE l.user_id = ? AND l.is_deleted = 0 ORDER BY l.created_at DESC LIMIT 200`, [user.id]);
  const views = rows.reduce((n, r) => n + Number(r.views || 0), 0);
  const now = Date.now();

  return (
    <div className="page">
      <header className="city-head">
        <h1 className="page-title">My listings</h1>
        <p className="muted">{rows.length} ads · {views} total views</p>
        <a className="btn btn-primary" href="/create-listing.php">➕ Post a new ad</a>
      </header>

      {rows.length === 0 ? (
        <p className="muted">You haven&apos;t posted any ads yet.</p>
      ) : (
        <div className="manage-list">
          {rows.map((l) => {
            const expired = l.expires_at ? new Date(l.expires_at).getTime() < now : false;
            return (
              <div key={l.id} className="manage-row">
                <div className="ad-thumb manage-thumb" style={l.photo_url ? { backgroundImage: `url(${JSON.stringify(l.photo_url)})` } : undefined} />
                <div className="manage-info">
                  <a href={`/listing.php?id=${l.id}`}><strong>{l.title}</strong></a>
                  <span className="muted">
                    {l.category_name} · {l.city_name} · {Number(l.views)} views ·{' '}
                    {expired ? 'Expired' : l.expires_at ? `Expires ${new Date(l.expires_at).toLocaleDateString('en-US', { timeZone: 'UTC' })}` : l.status}
                  </span>
                </div>
                <div className="row">
                  <a className="btn btn-secondary" href={`/edit-listing.php?id=${l.id}`}>Edit</a>
                  <form action={deleteListing}>
                    <input type="hidden" name="id" value={l.id} />
                    <input type="hidden" name="return" value="my-listings" />
                    <ConfirmButton message="Delete this listing?">Delete</ConfirmButton>
                  </form>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
