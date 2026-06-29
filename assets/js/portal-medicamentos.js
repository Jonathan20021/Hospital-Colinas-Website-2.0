/* Mis Medicamentos — Portal del Paciente · Hospital General Las Colinas
 * Sincroniza vía /api/portal-proxy.php → /portal/me/medications. Token en sesión.
 * Sin backend → modo vista previa (banner, en memoria, no guarda).
 */
(function () {
    'use strict';
    const root = document.getElementById('med-app');
    if (!root) return;

    const BOOT = window.MED_BOOT || { today: '' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    let PREVIEW = false;
    let MEDS = [];
    let TODAY = [];
    const DATE = () => BOOT.today;

    const PRESETS = [
        { label: '1 vez al día', times: ['08:00'] },
        { label: '2 veces al día', times: ['08:00', '20:00'] },
        { label: '3 veces al día', times: ['08:00', '14:00', '20:00'] },
        { label: 'Cada 8 horas', times: ['06:00', '14:00', '22:00'] },
        { label: 'Antes de dormir', times: ['22:00'] },
    ];

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
        const r = await proxy('GET', '/portal/me/medications');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) { MEDS = r.data.medications || []; TODAY = r.data.today || []; }
        else { PREVIEW = true; document.getElementById('med-preview')?.removeAttribute('hidden'); }
        root.setAttribute('aria-busy', 'false');
        renderAll();
        bind();
    }

    const $ = id => document.getElementById(id);
    function renderAll() { renderToday(); renderList(); if (window.lucide) lucide.createIcons(); }

    /* ---------- Hoy ---------- */
    function renderToday() {
        const card = $('med-today-card');
        const total = TODAY.length;
        const taken = TODAY.filter(d => d.taken).length;
        if (!total) {
            card.innerHTML = `<div class="med-today-head"><h2>Tomas de hoy</h2></div>
                <p class="med-today-empty">Agrega tus medicamentos con horarios y aquí verás tus tomas del día.</p>`;
            return;
        }
        const pct = Math.round((taken / total) * 100);
        const rows = TODAY.map((d, i) => `
            <button type="button" class="med-dose ${d.taken ? 'is-taken' : ''}" data-i="${i}">
                <span class="med-dose-check"><i data-lucide="check"></i></span>
                <span class="med-dose-info"><span class="nm ${d.taken ? 'done' : ''}">${esc(d.name)}</span><span class="ds">${esc(d.dose || 'Tu dosis')}</span></span>
                <span class="med-dose-time">${esc(d.time)}</span>
            </button>`).join('');
        card.innerHTML = `
            <div class="med-today-head"><h2>Tomas de hoy</h2><span class="med-today-progress">${taken} de ${total}</span></div>
            <div class="med-progressbar"><i style="width:${pct}%"></i></div>
            ${taken === total ? '<div class="med-today-alldone"><i data-lucide="party-popper"></i> ¡Completaste todas tus tomas de hoy!</div>' : ''}
            <div class="med-dose-list">${rows}</div>`;
        card.querySelectorAll('.med-dose').forEach(b => b.addEventListener('click', () => toggleIntake(+b.dataset.i)));
    }

    async function toggleIntake(i) {
        const d = TODAY[i];
        if (!d) return;
        d.taken = !d.taken;
        renderToday(); if (window.lucide) lucide.createIcons();
        await persist('POST', '/portal/me/medications/intake', { medication_id: d.medication_id, date: DATE(), time: d.time, taken: d.taken });
    }

    /* ---------- Lista ---------- */
    function renderList() {
        const wrap = $('med-list');
        if (!MEDS.length) {
            wrap.innerHTML = `<div class="med-empty"><div class="ic"><i data-lucide="pill"></i></div>
                <h3>Aún no tienes medicamentos</h3><p>Agrega tus medicinas para llevar el control de tus tomas y horarios.</p></div>`;
            return;
        }
        wrap.innerHTML = MEDS.map(m => `
            <div class="med-card ${m.active ? '' : 'is-inactive'}" data-id="${m.id}">
                <span class="med-card-ic"><i data-lucide="pill"></i></span>
                <div class="med-card-body">
                    <div class="med-card-name">${esc(m.name)}${m.dose ? ` <span class="dose">· ${esc(m.dose)}</span>` : ''}</div>
                    ${m.times && m.times.length ? `<div class="med-card-times">${m.times.map(t => `<span class="med-time-chip"><i data-lucide="clock"></i>${esc(t)}</span>`).join('')}</div>` : ''}
                    ${m.note ? `<div class="med-card-note">${esc(m.note)}</div>` : ''}
                    ${!m.active ? '<span class="med-card-paused">En pausa</span>' : ''}
                </div>
                <span class="med-card-chev"><i data-lucide="chevron-right"></i></span>
            </div>`).join('');
        wrap.querySelectorAll('.med-card').forEach(c => c.addEventListener('click', () => openEdit(+c.dataset.id)));
    }

    /* ---------- Agregar / editar ---------- */
    let draft = null;
    function openAdd() { draft = { id: 0, name: '', dose: '', times: [], note: '', active: true }; renderForm('Agregar medicamento', false); showDialog($('med-sheet')); }
    function openEdit(id) {
        const m = MEDS.find(x => x.id === id); if (!m) return;
        draft = { id: m.id, name: m.name, dose: m.dose, times: m.times.slice(), note: m.note, active: m.active };
        renderForm('Editar medicamento', true); showDialog($('med-sheet'));
    }

    function renderForm(title, isEdit) {
        $('med-sheet-title').textContent = title;
        $('med-del-btn').hidden = !isEdit;
        $('med-sheet-body').innerHTML = `
            <div class="med-field"><label for="m-name">Nombre del medicamento</label>
                <input class="med-input" id="m-name" type="text" maxlength="120" placeholder="Ej.: Losartán" value="${esc(draft.name)}"></div>
            <div class="med-field"><label for="m-dose">Dosis <span class="hint">(opcional)</span></label>
                <input class="med-input" id="m-dose" type="text" maxlength="60" placeholder="Ej.: 50 mg · 1 tableta" value="${esc(draft.dose)}"></div>
            <div class="med-field">
                <label>Horarios</label>
                <div class="med-presets" id="m-presets">${PRESETS.map((p, i) => `<button type="button" class="med-preset" data-p="${i}">${p.label}</button>`).join('')}</div>
                <div class="med-times-edit" id="m-times"></div>
            </div>
            ${isEdit ? `<div class="med-field"><div class="med-toggle-row"><span>Medicamento activo</span><button type="button" class="med-switch ${draft.active ? 'is-on' : ''}" id="m-active" role="switch" aria-checked="${draft.active}"></button></div></div>` : ''}
            <div class="med-field"><label for="m-note">Indicación <span class="hint">(opcional)</span></label>
                <input class="med-input" id="m-note" type="text" maxlength="255" placeholder="Ej.: con la comida, en ayunas…" value="${esc(draft.note)}"></div>`;
        if (window.lucide) lucide.createIcons();
        renderTimes();
        wireForm();
    }

    function renderTimes() {
        const wrap = $('m-times');
        wrap.innerHTML = draft.times.map(t => `<span class="med-time-pill">${esc(t)}<button type="button" data-rm="${esc(t)}" aria-label="Quitar"><i data-lucide="x"></i></button></span>`).join('')
            + `<span class="med-time-add"><input type="time" id="m-time-input"><button type="button" id="m-time-add" aria-label="Agregar hora"><i data-lucide="plus"></i></button></span>`;
        if (window.lucide) lucide.createIcons();
        wrap.querySelectorAll('[data-rm]').forEach(b => b.addEventListener('click', () => { draft.times = draft.times.filter(x => x !== b.dataset.rm); renderTimes(); }));
        $('m-time-add').addEventListener('click', () => {
            const v = $('m-time-input').value;
            if (v && !draft.times.includes(v)) { draft.times.push(v); draft.times.sort(); renderTimes(); }
        });
    }

    function wireForm() {
        $('m-presets').querySelectorAll('.med-preset').forEach(b => b.addEventListener('click', () => {
            draft.times = PRESETS[+b.dataset.p].times.slice(); renderTimes();
        }));
        const sw = $('m-active');
        if (sw) sw.addEventListener('click', () => { draft.active = !draft.active; sw.classList.toggle('is-on', draft.active); sw.setAttribute('aria-checked', draft.active); });
    }

    async function save(e) {
        e?.preventDefault();
        const name = $('m-name').value.trim();
        if (!name) { toast('Escribe el nombre del medicamento.', 'error'); return; }
        const payload = { name, dose: $('m-dose').value.trim() || null, times: draft.times, note: $('m-note').value.trim() || null, active: draft.active };
        closeDialog($('med-sheet'));
        if (PREVIEW) {
            if (draft.id) { const m = MEDS.find(x => x.id === draft.id); Object.assign(m, payload); }
            else MEDS.unshift({ id: -Date.now(), ...payload });
            rebuildToday(); renderAll(); toast('Guardado (vista previa)', 'info'); return;
        }
        const r = draft.id ? await persist('PUT', '/portal/me/medications/' + draft.id, payload)
            : await persist('POST', '/portal/me/medications', payload);
        if (r.ok) { await reload(); toast('Medicamento guardado', 'success'); }
    }

    async function delMed() {
        if (!draft.id) return;
        closeDialog($('med-sheet'));
        if (PREVIEW) { MEDS = MEDS.filter(m => m.id !== draft.id); rebuildToday(); renderAll(); return; }
        const r = await persist('DELETE', '/portal/me/medications/' + draft.id);
        if (r.ok) { await reload(); toast('Medicamento eliminado', 'info'); }
    }

    // recalcula el checklist de hoy en vista previa
    function rebuildToday() {
        TODAY = [];
        MEDS.filter(m => m.active).forEach(m => (m.times || []).forEach(t => TODAY.push({ medication_id: m.id, name: m.name, dose: m.dose, time: t, taken: false })));
        TODAY.sort((a, b) => a.time.localeCompare(b.time));
    }

    async function reload() {
        const r = await proxy('GET', '/portal/me/medications');
        if (r.ok && r.data) { MEDS = r.data.medications || []; TODAY = r.data.today || []; }
        renderAll();
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
        $('med-add-btn').addEventListener('click', openAdd);
        $('med-form').addEventListener('submit', save);
        $('med-del-btn').addEventListener('click', delMed);
        document.querySelectorAll('[data-med-close]').forEach(b => b.addEventListener('click', () => closeDialog(b.closest('dialog'))));
        $('med-sheet').addEventListener('click', e => { if (e.target === $('med-sheet')) closeDialog($('med-sheet')); });
    }

    load();
})();
