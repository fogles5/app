import { notFound } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from './db';
import { getUser, type SessionUser } from './auth';

// Every admin page and every admin server action must call this: it re-checks the role in the DB on each request.
export async function requireAdmin(): Promise<SessionUser> {
  const user = await getUser();
  if (!user) notFound();
  const r = await query<RowDataPacket>(
    "SELECT 1 FROM users WHERE id = ? AND (is_admin = 1 OR role = 'admin') AND is_banned = 0 AND is_suspended = 0", [user.id]);
  if (!r[0]) notFound();
  return user;
}
