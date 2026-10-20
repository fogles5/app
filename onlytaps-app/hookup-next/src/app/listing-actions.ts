'use server';

import { redirect } from 'next/navigation';
import type { ResultSetHeader, RowDataPacket } from 'mysql2';
import { query, pool } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { bridge } from '@/lib/bridge';
import { activeListingCount, featuredUsedThisMonth, getMembership } from '@/lib/membership';

export interface FormState { error?: string }

const GENDERS = ['male', 'female', 'couple', 'trans', 'other'];
const SEEKING = ['male', 'female', 'couple', 'trans', 'any'];

async function parse(fd: FormData) {
  const str = (k: string, max: number) => String(fd.get(k) ?? '').trim().slice(0, max);
  const categoryId = parseInt(str('category_id', 10), 10) || 0;
  const cityId = parseInt(str('city_id', 10), 10) || 0;
  const title = str('title', 255);
  const description = str('description', 10000);
  const ageRaw = str('age', 3);
  const age = ageRaw ? parseInt(ageRaw, 10) : null;
  const gender = str('gender', 10);
  const seeking = str('seeking', 10);
  const neighborhood = str('neighborhood', 255);
  const zip = str('zip_code', 20);

  let error: string | undefined;
  if (!categoryId || !cityId || !title || !description) error = 'All required fields must be filled';
  else if (title.length < 10 || title.length > 100) error = 'Title must be between 10 and 100 characters';
  else if (description.length < 50) error = 'Description must be at least 50 characters';
  else if (age !== null && (!Number.isInteger(age) || age < 18 || age > 99)) error = 'Age must be between 18 and 99';
  else if (gender && !GENDERS.includes(gender)) error = 'Invalid gender';
  else if (seeking && !SEEKING.includes(seeking)) error = 'Invalid seeking value';
  if (error) return { error } as const;

  const ok = await query<RowDataPacket>(
    'SELECT (SELECT COUNT(*) FROM categories WHERE id = ?) AS c, (SELECT COUNT(*) FROM cities WHERE id = ?) AS t', [categoryId, cityId]);
  if (!Number(ok[0].c) || !Number(ok[0].t)) return { error: 'Invalid category or location' } as const;

  return { data: { categoryId, cityId, title, description, age, gender: gender || null, seeking: seeking || null, neighborhood: neighborhood || null, zip: zip || null } } as const;
}

async function uploadPhoto(fd: FormData): Promise<{ path?: string; error?: string }> {
  const file = fd.get('photo');
  if (!(file instanceof File) || file.size === 0) return {};
  const out = new FormData();
  out.append('photo', file, file.name);
  out.append('type', 'listing');
  const r = await bridge('/api/bridge/upload.php', { method: 'POST', body: out });
  const j = (await r.json().catch(() => ({}))) as { success?: boolean; path?: string; message?: string };
  return r.ok && j.success ? { path: j.path } : { error: j.message || 'Photo upload failed' };
}

export async function createListing(_prev: FormState, fd: FormData): Promise<FormState> {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/create-listing.php');
  const p = await parse(fd);
  if ('error' in p) return { error: p.error };

  const m = await getMembership(user.id);
  if ((await activeListingCount(user.id)) >= m.plan.maxListings) {
    return { error: `Your ${m.plan.name} plan allows ${m.plan.maxListings} active ads. Delete one or upgrade at /membership.php` };
  }
  const feature = m.plan.featuredPerMonth > 0 && (await featuredUsedThisMonth(user.id)) < m.plan.featuredPerMonth;

  const photo = await uploadPhoto(fd);
  if (photo.error) return { error: photo.error };

  const d = p.data;
  const [res] = await pool.query<ResultSetHeader>(
    `INSERT INTO listings (user_id, category_id, city_id, title, description, age, gender, seeking, neighborhood, zip_code, photo_url, status, expires_at, is_featured, featured_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ${feature ? 'NOW()' : 'NULL'})`,
    [user.id, d.categoryId, d.cityId, d.title, d.description, d.age, d.gender, d.seeking, d.neighborhood, d.zip, photo.path ?? null, feature ? 1 : 0]);
  redirect('/listing.php?id=' + res.insertId);
}

export async function updateListing(_prev: FormState, fd: FormData): Promise<FormState> {
  const user = await getUser();
  const id = parseInt(String(fd.get('id') ?? '0'), 10);
  if (!user) redirect('/login.php');
  const own = await query<RowDataPacket>('SELECT id FROM listings WHERE id = ? AND user_id = ? AND is_deleted = 0', [id, user.id]);
  if (!own[0]) return { error: 'Listing not found or access denied' };

  const p = await parse(fd);
  if ('error' in p) return { error: p.error };
  const photo = await uploadPhoto(fd);
  if (photo.error) return { error: photo.error };

  const d = p.data;
  await query(
    `UPDATE listings SET category_id = ?, city_id = ?, title = ?, description = ?, age = ?, gender = ?, seeking = ?,
            neighborhood = ?, zip_code = ?${photo.path ? ', photo_url = ?' : ''} WHERE id = ? AND user_id = ?`,
    [d.categoryId, d.cityId, d.title, d.description, d.age, d.gender, d.seeking, d.neighborhood, d.zip, ...(photo.path ? [photo.path] : []), id, user.id]);
  redirect('/listing.php?id=' + id);
}

export async function deleteListing(fd: FormData): Promise<void> {
  const user = await getUser();
  const id = parseInt(String(fd.get('id') ?? '0'), 10);
  if (!user || !id) redirect('/login.php');
  await query('UPDATE listings SET is_deleted = 1, status = ? WHERE id = ? AND user_id = ?', ['deleted', id, user.id]);
  redirect(fd.get('return') === 'my-listings' ? '/my-listings.php' : '/choose-location.php');
}

export async function toggleFavorite(fd: FormData): Promise<void> {
  const user = await getUser();
  const id = parseInt(String(fd.get('id') ?? '0'), 10);
  if (!user) redirect('/login.php');
  if (!id) redirect('/favorites.php');
  const [del] = await pool.query<ResultSetHeader>('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [user.id, id]);
  if (!del.affectedRows) {
    await query('INSERT IGNORE INTO favorites (user_id, listing_id) SELECT ?, id FROM listings WHERE id = ? AND is_deleted = 0', [user.id, id]);
  }
  redirect(fd.get('return') === 'favorites' ? '/favorites.php' : `/listing.php?id=${id}`);
}
