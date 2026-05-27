/**
 * Portal del Medico - JS principal
 *
 * Carga calendario, monta utilidades comunes:
 *   - api(): cliente del proxy /api/doctor-proxy.php
 *   - toggle de password en login
 *   - Renderizado de FullCalendar si existe #doctor-calendar
 */
(function () {
    'use strict';

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

    /** Toggle de visibilidad de password (login). */
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

    /** Monta FullCalendar en #doctor-calendar (data-events JSON). */
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('doctor-calendar');
        if (!el || typeof FullCalendar === 'undefined') return;
        let events = [];
        try { events = JSON.parse(el.dataset.events || '[]'); } catch (_) { events = []; }

        const calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'es',
            firstDay: 1,
            height: 480,
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia' },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            events,
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                window.location.href = '/portal-medico/consulta.php?appt=' + info.event.id;
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '21:00:00',
        });
        calendar.render();
    });
})();
