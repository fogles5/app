import { redirect } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { aiConfigured } from '@/lib/ai';
import ProfileForm, { type ProfileInit } from '@/components/ProfileForm';

export const metadata = { title: 'Edit profile · Turnpage' };

const arr = (v: unknown): string => {
  try { const a = typeof v === 'string' ? JSON.parse(v) : v; return Array.isArray(a) ? a.join(', ') : ''; } catch { return ''; }
};

export default async function EditProfile() {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/edit-profile.php');

  const [u] = await query<RowDataPacket>('SELECT display_name, about_me, avatar FROM users WHERE id = ?', [user.id]);
  const [p] = await query<RowDataPacket>('SELECT * FROM user_profiles WHERE user_id = ?', [user.id]);

  const init: ProfileInit = {
    display_name: u?.display_name ?? '', about_me: u?.about_me ?? '', has_avatar: !!u?.avatar,
    height: p?.height ?? null, body_type: p?.body_type ?? null, ethnicity: p?.ethnicity ?? '',
    relationship_status: p?.relationship_status ?? null, interests: arr(p?.interests), occupation: p?.occupation ?? '',
    education: p?.education ?? null, smoking: p?.smoking ?? null, drinking: p?.drinking ?? null, languages: arr(p?.languages),
    display_distance: p ? !!p.display_distance : true, show_age: p ? !!p.show_age : true, show_online_status: p ? !!p.show_online_status : true,
  };

  return (
    <div className="page narrow">
      <h1 className="page-title">Edit profile</h1>
      <p className="muted"><a href={`/profile.php?id=${user.id}`}>‹ View profile</a></p>
      <ProfileForm init={init} aiEnabled={aiConfigured()} />
    </div>
  );
}
