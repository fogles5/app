"use strict";
(function () {
    'use strict';
    if (window.HookupRT)
        return;
    const listeners = {};
    let ws = null;
    let authed = false;
    let retry = 0;
    let pingTimer;
    let stopped = false;
    function emit(type, data) {
        (listeners[type] || []).slice().forEach((fn) => {
            try {
                fn(data);
            }
            catch (e) {
                console.error(e);
            }
        });
    }
    const RT = {
        me: null,
        on(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
        send(obj) {
            if (ws && ws.readyState === WebSocket.OPEN && authed) {
                ws.send(JSON.stringify(obj));
                return true;
            }
            return false;
        },
        get connected() { return authed; }
    };
    function schedule() {
        if (stopped)
            return;
        retry++;
        const delay = Math.min(30000, 1000 * Math.pow(2, Math.min(retry, 5))) + Math.random() * 500;
        window.setTimeout(connect, delay);
    }
    function connect() {
        fetch('/api/ws-token.php', { credentials: 'same-origin', cache: 'no-store' })
            .then((r) => {
            if (r.status === 401) {
                stopped = true;
                return null;
            }
            return r.ok ? r.json() : Promise.reject(new Error('token'));
        })
            .then((data) => {
            if (!data)
                return;
            const proto = location.protocol === 'https:' ? 'wss://' : 'ws://';
            const sock = new WebSocket(proto + location.host + '/ws');
            ws = sock;
            sock.onopen = () => sock.send(JSON.stringify({ type: 'auth', token: data.token }));
            sock.onmessage = (e) => {
                let m;
                try {
                    m = JSON.parse(e.data);
                }
                catch {
                    return;
                }
                if (m.type === 'auth_ok') {
                    authed = true;
                    retry = 0;
                    RT.me = m.user;
                    window.clearInterval(pingTimer);
                    pingTimer = window.setInterval(() => RT.send({ type: 'ping' }), 25000);
                    emit('ready', m);
                    return;
                }
                emit(m.type, m);
            };
            sock.onclose = () => {
                const was = authed;
                authed = false;
                window.clearInterval(pingTimer);
                if (was)
                    emit('disconnected');
                schedule();
            };
        })
            .catch(schedule);
    }
    window.addEventListener('online', () => {
        if (!authed && ws && ws.readyState > WebSocket.OPEN) {
            retry = 0;
            connect();
        }
    });
    window.HookupRT = RT;
    connect();
})();
(function () {
    'use strict';
    const RT = window.HookupRT;
    if (!RT)
        return;
    let unread = parseInt(String(window.__UNREAD__ || '0'), 10) || 0;
    const onMessagesPage = !!window.__ON_MESSAGES__;
    function paint() {
        document.querySelectorAll('[data-unread-badge]').forEach((el) => {
            el.textContent = unread > 99 ? '99+' : String(unread);
            el.hidden = unread <= 0;
        });
        document.title = document.title.replace(/^\(\d+\)\s*/, '');
        if (unread > 0)
            document.title = '(' + unread + ') ' + document.title;
    }
    function toast(from, body) {
        const host = document.getElementById('toast-host');
        if (!host)
            return;
        const a = document.createElement('a');
        a.className = 'toast';
        a.href = '/messages.php?with=' + encodeURIComponent(String(from.id));
        const strong = document.createElement('strong');
        strong.textContent = from.name;
        const span = document.createElement('span');
        span.textContent = body.length > 90 ? body.slice(0, 90) + '…' : body;
        a.append(strong, span);
        host.appendChild(a);
        setTimeout(() => { a.classList.add('toast-out'); setTimeout(() => a.remove(), 300); }, 5000);
    }
    RT.on('ready', () => { RT.send({ type: 'conversations' }); });
    RT.on('conversations', (m) => {
        unread = m.items.reduce((n, c) => n + c.unread, 0);
        paint();
    });
    RT.on('message', (m) => {
        if (!RT.me || m.message.receiver_id !== RT.me.id)
            return;
        if (onMessagesPage)
            return;
        unread++;
        paint();
        if (m.from)
            toast(m.from, m.message.body);
    });
    RT.on('read_ack', () => { if (!onMessagesPage)
        RT.send({ type: 'conversations' }); });
    window.HookupUnread = { set(n) { unread = Math.max(0, n); paint(); } };
    paint();
})();
