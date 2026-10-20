import crypto from 'node:crypto';

export const stripeConfigured = () => !!process.env.STRIPE_SECRET_KEY && !!process.env.SITE_URL;

// Stripe takes form-encoded bodies with bracketed keys for nesting.
function encode(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([k, v]) => {
    const key = prefix ? `${prefix}[${k}]` : k;
    if (v !== null && typeof v === 'object') return encode(v as Record<string, unknown>, key);
    return [`${encodeURIComponent(key)}=${encodeURIComponent(String(v))}`];
  });
}

export async function stripeApi<T = Record<string, unknown>>(method: 'POST' | 'GET', path: string, params: Record<string, unknown> = {}): Promise<T> {
  const r = await fetch('https://api.stripe.com/v1' + path, {
    method,
    headers: { Authorization: `Bearer ${process.env.STRIPE_SECRET_KEY}`, 'Content-Type': 'application/x-www-form-urlencoded' },
    body: method === 'POST' ? encode(params).join('&') : undefined,
    cache: 'no-store',
  });
  const j = (await r.json()) as T & { error?: { message?: string } };
  if (!r.ok) throw new Error(j.error?.message || 'Stripe request failed');
  return j;
}

// Verifies the Stripe-Signature header (HMAC-SHA256 over "t.payload") with a 5 minute replay window.
export function verifyWebhook(payload: string, header: string | null, secret: string): boolean {
  if (!header) return false;
  const parts = Object.fromEntries(header.split(',').map((p) => p.split('=') as [string, string]));
  const t = parts.t;
  const sigs = header.split(',').filter((p) => p.startsWith('v1=')).map((p) => p.slice(3));
  if (!t || !sigs.length || Math.abs(Date.now() / 1000 - Number(t)) > 300) return false;
  const expected = crypto.createHmac('sha256', secret).update(`${t}.${payload}`).digest();
  return sigs.some((s) => {
    const b = Buffer.from(s, 'hex');
    return b.length === expected.length && crypto.timingSafeEqual(b, expected);
  });
}
