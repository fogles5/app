import { query } from './db';
import { bridge, sessionId } from './bridge';
import { getMembership } from './membership';

export interface SessionUser { id: number; username: string; csrf: string | null; unread: number; isAdmin: boolean; plan: string }

// Resolves the logged-in user by asking the PHP app (which owns the session) over loopback.
export async function getUser(): Promise<SessionUser | null> {
  if (!(await sessionId())) return null;
  try {
    const r = await bridge('/api/whoami.php');
    if (!r.ok) return null;
    const s = (await r.json()) as { user_id: number | null; csrf: string | null };
    if (!s.user_id) return null;
    const u = await query<{ username: string; unread: number; is_admin: number; role: string | null } & import('mysql2').RowDataPacket>(
      `SELECT username, is_admin, role,
              (SELECT COUNT(*) FROM messages WHERE receiver_id = u.id AND is_read = 0) AS unread
       FROM users u WHERE id = ? LIMIT 1`, [s.user_id]);
    if (!u[0]) return null;
    const plan = (await getMembership(s.user_id)).plan.slug;
    return { plan, id: s.user_id, username: u[0].username, csrf: s.csrf, unread: Number(u[0].unread), isAdmin: u[0].is_admin === 1 || u[0].role === 'admin' };
  } catch {
    return null;
  }
}
