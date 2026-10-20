import { cookies } from 'next/headers';

const BASE = 'http://127.0.0.1:8000';

export async function sessionId(): Promise<string | null> {
  const sid = (await cookies()).get('PHPSESSID')?.value;
  return sid && /^[A-Za-z0-9,-]{10,128}$/.test(sid) ? sid : null;
}

// Calls a PHP bridge endpoint on loopback, authenticated by the shared secret and the user's session cookie.
export async function bridge(path: string, init: RequestInit = {}): Promise<Response> {
  const secret = process.env.BRIDGE_SECRET;
  if (!secret) throw new Error('BRIDGE_SECRET is not configured');
  const sid = await sessionId();
  const headers = new Headers(init.headers);
  headers.set('X-Bridge-Secret', secret);
  if (sid) headers.set('Cookie', 'PHPSESSID=' + sid);
  return fetch(BASE + path, { ...init, headers, cache: 'no-store' });
}
