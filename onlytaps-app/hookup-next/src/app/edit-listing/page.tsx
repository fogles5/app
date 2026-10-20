import { redirect } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { formOptions } from '@/lib/options';
import { deleteListing } from '@/app/listing-actions';
import ListingForm, { type Initial } from '@/components/ListingForm';
import ConfirmButton from '@/components/ConfirmButton';

export const metadata = { title: 'Edit ad · Turnpage' };

export default async function EditListing({ searchParams }: { searchParams: Promise<{ id?: string }> }) {
  const user = await getUser();
  if (!user) redirect('/login.php');
  const id = parseInt((await searchParams).id ?? '0', 10);
  const rows = await query<RowDataPacket & Initial>(
    'SELECT id, category_id, city_id, title, description, age, gender, seeking, neighborhood, zip_code FROM listings WHERE id = ? AND user_id = ? AND is_deleted = 0 LIMIT 1',
    [id, user.id]);
  if (!rows[0]) redirect('/choose-location.php');
  const { categories, states } = await formOptions();
  const initial: Initial = { ...rows[0] };
  return (
    <div className="page narrow">
      <h1 className="page-title">Edit ad</h1>
      <ListingForm mode="edit" categories={categories} states={states} initial={initial} />
      <form action={deleteListing} className="danger-zone">
        <input type="hidden" name="id" value={id} />
        <ConfirmButton message="Delete this ad? This cannot be undone.">Delete this ad</ConfirmButton>
      </form>
    </div>
  );
}
