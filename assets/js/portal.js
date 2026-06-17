/* Portal de Pacientes — Hospital General Las Colinas
 * Interacciones AJAX: slot picker calendar-style, cancelar cita, reenviar verificación.
 * Las llamadas pasan por /api/portal-proxy.php (mismo origen) — el token vive en sesión PHP.
 */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    async function proxy(method, path, payload = {}) {
        const isGet = method === 'GET';
        const res = await fetch(proxyUrl, {
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

    function showToast(message, type = 'info') {
        const region = document.getElementById('portal-toast-region');
        if (!region) return;
        const toast = document.createElement('div');
        toast.className = `portal-toast is-${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = `<i data-lucide="${type === 'success' ? 'check-circle-2' : type === 'error' ? 'alert-circle' : 'info'}"></i><span></span>`;
        toast.querySelector('span').textContent = message;
        region.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();
        window.setTimeout(() => toast.remove(), 4200);
    }

    function setButtonLoading(button, loading, label = 'Procesando…') {
        if (!button) return;
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<i data-lucide="loader-circle" class="animate-spin"></i><span>${label}</span>`;
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        }
        if (window.lucide) window.lucide.createIcons();
    }

    const sidebarToggle = document.getElementById('portal-sidebar-toggle');
    const root = document.documentElement;

    function setSidebarCollapsed(collapsed, persist = true) {
        root.classList.toggle('portal-sidebar-collapsed', collapsed);
        if (!sidebarToggle) return;
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Colapsar menú');
        sidebarToggle.title = collapsed ? 'Expandir menú' : 'Colapsar menú';
        sidebarToggle.innerHTML = `<i data-lucide="${collapsed ? 'panel-left-open' : 'panel-left-close'}"></i>`;
        if (persist) {
            try { localStorage.setItem('hglc-portal-sidebar', collapsed ? 'collapsed' : 'expanded'); }
            catch (e) {}
        }
        if (window.lucide) window.lucide.createIcons();
    }

    if (sidebarToggle) {
        setSidebarCollapsed(root.classList.contains('portal-sidebar-collapsed'), false);
        sidebarToggle.addEventListener('click', () => {
            setSidebarCollapsed(!root.classList.contains('portal-sidebar-collapsed'));
        });
    }

    document.querySelectorAll('.js-open-more').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById('portal-more-dialog');
            if (dialog && !dialog.open) dialog.showModal();
        });
    });

    document.querySelectorAll('.js-close-dialog').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('.portal-dialog').forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('.portal-password-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target || '');
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
            button.innerHTML = `<i data-lucide="${showing ? 'eye' : 'eye-off'}"></i>`;
            if (window.lucide) window.lucide.createIcons();
        });
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const submit = form.querySelector('button[type="submit"]');
            if (!submit || form.id === 'portal-cancel-form' || (form.id === 'specialty-form' && !document.getElementById('specialty_id')?.value)) return;
            setButtonLoading(submit, true, submit.dataset.loadingLabel || 'Procesando…');
        });
    });

    // ── Reenviar verificación de email ──────────────────────────────────────
    const btnResend = document.getElementById('btn-resend-verify');
    if (btnResend) {
        btnResend.addEventListener('click', async () => {
            const status = document.getElementById('resend-status');
            status.textContent = 'Enviando…';
            const email = btnResend.dataset.email || '';
            const r = await proxy('POST', '/portal/auth/resend-verification', { email });
            status.textContent = r.message || (r.ok ? 'Enviado.' : 'Error.');
            status.style.color = r.ok ? '#10b981' : '#ef4444';
        });
    }

    // ── Cancelar cita ───────────────────────────────────────────────────────
    let appointmentToCancel = null;
    const cancelDialog = document.getElementById('portal-cancel-dialog');
    const cancelForm = document.getElementById('portal-cancel-form');
    const cancelReason = document.getElementById('portal-cancel-reason');
    document.querySelectorAll('.js-cancel-appt').forEach(btn => {
        btn.addEventListener('click', () => {
            appointmentToCancel = btn.dataset.apptId || null;
            if (cancelReason) cancelReason.value = '';
            if (cancelDialog && !cancelDialog.open) cancelDialog.showModal();
        });
    });

    if (cancelForm) {
        cancelForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!appointmentToCancel) return;
            const confirmButton = document.getElementById('portal-confirm-cancel');
            setButtonLoading(confirmButton, true, 'Cancelando…');
            const r = await proxy('DELETE', `/portal/me/appointments/${appointmentToCancel}`, {
                cancellation_reason: cancelReason?.value.trim() || '',
            });
            if (r.ok) {
                cancelDialog?.close();
                showToast('La cita fue cancelada.', 'success');
                window.setTimeout(() => location.reload(), 700);
            } else {
                showToast(r.message || 'No se pudo cancelar la cita.', 'error');
                setButtonLoading(confirmButton, false);
            }
        });
    }

    // ── Selector de especialidad ────────────────────────────────────────────
    const specialtySearch = document.getElementById('specialty-search');
    const specialtyGrid = document.getElementById('specialty-grid');
    const specialtyInput = document.getElementById('specialty_id');
    const specialtyEmpty = document.getElementById('specialty-empty');
    const specialtyClear = document.getElementById('specialty-search-clear');
    const specialtyShowAll = document.getElementById('specialty-show-all');
    const specialtyStatus = document.getElementById('specialty-selection-status');
    const specialtyForm = document.getElementById('specialty-form');

    function filterSpecialties() {
        if (!specialtyGrid || !specialtySearch) return;
        const query = specialtySearch.value.trim().toLocaleLowerCase('es');
        let visible = 0;
        specialtyGrid.classList.toggle('is-searching', query !== '');
        specialtyGrid.querySelectorAll('li[data-specialty-name]').forEach(item => {
            const matches = !query || item.dataset.specialtyName.includes(query);
            item.hidden = !matches;
            if (matches) visible += 1;
        });
        if (specialtyEmpty) specialtyEmpty.hidden = visible > 0;
        if (specialtyClear) specialtyClear.hidden = query === '';
        if (specialtyShowAll) specialtyShowAll.hidden = query !== '' || specialtyGrid.classList.contains('is-expanded');
    }

    specialtySearch?.addEventListener('input', filterSpecialties);
    specialtyClear?.addEventListener('click', () => {
        specialtySearch.value = '';
        specialtySearch.focus();
        filterSpecialties();
    });

    specialtyShowAll?.addEventListener('click', () => {
        specialtyGrid?.classList.add('is-expanded');
        specialtyShowAll.hidden = true;
        specialtyStatus.textContent = `Mostrando las ${specialtyShowAll.dataset.total || ''} especialidades.`;
    });

    specialtyGrid?.querySelectorAll('.specialty-card').forEach(card => {
        card.addEventListener('click', () => {
            specialtyGrid.querySelectorAll('.specialty-card').forEach(item => item.classList.remove('is-selected'));
            card.classList.add('is-selected');
            if (specialtyInput) specialtyInput.value = card.dataset.specialtyId || '';
            card.setAttribute('aria-pressed', 'true');
            specialtyGrid.querySelectorAll('.specialty-card:not(.is-selected)').forEach(item => item.setAttribute('aria-pressed', 'false'));
            specialtyGrid.querySelectorAll('.specialty-card').forEach(item => {
                item.disabled = true;
            });
            card.classList.add('is-loading');
            const arrow = card.querySelector('.specialty-card-arrow');
            if (arrow) arrow.setAttribute('data-lucide', 'loader-circle');
            if (specialtyStatus) specialtyStatus.textContent = `Cargando médicos de ${card.querySelector('.specialty-card-name')?.textContent.trim() || 'la especialidad'}…`;
            if (window.lucide) window.lucide.createIcons();
            window.setTimeout(() => specialtyForm?.requestSubmit(), 120);
        });
    });

    specialtyForm?.addEventListener('submit', event => {
        if (specialtyInput?.value) return;
        event.preventDefault();
        showToast('Selecciona una especialidad para continuar.', 'error');
    });

    // ── Slot picker calendar-style (agendar.php paso 3) ─────────────────────
    const doctorId = window.PORTAL_DOCTOR_ID;
    if (doctorId) initSlotPicker(doctorId);

    async function initSlotPicker(docId) {
        const loader = document.querySelector('.portal-slot-loader');
        const picker = document.getElementById('slot-picker');
        const form   = document.getElementById('confirm-form');
        const confirmWhen = document.getElementById('confirm-when');
        const apptInput = document.getElementById('appointment_time');

        const today = new Date(); today.setHours(0, 0, 0, 0);
        const from = today.toISOString().slice(0, 10);
        const to   = new Date(today.getTime() + 45 * 86400000).toISOString().slice(0, 10);

        const r = await proxy('GET', `/portal/doctors/${docId}/slots`, {
            date_from: from, date_to: to, slot_minutes: 30,
        });

        loader.classList.add('hidden');
        picker.classList.remove('hidden');

        if (!r.ok) {
            picker.innerHTML = `<p class="portal-empty-text">${r.message || 'No se pudieron cargar los horarios.'}</p>`;
            return;
        }

        const days = r.data?.days || {};
        if (Object.keys(days).length === 0) {
            picker.innerHTML = `<p class="portal-empty-text">No hay horarios disponibles en los próximos 45 días.</p>`;
            return;
        }

        // Render del calendario: mes actual con navegación, días disponibles destacados
        let viewYear = today.getFullYear();
        let viewMonth = today.getMonth(); // 0-indexed
        let selectedDay = null;

        function render() {
            const monthName = new Date(viewYear, viewMonth, 1)
                .toLocaleDateString('es-DO', { month: 'long', year: 'numeric' });

            // Primer día del mes y total días
            const firstDay = new Date(viewYear, viewMonth, 1);
            const lastDay  = new Date(viewYear, viewMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            // Día de la semana del primero (0 = domingo, 1 = lunes, ...). Usamos lunes como inicio.
            let startOffset = firstDay.getDay() - 1;
            if (startOffset < 0) startOffset = 6;

            const minDate = today.toISOString().slice(0, 10);
            const maxDate = to;

            const canPrev = (new Date(viewYear, viewMonth, 1)) > new Date(today.getFullYear(), today.getMonth(), 1);
            const maxView = new Date(to + 'T00:00:00');
            const canNext = (new Date(viewYear, viewMonth + 1, 1)) <= new Date(maxView.getFullYear(), maxView.getMonth(), 1);

            const cells = [];
            for (let i = 0; i < startOffset; i++) cells.push('<div class="cal-cell cal-empty"></div>');
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const available = !!days[dateStr];
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
                        <button type="button" class="cal-nav" id="cal-prev" ${canPrev ? '' : 'disabled'} aria-label="Mes anterior">‹</button>
                        <div class="cal-title">${monthName.charAt(0).toUpperCase() + monthName.slice(1)}</div>
                        <button type="button" class="cal-nav" id="cal-next" ${canNext ? '' : 'disabled'} aria-label="Mes siguiente">›</button>
                    </div>
                    <div class="cal-weekdays">
                        <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
                    </div>
                    <div class="cal-grid">${cells.join('')}</div>
                    <div class="cal-legend">
                        <span><span class="cal-dot cal-dot-static"></span> Disponible</span>
                        <span><span class="cal-square cal-square-disabled"></span> Sin cupo</span>
                    </div>
                </div>
                <div class="cal-times">
                    <h3>Horarios ${selectedDay ? 'del ' + formatDayLabel(selectedDay) : ''}</h3>
                    <div class="cal-times-grid" id="cal-times-grid">
                        ${selectedDay
                            ? days[selectedDay].map(ts => {
                                const t = new Date(ts.replace(' ', 'T'));
                                const lbl = t.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit', hour12: true });
                                return `<button type="button" class="cal-time" data-time="${ts}">${lbl}</button>`;
                              }).join('')
                            : '<p class="portal-empty-text">Selecciona un día disponible en el calendario.</p>'
                        }
                    </div>
                </div>
            `;

            // Wire events
            const prevBtn = document.getElementById('cal-prev');
            const nextBtn = document.getElementById('cal-next');
            if (prevBtn) prevBtn.addEventListener('click', () => {
                viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                render();
            });
            if (nextBtn) nextBtn.addEventListener('click', () => {
                viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                render();
            });

            picker.querySelectorAll('.cal-cell[data-day]').forEach(b => {
                b.addEventListener('click', () => {
                    selectedDay = b.dataset.day;
                    render();
                });
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

        render();
    }
})();
