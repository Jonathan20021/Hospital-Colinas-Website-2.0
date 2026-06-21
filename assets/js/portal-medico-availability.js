/**
 * Disponibilidad — calendario interactivo de rango (estilo Airbnb).
 * Selección de un día o un rango; bloquea las ausencias llamando al endpoint
 * existente POST /portal-doctor/me/availability una vez por día (sin tocar el
 * backend). doctorApi se resuelve en el boot (este archivo carga antes que
 * portal-medico.js).
 *
 * Nota de implementación: el grid se CONSTRUYE (innerHTML) solo al navegar de
 * mes; el resaltado de rango se PINTA (toggle de clases) en hover/selección.
 * Así no se recrea el DOM en cada mouseover, lo que rompería el click de cierre.
 */
(function () {
    'use strict';
    const CFG = window.DM_AVAIL || {};
    let api = window.doctorApi;

    const MES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const DOW = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const pad = (n) => String(n).padStart(2, '0');
    const isoOf = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
    const $ = (id) => document.getElementById(id);
    const refreshIcons = () => { if (window.lucide) window.lucide.createIcons(); };
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
    const fmtLong = (iso) => { const dt = new Date(iso + 'T00:00:00'); return dt.getDate() + ' de ' + MES[dt.getMonth()]; };
    const fmtFull = (iso) => { const dt = new Date(iso + 'T00:00:00'); return DOW[dt.getDay()] + ' ' + dt.getDate() + ' de ' + MES[dt.getMonth()] + ' de ' + dt.getFullYear(); };

    const blocked = new Set(CFG.blocked || []);
    const today = CFG.today;
    let view, start = null, end = null, hover = null;

    function daysBetween(a, b) {
        const out = []; const d = new Date(a + 'T00:00:00'); const e = new Date(b + 'T00:00:00');
        while (d <= e) { out.push(isoOf(d.getFullYear(), d.getMonth(), d.getDate())); d.setDate(d.getDate() + 1); }
        return out;
    }
    function effRange() {
        if (start && end) return [start, end];
        if (start && hover) return start <= hover ? [start, hover] : [hover, start];
        if (start) return [start, start];
        return null;
    }
    function selectableDays() {
        if (!start) return [];
        return daysBetween(start, end || start).filter((d) => !blocked.has(d) && d >= today);
    }

    // Construye el grid del mes (solo al iniciar o navegar). Sin clases de rango.
    function build() {
        const grid = $('av-grid'); if (!grid) return;
        const y = view.getFullYear(), m = view.getMonth();
        $('av-month').textContent = cap(MES[m]) + ' de ' + y;
        const firstDow = (new Date(y, m, 1).getDay() + 6) % 7; // 0 = lunes
        const dim = new Date(y, m + 1, 0).getDate();
        let html = '', cell = 1 - firstDow;
        for (let i = 0; i < 42; i++, cell++) {
            if (cell < 1 || cell > dim) { html += '<div class="av-cell av-empty"></div>'; continue; }
            const ds = isoOf(y, m, cell);
            const past = ds < today, isBlk = blocked.has(ds), isToday = ds === today;
            let dcls = 'av-day';
            if (past) dcls += ' is-past';
            if (isBlk) dcls += ' is-blocked';
            if (isToday) dcls += ' is-today';
            const disabled = past || isBlk;
            html += '<div class="av-cell" data-d="' + ds + '"><button type="button" class="' + dcls + '" data-d="' + ds + '"'
                  + (disabled ? ' disabled' : '') + '>' + cell + '</button></div>';
        }
        grid.innerHTML = html;
        paint();
    }

    // Pinta el resaltado del rango sobre las celdas existentes (no recrea el DOM).
    function paint() {
        const er = effRange();
        document.querySelectorAll('#av-grid .av-cell').forEach((cellEl) => {
            const ds = cellEl.dataset.d;
            cellEl.classList.remove('is-start', 'is-end', 'in-range', 'is-single');
            if (!ds || !er) return;
            if (ds === er[0] && ds === er[1]) cellEl.classList.add('is-single');
            else if (ds === er[0]) cellEl.classList.add('is-start');
            else if (ds === er[1]) cellEl.classList.add('is-end');
            else if (ds > er[0] && ds < er[1]) cellEl.classList.add('in-range');
        });
    }

    function updateSummary() {
        const main = $('av-sel-main'), sub = $('av-sel-sub'), btn = $('av-confirm-btn'), clr = $('av-clear'), sel = $('av-sel');
        if (!start) {
            main.textContent = 'Ninguna fecha seleccionada';
            sub.textContent = 'Elige en el calendario de arriba';
            btn.disabled = true; btn.innerHTML = '<i data-lucide="calendar-off"></i> Bloquear fechas';
            clr.hidden = true; sel.classList.remove('on'); refreshIcons(); return;
        }
        const a = start, b = end || start;
        const n = selectableDays().length;
        if (a === b) { main.textContent = cap(fmtFull(a)); sub.textContent = n ? '1 día a bloquear' : 'Este día ya está bloqueado'; }
        else { main.textContent = 'Del ' + fmtLong(a) + ' al ' + fmtLong(b); sub.textContent = n + ' día' + (n === 1 ? '' : 's') + ' a bloquear'; }
        btn.disabled = n === 0;
        btn.innerHTML = '<i data-lucide="calendar-off"></i> ' + (n ? ('Bloquear ' + n + ' día' + (n === 1 ? '' : 's')) : 'Bloquear fechas');
        clr.hidden = false; sel.classList.add('on'); refreshIcons();
    }

    function setStatus(msg, type) {
        const el = $('av-status'); if (!el) return;
        el.textContent = msg || '';
        el.className = 'doctor-save-status' + (type === 'saved' ? ' doctor-save-saved' : type === 'error' ? ' doctor-save-error' : '');
    }

    async function confirmBlock() {
        api = window.doctorApi || api;
        if (!api || !start) return;
        const days = selectableDays();
        if (!days.length) return;
        if (days.length > 92) { setStatus('Selecciona un máximo de 92 días por vez.', 'error'); return; }
        const reason = ($('av-reason').value || '').trim();
        const btn = $('av-confirm-btn'); btn.disabled = true;
        let ok = 0, fail = 0;
        for (let i = 0; i < days.length; i++) {
            btn.innerHTML = 'Bloqueando ' + (i + 1) + ' / ' + days.length + '…';
            try { const r = await api('POST', '/portal-doctor/me/availability', { date: days[i], reason }); if (r && r.ok) ok++; else fail++; }
            catch (e) { fail++; }
        }
        if (!fail) setStatus('✓ ' + ok + ' día' + (ok === 1 ? '' : 's') + ' bloqueado' + (ok === 1 ? '' : 's') + ' correctamente.', 'saved');
        else setStatus('Se bloquearon ' + ok + ' día(s); ' + fail + ' fallaron.', 'error');
        setTimeout(() => location.reload(), 800);
    }

    async function deleteGroup(btn) {
        api = window.doctorApi || api;
        const ids = (btn.dataset.ids || '').split(',').filter(Boolean);
        if (!ids.length || !api) return;
        if (!window.confirm('¿Eliminar ' + (ids.length > 1 ? ('estos ' + ids.length + ' días') : 'esta ausencia') + '?')) return;
        btn.disabled = true;
        let fail = 0;
        for (const id of ids) { try { const r = await api('DELETE', '/portal-doctor/me/availability/' + id); if (!(r && r.ok)) fail++; } catch (e) { fail++; } }
        if (!fail) { const item = btn.closest('.av-item'); if (item) item.style.opacity = '.3'; setTimeout(() => location.reload(), 250); }
        else { alert('No se pudieron eliminar todas las fechas.'); location.reload(); }
    }

    function init() {
        const grid = $('av-grid'); if (!grid) return;
        api = window.doctorApi || api;
        view = new Date(today + 'T00:00:00'); view.setDate(1);
        build(); updateSummary();

        grid.addEventListener('click', (e) => {
            const b = e.target.closest('.av-day'); if (!b || b.disabled) return;
            const ds = b.dataset.d;
            if (!start || (start && end)) { start = ds; end = null; }
            else if (ds >= start) { end = ds; }
            else { end = start; start = ds; }
            hover = null; updateSummary(); paint();
        });
        grid.addEventListener('mouseover', (e) => {
            const b = e.target.closest('.av-day'); if (!b || b.disabled) return;
            if (start && !end) { hover = b.dataset.d; paint(); }
        });
        grid.addEventListener('mouseleave', () => { if (start && !end && hover) { hover = null; paint(); } });

        $('av-prev').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); build(); });
        $('av-next').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); build(); });
        $('av-clear').addEventListener('click', () => { start = end = hover = null; updateSummary(); paint(); setStatus(''); });
        $('av-confirm-btn').addEventListener('click', confirmBlock);

        const list = $('av-list');
        if (list) list.addEventListener('click', (e) => { const b = e.target.closest('.av-del'); if (b) deleteGroup(b); });

        refreshIcons();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
