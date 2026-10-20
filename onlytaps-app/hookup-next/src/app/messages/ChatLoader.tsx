'use client';

import { useEffect } from 'react';

// chat.js needs window.HookupRT (set by realtime.js, loaded in the layout), so wait for it.
export default function ChatLoader() {
  useEffect(() => {
    let tries = 0;
    let script: HTMLScriptElement | null = null;
    const t = window.setInterval(() => {
      if (window.HookupRT || ++tries > 100) {
        window.clearInterval(t);
        if (window.HookupRT) {
          script = document.createElement('script');
          script.src = '/assets/js/chat.js?v=2';
          document.body.appendChild(script);
        }
      }
    }, 50);
    return () => { window.clearInterval(t); script?.remove(); };
  }, []);
  return null;
}

declare global {
  interface Window { HookupRT?: unknown }
}
