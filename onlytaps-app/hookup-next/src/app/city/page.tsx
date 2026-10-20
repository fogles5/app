import { redirect } from 'next/navigation';
import { query } from '@/lib/db';
import type { RowDataPacket } from 'mysql2';

interface Row extends RowDataPacket {
  id: number; title: string; description: string; category_name: string | null; username: string | null;
  is_premium: number | null; is_featured: number; photo_url: string | null; created_at: string; age: number | null; is_demo: number | null;
}

const PER_PAGE = 20;

export const metadata = { title: 'Local listings · Turnpage' };

export default async function City({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const location = (sp.location ?? '').trim();
  const category = (sp.category ?? '').trim();
  const q = (sp.q ?? '').trim().slice(0, 80);
  const page = Math.max(1, parseInt(sp.page ?? '1', 10) || 1);
  if (!location) redirect('/choose-location.php');

  const cities = await query<RowDataPacket & { id: number; name: string }>('SELECT id, name FROM cities WHERE name = ? LIMIT 1', [location]);
  if (!cities[0]) redirect('/choose-location.php');
  const city = cities[0];

  const categories = await query<RowDataPacket & { id: number; name: string; slug: string }>(
    'SELECT id, name, slug FROM categories ORDER BY name ASC');

  const where = ['l.city_id = ?', "l.status = 'active'", 'l.is_deleted = 0'];
  const params: unknown[] = [city.id];
  if (category) { where.push('c.slug = ?'); params.push(category); }
  if (q) { where.push('(l.title LIKE ? OR l.description LIKE ?)'); params.push(`%${q}%`, `%${q}%`); }
  const from = `FROM listings l LEFT JOIN categories c ON l.category_id = c.id LEFT JOIN users u ON l.user_id = u.id WHERE ${where.join(' AND ')}`;

  const [{ total }] = await query<RowDataPacket & { total: number }>(`SELECT COUNT(*) AS total ${from}`, params);
  const rows = await query<Row>(
    `SELECT l.id, l.title, l.description, l.is_featured, l.photo_url, l.created_at, l.age, u.is_demo,
            c.name AS category_name, u.username, (u.is_premium = 1 AND u.premium_expires > NOW()) AS is_premium
     ${from} ORDER BY l.is_featured DESC, l.created_at DESC LIMIT ? OFFSET ?`,
    [...params, PER_PAGE, (page - 1) * PER_PAGE]);
  const pages = Math.max(1, Math.ceil(Number(total) / PER_PAGE));

  const link = (over: Record<string, string>) => {
    const p = new URLSearchParams({ location, ...(category && { category }), ...(q && { q }), ...over });
    return '/city.php?' + p.toString();
  };

  return (
    <div className="page">
      <header className="city-head">
        <h1 className="page-title">{city.name}</h1>
        <form className="search-row" action="/city.php" method="get">
          <input type="hidden" name="location" value={city.name} />
          {category && <input type="hidden" name="category" value={category} />}
          <input type="search" name="q" defaultValue={q} placeholder={`Search ${city.name} ads`} autoComplete="off" />
          <button className="btn btn-primary" type="submit">Search</button>
        </form>
        <p className="muted"><a href="/choose-location.php">Change location</a> · {Number(total)} ads</p>
      </header>

      <div className="chips">
        <a className={'chip' + (!category ? ' on' : '')} href={link({})}>All</a>
        {categories.map((c) => (
          <a key={c.id} className={'chip' + (category === c.slug ? ' on' : '')} href={link({ category: c.slug })}>{c.name}</a>
        ))}
      </div>

      <div className="listing-grid">
        {rows.map((l) => (
          <a key={l.id} className="ad-card" href={`/listing.php?id=${l.id}`}>
            <div className="ad-thumb" style={l.photo_url ? { backgroundImage: `url(${JSON.stringify(l.photo_url)})` } : undefined}>
              {l.is_featured ? <span className="tag-featured">★ Featured</span> : null}
              {l.is_demo ? <span className="tag-sample">Sample</span> : null}
            </div>
            <div className="ad-body">
              <h3>{l.title}</h3>
              <p>{l.description.slice(0, 120)}{l.description.length > 120 ? '…' : ''}</p>
              <div className="ad-meta">
                <span>{l.category_name}</span>
                <span>{l.username}{l.is_premium ? ' 💎' : ''}</span>
              </div>
            </div>
          </a>
        ))}
        {rows.length === 0 && <p className="muted">No ads found. Be the first to <a href="/create-listing.php">post one</a>.</p>}
      </div>

      {pages > 1 && (
        <nav className="pager">
          {page > 1 && <a className="btn btn-secondary" href={link({ page: String(page - 1) })}>‹ Prev</a>}
          <span>Page {page} of {pages}</span>
          {page < pages && <a className="btn btn-secondary" href={link({ page: String(page + 1) })}>Next ›</a>}
        </nav>
      )}
    </div>
  );
}
