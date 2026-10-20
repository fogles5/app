import { redirect, notFound } from 'next/navigation';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import type { RowDataPacket } from 'mysql2';
import { toggleFavorite } from '../listing-actions';

interface L extends RowDataPacket {
  id: number; user_id: number; title: string; description: string; photo_url: string | null; views: number;
  age: number | null; gender: string | null; seeking: string | null; neighborhood: string | null; created_at: string;
  category_name: string | null; city_name: string | null; username: string | null; is_online: number | null; is_demo: number | null;
}

export const metadata = { title: 'Listing · Turnpage' };

export default async function Listing({ searchParams }: { searchParams: Promise<{ id?: string }> }) {
  const id = parseInt((await searchParams).id ?? '0', 10);
  if (!id) redirect('/choose-location.php');

  const rows = await query<L>(
    `SELECT l.*, c.name AS category_name, ct.name AS city_name, u.username, u.is_online, u.is_demo
     FROM listings l LEFT JOIN categories c ON l.category_id = c.id
     LEFT JOIN cities ct ON l.city_id = ct.id LEFT JOIN users u ON l.user_id = u.id
     WHERE l.id = ? AND l.is_deleted = 0 LIMIT 1`, [id]);
  const l = rows[0];
  if (!l) notFound();

  await query('UPDATE listings SET views = views + 1 WHERE id = ?', [id]);
  const user = await getUser();
  const own = user?.id === l.user_id;
  const saved = user ? (await query('SELECT 1 FROM favorites WHERE user_id = ? AND listing_id = ?', [user.id, id])).length > 0 : false;
  const images = await query<RowDataPacket & { image_url?: string; filename?: string }>('SELECT * FROM listing_images WHERE listing_id = ? LIMIT 8', [id]).catch(() => []);

  return (
    <div className="page narrow">
      <p className="muted"><a href={l.city_name ? `/city.php?location=${encodeURIComponent(l.city_name)}` : '/choose-location.php'}>‹ Back to {l.city_name ?? 'listings'}</a></p>
      <article className="listing-detail">
        <header>
          <span className="chip on">{l.category_name}</span>
          <h1 className="page-title">{l.title}</h1>
          <p className="muted">
            {l.city_name}{l.neighborhood ? ` · ${l.neighborhood}` : ''} · {new Date(l.created_at).toLocaleDateString('en-US', { timeZone: 'UTC' })} · {Number(l.views) + 1} views
          </p>
        </header>

        {l.photo_url && <img className="detail-photo" src={l.photo_url} alt="" />}
        {images.map((im, i) => {
          const src = im.image_url || im.filename;
          return src ? <img key={i} className="detail-photo" src={src.startsWith('http') || src.startsWith('/') ? src : '/' + src} alt="" /> : null;
        })}

        <p className="detail-body">{l.description}</p>

        <dl className="facts">
          {l.age != null && <><dt>Age</dt><dd>{l.age}</dd></>}
          {l.gender && <><dt>Gender</dt><dd>{l.gender}</dd></>}
          {l.seeking && <><dt>Seeking</dt><dd>{l.seeking}</dd></>}
        </dl>

        <aside className="poster">
          <div>
            <strong>{l.username}</strong>
            <span className={'muted' + (l.is_online ? ' online' : '')}> {l.is_online ? '● Online' : 'Offline'}</span>
          </div>
          {own ? (
            <a className="btn btn-secondary" href={`/edit-listing.php?id=${l.id}`}>Edit your ad</a>
          ) : l.is_demo ? (
            <p className="muted">This is a sample listing with a fictional profile. It can’t receive messages.</p>
          ) : user ? (
            <div className="row">
              <a className="btn btn-primary" href={`/messages.php?with=${l.user_id}`}>💬 Message</a>
              <form action={toggleFavorite}>
                <input type="hidden" name="id" value={l.id} />
                <button className="btn btn-secondary" type="submit">{saved ? '❤️ Saved' : '🤍 Save'}</button>
              </form>
              <a className="btn btn-secondary" href={`/report-abuse.php?listing_id=${l.id}`}>Report</a>
            </div>
          ) : (
            <a className="btn btn-primary" href="/login.php">Log in to message</a>
          )}
        </aside>
      </article>
    </div>
  );
}
