import { notFound } from 'next/navigation';
import type { RowDataPacket } from 'mysql2';
import { Bot } from 'lucide-react';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { aiConfigured } from '@/lib/ai';
import AiChat from '@/components/AiChat';

export const dynamic = 'force-dynamic';

export default async function AiProfile({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const [p] = await query<RowDataPacket>('SELECT id, slug, name, age, tagline, bio, interests, hue FROM ai_personas WHERE slug = ? AND is_active = 1', [slug.slice(0, 40)]);
  if (!p) notFound();

  const user = await getUser();
  const hist = user
    ? await query<RowDataPacket>(
        'SELECT role, content FROM (SELECT id, role, content FROM ai_messages WHERE user_id = ? AND persona_id = ? ORDER BY id DESC LIMIT 40) t ORDER BY id ASC', [user.id, p.id])
    : [];

  const note = !user ? undefined : !aiConfigured() ? 'AI chat isn’t enabled on this server yet.' : undefined;

  return (
    <div className="mx-auto max-w-3xl px-5 py-6">
      <section className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
        <div className="h-28" style={{ backgroundImage: `linear-gradient(135deg, hsl(${p.hue} 70% 40%), hsl(${(p.hue + 60) % 360} 70% 30%))` }} />
        <div className="px-6 pb-6">
          <div className="-mt-12 flex items-end gap-4">
            <div className="grid size-24 shrink-0 place-items-center rounded-full text-4xl font-extrabold text-white ring-4 ring-bg"
              style={{ backgroundImage: `linear-gradient(135deg, hsl(${p.hue} 80% 55%), hsl(${(p.hue + 50) % 360} 80% 45%))` }}>
              {String(p.name).charAt(0)}
            </div>
            <div className="pb-1">
              <h1 className="flex items-center gap-2 text-2xl font-extrabold">
                {p.name}, {p.age}
                <span className="inline-flex items-center gap-1 rounded-full bg-violet-500/30 px-2 py-0.5 text-[10px] font-bold uppercase text-violet-100"><Bot className="size-3" />AI</span>
              </h1>
              <p className="text-sm text-white/60">{p.tagline}</p>
            </div>
          </div>
          <p className="mt-4 text-white/80">{p.bio}</p>
          <div className="mt-3 flex flex-wrap gap-1.5">
            {String(p.interests).split(',').map((i) => <span key={i} className="rounded-full bg-white/10 px-2.5 py-0.5 text-xs">{i.trim()}</span>)}
          </div>
        </div>
      </section>

      <div className="mt-5">
        {user ? (
          <AiChat slug={p.slug} name={p.name} hue={p.hue} canChat={aiConfigured()} note={note}
            initial={hist.map((h) => ({ role: h.role as 'user' | 'assistant', content: String(h.content) }))} />
        ) : (
          <div className="rounded-3xl border border-white/10 bg-white/5 p-8 text-center">
            <p className="mb-4 text-white/70">Log in to chat with {p.name}.</p>
            <a className="btn btn-primary" href={`/login.php?redirect=/ai/${p.slug}`}>Log in</a>
          </div>
        )}
      </div>
    </div>
  );
}
