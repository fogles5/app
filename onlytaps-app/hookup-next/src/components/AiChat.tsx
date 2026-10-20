'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { Bot, Send, Trash2 } from 'lucide-react';
import { clearAiChat, sendAiMessage } from '@/app/ai-actions';

interface Msg { role: 'user' | 'assistant'; content: string }

export default function AiChat({ slug, name, hue, initial, canChat, note }: {
  slug: string; name: string; hue: number; initial: Msg[]; canChat: boolean; note?: string;
}) {
  const [msgs, setMsgs] = useState<Msg[]>(initial);
  const [text, setText] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const [left, setLeft] = useState<number | null>(null);
  const [pending, start] = useTransition();
  const end = useRef<HTMLDivElement>(null);

  useEffect(() => { end.current?.scrollIntoView({ behavior: 'smooth', block: 'end' }); }, [msgs, pending]);

  const send = () => {
    const t = text.trim();
    if (!t || pending) return;
    setErr(null);
    setMsgs((m) => [...m, { role: 'user', content: t }]);
    setText('');
    start(async () => {
      const r = await sendAiMessage(slug, t);
      if (r.reply) {
        setMsgs((m) => [...m, { role: 'assistant', content: r.reply! }]);
        setLeft(r.remaining ?? null);
      } else {
        setMsgs((m) => m.slice(0, -1));
        setText(t);
        setErr(r.error ?? 'Something went wrong.');
      }
    });
  };

  return (
    <div className="flex h-[32rem] flex-col overflow-hidden rounded-3xl border border-white/10 bg-white/5 backdrop-blur">
      <div className="flex items-center gap-2 border-b border-white/10 px-4 py-3 text-sm">
        <Bot className="size-4 text-violet-300" />
        <span className="text-white/70">You’re chatting with an <strong className="text-white">AI character</strong>, not a real person.</span>
        {msgs.length > 0 && (
          <button type="button" title="Clear chat" className="ml-auto text-white/50 hover:text-white"
            onClick={() => { setMsgs([]); void clearAiChat(slug); }}><Trash2 className="size-4" /></button>
        )}
      </div>

      <div className="flex-1 space-y-3 overflow-y-auto p-4" aria-live="polite">
        {msgs.length === 0 && <p className="mt-10 text-center text-white/50">Say hi to {name} 👋</p>}
        {msgs.map((m, i) => (
          <div key={i} className={'flex ' + (m.role === 'user' ? 'justify-end' : 'justify-start')}>
            <div
              className={'max-w-[80%] whitespace-pre-wrap break-words rounded-2xl px-3.5 py-2 text-sm ' + (m.role === 'user' ? 'text-white' : 'bg-white/10 text-white/90')}
              style={m.role === 'user' ? { backgroundImage: 'var(--grad)' } : { borderLeft: `3px solid hsl(${hue} 80% 65%)` }}>
              {m.content}
            </div>
          </div>
        ))}
        {pending && <div className="text-sm text-white/50">{name} is typing…</div>}
        <div ref={end} />
      </div>

      <div className="border-t border-white/10 p-3">
        {err && <p role="alert" className="mb-2 text-sm text-red-300">{err}</p>}
        {note && <p className="mb-2 text-sm text-amber-200">{note}</p>}
        <form onSubmit={(e) => { e.preventDefault(); send(); }} className="flex gap-2">
          <input value={text} onChange={(e) => setText(e.target.value)} maxLength={500} disabled={!canChat}
            placeholder={canChat ? `Message ${name}…` : 'Chat unavailable'} autoComplete="off" />
          <button className="btn btn-primary shrink-0" type="submit" disabled={!canChat || pending || !text.trim()} aria-label="Send">
            <Send className="size-4" />
          </button>
        </form>
        {left !== null && <p className="mt-1.5 text-xs text-white/40">{left} AI messages left today</p>}
      </div>
    </div>
  );
}
