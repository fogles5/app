import { redirect } from 'next/navigation';
import { getUser } from '@/lib/auth';
import { formOptions } from '@/lib/options';
import ListingForm from '@/components/ListingForm';

export const metadata = { title: 'Post an ad · Turnpage' };

export default async function CreateListing() {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/create-listing.php');
  const { categories, states } = await formOptions();
  return (
    <div className="page narrow">
      <h1 className="page-title">Post an ad</h1>
      <p className="muted">Ads stay active for 30 days.</p>
      <ListingForm mode="create" categories={categories} states={states} />
    </div>
  );
}
