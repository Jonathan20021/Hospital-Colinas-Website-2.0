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
    // OJO: en el dashboard, portal-medico.js (que define window.doctorApi) carga
    // DESPUÉS de este archivo. Por eso NO se captura aquí (sería undefined y
    // abortaría todo el módulo), sino dentro del boot (DOMContentLoaded), cuando
    // todos los <script> síncronos ya se ejecutaron.
    let api = window.doctorApi;

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
        // Área apilada suave · paleta de marca HGLC (verde/navy/rojo)
        const grad = (hex) => { const g = cx.createLinearGradient(0, 0, 0, 250); g.addColorStop(0, hex + 'cc'); g.addColorStop(1, hex + '7a'); return g; };
        const tip = { backgroundColor: '#262161', titleColor: '#fff', bodyColor: '#d7d5ec', padding: 12, cornerRadius: 12, boxPadding: 6, usePointStyle: true, titleFont: { weight: '700', family: 'Outfit' } };
        // Área apilada en bandas limpias (fill al dataset anterior, no a origin → sin superposición turbia)
        const mk = (label, data, hex, fillTo) => ({ label, data, borderColor: hex, backgroundColor: grad(hex), fill: fillTo, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: hex, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 });

        const data = {
            labels,
            datasets: [
                mk('Completadas', completed, '#5da334', 'origin'),
                mk('Agendadas',   scheduled, '#322d82', '-1'),
                mk('Canceladas',  cancelled, '#be123c', '-1'),
            ],
        };
        const options = {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { left: 6, right: 12, top: 6 } },
            plugins: {
                legend: { display: false },
                tooltip: tip,
            },
            scales: {
                x: { stacked: true, offset: true, grid: { display: false }, border: { display: false }, ticks: { color: '#9aa2bb', font: { size: 10.5, family: 'Plus Jakarta Sans' }, maxRotation: 0, autoSkip: true, autoSkipPadding: 12, maxTicksLimit: 10 } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(38,33,97,.06)' }, border: { display: false }, ticks: { precision: 0, color: '#9aa2bb', font: { size: 10.5, family: 'Plus Jakarta Sans' } } },
            },
        };
        if (activityChart) {
            activityChart.data = data;
            activityChart.options = options;
            activityChart.update();
        } else {
            activityChart = new Chart(canvas, { type: 'line', data, options });
        }
    }

    // ── Doughnut "Estado de citas" (distribución del período) ──────────────────
    let diagChart = null;
    function renderDiagnoseDonut(series) {
        const canvas = $('#dm-diag-chart');
        if (!canvas || typeof Chart === 'undefined') return;
        let completed, scheduled, cancelled;
        if (series && series.length) {
            completed = series.reduce((s, d) => s + (+d.completed || 0), 0);
            scheduled = series.reduce((s, d) => s + (+d.scheduled || 0), 0);
            cancelled = series.reduce((s, d) => s + (+d.cancelled || 0), 0);
        } else {
            completed = +canvas.dataset.completed || 0;
            scheduled = +canvas.dataset.scheduled || 0;
            cancelled = +canvas.dataset.cancelled || 0;
        }
        const total = completed + scheduled + cancelled;
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('dm-donut-total', total.toLocaleString('es'));
        set('dm-dl-completed', completed); set('dm-dl-scheduled', scheduled); set('dm-dl-cancelled', cancelled);
        const data = { labels: ['Completadas', 'Agendadas', 'Canceladas'], datasets: [{ data: [completed, scheduled, cancelled], backgroundColor: ['#5da334', '#322d82', '#be123c'], borderWidth: 0, hoverOffset: 6, spacing: 2 }] };
        const options = { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false }, tooltip: { backgroundColor: '#262161', padding: 11, cornerRadius: 11, usePointStyle: true, bodyFont: { family: 'Plus Jakarta Sans' } } } };
        if (diagChart) { diagChart.data = data; diagChart.update(); }
        else diagChart = new Chart(canvas, { type: 'doughnut', data, options });
    }

    // ── Deltas reales de los KPIs (últimos 7 d vs 7 d previos) ─────────────────
    function fechaCorta(dt) { return dt.getDate() + ' ' + MES_CORTO[dt.getMonth()].toLowerCase() + ' ' + dt.getFullYear(); }
    function renderDeltas(series) {
        if (!series || series.length < 14) return;
        const n = series.length;
        const sum = (key, a, b) => series.slice(a, b).reduce((s, d) => s + (+d[key] || 0), 0);
        const metric = { today: 'total', pending: 'scheduled', completed: 'completed', week: 'total' };
        Object.keys(metric).forEach((k) => {
            const el = $('[data-k="' + k + '"] [data-k-delta]');
            if (!el) return;
            const recent = sum(metric[k], n - 7, n), prev = sum(metric[k], n - 14, n - 7);
            if (recent === 0 && prev === 0) { el.hidden = true; return; }
            const pct = prev === 0 ? 100 : Math.round((recent - prev) / prev * 100);
            const up = pct >= 0;
            el.className = 'dm-k-delta ' + (up ? 'up' : 'down');
            el.innerHTML = '<i data-lucide="' + (up ? 'trending-up' : 'trending-down') + '"></i> ' + (up ? '+' : '') + pct + '% · 7 d';
            el.hidden = false;
        });
        if (window.lucide) window.lucide.createIcons();
    }

    let activitySeries = null;
    async function initActivity() {
        const r = await api('GET', '/portal-doctor/me/activity', { days: 30 });
        if (!r.ok || !r.data || !Array.isArray(r.data.series)) return;
        activitySeries = r.data.series;
        renderSparklines(activitySeries);
        renderDiagnoseDonut(activitySeries);
        renderDeltas(activitySeries);

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
        const next = (list || []).filter((a) => String(a.appointment_time).slice(0, 10) !== today).slice(0, 6);
        if (!next.length) return; // conserva el empty server-rendered
        box.innerHTML = next.map((a) => {
            const dt = new Date(a.appointment_time);
            const time = pad(dt.getHours()) + ':' + pad(dt.getMinutes());
            const av = window.doctorAvatar ? window.doctorAvatar(a.patient_name, 'md') : '';
            const sub = fechaCorta(dt) + (a.patient_phone ? ' · ' + esc(a.patient_phone) : '');
            return '<a class="dm-vrow" href="' + esc(CFG.urls.consulta) + '?appt=' + (a.id | 0) + '">' + av +
                '<div class="info"><div class="n">' + esc(a.patient_name) + '</div><div class="s">' + sub + '</div></div>' +
                '<span class="when"><b>' + time + '</b>' + MES_CORTO[dt.getMonth()] + ' ' + pad(dt.getDate()) + '</span></a>';
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
        api = window.doctorApi || api;       // ya definido: portal-medico.js corrió antes del DOMContentLoaded
        renderDiagnoseDonut();               // doughnut con los datos del PHP (no necesita la API)
        if (!api) return;                    // sin API se conserva el contenido server-rendered
        initActivity();
        initCalendar();
        initLiveLists();
        // El reveal de entrada lo gestiona portal-medico.js (global a todas las páginas).
    });
})();
