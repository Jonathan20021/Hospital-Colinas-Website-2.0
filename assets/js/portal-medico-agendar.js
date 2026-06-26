/* Portal Médico — Nueva cita (auto-agenda).
 * Wizard en drawer: ① Paciente (buscar mis pacientes / crear nuevo) →
 * ② Fecha y hora (slots libres + opción de hora manual) → ③ Confirmar.
 * Todo vía window.doctorApi (proxy server-side; el JWT nunca llega al navegador).
 */
(function () {
    'use strict';

    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }
    var $ = function (s, r) { return (r || document).querySelector(s); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function pad(n) { return String(n).padStart(2, '0'); }

    var MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var MES_L = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var DIA_L = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    var st = {};
    function reset() {
        var now = new Date();
        st = { step: 1, mode: 'buscar', patient: null, time: null, manual: false, slots: {}, slotMin: 30,
               viewY: now.getFullYear(), viewM: now.getMonth(), selDay: null, busy: false };
    }

    function fmtSql(sql) { // 'YYYY-MM-DD HH:MM:SS' → "lun 29 jun 2026 · 2:37 p. m."
        var m = String(sql).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!m) return sql;
        var d = new Date(+m[1], +m[2] - 1, +m[3]);
        var h = +m[4], ap = h < 12 ? 'a. m.' : 'p. m.', h12 = (h % 12) || 12;
        return DIA_L[d.getDay()].slice(0, 3) + ' ' + (+m[3]) + ' ' + MES[+m[2] - 1] + ' ' + m[1] + ' · ' + h12 + ':' + m[5] + ' ' + ap;
    }
    function timeLabel(sql) { var m = String(sql).match(/(\d{2}):(\d{2})/); if (!m) return sql; var h = +m[1], ap = h < 12 ? 'a.m.' : 'p.m.', h12 = (h % 12) || 12; return h12 + ':' + m[2] + ' ' + ap; }

    // ── Apertura / cierre ─────────────────────────────────────────────────────
    function open() { reset(); var m = $('#nv-modal'); m.hidden = false; document.documentElement.classList.add('nv-locked'); document.body.style.overflow = 'hidden'; render(); }
    function close() { var m = $('#nv-modal'); if (m) { m.hidden = true; document.documentElement.classList.remove('nv-locked'); document.body.style.overflow = ''; } }

    // ── Render maestro ────────────────────────────────────────────────────────
    function setSteps() {
        document.querySelectorAll('#nv-steps .nv-stp').forEach(function (el) {
            var s = +el.getAttribute('data-s');
            el.classList.toggle('on', s === st.step);
            el.classList.toggle('done', s < st.step);
        });
    }
    function render() {
        setSteps();
        if (st.step === 1) renderPatient();
        else if (st.step === 2) renderWhen();
        else renderConfirm();
        if (window.lucide) lucide.createIcons();
    }
    function go(step) { st.step = step; render(); }

    // ── Paso 1: Paciente ──────────────────────────────────────────────────────
    function renderPatient() {
        var chip = st.patient
            ? '<div class="nv-chip"><i data-lucide="user-check"></i><div><strong>' + esc(st.patient.name) + '</strong>'
                + '<span>' + (st.patient.cedula ? esc(st.patient.cedula) + ' · ' : '') + esc(st.patient.phone || '') + '</span></div>'
                + '<button type="button" class="nv-chip-x" id="nv-clearpat" aria-label="Quitar"><i data-lucide="x"></i></button></div>'
            : '';

        $('#nv-body').innerHTML =
            '<div class="nv-seg">'
            + '<button type="button" class="nv-seg-b ' + (st.mode === 'buscar' ? 'on' : '') + '" data-mode="buscar"><i data-lucide="search"></i> Mis pacientes</button>'
            + '<button type="button" class="nv-seg-b ' + (st.mode === 'nuevo' ? 'on' : '') + '" data-mode="nuevo"><i data-lucide="user-plus"></i> Paciente nuevo</button>'
            + '</div>'
            + chip
            + (st.mode === 'buscar'
                ? '<div class="nv-search"><i data-lucide="search"></i><input type="text" id="nv-q" class="doctor-input" autocomplete="off" placeholder="Buscar por nombre, cédula o teléfono…"></div>'
                  + '<div class="nv-results" id="nv-results"><p class="nv-hint">Escribe para buscar entre tus pacientes con citas previas.</p></div>'
                : '<div class="nv-form">'
                  + '<label class="nv-f nv-col2">Nombre completo *<input type="text" id="np-name" class="doctor-input" autocomplete="off" placeholder="Nombre y apellidos"></label>'
                  + '<label class="nv-f">Cédula<input type="text" id="np-ced" class="doctor-input" autocomplete="off" placeholder="000-0000000-0"></label>'
                  + '<label class="nv-f">Teléfono *<input type="tel" id="np-phone" class="doctor-input" autocomplete="off" placeholder="809-000-0000"></label>'
                  + '<label class="nv-f">Fecha de nacimiento<input type="date" id="np-dob" class="doctor-input" max="' + todayStr() + '"></label>'
                  + '<label class="nv-f">Sexo<select id="np-gender" class="doctor-input"><option value="">—</option><option value="Male">Masculino</option><option value="Female">Femenino</option><option value="Other">Otro</option></select></label>'
                  + '<p class="nv-note nv-col2"><i data-lucide="shield-check"></i> Si la cédula ya existe, se reutiliza el expediente (no se duplica).</p>'
                  + '</div>');

        $('#nv-foot').innerHTML =
            '<button type="button" class="doctor-btn doctor-btn-ghost" data-nv-close>Cancelar</button>'
            + '<button type="button" class="doctor-btn doctor-btn-primary" id="nv-next1"><span>Continuar</span> <i data-lucide="arrow-right"></i></button>';

        // toggle modo
        $('#nv-body').querySelectorAll('.nv-seg-b').forEach(function (b) {
            b.addEventListener('click', function () { st.mode = b.getAttribute('data-mode'); render(); });
        });
        var clr = $('#nv-clearpat'); if (clr) clr.addEventListener('click', function () { st.patient = null; render(); });

        if (st.mode === 'buscar') {
            var q = $('#nv-q'); var t = null;
            q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { searchPatients(q.value.trim()); }, 280); });
            q.focus();
        }
        $('#nv-next1').addEventListener('click', onNext1);
    }

    function searchPatients(term) {
        var box = $('#nv-results');
        if (term.length < 2) { box.innerHTML = '<p class="nv-hint">Escribe al menos 2 caracteres.</p>'; return; }
        box.innerHTML = '<p class="nv-hint">Buscando…</p>';
        window.doctorApi('GET', '/portal-doctor/me/patients', { q: term, per_page: 8 }).then(function (r) {
            var items = (r.ok && r.data && r.data.items) ? r.data.items : [];
            if (!items.length) { box.innerHTML = '<p class="nv-hint">Sin coincidencias entre tus pacientes. Usa “Paciente nuevo”.</p>'; return; }
            box.innerHTML = items.map(function (p, i) {
                return '<button type="button" class="nv-res" data-i="' + i + '">'
                    + '<span class="nv-res-av">' + esc((p.name || '?').trim().charAt(0).toUpperCase()) + '</span>'
                    + '<span class="nv-res-main"><strong>' + esc(p.name) + '</strong>'
                    + '<span>' + (p.cedula ? esc(p.cedula) + ' · ' : '') + esc(p.phone || 's/teléfono') + '</span></span>'
                    + '<i data-lucide="chevron-right"></i></button>';
            }).join('');
            box.querySelectorAll('.nv-res').forEach(function (b) {
                b.addEventListener('click', function () {
                    var p = items[+b.getAttribute('data-i')];
                    st.patient = { id: p.id, name: p.name, cedula: p.cedula, phone: p.phone };
                    render();
                });
            });
            if (window.lucide) lucide.createIcons();
        });
    }

    function setStatusFoot(msg, kind) {
        var f = $('#nv-foot'); var el = f.querySelector('.nv-foot-msg');
        if (!el) { el = document.createElement('p'); el.className = 'nv-foot-msg'; f.insertBefore(el, f.firstChild); }
        el.textContent = msg || ''; el.className = 'nv-foot-msg' + (kind ? ' ' + kind : '');
    }

    async function onNext1() {
        if (st.mode === 'buscar') {
            if (!st.patient) { setStatusFoot('Selecciona un paciente o crea uno nuevo.', 'err'); return; }
            return go(2);
        }
        // modo nuevo: crear/reutilizar paciente
        var name = $('#np-name').value.trim();
        var phone = $('#np-phone').value.trim();
        if (name.length < 3) { setStatusFoot('Escribe el nombre completo.', 'err'); $('#np-name').focus(); return; }
        if (!phone) { setStatusFoot('El teléfono es obligatorio.', 'err'); $('#np-phone').focus(); return; }
        if (st.busy) return; st.busy = true;
        var btn = $('#nv-next1'); btn.disabled = true; setStatusFoot('Guardando paciente…', '');
        var r;
        try {
            r = await window.doctorApi('POST', '/portal-doctor/me/patients', {
                name: name, phone: phone,
                cedula: $('#np-ced').value.trim(),
                dob: $('#np-dob').value,
                gender: $('#np-gender').value
            });
        } catch (e) { r = { ok: false, message: 'Error de conexión.' }; }
        st.busy = false; btn.disabled = false;
        if (r.ok && r.data) {
            st.patient = { id: r.data.id, name: r.data.name, cedula: r.data.cedula, phone: phone };
            go(2);
        } else {
            setStatusFoot('⚠ ' + (r.message || 'No se pudo registrar el paciente.'), 'err');
        }
    }

    // ── Paso 2: Fecha y hora ──────────────────────────────────────────────────
    function todayStr() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    function renderWhen() {
        $('#nv-body').innerHTML =
            '<div class="nv-patbar"><i data-lucide="user"></i> <strong>' + esc(st.patient ? st.patient.name : '') + '</strong></div>'
            + '<div class="nv-slot-loader" id="nv-loader"><i data-lucide="loader-2" class="nv-spin"></i> Cargando horarios disponibles…</div>'
            + '<div id="nv-cal" hidden></div>'
            + '<div class="nv-manual">'
            + '<label class="nv-manual-tog"><input type="checkbox" id="nv-manual-chk"> Otra hora (fuera de los cupos)</label>'
            + '<input type="datetime-local" id="nv-manual-inp" class="doctor-input" hidden min="' + todayStr() + 'T00:00">'
            + '</div>';
        renderFootWhen();

        $('#nv-manual-chk').addEventListener('change', function () {
            st.manual = this.checked;
            $('#nv-manual-inp').hidden = !this.checked;
            if (this.checked) { st.time = null; clearSlotSel(); } else { $('#nv-manual-inp').value = ''; }
            updateContinue();
        });
        $('#nv-manual-inp').addEventListener('change', function () {
            if (!this.value) { st.time = null; updateContinue(); return; }
            st.time = this.value.replace('T', ' ').slice(0, 16) + ':00';
            clearSlotSel(); updateContinue();
        });

        if (Object.keys(st.slots).length) { showCal(); }
        else { loadSlots(); }
    }

    function renderFootWhen() {
        $('#nv-foot').innerHTML =
            '<button type="button" class="doctor-btn doctor-btn-ghost" id="nv-back2"><i data-lucide="arrow-left"></i> Atrás</button>'
            + '<button type="button" class="doctor-btn doctor-btn-primary" id="nv-next2" disabled><span>Continuar</span> <i data-lucide="arrow-right"></i></button>';
        $('#nv-back2').addEventListener('click', function () { go(1); });
        $('#nv-next2').addEventListener('click', function () { if (st.time) go(3); });
        updateContinue();
    }
    function updateContinue() { var b = $('#nv-next2'); if (b) b.disabled = !st.time; }
    function clearSlotSel() { document.querySelectorAll('#nv-cal .nv-time.on').forEach(function (x) { x.classList.remove('on'); }); }

    function loadSlots() {
        var from = todayStr();
        var to = new Date(Date.now() + 30 * 86400000); to = to.getFullYear() + '-' + pad(to.getMonth() + 1) + '-' + pad(to.getDate());
        window.doctorApi('GET', '/portal-doctor/me/slots', { date_from: from, date_to: to, slot_minutes: 30 }).then(function (r) {
            if (r.ok && r.data) { st.slots = r.data.days || {}; st.slotMin = r.data.slot_minutes || 30; }
            showCal();
        }).catch(function () { showCal(); });
    }

    function showCal() {
        var loader = $('#nv-loader'); if (loader) loader.hidden = true;
        var cal = $('#nv-cal'); cal.hidden = false;
        if (!Object.keys(st.slots).length) {
            cal.innerHTML = '<div class="nv-empty"><i data-lucide="calendar-x"></i><p>No hay cupos en los próximos 30 días. Usa “Otra hora” para agendar manualmente.</p></div>';
            if (window.lucide) lucide.createIcons();
            return;
        }
        paintCal();
    }

    function paintCal() {
        var cal = $('#nv-cal');
        var y = st.viewY, mo = st.viewM;
        var first = new Date(y, mo, 1);
        var dim = new Date(y, mo + 1, 0).getDate();
        var off = (first.getDay() + 6) % 7; // lunes primero
        var minM = new Date(); minM = new Date(minM.getFullYear(), minM.getMonth(), 1);
        var maxM = new Date(Date.now() + 30 * 86400000); maxM = new Date(maxM.getFullYear(), maxM.getMonth(), 1);
        var canPrev = new Date(y, mo, 1) > minM;
        var canNext = new Date(y, mo + 1, 1) <= maxM;

        var cells = '';
        for (var i = 0; i < off; i++) cells += '<div class="nv-cell nv-empty-cell"></div>';
        for (var d = 1; d <= dim; d++) {
            var ds = y + '-' + pad(mo + 1) + '-' + pad(d);
            var avail = !!st.slots[ds];
            var cls = 'nv-cell' + (avail ? ' nv-avail' : ' nv-off') + (st.selDay === ds ? ' on' : '') + (ds === todayStr() ? ' nv-today' : '');
            cells += avail
                ? '<button type="button" class="' + cls + '" data-day="' + ds + '">' + d + '<span class="nv-dot"></span></button>'
                : '<div class="' + cls + '">' + d + '</div>';
        }

        var times = '';
        if (st.selDay && st.slots[st.selDay]) {
            times = st.slots[st.selDay].map(function (ts) {
                return '<button type="button" class="nv-time ' + (st.time === ts ? 'on' : '') + '" data-time="' + ts + '">' + timeLabel(ts) + '</button>';
            }).join('');
        } else {
            times = '<p class="nv-hint">Elige un día disponible.</p>';
        }

        cal.innerHTML =
            '<div class="nv-cal-head"><button type="button" class="nv-nav" id="nv-prev" ' + (canPrev ? '' : 'disabled') + '>‹</button>'
            + '<div class="nv-cal-title">' + (MES_L[mo].charAt(0).toUpperCase() + MES_L[mo].slice(1)) + ' ' + y + '</div>'
            + '<button type="button" class="nv-nav" id="nv-next" ' + (canNext ? '' : 'disabled') + '>›</button></div>'
            + '<div class="nv-wd"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>'
            + '<div class="nv-grid">' + cells + '</div>'
            + '<div class="nv-times-h">' + (st.selDay ? 'Horarios · ' + fmtDayLabel(st.selDay) : 'Horarios') + '</div>'
            + '<div class="nv-times">' + times + '</div>';

        var p = $('#nv-prev'); if (p) p.addEventListener('click', function () { st.viewM--; if (st.viewM < 0) { st.viewM = 11; st.viewY--; } paintCal(); });
        var n = $('#nv-next'); if (n) n.addEventListener('click', function () { st.viewM++; if (st.viewM > 11) { st.viewM = 0; st.viewY++; } paintCal(); });
        cal.querySelectorAll('.nv-cell[data-day]').forEach(function (b) {
            b.addEventListener('click', function () { st.selDay = b.getAttribute('data-day'); paintCal(); });
        });
        cal.querySelectorAll('.nv-time').forEach(function (b) {
            b.addEventListener('click', function () {
                st.time = b.getAttribute('data-time'); st.manual = false;
                var chk = $('#nv-manual-chk'); if (chk) { chk.checked = false; $('#nv-manual-inp').hidden = true; }
                cal.querySelectorAll('.nv-time.on').forEach(function (x) { x.classList.remove('on'); });
                b.classList.add('on'); updateContinue();
            });
        });
        if (window.lucide) lucide.createIcons();
    }
    function fmtDayLabel(ds) { var m = ds.split('-'); var d = new Date(+m[0], +m[1] - 1, +m[2]); return DIA_L[d.getDay()] + ' ' + (+m[2]) + ' de ' + MES_L[+m[1] - 1]; }

    // ── Paso 3: Confirmar ─────────────────────────────────────────────────────
    function renderConfirm() {
        $('#nv-body').innerHTML =
            '<div class="nv-sum">'
            + '<div class="nv-sum-row"><span class="k"><i data-lucide="user"></i> Paciente</span><span class="v">' + esc(st.patient.name) + (st.patient.cedula ? ' · ' + esc(st.patient.cedula) : '') + '</span></div>'
            + '<div class="nv-sum-row"><span class="k"><i data-lucide="calendar-clock"></i> Fecha y hora</span><span class="v">' + esc(fmtSql(st.time)) + '</span></div>'
            + '</div>'
            + '<label class="nv-f">Motivo / notas (opcional)<textarea id="nv-notes" class="doctor-input" rows="3" placeholder="Motivo de la consulta…"></textarea></label>';
        $('#nv-foot').innerHTML =
            '<button type="button" class="doctor-btn doctor-btn-ghost" id="nv-back3"><i data-lucide="arrow-left"></i> Atrás</button>'
            + '<button type="button" class="doctor-btn doctor-btn-primary" id="nv-confirm"><i data-lucide="check"></i> Confirmar cita</button>';
        $('#nv-back3').addEventListener('click', function () { go(2); });
        $('#nv-confirm').addEventListener('click', onConfirm);
    }

    async function onConfirm() {
        if (st.busy) return; st.busy = true;
        var btn = $('#nv-confirm'); btn.disabled = true; setStatusFoot('Agendando…', '');
        var r;
        try {
            r = await window.doctorApi('POST', '/portal-doctor/me/appointments', {
                patient_id: st.patient.id, appointment_time: st.time, notes: $('#nv-notes').value.trim()
            });
        } catch (e) { r = { ok: false, message: 'Error de conexión.' }; }
        st.busy = false; btn.disabled = false;
        if (r.ok) {
            $('#nv-body').innerHTML = '<div class="nv-done"><div class="nv-done-ic"><i data-lucide="check"></i></div>'
                + '<h3>¡Cita agendada!</h3><p>' + esc(st.patient.name) + '</p><p class="nv-done-when">' + esc(fmtSql(st.time)) + '</p></div>';
            $('#nv-foot').innerHTML = '<button type="button" class="doctor-btn doctor-btn-primary" id="nv-done-ok" style="width:100%;justify-content:center"><i data-lucide="calendar-check"></i> Ver en la agenda</button>';
            if (window.lucide) lucide.createIcons();
            $('#nv-done-ok').addEventListener('click', function () { location.reload(); });
            setTimeout(function () { location.reload(); }, 1400);
        } else {
            setStatusFoot('⚠ ' + (r.message || 'No se pudo agendar.'), 'err');
        }
    }

    // ── Wiring global ─────────────────────────────────────────────────────────
    window.openNuevaCita = function () { whenApi(open); };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-nueva-cita]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); window.openNuevaCita(); }); });
        document.addEventListener('click', function (e) { if (e.target.closest('[data-nv-close]')) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('#nv-modal').hidden) close(); });
    });
})();
