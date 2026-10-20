'use client';

import { useState, useTransition } from 'react';
import { Sparkles, Wand2 } from 'lucide-react';
import { generateBio } from '@/app/ai-actions';

const TONES = ['friendly', 'playful', 'confident', 'romantic', 'mysterious', 'professional'];

export default function AiBioWriter({ enabled, onResult }: { enabled: boolean; onResult: (bio: string) => void }) {
  const [open, setOpen] = useState(false);
  const [keywords, setKeywords] = useState('');
  const [tone, setTone] = useState('friendly');
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  const run = () =>
    start(async () => {
      setMsg(null);
      const r = await generateBio(keywords, tone);
      if (r.bio) {
        onResult(r.bio);
        setMsg({ ok: true, text: `Bio generated — edit it as you like. ${r.remaining} AI uses left today.` });
      } else {
        setMsg({ ok: false, text: r.error ?? 'Something went wrong.' });
      }
    });

  return (
    <div className="rounded-2xl border border-violet-400/30 bg-violet-500/10 p-4">
      <button type="button" onClick={() => setOpen((o) => !o)} className="flex w-full items-center gap-2 text-left font-semibold">
        <Sparkles className="size-4 text-violet-300" /> Write my bio with AI
        <span className="ml-auto text-xs font-normal text-white/50">{open ? 'Hide' : 'Try it'}</span>
      </button>
      {open && (
        <div className="mt-3 flex flex-col gap-3">
          {!enabled && <p className="text-sm text-amber-200">AI features aren’t enabled on this server yet.</p>}
          <label className="flex flex-col gap-1.5 text-sm font-semibold">
            A few keywords about you
            <input value={keywords} onChange={(e) => setKeywords(e.target.value)} maxLength={300}
              placeholder="e.g. hiking, dogs, craft beer, looking for fun dates" />
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-semibold">
            Tone
            <select value={tone} onChange={(e) => setTone(e.target.value)}>
              {TONES.map((t) => <option key={t} value={t}>{t[0].toUpperCase() + t.slice(1)}</option>)}
            </select>
          </label>
          <button type="button" className="btn btn-primary btn-sm self-start" disabled={pending || !enabled} onClick={run}>
            <Wand2 className="size-4" /> {pending ? 'Writing…' : 'Generate bio'}
          </button>
          {msg && <p role="status" className={'text-sm ' + (msg.ok ? 'text-green-300' : 'text-red-300')}>{msg.text}</p>}
        </div>
      )}
    </div>
  );
}
