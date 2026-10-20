"use strict";
(function () {
    'use strict';
    const RT = window.HookupRT;
    const root = document.getElementById('chat-app');
    if (!RT || !root)
        return;
    const rt = RT;
    const csrf = root.dataset.csrf || '';
    const q = (sel) => {
        const el = root.querySelector(sel);
        if (!el)
            throw new Error('Missing element ' + sel);
        return el;
    };
    const els = {
        list: q('#conv-list'), search: q('#conv-search'), empty: q('#conv-empty'),
        thread: q('#thread'), placeholder: q('#thread-placeholder'),
        scroll: q('#msg-scroll'), msgs: q('#msg-list'), more: q('#load-more'),
        name: q('#peer-name'), status: q('#peer-status'), avatar: q('#peer-avatar'),
        profile: q('#peer-profile'), report: q('#peer-report'), block: q('#peer-block'),
        form: q('#composer'), input: q('#composer-input'), count: q('#composer-count'),
        back: q('#thread-back'), banner: q('#conn-banner'), menuBtn: q('#peer-menu-btn'), menu: q('#peer-menu')
    };
    const MAX = 2000;
    let convs = new Map();
    const threads = new Map();
    let active = null;
    const typingTimers = {};
    let sentTyping = false;
    let typingStop;
    let loadingOlder = false;
    let initialHandled = false;
    const me = () => (rt.me ? rt.me.id : 0);
    const hue = (id) => (id * 47) % 360;
    const initials = (name) => (name || '?').trim().charAt(0).toUpperCase();
    function avatarEl(user, cls = '') {
        const d = document.createElement('div');
        d.className = 'avatar ' + cls;
        const src = user.avatar && !/^[a-z]+:/i.test(user.avatar) ? '/' + user.avatar.replace(/^\/+/, '') : null;
        if (src) {
            const img = document.createElement('img');
            img.src = src;
            img.alt = '';
            img.loading = 'lazy';
            img.onerror = () => { img.remove(); d.textContent = initials(user.name); };
            d.appendChild(img);
        }
        else {
            d.textContent = initials(user.name);
        }
        d.style.setProperty('--h', String(hue(user.id)));
        return d;
    }
    const fmtTime = (iso) => new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    function daysAgo(iso) {
        const d = new Date(iso), now = new Date();
        return Math.floor((new Date(now.toDateString()).getTime() - new Date(d.toDateString()).getTime()) / 864e5);
    }
    function fmtRel(iso) {
        const days = daysAgo(iso), d = new Date(iso);
        if (days <= 0)
            return fmtTime(iso);
        if (days === 1)
            return 'Yesterday';
        if (days < 7)
            return d.toLocaleDateString([], { weekday: 'short' });
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
    function dayLabel(iso) {
        const days = daysAgo(iso);
        if (days <= 0)
            return 'Today';
        if (days === 1)
            return 'Yesterday';
        return new Date(iso).toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });
    }
    function totalUnread() {
        let n = 0;
        convs.forEach((c) => { n += c.unread; });
        return n;
    }
    function syncHeader() { if (window.HookupUnread)
        window.HookupUnread.set(totalUnread()); }
    const statusText = (online) => (online ? 'Online' : 'Offline');
    function renderList() {
        const term = els.search.value.trim().toLowerCase();
        const items = Array.from(convs.values())
            .sort((a, b) => { var _a, _b, _c, _d; return ((_b = (_a = b.last) === null || _a === void 0 ? void 0 : _a.id) !== null && _b !== void 0 ? _b : 0) - ((_d = (_c = a.last) === null || _c === void 0 ? void 0 : _c.id) !== null && _d !== void 0 ? _d : 0); })
            .filter((c) => !term || c.user.name.toLowerCase().includes(term));
        els.list.textContent = '';
        items.forEach((c) => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'conv' + (c.user.id === active ? ' active' : '') + (c.unread ? ' unread' : '');
            a.dataset.id = String(c.user.id);
            const av = avatarEl(c.user);
            const dot = document.createElement('span');
            dot.className = 'presence' + (c.user.online ? ' on' : '');
            av.appendChild(dot);
            const body = document.createElement('div');
            body.className = 'conv-body';
            const top = document.createElement('div');
            top.className = 'conv-top';
            const nm = document.createElement('span');
            nm.className = 'conv-name';
            nm.textContent = c.user.name;
            const tm = document.createElement('span');
            tm.className = 'conv-time';
            tm.textContent = c.last ? fmtRel(c.last.created_at) : '';
            top.append(nm, tm);
            const bottom = document.createElement('div');
            bottom.className = 'conv-bottom';
            const pv = document.createElement('span');
            pv.className = 'conv-preview';
            if (c.typing) {
                pv.textContent = 'typing…';
                pv.classList.add('typing');
            }
            else if (c.last) {
                pv.textContent = (c.last.sender_id === me() ? 'You: ' : '') + c.last.body;
            }
            else {
                pv.textContent = 'Say hello 👋';
            }
            bottom.appendChild(pv);
            if (c.unread) {
                const b = document.createElement('span');
                b.className = 'conv-badge';
                b.textContent = c.unread > 99 ? '99+' : String(c.unread);
                bottom.appendChild(b);
            }
            body.append(top, bottom);
            a.append(av, body);
            els.list.appendChild(a);
        });
        els.empty.hidden = items.length > 0;
        els.empty.textContent = term
            ? 'No conversations match “' + els.search.value.trim() + '”.'
            : 'No conversations yet. Message someone from a listing or profile to get started.';
    }
    function bubble(m) {
        var _a;
        const mine = m.sender_id === me();
        const row = document.createElement('div');
        row.className = 'msg ' + (mine ? 'mine' : 'theirs') + (m.pending ? ' pending' : '') + (m.failed ? ' failed' : '');
        row.dataset.id = String((_a = m.id) !== null && _a !== void 0 ? _a : '');
        if (m.cid)
            row.dataset.cid = m.cid;
        const b = document.createElement('div');
        b.className = 'bubble';
        b.textContent = m.body;
        const meta = document.createElement('div');
        meta.className = 'msg-meta';
        const t = document.createElement('span');
        t.textContent = m.pending ? 'Sending…' : (m.failed ? (m.error || 'Failed') + ' · tap to retry' : fmtTime(m.created_at));
        meta.appendChild(t);
        if (mine && !m.pending && !m.failed) {
            const r = document.createElement('span');
            r.className = 'ticks' + (m.read ? ' read' : '');
            r.textContent = m.read ? '✓✓' : '✓';
            r.title = m.read ? 'Read' : 'Sent';
            meta.appendChild(r);
        }
        row.append(b, meta);
        if (m.failed)
            row.addEventListener('click', () => retry(m));
        return row;
    }
    function renderThread(keepScroll = false) {
        const t = active !== null ? threads.get(active) : undefined;
        els.msgs.textContent = '';
        let lastDay = '';
        (t ? t.items : []).forEach((m) => {
            const d = new Date(m.created_at).toDateString();
            if (d !== lastDay) {
                lastDay = d;
                const sep = document.createElement('div');
                sep.className = 'day-sep';
                sep.textContent = dayLabel(m.created_at);
                els.msgs.appendChild(sep);
            }
            els.msgs.appendChild(bubble(m));
        });
        els.more.hidden = !(t && t.more);
        if (!keepScroll)
            scrollBottom();
    }
    const scrollBottom = () => { els.scroll.scrollTop = els.scroll.scrollHeight; };
    const nearBottom = () => els.scroll.scrollHeight - els.scroll.scrollTop - els.scroll.clientHeight < 120;
    function setPeerHeader(user) {
        els.name.textContent = user.name;
        els.status.textContent = statusText(user.online);
        els.status.classList.toggle('on', !!user.online);
        const fresh = avatarEl(user, 'avatar-md');
        fresh.id = 'peer-avatar';
        els.avatar.replaceWith(fresh);
        els.avatar = fresh;
        els.profile.href = '/profile.php?id=' + user.id;
        els.report.href = '/report-abuse.php';
        els.block.dataset.id = String(user.id);
    }
    function openThread(id, push = true) {
        active = id;
        root.classList.add('thread-open');
        els.placeholder.hidden = true;
        els.thread.hidden = false;
        const c = convs.get(id);
        if (c)
            setPeerHeader(c.user);
        if (push)
            history.pushState({ with: id }, '', '/messages.php?with=' + id);
        const t = threads.get(id);
        if (t && t.loaded) {
            renderThread();
            markRead();
        }
        else {
            els.msgs.textContent = '';
            els.more.hidden = true;
            rt.send({ type: 'history', with: id });
        }
        renderList();
        els.input.focus({ preventScroll: true });
    }
    function closeThread(push = true) {
        active = null;
        root.classList.remove('thread-open');
        els.thread.hidden = true;
        els.placeholder.hidden = false;
        if (push)
            history.pushState({}, '', '/messages.php');
        renderList();
    }
    function markRead() {
        const c = active !== null ? convs.get(active) : undefined;
        if (c && c.unread > 0 && !document.hidden) {
            c.unread = 0;
            rt.send({ type: 'read', with: active });
            renderList();
            syncHeader();
        }
    }
    const uid = () => 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    function send(body, existing) {
        if (active === null)
            return;
        let t = threads.get(active);
        if (!t) {
            t = { items: [], more: false, loaded: true };
            threads.set(active, t);
        }
        let m;
        if (existing) {
            m = existing;
            m.pending = true;
            m.failed = false;
        }
        else {
            m = { cid: uid(), sender_id: me(), receiver_id: active, body: body || '', created_at: new Date().toISOString(), pending: true };
            t.items.push(m);
        }
        renderThread();
        if (!rt.send({ type: 'send', to: m.receiver_id, body: m.body, cid: m.cid })) {
            m.pending = false;
            m.failed = true;
            m.error = 'Not connected';
            renderThread(true);
        }
    }
    function retry(m) { if (rt.connected)
        send(null, m); }
    function stopTyping() {
        window.clearTimeout(typingStop);
        if (sentTyping && active !== null)
            rt.send({ type: 'typing', to: active, typing: false });
        sentTyping = false;
    }
    rt.on('ready', () => {
        els.banner.hidden = true;
        rt.send({ type: 'conversations' });
        if (active !== null) {
            const t = threads.get(active);
            if (t)
                t.loaded = false;
            rt.send({ type: 'history', with: active });
        }
    });
    rt.on('disconnected', () => { els.banner.hidden = false; });
    rt.on('conversations', (m) => {
        const prevTyping = new Map();
        convs.forEach((c, id) => prevTyping.set(id, !!c.typing));
        const next = new Map();
        m.items.forEach((c) => { c.typing = prevTyping.get(c.user.id) || false; next.set(c.user.id, c); });
        if (active !== null && !next.has(active)) {
            const keep = convs.get(active);
            if (keep)
                next.set(active, keep);
        }
        convs = next;
        renderList();
        syncHeader();
        if (active !== null) {
            const c = convs.get(active);
            if (c)
                setPeerHeader(c.user);
        }
        if (!initialHandled) {
            initialHandled = true;
            routeFromUrl();
        }
    });
    rt.on('history', (m) => {
        const id = m.with.id;
        const t = threads.get(id) || { items: [], more: false, loaded: false };
        if (m.before) {
            const prevH = els.scroll.scrollHeight;
            t.items = m.items.concat(t.items);
            t.more = m.more;
            threads.set(id, t);
            if (id === active) {
                renderThread(true);
                els.scroll.scrollTop = els.scroll.scrollHeight - prevH;
            }
            loadingOlder = false;
            return;
        }
        t.items = m.items;
        t.more = m.more;
        t.loaded = true;
        threads.set(id, t);
        const existing = convs.get(id);
        if (!existing)
            convs.set(id, { user: m.with, last: m.items[m.items.length - 1] || null, unread: 0 });
        else
            existing.user = m.with;
        if (id === active) {
            setPeerHeader(m.with);
            renderThread();
            markRead();
        }
        renderList();
    });
    rt.on('message', (m) => {
        const msg = m.message, peer = m.peer, mine = msg.sender_id === me();
        const t = threads.get(peer);
        let c = convs.get(peer);
        if (!c) {
            const u = m.from || { id: peer, name: 'User #' + peer, avatar: null };
            c = { user: { id: u.id, name: u.name, avatar: u.avatar, online: true }, last: msg, unread: 0 };
            convs.set(peer, c);
        }
        c.last = msg;
        c.typing = false;
        if (t) {
            const idx = m.cid ? t.items.findIndex((x) => x.cid === m.cid) : -1;
            if (idx !== -1)
                t.items[idx] = msg;
            else if (!t.items.some((x) => x.id === msg.id))
                t.items.push(msg);
        }
        if (peer === active) {
            const stick = nearBottom() || mine;
            renderThread(!stick);
            if (!mine) {
                if (document.hidden)
                    c.unread++;
                else
                    rt.send({ type: 'read', with: peer });
            }
        }
        else if (!mine) {
            c.unread++;
        }
        renderList();
        syncHeader();
    });
    rt.on('send_failed', (m) => {
        const t = active !== null ? threads.get(active) : undefined;
        const x = t === null || t === void 0 ? void 0 : t.items.find((i) => i.cid === m.cid);
        if (x) {
            x.pending = false;
            x.failed = true;
            x.error = m.error;
            renderThread(true);
        }
    });
    function paintTyping(id) {
        if (id !== active)
            return;
        const c = convs.get(id);
        els.status.textContent = c && c.typing ? 'typing…' : statusText(c === null || c === void 0 ? void 0 : c.user.online);
        els.status.classList.toggle('typing', !!(c && c.typing));
    }
    rt.on('typing', (m) => {
        const c = convs.get(m.user_id);
        if (!c)
            return;
        c.typing = m.typing;
        window.clearTimeout(typingTimers[m.user_id]);
        if (m.typing)
            typingTimers[m.user_id] = window.setTimeout(() => { c.typing = false; paintTyping(m.user_id); renderList(); }, 4000);
        paintTyping(m.user_id);
        renderList();
    });
    rt.on('read', (m) => {
        const t = threads.get(m.by);
        if (!t)
            return;
        t.items.forEach((x) => { if (x.sender_id === me())
            x.read = true; });
        if (m.by === active)
            renderThread(true);
    });
    rt.on('read_ack', () => { rt.send({ type: 'conversations' }); });
    rt.on('presence', (m) => {
        const c = convs.get(m.user_id);
        if (!c)
            return;
        c.user.online = m.online;
        if (m.user_id === active) {
            els.status.textContent = statusText(m.online);
            els.status.classList.toggle('on', m.online);
        }
        renderList();
    });
    els.list.addEventListener('click', (e) => {
        const el = e.target.closest('.conv');
        if (el)
            openThread(parseInt(el.dataset.id || '0', 10));
    });
    els.search.addEventListener('input', renderList);
    els.back.addEventListener('click', () => closeThread());
    window.addEventListener('popstate', () => routeFromUrl());
    document.addEventListener('visibilitychange', () => { if (!document.hidden)
        markRead(); });
    window.addEventListener('focus', markRead);
    function loadOlder() {
        const t = active !== null ? threads.get(active) : undefined;
        if (t && t.more && !loadingOlder && t.items.length && t.items[0].id) {
            loadingOlder = true;
            rt.send({ type: 'history', with: active, before: t.items[0].id });
        }
    }
    els.scroll.addEventListener('scroll', () => { if (els.scroll.scrollTop < 60)
        loadOlder(); });
    els.more.addEventListener('click', loadOlder);
    function autosize() {
        els.input.style.height = 'auto';
        els.input.style.height = Math.min(els.input.scrollHeight, 140) + 'px';
        const n = els.input.value.length;
        els.count.textContent = n > MAX - 200 ? String(MAX - n) : '';
        els.count.classList.toggle('over', n > MAX);
    }
    els.input.addEventListener('input', () => {
        autosize();
        if (active === null)
            return;
        if (!sentTyping && els.input.value.trim()) {
            sentTyping = true;
            rt.send({ type: 'typing', to: active, typing: true });
        }
        window.clearTimeout(typingStop);
        typingStop = window.setTimeout(stopTyping, 2000);
    });
    els.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            els.form.requestSubmit();
        }
    });
    els.form.addEventListener('submit', (e) => {
        e.preventDefault();
        const body = els.input.value.trim();
        if (!body || active === null || body.length > MAX)
            return;
        stopTyping();
        send(body);
        els.input.value = '';
        autosize();
    });
    els.menuBtn.addEventListener('click', (e) => { e.stopPropagation(); els.menu.hidden = !els.menu.hidden; });
    document.addEventListener('click', () => { els.menu.hidden = true; });
    els.block.addEventListener('click', () => {
        const id = els.block.dataset.id;
        if (!id || !confirm('Block ' + els.name.textContent + '? They will no longer be able to message you.'))
            return;
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('user_id', id);
        fd.append('action', 'block');
        fetch('/api/block.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => { alert(d.ok ? 'User blocked.' : (d.error || 'Could not block user.')); });
    });
    function routeFromUrl() {
        const id = parseInt(new URLSearchParams(location.search).get('with') || '0', 10);
        if (id && id !== me()) {
            if (convs.has(id))
                openThread(id, false);
            else {
                active = id;
                root.classList.add('thread-open');
                els.placeholder.hidden = true;
                els.thread.hidden = false;
                els.msgs.textContent = '';
                rt.send({ type: 'history', with: id });
            }
        }
        else if (active !== null) {
            closeThread(false);
        }
    }
    els.banner.hidden = rt.connected;
    autosize();
})();
