/* Diario de Síntomas — Portal del Paciente · Hospital General Las Colinas
 * Sincroniza vía /api/portal-proxy.php → /portal/me/symptoms. El token vive en sesión.
 * Sin backend → modo vista previa (banner, en memoria, no guarda).
 */
(function () {
    'use strict';
    const root = document.getElementById('sym-app');
    if (!root) return;

    const BOOT = window.SYM_BOOT || { now: '' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    let PREVIEW = false;
    let ENTRIES = [];

    /* catálogo (ids = SymptomsPdf::LABELS) */
    const SYMPTOMS = [
        { id: 'headache', emo: '🤕', label: 'Dolor de cabeza' },
        { id: 'fever', emo: '🌡️', label: 'Fiebre' },
        { id: 'cough', emo: '😷', label: 'Tos' },
        { id: 'sore_throat', emo: '😖', label: 'Dolor de garganta' },
        { id: 'congestion', emo: '👃', label: 'Congestión nasal' },
        { id: 'fatigue', emo: '🥱', label: 'Fatiga' },
        { id: 'nausea', emo: '🤢', label: 'Náuseas' },
        { id: 'vomiting', emo: '🤮', label: 'Vómito' },
        { id: 'diarrhea', emo: '🚽', label: 'Diarrea' },
        { id: 'abdominal_pain', emo: '🌀', label: 'Dolor abdominal' },
        { id: 'muscle_pain', emo: '💪', label: 'Dolor muscular' },
        { id: 'joint_pain', emo: '🦴', label: 'Dolor articular' },
        { id: 'back_pain', emo: '🩹', label: 'Dolor de espalda' },
        { id: 'chest_pain', emo: '💢', label: 'Dolor de pecho' },
        { id: 'shortness_breath', emo: '😮‍💨', label: 'Falta de aire' },
        { id: 'dizziness', emo: '💫', label: 'Mareo' },
        { id: 'rash', emo: '🔴', label: 'Erupción en la piel' },
        { id: 'itching', emo: '🖐️', label: 'Picazón' },
        { id: 'insomnia', emo: '🌙', label: 'Insomnio' },
        { id: 'anxiety', emo: '😰', label: 'Ansiedad' },
        { id: 'palpitations', emo: '💓', label: 'Palpitaciones' },
        { id: 'loss_appetite', emo: '🍽️', label: 'Falta de apetito' },
    ];
    const symById = id => SYMPTOMS.find(s => s.id === id);
    const SEV = { 1: 'Leve', 2: 'Moderado', 3: 'Fuerte' };
    const FEEL = [{ id: 'good', emo: '🙂', label: 'Bien' }, { id: 'regular', emo: '😐', label: 'Regular' }, { id: 'bad', emo: '😣', label: 'Mal' }];
    const feelEmo = id => (FEEL.find(f => f.id === id) || {}).emo || '';

    /* ---------- proxy ---------- */
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

    /* ---------- carga ---------- */
    async function load() {
        const r = await proxy('GET', '/portal/me/symptoms');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) ENTRIES = Array.isArray(r.data.entries) ? r.data.entries : [];
        else { PREVIEW = true; document.getElementById('sym-preview')?.removeAttribute('hidden'); }
        root.setAttribute('aria-busy', 'false');
        renderList();
        bind();
    }

    /* ---------- timeline ---------- */
    const $ = id => document.getElementById(id);

    function renderList() {
        const wrap = $('sym-list');
        if (!ENTRIES.length) {
            wrap.innerHTML = `
                <div class="sym-empty">
                    <div class="ic"><i data-lucide="notebook-pen"></i></div>
                    <h2>Tu diario está vacío</h2>
                    <p>Registra cómo te sientes. Con el tiempo verás patrones que puedes comentar con tu médico.</p>
                </div>`;
            if (window.lucide) lucide.createIcons();
            return;
        }
        // agrupar por fecha
        const groups = {};
        ENTRIES.forEach(e => { const d = (e.recorded_at || '').slice(0, 10); (groups[d] = groups[d] || []).push(e); });
        const days = Object.keys(groups).sort((a, b) => (a < b ? 1 : -1));
        wrap.innerHTML = days.map(d => `
            <div>
                <div class="sym-day-label">${dayLabel(d)}</div>
                <div class="sym-day">${groups[d].map(entryHtml).join('')}</div>
            </div>`).join('');
        wrap.querySelectorAll('.sym-entry-del').forEach(b => b.addEventListener('click', () => delEntry(+b.dataset.id)));
        if (window.lucide) lucide.createIcons();
    }

    function entryHtml(e) {
        const sev = Math.min(3, Math.max(1, e.severity || 1));
        const time = (e.recorded_at || '').slice(11, 16);
        const chips = (e.symptoms || []).map(id => { const s = symById(id); return `<span class="sym-tag">${s ? `<span class="emo">${s.emo}</span> ${s.label}` : id}</span>`; }).join('');
        const note = e.note ? `<div class="sym-entry-note">“${esc(e.note)}”</div>` : '';
        const feel = e.feeling ? `<span class="sym-entry-feel">${feelEmo(e.feeling)}</span>` : '';
        return `
            <div class="sym-entry sev${sev}">
                ${e.id > 0 ? `<button class="sym-entry-del" data-id="${e.id}" aria-label="Borrar"><i data-lucide="trash-2"></i></button>` : ''}
                <div class="sym-entry-top">
                    <span class="sym-entry-time">${time}</span>${feel}
                    <span class="sym-sev-badge sev${sev}">${SEV[sev]}</span>
                </div>
                ${chips ? `<div class="sym-chips">${chips}</div>` : ''}
                ${note}
            </div>`;
    }

    /* ---------- registro ---------- */
    let draft = { symptoms: [], severity: 1, feeling: '' };
    function openAdd() {
        draft = { symptoms: [], severity: 1, feeling: '' };
        const nowLocal = (BOOT.now || '').replace(' ', 'T');
        $('sym-sheet-date').textContent = 'Ahora · ' + fmtNice(BOOT.now);
        $('sym-sheet-body').innerHTML = `
            <div>
                <span class="sym-group-label">¿Qué síntomas tienes?</span>
                <div class="sym-chip-grid" id="sym-chips">
                    ${SYMPTOMS.map(s => `<button type="button" class="sym-chip" data-sym="${s.id}"><span class="emo">${s.emo}</span> ${s.label}</button>`).join('')}
                </div>
            </div>
            <div>
                <span class="sym-group-label">Intensidad</span>
                <div class="sym-seg" id="sym-seg">
                    <button type="button" data-sev="1" class="is-on">Leve</button>
                    <button type="button" data-sev="2">Moderado</button>
                    <button type="button" data-sev="3">Fuerte</button>
                </div>
            </div>
            <div>
                <span class="sym-group-label">¿Cómo te sientes en general?</span>
                <div class="sym-feel" id="sym-feel">
                    ${FEEL.map(f => `<button type="button" data-feel="${f.id}"><span class="emo">${f.emo}</span><span class="lb">${f.label}</span></button>`).join('')}
                </div>
            </div>
            <div>
                <span class="sym-group-label">Fecha y hora</span>
                <input class="sym-input" id="sym-when" type="datetime-local" value="${nowLocal}" max="${nowLocal}">
            </div>
            <div>
                <span class="sym-group-label">Nota para tu médico</span>
                <textarea class="sym-note" id="sym-note" maxlength="500" placeholder="Describe cómo te sientes, desde cuándo, qué lo mejora o empeora…"></textarea>
            </div>`;
        if (window.lucide) lucide.createIcons();
        wireForm();
        showDialog($('sym-sheet'));
    }

    function wireForm() {
        $('sym-chips').querySelectorAll('.sym-chip').forEach(ch => ch.addEventListener('click', () => {
            ch.classList.toggle('is-on');
            const id = ch.dataset.sym;
            if (ch.classList.contains('is-on')) draft.symptoms.push(id);
            else draft.symptoms = draft.symptoms.filter(x => x !== id);
        }));
        $('sym-seg').querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
            $('sym-seg').querySelectorAll('button').forEach(x => x.classList.remove('is-on'));
            b.classList.add('is-on'); draft.severity = +b.dataset.sev;
        }));
        $('sym-feel').querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
            const on = b.classList.contains('is-on');
            $('sym-feel').querySelectorAll('button').forEach(x => x.classList.remove('is-on'));
            if (!on) { b.classList.add('is-on'); draft.feeling = b.dataset.feel; } else draft.feeling = '';
        }));
    }

    async function save(e) {
        e?.preventDefault();
        const note = $('sym-note').value.trim();
        if (!draft.symptoms.length && !note && !draft.feeling) { toast('Registra al menos un síntoma o una nota.', 'error'); return; }
        const when = $('sym-when').value;
        const payload = {
            symptoms: draft.symptoms, severity: draft.severity, feeling: draft.feeling || null,
            note: note || null, recorded_at: when ? when.replace('T', ' ').slice(0, 16) : undefined,
        };
        closeDialog($('sym-sheet'));
        if (PREVIEW) {
            ENTRIES.unshift({ id: -Date.now(), recorded_at: payload.recorded_at || BOOT.now, symptoms: payload.symptoms, severity: payload.severity, feeling: payload.feeling || '', note: payload.note || '' });
            renderList(); toast('Registrado (vista previa)', 'info'); return;
        }
        const r = await persist('POST', '/portal/me/symptoms', payload);
        if (r.ok) { await reload(); toast('Registro guardado', 'success'); }
    }

    async function reload() {
        const r = await proxy('GET', '/portal/me/symptoms');
        if (r.ok && r.data) ENTRIES = Array.isArray(r.data.entries) ? r.data.entries : [];
        renderList();
    }

    async function delEntry(id) {
        if (PREVIEW) { ENTRIES = ENTRIES.filter(e => e.id !== id); renderList(); return; }
        const r = await persist('DELETE', '/portal/me/symptoms/' + id);
        if (r.ok) { ENTRIES = ENTRIES.filter(e => e.id !== id); renderList(); toast('Entrada eliminada', 'info'); }
    }

    /* ---------- helpers ---------- */
    const MESL = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    function parseD(d) { const [y, m, dd] = String(d).split('-').map(Number); return new Date(y, m - 1, dd, 12); }
    function todayStr() { const n = (BOOT.now || '').slice(0, 10); return n; }
    function dayLabel(d) {
        const t = todayStr();
        if (d === t) return 'Hoy';
        const dt = parseD(d), td = parseD(t);
        if (Math.round((td - dt) / 86400000) === 1) return 'Ayer';
        return `${DIAS[dt.getDay()]} ${dt.getDate()} de ${MESL[dt.getMonth()]}`;
    }
    function fmtNice(dtStr) {
        const [d, h] = String(dtStr).split(' '); const dt = parseD(d);
        return `${dt.getDate()} de ${MESL[dt.getMonth()]}, ${h || ''}`;
    }
    function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

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
        $('sym-add-btn').addEventListener('click', openAdd);
        $('sym-form').addEventListener('submit', save);
        document.querySelectorAll('[data-sym-close]').forEach(b => b.addEventListener('click', () => closeDialog(b.closest('dialog'))));
        $('sym-sheet').addEventListener('click', e => { if (e.target === $('sym-sheet')) closeDialog($('sym-sheet')); });
        // sin backend, el PDF no está disponible: aviso suave
        if (PREVIEW) $('sym-share').addEventListener('click', ev => { ev.preventDefault(); toast('El PDF estará disponible al conectar con el servidor.', 'info'); });
    }

    load();
})();
