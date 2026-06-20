/* ============================================================================
   Chat seguro Paciente ↔ Médico — motor de UI compartido por ambos portales.
   Autocontenido: habla con su proxy server-side vía la meta csrf-token.
   Configurar antes de cargar este archivo con window.HGCHAT_CONFIG:
   {
     role:      'doctor' | 'patient',
     proxyUrl:  '/api/doctor-proxy.php' | '/api/portal-proxy.php',
     apiBase:   '/portal-doctor/me/chat' | '/portal/me/chat',
     peerIdKey: 'patient_id' | 'doctor_id',   // identifica al interlocutor
     onThreadOpen: function (thread) {}        // opcional (riel de contexto)
   }
   ============================================================================ */
(function () {
    'use strict';

    var cfg = window.HGCHAT_CONFIG;
    if (!cfg) return;
    var root = document.querySelector('[data-hgchat]');
    if (!root) return;
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var $ = function (sel) { return root.querySelector(sel); };
    var els = {
        threads:   $('[data-hg-threads]'),
        search:    $('[data-hg-search]'),
        thread:    $('[data-hg-thread]'),
        msgs:      $('[data-hg-msgs]'),
        peerName:  $('[data-hg-peer-name]'),
        peerSub:   $('[data-hg-peer-sub]'),
        peerAv:    $('[data-hg-peer-av]'),
        composer:  $('[data-hg-composer]'),
        input:     $('[data-hg-input]'),
        send:      $('[data-hg-send]'),
        attach:    $('[data-hg-attach]'),
        file:      $('[data-hg-file]'),
        back:      $('[data-hg-back]'),
        newBtn:    $('[data-hg-new]'),
        sheet:     $('[data-hg-sheet]'),
        sheetList: $('[data-hg-sheet-list]')
    };

    var state = { threads: [], filter: '', active: null, messages: [], lastId: 0, sending: false, timer: null };

    // ── Helpers ──────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function initials(name) {
        var p = String(name || '').trim().split(/\s+/).filter(Boolean);
        var s = (p[0] ? p[0][0] : '') + (p[1] ? p[1][0] : '');
        return (s || '?').toUpperCase();
    }
    function parseDT(s) {
        // "2026-06-20 06:59:03" → {Y,Mo,D,H,Mi} sin sorpresas de zona horaria.
        var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s || ''));
        if (!m) return null;
        return { Y: +m[1], Mo: +m[2], D: +m[3], H: +m[4], Mi: +m[5] };
    }
    function fmtTime(s) {
        var t = parseDT(s); if (!t) return '';
        return ('0' + t.H).slice(-2) + ':' + ('0' + t.Mi).slice(-2);
    }
    var MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    function dayKey(s) { var t = parseDT(s); return t ? t.Y * 10000 + t.Mo * 100 + t.D : 0; }
    function fmtDay(s) {
        var t = parseDT(s); if (!t) return '';
        var now = new Date();
        var todayK = now.getFullYear() * 10000 + (now.getMonth() + 1) * 100 + now.getDate();
        var k = dayKey(s);
        if (k === todayK) return 'Hoy';
        if (k === todayK - 1) return 'Ayer';
        return t.D + ' ' + MES[t.Mo - 1] + ' ' + t.Y;
    }
    function fmtListTime(s) {
        if (!s) return '';
        var k = dayKey(s);
        var now = new Date();
        var todayK = now.getFullYear() * 10000 + (now.getMonth() + 1) * 100 + now.getDate();
        if (k === todayK) return fmtTime(s);
        if (k === todayK - 1) return 'Ayer';
        var t = parseDT(s); return t ? (t.D + ' ' + MES[t.Mo - 1]) : '';
    }
    function icons() { if (window.lucide) window.lucide.createIcons(); }

    function api(method, path, payload) {
        var isGet = method === 'GET';
        return fetch(cfg.proxyUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ method: method, path: cfg.apiBase + path, query: isGet ? payload : undefined, body: isGet ? undefined : payload })
        }).then(function (r) {
            return r.json().catch(function () { return null; }).then(function (j) {
                return { ok: r.ok && j && j.success, status: r.status, data: j && j.data, message: j && j.message };
            });
        });
    }

    // ── Lista de conversaciones ───────────────────────────────────────────────
    function renderThreads() {
        var q = state.filter.trim().toLowerCase();
        var list = state.threads.filter(function (t) { return !q || (t.name || '').toLowerCase().indexOf(q) !== -1; });
        if (!list.length) {
            els.threads.innerHTML = '<p class="hgchat-list-empty">' +
                (state.threads.length ? 'Sin coincidencias.' : 'Aún no tienes conversaciones.<br>Usa “Nuevo” para empezar.') + '</p>';
            return;
        }
        els.threads.innerHTML = list.map(function (t) {
            var active = state.active && state.active.id === t.id ? ' on' : '';
            var unread = t.unread > 0 ? ' unread' : '';
            var badge = t.unread > 0 ? '<span class="hgchat-badge">' + (t.unread > 99 ? '99+' : t.unread) + '</span>' : '';
            var pre = t.last_sender && cfg.role === t.last_sender ? 'Tú: ' : '';
            return '<button type="button" class="hgchat-item' + active + unread + '" data-hg-open="' + t.id + '">' +
                '<span class="hgchat-av">' + esc(initials(t.name)) + '</span>' +
                '<span class="hgchat-item-body">' +
                    '<span class="hgchat-item-top"><span class="hgchat-item-name">' + esc(t.name) + '</span>' +
                    '<span class="hgchat-item-time">' + esc(fmtListTime(t.last_message_at)) + '</span></span>' +
                    '<span class="hgchat-item-preview">' + esc(pre + (t.preview || 'Toca para escribir')) + '</span>' +
                '</span>' + badge + '</button>';
        }).join('');
    }

    function loadThreads() {
        return api('GET', '/threads').then(function (r) {
            if (r.ok && r.data) { state.threads = r.data.threads || []; renderThreads(); }
        });
    }

    // ── Mensajes ──────────────────────────────────────────────────────────────
    function nearBottom() {
        var el = els.msgs;
        return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
    }
    function scrollBottom() { els.msgs.scrollTop = els.msgs.scrollHeight; }

    function cardHtml(card) {
        var map = {
            prescription: ['file-text', 'Receta médica'], lab: ['flask-conical', 'Resultado de laboratorio'],
            imaging: ['scan', 'Estudio de imagen'], appointment: ['calendar-check', 'Cita médica'],
            consultation: ['stethoscope', 'Consulta médica']
        };
        var info = map[card.type] || ['paperclip', 'Documento'];
        var meta = card.meta || {};
        var href = (typeof cfg.cardHref === 'function') ? (cfg.cardHref(card) || '') : '';
        var open = href ? '<a class="hgchat-card" href="' + esc(href) + '" target="_blank" rel="noopener">' : '<span class="hgchat-card">';
        var close = href ? '</a>' : '</span>';
        return open +
            '<span class="hgchat-card-ic"><i data-lucide="' + info[0] + '"></i></span>' +
            '<span class="hgchat-card-meta"><strong>' + esc(meta.title || info[1]) + '</strong>' +
            (meta.subtitle ? '<small>' + esc(meta.subtitle) + '</small>' : '') + '</span>' +
            '<span class="hgchat-card-go"><i data-lucide="chevron-right"></i></span>' + close;
    }

    function fmtSize(b) {
        b = +b || 0;
        if (b < 1024) return b + ' B';
        if (b < 1048576) return Math.round(b / 1024) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function attachHtml(a) {
        var url = cfg.fileUrl + '?id=' + a.id;
        if (a.is_image) {
            return '<a class="hgchat-att-imgwrap" href="' + url + '" target="_blank" rel="noopener">' +
                '<img class="hgchat-att-img" src="' + url + '" alt="' + esc(a.name) + '" loading="lazy"></a>';
        }
        return '<a class="hgchat-att-file" href="' + url + '" target="_blank" rel="noopener">' +
            '<span class="hgchat-att-ic"><i data-lucide="file-text"></i></span>' +
            '<span class="hgchat-att-meta"><strong>' + esc(a.name) + '</strong><small>' + fmtSize(a.size) + '</small></span>' +
            '<span class="hgchat-att-dl"><i data-lucide="download"></i></span></a>';
    }

    function renderMessages() {
        var html = '', lastDay = 0, lastSender = '';
        state.messages.forEach(function (m) {
            var dk = dayKey(m.created_at);
            if (dk !== lastDay) { html += '<div class="hgchat-day">' + esc(fmtDay(m.created_at)) + '</div>'; lastDay = dk; lastSender = ''; }
            if (m.kind === 'system' || m.deleted) {
                html += '<div class="hgchat-sys">' + esc(m.deleted ? 'Mensaje eliminado' : (m.body || '')) + '</div>';
                lastSender = ''; return;
            }
            var mine = !!m.mine;
            var first = lastSender !== m.sender_type;
            lastSender = m.sender_type;
            var ticks = '';
            if (mine) {
                ticks = '<span class="hgchat-ticks' + (m.read ? ' read' : '') + '">' +
                    '<i data-lucide="' + (m.read ? 'check-check' : 'check') + '"></i></span>';
            }
            var inner = '';
            if (m.attachment) inner += attachHtml(m.attachment);
            if (m.card) inner += cardHtml(m.card);
            if (m.body) inner += '<span class="hgchat-bubble-text">' + esc(m.body) + '</span>';
            html += '<div class="hgchat-row' + (mine ? ' mine' : '') + (first ? ' first' : '') + '">' +
                '<div class="hgchat-bubble">' + inner +
                '<span class="hgchat-meta"><span>' + esc(fmtTime(m.created_at)) + '</span>' + ticks + '</span>' +
                '</div></div>';
        });
        els.msgs.innerHTML = html;
        icons();
    }

    function loadMessages(mode) {
        if (!state.active) return Promise.resolve();
        var full = mode === 'full';
        var after = full ? 0 : state.lastId;
        var keepBottom = full || nearBottom();
        return api('GET', '/threads/' + state.active.id + '/messages', { after_id: after }).then(function (r) {
            if (!r.ok || !r.data) return;
            var incoming = r.data.messages || [];
            if (full) state.messages = incoming;
            else if (incoming.length) state.messages = state.messages.concat(incoming);
            else return; // nada nuevo
            state.messages.forEach(function (m) { if (m.id > state.lastId) state.lastId = m.id; });
            renderMessages();
            if (keepBottom) scrollBottom();
        });
    }

    function setHeader(t) {
        els.peerName.textContent = t.name || '';
        els.peerAv.textContent = initials(t.name);
        if (els.peerSub) {
            if (t.specialty) els.peerSub.innerHTML = '<i data-lucide="stethoscope"></i>' + esc(t.specialty);
            else els.peerSub.innerHTML = '<i data-lucide="shield-check"></i>Conversación protegida';
        }
    }

    function openThread(t) {
        state.active = t; state.messages = []; state.lastId = 0;
        els.thread.classList.remove('is-empty');
        root.classList.add('show-thread'); root.classList.remove('show-context');
        setHeader(t);
        els.msgs.innerHTML = '<div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>';
        renderThreads();
        icons();
        loadMessages('full').then(function () {
            scrollBottom();
            // El servidor marca leídos al cargar; refrescamos la lista para limpiar el badge.
            var th = state.threads.filter(function (x) { return x.id === t.id; })[0];
            if (th) { th.unread = 0; renderThreads(); }
            loadThreads();
        });
        if (typeof cfg.onThreadOpen === 'function') cfg.onThreadOpen(t);
        if (els.input) els.input.focus();
    }

    function normalizeThread(th) {
        return {
            id: th.id, name: th.name, specialty: th.specialty || '',
            last_message_at: th.last_message_at || null, preview: th.preview || '',
            last_sender: th.last_sender || null, unread: th.unread || 0,
            peerId: th[cfg.peerIdKey]
        };
    }

    // ── Enviar ────────────────────────────────────────────────────────────────
    function send() {
        if (!state.active || state.sending) return;
        var text = (els.input.value || '').trim();
        if (!text) return;
        state.sending = true; els.send.disabled = true;
        els.input.value = ''; autosize();
        api('POST', '/threads/' + state.active.id + '/messages', { body: text }).then(function (r) {
            state.sending = false; els.send.disabled = false;
            if (r.ok && r.data && r.data.message) {
                state.messages.push(r.data.message);
                if (r.data.message.id > state.lastId) state.lastId = r.data.message.id;
                renderMessages(); scrollBottom();
                loadThreads();
            } else {
                els.input.value = text; autosize();
                flash(r.message || 'No se pudo enviar el mensaje.');
            }
            els.input.focus();
        }).catch(function () {
            state.sending = false; els.send.disabled = false;
            els.input.value = text; autosize();
            flash('Sin conexión. Intenta de nuevo.');
        });
    }

    function flash(msg) {
        var el = root.querySelector('[data-hg-flash]');
        if (!el) return;
        el.textContent = msg; el.classList.add('show');
        setTimeout(function () { el.classList.remove('show'); }, 3200);
    }

    function autosize() {
        var el = els.input; if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 160) + 'px';
        els.send.disabled = !(el.value || '').trim() || state.sending;
    }

    var HG_ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    function handleFile(file) {
        if (!file || !state.active || state.sending) return;
        if (HG_ALLOWED.indexOf(file.type) === -1) { flash('Tipo de archivo no permitido (imágenes o PDF).'); return; }
        if (file.size > 5 * 1024 * 1024) { flash('El archivo supera el límite de 5 MB.'); return; }
        state.sending = true; els.send.disabled = true; if (els.attach) els.attach.disabled = true;
        flash('Subiendo…');
        var reader = new FileReader();
        reader.onerror = function () { state.sending = false; els.send.disabled = false; if (els.attach) els.attach.disabled = false; flash('No se pudo leer el archivo.'); };
        reader.onload = function () {
            var dataUrl = String(reader.result || '');
            var b64 = dataUrl.indexOf('base64,') !== -1 ? dataUrl.slice(dataUrl.indexOf('base64,') + 7) : '';
            api('POST', '/threads/' + state.active.id + '/attachments', { filename: file.name, mime: file.type, data: b64 }).then(function (r) {
                state.sending = false; els.send.disabled = false; if (els.attach) els.attach.disabled = false;
                if (r.ok && r.data && r.data.message) {
                    state.messages.push(r.data.message);
                    if (r.data.message.id > state.lastId) state.lastId = r.data.message.id;
                    renderMessages(); scrollBottom(); loadThreads();
                } else { flash(r.message || 'No se pudo subir el archivo.'); }
            }).catch(function () { state.sending = false; els.send.disabled = false; if (els.attach) els.attach.disabled = false; flash('Sin conexión. Intenta de nuevo.'); });
        };
        reader.readAsDataURL(file);
    }

    // ── Nuevo chat (contactos) ────────────────────────────────────────────────
    function openSheet() {
        els.sheet.classList.add('open');
        els.sheetList.innerHTML = '<div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>';
        api('GET', '/contacts').then(function (r) {
            var list = (r.data && r.data.contacts) || [];
            if (!list.length) { els.sheetList.innerHTML = '<p class="hgchat-list-empty">No hay contactos disponibles todavía.</p>'; return; }
            els.sheetList.innerHTML = list.map(function (c) {
                var sub = c.specialty || (c.last_seen ? 'Última atención: ' + fmtListTime(c.last_seen) : '');
                return '<button type="button" class="hgchat-item" data-hg-contact="' + c[cfg.peerIdKey] + '">' +
                    '<span class="hgchat-av">' + esc(initials(c.name)) + '</span>' +
                    '<span class="hgchat-item-body"><span class="hgchat-item-top"><span class="hgchat-item-name">' + esc(c.name) + '</span></span>' +
                    '<span class="hgchat-item-preview">' + esc(sub) + '</span></span>' +
                    (c.unread > 0 ? '<span class="hgchat-badge">' + c.unread + '</span>' : '') + '</button>';
            }).join('');
        });
    }
    function closeSheet() { els.sheet.classList.remove('open'); }

    function startWith(peerId) {
        var payload = {}; payload[cfg.peerIdKey] = +peerId;
        api('POST', '/threads', payload).then(function (r) {
            if (!r.ok || !r.data) { flash(r.message || 'No se pudo abrir la conversación.'); return; }
            closeSheet();
            var t = normalizeThread(r.data.thread);
            loadThreads().then(function () { openThread(t); });
        });
    }

    // ── Polling adaptativo ────────────────────────────────────────────────────
    function schedule() {
        clearTimeout(state.timer);
        var hidden = document.hidden;
        var ms = hidden ? 20000 : (state.active ? 4000 : 9000);
        state.timer = setTimeout(tick, ms);
    }
    function tick() {
        var jobs = [loadThreads()];
        if (state.active && !document.hidden) {
            // Si mi último mensaje sigue sin leerse, recarga completa para captar el recibo de lectura.
            var last = state.messages[state.messages.length - 1];
            jobs.push(loadMessages(last && last.mine && !last.read ? 'full' : 'inc'));
        }
        Promise.all(jobs).catch(function () {}).then(schedule);
    }

    // ── Eventos ───────────────────────────────────────────────────────────────
    els.threads.addEventListener('click', function (e) {
        var b = e.target.closest('[data-hg-open]'); if (!b) return;
        var id = +b.getAttribute('data-hg-open');
        var t = state.threads.filter(function (x) { return x.id === id; })[0];
        if (t) openThread(normalizeThread(t));
    });
    if (els.search) els.search.addEventListener('input', function () { state.filter = els.search.value; renderThreads(); });
    if (els.composer) els.composer.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    if (els.input) {
        els.input.addEventListener('input', autosize);
        els.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
    }
    if (els.attach && els.file) {
        els.attach.addEventListener('click', function () { if (state.active) els.file.click(); });
        els.file.addEventListener('change', function () {
            var f = els.file.files && els.file.files[0];
            els.file.value = '';
            handleFile(f);
        });
    }
    if (els.back) els.back.addEventListener('click', function () { root.classList.remove('show-thread', 'show-context'); state.active = null; renderThreads(); });
    if (els.newBtn) els.newBtn.addEventListener('click', openSheet);
    if (els.sheet) {
        els.sheet.addEventListener('click', function (e) {
            if (e.target === els.sheet || e.target.closest('[data-hg-sheet-close]')) { closeSheet(); return; }
            var c = e.target.closest('[data-hg-contact]'); if (c) startWith(c.getAttribute('data-hg-contact'));
        });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });

    /** Enviar una tarjeta del expediente (lo invoca la hoja "Compartir" del host). */
    function sendCard(card) {
        if (!state.active || state.sending || !card || !card.type) return;
        state.sending = true; els.send.disabled = true;
        api('POST', '/threads/' + state.active.id + '/messages', {
            kind: 'card', card_type: card.type, card_ref: String(card.ref || ''), card_meta: card.meta || {}
        }).then(function (r) {
            state.sending = false; els.send.disabled = false;
            if (r.ok && r.data && r.data.message) {
                state.messages.push(r.data.message);
                if (r.data.message.id > state.lastId) state.lastId = r.data.message.id;
                renderMessages(); scrollBottom(); loadThreads();
            } else { flash(r.message || 'No se pudo compartir.'); }
        }).catch(function () { state.sending = false; els.send.disabled = false; flash('Sin conexión. Intenta de nuevo.'); });
    }

    // Exponer utilidades al host (la hoja "Compartir" vive en la página por ser rol-específica).
    window.HGChat = {
        reload: loadThreads, openContacts: openSheet, sendCard: sendCard, state: state,
        activeThreadId: function () { return state.active && state.active.id; }
    };

    // ── Init ──────────────────────────────────────────────────────────────────
    autosize();
    loadThreads().then(function () {
        // Autoabrir si la URL trae ?with=<peerId> (deep-link desde otra página del portal).
        var m = /[?&]with=(\d+)/.exec(location.search);
        if (m) startWith(m[1]);
    });
    schedule();
})();
