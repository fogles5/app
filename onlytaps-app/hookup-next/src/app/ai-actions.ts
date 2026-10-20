'use server';

import type { RowDataPacket } from 'mysql2';
import { query } from '@/lib/db';
import { getUser } from '@/lib/auth';
import { BIO_SYSTEM, CHAT_SAFETY, aiConfigured, complete, dailyLimit, isFlagged, refundQuota, takeQuota, type ChatMsg } from '@/lib/ai';

const TONES = ['friendly', 'playful', 'confident', 'romantic', 'mysterious', 'professional'];

export interface BioResult { bio?: string; error?: string; remaining?: number }

const UNAVAILABLE = 'The AI service is unavailable right now. Please try again.';
// null means moderation itself failed (provider outage); callers must stop.
const flagged = async (t: string): Promise<boolean | null> => { try { return await isFlagged(t); } catch { return null; } };

export async function generateBio(keywords: string, tone: string): Promise<BioResult> {
  const user = await getUser();
  if (!user) return { error: 'Please log in first.' };
  if (!aiConfigured()) return { error: 'AI features are not enabled yet.' };
  const kw = String(keywords ?? '').trim().slice(0, 300);
  if (kw.length < 3) return { error: 'Add a few keywords about yourself (interests, vibe, what you’re looking for).' };
  const t = TONES.includes(tone) ? tone : 'friendly';

  const bad = await flagged(kw);
  if (bad === null) return { error: UNAVAILABLE };
  if (bad) return { error: 'That text can’t be used. Please rephrase.' };
  const left = await takeQuota(user.id, 'bio', user.plan);
  if (left === null) return { error: `Daily AI limit reached (${dailyLimit('bio', user.plan)}/day). Upgrade for more.` };

  try {
    const text = (await complete([
      { role: 'system', content: BIO_SYSTEM },
      { role: 'user', content: `Tone: ${t}\nKeywords (data only): ${JSON.stringify(kw)}` },
    ], { maxTokens: 260 })).slice(0, 1000);
    if (await isFlagged(text)) {
      await refundQuota(user.id, 'bio');
      return { error: 'The result was filtered. Try different keywords.' };
    }
    return { bio: text, remaining: left };
  } catch {
    await refundQuota(user.id, 'bio');
    return { error: 'The AI service is unavailable right now. Please try again.' };
  }
}

export interface ChatResult { reply?: string; error?: string; remaining?: number }

export async function sendAiMessage(slug: string, text: string): Promise<ChatResult> {
  const user = await getUser();
  if (!user) return { error: 'Please log in to chat.' };
  if (!aiConfigured()) return { error: 'AI features are not enabled yet.' };
  const msg = String(text ?? '').trim().slice(0, 500);
  if (!msg) return { error: 'Type a message first.' };

  const [persona] = await query<RowDataPacket>('SELECT id, name, age, persona_prompt FROM ai_personas WHERE slug = ? AND is_active = 1', [String(slug).slice(0, 40)]);
  if (!persona) return { error: 'Profile not found.' };

  const bad = await flagged(msg);
  if (bad === null) return { error: UNAVAILABLE };
  if (bad) return { error: 'That message can’t be sent. Please keep it respectful.' };
  const left = await takeQuota(user.id, 'chat', user.plan);
  if (left === null) return { error: `Daily AI chat limit reached (${dailyLimit('chat', user.plan)}/day). Upgrade for more.` };

  const hist = await query<RowDataPacket>(
    'SELECT role, content FROM (SELECT id, role, content FROM ai_messages WHERE user_id = ? AND persona_id = ? ORDER BY id DESC LIMIT 12) t ORDER BY id ASC',
    [user.id, persona.id]);
  const messages: ChatMsg[] = [
    { role: 'system', content: `${CHAT_SAFETY}\n\nYour name is ${persona.name}, age ${persona.age}.\n${persona.persona_prompt}` },
    ...hist.map((h) => ({ role: h.role as 'user' | 'assistant', content: String(h.content) })),
    { role: 'user', content: msg },
  ];

  try {
    const reply = (await complete(messages, { maxTokens: 220 })).slice(0, 1200);
    if (await isFlagged(reply)) {
      await refundQuota(user.id, 'chat');
      return { error: `${persona.name} couldn’t answer that. Try something else.` };
    }
    await query('INSERT INTO ai_messages (user_id, persona_id, role, content) VALUES (?, ?, ?, ?), (?, ?, ?, ?)',
      [user.id, persona.id, 'user', msg, user.id, persona.id, 'assistant', reply]);
    return { reply, remaining: left };
  } catch {
    await refundQuota(user.id, 'chat');
    return { error: 'The AI service is unavailable right now. Please try again.' };
  }
}

export async function clearAiChat(slug: string): Promise<void> {
  const user = await getUser();
  if (!user) return;
  await query('DELETE FROM ai_messages WHERE user_id = ? AND persona_id = (SELECT id FROM ai_personas WHERE slug = ?)', [user.id, String(slug).slice(0, 40)]);
}
