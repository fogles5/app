'use server';

import { redirect } from 'next/navigation';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { bridge } from '@/lib/bridge';

export interface ProfileState { error?: string; ok?: string }

const BODY = ['slim', 'athletic', 'average', 'curvy', 'muscular', 'heavyset', 'other'];
const REL = ['single', 'married', 'divorced', 'widowed', 'separated', 'in_relationship', 'complicated'];
const EDU = ['high_school', 'some_college', 'bachelors', 'masters', 'phd', 'other'];
const SMOKE = ['never', 'occasionally', 'regularly', 'trying_to_quit'];
const DRINK = ['never', 'socially', 'regularly'];

const oneOf = (v: string, list: string[]) => (list.includes(v) ? v : null);
const list = (v: string) => v.split(',').map((s) => s.trim().slice(0, 40)).filter(Boolean).slice(0, 20);

export async function saveProfile(_prev: ProfileState, fd: FormData): Promise<ProfileState> {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/edit-profile.php');
  const s = (k: string, max: number) => String(fd.get(k) ?? '').trim().slice(0, max);

  const display = s('display_name', 100);
  const about = s('about_me', 2000);
  const heightRaw = parseInt(s('height', 3), 10);
  const height = heightRaw >= 100 && heightRaw <= 250 ? heightRaw : null;

  let avatar: string | undefined;
  const file = fd.get('avatar');
  if (file instanceof File && file.size > 0) {
    const out = new FormData();
    out.append('photo', file, file.name);
    out.append('type', 'user');
    const r = await bridge('/api/bridge/upload.php', { method: 'POST', body: out });
    const j = (await r.json().catch(() => ({}))) as { success?: boolean; path?: string; message?: string };
    if (!r.ok || !j.success) return { error: j.message || 'Photo upload failed' };
    avatar = j.path;
  }

  await query(
    `UPDATE users SET display_name = ?, about_me = ?${avatar ? ', avatar = ?' : fd.get('remove_avatar') ? ', avatar = NULL' : ''} WHERE id = ?`,
    [display || null, about || null, ...(avatar ? [avatar] : []), user.id]);

  await query(
    `INSERT INTO user_profiles (user_id, height, body_type, ethnicity, relationship_status, interests, occupation, education,
                                smoking, drinking, languages, display_distance, show_age, show_online_status)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE height=VALUES(height), body_type=VALUES(body_type), ethnicity=VALUES(ethnicity),
       relationship_status=VALUES(relationship_status), interests=VALUES(interests), occupation=VALUES(occupation),
       education=VALUES(education), smoking=VALUES(smoking), drinking=VALUES(drinking), languages=VALUES(languages),
       display_distance=VALUES(display_distance), show_age=VALUES(show_age), show_online_status=VALUES(show_online_status)`,
    [user.id, height, oneOf(s('body_type', 20), BODY), s('ethnicity', 100) || null, oneOf(s('relationship_status', 20), REL),
     JSON.stringify(list(s('interests', 500))), s('occupation', 100) || null, oneOf(s('education', 20), EDU),
     oneOf(s('smoking', 20), SMOKE), oneOf(s('drinking', 20), DRINK), JSON.stringify(list(s('languages', 300))),
     fd.get('display_distance') ? 1 : 0, fd.get('show_age') ? 1 : 0, fd.get('show_online_status') ? 1 : 0]);

  return { ok: 'Profile saved' };
}
