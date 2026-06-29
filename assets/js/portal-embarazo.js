/* Embarazo semana a semana — Portal del Paciente · Hospital General Las Colinas
 * Calcula la semana de gestación desde la FUM y muestra el desarrollo del bebé.
 * Sincroniza vía /api/portal-proxy.php → /portal/me/pregnancy. Token en sesión.
 */
(function () {
    'use strict';
    const root = document.getElementById('preg-app');
    if (!root) return;

    const BOOT = window.PREG_BOOT || { today: '' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    let PREVIEW = false;
    let STATE = { lmp_date: null, active: false };
    let viewWeek = null;

    /* contenido de 4 a 40 semanas */
    const WEEKS = {
        4: { e: '🌱', c: 'una semilla de amapola', len: '2 mm', dev: 'El embrión se implanta en el útero y empieza a formarse el saco gestacional.' },
        5: { e: '🌱', c: 'una semilla de sésamo', len: '3 mm', dev: 'Comienza a formarse el tubo neural (cerebro y médula espinal).' },
        6: { e: '🫛', c: 'una lenteja', len: '5 mm', dev: 'El corazón empieza a latir.' },
        7: { e: '🫐', c: 'un arándano', len: '1 cm', dev: 'Se forman los brotes de brazos y piernas.' },
        8: { e: '🍇', c: 'una frambuesa', len: '1.6 cm', w: '1 g', dev: 'Se forman los dedos y los rasgos de la cara.' },
        9: { e: '🍒', c: 'una cereza', len: '2.3 cm', w: '2 g', dev: 'Los órganos principales se están formando.' },
        10: { e: '🍓', c: 'una fresa', len: '3.1 cm', w: '4 g', dev: 'Ya se le llama feto; aparecen uñas diminutas.' },
        11: { e: '🥝', c: 'un higo', len: '4.1 cm', w: '7 g', dev: 'Puede abrir y cerrar las manos.' },
        12: { e: '🍋', c: 'una lima', len: '5.4 cm', w: '14 g', dev: 'Tiene reflejos. ¡Fin del primer trimestre!' },
        13: { e: '🫛', c: 'una vaina de guisantes', len: '7.4 cm', w: '23 g', dev: 'Se forman las huellas dactilares.' },
        14: { e: '🍋', c: 'un limón', len: '8.7 cm', w: '43 g', dev: 'Empieza a hacer gestos faciales.' },
        15: { e: '🍎', c: 'una manzana', len: '10 cm', w: '70 g', dev: 'Percibe la luz aunque tiene los ojos cerrados.' },
        16: { e: '🥑', c: 'un aguacate', len: '11.6 cm', w: '100 g', dev: 'Pronto podrías sentir sus primeros movimientos.' },
        17: { e: '🍐', c: 'una pera', len: '13 cm', w: '140 g', dev: 'Desarrolla grasa para regular su temperatura.' },
        18: { e: '🫑', c: 'un pimiento', len: '14.2 cm', w: '190 g', dev: 'Puede empezar a oír sonidos.' },
        19: { e: '🍅', c: 'un tomate', len: '15.3 cm', w: '240 g', dev: 'Se forma el vérnix que protege su piel.' },
        20: { e: '🍌', c: 'un banano', len: '25.6 cm', w: '300 g', dev: '¡Mitad del camino! Suele hacerse la ecografía de detalle.' },
        21: { e: '🥕', c: 'una zanahoria', len: '26.7 cm', w: '360 g', dev: 'Sus movimientos son cada vez más fuertes.' },
        22: { e: '🥥', c: 'un coco pequeño', len: '27.8 cm', w: '430 g', dev: 'Aparecen cejas y pestañas.' },
        23: { e: '🍆', c: 'una berenjena', len: '28.9 cm', w: '500 g', dev: 'Reacciona a los sonidos del exterior.' },
        24: { e: '🌽', c: 'una mazorca de maíz', len: '30 cm', w: '600 g', dev: 'Sus pulmones siguen madurando.' },
        25: { e: '🥬', c: 'un nabo', len: '34.6 cm', w: '660 g', dev: 'Responde a tu voz y al tacto.' },
        26: { e: '🥬', c: 'una lechuga', len: '35.6 cm', w: '760 g', dev: 'Empieza a abrir los ojos.' },
        27: { e: '🥦', c: 'una coliflor', len: '36.6 cm', w: '875 g', dev: '¡Fin del segundo trimestre!' },
        28: { e: '🍆', c: 'una berenjena grande', len: '37.6 cm', w: '1 kg', dev: 'Tiene fases de sueño REM (sueña).' },
        29: { e: '🎃', c: 'una calabaza pequeña', len: '38.6 cm', w: '1.2 kg', dev: 'Sus músculos y pulmones se fortalecen.' },
        30: { e: '🥬', c: 'un repollo', len: '39.9 cm', w: '1.3 kg', dev: 'Empieza a regular su temperatura.' },
        31: { e: '🥥', c: 'un coco', len: '41.1 cm', w: '1.5 kg', dev: 'Todos sus sentidos están activos.' },
        32: { e: '🍈', c: 'un melón', len: '42.4 cm', w: '1.7 kg', dev: 'Suele colocarse cabeza abajo.' },
        33: { e: '🍍', c: 'una piña', len: '43.7 cm', w: '1.9 kg', dev: 'Sus huesos se endurecen (excepto el cráneo).' },
        34: { e: '🍈', c: 'un melón cantalupo', len: '45 cm', w: '2.1 kg', dev: 'Sus pulmones casi están listos.' },
        35: { e: '🍯', c: 'un panal de miel', len: '46.2 cm', w: '2.4 kg', dev: 'Gana peso rápidamente.' },
        36: { e: '🥬', c: 'una lechuga romana', len: '47.4 cm', w: '2.6 kg', dev: 'Desciende hacia la pelvis.' },
        37: { e: '🥬', c: 'una acelga', len: '48.6 cm', w: '2.9 kg', dev: 'A término temprano: ya casi está listo.' },
        38: { e: '🎃', c: 'una calabaza', len: '49.8 cm', w: '3.1 kg', dev: 'Sus órganos están preparados para nacer.' },
        39: { e: '🍉', c: 'una sandía pequeña', len: '50.7 cm', w: '3.3 kg', dev: 'A término: puede nacer cualquier día.' },
        40: { e: '🎃', c: 'una calabaza grande', len: '51.2 cm', w: '3.5 kg', dev: '¡Llegó tu fecha probable de parto!' },
    };
    const TIPS = {
        1: ['Toma ácido fólico todos los días.', 'Agenda tu primera consulta prenatal.', 'Evita alcohol, tabaco y automedicarte.', 'Descansa y mantente hidratada.'],
        2: ['No faltes a tus controles prenatales.', 'Hazte la ecografía de detalle (semana 20-22).', 'Come variado y rico en hierro y calcio.', 'Haz actividad física suave si tu médico lo permite.'],
        3: ['Vigila los movimientos de tu bebé cada día.', 'Prepara tu plan de parto y tu maleta.', 'Tus controles serán más frecuentes.', 'Consulta de inmediato si hay sangrado, dolor fuerte o menos movimientos.'],
    };
    const TRIM_NAME = { 1: 'Primer trimestre', 2: 'Segundo trimestre', 3: 'Tercer trimestre' };
    const trimester = w => (w <= 13 ? 1 : (w <= 27 ? 2 : 3));

    async function proxy(method, path, payload = {}) {
        const isGet = method === 'GET';
        let res;
        try {
            res = await fetch(proxyUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ method, path, query: isGet ? payload : undefined, body: isGet ? undefined : payload }),
            });
        } catch (e) { return { ok: false, status: 0, success: false }; }
        const txt = await res.text();
        let json; try { json = JSON.parse(txt); } catch { json = { success: false }; }
        return { ok: res.ok && json.success, status: res.status, ...json };
    }
    async function persist(method, path, payload) {
        if (PREVIEW) return { ok: true, preview: true };
        const r = await proxy(method, path, payload);
        if (!r.ok && r.status !== 0) toast('No se pudo guardar. Intenta de nuevo.', 'error');
        else if (r.status === 0) toast('Sin conexión.', 'error');
        return r;
    }

    async function load() {
        const r = await proxy('GET', '/portal/me/pregnancy');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) STATE = { lmp_date: r.data.lmp_date || null, active: !!r.data.active };
        else { PREVIEW = true; document.getElementById('preg-preview')?.removeAttribute('hidden'); }
        root.setAttribute('aria-busy', 'false');
        render();
        bindStatic();
    }

    const $ = id => document.getElementById(id);
    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /* ---------- cálculo ---------- */
    function parseD(ymd) { const [y, m, d] = String(ymd).split('-').map(Number); return new Date(y, m - 1, d); }
    function daysSince(ymd) { return Math.floor((parseD(BOOT.today) - parseD(ymd)) / 86400000); }
    function currentWeek() { return Math.min(42, Math.max(1, Math.floor(daysSince(STATE.lmp_date) / 7) + 1)); }
    function dueDate() { const d = parseD(STATE.lmp_date); d.setDate(d.getDate() + 280); return d; }
    function longDate(dt) { return `${dt.getDate()} de ${MESES[dt.getMonth()]} de ${dt.getFullYear()}`; }

    /* ---------- render ---------- */
    function render() {
        if (!STATE.active || !STATE.lmp_date) { renderOnboard(); $('preg-settings-btn').hidden = true; return; }
        $('preg-settings-btn').hidden = false;
        if (viewWeek == null) viewWeek = currentWeek();
        renderWeek();
    }

    function renderOnboard() {
        $('preg-content').innerHTML = `
            <div class="preg-onboard">
                <div class="badge">🤰</div>
                <h2>Sigue tu embarazo semana a semana</h2>
                <p>Indica la fecha de tu última menstruación y descubre cómo crece tu bebé, su tamaño, y la fecha probable de parto.</p>
                <button type="button" class="btn" id="preg-start"><i data-lucide="sparkles"></i> Comenzar</button>
            </div>`;
        if (window.lucide) lucide.createIcons();
        $('preg-start').addEventListener('click', openSetup);
    }

    function renderWeek() {
        const cur = currentWeek();
        const w = Math.min(40, Math.max(4, viewWeek));
        const data = WEEKS[w] || WEEKS[40];
        const tri = trimester(w);
        const due = dueDate();
        const daysToDue = Math.round((due - parseD(BOOT.today)) / 86400000);
        const pct = Math.min(100, Math.round((cur / 40) * 100));
        const isCurrent = w === Math.min(40, cur);

        const countdown = daysToDue > 0
            ? `<div class="big">${daysToDue >= 14 ? Math.round(daysToDue / 7) + ' semanas' : daysToDue + ' días'}</div><div class="sm">para conocer a tu bebé</div>`
            : `<div class="big">¡Es la hora! 🎉</div><div class="sm">Llegaste a tu fecha probable de parto</div>`;

        $('preg-content').innerHTML = `
            <div class="preg-content-flex">
                <div class="preg-hero">
                    <div class="preg-hero-top">
                        <span class="preg-trimester">${TRIM_NAME[tri]}</span>
                        <span class="preg-hero-week">${isCurrent ? 'Tu semana actual' : 'Vista previa'}</span>
                    </div>
                    <div class="preg-hero-main">
                        <div class="preg-fruit">${data.e}</div>
                        <div class="preg-hero-info">
                            <div class="wk">Semana ${w}</div>
                            <div class="cmp">Tu bebé es del tamaño de ${data.c}</div>
                            <div class="sz"><span>📏 <b>${data.len}</b></span>${data.w ? `<span>⚖️ <b>${data.w}</b></span>` : ''}</div>
                        </div>
                    </div>
                    <div class="preg-progress">
                        <div class="preg-progressbar"><i style="width:${pct}%"></i></div>
                        <div class="preg-progress-labels"><span>Semana 1</span><span>${cur > 40 ? 'A término' : 'Semana ' + cur + ' de 40'}</span><span>Semana 40</span></div>
                    </div>
                </div>

                <div class="preg-nav">
                    <button type="button" class="preg-nav-btn" id="preg-prev" ${w <= 4 ? 'disabled' : ''}><i data-lucide="chevron-left"></i> Anterior</button>
                    <span class="preg-nav-current">${isCurrent ? `Semana ${w}` : `<button type="button" id="preg-back-cur">Volver a mi semana</button>`}</span>
                    <button type="button" class="preg-nav-btn" id="preg-next" ${w >= 40 ? 'disabled' : ''}>Siguiente <i data-lucide="chevron-right"></i></button>
                </div>

                <div class="preg-cards">
                    <div class="preg-card preg-countdown"><div class="preg-card-h"><i data-lucide="baby"></i> Cuenta regresiva</div>${countdown}</div>
                    <div class="preg-card preg-due"><div class="preg-card-h"><i data-lucide="calendar-heart"></i> Fecha probable de parto</div><div class="big">${longDate(due)}</div><div class="sm" style="font-size:.82rem;color:var(--portal-muted);margin-top:3px">Estimada según tu FUM</div></div>
                    <div class="preg-card full"><div class="preg-card-h"><i data-lucide="sparkles"></i> Esta semana</div><p>${data.dev}</p></div>
                    <div class="preg-card full"><div class="preg-card-h"><i data-lucide="list-checks"></i> Recomendaciones · ${TRIM_NAME[tri]}</div>
                        <ul class="preg-tips">${TIPS[tri].map(t => `<li><i data-lucide="check"></i> ${t}</li>`).join('')}</ul></div>
                </div>

                <p class="preg-disclaimer"><i data-lucide="heart"></i> Esta información es educativa y orientativa. Sigue siempre las indicaciones de tu obstetra y no faltes a tus controles.</p>
            </div>`;
        if (window.lucide) lucide.createIcons();
        const prev = $('preg-prev'), next = $('preg-next'), back = $('preg-back-cur');
        if (prev) prev.addEventListener('click', () => { viewWeek = Math.max(4, w - 1); renderWeek(); });
        if (next) next.addEventListener('click', () => { viewWeek = Math.min(40, w + 1); renderWeek(); });
        if (back) back.addEventListener('click', () => { viewWeek = currentWeek(); renderWeek(); });
    }

    /* ---------- setup ---------- */
    function openSetup() {
        const inp = $('preg-lmp');
        inp.max = BOOT.today;
        inp.value = STATE.lmp_date || '';
        $('preg-end-btn').hidden = !STATE.active;
        updateCalc();
        showDialog($('preg-setup'));
    }
    function updateCalc() {
        const v = $('preg-lmp').value;
        const hint = $('preg-setup-calc');
        if (!v) { hint.hidden = true; return; }
        const days = Math.floor((parseD(BOOT.today) - parseD(v)) / 86400000);
        if (days < 0) { hint.hidden = false; hint.textContent = 'La fecha no puede ser futura.'; return; }
        const wk = Math.floor(days / 7) + 1;
        if (wk > 45) { hint.hidden = false; hint.textContent = 'Esa fecha indica más de 40 semanas; revisa el dato.'; return; }
        const due = parseD(v); due.setDate(due.getDate() + 280);
        hint.hidden = false;
        hint.textContent = `Estarías en la semana ${Math.min(40, wk)} · Parto estimado: ${longDate(due)}`;
    }
    async function saveSetup(e) {
        e?.preventDefault();
        const v = $('preg-lmp').value;
        if (!v) { toast('Elige la fecha de tu última menstruación.', 'error'); return; }
        if (v > BOOT.today) { toast('La fecha no puede ser futura.', 'error'); return; }
        STATE = { lmp_date: v, active: true };
        viewWeek = null;
        closeDialog($('preg-setup'));
        render();
        await persist('PUT', '/portal/me/pregnancy', { lmp_date: v, active: true });
    }
    async function endTracking() {
        closeDialog($('preg-setup'));
        STATE = { lmp_date: null, active: false };
        viewWeek = null;
        render();
        await persist('PUT', '/portal/me/pregnancy', { lmp_date: '', active: false });
        toast('Seguimiento finalizado', 'info');
    }

    /* ---------- helpers ---------- */
    function showDialog(d) { if (!d.open) { try { d.showModal(); } catch { d.setAttribute('open', ''); } } }
    function closeDialog(d) { try { d.close(); } catch { d.removeAttribute('open'); } }
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
        setTimeout(() => { t.classList.add('is-leaving'); setTimeout(() => t.remove(), 350); }, 3000);
    }

    function bindStatic() {
        $('preg-settings-btn').addEventListener('click', openSetup);
        $('preg-setup-form').addEventListener('submit', saveSetup);
        $('preg-end-btn').addEventListener('click', endTracking);
        $('preg-lmp').addEventListener('input', updateCalc);
        document.querySelectorAll('[data-preg-close]').forEach(b => b.addEventListener('click', () => closeDialog(b.closest('dialog'))));
        $('preg-setup').addEventListener('click', e => { if (e.target === $('preg-setup')) closeDialog($('preg-setup')); });
    }

    load();
})();
