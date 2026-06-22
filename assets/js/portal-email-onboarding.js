/* Portal de Pacientes — Alta de correo (onboarding)
 * Modal de 2 pasos para que un paciente sin correo registrado agregue el suyo:
 *   1) escribe su correo  → le llega un código
 *   2) escribe el código   → su correo queda guardado
 * Llama a /api/portal-proxy.php (mismo origen); el token vive en la sesión PHP.
 * Sin términos técnicos en la interfaz.
 */
(function () {
    'use strict';

    var dialog = document.getElementById('portal-email-dialog');
    if (!dialog) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var proxyUrl = document.querySelector('meta[name="portal-api-url"]')?.content || '/api/portal-proxy.php';
    var cfg = window.HGLC_ONBOARD || {};
    var pendingEmail = '';

    function proxy(path, body) {
        return fetch(proxyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ method: 'POST', path: path, body: body || {} })
        }).then(function (r) {
            return r.text().then(function (t) {
                var j; try { j = JSON.parse(t); } catch (e) { j = { success: false, message: 'Respuesta inválida.' }; }
                return { ok: r.ok && j.success, status: r.status, data: j.data, message: j.message, errors: j.errors };
            });
        });
    }

    function icons() { if (window.lucide) window.lucide.createIcons(); }

    function step(name) {
        dialog.querySelectorAll('.portal-onboard-step').forEach(function (s) {
            s.hidden = s.getAttribute('data-step') !== name;
        });
        var focusEl = dialog.querySelector('.portal-onboard-step:not([hidden]) input');
        if (focusEl) { try { focusEl.focus(); } catch (e) {} }
    }

    function setError(formName, msg) {
        var box = dialog.querySelector('.portal-onboard-step[data-step="' + formName + '"] [data-error]');
        if (!box) return;
        if (msg) { box.textContent = msg; box.hidden = false; }
        else { box.textContent = ''; box.hidden = true; }
    }

    function loading(btn, on, label) {
        if (!btn) return;
        if (on) {
            btn.dataset.html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-circle" class="animate-spin"></i><span>' + (label || 'Procesando…') + '</span>';
        } else {
            btn.disabled = false;
            if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
        }
        icons();
    }

    function open(initial) {
        step(initial || 'email');
        if (!dialog.open) { try { dialog.showModal(); } catch (e) { dialog.setAttribute('open', ''); } }
        icons();
    }

    // ── Paso 1: enviar el código ─────────────────────────────────────────────
    var emailForm = dialog.querySelector('form[data-form="email"]');
    var e1 = dialog.querySelector('#onb-email');
    var e2 = dialog.querySelector('#onb-email2');

    function startSend(btn) {
        var email = (e1.value || '').trim().toLowerCase();
        var email2 = (e2.value || '').trim().toLowerCase();
        setError('email', '');
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(email)) { setError('email', 'Escribe un correo electrónico válido.'); e1.focus(); return; }
        if (email !== email2) { setError('email', 'Los dos correos no coinciden. Revísalos.'); e2.focus(); return; }
        loading(btn, true, 'Enviando…');
        proxy('/portal/me/email-start', { email: email }).then(function (r) {
            loading(btn, false);
            if (!r.ok) { setError('email', r.message || 'No pudimos enviar el código. Intenta de nuevo.'); return; }
            pendingEmail = email;
            var masked = (r.data && r.data.email_masked) || email;
            var m = dialog.querySelector('[data-masked]');
            if (m) m.textContent = masked;
            var codeInput = dialog.querySelector('#onb-code');
            if (codeInput) codeInput.value = '';
            setError('code', '');
            step('code');
        }).catch(function () {
            loading(btn, false);
            setError('email', 'No hay conexión en este momento. Intenta de nuevo.');
        });
    }

    if (emailForm) {
        emailForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            startSend(emailForm.querySelector('button[type="submit"]'));
        });
    }

    // ── Paso 2: confirmar el código ──────────────────────────────────────────
    var codeForm = dialog.querySelector('form[data-form="code"]');
    var codeInput = dialog.querySelector('#onb-code');
    if (codeInput) {
        codeInput.addEventListener('input', function () {
            codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    if (codeForm) {
        codeForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var btn = codeForm.querySelector('button[type="submit"]');
            var code = (codeInput.value || '').replace(/\D/g, '');
            setError('code', '');
            if (code.length !== 6) { setError('code', 'Escribe el código de 6 dígitos.'); return; }
            loading(btn, true, 'Confirmando…');
            proxy('/portal/me/email-confirm', { code: code }).then(function (r) {
                loading(btn, false);
                if (!r.ok) {
                    if (r.status === 409 && r.errors && r.errors.no_pending) {
                        setError('code', 'El código venció. Pide uno nuevo.');
                    } else {
                        setError('code', r.message || 'No pudimos confirmar el código.');
                    }
                    return;
                }
                step('done');
                icons();
            }).catch(function () {
                loading(btn, false);
                setError('code', 'No hay conexión en este momento. Intenta de nuevo.');
            });
        });
    }

    // Reenviar / usar otro correo / terminar
    dialog.addEventListener('click', function (ev) {
        var act = ev.target.closest('[data-action]');
        if (!act) return;
        var action = act.getAttribute('data-action');
        if (action === 'resend') {
            if (!pendingEmail) { step('email'); return; }
            setError('code', '');
            loading(act, true, 'Reenviando…');
            proxy('/portal/me/email-start', { email: pendingEmail }).then(function (r) {
                loading(act, false);
                if (!r.ok) { setError('code', r.message || 'No pudimos reenviar el código.'); return; }
                setError('code', '');
                var m = dialog.querySelector('[data-masked]');
                if (r.data && r.data.email_masked && m) m.textContent = r.data.email_masked;
            }).catch(function () { loading(act, false); setError('code', 'No hay conexión.'); });
        } else if (action === 'change') {
            setError('email', '');
            step('email');
        } else if (action === 'finish') {
            try { dialog.close(); } catch (e) {}
            window.location.reload();
        }
    });

    // Si se cierra tras confirmar (paso "done" visible), recargar para reflejar el correo.
    dialog.addEventListener('close', function () {
        var done = dialog.querySelector('.portal-onboard-step[data-step="done"]');
        if (done && !done.hidden) window.location.reload();
    });

    // Disparadores: botón manual o auto en el inicio (una vez por sesión).
    document.querySelectorAll('[data-open-email-onboarding]').forEach(function (b) {
        b.addEventListener('click', function (ev) { ev.preventDefault(); open('email'); });
    });

    try {
        var dismissed = sessionStorage.getItem('hglc-onboard-seen') === '1';
        if (!dismissed && cfg.page === 'dashboard') {
            sessionStorage.setItem('hglc-onboard-seen', '1');
            window.setTimeout(function () { open('email'); }, 650);
        }
    } catch (e) {}
})();
