import type { RowDataPacket } from 'mysql2';
import { query } from './db';
import type { StateOpt } from '@/components/ListingForm';

export async function formOptions() {
  const categories = await query<RowDataPacket & { id: number; name: string }>('SELECT id, name FROM categories ORDER BY name');
  const rows = await query<RowDataPacket & { sid: number; sname: string; abbr: string; cid: number | null; cname: string | null }>(
    `SELECT s.id AS sid, s.name AS sname, s.abbreviation AS abbr, c.id AS cid, c.name AS cname
     FROM states s LEFT JOIN cities c ON c.state_id = s.id AND c.is_active = 1 ORDER BY s.name, c.name`);
  const map = new Map<number, StateOpt>();
  for (const r of rows) {
    let s = map.get(r.sid);
    if (!s) { s = { id: r.sid, name: r.sname, abbr: r.abbr, cities: [] }; map.set(r.sid, s); }
    if (r.cid) s.cities.push({ id: r.cid, name: r.cname as string });
  }
  return { categories: categories.map((c) => ({ id: c.id, name: c.name })), states: Array.from(map.values()) };
}
