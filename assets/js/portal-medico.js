/**
 * Portal del Medico - JS principal
 *
 * - api(): cliente del proxy /api/doctor-proxy.php
 * - Toggle de password en login
 * - FullCalendar con rendering custom (events sin cortar)
 * - Animacion de counters en KPIs
 * - Auto-save indicator helper
 */
(function () {
    'use strict';

    // ── Sidebar colapsable (escritorio) / drawer (móvil) ─────────────
    (function initSidebar() {
        const app = document.getElementById('dmApp');
        if (!app) return;
        const mq = window.matchMedia('(max-width: 1020px)');
        try { if (localStorage.getItem('dmSidebar') === 'collapsed' && !mq.matches) app.classList.add('collapsed'); } catch (e) {}
        function toggle() {
            if (mq.matches) {
                app.classList.toggle('drawer-open');
            } else {
                app.classList.toggle('collapsed');
                try { localStorage.setItem('dmSidebar', app.classList.contains('collapsed') ? 'collapsed' : 'open'); } catch (e) {}
            }
        }
        document.querySelectorAll('[data-dm-toggle]').forEach((b) => b.addEventListener('click', toggle));
        document.querySelectorAll('[data-dm-close]').forEach((b) => b.addEventListener('click', () => app.classList.remove('drawer-open')));
        app.querySelectorAll('.dm-sb .dm-link').forEach((l) => l.addEventListener('click', () => { if (mq.matches) app.classList.remove('drawer-open'); }));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') app.classList.remove('drawer-open'); });
        mq.addEventListener('change', () => app.classList.remove('drawer-open'));
    })();

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const CSRF = csrfMeta ? csrfMeta.content : '';

    /** Cliente del proxy server-side. Devuelve { ok, data, message, errors }. */
    window.doctorApi = async function (method, path, payload) {
        method = (method || 'GET').toUpperCase();
        const body = { method, path };
        if (method === 'GET') body.query = payload || {};
        else                  body.body  = payload || {};

        const r = await fetch('/api/doctor-proxy.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
            },
            body: JSON.stringify(body),
        });
        let json = null;
        try { json = await r.json(); } catch (_) {}
        return {
            ok: r.ok && json && json.success,
            status: r.status,
            data: json && json.data,
            message: json && json.message,
            errors: json && json.errors,
        };
    };

    /** Toggle de visibilidad de password (login + reset). */
    document.querySelectorAll('[data-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const sel = btn.getAttribute('data-toggle');
            const input = document.querySelector(sel);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            const icon = btn.querySelector('i[data-lucide]');
            if (icon && window.lucide) {
                icon.setAttribute('data-lucide', input.type === 'password' ? 'eye' : 'eye-off');
                window.lucide.createIcons({ icons: icon.parentElement });
            }
        });
    });

    /** Helpers para gradiente determinista por nombre (espejo del PHP). */
    const PALETTES = [
        ['#0d9488', '#0284c7'], ['#7c3aed', '#db2777'], ['#0891b2', '#1e40af'],
        ['#059669', '#0d9488'], ['#dc2626', '#ea580c'], ['#1d4ed8', '#6d28d9'],
        ['#b45309', '#dc2626'], ['#0f766e', '#0e7490'], ['#6d28d9', '#1e40af'],
        ['#be185d', '#7c2d12'],
    ];
    function crc32(str) {
        // Implementacion minima de CRC32 para que avatars JS coincidan con PHP
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < str.length; i++) {
            crc = crc ^ str.charCodeAt(i);
            for (let j = 0; j < 8; j++) {
                crc = (crc >>> 1) ^ (0xEDB88320 & -(crc & 1));
            }
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }
    function avatarPalette(name) {
        if (!name) return PALETTES[0];
        return PALETTES[crc32(String(name)) % PALETTES.length];
    }
    function avatarInitials(name, max) {
        max = max || 2;
        if (!name) return '?';
        const parts = String(name).trim().split(/\s+/);
        let out = '';
        for (const p of parts) {
            if (!p) continue;
            if (out.length >= max) break;
            out += p[0];
        }
        return (out || '?').toUpperCase();
    }
    window.doctorAvatar = function (name, size) {
        size = size || 'sm';
        const [c1, c2] = avatarPalette(name);
        const initials = avatarInitials(name);
        return '<span class="doctor-av doctor-av-' + size + '"'
             + ' style="background: linear-gradient(135deg, ' + c1 + ', ' + c2 + ');">'
             + initials + '</span>';
    };

    /** Animacion de counters en .doctor-kpi-value (count-up). */
    function animateCounter(el) {
        const target = parseFloat((el.textContent || '0').replace(/[^\d.-]/g, ''));
        if (isNaN(target) || target === 0) return;
        if (target > 9999) return; // no animar numeros enormes
        const duration = 800;
        const start = performance.now();
        const initial = 0;
        const isPercent = el.textContent.includes('%');
        const isCurrency = el.textContent.includes('$');
        const original = el.textContent;
        function frame(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const current = Math.round(initial + (target - initial) * eased);
            if (isCurrency) el.textContent = '$' + current.toLocaleString();
            else if (isPercent) el.textContent = current + '%';
            else el.textContent = current.toLocaleString();
            if (t < 1) requestAnimationFrame(frame);
            else el.textContent = original;
        }
        requestAnimationFrame(frame);
    }

    /** Monta FullCalendar (mes/sem/dia) con rendering custom para que eventos no se corten. */
    function mountCalendar(el) {
        if (typeof FullCalendar === 'undefined') return;
        let events = [];
        try { events = JSON.parse(el.dataset.events || '[]'); } catch (_) {}

        const isLarge = el.id === 'doctor-agenda';
        const calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'es',
            firstDay: 1,
            height: isLarge ? 720 : 520,
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia', list: 'Lista' },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: isLarge
                    ? 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    : 'dayGridMonth,timeGridWeek',
            },
            events,
            displayEventTime: true,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            // En vista mensual: pinta como chip de bloque con color de fondo
            eventDisplay: 'block',
            views: {
                dayGridMonth: {
                    displayEventTime: false,
                    eventContent: function (arg) {
                        const status = arg.event.extendedProps.status || 'scheduled';
                        const dot = document.createElement('span');
                        dot.className = 'fc-evt-dot';
                        const title = document.createElement('span');
                        title.className = 'fc-evt-title';
                        title.textContent = arg.event.title;
                        const wrap = document.createElement('div');
                        wrap.className = 'fc-evt-wrap fc-evt-' + status;
                        wrap.appendChild(dot);
                        wrap.appendChild(title);
                        return { domNodes: [wrap] };
                    }
                }
            },
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                if (window.openApptModal) window.openApptModal(info.event.id);
                else window.location.href = '/portal-medico/consulta.php?appt=' + info.event.id;
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '21:00:00',
            nowIndicator: true,
            dayMaxEvents: 3,
            moreLinkText: (n) => '+' + n + ' mas',
        });
        calendar.render();
    }

    /** Auto-save hint para forms (usado en consulta y perfil). */
    window.doctorAutoSaveHint = function (statusEl, state) {
        if (!statusEl) return;
        if (state === 'saving') {
            statusEl.textContent = '· Guardando...';
            statusEl.className = 'doctor-save-status doctor-save-saving';
        } else if (state === 'saved') {
            statusEl.textContent = '✓ Guardado · ' + new Date().toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
            statusEl.className = 'doctor-save-status doctor-save-saved';
        } else if (state === 'error') {
            statusEl.textContent = '⚠ Error al guardar';
            statusEl.className = 'doctor-save-status doctor-save-error';
        } else {
            statusEl.textContent = '';
            statusEl.className = 'doctor-save-status';
        }
    };

    /** Boot. */
    document.addEventListener('DOMContentLoaded', () => {
        // KPI counters animation
        document.querySelectorAll('.doctor-kpi-value').forEach(animateCounter);

        // Calendars
        ['doctor-calendar', 'doctor-agenda'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) mountCalendar(el);
        });

        // Lucide refresh tras mounts
        if (window.lucide) window.lucide.createIcons();
    });
})();
