/* Mis Signos Vitales — Portal del Paciente · Hospital General Las Colinas
 * Combina vitales del hospital (vital_signs) con mediciones caseras del paciente.
 * Sincroniza vía /api/portal-proxy.php → /portal/me/vitals. El token vive en sesión.
 * Sin backend → modo vista previa (banner, en memoria, no guarda).
 */
(function () {
    'use strict';
    const root = document.getElementById('vit-app');
    if (!root) return;

    const BOOT = window.VIT_BOOT || { today: '', now: '' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';

    let PREVIEW = false;
    let DATA = { series: {}, latest: {}, height_cm: null };

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

    /* ---------- catálogo de signos + interpretación clínica ---------- */
    const round1 = v => Math.round(v * 10) / 10;
    const I = (label, tone) => ({ label, tone });

    const METRICS = [
        {
            key: 'bp', name: 'Presión arterial', unit: 'mmHg', icon: 'heart-pulse', duo: true,
            value: p => `${p.sys}/${p.dia}`,
            interp: p => {
                const s = p.sys, d = p.dia;
                if (s < 90 || d < 60) return I('Baja', 'low');
                if (s >= 140 || d >= 90) return I('Alta (etapa 2)', 'high');
                if (s >= 130 || d >= 80) return I('Alta (etapa 1)', 'high');
                if (s >= 120) return I('Elevada', 'warn');
                return I('Normal', 'ok');
            },
        },
        {
            key: 'weight', name: 'Peso', unit: 'kg', icon: 'scale',
            value: p => round1(p.v),
            sub: () => DATA.height_cm ? `IMC ${bmi()}` : '',
            interp: () => {
                const b = bmi();
                if (!b) return I('Sin IMC', 'none');
                if (b < 18.5) return I('Bajo peso', 'low');
                if (b < 25) return I('Peso saludable', 'ok');
                if (b < 30) return I('Sobrepeso', 'warn');
                return I('Obesidad', 'high');
            },
        },
        {
            key: 'glucose', name: 'Glucosa', unit: 'mg/dL', icon: 'droplet',
            value: p => p.v,
            interp: p => {
                const g = p.v;
                if (g < 70) return I('Baja', 'low');
                if (g < 100) return I('Normal', 'ok');
                if (g < 126) return I('Prediabetes', 'warn');
                return I('Alta', 'high');
            },
        },
        {
            key: 'heart_rate', name: 'Pulso', unit: 'lpm', icon: 'activity',
            value: p => p.v,
            interp: p => { const h = p.v; if (h < 60) return I('Bajo', 'low'); if (h > 100) return I('Elevado', 'warn'); return I('Normal', 'ok'); },
        },
        {
            key: 'temperature', name: 'Temperatura', unit: '°C', icon: 'thermometer',
            value: p => round1(p.v).toFixed(1),
            interp: p => { const t = p.v; if (t < 35) return I('Baja', 'low'); if (t >= 38) return I('Fiebre', 'high'); if (t >= 37.3) return I('Febrícula', 'warn'); return I('Normal', 'ok'); },
        },
        {
            key: 'spo2', name: 'Saturación O₂', unit: '%', icon: 'wind',
            value: p => p.v,
            interp: p => { const s = p.v; if (s < 90) return I('Muy baja', 'high'); if (s < 95) return I('Baja', 'warn'); return I('Normal', 'ok'); },
        },
    ];
    const metricByKey = k => METRICS.find(m => m.key === k);

    function bmi() {
        const h = DATA.height_cm;
        const w = DATA.latest.weight?.v;
        if (!h || !w) return null;
        return round1(w / ((h / 100) * (h / 100)));
    }

    /* ---------- carga ---------- */
    async function load() {
        const r = await proxy('GET', '/portal/me/vitals');
        if (r.status === 401) { window.location.href = 'login.php'; return; }
        if (r.ok && r.data) {
            DATA = { series: r.data.series || {}, latest: r.data.latest || {}, height_cm: r.data.height_cm || null };
        } else {
            PREVIEW = true;
            document.getElementById('vit-preview')?.removeAttribute('hidden');
        }
        root.setAttribute('aria-busy', 'false');
        renderCards();
        bind();
    }

    /* ---------- tarjetas ---------- */
    const $ = id => document.getElementById(id);

    function renderCards() {
        const grid = $('vit-grid');
        grid.innerHTML = METRICS.map(m => {
            const latest = DATA.latest[m.key];
            const series = DATA.series[m.key] || [];
            let valueHtml, badgeHtml, metaHtml;
            if (latest) {
                const it = m.interp(latest);
                const sub = m.sub ? m.sub() : '';
                valueHtml = `<span class="num">${m.value(latest)}</span><span class="sub">${m.unit}${sub ? ' · ' + sub : ''}</span>`;
                badgeHtml = `<span class="vit-badge ${it.tone}">${toneIcon(it.tone)}${it.label}</span>`;
                const src = latest.src === 'self' ? '<span class="vit-src"><i data-lucide="house"></i>En casa</span>' : '<span class="vit-src"><i data-lucide="hospital"></i>Hospital</span>';
                metaHtml = `${src}<span>${fmtDate(latest.t)}</span>`;
            } else {
                valueHtml = `<span class="num empty">Sin datos</span>`;
                badgeHtml = `<span class="vit-badge none">Aún no registrado</span>`;
                metaHtml = `<span>Toca + Registrar para empezar</span>`;
            }
            return `
                <div class="vit-card" data-key="${m.key}" role="button" tabindex="0">
                    <div class="vit-card-top">
                        <span class="vit-card-ic"><i data-lucide="${m.icon}"></i></span>
                        <div><div class="vit-card-name">${m.name}</div><div class="vit-card-unit">${m.unit}</div></div>
                    </div>
                    <div class="vit-card-value">${valueHtml}</div>
                    ${badgeHtml}
                    <div class="vit-spark">${sparkline(m, series)}</div>
                    <div class="vit-card-meta">${metaHtml}</div>
                </div>`;
        }).join('');
        grid.querySelectorAll('.vit-card').forEach(c => {
            const open = () => openDetail(c.dataset.key);
            c.addEventListener('click', open);
            c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        });
        if (window.lucide) lucide.createIcons();
    }

    function toneIcon(tone) {
        const ic = { ok: 'check-circle-2', warn: 'alert-triangle', high: 'alert-circle', low: 'arrow-down-circle', none: 'minus-circle' }[tone] || 'minus-circle';
        return `<i data-lucide="${ic}"></i>`;
    }

    /* ---------- sparkline ---------- */
    function sparkline(m, series) {
        if (!series.length) return `<div class="vit-spark-empty">—</div>`;
        const W = 240, H = 46, pad = 4;
        if (m.duo) {
            const sys = series.map(p => p.sys), dia = series.map(p => p.dia);
            const all = sys.concat(dia);
            const path1 = linePath(sys, W, H, pad, Math.min(...all), Math.max(...all));
            const path2 = linePath(dia, W, H, pad, Math.min(...all), Math.max(...all));
            return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none"><path class="ln" d="${path1}"/><path class="ln ln2" d="${path2}"/></svg>`;
        }
        const vals = series.map(p => p.v);
        const mn = Math.min(...vals), mx = Math.max(...vals);
        const path = linePath(vals, W, H, pad, mn, mx);
        const area = path + ` L ${W - pad} ${H - pad} L ${pad} ${H - pad} Z`;
        const lastX = W - pad, lastY = yMap(vals[vals.length - 1], H, pad, mn, mx);
        return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none"><path class="area" d="${area}"/><path class="ln" d="${path}"/><circle class="dot" cx="${lastX}" cy="${lastY}" r="2.6"/></svg>`;
    }
    function yMap(v, H, pad, mn, mx) { const r = mx - mn || 1; return H - pad - ((v - mn) / r) * (H - 2 * pad); }
    function linePath(vals, W, H, pad, mn, mx) {
        const n = vals.length;
        return vals.map((v, i) => {
            const x = n === 1 ? W / 2 : pad + (i / (n - 1)) * (W - 2 * pad);
            const y = yMap(v, H, pad, mn, mx);
            return `${i === 0 ? 'M' : 'L'} ${round1(x)} ${round1(y)}`;
        }).join(' ');
    }

    /* ---------- registro ---------- */
    function openAdd() {
        const body = $('vit-sheet-body');
        const nowLocal = (BOOT.now || '').replace(' ', 'T');
        body.innerHTML = `
            <div class="vit-field">
                <span class="vit-field-ic"><i data-lucide="heart-pulse"></i> Presión arterial <span class="hint">(mmHg)</span></span>
                <div class="vit-duo">
                    <input class="vit-input" id="f-sys" type="number" inputmode="numeric" placeholder="Sis." min="60" max="260">
                    <span class="sep">/</span>
                    <input class="vit-input" id="f-dia" type="number" inputmode="numeric" placeholder="Dia." min="30" max="200">
                </div>
            </div>
            <div class="vit-row2">
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="droplet"></i> Glucosa</span><input class="vit-input" id="f-glucose" type="number" inputmode="numeric" placeholder="mg/dL"></div>
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="activity"></i> Pulso</span><input class="vit-input" id="f-heart_rate" type="number" inputmode="numeric" placeholder="lpm"></div>
            </div>
            <div class="vit-row2">
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="thermometer"></i> Temperatura</span><input class="vit-input" id="f-temperature" type="number" inputmode="decimal" step="0.1" placeholder="°C"></div>
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="wind"></i> Saturación O₂</span><input class="vit-input" id="f-spo2" type="number" inputmode="numeric" placeholder="%"></div>
            </div>
            <div class="vit-row2">
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="scale"></i> Peso</span><input class="vit-input" id="f-weight_kg" type="number" inputmode="decimal" step="0.1" placeholder="kg"></div>
                <div class="vit-field"><span class="vit-field-ic"><i data-lucide="ruler"></i> Estatura</span><input class="vit-input" id="f-height_cm" type="number" inputmode="decimal" step="0.5" placeholder="cm" value="${DATA.height_cm || ''}"></div>
            </div>
            <div class="vit-field">
                <label for="f-when">Fecha y hora</label>
                <input class="vit-input" id="f-when" type="datetime-local" value="${nowLocal}" max="${nowLocal}">
            </div>
            <div class="vit-field">
                <label for="f-note">Nota <span class="hint">(opcional)</span></label>
                <input class="vit-input" id="f-note" type="text" maxlength="255" placeholder="Ej.: en ayunas, después de caminar…">
            </div>`;
        if (window.lucide) lucide.createIcons();
        showDialog($('vit-sheet'));
    }

    async function save(e) {
        e?.preventDefault();
        const num = id => { const v = $(id).value.trim(); return v === '' ? null : v; };
        const payload = {
            systolic: num('f-sys'), diastolic: num('f-dia'),
            glucose: num('f-glucose'), heart_rate: num('f-heart_rate'),
            temperature: num('f-temperature'), spo2: num('f-spo2'),
            weight_kg: num('f-weight_kg'), height_cm: num('f-height_cm'),
            note: $('f-note').value.trim() || null,
        };
        const when = $('f-when').value;
        if (when) payload.recorded_at = when.replace('T', ' ').slice(0, 16);

        if ((payload.systolic === null) !== (payload.diastolic === null)) { toast('La presión necesita los dos valores.', 'error'); return; }
        const hasAny = ['systolic', 'glucose', 'heart_rate', 'temperature', 'spo2', 'weight_kg'].some(k => payload[k] !== null);
        if (!hasAny) { toast('Ingresa al menos una medición.', 'error'); return; }

        closeDialog($('vit-sheet'));
        if (PREVIEW) { previewInsert(payload); renderCards(); toast('Registrado (vista previa)', 'info'); return; }
        const r = await persist('POST', '/portal/me/vitals', payload);
        if (r.ok) { await reload(); toast('Medición guardada', 'success'); }
    }

    // En vista previa, refleja el registro en memoria para ver la UI.
    function previewInsert(p) {
        const t = (p.recorded_at || BOOT.now).slice(0, 16);
        const id = -Date.now();
        const push = (key, obj) => { (DATA.series[key] = DATA.series[key] || []).push(obj); DATA.latest[key] = obj; };
        if (p.systolic && p.diastolic) push('bp', { t, src: 'self', id, sys: +p.systolic, dia: +p.diastolic });
        if (p.weight_kg) push('weight', { t, src: 'self', id, v: +p.weight_kg });
        if (p.glucose) push('glucose', { t, src: 'self', id, v: +p.glucose });
        if (p.heart_rate) push('heart_rate', { t, src: 'self', id, v: +p.heart_rate });
        if (p.temperature) push('temperature', { t, src: 'self', id, v: +p.temperature });
        if (p.spo2) push('spo2', { t, src: 'self', id, v: +p.spo2 });
        if (p.height_cm) DATA.height_cm = +p.height_cm;
    }

    async function reload() {
        const r = await proxy('GET', '/portal/me/vitals');
        if (r.ok && r.data) DATA = { series: r.data.series || {}, latest: r.data.latest || {}, height_cm: r.data.height_cm || null };
        renderCards();
    }

    /* ---------- detalle / historial ---------- */
    function openDetail(key) {
        const m = metricByKey(key);
        const series = (DATA.series[key] || []).slice();
        $('vit-detail-title').textContent = m.name;
        const body = $('vit-detail-body');
        if (!series.length) {
            body.innerHTML = `<p style="color:var(--portal-muted);font-size:.9rem">Aún no tienes mediciones de ${m.name.toLowerCase()}. Toca <b>Registrar</b> para empezar.</p>`;
            showDialog($('vit-detail'));
            return;
        }
        const rows = series.slice().reverse().map(p => {
            const val = m.duo ? `${p.sys}/${p.dia}` : (key === 'temperature' ? round1(p.v).toFixed(1) : p.v);
            const it = m.interp(p);
            const del = (p.src === 'self' && p.id > 0) ? `<button class="vit-hist-del" data-id="${p.id}" aria-label="Borrar"><i data-lucide="trash-2"></i></button>` : '';
            return `<div class="vit-hist-row">
                <div><span class="v">${val}</span> <span class="vit-badge ${it.tone}" style="margin-left:6px">${it.label}</span><div class="d">${fmtDateLong(p.t)}</div></div>
                <div style="display:flex;align-items:center;gap:8px"><span class="src-tag ${p.src === 'self' ? 'self' : ''}">${p.src === 'self' ? 'En casa' : 'Hospital'}</span>${del}</div>
            </div>`;
        }).join('');
        body.innerHTML = `<div class="vit-chart">${bigChart(m, series)}</div><div class="vit-hist">${rows}</div>`;
        if (window.lucide) lucide.createIcons();
        body.querySelectorAll('.vit-hist-del').forEach(b => b.addEventListener('click', () => delPoint(+b.dataset.id, key)));
        showDialog($('vit-detail'));
    }

    function bigChart(m, series) {
        const W = 480, H = 170, padX = 8, padTop = 12, padBot = 22;
        const last = series.slice(-14);
        const draw = (vals, cls, mn, mx) => {
            const n = vals.length;
            const pts = vals.map((v, i) => {
                const x = n === 1 ? W / 2 : padX + (i / (n - 1)) * (W - 2 * padX);
                const y = H - padBot - ((v - mn) / ((mx - mn) || 1)) * (H - padTop - padBot);
                return [round1(x), round1(y)];
            });
            const d = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p[0]} ${p[1]}`).join(' ');
            const dots = pts.map(p => `<circle class="dot" cx="${p[0]}" cy="${p[1]}" r="3"/>`).join('');
            return `<path class="ln ${cls}" d="${d}"/>${dots}`;
        };
        let body;
        if (m.duo) {
            const sys = last.map(p => p.sys), dia = last.map(p => p.dia), all = sys.concat(dia);
            const mn = Math.min(...all) - 5, mx = Math.max(...all) + 5;
            body = draw(sys, '', mn, mx) + draw(dia, 'ln2', mn, mx);
        } else {
            const vals = last.map(p => p.v);
            const mn = Math.min(...vals) * 0.96, mx = Math.max(...vals) * 1.04;
            body = draw(vals, '', mn, mx);
        }
        const firstLbl = fmtDate(last[0].t), lastLbl = fmtDate(last[last.length - 1].t);
        return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none">
            <line class="grid" x1="0" y1="${H - padBot}" x2="${W}" y2="${H - padBot}"/>
            ${body}
            <text class="lbl" x="8" y="${H - 6}">${firstLbl}</text>
            <text class="lbl" x="${W - 8}" y="${H - 6}" text-anchor="end">${lastLbl}</text>
        </svg>`;
    }

    async function delPoint(id, key) {
        if (PREVIEW) {
            for (const k in DATA.series) DATA.series[k] = DATA.series[k].filter(p => p.id !== id);
            for (const k in DATA.series) DATA.latest[k] = DATA.series[k].length ? DATA.series[k][DATA.series[k].length - 1] : null;
            closeDialog($('vit-detail')); renderCards(); return;
        }
        const r = await persist('DELETE', '/portal/me/vitals/' + id);
        if (r.ok) { closeDialog($('vit-detail')); await reload(); toast('Medición eliminada', 'info'); }
    }

    /* ---------- helpers ---------- */
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const MESL = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    function parseT(t) { const [d, h] = String(t).split(' '); const [y, m, dd] = d.split('-').map(Number); const [hh, mi] = (h || '00:00').split(':').map(Number); return new Date(y, m - 1, dd, hh || 0, mi || 0); }
    function fmtDate(t) { const d = parseT(t); return `${d.getDate()} ${MESES[d.getMonth()]}`; }
    function fmtDateLong(t) { const d = parseT(t); return `${d.getDate()} de ${MESL[d.getMonth()]} ${d.getFullYear()} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`; }

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

    /* ---------- bind ---------- */
    function bind() {
        $('vit-add-btn').addEventListener('click', openAdd);
        $('vit-form').addEventListener('submit', save);
        document.querySelectorAll('[data-vit-close]').forEach(b => b.addEventListener('click', () => closeDialog(b.closest('dialog'))));
        document.querySelectorAll('dialog.vit-sheet, dialog.vit-detail-dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) closeDialog(d); }));
    }

    load();
})();
