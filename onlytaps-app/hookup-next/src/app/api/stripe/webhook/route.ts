import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { grantPlan, revokePlan } from '@/lib/membership';
import { verifyWebhook } from '@/lib/stripe';

export const dynamic = 'force-dynamic';

// Paid access lasts a billing month plus a few days of grace; invoice.paid keeps extending it.
const PERIOD_DAYS = 35;

interface StripeEvent { id: string; type: string; data: { object: Record<string, any> } } // eslint-disable-line @typescript-eslint/no-explicit-any

async function userBySubscription(sub: string | undefined): Promise<number | null> {
  if (!sub) return null;
  const r = await query<RowDataPacket>('SELECT id FROM users WHERE stripe_subscription_id = ? LIMIT 1', [sub]);
  return r[0] ? Number(r[0].id) : null;
}

export async function POST(req: Request) {
  const secret = process.env.STRIPE_WEBHOOK_SECRET;
  if (!secret) return new Response('Webhook not configured', { status: 503 });
  const payload = await req.text();
  if (!verifyWebhook(payload, req.headers.get('stripe-signature'), secret)) return new Response('Bad signature', { status: 400 });

  const ev = JSON.parse(payload) as StripeEvent;
  const seen = await query<RowDataPacket>('SELECT id FROM stripe_events WHERE id = ?', [ev.id]);
  if (seen[0]) return Response.json({ received: true, duplicate: true });

  const o = ev.data.object;
  if (ev.type === 'checkout.session.completed') {
    const userId = parseInt(String(o.client_reference_id ?? ''), 10);
    const plan = String(o.metadata?.plan ?? '');
    if (userId && plan && o.payment_status !== 'unpaid') {
      await grantPlan(userId, plan, PERIOD_DAYS, { customer: o.customer ?? null, subscription: o.subscription ?? null });
    }
  } else if (ev.type === 'invoice.paid') {
    const userId = await userBySubscription(o.subscription);
    if (userId) await query('UPDATE users SET premium_expires = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?', [PERIOD_DAYS, userId]);
  } else if (ev.type === 'customer.subscription.deleted') {
    const userId = await userBySubscription(o.id);
    if (userId) await revokePlan(userId);
  }

  await query('INSERT IGNORE INTO stripe_events (id, type) VALUES (?, ?)', [ev.id, ev.type]);
  return Response.json({ received: true });
}
