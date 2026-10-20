import type { RowDataPacket } from 'mysql2';
import { query } from './db';

export interface Plan {
  id: number; slug: string; name: string; price: number; description: string;
  maxListings: number; featuredPerMonth: number; features: string[]; color: string;
}

interface PlanRow extends RowDataPacket {
  id: number; slug: string; name: string; price: string; description: string | null;
  max_active_listings: number; featured_listings_per_month: number; features: unknown; badge_color: string | null;
}

const toPlan = (r: PlanRow): Plan => ({
  id: r.id, slug: r.slug, name: r.name, price: Number(r.price), description: r.description ?? '',
  maxListings: r.max_active_listings, featuredPerMonth: r.featured_listings_per_month,
  features: Array.isArray(r.features) ? (r.features as string[]) : [], color: r.badge_color ?? '#8b5cf6',
});

export async function listPlans(): Promise<Plan[]> {
  const rows = await query<PlanRow>('SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY display_order, id');
  return rows.map(toPlan);
}

export async function getPlanBySlug(slug: string): Promise<Plan | null> {
  const rows = await query<PlanRow>('SELECT * FROM membership_plans WHERE slug = ? AND is_active = 1 LIMIT 1', [slug]);
  return rows[0] ? toPlan(rows[0]) : null;
}

export interface Membership { plan: Plan; paid: boolean; expires: Date | null; hasSubscription: boolean }

// A paid plan only counts while premium_expires is in the future; otherwise the user is effectively on Free.
export async function getMembership(userId: number): Promise<Membership> {
  const rows = await query<PlanRow & { premium_expires: Date | null; stripe_subscription_id: string | null }>(
    `SELECT p.*, u.premium_expires, u.stripe_subscription_id
     FROM users u JOIN membership_plans p ON p.id = u.current_plan_id
     WHERE u.id = ? AND p.slug <> 'free' AND u.premium_expires > NOW() LIMIT 1`, [userId]);
  if (rows[0]) {
    return { plan: toPlan(rows[0]), paid: true, expires: rows[0].premium_expires, hasSubscription: !!rows[0].stripe_subscription_id };
  }
  const free = await getPlanBySlug('free');
  return {
    plan: free ?? { id: 1, slug: 'free', name: 'Free', price: 0, description: '', maxListings: 3, featuredPerMonth: 0, features: [], color: '#95a5a6' },
    paid: false, expires: null, hasSubscription: false,
  };
}

export async function grantPlan(userId: number, slug: string, days: number, stripe?: { customer?: string | null; subscription?: string | null }): Promise<boolean> {
  const plan = await getPlanBySlug(slug);
  if (!plan || plan.slug === 'free') return false;
  await query(
    `UPDATE users SET current_plan_id = ?, is_premium = 1, premium_expires = DATE_ADD(NOW(), INTERVAL ? DAY),
            stripe_customer_id = COALESCE(?, stripe_customer_id), stripe_subscription_id = COALESCE(?, stripe_subscription_id)
     WHERE id = ?`, [plan.id, days, stripe?.customer ?? null, stripe?.subscription ?? null, userId]);
  return true;
}

export async function revokePlan(userId: number): Promise<void> {
  await query(
    "UPDATE users SET current_plan_id = (SELECT id FROM membership_plans WHERE slug = 'free'), is_premium = 0, premium_expires = NULL, stripe_subscription_id = NULL WHERE id = ?",
    [userId]);
}

export async function activeListingCount(userId: number): Promise<number> {
  const r = await query<RowDataPacket>(
    "SELECT COUNT(*) AS n FROM listings WHERE user_id = ? AND is_deleted = 0 AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())", [userId]);
  return Number(r[0].n);
}

export async function featuredUsedThisMonth(userId: number): Promise<number> {
  const r = await query<RowDataPacket>(
    "SELECT COUNT(*) AS n FROM listings WHERE user_id = ? AND is_featured = 1 AND featured_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [userId]);
  return Number(r[0].n);
}
