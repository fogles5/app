interface RTUser { id: number; name: string; avatar?: string | null; online?: boolean }
interface ChatMessage {
  id?: number; cid?: string; sender_id: number; receiver_id: number; body: string;
  created_at: string; read?: boolean; pending?: boolean; failed?: boolean; error?: string;
}
interface Conversation { user: RTUser; last: ChatMessage | null; unread: number; typing?: boolean }
interface ServerEvent { type: string; [key: string]: any }
type RTHandler = (data: any) => void;

interface HookupRTApi {
  me: RTUser | null;
  readonly connected: boolean;
  on(type: string, fn: RTHandler): void;
  send(obj: Record<string, unknown>): boolean;
}

interface Window {
  HookupRT?: HookupRTApi;
  HookupUnread?: { set(n: number): void };
  __UNREAD__?: number | string;
  __ON_MESSAGES__?: boolean;
}
