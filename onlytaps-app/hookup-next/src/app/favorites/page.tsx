import { redirect } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { toggleFavorite } from '../listing-actions';

interface Row extends RowDataPacket {
  id: number; title: string; description: string; photo_url: string | null;
  category_name: string | null; city_name: string | null; username: string | null;
}

export const metadata = { title: 'Favorites · Turnpage' };

export default async function Favorites() {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/favorites.php');

  const rows = await query<Row>(
    `SELECT l.id, l.title, l.description, l.photo_url, c.name AS category_name, ct.name AS city_name, u.username
     FROM favorites f JOIN listings l ON f.listing_id = l.id
     LEFT JOIN categories c ON l.category_id = c.id LEFT JOIN cities ct ON l.city_id = ct.id
     LEFT JOIN users u ON l.user_id = u.id
     WHERE f.user_id = ? AND l.is_deleted = 0 ORDER BY f.created_at DESC LIMIT 100`, [user.id]);

  return (
    <div className="page">
      <header className="city-head">
        <h1 className="page-title">❤️ Favorites</h1>
        <p className="muted">{rows.length} saved</p>
      </header>

      {rows.length === 0 ? (
        <p className="muted">Nothing saved yet. Tap “Save” on an ad to keep it here. <a href="/choose-location.php">Browse ads</a></p>
      ) : (
        <div className="listing-grid">
          {rows.map((l) => (
            <div key={l.id} className="ad-card">
              <a href={`/listing.php?id=${l.id}`}>
                <div className="ad-thumb" style={l.photo_url ? { backgroundImage: `url(${JSON.stringify(l.photo_url)})` } : undefined} />
                <div className="ad-body">
                  <h3>{l.title}</h3>
                  <p>{l.description.slice(0, 100)}{l.description.length > 100 ? '…' : ''}</p>
                  <div className="ad-meta"><span>{l.category_name} · {l.city_name}</span><span>{l.username}</span></div>
                </div>
              </a>
              <form action={toggleFavorite} className="fav-form">
                <input type="hidden" name="id" value={l.id} />
                <input type="hidden" name="return" value="favorites" />
                <button className="btn btn-secondary" type="submit">Remove</button>
              </form>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
