export const dynamic = 'force-dynamic';

export async function POST(req: Request) {
  const fd = await req.formData().catch(() => null);
  const raw = String(fd?.get('next') ?? '/');
  // Only same-site relative paths; blocks open redirects like //evil.com or /\evil.com.
  const next = /^\/(?!\/)[^\\\r\n]*$/.test(raw) ? raw : '/';

  return new Response(null, {
    status: 303,
    headers: {
      Location: next,
      'Set-Cookie': 'age_ok=1; Path=/; Max-Age=31536000; Secure; HttpOnly; SameSite=Lax',
      'Cache-Control': 'no-store',
    },
  });
}
