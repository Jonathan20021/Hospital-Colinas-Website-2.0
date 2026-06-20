(function () {
    'use strict';

    const root = document.getElementById('colinasAi');
    if (!root) return;

    const dataEl = document.getElementById('colinasAiData');
    let data = { endpoint: '', doctors: [], assistantName: 'Colinas IA', welcomeMessage: '', basePath: '/' };
    try {
        if (dataEl) data = Object.assign(data, JSON.parse(dataEl.textContent || '{}'));
    } catch (e) { /* ignore */ }

    const doctorMap = new Map((data.doctors || []).map((d) => [d.slug, d]));
    const STORAGE_KEY = 'colinasAi_conversation_v1';
    const FIRST_VISIT_KEY = 'colinasAi_seen_v1';
    const MAX_TURNS = 24;

    const fab = root.querySelector('.cai-fab');
    const panel = root.querySelector('.cai-panel');
    const closeBtn = root.querySelector('.cai-close');
    const resetBtn = root.querySelector('.cai-reset');
    const messagesEl = root.querySelector('.cai-messages');
    const form = root.querySelector('.cai-form');
    const input = root.querySelector('.cai-input');
    const sendBtn = root.querySelector('.cai-send');
    const quickEl = root.querySelector('.cai-quick');

    const STARTERS = [
        { label: 'Hazme un tour por el sitio', icon: 'compass', text: 'Hazme un tour rápido por la página y muéstrame las secciones principales.' },
        { label: 'Buscar especialista', icon: 'user-round-search', text: 'Necesito un especialista. ¿Qué áreas tienen disponibles?' },
        { label: 'Servicios del hospital', icon: 'stethoscope', text: '¿Qué servicios ofrece el hospital?' },
        { label: 'Cómo agendar cita', icon: 'calendar-days', text: '¿Cómo agendo una cita?' },
        { label: 'Horarios y ubicación', icon: 'map-pin', text: '¿Dónde están y cuáles son los horarios?' },
        { label: 'Seguros aceptados', icon: 'shield-check', text: '¿Con qué seguros trabajan?' },
    ];

    const MOBILE_BREAKPOINT = 640;
    const isMobile = () => window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;

    // ============ State ============
    let conversation = loadConversation();
    let lastUserMessage = null; // for retry

    function loadConversation() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr.slice(-MAX_TURNS) : [];
        } catch (e) { return []; }
    }

    function persistConversation() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(conversation.slice(-MAX_TURNS)));
        } catch (e) { /* quota or private mode — ignore */ }
    }

    function clearConversation() {
        conversation = [];
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
    }

    // ============ Helpers ============
    function refreshIcons() {
        if (window.lucide) window.lucide.createIcons();
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function basePath(path) {
        const base = data.basePath || '/';
        if (!path) return base;
        if (path.startsWith('#')) return base + path;
        return base + path.replace(/^\/+/, '');
    }

    function lockBody() {
        if (!isMobile()) return;
        document.body.classList.add('cai-locked');
        document.body.dataset.caiScrollY = String(window.scrollY);
        document.body.style.top = `-${window.scrollY}px`;
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function unlockBody() {
        if (!document.body.classList.contains('cai-locked')) return;
        const scrollY = parseInt(document.body.dataset.caiScrollY || '0', 10);
        document.body.classList.remove('cai-locked');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        delete document.body.dataset.caiScrollY;
        window.scrollTo(0, scrollY);
    }

    // ============ Panel open/close ============
    function openPanel() {
        root.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        root.classList.remove('cai-attention');
        lockBody();
        try { localStorage.setItem(FIRST_VISIT_KEY, '1'); } catch (e) { /* ignore */ }
        if (!isMobile()) setTimeout(() => input?.focus(), 200);

        if (messagesEl.children.length === 0) {
            renderInitialState();
        }
        refreshIcons();
    }

    function closePanel() {
        root.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
        unlockBody();
    }

    function renderInitialState() {
        messagesEl.innerHTML = '';
        if (conversation.length === 0) {
            renderAssistantMessage(timeGreeting() + (data.welcomeMessage || `Soy ${data.assistantName}, tu asistente del hospital. ¿En qué puedo ayudarte?`), { skipPersist: true });
            renderStarterChips();
        } else {
            conversation.forEach((m) => {
                if (m.role === 'user') renderUserMessage(m.content, { skipPersist: true });
                else if (m.role === 'assistant') renderAssistantMessage(m.content, { skipPersist: true });
            });
        }
    }

    function timeGreeting() {
        const h = new Date().getHours();
        if (h >= 5 && h < 12) return '¡Buenos días! ';
        if (h >= 12 && h < 19) return '¡Buenas tardes! ';
        return '¡Buenas noches! ';
    }

    function resetConversation() {
        clearConversation();
        messagesEl.innerHTML = '';
        renderInitialState();
        scrollToBottom();
    }

    // ============ Markdown / message parsing ============
    // Tags handled: [[doctor:slug]] [[link:t|l]] [[action:t|l]] [[scroll:#x]] [[suggest:a|b|c]]
    function parseAssistantMessage(text) {
        const result = { html: '', cards: [], scrollTo: null, actions: [], suggestions: [] };
        let body = '';
        let i = 0;
        const len = text.length;

        while (i < len) {
            const startIdx = text.indexOf('[[', i);
            if (startIdx === -1) {
                body += renderMarkdown(text.slice(i));
                break;
            }
            body += renderMarkdown(text.slice(i, startIdx));
            const endIdx = text.indexOf(']]', startIdx + 2);
            if (endIdx === -1) {
                body += renderMarkdown(text.slice(startIdx));
                break;
            }
            const tag = text.slice(startIdx + 2, endIdx);
            handleTag(tag, result, (html) => { body += html; });
            i = endIdx + 2;
        }

        result.html = body.trim();
        return result;
    }

    function handleTag(tag, result, append) {
        const sep = tag.indexOf(':');
        if (sep === -1) return;
        const type = tag.slice(0, sep).trim();
        const value = tag.slice(sep + 1);

        if (type === 'doctor') {
            const doctor = doctorMap.get(value.trim());
            if (doctor) result.cards.push(doctor);
            return;
        }
        if (type === 'link') {
            const [target, label] = value.split('|');
            const href = basePath((target || '').trim());
            const txt = ((label || target) || '').trim();
            append(`<a href="${escapeHtml(href)}" class="cai-link" target="_self">${escapeHtml(txt)} <i data-lucide="arrow-up-right"></i></a>`);
            return;
        }
        if (type === 'action') {
            const [actionType, label] = value.split('|');
            result.actions.push({ type: (actionType || '').trim(), label: ((label || actionType) || '').trim() });
            return;
        }
        if (type === 'scroll') {
            result.scrollTo = value.trim();
            return;
        }
        if (type === 'suggest') {
            value.split('|').forEach((s) => {
                const t = s.trim();
                if (t) result.suggestions.push(t);
            });
            return;
        }
    }

    // Lightweight markdown: paragraphs, lists, bold, italic, inline code, links, auto-links
    function renderMarkdown(text) {
        if (!text) return '';
        const lines = text.split('\n');
        const out = [];
        let listKind = null; // 'ul' | 'ol' | null
        let listBuffer = [];

        const flushList = () => {
            if (listKind && listBuffer.length) {
                out.push(`<${listKind}>` + listBuffer.map((it) => `<li>${it}</li>`).join('') + `</${listKind}>`);
            }
            listKind = null;
            listBuffer = [];
        };

        for (let raw of lines) {
            const line = raw.replace(/\s+$/, '');
            const ulMatch = line.match(/^\s*[-•*]\s+(.+)/);
            const olMatch = line.match(/^\s*(\d+)[.)]\s+(.+)/);

            if (ulMatch) {
                if (listKind && listKind !== 'ul') flushList();
                listKind = 'ul';
                listBuffer.push(renderInline(ulMatch[1]));
                continue;
            }
            if (olMatch) {
                if (listKind && listKind !== 'ol') flushList();
                listKind = 'ol';
                listBuffer.push(renderInline(olMatch[2]));
                continue;
            }
            if (line.trim() === '') {
                flushList();
                out.push('<br>');
                continue;
            }
            flushList();
            out.push(renderInline(line));
        }
        flushList();

        return out.join('').replace(/(<br>)+$/g, '').replace(/(<br>){3,}/g, '<br><br>');
    }

    function renderInline(text) {
        let out = escapeHtml(text);
        // Markdown links [text](url)
        out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|tel:[^\s)]+|mailto:[^\s)]+|#[\w-]+|\/[^\s)]+)\)/g,
            (m, label, url) => `<a href="${escapeHtml(url)}" class="cai-link" target="_blank" rel="noopener">${escapeHtml(label)} <i data-lucide="arrow-up-right"></i></a>`);
        // Auto-link bare URLs
        out = out.replace(/(^|\s)((?:https?:\/\/|www\.)[^\s<]+)/g,
            (m, pre, url) => {
                const href = url.startsWith('http') ? url : 'https://' + url;
                return `${pre}<a href="${escapeHtml(href)}" class="cai-link" target="_blank" rel="noopener">${escapeHtml(url)}</a>`;
            });
        // Bold **x**
        out = out.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        // Italic _x_
        out = out.replace(/(^|[\s(])_([^_\n]+)_(?=[\s).,!?]|$)/g, '$1<em>$2</em>');
        // Inline code `x`
        out = out.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        // Replace literal \n inside (no-op after split, but for safety)
        out = out.replace(/\n/g, '<br>');
        return out;
    }

    // Extract plain text from message text by stripping our tags & markdown
    function plainText(text) {
        return String(text)
            .replace(/\[\[(?:doctor|link|action|scroll|suggest):[^\]]*\]\]/g, '')
            .replace(/\*\*([^*]+)\*\*/g, '$1')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/_([^_]+)_/g, '$1')
            .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
            .trim();
    }

    // ============ Renderers ============
    function renderDoctorCard(doctor) {
        const profileHref = basePath('medico/' + doctor.slug);
        const wrap = document.createElement('article');
        wrap.className = 'cai-doctor-card';
        const phone = (doctor.phone || '').trim();
        wrap.innerHTML = `
            <a class="cai-doctor-link" href="${escapeHtml(profileHref)}">
                <img src="${escapeHtml(doctor.photo)}" alt="${escapeHtml(doctor.name)}" loading="lazy">
                <span>
                    <strong>${escapeHtml(doctor.name)}</strong>
                    <small>${escapeHtml(doctor.specialty || '')}</small>
                    ${doctor.office ? `<em><i data-lucide="map-pin"></i>${escapeHtml(doctor.office)}</em>` : ''}
                </span>
            </a>
            <div class="cai-doctor-cta">
                <a href="${escapeHtml(profileHref)}" class="cai-doctor-btn is-primary">
                    <i data-lucide="user-round"></i> Ver perfil
                </a>
                <button type="button" class="cai-doctor-btn" data-cai-appointment>
                    <i data-lucide="calendar-days"></i> Agendar
                </button>
                ${phone ? `<a href="tel:${escapeHtml(phone.replace(/[^+\d]/g, ''))}" class="cai-doctor-btn" aria-label="Llamar">
                    <i data-lucide="phone"></i>
                </a>` : ''}
            </div>
        `;
        wrap.querySelector('[data-cai-appointment]')?.addEventListener('click', () => triggerAction('appointment'));
        return wrap;
    }

    function renderActionButton(action) {
        const btn = document.createElement('button');
        btn.className = 'cai-action-btn';
        btn.type = 'button';
        const iconMap = {
            appointment: 'calendar-days',
            call: 'phone',
            directory: 'users-round',
            whatsapp: 'message-circle',
            email: 'mail',
        };
        btn.innerHTML = `<i data-lucide="${iconMap[action.type] || 'arrow-right'}"></i> ${escapeHtml(action.label)}`;
        btn.addEventListener('click', () => triggerAction(action.type));
        return btn;
    }

    function renderSuggestions(suggestions) {
        const wrap = document.createElement('div');
        wrap.className = 'cai-suggestions';
        suggestions.slice(0, 4).forEach((sug) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cai-suggestion-chip';
            btn.innerHTML = `<i data-lucide="sparkle"></i> ${escapeHtml(sug)}`;
            btn.addEventListener('click', () => {
                wrap.remove();
                send(sug);
            });
            wrap.appendChild(btn);
        });
        return wrap;
    }

    function triggerAction(type) {
        if (type === 'appointment') {
            // Close panel on mobile so the modal isn't obscured
            if (isMobile()) closePanel();
            setTimeout(() => document.querySelector('.js-open-appointment')?.click(), isMobile() ? 280 : 0);
            return;
        }
        if (type === 'call') {
            window.location.href = 'tel:18098060444';
            return;
        }
        if (type === 'directory') {
            window.location.href = basePath('directorio-medico');
            return;
        }
        if (type === 'whatsapp') {
            window.open('https://wa.me/18095012002', '_blank', 'noopener');
            return;
        }
        if (type === 'email') {
            window.location.href = 'mailto:info@colinashospital.com';
            return;
        }
    }

    function renderUserMessage(text, opts) {
        opts = opts || {};
        const div = document.createElement('div');
        div.className = 'cai-msg cai-msg-user';
        div.innerHTML = `<div class="cai-bubble">${escapeHtml(text).replace(/\n/g, '<br>')}</div>`;
        messagesEl.appendChild(div);
        if (!opts.skipPersist) {
            conversation.push({ role: 'user', content: text });
            persistConversation();
        }
        scrollToBottom();
    }

    function renderAssistantMessage(text, opts) {
        opts = opts || {};
        const parsed = parseAssistantMessage(text);
        const wrap = document.createElement('div');
        wrap.className = 'cai-msg cai-msg-bot';
        wrap.innerHTML = `
            <span class="cai-avatar"><i data-lucide="sparkles"></i></span>
            <div class="cai-bubble-wrap">
                <div class="cai-bubble">${parsed.html}</div>
                <button type="button" class="cai-copy" aria-label="Copiar mensaje" title="Copiar">
                    <i data-lucide="copy"></i>
                </button>
            </div>
        `;
        messagesEl.appendChild(wrap);

        const bubble = wrap.querySelector('.cai-bubble');
        if (parsed.cards.length > 0) {
            const cardsWrap = document.createElement('div');
            cardsWrap.className = 'cai-doctor-cards';
            parsed.cards.forEach((doc) => cardsWrap.appendChild(renderDoctorCard(doc)));
            bubble.appendChild(cardsWrap);
        }
        if (parsed.actions.length > 0) {
            const actionsWrap = document.createElement('div');
            actionsWrap.className = 'cai-actions';
            parsed.actions.forEach((a) => actionsWrap.appendChild(renderActionButton(a)));
            bubble.appendChild(actionsWrap);
        }

        // Copy button
        wrap.querySelector('.cai-copy')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            try {
                await navigator.clipboard.writeText(plainText(text));
                button.classList.add('is-copied');
                button.setAttribute('aria-label', 'Copiado');
                setTimeout(() => button.classList.remove('is-copied'), 1500);
            } catch (e) { /* ignore */ }
        });

        // Suggestions go INSIDE the bubble-wrap (column) so they sit below the bubble
        // and share its width — otherwise they'd compete with the bubble for flex space.
        if (parsed.suggestions.length > 0) {
            const bubbleWrap = wrap.querySelector('.cai-bubble-wrap');
            bubbleWrap.appendChild(renderSuggestions(parsed.suggestions));
        }

        if (parsed.scrollTo) {
            const target = document.querySelector(parsed.scrollTo);
            if (target) {
                if (isMobile()) closePanel();
                setTimeout(() => {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.classList.add('cai-flash');
                    setTimeout(() => target.classList.remove('cai-flash'), 1500);
                }, isMobile() ? 320 : 50);
            }
        }

        if (!opts.skipPersist) {
            conversation.push({ role: 'assistant', content: text });
            persistConversation();
        }

        refreshIcons();
        scrollToBottom();
    }

    function renderErrorMessage(message) {
        const wrap = document.createElement('div');
        wrap.className = 'cai-msg cai-msg-bot cai-msg-error';
        wrap.innerHTML = `
            <span class="cai-avatar"><i data-lucide="alert-triangle"></i></span>
            <div class="cai-bubble">
                <span>${escapeHtml(message)}</span>
                <button type="button" class="cai-retry">
                    <i data-lucide="rotate-cw"></i> Reintentar
                </button>
            </div>
        `;
        messagesEl.appendChild(wrap);
        wrap.querySelector('.cai-retry')?.addEventListener('click', () => {
            wrap.remove();
            if (lastUserMessage) send(lastUserMessage, { retry: true });
        });
        refreshIcons();
        scrollToBottom();
    }

    function renderTyping() {
        const div = document.createElement('div');
        div.className = 'cai-msg cai-msg-bot cai-typing';
        div.innerHTML = `
            <span class="cai-avatar"><i data-lucide="sparkles"></i></span>
            <div class="cai-bubble">
                <span class="cai-dot"></span><span class="cai-dot"></span><span class="cai-dot"></span>
            </div>
        `;
        messagesEl.appendChild(div);
        refreshIcons();
        scrollToBottom();
        return div;
    }

    function renderStarterChips() {
        quickEl.innerHTML = '';
        STARTERS.forEach((s) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cai-quick-chip';
            btn.innerHTML = `<i data-lucide="${s.icon}"></i> ${escapeHtml(s.label)}`;
            btn.addEventListener('click', () => {
                hideStarterChips();
                send(s.text);
            });
            quickEl.appendChild(btn);
        });
        quickEl.classList.add('is-visible');
        refreshIcons();
    }

    function hideStarterChips() {
        quickEl.classList.remove('is-visible');
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    }

    // ============ Send ============
    async function send(text, opts) {
        opts = opts || {};
        const message = (text || input.value || '').trim();
        if (!message) return;

        if (!opts.retry) {
            input.value = '';
            input.style.height = 'auto';
            renderUserMessage(message);
            lastUserMessage = message;
        } else {
            lastUserMessage = message;
        }
        hideStarterChips();
        sendBtn.disabled = true;
        sendBtn.classList.add('is-loading');

        const typing = renderTyping();

        try {
            const response = await fetch(data.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ messages: conversation.slice(-12) }),
            });
            const json = await response.json().catch(() => ({}));
            typing.remove();

            if (!response.ok || !json.ok) {
                const msg = json.error || (response.status === 429
                    ? 'Estás enviando mensajes muy rápido. Espera unos segundos.'
                    : 'No pude procesar tu mensaje. Intenta de nuevo.');
                renderErrorMessage(msg);
                return;
            }
            renderAssistantMessage(json.reply);
        } catch (err) {
            typing.remove();
            renderErrorMessage('No pude conectarme en este momento. Revisa tu conexión.');
        } finally {
            sendBtn.disabled = false;
            sendBtn.classList.remove('is-loading');
            if (!isMobile()) input.focus();
        }
    }

    // ============ Event wiring ============
    fab?.addEventListener('click', () => {
        if (root.classList.contains('is-open')) closePanel();
        else openPanel();
    });
    closeBtn?.addEventListener('click', closePanel);
    resetBtn?.addEventListener('click', () => {
        if (conversation.length === 0) return;
        resetConversation();
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        send();
    });

    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            closePanel();
        }
    });

    // First-visit attention bounce on the FAB
    try {
        if (!localStorage.getItem(FIRST_VISIT_KEY)) {
            setTimeout(() => root.classList.add('cai-attention'), 1800);
        }
    } catch (e) { /* ignore */ }

    refreshIcons();
})();
