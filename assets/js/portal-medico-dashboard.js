/**
 * Dashboard interactivo del portal médico.
 *
 * Mejora progresiva: el dashboard ya viene renderizado por PHP (funciona sin JS).
 * Este módulo lo "toma" cuando hay JS + API disponible y añade:
 *   - Sparklines REALES en los KPIs (serie diaria del endpoint /me/activity).
 *   - Gráfico de actividad (Chart.js) con rango 7/14/30 días.
 *   - Mini-calendario interactivo (navegar meses, marcar citas, clic en día → detalle).
 *   - Listas en vivo (próximas citas / pacientes de hoy) con refresco sin recargar.
 *
 * Si el API no responde, se conserva el contenido server-rendered (no se vacía nada).
 */
(function () {
    'use strict';

    const CFG = window.DM_DASH || {};
    const api = window.doctorApi;
    if (!api) return;

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
    const pad = (n) => String(n).padStart(2, '0');
    const isoOf = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    const MES_LARGO  = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    const MES_CORTO  = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];

    // ── Caché de eventos por mes (YYYY-MM → [{id,start,title,status}]) ─────────
    const monthCache = {};
    function seedEvents(list) {
        (list || []).forEach((ev) => {
            const iso = String(ev.start || '').slice(0, 10);
            if (!iso) return;
            const key = iso.slice(0, 7);
            (monthCache[key] = monthCache[key] || []).push({
                id: ev.id, iso, title: ev.title || ev.patient_name || 'Cita',
                status: ev.status || 'scheduled',
                time: String(ev.start || '').slice(11, 16),
            });
        });
    }

    async function loadMonth(key) {
        if (monthCache[key]) return monthCache[key];
        monthCache[key] = []; // marca como cargado para no repetir
        const [y, m] = key.split('-').map(Number);
        const from = key + '-01';
        const to   = key + '-' + pad(new Date(y, m, 0).getDate());
        const r = await api('GET', '/portal-doctor/me/appointments', { date_from: from, date_to: to, per_page: 500 });
        if (r.ok && r.data && Array.isArray(r.data.items)) {
            monthCache[key] = r.data.items.map((a) => ({
                id: a.id,
                iso: String(a.appointment_time || '').slice(0, 10),
                title: a.patient_name || 'Cita',
                status: a.status || 'scheduled',
                time: String(a.appointment_time || '').slice(11, 16),
            }));
        }
        return monthCache[key];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  1) SPARKLINES REALES + GRÁFICO DE ACTIVIDAD
    // ════════════════════════════════════════════════════════════════════════
    function sparkPath(values, W, H) {
        const n = values.length;
        if (!n) return { line: '', fill: '' };
        const max = Math.max(1, ...values);
        const step = n > 1 ? W / (n - 1) : 0;
        let line = '';
        values.forEach((v, i) => {
            const x = +(i * step).toFixed(1);
            const y = +(H - (v / max) * (H - 2) - 1).toFixed(1);
            line += (i === 0 ? 'M' : 'L') + x + ',' + y + ' ';
        });
        line = line.trim();
        const fill = line + ' L' + W + ',' + H + ' L0,' + H + ' Z';
        return { line, fill };
    }

    function renderSparklines(series) {
        const pick = {
            total:     series.map((d) => +d.total),
            scheduled: series.map((d) => +d.scheduled),
            completed: series.map((d) => +d.completed),
            cancelled: series.map((d) => +d.cancelled),
        };
        $$('.dm-kpi-spark[data-series]').forEach((svg) => {
            const key = svg.dataset.series;
            const vals = pick[key] || pick.total;
            const { line, fill } = sparkPath(vals, 100, 24);
            const lineEl = $('path[fill="none"]', svg);
            const fillEl = $('path.fill', svg);
            if (fillEl) fillEl.setAttribute('d', fill);
            if (lineEl) {
                lineEl.setAttribute('d', line);
                // animación de trazo
                const len = lineEl.getTotalLength ? lineEl.getTotalLength() : 0;
                if (len) {
                    lineEl.style.transition = 'none';
                    lineEl.style.strokeDasharray = len;
                    lineEl.style.strokeDashoffset = len;
                    requestAnimationFrame(() => {
                        lineEl.style.transition = 'stroke-dashoffset .9s ease';
                        lineEl.style.strokeDashoffset = '0';
                    });
                }
            }
        });
    }

    let activityChart = null;
    function renderActivityChart(series, days) {
        const canvas = $('#dm-activity-chart');
        if (!canvas || typeof Chart === 'undefined') return;
        const slice = series.slice(-days);
        const labels = slice.map((d) => {
            const dt = new Date(d.date + 'T00:00:00');
            return dt.getDate() + ' ' + MES_CORTO[dt.getMonth()].toLowerCase();
        });
        const completed = slice.map((d) => +d.completed);
        const scheduled = slice.map((d) => +d.scheduled);
        const cancelled = slice.map((d) => +d.cancelled);

        const cx = canvas.getContext('2d');
        const grad = (hex) => { const g = cx.createLinearGradient(0, 0, 0, 180); g.addColorStop(0, hex + 'cc'); g.addColorStop(1, hex + '55'); return g; };
        const tip = { backgroundColor: '#0f1729', titleColor: '#fff', bodyColor: '#cbd2e0', padding: 11, cornerRadius: 10, boxPadding: 5, usePointStyle: true, titleFont: { weight: '700' } };

        const data = {
            labels,
            datasets: [
                { label: 'Completadas', data: completed, backgroundColor: grad('#059669'), borderRadius: 5, stack: 's', maxBarThickness: 26 },
                { label: 'Agendadas',   data: scheduled, backgroundColor: grad('#4f46e5'), borderRadius: 5, stack: 's', maxBarThickness: 26 },
                { label: 'Canceladas',  data: cancelled, backgroundColor: grad('#e11d48'), borderRadius: 5, stack: 's', maxBarThickness: 26 },
            ],
        };
        const options = {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14, font: { size: 11.5, weight: '600' } } },
                tooltip: tip,
            },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(15,23,41,.06)' }, border: { display: false }, ticks: { precision: 0, font: { size: 10 } } },
            },
        };
        if (activityChart) {
            activityChart.data = data;
            activityChart.options = options;
            activityChart.update();
        } else {
            activityChart = new Chart(canvas, { type: 'bar', data, options });
        }
    }

    let activitySeries = null;
    async function initActivity() {
        const r = await api('GET', '/portal-doctor/me/activity', { days: 30 });
        if (!r.ok || !r.data || !Array.isArray(r.data.series)) return;
        activitySeries = r.data.series;
        renderSparklines(activitySeries);

        // rango por defecto = el del botón activo (14)
        const seg = $('#dm-activity-range');
        let range = 14;
        if (seg) {
            const active = $('.dm-seg-btn.on', seg);
            if (active) range = +active.dataset.days || 14;
            seg.addEventListener('click', (e) => {
                const btn = e.target.closest('.dm-seg-btn');
                if (!btn) return;
                $$('.dm-seg-btn', seg).forEach((b) => b.classList.remove('on'));
                btn.classList.add('on');
                renderActivityChart(activitySeries, +btn.dataset.days || 14);
            });
        }
        renderActivityChart(activitySeries, range);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  2) MINI-CALENDARIO INTERACTIVO
    // ════════════════════════════════════════════════════════════════════════
    function initCalendar() {
        const root = $('#dm-minical');
        if (!root) return;
        const grid  = $('#dm-minical-grid', root);
        const label = $('#dm-minical-label', root);
        const detail = $('#dm-minical-detail', root);
        if (!grid || !label) return;

        seedEvents(CFG.events);
        const today = CFG.today || isoOf(new Date());
        let view = new Date(today + 'T00:00:00');
        view.setDate(1);
        let selected = null;

        async function render() {
            const y = view.getFullYear(), m = view.getMonth();
            const key = y + '-' + pad(m + 1);
            label.textContent = MES_LARGO[m] + ' de ' + y;

            const evs = await loadMonth(key);
            const byDay = {};
            evs.forEach((e) => { (byDay[e.iso] = byDay[e.iso] || []).push(e); });

            const firstDow = (new Date(y, m, 1).getDay() + 6) % 7; // 0=lun
            const dim = new Date(y, m + 1, 0).getDate();
            const dimPrev = new Date(y, m, 0).getDate();

            let html = '<table><thead><tr>' +
                ['L','M','X','J','V','S','D'].map((d) => '<th>' + d + '</th>').join('') +
                '</tr></thead><tbody>';
            let cell = 1 - firstDow;
            for (let w = 0; w < 6 && cell <= dim; w++) {
                html += '<tr>';
                for (let d = 0; d < 7; d++, cell++) {
                    if (cell >= 1 && cell <= dim) {
                        const iso = y + '-' + pad(m + 1) + '-' + pad(cell);
                        const cnt = (byDay[iso] || []).length;
                        const cls = [];
                        if (iso === today) cls.push('today');
                        if (cnt) cls.push('has');
                        if (iso === selected) cls.push('sel');
                        html += '<td class="' + cls.join(' ') + '" data-iso="' + iso + '" tabindex="0" role="button">' +
                                '<span>' + cell + (cnt > 1 ? '<i class="dm-cal-cnt">' + cnt + '</i>' : '') + '</span></td>';
                    } else {
                        const num = cell < 1 ? dimPrev + cell : cell - dim;
                        html += '<td class="mut"><span>' + num + '</span></td>';
                    }
                }
                html += '</tr>';
            }
            html += '</tbody></table>';
            grid.innerHTML = html;
        }

        function showDay(iso) {
            if (!detail) return;
            selected = iso;
            $$('td.sel', grid).forEach((td) => td.classList.remove('sel'));
            const td = $('td[data-iso="' + iso + '"]', grid);
            if (td) td.classList.add('sel');

            const key = iso.slice(0, 7);
            const evs = (monthCache[key] || []).filter((e) => e.iso === iso)
                .sort((a, b) => (a.time || '').localeCompare(b.time || ''));
            const dt = new Date(iso + 'T00:00:00');
            const head = dt.getDate() + ' de ' + MES_LARGO[dt.getMonth()];
            const estadoEs = { scheduled: 'Agendada', completed: 'Completada', cancelled: 'Cancelada', pending: 'Pendiente' };

            let body;
            if (!evs.length) {
                body = '<p class="dm-mcd-empty">Sin citas este día.</p>';
            } else {
                body = evs.map((e) => '<a class="dm-mcd-row" href="' + esc(CFG.urls.consulta) + '?appt=' + e.id + '">' +
                    '<span class="dm-mcd-t">' + esc(e.time || '--:--') + '</span>' +
                    '<span class="dm-mcd-n">' + esc(e.title) + '</span>' +
                    '<span class="dm-pill dm-pill-' + esc(e.status) + '">' + esc(estadoEs[e.status] || e.status) + '</span>' +
                    '</a>').join('');
            }
            detail.innerHTML = '<div class="dm-mcd-h"><strong>' + esc(head) + '</strong>' +
                '<a href="' + esc(CFG.urls.agenda) + '?date=' + iso + '" class="dm-clink">Ver en agenda</a></div>' + body;
            detail.hidden = false;
        }

        grid.addEventListener('click', (e) => {
            const td = e.target.closest('td[data-iso]');
            if (td) showDay(td.dataset.iso);
        });
        grid.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.dataset && e.target.dataset.iso) {
                e.preventDefault(); showDay(e.target.dataset.iso);
            }
        });
        $('#dm-minical-prev', root)?.addEventListener('click', () => { view.setMonth(view.getMonth() - 1); render(); });
        $('#dm-minical-next', root)?.addEventListener('click', () => { view.setMonth(view.getMonth() + 1); render(); });

        render();
    }

    // ════════════════════════════════════════════════════════════════════════
    //  3) LISTAS EN VIVO (próximas citas / pacientes de hoy)
    // ════════════════════════════════════════════════════════════════════════
    function renderUpcoming(list) {
        const box = $('#dm-upcoming');
        if (!box) return;
        const today = CFG.today;
        const next = (list || []).filter((a) => String(a.appointment_time).slice(0, 10) !== today).slice(0, 5);
        if (!next.length) return; // conserva el empty server-rendered
        box.innerHTML = next.map((a) => {
            const dt = new Date(a.appointment_time);
            const time = pad(dt.getHours()) + ':' + pad(dt.getMinutes());
            const phone = a.patient_phone ? ' · <i data-lucide="phone"></i> ' + esc(a.patient_phone) : '';
            return '<div class="dm-row dm-row-live">' +
                '<a class="dm-row-link" href="' + esc(CFG.urls.consulta) + '?appt=' + (a.id | 0) + '">' +
                  '<div class="dm-rdate"><strong>' + pad(dt.getDate()) + '</strong><span>' + MES_CORTO[dt.getMonth()] + '</span></div>' +
                  '<div class="dm-rinfo"><div class="n">' + esc(a.patient_name) + '</div>' +
                  '<div class="m"><i data-lucide="clock"></i> ' + time + phone + '</div></div>' +
                '</a>' +
                '<button type="button" class="dm-row-cancel" data-cancel="' + (a.id | 0) + '" title="Cancelar cita" aria-label="Cancelar cita"><i data-lucide="x"></i></button>' +
                '</div>';
        }).join('');
        if (window.lucide) window.lucide.createIcons();
    }

    function renderToday(list) {
        const box = $('#dm-today');
        if (!box) return;
        const today = CFG.today;
        const todays = (list || []).filter((a) => String(a.appointment_time).slice(0, 10) === today);
        if (!todays.length) return; // conserva el empty server-rendered
        box.innerHTML = todays.map((a) => {
            const dt = new Date(a.appointment_time);
            const time = pad(dt.getHours()) + ':' + pad(dt.getMinutes());
            const estado = dt.getTime() < Date.now() ? 'completada' : 'próxima';
            const av = window.doctorAvatar ? window.doctorAvatar(a.patient_name, 'sm') : '';
            return '<a class="dm-visit" href="' + esc(CFG.urls.consulta) + '?appt=' + (a.id | 0) + '">' +
                av +
                '<div style="min-width:0;flex:1"><div class="n">' + esc(a.patient_name) + '</div>' +
                '<div class="m">' + time + ' · ' + estado + '</div></div>' +
                '<span class="dm-rgo"><i data-lucide="chevron-right"></i></span></a>';
        }).join('');
        if (window.lucide) window.lucide.createIcons();
    }

    async function refreshLists(silent) {
        const btns = $$('[data-dm-refresh]');
        btns.forEach((b) => b.classList.add('spinning'));
        const r = await api('GET', '/portal-doctor/me/dashboard');
        btns.forEach((b) => b.classList.remove('spinning'));
        if (!r.ok || !r.data) return;
        renderUpcoming(r.data.upcoming || []);
        renderToday(r.data.upcoming || []);
        if (!silent) flash();
    }

    function flash() {
        $$('.dm-card.dm-live').forEach((c) => {
            c.classList.remove('dm-flash');
            void c.offsetWidth;
            c.classList.add('dm-flash');
        });
    }

    function initLiveLists() {
        // Cancelar cita inline (delegación)
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-cancel]');
            if (!btn) return;
            e.preventDefault();
            const id = btn.dataset.cancel;
            if (!window.confirm('¿Cancelar esta cita?')) return;
            const reason = window.prompt('Motivo de cancelación (opcional):') || '';
            btn.disabled = true;
            const c = await api('POST', '/portal-doctor/me/appointments/' + id + '/cancel', { reason });
            if (c.ok) refreshLists(false);
            else { btn.disabled = false; alert(c.message || 'No se pudo cancelar.'); }
        });

        $$('[data-dm-refresh]').forEach((b) => b.addEventListener('click', () => refreshLists(false)));

        // Auto-refresco cada 90 s mientras la pestaña esté visible
        setInterval(() => { if (!document.hidden && navigator.onLine !== false) refreshLists(true); }, 90000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshLists(true); });
        // Reanudar al recuperar conexión (PWA): refresca datos + gráficos
        window.addEventListener('online', () => {
            refreshLists(true);
            if (activitySeries === null) initActivity();
        });
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initActivity();
        initCalendar();
        initLiveLists();
    });
})();
