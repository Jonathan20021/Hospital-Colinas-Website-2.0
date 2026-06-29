/* Mi Ciclo — control menstrual del Portal del Paciente · Hospital General Las Colinas
 *
 * Las predicciones (fase, ventana fértil, ovulación, próximo periodo) se calculan
 * en el cliente para una UI instantánea. La persistencia va por
 * /api/portal-proxy.php → /portal/me/cycle/* (datos en medical_call_center).
 * El token JWT nunca toca el navegador. Si el backend aún no responde, la
 * herramienta entra en "modo vista previa" (en memoria, sin guardar).
 */
(function () {
    'use strict';

    const root = document.getElementById('cyc-app');
    if (!root) return;

    const BOOT = window.CYC_BOOT || { firstName: 'paciente', gender: '', today: null };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    /* ====================== Utilidades de fecha ====================== */
    const MS_DAY = 86400000;
    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const MESES_AB = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    function parseDate(str) {
        if (!str) return null;
        const [y, m, d] = String(str).split('-').map(Number);
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d, 12, 0, 0, 0); // mediodía local: a salvo de DST
    }
    function fmtDate(dt) {
        const y = dt.getFullYear();
        const m = String(dt.getMonth() + 1).padStart(2, '0');
        const d = String(dt.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    function addDays(dt, n) { const x = new Date(dt.getTime()); x.setDate(x.getDate() + n); return x; }
    function diffDays(a, b) { return Math.round((stripTime(b) - stripTime(a)) / MS_DAY); }
    function stripTime(dt) { return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()).getTime(); }
    function longDate(dt) { return `${DIAS[dt.getDay()]} ${dt.getDate()} de ${MESES[dt.getMonth()]}`; }

    const TODAY = parseDate(BOOT.today) || new Date(new Date().setHours(12, 0, 0, 0));
    const TODAY_STR = fmtDate(TODAY);

    /* ====================== Catálogos ====================== */
    const FLOW = [
        { id: 'none', emoji: '🚫', label: 'Sin sangrado' },
        { id: 'light', emoji: '💧', label: 'Ligero' },
        { id: 'medium', emoji: '🩸', label: 'Moderado' },
        { id: 'heavy', emoji: '🌊', label: 'Abundante' },
    ];
    const SYMPTOMS = [
        { id: 'cramps', emoji: '🌀', label: 'Cólicos' },
        { id: 'headache', emoji: '🤕', label: 'Dolor de cabeza' },
        { id: 'bloating', emoji: '🎈', label: 'Hinchazón' },
        { id: 'tender_breasts', emoji: '💗', label: 'Senos sensibles' },
        { id: 'acne', emoji: '💢', label: 'Acné' },
        { id: 'fatigue', emoji: '🥱', label: 'Fatiga' },
        { id: 'cravings', emoji: '🍫', label: 'Antojos' },
        { id: 'nausea', emoji: '🤢', label: 'Náuseas' },
        { id: 'backache', emoji: '🩹', label: 'Dolor de espalda' },
        { id: 'insomnia', emoji: '🌙', label: 'Insomnio' },
        { id: 'discharge', emoji: '💧', label: 'Flujo vaginal' },
        { id: 'dizziness', emoji: '💫', label: 'Mareos' },
    ];
    const MOODS = [
        { id: 'happy', emoji: '😊', label: 'Feliz' },
        { id: 'calm', emoji: '😌', label: 'Tranquila' },
        { id: 'energetic', emoji: '⚡', label: 'Con energía' },
        { id: 'irritable', emoji: '😠', label: 'Irritable' },
        { id: 'sad', emoji: '😔', label: 'Triste' },
        { id: 'anxious', emoji: '😰', label: 'Ansiosa' },
        { id: 'sensitive', emoji: '🥺', label: 'Sensible' },
        { id: 'tired', emoji: '😴', label: 'Cansada' },
    ];
    const INTIMACY = [
        { id: 'none', label: 'Sin actividad' },
        { id: 'protected', label: 'Con protección' },
        { id: 'unprotected', label: 'Sin protección' },
    ];
    const GOALS = [
        { id: 'track', icon: 'venus', label: 'Seguir mi ciclo', desc: 'Conoce y predice tu periodo.' },
        { id: 'conceive', icon: 'baby', label: 'Buscar embarazo', desc: 'Resalta tu ventana fértil y ovulación.' },
        { id: 'pregnant', icon: 'heart', label: 'Estoy embarazada', desc: 'Sigue las semanas de tu embarazo.' },
    ];
    const flowById = id => FLOW.find(f => f.id === id);
    const symById = id => SYMPTOMS.find(s => s.id === id);
    const moodById = id => MOODS.find(m => m.id === id);

    /* ====================== Estado ====================== */
    let PREVIEW = false;
    const state = {
        settings: { avg_cycle_length: 28, avg_period_length: 5, goal: 'track' },
        periods: [],   // [{id, start_date, end_date}]
        logs: {},      // { 'YYYY-MM-DD': {flow, symptoms:[], moods:[], pain, temp, intimacy, notes} }
    };
    let calCursor = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1, 12);
    let pred = null; // resultado de computePredictions()

    /* ====================== Proxy / API ====================== */
    async function proxy(method, path, payload = {}) {
        const isGet = method === 'GET';
        let res;
        try {
            res = await fetch(proxyUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ method, path, query: isGet ? payload : undefined, body: isGet ? undefined : payload }),
            });
        } catch (e) {
            return { ok: false, status: 0, success: false, message: 'red' };
        }
        const txt = await res.text();
        let json; try { json = JSON.parse(txt); } catch { json = { success: false }; }
        return { ok: res.ok && json.success, status: res.status, ...json };
    }

    // Persiste si hay backend; en vista previa solo conserva en memoria.
    async function persist(method, path, payload) {
        if (PREVIEW) return { ok: true, preview: true };
        const r = await proxy(method, path, payload);
        if (!r.ok && r.status !== 0) toast('No se pudo guardar. Intenta de nuevo.', 'error');
        else if (r.status === 0) toast('Sin conexión. Se guardará cuando vuelvas a tener señal.', 'error');
        return r;
    }

    /* ====================== Carga inicial ====================== */
    async function load() {
        const r = await proxy('GET', '/portal/me/cycle');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) {
            applyData(r.data);
        } else {
            PREVIEW = true;
            document.getElementById('cyc-preview')?.removeAttribute('hidden');
        }
        root.setAttribute('aria-busy', 'false');
        recompute();
        bind();
        if (!state.periods.length) openOnboarding();
        renderAll();
    }

    function applyData(d) {
        if (d.settings) state.settings = Object.assign(state.settings, d.settings);
        state.periods = Array.isArray(d.periods) ? d.periods.slice() : [];
        state.logs = d.logs && typeof d.logs === 'object' ? d.logs : {};
    }

    function sortedPeriods() {
        return state.periods.slice().sort((a, b) => (a.start_date < b.start_date ? -1 : 1));
    }

    /* ====================== Predicciones (cliente) ====================== */
    function computePredictions() {
        const periods = sortedPeriods();
        const setCycle = clampInt(state.settings.avg_cycle_length, 21, 40, 28);
        const setPeriod = clampInt(state.settings.avg_period_length, 2, 10, 5);

        // longitudes observadas entre inicios consecutivos
        const lengths = [];
        for (let i = 1; i < periods.length; i++) {
            const dd = diffDays(parseDate(periods[i - 1].start_date), parseDate(periods[i].start_date));
            if (dd >= 18 && dd <= 60) lengths.push(dd);
        }
        const recent = lengths.slice(-6);
        const avgCycle = recent.length ? Math.round(avg(recent)) : setCycle;

        // duración media del periodo (real si hay end)
        const durs = periods.map(p => p.end_date ? diffDays(parseDate(p.start_date), parseDate(p.end_date)) + 1 : null).filter(x => x && x >= 1 && x <= 12);
        const avgPeriod = durs.length ? Math.round(avg(durs.slice(-6))) : setPeriod;

        // regularidad: desviación de las longitudes
        let regularity = null, spread = 0;
        if (recent.length >= 2) {
            spread = Math.max(...recent) - Math.min(...recent);
            regularity = spread <= 3 ? 'regular' : (spread <= 7 ? 'algo-irregular' : 'irregular');
        }

        const lastStart = periods.length ? parseDate(periods[periods.length - 1].start_date) : null;

        // proyección de ciclos (incluye el actual + futuros) para el calendario
        const cycles = [];
        if (lastStart) {
            for (let k = 0; k < 13; k++) {
                const start = addDays(lastStart, avgCycle * k);
                const ovulation = addDays(start, avgCycle - 14);
                cycles.push({
                    start,
                    periodEnd: addDays(start, avgPeriod - 1),
                    ovulation,
                    fertileStart: addDays(ovulation, -5),
                    fertileEnd: addDays(ovulation, 1),
                    predicted: k > 0,
                });
            }
        }

        // estado "hoy"
        let cycleDay = null, phase = null, daysToPeriod = null, nextStart = null, late = 0;
        if (lastStart) {
            cycleDay = diffDays(lastStart, TODAY) + 1;
            // si ya pasó un ciclo completo sin registro, ubicar el periodo previsto vigente
            let baseStart = lastStart;
            while (cycleDay > avgCycle + 0) {
                const nb = addDays(baseStart, avgCycle);
                if (diffDays(nb, TODAY) + 1 > avgCycle && diffDays(nb, TODAY) >= 0) { baseStart = nb; cycleDay = diffDays(baseStart, TODAY) + 1; }
                else break;
            }
            nextStart = addDays(baseStart, avgCycle);
            daysToPeriod = diffDays(TODAY, nextStart);
            const ovDay = avgCycle - 14;
            if (cycleDay <= avgPeriod) phase = 'period';
            else if (cycleDay >= ovDay - 5 && cycleDay <= ovDay - 1) phase = 'fertile';
            else if (cycleDay >= ovDay && cycleDay <= ovDay + 1) phase = 'ovulation';
            else if (cycleDay < ovDay - 5) phase = 'follicular';
            else phase = 'luteal';
            if (daysToPeriod < 0) { late = -daysToPeriod; phase = 'late'; }
        }

        // embarazo
        let pregWeeks = null, pregDays = null, dueDate = null;
        if (state.settings.goal === 'pregnant' && lastStart) {
            const gd = diffDays(lastStart, TODAY);
            pregWeeks = Math.floor(gd / 7);
            pregDays = gd % 7;
            dueDate = addDays(lastStart, 280);
        }

        return { avgCycle, avgPeriod, lengths: recent, regularity, spread, lastStart, cycles, cycleDay, phase, daysToPeriod, nextStart, late, pregWeeks, pregDays, dueDate, ovDay: avgCycle - 14 };
    }
    function recompute() { pred = computePredictions(); }

    const avg = arr => arr.reduce((a, b) => a + b, 0) / arr.length;
    function clampInt(v, lo, hi, def) { v = parseInt(v, 10); if (isNaN(v)) return def; return Math.min(hi, Math.max(lo, v)); }

    // sets de fechas para pintar el calendario
    function dayState(dt) {
        const key = fmtDate(dt);
        let st = { period: false, predPeriod: false, fertile: false, ovu: false };
        // periodos reales registrados
        for (const p of state.periods) {
            const s = parseDate(p.start_date);
            const e = p.end_date ? parseDate(p.end_date) : addDays(s, (pred?.avgPeriod || 5) - 1);
            if (stripTime(dt) >= stripTime(s) && stripTime(dt) <= stripTime(e)) st.period = true;
        }
        if (pred) {
            for (const c of pred.cycles) {
                if (c.predicted && stripTime(dt) >= stripTime(c.start) && stripTime(dt) <= stripTime(c.periodEnd)) st.predPeriod = true;
                if (stripTime(dt) >= stripTime(c.fertileStart) && stripTime(dt) <= stripTime(c.fertileEnd)) st.fertile = true;
                if (stripTime(dt) === stripTime(c.ovulation)) st.ovu = true;
            }
        }
        if (st.period) st.predPeriod = false;
        st.logged = !!state.logs[key];
        return st;
    }

    /* ====================== Render principal ====================== */
    function renderAll() { renderToday(); renderCalendar(); renderTrends(); renderReminders(); if (window.lucide) lucide.createIcons(); }

    const $ = id => document.getElementById(id);
    const CX = 140, CY = 140, R = 120, C = 2 * Math.PI * R;

    function renderToday() {
        const wheel = $('cyc-wheel');
        const segG = $('cyc-ring-segments');
        const todayG = $('cyc-ring-today');
        const big = $('cyc-wheel-big'), sub = $('cyc-wheel-sub'), kicker = $('cyc-wheel-kicker');
        const cta = $('cyc-wheel-cta'), prog = $('cyc-ring-progress');
        segG.innerHTML = ''; todayG.innerHTML = '';
        wheel.className = 'cyc-wheel';

        if (!pred || !pred.lastStart) {
            kicker.textContent = 'Mi Ciclo';
            big.textContent = '✨';
            sub.textContent = 'Configura tu ciclo para empezar';
            cta.hidden = false; cta.textContent = 'Comenzar';
            cta.onclick = openOnboarding;
            prog.style.strokeDashoffset = C;
            $('cyc-phase-sub').textContent = 'Aún no has configurado tu ciclo';
            updateGoalLabel();
            return;
        }
        cta.hidden = true;
        updateGoalLabel();

        // Modo embarazo
        if (state.settings.goal === 'pregnant' && pred.pregWeeks != null) {
            wheel.classList.add('phase-fertile');
            kicker.textContent = 'Embarazo';
            big.textContent = `${pred.pregWeeks}`;
            sub.innerHTML = `semana${pred.pregWeeks === 1 ? '' : 's'} y ${pred.pregDays} día${pred.pregDays === 1 ? '' : 's'}`;
            const f = Math.min(1, diffDays(pred.lastStart, TODAY) / 280);
            prog.style.strokeDashoffset = C * (1 - f);
            $('cyc-phase-sub').textContent = `Fecha probable de parto: ${pred.dueDate.getDate()} ${MESES_AB[pred.dueDate.getMonth()]} ${pred.dueDate.getFullYear()}`;
            renderTips();
            return;
        }

        // progreso
        const frac = Math.min(1, Math.max(0, pred.cycleDay / pred.avgCycle));
        prog.style.strokeDashoffset = C * (1 - frac);

        // segmento de periodo (día 1..avgPeriod)
        addSeg(segG, 0, pred.avgPeriod / pred.avgCycle, 'cyc-seg-period');
        // segmento fértil
        const fs = (pred.ovDay - 5 - 1) / pred.avgCycle;
        const fe = (pred.ovDay + 1) / pred.avgCycle;
        addSeg(segG, fs, fe - fs, 'cyc-seg-fertile');
        addSeg(segG, (pred.ovDay - 1) / pred.avgCycle, 1 / pred.avgCycle, 'cyc-seg-ovu');

        // marcador hoy
        const ang = 2 * Math.PI * frac;
        const x = CX + R * Math.cos(ang), y = CY + R * Math.sin(ang);
        const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('r', 9);
        dot.setAttribute('class', 'cyc-today-dot');
        todayG.appendChild(dot);

        // textos centrales
        const phaseMap = {
            period: ['phase-period', 'Estás en tu periodo', 'period'],
            follicular: ['phase-follicular', 'Fase folicular', 'follicular'],
            fertile: ['phase-fertile', 'Días fértiles', 'fertile'],
            ovulation: ['phase-ovulation', 'Ovulación', 'ovulation'],
            luteal: ['phase-luteal', 'Fase lútea', 'luteal'],
            late: ['phase-luteal', 'Periodo atrasado', 'late'],
        };
        const pm = phaseMap[pred.phase] || phaseMap.follicular;
        wheel.classList.add(pm[0]);

        if (pred.phase === 'late') {
            kicker.textContent = 'Atrasado';
            big.textContent = `${pred.late}`;
            sub.textContent = `día${pred.late === 1 ? '' : 's'} de atraso`;
        } else if (pred.phase === 'period') {
            kicker.textContent = `Día ${pred.cycleDay} del periodo`;
            big.textContent = `${pred.cycleDay}`;
            sub.textContent = 'de tu periodo';
        } else {
            kicker.textContent = pm[1];
            big.textContent = `${Math.max(0, pred.daysToPeriod)}`;
            sub.textContent = pred.daysToPeriod === 0 ? 'tu periodo llega hoy' : `día${pred.daysToPeriod === 1 ? '' : 's'} para tu periodo`;
        }

        const subParts = [`Día ${pred.cycleDay} de tu ciclo`, pm[1]];
        $('cyc-phase-sub').textContent = subParts.join(' · ');
        renderTips();
    }

    function addSeg(g, startFrac, lenFrac, cls) {
        if (lenFrac <= 0) return;
        const el = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        el.setAttribute('cx', CX); el.setAttribute('cy', CY); el.setAttribute('r', R);
        el.setAttribute('class', 'cyc-seg ' + cls);
        const dash = lenFrac * C;
        el.style.strokeDasharray = `${dash} ${C - dash}`;
        el.style.strokeDashoffset = `${-startFrac * C}`;
        g.appendChild(el);
    }

    function renderTips() {
        const wrap = $('cyc-insight-cards');
        const goal = state.settings.goal;
        const tips = [];
        const p = pred.phase;
        if (p === 'period') {
            tips.push(['tone-period', 'flame', 'Cuida tu energía', 'El descanso, la hidratación y el calor local ayudan con los cólicos. Si el sangrado es muy abundante, coméntalo en tu próxima consulta.']);
        } else if (p === 'fertile' || p === 'ovulation') {
            if (goal === 'conceive') tips.push(['tone-fertile', 'sparkles', 'Tu ventana más fértil', 'Estos son los días de mayor probabilidad de embarazo. La ovulación estimada es alrededor del día ' + pred.ovDay + ' de tu ciclo.']);
            else tips.push(['tone-fertile', 'sparkles', 'Días fértiles', 'Tu cuerpo está en su fase más fértil. Si no buscas embarazo, toma tus precauciones.']);
        } else if (p === 'luteal') {
            tips.push(['tone-luteal', 'moon', 'Fase premenstrual', 'Es normal sentir cambios de ánimo, hinchazón o antojos. Dormir bien y moverte ayuda. Registra cómo te sientes.']);
        } else if (p === 'late') {
            tips.push(['tone-period', 'alert-circle', 'Tu periodo está atrasado', 'Un atraso puede deberse a muchos factores. Si tu periodo no llega y podrías estar embarazada, considera una prueba o consulta con tu ginecólogo.']);
        } else {
            tips.push(['', 'trending-up', 'Tu energía va en aumento', 'Tras el periodo, muchas mujeres se sienten con más energía. Buen momento para actividad física.']);
        }
        // recordatorio de registro
        if (!state.logs[TODAY_STR]) tips.push(['', 'pencil', '¿Cómo te sientes hoy?', 'Registra tu flujo, síntomas y ánimo. Mientras más registres, mejores serán tus predicciones.']);
        // próximo periodo / cita
        if (pred.nextStart && pred.phase !== 'late' && pred.phase !== 'period') {
            tips.push(['', 'calendar-heart', 'Tu próximo periodo', `Lo esperamos para el ${longDate(pred.nextStart)}. Te avisaremos al acercarse.`]);
        }
        wrap.innerHTML = tips.map(t => `
            <div class="cyc-tip ${t[0]}">
                <span class="cyc-tip-ic"><i data-lucide="${t[1]}"></i></span>
                <div><h3>${t[2]}</h3><p>${t[3]}</p></div>
            </div>`).join('');
    }

    /* ====================== Calendario ====================== */
    function renderCalendar() {
        const grid = $('cyc-cal-grid');
        const y = calCursor.getFullYear(), m = calCursor.getMonth();
        $('cyc-cal-month').textContent = `${MESES[m]} ${y}`;
        const first = new Date(y, m, 1, 12);
        let startDow = first.getDay(); // 0 dom
        startDow = startDow === 0 ? 6 : startDow - 1; // semana inicia lunes
        const daysIn = new Date(y, m + 1, 0).getDate();

        let html = '';
        for (let i = 0; i < startDow; i++) html += '<button class="cyc-day is-empty" tabindex="-1"></button>';
        for (let d = 1; d <= daysIn; d++) {
            const dt = new Date(y, m, d, 12);
            const key = fmtDate(dt);
            const st = dayState(dt);
            const cls = ['cyc-day'];
            if (st.period) cls.push('is-period');
            else if (st.ovu) cls.push('is-ovu');
            else if (st.fertile) cls.push('is-fertile');
            else if (st.predPeriod) cls.push('is-pred');
            if (key === TODAY_STR) cls.push('is-today');
            const future = stripTime(dt) > stripTime(TODAY);
            html += `<button class="${cls.join(' ')}" data-date="${key}" ${future ? 'data-future="1"' : ''}>
                        <span class="cyc-day-num">${d}</span>
                        ${st.logged ? '<span class="cyc-day-marks"><i></i></span>' : ''}
                     </button>`;
        }
        grid.innerHTML = html;
        grid.querySelectorAll('.cyc-day[data-date]').forEach(btn => {
            btn.addEventListener('click', () => selectDay(btn.dataset.date));
        });
    }

    function selectDay(key) {
        const dt = parseDate(key);
        const detail = $('cyc-day-detail');
        const log = state.logs[key];
        const st = dayState(dt);
        const future = stripTime(dt) > stripTime(TODAY);
        const tags = [];
        if (st.period) tags.push(['droplet', 'Periodo']);
        if (st.predPeriod) tags.push(['droplet', 'Periodo previsto']);
        if (st.ovu) tags.push(['egg', 'Ovulación estimada']);
        else if (st.fertile) tags.push(['sparkles', 'Día fértil']);
        if (log?.flow && log.flow !== 'none') { const f = flowById(log.flow); tags.push(['waves', 'Flujo ' + (f?.label.toLowerCase() || '')]); }
        (log?.symptoms || []).forEach(s => { const o = symById(s); if (o) tags.push([null, o.emoji + ' ' + o.label]); });
        (log?.moods || []).forEach(mo => { const o = moodById(mo); if (o) tags.push([null, o.emoji + ' ' + o.label]); });

        detail.hidden = false;
        detail.innerHTML = `
            <div class="cyc-day-detail-head">
                <h3>${longDate(dt)}</h3>
                ${!future ? `<button type="button" class="cyc-action-log btn-sm" id="cyc-detail-edit"><i data-lucide="pencil"></i></button>` : ''}
            </div>
            ${tags.length ? `<div class="cyc-day-tags">${tags.map(t => `<span class="cyc-day-tag">${t[0] ? `<i data-lucide="${t[0]}"></i>` : ''}${t[1]}</span>`).join('')}</div>`
                : `<p class="cyc-empty">${future ? 'Día futuro — predicción basada en tu historial.' : 'Sin registro este día. Toca el lápiz para añadir cómo te sentiste.'}</p>`}
            ${!future ? `<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" class="btn btn-outline btn-sm" id="cyc-detail-period"><i data-lucide="droplet"></i> ${st.period ? 'Editar periodo' : 'Marcar inicio de periodo'}</button>
            </div>` : ''}
        `;
        if (window.lucide) lucide.createIcons();
        $('cyc-detail-edit')?.addEventListener('click', () => openLog(key));
        $('cyc-detail-period')?.addEventListener('click', () => markPeriodStart(key));
        detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ====================== Tendencias ====================== */
    function renderTrends() {
        const wrap = $('cyc-stats');
        if (!pred || !pred.lastStart) {
            wrap.innerHTML = `<div class="cyc-card"><p class="cyc-empty">Registra al menos un periodo para ver tus tendencias.</p></div>`;
            return;
        }
        const regTxt = { regular: 'Regular', 'algo-irregular': 'Algo irregular', irregular: 'Irregular' };
        const regPct = pred.regularity === 'regular' ? 92 : pred.regularity === 'algo-irregular' ? 64 : 38;

        // síntomas más frecuentes
        const counts = {};
        Object.values(state.logs).forEach(l => (l.symptoms || []).forEach(s => counts[s] = (counts[s] || 0) + 1));
        const topSym = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 4);

        let cards = `
            <div class="cyc-stat">
                <div class="cyc-stat-label"><i data-lucide="repeat"></i> Ciclo promedio</div>
                <div class="cyc-stat-value">${pred.avgCycle} <small>días</small></div>
                <div class="cyc-stat-foot">${pred.lengths.length ? 'Según tus últimos ' + pred.lengths.length + ' ciclos' : 'Estimado inicial'}</div>
            </div>
            <div class="cyc-stat">
                <div class="cyc-stat-label"><i data-lucide="droplet"></i> Duración del periodo</div>
                <div class="cyc-stat-value">${pred.avgPeriod} <small>días</small></div>
                <div class="cyc-stat-foot">Promedio de tus periodos</div>
            </div>
            <div class="cyc-stat">
                <div class="cyc-stat-label"><i data-lucide="activity"></i> Regularidad</div>
                <div class="cyc-stat-value" style="font-size:1.3rem">${pred.regularity ? regTxt[pred.regularity] : '—'}</div>
                <div class="cyc-reg-bar ${pred.regularity === 'irregular' ? 'is-irregular' : ''}"><i style="width:${regPct}%"></i></div>
                ${pred.regularity ? `<div class="cyc-stat-foot">Variación de ${pred.spread} día${pred.spread === 1 ? '' : 's'} entre ciclos</div>` : ''}
            </div>
            <div class="cyc-stat">
                <div class="cyc-stat-label"><i data-lucide="list"></i> Ciclos registrados</div>
                <div class="cyc-stat-value">${state.periods.length}</div>
                <div class="cyc-stat-foot">${Object.keys(state.logs).length} día${Object.keys(state.logs).length === 1 ? '' : 's'} con registro</div>
            </div>`;
        wrap.innerHTML = cards;

        // barras de historial
        if (pred.lengths.length >= 1) {
            const maxL = Math.max(...pred.lengths, pred.avgCycle) + 2;
            const bars = pred.lengths.map((l, i) => {
                const h = Math.round((l / maxL) * 100);
                const ph = Math.round((pred.avgPeriod / l) * 100);
                return `<div class="cyc-bar"><div class="cyc-bar-fill" style="height:${h}%"><div class="cyc-bar-period" style="height:${ph}%"></div></div><span class="cyc-bar-val">${l}</span><span class="cyc-bar-lbl">#${i + 1}</span></div>`;
            }).join('');
            const hist = document.createElement('div');
            hist.className = 'cyc-card cyc-history';
            hist.innerHTML = `<h3>Longitud de tus últimos ciclos</h3><div class="cyc-bars">${bars}</div>`;
            wrap.appendChild(hist);
        }
        if (topSym.length) {
            const tags = topSym.map(([id, n]) => { const o = symById(id); return `<span class="cyc-day-tag">${o ? o.emoji + ' ' + o.label : id} · ${n}</span>`; }).join('');
            const sc = document.createElement('div');
            sc.className = 'cyc-card';
            sc.innerHTML = `<h3 style="margin:0 0 12px;font-size:1rem;font-weight:700">Tus síntomas más frecuentes</h3><div class="cyc-day-tags">${tags}</div>`;
            wrap.appendChild(sc);
        }
    }

    /* ====================== Registro diario (sheet) ====================== */
    function openLog(key) {
        const sheet = $('cyc-log-sheet');
        const dt = parseDate(key);
        sheet.dataset.date = key;
        $('cyc-log-date').textContent = key === TODAY_STR ? 'Hoy · ' + longDate(dt) : longDate(dt);
        const log = state.logs[key] || { flow: '', symptoms: [], moods: [], pain: 0, temp: '', intimacy: '', notes: '' };
        $('cyc-sheet-body').innerHTML = buildLogForm(log);
        if (window.lucide) lucide.createIcons();
        wireLogForm();
        showDialog(sheet);
    }

    function buildLogForm(log) {
        const chip = (active, val, inner) => `<button type="button" class="cyc-chip ${active ? 'is-on' : ''}" data-val="${val}">${inner}</button>`;
        const flow = `<div class="cyc-group cyc-flow"><span class="cyc-group-label">Sangrado</span><div class="cyc-chips" data-group="flow" data-single="1">
            ${FLOW.map(f => chip(log.flow === f.id, f.id, `<span class="cyc-chip-emoji">${f.emoji}</span> ${f.label}`)).join('')}</div></div>`;
        const sym = `<div class="cyc-group"><span class="cyc-group-label">Síntomas</span><div class="cyc-chips" data-group="symptoms">
            ${SYMPTOMS.map(s => chip((log.symptoms || []).includes(s.id), s.id, `<span class="cyc-chip-emoji">${s.emoji}</span> ${s.label}`)).join('')}</div></div>`;
        const mood = `<div class="cyc-group"><span class="cyc-group-label">Estado de ánimo</span><div class="cyc-chips" data-group="moods">
            ${MOODS.map(m => chip((log.moods || []).includes(m.id), m.id, `<span class="cyc-chip-emoji">${m.emoji}</span> ${m.label}`)).join('')}</div></div>`;
        const intim = `<div class="cyc-group"><span class="cyc-group-label">Actividad sexual</span><div class="cyc-chips" data-group="intimacy" data-single="1">
            ${INTIMACY.map(o => chip(log.intimacy === o.id, o.id, o.label)).join('')}</div></div>`;
        const pain = `<div class="cyc-group"><span class="cyc-group-label">Dolor</span><div class="cyc-range">
            <input type="range" id="cyc-pain" min="0" max="3" step="1" value="${log.pain || 0}">
            <output id="cyc-pain-out"></output></div></div>`;
        const temp = `<div class="cyc-group"><span class="cyc-group-label">Temperatura basal (opcional)</span><div class="cyc-range">
            <input type="range" id="cyc-temp" min="35.5" max="37.8" step="0.05" value="${log.temp || 36.5}">
            <output id="cyc-temp-out"></output></div></div>`;
        const notes = `<div class="cyc-group"><span class="cyc-group-label">Notas</span>
            <textarea class="cyc-note" id="cyc-notes" placeholder="Escribe lo que quieras recordar…">${(log.notes || '').replace(/</g, '&lt;')}</textarea></div>`;
        return flow + sym + mood + intim + pain + temp + notes;
    }

    function wireLogForm() {
        $('cyc-sheet-body').querySelectorAll('.cyc-chips').forEach(group => {
            const single = group.dataset.single === '1';
            group.querySelectorAll('.cyc-chip').forEach(ch => ch.addEventListener('click', () => {
                if (single) { const on = ch.classList.contains('is-on'); group.querySelectorAll('.cyc-chip').forEach(x => x.classList.remove('is-on')); if (!on) ch.classList.add('is-on'); }
                else ch.classList.toggle('is-on');
            }));
        });
        const pain = $('cyc-pain'), painOut = $('cyc-pain-out');
        const PAIN = ['Ninguno', 'Leve', 'Moderado', 'Fuerte'];
        const upPain = () => painOut.textContent = PAIN[pain.value];
        pain.addEventListener('input', upPain); upPain();
        const temp = $('cyc-temp'), tempOut = $('cyc-temp-out');
        const upTemp = () => tempOut.textContent = (+temp.value).toFixed(2) + ' °C';
        temp.addEventListener('input', upTemp); upTemp();
    }

    function collectLog() {
        const body = $('cyc-sheet-body');
        const single = g => body.querySelector(`.cyc-chips[data-group="${g}"] .cyc-chip.is-on`)?.dataset.val || '';
        const multi = g => Array.from(body.querySelectorAll(`.cyc-chips[data-group="${g}"] .cyc-chip.is-on`)).map(x => x.dataset.val);
        const temp = $('cyc-temp');
        return {
            flow: single('flow'),
            symptoms: multi('symptoms'),
            moods: multi('moods'),
            intimacy: single('intimacy'),
            pain: parseInt($('cyc-pain').value, 10) || 0,
            temp: temp && +temp.value !== 36.5 ? +(+temp.value).toFixed(2) : '',
            notes: $('cyc-notes').value.trim().slice(0, 500),
        };
    }

    async function saveLog() {
        const sheet = $('cyc-log-sheet');
        const key = sheet.dataset.date;
        const log = collectLog();
        const empty = !log.flow && !log.symptoms.length && !log.moods.length && !log.intimacy && !log.pain && !log.temp && !log.notes;
        if (empty) { delete state.logs[key]; }
        else state.logs[key] = log;
        closeDialog(sheet);
        recompute(); renderAll();
        if (empty) await persist('PUT', '/portal/me/cycle/log', { date: key, clear: true });
        else { const r = await persist('PUT', '/portal/me/cycle/log', Object.assign({ date: key }, log)); if (r.ok && !r.preview) toast('Registro guardado', 'success'); }
    }

    async function clearLogDay() {
        const sheet = $('cyc-log-sheet');
        const key = sheet.dataset.date;
        delete state.logs[key];
        closeDialog(sheet);
        recompute(); renderAll();
        await persist('PUT', '/portal/me/cycle/log', { date: key, clear: true });
    }

    /* ====================== Periodos ====================== */
    async function markPeriodStart(key) {
        // evita duplicar un inicio muy cercano
        const existing = state.periods.find(p => Math.abs(diffDays(parseDate(p.start_date), parseDate(key))) <= 1);
        if (existing) { existing.start_date = key; }
        else {
            const local = { id: 'local-' + Date.now(), start_date: key, end_date: null };
            state.periods.push(local);
            const r = await persist('POST', '/portal/me/cycle/period', { start_date: key });
            if (r.ok && r.data && r.data.id) local.id = r.data.id;
        }
        if (existing) await persist('POST', '/portal/me/cycle/period', { start_date: key });
        recompute(); renderAll();
        const sheet = $('cyc-log-sheet'); if (sheet.open) closeDialog(sheet);
        toast(key === TODAY_STR ? '¡Listo! Registramos el inicio de tu periodo' : 'Periodo registrado', 'success');
        switchTab('hoy');
    }

    /* ====================== Objetivo ====================== */
    function updateGoalLabel() {
        const g = GOALS.find(x => x.id === state.settings.goal) || GOALS[0];
        $('cyc-goal-label').textContent = g.label;
    }
    function openGoal() {
        const dlg = $('cyc-goal-dialog');
        $('cyc-goal-options').innerHTML = GOALS.map(g => `
            <button type="button" class="cyc-goal-opt ${state.settings.goal === g.id ? 'is-on' : ''}" data-goal="${g.id}">
                <span class="cyc-goal-opt-ic"><i data-lucide="${g.icon}"></i></span>
                <span><strong>${g.label}</strong><span>${g.desc}</span></span>
                <i data-lucide="check-circle-2" class="cyc-goal-check"></i>
            </button>`).join('');
        if (window.lucide) lucide.createIcons();
        $('cyc-goal-options').querySelectorAll('.cyc-goal-opt').forEach(b => b.addEventListener('click', async () => {
            state.settings.goal = b.dataset.goal;
            updateGoalLabel();
            closeDialog(dlg);
            recompute(); renderAll();
            await persist('PUT', '/portal/me/cycle/settings', { goal: state.settings.goal });
        }));
        showDialog(dlg);
    }

    /* ====================== Onboarding ====================== */
    let onbData = { last: '', period: 5, cycle: 28, goal: 'track' };
    let onbStep = 0;
    function openOnboarding() {
        onbData = { last: '', period: state.settings.avg_period_length || 5, cycle: state.settings.avg_cycle_length || 28, goal: state.settings.goal || 'track' };
        onbStep = 0;
        renderOnboarding();
        showDialog($('cyc-onboard'));
    }
    function renderOnboarding() {
        const inner = $('cyc-onboard-inner');
        const steps = [
            { // 1 fecha
                hero: ['flower-2', `Hola, ${BOOT.firstName} 🌸`, 'Vamos a configurar Mi Ciclo en unos pasos rápidos. Tus datos son privados y solo tú los ves.'],
                q: '¿Cuándo empezó tu último periodo?',
                help: 'Si no recuerdas el día exacto, una fecha aproximada está bien.',
                body: `<div class="cyc-onb-field"><input type="date" id="onb-last" max="${TODAY_STR}" value="${onbData.last}"></div>`,
            },
            { // 2 duración periodo
                hero: ['droplet', '¿Cuántos días te dura?', 'Esto nos ayuda a predecir mejor.'],
                q: '¿Cuántos días dura tu periodo normalmente?',
                body: stepper('period', onbData.period, 'días de sangrado'),
            },
            { // 3 duración ciclo
                hero: ['repeat', 'Tu ciclo', 'Es el tiempo desde el primer día de un periodo hasta el primer día del siguiente.'],
                q: '¿Cada cuántos días te llega?',
                help: 'Si no estás segura, deja 28. Lo iremos ajustando con tus registros.',
                body: stepper('cycle', onbData.cycle, 'días de ciclo'),
            },
            { // 4 objetivo
                hero: ['target', '¿Cuál es tu objetivo?', 'Puedes cambiarlo cuando quieras.'],
                q: '',
                body: `<div style="display:flex;flex-direction:column;gap:10px">${GOALS.map(g => `
                    <button type="button" class="cyc-goal-opt ${onbData.goal === g.id ? 'is-on' : ''}" data-onb-goal="${g.id}">
                        <span class="cyc-goal-opt-ic"><i data-lucide="${g.icon}"></i></span>
                        <span><strong>${g.label}</strong><span>${g.desc}</span></span>
                        <i data-lucide="check-circle-2" class="cyc-goal-check"></i></button>`).join('')}</div>`,
            },
        ];
        const s = steps[onbStep];
        const last = onbStep === steps.length - 1;
        inner.innerHTML = `
            <div class="cyc-onb-hero">
                <div class="cyc-onb-badge"><i data-lucide="${s.hero[0]}"></i></div>
                <h2>${s.hero[1]}</h2><p>${s.hero[2]}</p>
            </div>
            <div class="cyc-onb-body">
                <div class="cyc-onb-step is-active">
                    ${s.q ? `<div class="cyc-onb-q">${s.q}</div>` : ''}
                    ${s.help ? `<p class="cyc-onb-help">${s.help}</p>` : ''}
                    ${s.body}
                </div>
                <div class="cyc-onb-dots">${steps.map((_, i) => `<i class="${i === onbStep ? 'is-on' : ''}"></i>`).join('')}</div>
                <div class="cyc-onb-foot">
                    ${onbStep > 0 ? `<button type="button" class="btn btn-outline" id="onb-back">Atrás</button>` : ''}
                    <button type="button" class="btn btn-green" id="onb-next">${last ? 'Empezar' : 'Continuar'}</button>
                </div>
            </div>`;
        if (window.lucide) lucide.createIcons();
        wireOnboarding(steps.length);
    }
    function stepper(key, val, unit) {
        return `<div class="cyc-stepper" data-stepper="${key}">
            <button type="button" data-step="-1" aria-label="menos">−</button>
            <output>${val}<small>${unit}</small></output>
            <button type="button" data-step="1" aria-label="más">+</button></div>`;
    }
    function wireOnboarding(total) {
        const inner = $('cyc-onboard-inner');
        inner.querySelector('#onb-last')?.addEventListener('change', e => onbData.last = e.target.value);
        inner.querySelectorAll('.cyc-stepper').forEach(st => {
            const key = st.dataset.stepper, out = st.querySelector('output');
            const lim = key === 'period' ? [2, 10] : [21, 40];
            st.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
                onbData[key] = Math.min(lim[1], Math.max(lim[0], onbData[key] + parseInt(b.dataset.step, 10)));
                out.firstChild.textContent = onbData[key];
            }));
        });
        inner.querySelectorAll('[data-onb-goal]').forEach(b => b.addEventListener('click', () => {
            onbData.goal = b.dataset.onbGoal;
            inner.querySelectorAll('[data-onb-goal]').forEach(x => x.classList.toggle('is-on', x === b));
        }));
        inner.querySelector('#onb-back')?.addEventListener('click', () => { onbStep--; renderOnboarding(); });
        inner.querySelector('#onb-next')?.addEventListener('click', () => {
            if (onbStep === 0 && !onbData.last) { toast('Elige la fecha de tu último periodo', 'error'); return; }
            if (onbStep < total - 1) { onbStep++; renderOnboarding(); }
            else finishOnboarding();
        });
    }
    async function finishOnboarding() {
        state.settings.avg_period_length = onbData.period;
        state.settings.avg_cycle_length = onbData.cycle;
        state.settings.goal = onbData.goal;
        if (!state.periods.some(p => p.start_date === onbData.last)) {
            state.periods.push({ id: 'local-' + Date.now(), start_date: onbData.last, end_date: null });
        }
        closeDialog($('cyc-onboard'));
        recompute(); renderAll(); updateGoalLabel();
        toast('¡Tu ciclo está listo! 🌸', 'success');
        await persist('PUT', '/portal/me/cycle/settings', {
            avg_period_length: onbData.period, avg_cycle_length: onbData.cycle, goal: onbData.goal, onboarded: true,
        });
        await persist('POST', '/portal/me/cycle/period', { start_date: onbData.last });
    }

    /* ====================== Resumen médico ====================== */
    function openSummary() {
        const body = $('cyc-summary-body');
        if (!pred || !pred.lastStart) { body.innerHTML = '<p class="cyc-empty">Registra tu ciclo para generar el resumen.</p>'; showDialog($('cyc-summary-dialog')); return; }
        const counts = {};
        Object.values(state.logs).forEach(l => (l.symptoms || []).forEach(s => counts[s] = (counts[s] || 0) + 1));
        const topSym = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 6).map(([id]) => { const o = symById(id); return o ? o.emoji + ' ' + o.label : id; });
        const regTxt = { regular: 'Regular', 'algo-irregular': 'Algo irregular', irregular: 'Irregular' };
        const fum = pred.lastStart;
        body.innerHTML = `
            <div class="cyc-rep-block">
                <h3>Paciente</h3>
                <dl><div class="cyc-rep-row"><dt>Nombre</dt><dd>${esc(capitalize(BOOT.firstName))}</dd></div>
                <div class="cyc-rep-row"><dt>Generado</dt><dd>${longDate(TODAY)}</dd></div></dl>
            </div>
            <div class="cyc-rep-block">
                <h3>Resumen del ciclo</h3>
                <dl>
                    <div class="cyc-rep-row"><dt>Última menstruación (FUM)</dt><dd>${fmtNiceDate(fum)}</dd></div>
                    <div class="cyc-rep-row"><dt>Ciclo promedio</dt><dd>${pred.avgCycle} días</dd></div>
                    <div class="cyc-rep-row"><dt>Duración del periodo</dt><dd>${pred.avgPeriod} días</dd></div>
                    <div class="cyc-rep-row"><dt>Regularidad</dt><dd>${pred.regularity ? regTxt[pred.regularity] : 'Datos insuficientes'}</dd></div>
                    <div class="cyc-rep-row"><dt>Próximo periodo estimado</dt><dd>${pred.nextStart ? fmtNiceDate(pred.nextStart) : '—'}</dd></div>
                    <div class="cyc-rep-row"><dt>Ciclos registrados</dt><dd>${state.periods.length}</dd></div>
                </dl>
            </div>
            ${state.settings.goal === 'pregnant' && pred.pregWeeks != null ? `
            <div class="cyc-rep-block"><h3>Embarazo</h3><dl>
                <div class="cyc-rep-row"><dt>Edad gestacional</dt><dd>${pred.pregWeeks} sem ${pred.pregDays} d</dd></div>
                <div class="cyc-rep-row"><dt>Fecha probable de parto</dt><dd>${fmtNiceDate(pred.dueDate)}</dd></div></dl></div>` : ''}
            ${topSym.length ? `<div class="cyc-rep-block"><h3>Síntomas más frecuentes</h3><div class="cyc-rep-tags">${topSym.map(t => `<span class="cyc-day-tag">${t}</span>`).join('')}</div></div>` : ''}
            <p class="cyc-stat-foot" style="margin-top:4px">Información autorreportada por la paciente desde el Portal. No sustituye una evaluación médica.</p>`;
        showDialog($('cyc-summary-dialog'));
    }

    /* ====================== Recordatorios push ====================== */
    function pushSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }
    function urlB64ToUint8(b64) {
        const pad = '='.repeat((4 - b64.length % 4) % 4);
        const base = (b64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }
    async function ensurePushSubscription() {
        if (!pushSupported()) return { ok: false, reason: 'unsupported' };
        let perm = Notification.permission;
        if (perm === 'default') perm = await Notification.requestPermission();
        if (perm !== 'granted') return { ok: false, reason: 'denied' };
        let reg;
        try { reg = await navigator.serviceWorker.ready; } catch { return { ok: false, reason: 'no_sw' }; }
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            const keyRes = await proxy('GET', '/portal/me/push/key');
            const pub = keyRes && keyRes.data && keyRes.data.publicKey;
            if (!pub) return { ok: false, reason: 'no_key' };
            try { sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8(pub) }); }
            catch { return { ok: false, reason: 'subscribe_failed' }; }
        }
        const j = sub.toJSON();
        const r = await proxy('POST', '/portal/me/push/subscribe', { endpoint: sub.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth, ua: navigator.userAgent });
        return { ok: !!(r && r.ok), reason: (r && r.ok) ? '' : 'save_failed' };
    }
    const remindersOn = () => !!(state.settings.reminders && state.settings.reminders.period);

    function renderReminders() {
        const slot = $('cyc-reminders-slot');
        if (!slot) return;
        const applicable = pred && pred.lastStart && state.settings.goal !== 'pregnant';
        if (PREVIEW || !pushSupported() || !applicable) { slot.innerHTML = ''; return; }
        const on = remindersOn();
        slot.innerHTML = `
            <div class="cyc-remind ${on ? 'is-on' : ''}">
                <span class="cyc-remind-ic"><i data-lucide="bell"></i></span>
                <span class="cyc-remind-copy">
                    <strong>Recordatorios de Mi Ciclo</strong>
                    <span>${on ? 'Te avisaremos antes de tu periodo y tu ventana fértil.' : 'Recibe un aviso antes de tu periodo y tu ventana fértil.'}</span>
                </span>
                <button type="button" class="cyc-switch ${on ? 'is-on' : ''}" id="cyc-remind-switch" role="switch" aria-checked="${on}" aria-label="Activar recordatorios"></button>
            </div>`;
        $('cyc-remind-switch').addEventListener('click', toggleReminders);
    }

    async function toggleReminders() {
        const sw = $('cyc-remind-switch');
        const turningOn = !remindersOn();
        sw.classList.add('is-busy');
        if (turningOn) {
            const res = await ensurePushSubscription();
            if (!res.ok) {
                sw.classList.remove('is-busy');
                toast(res.reason === 'denied'
                    ? 'Activa las notificaciones en tu navegador para recibir recordatorios.'
                    : 'No se pudieron activar los recordatorios. Intenta de nuevo.', 'error');
                return;
            }
        }
        state.settings.reminders = { period: turningOn };
        await persist('PUT', '/portal/me/cycle/settings', { goal: state.settings.goal, reminders: { period: turningOn } });
        sw.classList.remove('is-busy');
        renderReminders();
        toast(turningOn ? 'Recordatorios activados 🔔' : 'Recordatorios desactivados', turningOn ? 'success' : 'info');
    }

    /* ====================== Tabs ====================== */
    function switchTab(name) {
        root.querySelectorAll('.cyc-tab-btn').forEach((b, i) => {
            const on = b.dataset.tab === name;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) moveInk(i);
        });
        root.querySelectorAll('.cyc-panel').forEach(p => {
            const on = p.dataset.panel === name;
            p.classList.toggle('is-active', on);
            p.hidden = !on;
        });
    }
    function moveInk(i) {
        const ink = root.querySelector('.cyc-tabs-ink');
        if (ink) ink.style.transform = `translateX(calc(${i} * (100% + 2px)))`;
    }

    /* ====================== Diálogos ====================== */
    function showDialog(dlg) { if (!dlg.open) { try { dlg.showModal(); } catch { dlg.setAttribute('open', ''); } } }
    function closeDialog(dlg) { try { dlg.close(); } catch { dlg.removeAttribute('open'); } }

    /* ====================== Toast ====================== */
    function toast(msg, type = 'info') {
        const region = document.getElementById('portal-toast-region');
        if (!region) return;
        const t = document.createElement('div');
        t.className = `portal-toast is-${type}`;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        t.innerHTML = `<i data-lucide="${type === 'success' ? 'check-circle-2' : type === 'error' ? 'alert-circle' : 'info'}"></i><span></span>`;
        t.querySelector('span').textContent = msg;
        region.appendChild(t);
        if (window.lucide) lucide.createIcons();
        setTimeout(() => { t.classList.add('is-leaving'); setTimeout(() => t.remove(), 350); }, 3200);
    }

    /* ====================== Helpers de texto ====================== */
    function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
    function capitalize(s) { return s ? s[0].toUpperCase() + s.slice(1) : s; }
    function fmtNiceDate(dt) { return `${dt.getDate()} de ${MESES[dt.getMonth()]} de ${dt.getFullYear()}`; }

    /* ====================== Bindings ====================== */
    function bind() {
        root.querySelectorAll('.cyc-tab-btn').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.tab)));
        moveInk(0);
        $('cyc-action-period').addEventListener('click', () => markPeriodStart(TODAY_STR));
        $('cyc-action-log').addEventListener('click', () => openLog(TODAY_STR));
        $('cyc-goal-btn').addEventListener('click', openGoal);
        $('cyc-cal-prev').addEventListener('click', () => { calCursor = new Date(calCursor.getFullYear(), calCursor.getMonth() - 1, 1, 12); renderCalendar(); if (window.lucide) lucide.createIcons(); });
        $('cyc-cal-next').addEventListener('click', () => { calCursor = new Date(calCursor.getFullYear(), calCursor.getMonth() + 1, 1, 12); renderCalendar(); if (window.lucide) lucide.createIcons(); });
        $('cyc-log-save').addEventListener('click', saveLog);
        $('cyc-log-clear').addEventListener('click', clearLogDay);
        $('cyc-summary-btn').addEventListener('click', openSummary);
        document.querySelectorAll('[data-cyc-close]').forEach(b => b.addEventListener('click', () => {
            const dlg = b.closest('dialog'); if (dlg) closeDialog(dlg);
        }));
        // cerrar al tocar el backdrop
        document.querySelectorAll('dialog.cyc-sheet, dialog.cyc-goal-dialog, dialog.cyc-summary-dialog').forEach(dlg => {
            dlg.addEventListener('click', e => { if (e.target === dlg) closeDialog(dlg); });
        });
    }

    load();
})();
