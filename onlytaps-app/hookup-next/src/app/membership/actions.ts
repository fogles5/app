'use server';

import { redirect } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { getPlanBySlug } from '@/lib/membership';
import { stripeApi, stripeConfigured } from '@/lib/stripe';

export async function startCheckout(fd: FormData): Promise<void> {
  const user = await getUser();
  if (!user) redirect('/login.php?redirect=/membership.php');
  const plan = await getPlanBySlug(String(fd.get('plan') ?? ''));
  if (!plan || plan.slug === 'free') redirect('/membership.php?status=invalid');
  if (!stripeConfigured()) redirect('/membership.php?status=unconfigured');

  const site = process.env.SITE_URL!.replace(/\/$/, '');
  let url: string | undefined;
  try {
    // Price comes from the DB, never from the form.
    const s = await stripeApi<{ url?: string }>('POST', '/checkout/sessions', {
      mode: 'subscription',
      client_reference_id: String(user.id),
      metadata: { plan: plan.slug, user_id: String(user.id) },
      success_url: `${site}/membership.php?status=success`,
      cancel_url: `${site}/membership.php?status=cancelled`,
      line_items: {
        0: {
          quantity: 1,
          price_data: {
            currency: 'usd',
            unit_amount: Math.round(plan.price * 100),
            recurring: { interval: 'month' },
            product_data: { name: `Turnpage ${plan.name}` },
          },
        },
      },
    });
    url = s.url;
  } catch {
    redirect('/membership.php?status=error');
  }
  redirect(url ?? '/membership.php?status=error');
}

export async function cancelSubscription(): Promise<void> {
  const user = await getUser();
  if (!user) redirect('/login.php');
  const r = await query<RowDataPacket>('SELECT stripe_subscription_id AS s FROM users WHERE id = ?', [user.id]);
  const sub = r[0]?.s as string | null;
  if (!sub || !stripeConfigured()) redirect('/membership.php?status=error');
  try {
    await stripeApi('POST', `/subscriptions/${encodeURIComponent(sub)}`, { cancel_at_period_end: 'true' });
  } catch {
    redirect('/membership.php?status=error');
  }
  redirect('/membership.php?status=cancel_scheduled');
}
