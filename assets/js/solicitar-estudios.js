/**
 * Solicitar autorización de estudios (Imágenes / Laboratorio).
 * Compartido por la página pública (invitado) y la del portal (autenticado).
 *
 * Invitado:  POST a guest-study-request.php (crea cuenta ligera + sesión) y
 *            luego sube documentos por el proxy autenticado.
 * Portal:    crea la solicitud y sube documentos, todo por el proxy autenticado.
 *
 * Los archivos viajan en base64 dentro del JSON (mismo patrón que el chat),
 * así reusa el proxy existente sin multipart.
 */
(function () {
    'use strict';

    var CFG = window.SE_CONFIG || {};
    var form = document.getElementById('se-form');
    if (!form) return;

    var MODE = (CFG.mode === 'portal') ? 'portal' : 'guest';
    var ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    var MAX_BYTES = 5 * 1024 * 1024;

    var STEPS = MODE === 'guest'
        ? ['tipo', 'estudios', 'seguro', 'datos', 'docs', 'confirm']
        : ['tipo', 'estudios', 'seguro', 'docs', 'confirm'];
    var LABELS = { tipo: 'Tipo', estudios: 'Estudios', seguro: 'Seguro', datos: 'Tus datos', docs: 'Documentos', confirm: 'Enviar' };

    var idx = 0;
    var files = {}; // { order: {filename, mime, data}, ... }

    var sections = {};
    form.querySelectorAll('[data-se-step]').forEach(function (el) { sections[el.getAttribute('data-se-step')] = el; });
    var nextBtn = form.querySelector('[data-se-next]');
    var backBtn = form.querySelector('[data-se-back]');
    var progressEl = document.querySelector('[data-se-progress]');

    /* ---------- utilidades ---------- */
    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function showError(step, msg) {
        var el = form.querySelector('[data-se-error="' + step + '"]');
        if (!el) return;
        if (msg) { el.textContent = msg; el.hidden = false; } else { el.hidden = true; }
    }
    function relucide() { if (window.lucide) lucide.createIcons(); }

    function renderProgress() {
        if (!progressEl) return;
        progressEl.innerHTML = STEPS.map(function (s, i) {
            var cls = i === idx ? 'is-current' : (i < idx ? 'is-done' : '');
            return '<li class="' + cls + '"><span>' + (i + 1) + '</span>' + esc(LABELS[s]) + '</li>';
        }).join('');
    }

    function showStep(i) {
        idx = Math.max(0, Math.min(i, STEPS.length - 1));
        Object.keys(sections).forEach(function (k) { sections[k].hidden = true; });
        var doneSec = sections.done; if (doneSec) doneSec.hidden = true;
        var cur = STEPS[idx];
        if (sections[cur]) sections[cur].hidden = false;

        backBtn.hidden = idx === 0;
        // En "confirm" el envío lo hace el botón propio de la sección.
        nextBtn.hidden = cur === 'confirm';
        nextBtn.innerHTML = 'Continuar <i data-lucide="arrow-right"></i>';

        if (cur === 'estudios') syncGroups();
        if (cur === 'confirm') buildSummary();
        renderProgress();
        relucide();
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
    }

    /* ---------- paso 1: tipo → grupos visibles ---------- */
    function studyType() {
        var r = form.querySelector('input[name="study_type"]:checked');
        return r ? r.value : '';
    }
    function syncGroups() {
        var t = studyType();
        var gImg = form.querySelector('[data-se-group="imaging"]');
        var gLab = form.querySelector('[data-se-group="lab"]');
        var showImg = (t === 'imaging' || t === 'both');
        var showLab = (t === 'lab' || t === 'both');
        if (gImg) { gImg.hidden = !showImg; if (!showImg) uncheck(gImg); }
        if (gLab) { gLab.hidden = !showLab; if (!showLab) uncheck(gLab); }
    }
    function uncheck(group) { group.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; }); }

    /* ---------- seguro: "otra" aseguradora ---------- */
    var insurerSel = form.querySelector('#se-insurer');
    var insurerOtherWrap = form.querySelector('[data-se-insurer-other]');
    if (insurerSel && insurerOtherWrap) {
        insurerSel.addEventListener('change', function () {
            insurerOtherWrap.hidden = insurerSel.value !== '__other__';
        });
    }

    /* ---------- documentos ---------- */
    form.querySelectorAll('.se-file').forEach(function (box) {
        var input = box.querySelector('input[type="file"]');
        var btn = box.querySelector('.se-file-btn');
        var prev = box.querySelector('.se-file-prev');
        var docType = box.getAttribute('data-doc');

        btn.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            input.value = '';
            if (!f) return;
            if (ALLOWED.indexOf(f.type) === -1) { showError('docs', 'Tipo de archivo no permitido. Usa una foto (JPG/PNG) o un PDF.'); return; }
            if (f.size > MAX_BYTES) { showError('docs', 'El archivo "' + f.name + '" supera los 5 MB.'); return; }
            showError('docs', '');
            var reader = new FileReader();
            reader.onload = function () {
                var result = String(reader.result || '');
                var b64 = result.indexOf(',') !== -1 ? result.slice(result.indexOf(',') + 1) : result;
                files[docType] = { filename: f.name, mime: f.type, data: b64 };
                renderPreview(box, prev, btn, f);
            };
            reader.readAsDataURL(f);
        });
    });

    function renderPreview(box, prev, btn, f) {
        var isImg = f.type.indexOf('image/') === 0;
        var thumb = isImg ? '<img alt="">' : '<span class="se-file-pdf"><i data-lucide="file-text"></i></span>';
        prev.innerHTML = thumb +
            '<span class="se-file-name">' + esc(f.name) + '</span>' +
            '<button type="button" class="se-file-x" aria-label="Quitar"><i data-lucide="x"></i></button>';
        prev.hidden = false;
        box.classList.add('has-file');
        btn.innerHTML = '<i data-lucide="refresh-ccw"></i> Cambiar';
        if (isImg) {
            var img = prev.querySelector('img');
            var rd = new FileReader();
            rd.onload = function () { img.src = rd.result; };
            rd.readAsDataURL(f);
        }
        prev.querySelector('.se-file-x').addEventListener('click', function () {
            delete files[box.getAttribute('data-doc')];
            prev.hidden = true; prev.innerHTML = '';
            box.classList.remove('has-file');
            btn.innerHTML = '<i data-lucide="upload"></i> Subir';
            relucide();
        });
        relucide();
    }

    /* ---------- recolectar estudios ---------- */
    function collectItems() {
        var items = [];
        form.querySelectorAll('input[name="study"]:checked').forEach(function (c) {
            // Ignorar solo los de un GRUPO oculto (imágenes vs laboratorio según el
            // tipo elegido), NO los de la sección completa cuando se oculta al
            // navegar entre pasos (si no, al "Enviar" se perdían todos).
            var grp = c.closest('[data-se-group]');
            if (grp && grp.hidden) return;
            items.push({ category: c.getAttribute('data-cat') || 'imaging', name: c.value });
        });
        var other = (form.querySelector('#se-other') || {}).value || '';
        other.split(/[\n,;]+/).forEach(function (s) {
            s = s.trim();
            if (s) items.push({ category: studyType() === 'lab' ? 'lab' : 'imaging', name: s });
        });
        return items;
    }

    /* ---------- validación por paso ---------- */
    function validateStep() {
        var step = STEPS[idx];
        if (step === 'tipo') {
            if (!studyType()) { showError('tipo', 'Selecciona qué necesitas autorizar.'); return false; }
        }
        if (step === 'estudios') {
            if (!collectItems().length) { showError('estudios', 'Marca al menos un estudio o escríbelo en "Otros estudios".'); return false; }
        }
        if (step === 'seguro') {
            if (insurerSel && insurerSel.value === '') { /* recomendado, no bloqueante */ }
            if (insurerSel && insurerSel.value === '__other__') {
                var on = (form.querySelector('#se-insurer-name') || {}).value || '';
                if (!on.trim()) { showError('seguro', 'Escribe el nombre de tu aseguradora.'); return false; }
            }
        }
        if (step === 'datos') {
            var name = (form.querySelector('#se-name') || {}).value || '';
            var ced = ((form.querySelector('#se-cedula') || {}).value || '').replace(/\D/g, '');
            var phone = (form.querySelector('#se-phone') || {}).value || '';
            if (!name.trim()) { showError('datos', 'Escribe tu nombre completo.'); return false; }
            if (ced.length < 8) { showError('datos', 'Escribe una cédula válida.'); return false; }
            if (phone.replace(/\D/g, '').length < 7) { showError('datos', 'Escribe un teléfono válido.'); return false; }
        }
        if (step === 'docs') {
            if (!files.order) { showError('docs', 'Sube una foto o PDF de tu orden médica.'); return false; }
            var noIns = insurerSel && insurerSel.value === '__none__';
            if (!noIns && !files.insurance_front) { showError('docs', 'Sube el frente de tu carnet del seguro (o elige "Pagaré sin seguro").'); return false; }
        }
        showError(step, '');
        return true;
    }

    /* ---------- resumen ---------- */
    function insurerValue() {
        if (!insurerSel) return '';
        if (insurerSel.value === '__other__') return (form.querySelector('#se-insurer-name') || {}).value || '';
        if (insurerSel.value === '__none__') return '';
        return insurerSel.value;
    }
    function buildSummary() {
        var box = form.querySelector('[data-se-summary]');
        if (!box) return;
        var items = collectItems();
        var t = { imaging: 'Imágenes', lab: 'Laboratorio', both: 'Imágenes y laboratorio' }[studyType()] || '—';
        var ins = insurerValue() || (insurerSel && insurerSel.value === '__none__' ? 'Sin seguro' : 'No indicada');
        var docCount = Object.keys(files).length;
        var rows = [
            ['Tipo', t],
            ['Estudios', items.map(function (i) { return i.name; }).join(', ') || '—'],
            ['Seguro', ins],
            ['Documentos', docCount + ' archivo' + (docCount === 1 ? '' : 's')]
        ];
        box.innerHTML = '<h3 class="se-summary-title">Revisa tu solicitud</h3>' + rows.map(function (r) {
            return '<div class="se-summary-row"><span>' + esc(r[0]) + '</span><strong>' + esc(r[1]) + '</strong></div>';
        }).join('');
    }

    /* ---------- payload ---------- */
    function buildPayload() {
        var p = {
            study_type: studyType(),
            items: collectItems(),
            insurer: insurerValue() || null,
            insurer_member_id: ((form.querySelector('#se-member') || {}).value || '').trim() || null,
            insurer_plan: ((form.querySelector('#se-plan') || {}).value || '').trim() || null,
            referring_center: ((form.querySelector('#se-center') || {}).value || '').trim() || null,
            referring_doctor: ((form.querySelector('#se-rdoctor') || {}).value || '').trim() || null,
            notes: ((form.querySelector('#se-other') || {}).value || '').trim() || null,
            consent_contact: !!(form.querySelector('input[name="consent_contact"]:checked'))
        };
        if (insurerSel && insurerSel.value === '__none__') p.no_insurance = true;
        if (MODE === 'guest') {
            p.full_name = ((form.querySelector('#se-name') || {}).value || '').trim();
            p.cedula = ((form.querySelector('#se-cedula') || {}).value || '').trim();
            p.phone = ((form.querySelector('#se-phone') || {}).value || '').trim();
            p.email = ((form.querySelector('#se-email') || {}).value || '').trim() || null;
            var cap = document.querySelector('[name="h-captcha-response"]');
            if (cap && cap.value) p.captcha_token = cap.value;
        }
        return p;
    }

    /* ---------- llamadas ---------- */
    function proxy(method, path, body) {
        return fetch(CFG.proxyUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrfToken || '' },
            body: JSON.stringify({ method: method, path: path, body: method === 'GET' ? undefined : body })
        }).then(function (r) {
            return r.text().then(function (txt) {
                var j; try { j = JSON.parse(txt); } catch (e) { j = {}; }
                return { ok: r.ok && j && j.success, status: r.status, data: j && j.data, message: j && j.message };
            });
        });
    }

    function uploadDocs(reqId) {
        var keys = Object.keys(files);
        var failed = 0;
        return keys.reduce(function (chain, k) {
            return chain.then(function () {
                var f = files[k];
                return proxy('POST', '/portal/me/study-requests/' + reqId + '/documents', {
                    doc_type: k, filename: f.filename, mime: f.mime, data: f.data
                }).then(function (r) { if (!r.ok) failed++; }).catch(function () { failed++; });
            });
        }, Promise.resolve()).then(function () { return failed; });
    }

    /* ---------- envío ---------- */
    var submitBtn = document.getElementById('se-submit');
    var submitting = false;

    function setSubmitting(on) {
        submitting = on;
        if (!submitBtn) return;
        submitBtn.disabled = on;
        submitBtn.innerHTML = on
            ? '<span class="se-spin"></span> Enviando…'
            : '<i data-lucide="send"></i> Enviar solicitud';
        relucide();
    }

    function doSubmit() {
        if (submitting) return;
        if (!form.querySelector('input[name="consent_contact"]:checked')) {
            showError('submit', 'Debes aceptar para que podamos contactarte.'); return;
        }
        showError('submit', '');
        setSubmitting(true);
        var payload = buildPayload();

        if (MODE === 'guest') {
            fetch(CFG.guestSubmitUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (r) {
                return r.text().then(function (txt) { var j; try { j = JSON.parse(txt); } catch (e) { j = {}; } return { ok: r.ok && j && j.success, data: j && j.data, message: j && j.message }; });
            }).then(function (r) {
                if (!r.ok || !r.data) { throw new Error(r.message || 'No pudimos enviar tu solicitud.'); }
                var d = r.data;
                if (d.csrf_token) CFG.csrfToken = d.csrf_token;
                // Cédula ya registrada y sin sesión: la solicitud quedó creada, pero
                // debe iniciar sesión para subir documentos y dar seguimiento.
                if (d.has_account && !d.csrf_token) {
                    finish({ code: d.public_code, needLogin: true });
                    return;
                }
                uploadDocs(d.request_id).then(function (failed) {
                    finish({ code: d.public_code, loggedIn: true, failed: failed });
                });
            }).catch(function (err) {
                setSubmitting(false);
                showError('submit', err.message || 'Ocurrió un error. Intenta de nuevo.');
            });
        } else {
            proxy('POST', '/portal/me/study-requests', payload).then(function (r) {
                if (!r.ok || !r.data) { throw new Error(r.message || 'No pudimos crear tu solicitud.'); }
                var reqId = r.data.request_id;
                var code = r.data.public_code;
                uploadDocs(reqId).then(function (failed) {
                    finish({ code: code, loggedIn: true, failed: failed });
                });
            }).catch(function (err) {
                setSubmitting(false);
                showError('submit', err.message || 'Ocurrió un error. Intenta de nuevo.');
            });
        }
    }

    /* ---------- pantalla final ---------- */
    function finish(res) {
        setSubmitting(false);
        Object.keys(sections).forEach(function (k) { sections[k].hidden = true; });
        nextBtn.hidden = true; backBtn.hidden = true;
        if (progressEl) progressEl.innerHTML = '';
        var done = sections.done;
        if (!done) { window.location.href = CFG.portalHome; return; }
        done.hidden = false;

        var copy = done.querySelector('[data-se-done-copy]');
        var codeEl = done.querySelector('[data-se-done-code]');
        var actions = done.querySelector('[data-se-done-actions]');

        if (res.code && codeEl) {
            codeEl.hidden = false;
            codeEl.innerHTML = 'Código de tu solicitud<strong>' + esc(res.code) + '</strong>';
        }
        if (res.needLogin) {
            copy.textContent = 'Ya tienes una cuenta con nosotros. Inicia sesión para subir tus documentos y dar seguimiento a tu autorización.';
            actions.innerHTML = '<a class="btn btn-green" href="' + esc(CFG.loginUrl) + '"><i data-lucide="log-in"></i> Iniciar sesión</a>';
        } else {
            var warn = res.failed ? '<p class="se-done-warn"><i data-lucide="alert-triangle"></i> Algunos archivos no se subieron. Puedes volver a intentarlo desde tu portal.</p>' : '';
            copy.innerHTML = 'Nuestro equipo de seguros revisará tu orden y gestionará la autorización con tu ARS. ' +
                'Cuando esté lista verás aquí tu <strong>copago / restante a pagar</strong> y las indicaciones. ' +
                'También podríamos llamarte al número que indicaste.' + warn;
            actions.innerHTML = '<a class="btn btn-green" href="' + esc(CFG.portalHome) + '"><i data-lucide="list-checks"></i> Ver mis solicitudes</a>';
        }
        relucide();
    }

    /* ---------- navegación ---------- */
    nextBtn.addEventListener('click', function () { if (validateStep()) showStep(idx + 1); });
    backBtn.addEventListener('click', function () { showStep(idx - 1); });
    form.querySelectorAll('input[name="study_type"]').forEach(function (r) {
        r.addEventListener('change', function () { showError('tipo', ''); });
    });
    if (submitBtn) submitBtn.addEventListener('click', function (e) { e.preventDefault(); doSubmit(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); if (STEPS[idx] === 'confirm') doSubmit(); });

    showStep(0);
})();
