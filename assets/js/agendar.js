/* Wizard de agendamiento como invitado.
 * Calendar slot picker + form submit hacia api/guest-appointment.php
 */
(function () {
    'use strict';

    const doctorId = window.PORTAL_DOCTOR_ID;
    if (!doctorId) return;

    const loader  = document.querySelector('.portal-slot-loader');
    const picker  = document.getElementById('slot-picker');
    const form    = document.getElementById('guest-form');
    const confirmWhen = document.getElementById('confirm-when');
    const apptInput = document.getElementById('appointment_time');
    const submitBtn = document.getElementById('g-submit');
    const result    = document.getElementById('guest-result');

    let selectedDay = null;
    let daysData = {};
    const today = new Date(); today.setHours(0,0,0,0);
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth();
    const minDate = today.toISOString().slice(0,10);
    const maxDate = new Date(today.getTime() + 30 * 86400000).toISOString().slice(0,10);

    const slotsBase = window.AGENDAR_SLOTS_URL || '/api/agendar-slots.php';
    const slotsUrl  = `${slotsBase}?doctor_id=${doctorId}&date_from=${minDate}&date_to=${maxDate}&slot_minutes=30`;

    function showError(msg, canRetry) {
        loader.classList.add('hidden');
        picker.classList.remove('hidden');
        picker.innerHTML = `
            <div class="portal-empty">
                <i data-lucide="calendar-x" class="h-10 w-10"></i>
                <p>${msg}</p>
                ${canRetry ? '<button type="button" class="btn btn-outline" id="slot-retry"><i data-lucide="refresh-cw" class="h-4 w-4"></i> Reintentar</button>' : ''}
                <p class="portal-hint" style="margin-top:1rem">También puedes llamarnos al <a href="tel:18098060444" class="portal-text-link">(809) 806-0444</a>.</p>
            </div>`;
        if (window.lucide) lucide.createIcons();
        const retry = document.getElementById('slot-retry');
        if (retry) retry.addEventListener('click', loadSlots);
    }

    function loadSlots() {
        loader.classList.remove('hidden');
        picker.classList.add('hidden');
        picker.innerHTML = '';

        const ctrl = new AbortController();
        const timeoutId = setTimeout(() => ctrl.abort(), 25000);

        fetch(slotsUrl, { signal: ctrl.signal, headers: { 'Accept': 'application/json' } })
            .then(async r => {
                clearTimeout(timeoutId);
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    const text = await r.text();
                    throw new Error(`Respuesta inválida del servidor (HTTP ${r.status}).`);
                }
                return r.json();
            })
            .then(r => {
                loader.classList.add('hidden');
                picker.classList.remove('hidden');
                if (!r.success) {
                    showError(r.message || 'No se pudieron cargar los horarios disponibles.', true);
                    return;
                }
                daysData = r.data?.days || {};
                if (Object.keys(daysData).length === 0) {
                    picker.innerHTML = '<p class="portal-empty-text">No hay horarios disponibles en los próximos 30 días.</p>';
                    return;
                }
                render();
            })
            .catch(e => {
                clearTimeout(timeoutId);
                const msg = e.name === 'AbortError'
                    ? 'La carga tomó demasiado tiempo. Verifica tu conexión e intenta de nuevo.'
                    : `No se pudieron cargar los horarios: ${e.message}`;
                showError(msg, true);
            });
    }

    loadSlots();

    function render() {
        const monthName = new Date(viewYear, viewMonth, 1)
            .toLocaleDateString('es-DO', { month: 'long', year: 'numeric' });
        const firstDay = new Date(viewYear, viewMonth, 1);
        const lastDay  = new Date(viewYear, viewMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        let startOffset = firstDay.getDay() - 1;
        if (startOffset < 0) startOffset = 6;

        const canPrev = (new Date(viewYear, viewMonth, 1)) > new Date(today.getFullYear(), today.getMonth(), 1);
        const maxView = new Date(maxDate + 'T00:00:00');
        const canNext = (new Date(viewYear, viewMonth + 1, 1)) <= new Date(maxView.getFullYear(), maxView.getMonth(), 1);

        const cells = [];
        for (let i = 0; i < startOffset; i++) cells.push('<div class="cal-cell cal-empty"></div>');
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${viewYear}-${String(viewMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const available = !!daysData[dateStr];
            const inRange = dateStr >= minDate && dateStr <= maxDate;
            const isSelected = selectedDay === dateStr;
            const isToday = dateStr === minDate;
            let cls = 'cal-cell';
            if (!inRange) cls += ' cal-out';
            else if (available) cls += ' cal-avail';
            else cls += ' cal-disabled';
            if (isSelected) cls += ' cal-selected';
            if (isToday) cls += ' cal-today';
            if (available && inRange) {
                cells.push(`<button type="button" class="${cls}" data-day="${dateStr}">${d}<span class="cal-dot"></span></button>`);
            } else {
                cells.push(`<div class="${cls}">${d}</div>`);
            }
        }

        picker.innerHTML = `
            <div class="cal-shell">
                <div class="cal-head">
                    <button type="button" class="cal-nav" id="cal-prev" ${canPrev ? '' : 'disabled'}>‹</button>
                    <div class="cal-title">${monthName.charAt(0).toUpperCase() + monthName.slice(1)}</div>
                    <button type="button" class="cal-nav" id="cal-next" ${canNext ? '' : 'disabled'}>›</button>
                </div>
                <div class="cal-weekdays"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
                <div class="cal-grid">${cells.join('')}</div>
                <div class="cal-legend">
                    <span><span class="cal-dot cal-dot-static"></span> Disponible</span>
                    <span><span class="cal-square cal-square-disabled"></span> Sin cupo</span>
                </div>
            </div>
            <div class="cal-times">
                <h3>Horarios ${selectedDay ? 'del ' + formatDayLabel(selectedDay) : ''}</h3>
                <div class="cal-times-grid">
                    ${selectedDay
                        ? daysData[selectedDay].map(ts => {
                            const t = new Date(ts.replace(' ', 'T'));
                            const lbl = t.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit', hour12: true });
                            return `<button type="button" class="cal-time" data-time="${ts}">${lbl}</button>`;
                          }).join('')
                        : '<p class="portal-empty-text">Selecciona un día disponible en el calendario.</p>'
                    }
                </div>
            </div>
        `;

        document.getElementById('cal-prev')?.addEventListener('click', () => {
            viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            render();
        });
        document.getElementById('cal-next')?.addEventListener('click', () => {
            viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            render();
        });
        picker.querySelectorAll('.cal-cell[data-day]').forEach(b => {
            b.addEventListener('click', () => { selectedDay = b.dataset.day; render(); });
        });
        picker.querySelectorAll('.cal-time').forEach(b => {
            b.addEventListener('click', () => {
                picker.querySelectorAll('.cal-time').forEach(x => x.classList.remove('is-active'));
                b.classList.add('is-active');
                const ts = b.dataset.time;
                apptInput.value = ts;
                const d = new Date(ts.replace(' ', 'T'));
                confirmWhen.textContent = d.toLocaleDateString('es-DO', {
                    weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: true,
                });
                form.classList.remove('hidden');
                setTimeout(() => form.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
            });
        });
    }

    function formatDayLabel(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('es-DO', { weekday: 'long', day: '2-digit', month: 'long' });
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!apptInput.value) {
            result.innerHTML = '<div class="portal-flash portal-flash-error" style="margin-top:1rem">Selecciona un horario primero.</div>';
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Agendando...';
        result.innerHTML = '';

        const payload = {
            name:             form.name.value.trim(),
            cedula:           form.cedula.value.trim(),
            email:            form.email.value.trim(),
            phone:            form.phone.value.trim(),
            doctor_id:        Number(form.doctor_id.value),
            appointment_time: form.appointment_time.value,
            notes:            form.notes.value.trim(),
        };

        if (window.AGENDAR_HCAPTCHA && typeof hcaptcha !== 'undefined') {
            const t = hcaptcha.getResponse();
            if (!t) {
                result.innerHTML = '<div class="portal-flash portal-flash-error" style="margin-top:1rem">Completa el CAPTCHA.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✓ Confirmar cita';
                return;
            }
            payload.captcha_token = t;
        }

        try {
            const submitUrl = window.AGENDAR_SUBMIT_URL || '/api/guest-appointment.php';
            const r = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const j = await r.json();
            if (j.success) {
                renderConfirmation(j.data);
            } else {
                const errs = j.errors ? Object.values(j.errors).flat().join(' · ') : '';
                result.innerHTML = `<div class="portal-flash portal-flash-error" style="margin-top:1rem">${j.message || 'Error.'} ${errs}</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✓ Confirmar cita';
                if (window.hcaptcha) hcaptcha.reset();
            }
        } catch (e) {
            result.innerHTML = `<div class="portal-flash portal-flash-error" style="margin-top:1rem">Error de conexión: ${e.message}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✓ Confirmar cita';
        }
    });

    function renderConfirmation(data) {
        const when = new Date(data.appointment_time.replace(' ', 'T'));
        const whenLabel = when.toLocaleDateString('es-DO', {
            weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true,
        });
        document.querySelector('.portal-main').innerHTML = `
            <div class="portal-card" style="text-align:center;padding:3rem 2rem">
                <div style="width:80px;height:80px;margin:0 auto 1.5rem;background:#dcfce7;color:#047857;border-radius:50%;display:grid;place-items:center;font-size:2.5rem">✓</div>
                <h1 style="font-size:2rem;color:#0f172a;margin-bottom:.5rem">¡Cita agendada!</h1>
                <p style="color:#475569;margin-bottom:2rem;font-size:1.1rem">Tu cita #${data.appointment_id} fue registrada correctamente.</p>

                <div style="background:#f8fafc;padding:1.5rem;border-radius:12px;max-width:480px;margin:0 auto;text-align:left">
                    <p style="margin:.25rem 0"><strong>Médico:</strong> ${data.doctor_name}</p>
                    <p style="margin:.25rem 0"><strong>Especialidad:</strong> ${data.specialty}</p>
                    <p style="margin:.25rem 0"><strong>Fecha y hora:</strong> ${whenLabel}</p>
                </div>

                ${data.email_sent
                    ? '<p style="color:#047857;margin:1.5rem 0;font-weight:600">📧 Te enviamos los detalles a tu correo.</p>'
                    : '<p style="color:#b45309;margin:1.5rem 0">⚠ No pudimos enviar el correo de confirmación. Guarda esta página o anota el número de cita.</p>'
                }

                <div style="background:linear-gradient(135deg,#ecfdf5,#fff);padding:1.5rem;border:1px solid #a7f3d0;border-radius:12px;margin-top:1.5rem">
                    <h3 style="margin:0 0 .5rem;color:#047857">🩺 Crea tu cuenta del portal</h3>
                    <p style="margin:0 0 1rem;color:#475569;font-size:.95rem">Para gestionar, ver y cancelar tus citas en línea.</p>
                    <a href="${data.register_url}" class="btn btn-green">Crear mi cuenta →</a>
                </div>

                <a href="/" style="display:block;margin-top:2rem;color:#6b7280;text-decoration:none">← Volver al inicio</a>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
})();
