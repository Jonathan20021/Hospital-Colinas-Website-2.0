/* Portal de Pacientes — Hospital General Las Colinas
 * Interacciones AJAX: slot picker para agendar, cancelar cita, reenviar verificación.
 * Las llamadas pasan por /api/portal-proxy.php (mismo origen) — el token vive en sesión PHP.
 */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function proxy(method, path, payload = {}) {
        const isGet = method === 'GET';
        const res = await fetch('/api/portal-proxy.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify({
                method,
                path,
                query: isGet ? payload : undefined,
                body:  isGet ? undefined : payload,
            }),
        });
        const txt = await res.text();
        let json;
        try { json = JSON.parse(txt); }
        catch { json = { success: false, message: 'Respuesta inválida.' }; }
        return { ok: res.ok && json.success, status: res.status, ...json };
    }

    // ── Reenviar verificación de email ──────────────────────────────────────
    const btnResend = document.getElementById('btn-resend-verify');
    if (btnResend) {
        btnResend.addEventListener('click', async () => {
            const status = document.getElementById('resend-status');
            status.textContent = 'Enviando…';
            const email = btnResend.dataset.email
                || document.querySelector('strong')?.textContent
                || '';
            const r = await proxy('POST', '/portal/auth/resend-verification', { email });
            status.textContent = r.message || (r.ok ? 'Enviado.' : 'Error.');
            status.style.color = r.ok ? '#10b981' : '#ef4444';
        });
    }

    // ── Cancelar cita ───────────────────────────────────────────────────────
    document.querySelectorAll('.js-cancel-appt').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Cancelar esta cita?')) return;
            const id = btn.dataset.apptId;
            const reason = prompt('Motivo de la cancelación (opcional):') || '';
            const r = await proxy('DELETE', `/portal/me/appointments/${id}`, { cancellation_reason: reason });
            if (r.ok) {
                alert('Cita cancelada.');
                location.reload();
            } else {
                alert(r.message || 'No se pudo cancelar.');
            }
        });
    });

    // ── Slot picker (agendar.php paso 3) ────────────────────────────────────
    const doctorId = window.PORTAL_DOCTOR_ID;
    if (doctorId) initSlotPicker(doctorId);

    async function initSlotPicker(docId) {
        const loader   = document.querySelector('.portal-slot-loader');
        const picker   = document.getElementById('slot-picker');
        const daysWrap = document.getElementById('slot-days');
        const timesWrap= document.getElementById('slot-times');
        const form     = document.getElementById('confirm-form');
        const confirmWhen = document.getElementById('confirm-when');
        const apptInput = document.getElementById('appointment_time');

        const today = new Date().toISOString().slice(0, 10);
        const in30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

        const r = await proxy('GET', `/portal/doctors/${docId}/slots`, {
            date_from: today,
            date_to: in30,
            slot_minutes: 30,
        });

        loader.classList.add('hidden');

        if (!r.ok) {
            picker.classList.remove('hidden');
            daysWrap.innerHTML = `<p class="portal-empty-text">${r.message || 'No se pudieron cargar los horarios.'}</p>`;
            return;
        }

        const days = r.data?.days || {};
        const dayKeys = Object.keys(days);
        if (dayKeys.length === 0) {
            picker.classList.remove('hidden');
            daysWrap.innerHTML = `<p class="portal-empty-text">No hay horarios disponibles en los próximos 30 días.</p>`;
            return;
        }

        picker.classList.remove('hidden');

        daysWrap.innerHTML = dayKeys.map(d => {
            const dt = new Date(d + 'T00:00:00');
            const label = dt.toLocaleDateString('es-DO', { weekday: 'short', day: '2-digit', month: 'short' });
            return `<button type="button" class="portal-slot-day" data-day="${d}">${label}</button>`;
        }).join('');

        daysWrap.querySelectorAll('.portal-slot-day').forEach(btn => {
            btn.addEventListener('click', () => {
                daysWrap.querySelectorAll('.portal-slot-day').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                renderTimes(days[btn.dataset.day]);
            });
        });

        function renderTimes(slots) {
            timesWrap.innerHTML = slots.map(ts => {
                const t = new Date(ts.replace(' ', 'T'));
                const label = t.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit', hour12: true });
                return `<button type="button" class="portal-slot-time" data-time="${ts}">${label}</button>`;
            }).join('');
            timesWrap.querySelectorAll('.portal-slot-time').forEach(btn => {
                btn.addEventListener('click', () => {
                    timesWrap.querySelectorAll('.portal-slot-time').forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const ts = btn.dataset.time;
                    apptInput.value = ts;
                    const d = new Date(ts.replace(' ', 'T'));
                    confirmWhen.textContent = d.toLocaleDateString('es-DO', {
                        weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: true,
                    });
                    form.classList.remove('hidden');
                });
            });
        }
    }
})();
