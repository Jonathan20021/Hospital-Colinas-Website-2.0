/**
 * Herramientas clínicas — calculadoras médicas (apoyo a la decisión).
 *
 * - Cada .tool-card[data-tool] tiene un calculador en CALC[...]. Los inputs llevan
 *   data-k (clave) y se recalcula en cada input/change.
 * - Pre-llenado: un buscador de pacientes (endpoints existentes vía doctorApi)
 *   rellena los campos marcados con data-fill (age, sex, weight, height) con la
 *   edad/sexo del paciente y su último registro de signos vitales.
 * - doctorApi se resuelve en el boot (este archivo carga antes que portal-medico.js).
 *
 * Todas las calculadoras son APOYO a la decisión; no sustituyen el criterio médico.
 */
(function () {
    'use strict';
    let api = window.doctorApi || null;
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // ── Salida ───────────────────────────────────────────────────────────────
    function setOut(card, val, unit, tag, level) {
        const out = card.querySelector('[data-out]');
        if (!out) return;
        const vEl = out.querySelector('.tool-out-val'); if (vEl) vEl.textContent = val;
        const uEl = out.querySelector('.tool-out-unit'); if (uEl && unit !== undefined) uEl.textContent = (unit || '');
        const tEl = out.querySelector('.tool-out-tag'); if (tEl) tEl.textContent = tag || '';
        out.classList.remove('tool-lvl-ok', 'tool-lvl-warn', 'tool-lvl-danger', 'tool-lvl-muted');
        out.classList.add('tool-lvl-' + (level || 'muted'));
    }
    const num = (card, k) => { const el = card.querySelector('[data-k="' + k + '"]'); if (!el) return NaN; const v = parseFloat(el.value); return isNaN(v) ? NaN : v; };
    const str = (card, k) => { const el = card.querySelector('[data-k="' + k + '"]'); return el ? el.value : ''; };
    const checkedPts = (card) => $$('.tool-check input:checked', card).reduce((s, c) => s + (parseInt(c.dataset.pts || '0', 10) || 0), 0);

    // ── Calculadoras ──────────────────────────────────────────────────────────
    const CALC = {
        imc(card) {
            const w = num(card, 'weight'), h = num(card, 'height');
            if (isNaN(w) || isNaN(h) || w <= 0 || h <= 0) { setOut(card, '—', 'kg/m²', 'Introduce peso y talla', 'muted'); return; }
            const m = h / 100, imc = w / (m * m);
            let tag, lvl;
            if (imc < 18.5) { tag = 'Bajo peso'; lvl = 'warn'; }
            else if (imc < 25) { tag = 'Peso normal'; lvl = 'ok'; }
            else if (imc < 30) { tag = 'Sobrepeso'; lvl = 'warn'; }
            else if (imc < 35) { tag = 'Obesidad grado I'; lvl = 'danger'; }
            else if (imc < 40) { tag = 'Obesidad grado II'; lvl = 'danger'; }
            else { tag = 'Obesidad grado III'; lvl = 'danger'; }
            setOut(card, imc.toFixed(1), 'kg/m²', tag, lvl);
        },
        bsa(card) {
            const w = num(card, 'weight'), h = num(card, 'height');
            if (isNaN(w) || isNaN(h) || w <= 0 || h <= 0) { setOut(card, '—', 'm²', 'Fórmula de Mosteller', 'muted'); return; }
            setOut(card, Math.sqrt((h * w) / 3600).toFixed(2), 'm²', 'Mosteller', 'ok');
        },
        cg(card) {
            const age = num(card, 'age'), w = num(card, 'weight'), cr = num(card, 'cr'), sex = str(card, 'sex');
            if (isNaN(age) || isNaN(w) || isNaN(cr) || !sex || cr <= 0 || age <= 0 || w <= 0) { setOut(card, '—', 'mL/min', 'Completa los 4 campos', 'muted'); return; }
            let clcr = ((140 - age) * w) / (72 * cr);
            if (sex === 'F') clcr *= 0.85;
            clcr = Math.max(0, clcr);
            let tag, lvl;
            if (clcr >= 90) { tag = 'Función renal normal (G1)'; lvl = 'ok'; }
            else if (clcr >= 60) { tag = 'Descenso leve (G2)'; lvl = 'ok'; }
            else if (clcr >= 45) { tag = 'Leve-moderado (G3a)'; lvl = 'warn'; }
            else if (clcr >= 30) { tag = 'Moderado-severo (G3b)'; lvl = 'warn'; }
            else if (clcr >= 15) { tag = 'Descenso severo (G4)'; lvl = 'danger'; }
            else { tag = 'Falla renal (G5)'; lvl = 'danger'; }
            setOut(card, Math.round(clcr), 'mL/min', tag, lvl);
        },
        ga(card) {
            const lmp = str(card, 'lmp');
            if (!lmp) { setOut(card, '—', '', 'Introduce la FUM', 'muted'); return; }
            const d = new Date(lmp + 'T00:00:00');
            if (isNaN(d.getTime())) { setOut(card, '—', '', 'Fecha inválida', 'muted'); return; }
            // Regla de Naegele: FUM + 7 días − 3 meses + 1 año
            const fpp = new Date(d);
            fpp.setDate(fpp.getDate() + 7);
            fpp.setMonth(fpp.getMonth() - 3);
            fpp.setFullYear(fpp.getFullYear() + 1);
            const MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            const fppStr = 'FPP: ' + fpp.getDate() + ' ' + MES[fpp.getMonth()] + ' ' + fpp.getFullYear();
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const days = Math.floor((today - d) / 86400000);
            if (days < 0) { setOut(card, '—', '', 'FUM en el futuro · ' + fppStr, 'muted'); return; }
            const wk = Math.floor(days / 7), dd = days % 7;
            let lvl = 'ok';
            if (wk > 42) lvl = 'danger';
            else if (wk >= 41) lvl = 'warn';
            setOut(card, wk + 's ' + dd + 'd', '', fppStr, lvl);
        },
        chads(card) {
            let score = 0;
            const age = num(card, 'age'), sex = str(card, 'sex');
            if (!isNaN(age)) { if (age >= 75) score += 2; else if (age >= 65) score += 1; }
            if (sex === 'F') score += 1;
            score += checkedPts(card);
            const effective = (sex === 'F') ? score - 1 : score; // riesgo sin el punto de sexo
            let tag, lvl;
            if (effective <= 0) { tag = 'Riesgo bajo — antiagregación/observación'; lvl = 'ok'; }
            else if (effective === 1) { tag = 'Riesgo intermedio — considerar anticoagular'; lvl = 'warn'; }
            else { tag = 'Riesgo alto — anticoagulación recomendada'; lvl = 'danger'; }
            setOut(card, score, '/ 9 puntos', tag, lvl);
        },
        wells(card) {
            const score = checkedPts(card);
            let tag, lvl;
            if (score >= 2) { tag = 'TVP probable — eco-Doppler'; lvl = 'danger'; }
            else { tag = 'TVP improbable — valorar dímero-D'; lvl = 'ok'; }
            setOut(card, score, 'puntos', tag, lvl);
        },
        curb(card) {
            let score = 0;
            const age = num(card, 'age');
            if (!isNaN(age) && age >= 65) score += 1;
            score += checkedPts(card);
            let tag, lvl;
            if (score <= 1) { tag = 'Bajo riesgo — manejo ambulatorio'; lvl = 'ok'; }
            else if (score === 2) { tag = 'Intermedio — observación/ingreso'; lvl = 'warn'; }
            else { tag = 'Alto riesgo — ingreso, valorar UCI'; lvl = 'danger'; }
            setOut(card, score, '/ 5 puntos', tag, lvl);
        }
    };

    function recalc(card) { const t = card.dataset.tool; if (CALC[t]) CALC[t](card); }
    function recalcAll() { $$('.tool-card').forEach(recalc); }

    // ── Pre-llenado de paciente ────────────────────────────────────────────────
    function setFill(key, value) {
        if (value == null || value === '') return;
        $$('[data-fill="' + key + '"]').forEach((el) => { el.value = value; });
    }
    function normSex(g) { if (!g) return ''; const s = String(g).trim().toLowerCase(); if (s[0] === 'm') return 'M'; if (s[0] === 'f') return 'F'; return ''; }
    function ageFromDob(dob) {
        if (!dob) return null; const d = new Date(dob); if (isNaN(d.getTime())) return null;
        const t = new Date(); let a = t.getFullYear() - d.getFullYear();
        const mm = t.getMonth() - d.getMonth(); if (mm < 0 || (mm === 0 && t.getDate() < d.getDate())) a--;
        return (a >= 0 && a < 130) ? a : null;
    }

    async function selectPatient(item) {
        api = window.doctorApi || api;
        $('#tool-patient-name').textContent = item.name || '—';
        const age = ageFromDob(item.dob), sx = normSex(item.gender);
        $('#tool-patient-meta').textContent = [item.cedula || '', age != null ? (age + ' años') : '', sx].filter(Boolean).join(' · ');
        $('#tool-patient-chip').hidden = false;
        $('#tool-patient-results').hidden = true;
        $('#tool-patient-q').value = '';
        if (age != null) setFill('age', age);
        if (sx) setFill('sex', sx);
        if (api) {
            try {
                const r = await api('GET', '/portal-doctor/patients/' + item.id);
                if (r && r.ok && r.data) {
                    const vit = (r.data.vitals && r.data.vitals[0]) || null;
                    if (vit) { if (vit.weight_kg) setFill('weight', vit.weight_kg); if (vit.height_cm) setFill('height', vit.height_cm); }
                }
            } catch (e) { /* sin vitales: se queda lo de edad/sexo */ }
        }
        recalcAll();
        if (window.lucide) lucide.createIcons();
    }

    let timer = null;
    async function doSearch(q) {
        api = window.doctorApi || api;
        const box = $('#tool-patient-results');
        if (!api || q.trim().length < 2) { box.hidden = true; return; }
        try {
            const r = await api('GET', '/portal-doctor/me/patients', { q: q.trim(), per_page: 8 });
            const items = (r && r.ok && r.data && r.data.items) || [];
            if (!items.length) { box.innerHTML = '<div class="tool-pb-empty">Sin coincidencias</div>'; box.hidden = false; return; }
            box.innerHTML = items.map((it, i) =>
                '<button type="button" class="tool-pb-item" data-i="' + i + '"><strong>' + esc(it.name) + '</strong><span>' + esc(it.cedula) + '</span></button>'
            ).join('');
            box._items = items;
            box.hidden = false;
        } catch (e) { box.hidden = true; }
    }

    // ── TABS (Calculadoras | Certificados) ─────────────────────────────────────
    let certLoaded = false;
    function initTabs() {
        const tabs = $$('.tool-tab');
        if (!tabs.length) return;
        tabs.forEach((t) => t.addEventListener('click', () => {
            tabs.forEach((x) => x.classList.remove('on'));
            t.classList.add('on');
            const which = t.dataset.tab;
            const calc = $('#tab-calc'), cert = $('#tab-cert');
            if (calc) calc.hidden = which !== 'calc';
            if (cert) cert.hidden = which !== 'cert';
            if (which === 'cert' && !certLoaded) { certLoaded = true; loadCerts(); }
            if (window.lucide) lucide.createIcons();
        }));
    }

    // ── CERTIFICADOS ────────────────────────────────────────────────────────────
    const CERT_FIELDS = {
        reposo: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Días de reposo<input type="number" class="doctor-input" data-ck="dias" min="1" max="120" inputmode="numeric"></label>'
            + '<label class="tool-f">Diagnóstico (opcional)<input type="text" class="doctor-input" data-ck="diagnostico"></label>'
            + '<label class="tool-f">Desde<input type="date" class="doctor-input" data-ck="desde"></label>'
            + '<label class="tool-f">Hasta<input type="date" class="doctor-input" data-ck="hasta"></label></div>',
        asistencia: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Fecha<input type="date" class="doctor-input" data-ck="fecha"></label>'
            + '<label class="tool-f">Motivo (opcional)<input type="text" class="doctor-input" data-ck="motivo"></label>'
            + '<label class="tool-f">Hora desde<input type="time" class="doctor-input" data-ck="hora_desde"></label>'
            + '<label class="tool-f">Hora hasta<input type="time" class="doctor-input" data-ck="hora_hasta"></label></div>',
        aptitud: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Propósito<input type="text" class="doctor-input" data-ck="proposito" placeholder="laboral, escolar, deportivo…"></label>'
            + '<label class="tool-f">Resultado<select class="doctor-input" data-ck="apto"><option value="1">Apto</option><option value="0">No apto</option></select></label></div>',
    };
    let certType = 'reposo', certPatient = null, certTimer = null;

    function renderCertFields() {
        const box = $('#cert-fields');
        if (box) box.innerHTML = CERT_FIELDS[certType] || '';
        if (window.lucide) lucide.createIcons();
    }
    function collectCertData() {
        const data = {};
        $$('#cert-fields [data-ck]').forEach((el) => { if (el.value !== '') data[el.dataset.ck] = el.value; });
        return data;
    }
    function certStatus(msg, type) {
        const el = $('#cert-status'); if (!el) return;
        el.textContent = msg || '';
        el.className = 'doctor-save-status' + (type === 'saved' ? ' doctor-save-saved' : type === 'error' ? ' doctor-save-error' : '');
    }
    async function certSearch(q) {
        api = window.doctorApi || api;
        const box = $('#cert-patient-results');
        if (!api || q.trim().length < 2) { box.hidden = true; return; }
        try {
            const r = await api('GET', '/portal-doctor/me/patients', { q: q.trim(), per_page: 8 });
            const items = (r && r.ok && r.data && r.data.items) || [];
            if (!items.length) { box.innerHTML = '<div class="tool-pb-empty">Sin coincidencias</div>'; box.hidden = false; return; }
            box.innerHTML = items.map((it, i) => '<button type="button" class="tool-pb-item" data-i="' + i + '"><strong>' + esc(it.name) + '</strong><span>' + esc(it.cedula) + '</span></button>').join('');
            box._items = items; box.hidden = false;
        } catch (e) { box.hidden = true; }
    }
    function certSelect(item) {
        certPatient = item;
        $('#cert-patient-name').textContent = item.name || '—';
        $('#cert-patient-meta').textContent = [item.cedula || '', normSex(item.gender)].filter(Boolean).join(' · ');
        $('#cert-patient-chip').hidden = false;
        $('#cert-patient-results').hidden = true;
        $('#cert-patient-q').value = '';
        if (window.lucide) lucide.createIcons();
    }
    function resetCertForm() {
        certPatient = null;
        $('#cert-patient-chip').hidden = true;
        const ext = $('#cert-ext'); if (ext) ext.checked = false;
        $('#cert-ext-fields').hidden = true;
        $('#cert-ext-name').value = ''; $('#cert-ext-ced').value = '';
        $('#cert-obs').value = '';
        renderCertFields();
    }
    async function emitCert() {
        api = window.doctorApi || api;
        const btn = $('#cert-emit');
        const ext = $('#cert-ext') && $('#cert-ext').checked;
        const body = { type: certType, data: collectCertData(), body_text: $('#cert-obs').value || '' };
        if (ext) {
            const nm = $('#cert-ext-name').value.trim(), cd = $('#cert-ext-ced').value.trim();
            if (!nm) { certStatus('Escribe el nombre del paciente externo.', 'error'); return; }
            body.patient_name = nm; body.patient_cedula = cd;
        } else {
            if (!certPatient) { certStatus('Selecciona un paciente o marca "paciente externo".', 'error'); return; }
            body.patient_id = certPatient.id;
        }
        btn.disabled = true; certStatus('Emitiendo…', '');
        try {
            const r = await api('POST', '/portal-doctor/me/certificates', body);
            if (r && r.ok && r.data) {
                certStatus('✓ Certificado ' + (r.data.folio || '') + ' emitido.', 'saved');
                window.open('certificado-pdf.php?id=' + r.data.id, '_blank');
                resetCertForm(); loadCerts();
            } else { certStatus((r && r.message) || 'No se pudo emitir el certificado.', 'error'); }
        } catch (e) { certStatus('Error de conexión.', 'error'); }
        btn.disabled = false;
    }
    async function loadCerts() {
        api = window.doctorApi || api;
        const box = $('#cert-list'); if (!box) return;
        try {
            const r = await api('GET', '/portal-doctor/me/certificates');
            renderCertList((r && r.ok && Array.isArray(r.data)) ? r.data : []);
        } catch (e) { box.innerHTML = '<div class="doctor-empty" style="padding:30px 16px"><p>No se pudo cargar.</p></div>'; }
    }
    function renderCertList(items) {
        const box = $('#cert-list'); if (!box) return;
        if (!items.length) { box.innerHTML = '<div class="doctor-empty" style="padding:30px 16px"><p class="doctor-empty-title">Sin certificados aún</p><p>Los que emitas aparecerán aquí.</p></div>'; return; }
        const TL = { reposo: 'Reposo', asistencia: 'Asistencia', aptitud: 'Aptitud' };
        box.innerHTML = items.map((c) => {
            const d = c.issued_at ? new Date(String(c.issued_at).replace(' ', 'T')) : null;
            const fecha = (d && !isNaN(d)) ? (d.getDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear()) : '';
            return '<div class="cert-item"><div class="cert-item-info"><strong>' + esc(c.patient_name) + '</strong>'
                + '<span>' + (TL[c.type] || 'Certificado') + ' · ' + esc(c.folio || '') + ' · ' + fecha + (c.revoked_at ? ' · <em>anulado</em>' : '') + '</span></div>'
                + '<a class="cert-item-pdf" href="certificado-pdf.php?id=' + c.id + '" target="_blank" rel="noopener" title="Abrir PDF"><i data-lucide="file-text"></i></a></div>';
        }).join('');
        if (window.lucide) lucide.createIcons();
    }
    function initCerts() {
        if (!$('#tab-cert')) return;
        renderCertFields();
        $$('.cert-type').forEach((b) => b.addEventListener('click', () => {
            $$('.cert-type').forEach((x) => x.classList.remove('on'));
            b.classList.add('on'); certType = b.dataset.type; renderCertFields();
        }));
        const ext = $('#cert-ext');
        if (ext) ext.addEventListener('change', () => {
            $('#cert-ext-fields').hidden = !ext.checked;
            if (ext.checked) { certPatient = null; $('#cert-patient-chip').hidden = true; }
        });
        const q = $('#cert-patient-q');
        if (q) {
            q.addEventListener('input', () => { clearTimeout(certTimer); certTimer = setTimeout(() => certSearch(q.value), 280); });
            q.addEventListener('focus', () => { if (q.value.trim().length >= 2) certSearch(q.value); });
        }
        const box = $('#cert-patient-results');
        if (box) box.addEventListener('click', (e) => {
            const btn = e.target.closest('.tool-pb-item'); if (!btn) return;
            const it = (box._items || [])[parseInt(btn.dataset.i, 10)]; if (it) certSelect(it);
        });
        const clr = $('#cert-patient-clear'); if (clr) clr.addEventListener('click', () => { certPatient = null; $('#cert-patient-chip').hidden = true; });
        const emit = $('#cert-emit'); if (emit) emit.addEventListener('click', emitCert);
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#cert-patient-q') && !e.target.closest('#cert-patient-results')) {
                const b = $('#cert-patient-results'); if (b) b.hidden = true;
            }
        });
    }

    function init() {
        api = window.doctorApi || api;
        initTabs();
        initCerts();
        const grid = $('#tool-grid');
        if (grid) {
            grid.addEventListener('input', (e) => { const c = e.target.closest('.tool-card'); if (c) recalc(c); });
            grid.addEventListener('change', (e) => { const c = e.target.closest('.tool-card'); if (c) recalc(c); });
        }
        recalcAll();

        // filtros por categoría
        $$('.tool-chip').forEach((chip) => chip.addEventListener('click', () => {
            $$('.tool-chip').forEach((c) => c.classList.remove('on'));
            chip.classList.add('on');
            const cat = chip.dataset.cat;
            $$('.tool-card').forEach((card) => { card.style.display = (cat === 'all' || card.dataset.cat === cat) ? '' : 'none'; });
        }));

        // buscador de paciente
        const q = $('#tool-patient-q');
        if (q) {
            q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => doSearch(q.value), 280); });
            q.addEventListener('focus', () => { if (q.value.trim().length >= 2) doSearch(q.value); });
        }
        const box = $('#tool-patient-results');
        if (box) box.addEventListener('click', (e) => {
            const btn = e.target.closest('.tool-pb-item'); if (!btn) return;
            const it = (box._items || [])[parseInt(btn.dataset.i, 10)];
            if (it) selectPatient(it);
        });
        const clr = $('#tool-patient-clear');
        if (clr) clr.addEventListener('click', () => { $('#tool-patient-chip').hidden = true; });
        document.addEventListener('click', (e) => { if (!e.target.closest('.tool-pb-search')) { const b = $('#tool-patient-results'); if (b) b.hidden = true; } });

        if (window.lucide) lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
