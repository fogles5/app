import { MapPin, Search, Sparkles } from 'lucide-react';
import { query } from '@/lib/db';
import type { RowDataPacket } from 'mysql2';

interface CityRow extends RowDataPacket { id: number; name: string; state_name: string; state_abbr: string; listing_count: number }

export const metadata = { title: 'Choose your location · Turnpage' };

export default async function ChooseLocation({ searchParams }: { searchParams: Promise<{ q?: string }> }) {
  const { q = '' } = await searchParams;
  const term = q.trim().slice(0, 60);
  const cities = await query<CityRow>(
    `SELECT c.id, c.name, s.name AS state_name, s.abbreviation AS state_abbr,
            (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active' AND is_deleted = 0) AS listing_count
     FROM cities c LEFT JOIN states s ON c.state_id = s.id
     WHERE c.is_active = 1 ${term ? 'AND (c.name LIKE ? OR s.name LIKE ?)' : ''}
     ORDER BY listing_count DESC, c.name ASC LIMIT 60`,
    term ? [`%${term}%`, `%${term}%`] : []);

  return (
    <div className="page narrow">
      <section className="hero">
        <span className="hero-eyebrow"><Sparkles className="mr-1 inline size-3.5" />Local ads, real people</span>
        <h1 className="hero-title">Find what’s <span className="hero-accent">happening near you</span></h1>
        <p className="hero-sub">Pick a city to browse fresh local ads and chat instantly.</p>
      </section>
      <form className="search-row" action="/choose-location.php" method="get">
        <input type="search" name="q" defaultValue={term} placeholder="Search city or state" autoComplete="off" />
        <button className="btn btn-primary" type="submit"><Search className="size-4" />Search</button>
      </form>
      <div className="city-grid">
        {cities.map((c) => (
          <a key={c.id} className="city-tile" href={`/city.php?location=${encodeURIComponent(c.name)}`}>
            <MapPin className="mb-1 size-4 text-brand-400" />
            <strong>{c.name}</strong>
            <span>{c.state_abbr}</span>
            <em>{c.listing_count} ads</em>
          </a>
        ))}
        {cities.length === 0 && <p className="muted">No locations match “{term}”.</p>}
      </div>
    </div>
  );
}
