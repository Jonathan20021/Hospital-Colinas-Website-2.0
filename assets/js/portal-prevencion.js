/* Recordatorios de Prevención — Portal del Paciente · Hospital General Las Colinas
 * Catálogo + motor (qué tamizaje toca según edad/sexo) en el cliente; el backend
 * solo guarda cuándo se hizo cada uno. Sincroniza vía /portal/me/screenings.
 */
(function () {
    'use strict';
    const root = document.getElementById('prev-app');
    if (!root) return;

    const BOOT = window.PREV_BOOT || { age: null, sex: '', today: '' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    let PREVIEW = false;
    let RECORDS = {}; // { key: 'YYYY-MM-DD' }

    /* catálogo — sex: any|female|male; ageMax null = sin tope; freq en meses */
    const SCREENINGS = [
        { key: 'blood_pressure', name: 'Presión arterial', icon: 'heart-pulse', sex: 'any', ageMin: 18, ageMax: null, freq: 12, desc: 'Mídela para detectar hipertensión a tiempo.' },
        { key: 'glucose', name: 'Glucosa en sangre', icon: 'droplet', sex: 'any', ageMin: 35, ageMax: null, freq: 36, desc: 'Ayuda a detectar diabetes o prediabetes.' },
        { key: 'cholesterol', name: 'Perfil lipídico (colesterol)', icon: 'activity', sex: 'any', ageMin: 20, ageMax: null, freq: 48, desc: 'Controla tu colesterol y triglicéridos.' },
        { key: 'weight', name: 'Control de peso (IMC)', icon: 'scale', sex: 'any', ageMin: 18, ageMax: null, freq: 12, desc: 'Vigila tu peso y composición corporal.' },
        { key: 'dental', name: 'Limpieza dental', icon: 'smile', sex: 'any', ageMin: 3, ageMax: null, freq: 6, desc: 'Mantén tu salud bucal con revisiones periódicas.' },
        { key: 'vision', name: 'Examen de la vista', icon: 'eye', sex: 'any', ageMin: 18, ageMax: null, freq: 24, desc: 'Revisa tu visión y salud ocular.' },
        { key: 'flu_vaccine', name: 'Vacuna de influenza', icon: 'syringe', sex: 'any', ageMin: 0, ageMax: null, freq: 12, desc: 'Protégete cada temporada de gripe.' },
        { key: 'colon', name: 'Tamizaje de colon', icon: 'scan-line', sex: 'any', ageMin: 45, ageMax: null, freq: 12, desc: 'Detecta pólipos o cáncer de colon a tiempo.' },
        { key: 'pap', name: 'Papanicolaou', icon: 'flower-2', sex: 'female', ageMin: 21, ageMax: 65, freq: 36, desc: 'Detecta cáncer de cuello uterino de forma temprana.' },
        { key: 'mammography', name: 'Mamografía', icon: 'scan', sex: 'female', ageMin: 40, ageMax: null, freq: 24, desc: 'Detecta cáncer de mama de forma temprana.' },
        { key: 'bone_density', name: 'Densitometría ósea', icon: 'bone', sex: 'female', ageMin: 60, ageMax: null, freq: 24, desc: 'Evalúa la salud de tus huesos (osteoporosis).' },
        { key: 'psa', name: 'Antígeno prostático (PSA)', icon: 'stethoscope', sex: 'male', ageMin: 50, ageMax: null, freq: 12, desc: 'Evaluación de la salud de la próstata.' },
    ];
    const byKey = k => SCREENINGS.find(s => s.key === k);

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
        const r = await proxy('GET', '/portal/me/screenings');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) RECORDS = r.data.records || {};
        else { PREVIEW = true; document.getElementById('prev-preview')?.removeAttribute('hidden'); }
        root.setAttribute('aria-busy', 'false');
        renderIntro();
        renderList();
        bind();
    }

    const $ = id => document.getElementById(id);
    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /* ---------- motor ---------- */
    function applies(s) {
        if (s.sex !== 'any' && s.sex !== BOOT.sex) return false;
        if (BOOT.age == null) return true; // sin edad: mostramos lo que aplica por sexo
        if (BOOT.age < s.ageMin) return false;
        if (s.ageMax != null && BOOT.age > s.ageMax) return false;
        return true;
    }
    function status(s) {
        const done = RECORDS[s.key] || null;
        if (!done) return { state: 'due', done: null, due: null };
        const dueDate = addMonths(done, s.freq);
        const late = parseD(BOOT.today) >= parseD(dueDate);
        return { state: late ? 'late' : 'ok', done, due: dueDate };
    }
    function addMonths(ymd, months) {
        const d = parseD(ymd); d.setMonth(d.getMonth() + months);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    function parseD(ymd) { const [y, m, d] = String(ymd).split('-').map(Number); return new Date(y, m - 1, d); }
    function longDate(ymd) { const d = parseD(ymd); return `${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)} ${d.getFullYear()}`; }
    function freqLabel(m) { if (m < 12) return `cada ${m} meses`; if (m === 12) return 'cada año'; return `cada ${Math.round(m / 12)} años`; }

    /* ---------- intro ---------- */
    function renderIntro() {
        const intro = $('prev-intro');
        const sexTxt = BOOT.sex === 'female' ? 'femenino' : BOOT.sex === 'male' ? 'masculino' : '';
        if (BOOT.age == null) {
            intro.className = 'prev-intro warn';
            intro.innerHTML = `<span class="ic"><i data-lucide="user-round"></i></span><span class="tx">Para personalizar tus recomendaciones necesitamos tu fecha de nacimiento. Mientras tanto te mostramos las generales. <a href="perfil.php">Completar perfil</a></span>`;
        } else {
            intro.className = 'prev-intro';
            intro.innerHTML = `<span class="ic"><i data-lucide="shield-check"></i></span><span class="tx">Recomendaciones según tu perfil: <b>${BOOT.age} años</b>${sexTxt ? ` · <b>${sexTxt}</b>` : ''}. Marca cada chequeo cuando te lo hagas para llevar el control.</span>`;
        }
        if (window.lucide) lucide.createIcons();
    }

    /* ---------- lista ---------- */
    function renderList() {
        const wrap = $('prev-list');
        const items = SCREENINGS.filter(applies).map(s => ({ s, st: status(s) }));
        const todo = items.filter(i => i.st.state !== 'ok');
        const ok = items.filter(i => i.st.state === 'ok');

        let html = '';
        if (todo.length) {
            html += `<div class="prev-section">
                <div class="prev-section-title"><i data-lucide="bell-ring"></i> Te toca <span class="count">(${todo.length})</span></div>
                ${todo.map(cardHtml).join('')}</div>`;
        }
        if (ok.length) {
            html += `<div class="prev-section">
                <div class="prev-section-title"><i data-lucide="check-circle-2"></i> Al día <span class="count">(${ok.length})</span></div>
                ${ok.map(cardHtml).join('')}</div>`;
        }
        if (!items.length) html = `<div class="prev-section"><p style="color:var(--portal-muted)">No hay tamizajes para mostrar con tu perfil.</p></div>`;
        wrap.innerHTML = html;
        wrap.querySelectorAll('[data-mark]').forEach(b => b.addEventListener('click', () => openSheet(b.dataset.mark)));
        if (window.lucide) lucide.createIcons();
    }

    function cardHtml({ s, st }) {
        const badge = {
            due: '<span class="prev-badge due"><i data-lucide="circle-dashed"></i> Te toca</span>',
            late: '<span class="prev-badge late"><i data-lucide="alert-circle"></i> Toca renovar</span>',
            ok: '<span class="prev-badge ok"><i data-lucide="check"></i> Al día</span>',
        }[st.state];
        let meta = `<span class="it"><i data-lucide="repeat"></i> Recomendado ${freqLabel(s.freq)}</span>`;
        if (st.done) meta += `<span class="it"><i data-lucide="calendar-check"></i> Último: ${longDate(st.done)}</span>`;
        if (st.state === 'ok' && st.due) meta += `<span class="it"><i data-lucide="calendar-clock"></i> Próximo: ${longDate(st.due)}</span>`;
        const btn = st.done
            ? `<button type="button" class="prev-act-btn" data-mark="${s.key}"><i data-lucide="pencil"></i> Actualizar fecha</button>`
            : `<button type="button" class="prev-act-btn done" data-mark="${s.key}"><i data-lucide="check-circle-2"></i> Ya me lo hice</button>`;
        return `
            <div class="prev-card ${st.state}">
                <span class="prev-card-ic"><i data-lucide="${s.icon}"></i></span>
                <div class="prev-card-body">
                    <div class="prev-card-top"><span class="prev-card-name">${esc(s.name)}</span>${badge}</div>
                    <p class="prev-card-desc">${esc(s.desc)}</p>
                    <div class="prev-card-meta">${meta}</div>
                    <div class="prev-card-action">${btn}</div>
                </div>
            </div>`;
    }

    /* ---------- marcar hecho ---------- */
    let markKey = null;
    function openSheet(key) {
        const s = byKey(key); if (!s) return;
        markKey = key;
        $('prev-sheet-title').textContent = s.name;
        $('prev-sheet-sub').textContent = '¿Cuándo te lo hiciste?';
        const date = $('prev-date');
        date.max = BOOT.today;
        date.value = RECORDS[key] || BOOT.today;
        $('prev-clear-btn').hidden = !RECORDS[key];
        showDialog($('prev-sheet'));
    }

    async function save(e) {
        e?.preventDefault();
        const date = $('prev-date').value;
        if (!date) { toast('Elige una fecha.', 'error'); return; }
        if (date > BOOT.today) { toast('La fecha no puede ser futura.', 'error'); return; }
        RECORDS[markKey] = date;
        closeDialog($('prev-sheet'));
        renderList();
        await persist('POST', '/portal/me/screenings', { key: markKey, date });
        toast('Registrado', 'success');
    }

    async function clearRecord() {
        if (!markKey) return;
        delete RECORDS[markKey];
        closeDialog($('prev-sheet'));
        renderList();
        await persist('DELETE', '/portal/me/screenings/' + markKey);
        toast('Registro quitado', 'info');
    }

    /* ---------- helpers ---------- */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
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

    function bind() {
        $('prev-form').addEventListener('submit', save);
        $('prev-clear-btn').addEventListener('click', clearRecord);
        document.querySelectorAll('[data-prev-close]').forEach(b => b.addEventListener('click', () => closeDialog(b.closest('dialog'))));
        $('prev-sheet').addEventListener('click', e => { if (e.target === $('prev-sheet')) closeDialog($('prev-sheet')); });
    }

    load();
})();
