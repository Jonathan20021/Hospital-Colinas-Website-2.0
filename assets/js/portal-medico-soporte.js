/* Portal Médico — Soporte TI (helpdesk).
 * Envía tickets de incidencia/solicitud al equipo de Soporte TI vía el proxy
 * server-side (window.doctorApi). El JWT nunca llega al navegador.
 */
(function () {
    'use strict';

    // portal-medico.js (que define window.doctorApi) puede cargar DESPUÉS de
    // este script. Esperamos a que exista antes de tocar la API.
    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }

    var $ = function (s, r) { return (r || document).querySelector(s); };

    // Subcategorías por tipo de incidencia.
    var SUBS = {
        sistema: ['Portal médico', 'SIGMA / SGC', 'Correo electrónico', 'Internet / red', 'Impresión', 'Telefonía IP', 'Otro software'],
        equipo: ['Computadora / laptop', 'Monitor', 'Impresora / escáner', 'Teléfono / extensión', 'Climatización (A/C)', 'Mobiliario', 'Eléctrico / tomacorriente', 'Otro equipo'],
        solicitud: ['Instalación de software', 'Acceso / permisos', 'Traslado de equipo', 'Insumos / consumibles', 'Otra solicitud']
    };
    var SUB_LABEL = { sistema: '¿Qué sistema?', equipo: '¿Qué equipo?', solicitud: 'Tipo de solicitud' };
    var SUBJ_PH = {
        sistema: 'Ej.: No puedo entrar al portal',
        equipo: 'Ej.: La impresora no enciende',
        solicitud: 'Ej.: Instalar lector de PDF en mi PC'
    };

    var PRI_LABEL = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
    var CAT_LABEL = { sistema: 'Sistema', equipo: 'Equipo del consultorio', solicitud: 'Solicitud puntual' };
    var CAT_ICON = { sistema: 'monitor', equipo: 'hard-drive', solicitud: 'clipboard-list' };
    var STATUS_LABEL = { enviado: 'Enviado', en_proceso: 'En proceso', resuelto: 'Resuelto', cerrado: 'Cerrado' };

    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    function fmtDate(s) {
        if (!s) return '';
        var m = String(s).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!m) return s;
        return (+m[3]) + ' ' + MESES[(+m[2]) - 1] + ' ' + m[1] + ' · ' + m[4] + ':' + m[5];
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    // ---- Tipo de incidencia ----
    var currentCat = 'sistema';
    function fillSubs(cat) {
        var sel = $('#sop-subcategory');
        sel.innerHTML = '';
        (SUBS[cat] || []).forEach(function (s) {
            var o = document.createElement('option');
            o.value = s; o.textContent = s; sel.appendChild(o);
        });
        $('#sop-sub-label').textContent = SUB_LABEL[cat] || 'Categoría';
        $('#sop-subject').placeholder = SUBJ_PH[cat] || 'Asunto';
    }

    function bindTypes() {
        var btns = document.querySelectorAll('.sop-type');
        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                btns.forEach(function (x) { x.classList.remove('on'); x.setAttribute('aria-checked', 'false'); });
                b.classList.add('on'); b.setAttribute('aria-checked', 'true');
                currentCat = b.getAttribute('data-cat');
                fillSubs(currentCat);
            });
        });
        fillSubs(currentCat);
    }

    // ---- Adjunto (foto) ----
    var attachment = null, attachmentName = null;
    function bindAttach() {
        var input = $('#sop-file'), prev = $('#sop-attach-preview'), img = $('#sop-attach-img'), clr = $('#sop-attach-clear');
        input.addEventListener('change', function (ev) {
            var f = ev.target.files && ev.target.files[0];
            if (!f) return;
            if (!/^image\/(png|jpeg)$/.test(f.type)) { setStatus('⚠ Solo imágenes JPG o PNG.', 'error'); input.value = ''; return; }
            if (f.size > 6 * 1024 * 1024) { setStatus('⚠ La imagen supera 6 MB. Toma una más liviana.', 'error'); input.value = ''; return; }
            var rd = new FileReader();
            rd.onload = function () {
                // Reescalar a máx 1600px y recomprimir a JPEG para aligerar el envío.
                var im = new Image();
                im.onload = function () {
                    var max = 1600, w = im.width, h = im.height;
                    if (w > max || h > max) { var r = Math.min(max / w, max / h); w = Math.round(w * r); h = Math.round(h * r); }
                    var c = document.createElement('canvas'); c.width = w; c.height = h;
                    c.getContext('2d').drawImage(im, 0, 0, w, h);
                    attachment = c.toDataURL('image/jpeg', 0.72);
                    attachmentName = (f.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
                    img.src = attachment; prev.hidden = false;
                };
                im.src = rd.result;
            };
            rd.readAsDataURL(f);
        });
        clr.addEventListener('click', function () { attachment = null; attachmentName = null; input.value = ''; prev.hidden = true; img.removeAttribute('src'); });
    }

    // ---- Estado / envío ----
    function setStatus(msg, kind) {
        var el = $('#sop-status');
        el.textContent = msg || '';
        if (window.doctorAutoSaveHint && kind) window.doctorAutoSaveHint(el, kind === 'error' ? 'error' : (kind === 'saving' ? 'saving' : 'saved'));
    }

    function bindSubmit() {
        $('#sop-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            var subject = $('#sop-subject').value.trim();
            var description = $('#sop-description').value.trim();
            if (!subject) { setStatus('⚠ Escribe un asunto.', 'error'); $('#sop-subject').focus(); return; }
            if (description.length < 10) { setStatus('⚠ Describe el problema con un poco más de detalle.', 'error'); $('#sop-description').focus(); return; }

            var body = {
                category: currentCat,
                subcategory: $('#sop-subcategory').value,
                priority: $('#sop-priority').value,
                subject: subject,
                description: description,
                location: {
                    building: $('#sop-building').value.trim(),
                    floor: $('#sop-floor').value.trim(),
                    office: $('#sop-office').value.trim(),
                    phone_ext: $('#sop-ext').value.trim(),
                    reference: $('#sop-reference').value.trim()
                },
                contact_phone: $('#sop-phone').value.trim(),
                attachment: attachment,
                attachment_name: attachmentName
            };

            var btn = $('#sop-submit'); btn.disabled = true;
            setStatus('Enviando ticket…', 'saving');
            var r;
            try { r = await window.doctorApi('POST', '/portal-doctor/me/support-tickets', body); }
            catch (err) { r = { ok: false, message: 'No se pudo enviar. Revisa tu conexión.' }; }
            btn.disabled = false;

            if (r && r.ok) {
                var folio = (r.data && r.data.folio) || '';
                setStatus('✓ Ticket enviado a Soporte TI' + (folio ? ' · ' + folio : '') + '. Te contactarán pronto.', 'saved');
                // Limpiar el formulario (conserva la ubicación para próximos tickets).
                $('#sop-subject').value = ''; $('#sop-description').value = '';
                $('#sop-attach-clear').click();
                loadList();
            } else {
                setStatus('⚠ ' + ((r && r.message) || 'No se pudo enviar el ticket.'), 'error');
            }
        });
    }

    // ---- Mis tickets ----
    function priPill(p) { return '<span class="sop-pri sop-pri-' + esc(p) + '">' + esc(PRI_LABEL[p] || p) + '</span>'; }

    function renderList(tickets) {
        var box = $('#sop-list');
        if (!tickets || !tickets.length) {
            box.innerHTML = '<div class="doctor-empty" style="padding:28px 16px">' +
                '<div class="doctor-empty-illustration"><i data-lucide="inbox"></i></div>' +
                '<p class="doctor-empty-title">Sin tickets todavía</p>' +
                '<p>Cuando reportes una incidencia, aparecerá aquí.</p></div>';
            if (window.lucide) lucide.createIcons();
            return;
        }
        box.innerHTML = tickets.map(function (t, i) {
            return '<button type="button" class="sop-ticket" data-i="' + i + '">' +
                '<span class="sop-ticket-ic"><i data-lucide="' + (CAT_ICON[t.category] || 'life-buoy') + '"></i></span>' +
                '<span class="sop-ticket-main">' +
                '<span class="sop-ticket-subj">' + esc(t.subject) + '</span>' +
                '<span class="sop-ticket-meta"><span class="sop-ticket-folio">' + esc(t.folio) + '</span> · ' +
                esc(fmtDate(t.created_at)) + ' · ' + esc(STATUS_LABEL[t.status] || t.status || 'Enviado') + '</span>' +
                '</span>' + priPill(t.priority) + '</button>';
        }).join('');
        var btns = box.querySelectorAll('.sop-ticket');
        btns.forEach(function (b) { b.addEventListener('click', function () { openDetail(tickets[+b.getAttribute('data-i')]); }); });
        if (window.lucide) lucide.createIcons();
    }

    function row(k, v) { return v ? '<div class="sop-md-row"><div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div></div>' : ''; }

    function openDetail(t) {
        if (!t) return;
        $('#sop-m-title').textContent = t.subject || 'Ticket';
        $('#sop-m-folio').textContent = (t.folio || '') + ' · ' + (STATUS_LABEL[t.status] || t.status || 'Enviado');
        var loc = t.location || {};
        var locStr = [loc.building, loc.floor, loc.office].filter(Boolean).join(' · ');
        $('#sop-m-body').innerHTML =
            row('Enviado', fmtDate(t.created_at)) +
            row('Tipo', CAT_LABEL[t.category] || t.category) +
            row('Categoría', t.subcategory) +
            '<div class="sop-md-row"><div class="k">Prioridad</div><div class="v">' + priPill(t.priority) + '</div></div>' +
            row('Descripción', t.description) +
            row('Ubicación', locStr) +
            row('Extensión', loc.phone_ext) +
            row('Referencia', loc.reference) +
            row('Tel. contacto', t.contact_phone) +
            (t.has_attachment ? row('Adjunto', t.attachment_name || 'Foto incluida en el correo') : '');
        var m = $('#sop-modal'); m.hidden = false; document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
    }
    function closeDetail() { $('#sop-modal').hidden = true; document.body.style.overflow = ''; }

    function prefill(profile) {
        if (!profile) return;
        var map = { building: 'sop-building', floor: 'sop-floor', office: 'sop-office', phone_ext: 'sop-ext', reference: 'sop-reference', contact_phone: 'sop-phone' };
        Object.keys(map).forEach(function (k) {
            var el = document.getElementById(map[k]);
            if (el && !el.value && profile[k]) el.value = profile[k];
        });
    }

    async function loadList() {
        try {
            var r = await window.doctorApi('GET', '/portal-doctor/me/support-tickets');
            if (r && r.ok && r.data) {
                renderList(r.data.tickets || []);
                prefill(r.data.profile || null);
            } else {
                renderList([]);
            }
        } catch (e) { renderList([]); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!$('#sop-form')) return;
        bindTypes();
        bindAttach();
        bindSubmit();
        document.querySelectorAll('[data-sop-close]').forEach(function (b) { b.addEventListener('click', closeDetail); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDetail(); });
        whenApi(loadList);
    });
})();
