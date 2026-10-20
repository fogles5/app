import type { RowDataPacket } from 'mysql2';
import { Bot, MessageCircle } from 'lucide-react';
import { query } from '@/lib/db';

export const metadata = { title: 'AI Profiles · Turnpage' };

export default async function AiProfiles() {
  const rows = await query<RowDataPacket>('SELECT slug, name, age, tagline, interests, hue FROM ai_personas WHERE is_active = 1 ORDER BY display_order, id');
  return (
    <div className="mx-auto max-w-6xl px-5">
      <section className="hero mt-6">
        <span className="hero-eyebrow"><Bot className="mr-1 inline size-3.5" />AI profiles</span>
        <h1 className="hero-title">Chat with <span className="hero-accent">AI characters</span></h1>
        <p className="hero-sub">Fictional AI personalities you can chat with any time. Always clearly labeled as AI.</p>
      </section>

      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {rows.map((p) => (
          <a key={p.slug} href={`/ai/${p.slug}`} className="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 p-5 text-center no-underline transition hover:-translate-y-1 hover:border-violet-400/50 hover:shadow-glow">
            <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-200"><Bot className="size-3" />AI</span>
            <div className="mx-auto mb-4 grid size-24 place-items-center rounded-full text-4xl font-extrabold text-white ring-4 ring-white/10"
              style={{ backgroundImage: `linear-gradient(135deg, hsl(${p.hue} 80% 55%), hsl(${(p.hue + 50) % 360} 80% 45%))` }}>
              {String(p.name).charAt(0)}
            </div>
            <h2 className="text-xl font-bold">{p.name}, {p.age}</h2>
            <p className="mt-1 text-sm text-white/65">{p.tagline}</p>
            <div className="mt-3 flex flex-wrap justify-center gap-1.5">
              {String(p.interests).split(',').slice(0, 3).map((i) => <span key={i} className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/80">{i.trim()}</span>)}
            </div>
            <span className="btn btn-primary btn-sm mt-4 w-full"><MessageCircle className="size-4" />Chat</span>
          </a>
        ))}
      </div>
    </div>
  );
}
