'use server';

import { revalidatePath } from 'next/cache';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { requireAdmin } from '@/lib/admin';
import { grantPlan, revokePlan } from '@/lib/membership';

const num = (fd: FormData, k: string) => parseInt(String(fd.get(k) ?? '0'), 10) || 0;

async function audit(adminId: number, action: string, detail: string) {
  await query("INSERT INTO security_logs (user_id, ip_address, action, details, severity) VALUES (?, '127.0.0.1', ?, ?, 'low')", [adminId, `admin_${action}`, detail.slice(0, 500)])
    .catch(() => undefined);
}

export async function userAction(fd: FormData): Promise<void> {
  const admin = await requireAdmin();
  const id = num(fd, 'id');
  const op = String(fd.get('op'));
  if (!id || id === admin.id) return;
  // Admins can't be modified from the UI so a compromised admin can't lock out the others.
  const t = await query<RowDataPacket>("SELECT is_admin, role FROM users WHERE id = ?", [id]);
  if (!t[0] || t[0].is_admin || t[0].role === 'admin') return;

  if (op === 'grant_plus' || op === 'grant_vip') {
    await grantPlan(id, op.slice(6), 30);
    await audit(admin.id, op, `user ${id}`);
    revalidatePath('/admin/users');
    return;
  }
  if (op === 'revoke_plan') {
    await revokePlan(id);
    await audit(admin.id, op, `user ${id}`);
    revalidatePath('/admin/users');
    return;
  }

  const sql: Record<string, string> = {
    ban: 'UPDATE users SET is_banned = 1 WHERE id = ?',
    unban: 'UPDATE users SET is_banned = 0 WHERE id = ?',
    suspend: "UPDATE users SET is_suspended = 1, suspended_until = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?",
    unsuspend: 'UPDATE users SET is_suspended = 0, suspended_until = NULL WHERE id = ?',
    verify: 'UPDATE users SET verified = 1 WHERE id = ?',
    unverify: 'UPDATE users SET verified = 0 WHERE id = ?',
  };
  if (!sql[op]) return;
  await query(sql[op], [id]);
  await audit(admin.id, op, `user ${id}`);
  revalidatePath('/admin/users');
}

export async function listingAction(fd: FormData): Promise<void> {
  const admin = await requireAdmin();
  const id = num(fd, 'id');
  const op = String(fd.get('op'));
  if (!id) return;
  if (op === 'remove') await query("UPDATE listings SET is_deleted = 1, status = 'deleted' WHERE id = ?", [id]);
  else if (op === 'restore') await query("UPDATE listings SET is_deleted = 0, status = 'active' WHERE id = ?", [id]);
  else return;
  await audit(admin.id, `listing_${op}`, `listing ${id}`);
  revalidatePath('/admin/listings');
}

export async function reportAction(fd: FormData): Promise<void> {
  const admin = await requireAdmin();
  const id = num(fd, 'id');
  const op = String(fd.get('op'));
  const r = await query<RowDataPacket>('SELECT listing_id FROM reports WHERE id = ?', [id]);
  if (!r[0]) return;
  if (op === 'dismiss') {
    await query("UPDATE reports SET status = 'reviewed', resolved_at = NOW(), assigned_to = ? WHERE id = ?", [admin.id, id]);
  } else if (op === 'takedown') {
    await query("UPDATE listings SET is_deleted = 1, status = 'deleted' WHERE id = ?", [r[0].listing_id]);
    await query("UPDATE reports SET status = 'action_taken', resolved_at = NOW(), assigned_to = ? WHERE id = ?", [admin.id, id]);
  } else return;
  await audit(admin.id, `report_${op}`, `report ${id}`);
  revalidatePath('/admin/reports');
}

const slugify = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 100);

export async function categoryAction(fd: FormData): Promise<void> {
  const admin = await requireAdmin();
  const op = String(fd.get('op'));
  const id = num(fd, 'id');
  const name = String(fd.get('name') ?? '').trim().slice(0, 100);

  if (op === 'add' && name && slugify(name)) {
    await query('INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)', [name, slugify(name)]);
  } else if (op === 'rename' && id && name) {
    await query('UPDATE categories SET name = ? WHERE id = ?', [name, id]);
  } else if (op === 'delete' && id) {
    const used = await query<RowDataPacket>('SELECT COUNT(*) AS n FROM listings WHERE category_id = ?', [id]);
    if (Number(used[0].n) === 0) await query('DELETE FROM categories WHERE id = ?', [id]);
  } else return;
  await audit(admin.id, `category_${op}`, `category ${id || name}`);
  revalidatePath('/admin/categories');
}
