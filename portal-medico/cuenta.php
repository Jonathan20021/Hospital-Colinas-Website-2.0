<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$doctor = doctor_current() ?? [];
$dName  = (string)($doctor['name'] ?? '');
[$avc1, $avc2] = doctor_avatar_palette($dName);
$avInitials = doctor_initials($dName);

// Cargar actividad de inicio de sesión
$actRes = portal_api_call('GET', '/portal-doctor/me/login-activity', [], doctor_token());
$recentLogins   = $actRes['data']['recent'] ?? [];
$trustedDevices = $actRes['data']['trusted_devices'] ?? [];

// Perfil del médico: precargar su "Trayectoria profesional" editable (campo biography).
// Si está vacío, caemos a education para no perder lo que el hospital haya cargado.
$meRes      = portal_api_call('GET', '/portal-doctor/me', [], doctor_token());
$meData     = $meRes['data'] ?? [];
$currentBio = trim((string)($meData['biography'] ?? ''));
if ($currentBio === '') {
    $currentBio = trim((string)($meData['education'] ?? ''));
}
$mySlug = trim((string)($meData['slug'] ?? ''));

function activity_label(string $reason, bool $success): string {
    if ($success) {
        return match ($reason) {
            'trusted_device' => 'Inicio de sesión (dispositivo confiable)',
            '2fa_ok'         => 'Inicio de sesión con 2FA',
            default          => 'Inicio de sesión',
        };
    }
    return match ($reason) {
        'rate_limited'    => 'Bloqueado por demasiados intentos',
        'bad_credentials' => 'Credenciales incorrectas',
        'locked'          => 'Cuenta bloqueada temporalmente',
        '2fa_bad_code'    => 'Código 2FA incorrecto',
        '2fa_bad_creds'   => 'Credenciales inválidas en 2FA',
        'awaiting_2fa'    => 'Pendiente de código 2FA',
        'inactive'        => 'Cuenta inactiva',
        default           => 'Intento fallido',
    };
}

doctor_layout_begin('Mi cuenta', 'cuenta');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Mi cuenta</p>
        <h1>Seguridad de tu cuenta</h1>
        <p class="doctor-subtitle">Edita tu trayectoria profesional —la que aparece en tu perfil del directorio médico— y gestiona la seguridad de tu cuenta. Tu nombre, especialidad y correo los administra el hospital.</p>
    </div>
</header>

<div class="doctor-grid-2" data-reveal data-reveal-d="1">
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="user" class="h-4 w-4"></i> Tu cuenta</h2>
        </header>
        <div class="doctor-account-summary">
            <div class="doctor-av doctor-av-lg" style="background: linear-gradient(135deg, <?= e($avc1) ?>, <?= e($avc2) ?>);"><?= e($avInitials) ?></div>
            <div class="doctor-account-info">
                <p class="doctor-account-name"><?= e($doctor['name'] ?? '') ?></p>
                <p class="doctor-account-row"><i data-lucide="mail" class="h-3.5 w-3.5"></i> <?= e($doctor['email'] ?? '') ?></p>
                <?php if (!empty($doctor['specialty'])): ?>
                    <p class="doctor-account-row"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($doctor['specialty']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="doctor-account-note">
            <i data-lucide="info" class="h-4 w-4"></i>
            <p>Tu nombre, especialidad y correo los administra el hospital. Tu <strong>trayectoria profesional</strong> la editas tú mismo más abajo.</p>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="key-round" class="h-4 w-4"></i> Cambiar contraseña</h2>
        </header>
        <form id="pwd-form" class="doctor-form-pad">
            <label class="doctor-label" for="current_password">Contraseña actual</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock" class="h-4 w-4"></i>
                <input type="password" name="current_password" id="current_password" class="doctor-input" required autocomplete="current-password" placeholder="********">
                <button type="button" class="doctor-input-toggle" data-toggle="#current_password" aria-label="Mostrar/ocultar"><i data-lucide="eye" class="h-4 w-4"></i></button>
            </div>

            <label class="doctor-label mt-4" for="new_password">Nueva contraseña</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                <input type="password" name="new_password" id="new_password" class="doctor-input" required minlength="8" autocomplete="new-password" placeholder="mínimo 8 caracteres">
                <button type="button" class="doctor-input-toggle" data-toggle="#new_password" aria-label="Mostrar/ocultar"><i data-lucide="eye" class="h-4 w-4"></i></button>
            </div>

            <label class="doctor-label mt-4" for="new_password_confirm">Confirmar nueva contraseña</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                <input type="password" name="new_password_confirm" id="new_password_confirm" class="doctor-input" required minlength="8" autocomplete="new-password" placeholder="repite tu nueva contraseña">
            </div>

            <p id="pwd-status" class="doctor-save-status mt-4"></p>

            <button type="submit" class="doctor-btn doctor-btn-primary mt-4">
                <i data-lucide="save" class="h-4 w-4"></i> Actualizar contraseña
            </button>
        </form>
    </div>
</div>

<div class="doctor-card mt-6" data-reveal data-reveal-d="2">
    <header class="doctor-card-header">
        <h2><i data-lucide="briefcase-medical" class="h-4 w-4"></i> Mi perfil profesional</h2>
    </header>
    <div class="doctor-form-pad">
        <p class="doctor-subtitle" style="margin-top:0">Esta es tu <strong>Trayectoria profesional</strong>, tal como aparece en tu perfil del directorio médico público. Incluye tu formación, experiencia y áreas de interés. Se publica de inmediato.</p>
        <form id="bio-form">
            <label class="doctor-label" for="bio">Trayectoria profesional</label>
            <textarea id="bio" name="biography" class="doctor-input" rows="7" maxlength="4000"
                placeholder="Ej.: Médico especialista en [especialidad], egresado de [universidad], con formación en [subespecialidad] y X años de experiencia en…"><?= e($currentBio) ?></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-top:6px;flex-wrap:wrap">
                <span class="doctor-subtitle" style="font-size:.82rem;margin:0"><span id="bio-count">0</span>/4000 caracteres</span>
                <?php if ($mySlug !== ''): ?>
                    <a href="<?= e(base_url('medico/' . $mySlug)) ?>" target="_blank" rel="noopener" style="font-size:.85rem;font-weight:600;color:#047857">Ver mi perfil público ↗</a>
                <?php endif; ?>
            </div>
            <p id="bio-status" class="doctor-save-status mt-4"></p>
            <button type="submit" class="doctor-btn doctor-btn-primary mt-2">
                <i data-lucide="save" class="h-4 w-4"></i> Guardar trayectoria
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('bio-form');
    if (!form) return;
    const ta = document.getElementById('bio');
    const count = document.getElementById('bio-count');
    const statusEl = document.getElementById('bio-status');
    const btn = form.querySelector('button[type=submit]');
    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }
    function upd() { count.textContent = ta.value.length; }
    ta.addEventListener('input', upd); upd();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        btn.disabled = true;
        if (window.doctorAutoSaveHint) window.doctorAutoSaveHint(statusEl, 'saving');
        else { statusEl.textContent = '· Guardando...'; statusEl.className = 'doctor-save-status doctor-save-saving'; }
        whenApi(async function () {
            try {
                const r = await window.doctorApi('PUT', '/portal-doctor/me/profile', { biography: ta.value });
                if (r && r.ok) {
                    statusEl.textContent = '✓ Trayectoria actualizada. Aparecerá en tu perfil público en breve.';
                    statusEl.className = 'doctor-save-status doctor-save-saved';
                } else {
                    statusEl.textContent = '⚠ ' + ((r && r.message) || 'No se pudo guardar.');
                    statusEl.className = 'doctor-save-status doctor-save-error';
                }
            } catch (err) {
                statusEl.textContent = '⚠ Error de conexión. Intenta de nuevo.';
                statusEl.className = 'doctor-save-status doctor-save-error';
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
</script>

<div class="doctor-card mt-6" data-reveal data-reveal-d="3">
    <header class="doctor-card-header">
        <h2><i data-lucide="pen-tool" class="h-4 w-4"></i> Mi firma</h2>
    </header>
    <div class="doctor-form-pad">
        <p class="doctor-subtitle" style="margin-top:0">Tu firma aparece en las recetas y documentos clínicos que emites, junto a tu exequátur y colegiatura CMD.</p>
        <p id="sig-current" class="doctor-save-status" style="margin:0 0 10px">Comprobando…</p>

        <div id="sig-preview-wrap" hidden style="margin:0 0 18px">
            <p class="doctor-label" style="margin:0 0 6px">Firma actual</p>
            <div style="display:inline-block;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:12px 16px;max-width:100%">
                <img id="sig-preview" alt="Firma actual" style="display:block;max-width:340px;max-height:130px;width:auto;height:auto">
            </div>
            <p class="doctor-subtitle" style="margin:8px 0 0;font-size:.85rem">Así se ve la firma que el hospital cargó por ti. Para cambiarla, dibuja o sube una nueva abajo y pulsa «Guardar firma».</p>
        </div>

        <p class="doctor-label" style="margin:0 0 6px">Dibujar o subir una firma nueva</p>
        <p class="doctor-subtitle" style="margin-top:0;font-size:.85rem">Dibújala con el mouse o el dedo, o sube una imagen (PNG/JPEG).</p>
        <canvas id="sig-pad" width="600" height="180" style="display:block;width:100%;max-width:600px;height:180px;border:1px dashed #cbd5e1;border-radius:12px;background:#fff;touch-action:none;cursor:crosshair"></canvas>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center">
            <button type="button" class="doctor-btn doctor-btn-ghost" id="sig-clear"><i data-lucide="eraser" class="h-4 w-4"></i> Limpiar</button>
            <label class="doctor-btn doctor-btn-outline" style="cursor:pointer;margin:0"><i data-lucide="upload" class="h-4 w-4"></i> Subir imagen
                <input type="file" id="sig-upload" accept="image/png,image/jpeg" hidden>
            </label>
            <button type="button" class="doctor-btn doctor-btn-primary" id="sig-save"><i data-lucide="save" class="h-4 w-4"></i> Guardar firma</button>
            <button type="button" class="doctor-btn doctor-btn-ghost" id="sig-delete" hidden><i data-lucide="trash-2" class="h-4 w-4"></i> Eliminar firma</button>
        </div>
        <p id="sig-status" class="doctor-save-status" style="margin-top:8px"></p>
    </div>
</div>

<script>
(function () {
    const canvas = document.getElementById('sig-pad');
    if (!canvas) return;
    // portal-medico.js (que define window.doctorApi) puede cargar DESPUÉS de este
    // script en línea. El DIBUJO no depende de la API → se engancha ya mismo; las
    // llamadas a la API (estado/guardar/eliminar) esperan a que doctorApi exista.
    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111827';
    let drawing = false, dirty = false, last = null, uploaded = null;

    function pos(e) {
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
    }
    function start(e) { drawing = true; uploaded = null; last = pos(e); e.preventDefault(); }
    function move(e) { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; dirty = true; e.preventDefault(); }
    function end() { drawing = false; }
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); canvas.addEventListener('touchend', end);

    const statusEl = document.getElementById('sig-status');
    const curEl = document.getElementById('sig-current');
    const delBtn = document.getElementById('sig-delete');

    function clearPad() { ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; uploaded = null; }
    document.getElementById('sig-clear').addEventListener('click', clearPad);

    document.getElementById('sig-upload').addEventListener('change', (ev) => {
        const f = ev.target.files[0]; if (!f) return;
        if (!/^image\/(png|jpeg)$/.test(f.type)) { statusEl.textContent = '⚠ Solo PNG o JPEG.'; return; }
        const rd = new FileReader();
        rd.onload = () => {
            uploaded = rd.result;
            const img = new Image();
            img.onload = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const r = Math.min(canvas.width / img.width, canvas.height / img.height);
                const w = img.width * r, h = img.height * r;
                ctx.drawImage(img, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
                dirty = true;
            };
            img.src = rd.result;
        };
        rd.readAsDataURL(f);
    });

    const previewWrap = document.getElementById('sig-preview-wrap');
    const previewImg  = document.getElementById('sig-preview');

    async function refresh() {
        try {
            const r = await window.doctorApi('GET', '/portal-doctor/me/signature');
            const has = !!(r.ok && r.data && r.data.has_signature && r.data.image);
            if (has) {
                previewImg.src = r.data.image;
                previewWrap.hidden = false;
                curEl.textContent = '✓ Tienes una firma registrada.';
            } else {
                previewImg.removeAttribute('src');
                previewWrap.hidden = true;
                curEl.textContent = 'Aún no has registrado tu firma (las recetas saldrán con una línea para firmar a mano).';
            }
            delBtn.hidden = !has;
        } catch (e) {}
    }
    whenApi(refresh);

    document.getElementById('sig-save').addEventListener('click', async () => {
        if (!dirty) { statusEl.textContent = 'Dibuja o sube tu firma primero.'; return; }
        if (!window.doctorApi) { statusEl.textContent = 'Cargando… intenta de nuevo en un instante.'; return; }
        const dataUri = uploaded || canvas.toDataURL('image/png');
        statusEl.textContent = 'Guardando…';
        const r = await window.doctorApi('POST', '/portal-doctor/me/signature', { image: dataUri });
        statusEl.textContent = r.ok ? '✓ Firma guardada.' : ('⚠ ' + (r.message || 'No se pudo guardar.'));
        if (r.ok) { uploaded = null; refresh(); }
    });

    delBtn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar tu firma registrada?')) return;
        if (!window.doctorApi) { statusEl.textContent = 'Cargando… intenta de nuevo en un instante.'; return; }
        const r = await window.doctorApi('DELETE', '/portal-doctor/me/signature');
        statusEl.textContent = r.ok ? '✓ Firma eliminada.' : '⚠ Error al eliminar.';
        if (r.ok) { clearPad(); refresh(); }
    });

    if (window.lucide) lucide.createIcons();
})();
</script>

<div class="doctor-card mt-6" data-reveal data-reveal-d="4">
    <header class="doctor-card-header">
        <h2><i data-lucide="image" class="h-4 w-4"></i> Mi membrete / logo</h2>
    </header>
    <div class="doctor-form-pad">
        <p class="doctor-subtitle" style="margin-top:0">Sube el logo o membrete de tu consultorio. Aparece en el encabezado de los documentos que redactes en el <strong>editor de documentos</strong>, junto al del hospital. Si no subes ninguno, se usa solo el membrete de HGLC.</p>
        <p id="lh-current" class="doctor-save-status" style="margin:0 0 10px">Comprobando…</p>

        <div id="lh-preview-wrap" hidden style="margin:0 0 18px">
            <p class="doctor-label" style="margin:0 0 6px">Logo actual</p>
            <div style="display:inline-block;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:12px 16px;max-width:100%">
                <img id="lh-preview" alt="Logo actual" style="display:block;max-width:340px;max-height:120px;width:auto;height:auto">
            </div>
        </div>

        <p class="doctor-label" style="margin:0 0 6px">Subir un logo nuevo</p>
        <p class="doctor-subtitle" style="margin-top:0;font-size:.85rem">PNG o JPG, preferiblemente horizontal y con fondo transparente o blanco (máx. ~2 MB).</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px">
            <label class="doctor-btn doctor-btn-outline" style="cursor:pointer;margin:0"><i data-lucide="upload" class="h-4 w-4"></i> Elegir imagen
                <input type="file" id="lh-upload" accept="image/png,image/jpeg,image/webp" hidden>
            </label>
            <button type="button" class="doctor-btn doctor-btn-primary" id="lh-save" disabled><i data-lucide="save" class="h-4 w-4"></i> Guardar logo</button>
            <button type="button" class="doctor-btn doctor-btn-ghost" id="lh-delete" hidden><i data-lucide="trash-2" class="h-4 w-4"></i> Eliminar logo</button>
        </div>
        <div id="lh-new-wrap" hidden style="margin-top:14px">
            <p class="doctor-label" style="margin:0 0 6px">Vista previa del nuevo logo</p>
            <div style="display:inline-block;border:1px dashed #cbd5e1;border-radius:12px;background:#fff;padding:12px 16px;max-width:100%">
                <img id="lh-new" alt="Nuevo logo" style="display:block;max-width:340px;max-height:120px;width:auto;height:auto">
            </div>
        </div>
        <p id="lh-status" class="doctor-save-status" style="margin-top:8px"></p>
    </div>
</div>

<script>
(function () {
    var upload = document.getElementById('lh-upload');
    if (!upload) return;
    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }
    var statusEl = document.getElementById('lh-status');
    var curEl = document.getElementById('lh-current');
    var previewWrap = document.getElementById('lh-preview-wrap');
    var previewImg = document.getElementById('lh-preview');
    var newWrap = document.getElementById('lh-new-wrap');
    var newImg = document.getElementById('lh-new');
    var saveBtn = document.getElementById('lh-save');
    var delBtn = document.getElementById('lh-delete');
    var pending = null;

    function refresh() {
        window.doctorApi('GET', '/portal-doctor/me/letterhead').then(function (r) {
            var has = !!(r.ok && r.data && r.data.has_logo && r.data.logo);
            if (has) {
                previewImg.src = r.data.logo; previewWrap.hidden = false;
                curEl.textContent = '✓ Tienes un logo cargado.';
            } else {
                previewImg.removeAttribute('src'); previewWrap.hidden = true;
                curEl.textContent = 'Aún no has cargado un logo. Los documentos saldrán solo con el membrete de HGLC.';
            }
            delBtn.hidden = !has;
        }).catch(function () {});
    }
    whenApi(refresh);

    upload.addEventListener('change', function (ev) {
        var f = ev.target.files[0]; if (!f) return;
        if (!/^image\/(png|jpeg|webp)$/.test(f.type)) { statusEl.textContent = '⚠ Solo PNG, JPG o WEBP.'; return; }
        if (f.size > 2 * 1024 * 1024) { statusEl.textContent = '⚠ La imagen supera los 2 MB.'; return; }
        var rd = new FileReader();
        rd.onload = function () { pending = rd.result; newImg.src = pending; newWrap.hidden = false; saveBtn.disabled = false; statusEl.textContent = ''; };
        rd.readAsDataURL(f);
    });

    saveBtn.addEventListener('click', function () {
        if (!pending) return;
        saveBtn.disabled = true; statusEl.textContent = 'Guardando…';
        window.doctorApi('POST', '/portal-doctor/me/letterhead', { image: pending }).then(function (r) {
            statusEl.textContent = r.ok ? '✓ Logo guardado.' : ('⚠ ' + (r.message || 'No se pudo guardar.'));
            if (r.ok) { pending = null; newWrap.hidden = true; upload.value = ''; refresh(); }
            else saveBtn.disabled = false;
        });
    });

    delBtn.addEventListener('click', function () {
        if (!confirm('¿Eliminar tu logo? Los documentos volverán a usar solo el membrete de HGLC.')) return;
        window.doctorApi('DELETE', '/portal-doctor/me/letterhead').then(function (r) {
            statusEl.textContent = r.ok ? '✓ Logo eliminado.' : '⚠ Error al eliminar.';
            if (r.ok) refresh();
        });
    });
})();
</script>

<div class="doctor-grid-2 mt-6" data-reveal>
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="history" class="h-4 w-4"></i> Actividad reciente</h2>
        </header>
        <?php if (!$recentLogins): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration"><i data-lucide="clock" class="h-7 w-7"></i></div>
                <p class="doctor-empty-title">Sin actividad registrada</p>
                <p>Cuando inicies sesión, los accesos aparecerán aquí.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-activity-list">
                <?php foreach (array_slice($recentLogins, 0, 8) as $a):
                    $ok = (int)$a['success'] === 1;
                    $ts = strtotime($a['attempted_at']);
                ?>
                    <li class="doctor-activity-row <?= $ok ? 'doctor-activity-success' : 'doctor-activity-failed' ?>">
                        <span class="doctor-activity-icon">
                            <i data-lucide="<?= $ok ? 'check' : 'x' ?>"></i>
                        </span>
                        <div class="doctor-activity-meta">
                            <p class="doctor-activity-title"><?= e(activity_label((string)($a['reason'] ?? ''), $ok)) ?></p>
                            <p class="doctor-activity-sub">
                                <i data-lucide="map-pin" class="h-3 w-3 inline-block align-text-bottom"></i> <?= e($a['ip_address'] ?: '—') ?>
                            </p>
                        </div>
                        <span class="doctor-activity-when"><?= e(doctor_fecha_corta($ts, true)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="laptop" class="h-4 w-4"></i> Dispositivos confiables</h2>
        </header>
        <?php if (!$trustedDevices): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration"><i data-lucide="smartphone" class="h-7 w-7"></i></div>
                <p class="doctor-empty-title">Ningún dispositivo confiable</p>
                <p>Cuando inicies sesión y marques "confiar en este dispositivo", aparecerán aquí.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-activity-list">
                <?php foreach ($trustedDevices as $d):
                    $ts = strtotime($d['created_at']);
                    $expTs = strtotime($d['expires_at']);
                ?>
                    <li class="doctor-device-row">
                        <span class="doctor-device-icon"><i data-lucide="monitor-smartphone" class="h-5 w-5"></i></span>
                        <div>
                            <p class="doctor-device-label"><?= e($d['device_label'] ?: 'Dispositivo') ?></p>
                            <p class="doctor-device-meta">
                                <?= e($d['ip_address'] ?? '—') ?>
                                · Agregado <?= e(doctor_fecha_corta($ts)) ?>
                                · Vence <?= e(doctor_fecha_corta($expTs)) ?>
                            </p>
                        </div>
                        <button type="button" class="doctor-device-revoke" data-revoke-id="<?= (int)$d['id'] ?>">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Revocar
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="doctor-card mt-6" data-push-card hidden>
    <header class="doctor-card-header">
        <h2><i data-lucide="bell" class="h-4 w-4"></i> Notificaciones push</h2>
    </header>
    <div class="doctor-form-pad">
        <p class="doctor-subtitle" style="margin-top:0;margin-bottom:16px">Recibe avisos de nuevas citas, cancelaciones y un resumen diario de tu agenda, aun con el portal cerrado. La activación aplica a este dispositivo.</p>
        <div class="doctor-push-row">
            <div class="doctor-push-state">
                <span class="doctor-push-dot" data-push-dot></span>
                <span data-push-status>Comprobando…</span>
            </div>
            <div class="doctor-push-actions">
                <button type="button" class="doctor-btn doctor-btn-primary" data-push-enable hidden><i data-lucide="bell" class="h-4 w-4"></i> Activar</button>
                <button type="button" class="doctor-btn doctor-btn-outline" data-push-disable hidden><i data-lucide="bell-off" class="h-4 w-4"></i> Desactivar</button>
                <button type="button" class="doctor-btn doctor-btn-ghost" data-push-test hidden><i data-lucide="send" class="h-4 w-4"></i> Probar</button>
            </div>
        </div>
        <p class="doctor-push-hint" data-push-hint hidden></p>
    </div>
</div>

<div class="doctor-card mt-6 doctor-card-warning">
    <header class="doctor-card-header">
        <h2><i data-lucide="shield-alert" class="h-4 w-4"></i> Consejos de seguridad</h2>
    </header>
    <ul class="doctor-tips">
        <li><i data-lucide="check" class="h-4 w-4"></i> Usa al menos 8 caracteres combinando letras, números y símbolos.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> No reutilices la contraseña que usas en otros sitios.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> No la compartas por correo, WhatsApp ni la pongas en notas visibles.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> Cierra sesión al terminar, sobre todo en computadoras compartidas.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> Si ves una sesión sospechosa en la lista de arriba, cambia tu contraseña de inmediato.</li>
    </ul>
</div>

<script>
document.querySelectorAll('[data-revoke-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('¿Revocar este dispositivo? La próxima vez tendrá que verificar con código.')) return;
        const r = await window.doctorApi('DELETE', '/portal-doctor/me/trusted-devices/' + btn.dataset.revokeId);
        if (r.ok) btn.closest('li').remove();
        else alert(r.message || 'Error al revocar.');
    });
});
</script>

<script>
document.getElementById('pwd-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const current = fd.get('current_password');
    const next    = fd.get('new_password');
    const conf    = fd.get('new_password_confirm');
    const status  = document.getElementById('pwd-status');

    if (next !== conf) {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ Las contraseñas nuevas no coinciden.';
        return;
    }
    if (next.length < 8) {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ Mínimo 8 caracteres.';
        return;
    }
    if (current === next) {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ La nueva contraseña debe ser distinta.';
        return;
    }

    window.doctorAutoSaveHint(status, 'saving');
    const r = await window.doctorApi('PUT', '/portal-doctor/me/password', {
        current_password: current,
        new_password: next,
    });
    if (r.ok) {
        window.doctorAutoSaveHint(status, 'saved');
        status.textContent = '✓ Contraseña actualizada correctamente.';
        e.target.reset();
    } else {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ ' + (r.message || 'Error al cambiar la contraseña.');
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const card = document.querySelector('[data-push-card]');
    if (!card || !window.DMPush || !window.DMPush.supported) return; // navegador sin soporte: tarjeta oculta
    card.hidden = false;
    const q = (s) => card.querySelector(s);
    const statusEl = q('[data-push-status]'), dot = q('[data-push-dot]');
    const enableBtn = q('[data-push-enable]'), disableBtn = q('[data-push-disable]');
    const testBtn = q('[data-push-test]'), hint = q('[data-push-hint]');

    function refresh(st) {
        const denied = st.permission === 'denied';
        enableBtn.hidden  = st.subscribed || denied;
        disableBtn.hidden = !st.subscribed;
        testBtn.hidden    = !st.subscribed;
        dot.className = 'doctor-push-dot' + (st.subscribed ? ' on' : '');
        statusEl.textContent = st.subscribed
            ? 'Activadas en este dispositivo'
            : (denied ? 'Bloqueadas en el navegador' : 'Desactivadas en este dispositivo');
        if (denied) { hint.hidden = false; hint.textContent = 'Las notificaciones están bloqueadas para este sitio en los ajustes del navegador. Habilítalas ahí para poder activarlas.'; }
        if (window.lucide) lucide.createIcons();
    }

    try { refresh(await window.DMPush.status()); } catch (e) {}

    enableBtn.addEventListener('click', async () => {
        enableBtn.disabled = true; hint.hidden = true;
        try { await window.DMPush.enable(); }
        catch (e) { hint.hidden = false; hint.textContent = e && e.message === 'denied' ? 'Permiso denegado en el navegador.' : 'No se pudieron activar. Intenta de nuevo.'; }
        enableBtn.disabled = false; refresh(await window.DMPush.status());
    });
    disableBtn.addEventListener('click', async () => {
        disableBtn.disabled = true; hint.hidden = true;
        try { await window.DMPush.disable(); } catch (e) {}
        disableBtn.disabled = false; refresh(await window.DMPush.status());
    });
    testBtn.addEventListener('click', async () => {
        testBtn.disabled = true; hint.hidden = true;
        let r = null; try { r = await window.DMPush.test(); } catch (e) {}
        testBtn.disabled = false; hint.hidden = false;
        hint.textContent = (r && r.ok)
            ? ('Notificación de prueba enviada (' + ((r.data && r.data.sent) || 0) + ' dispositivo[s]).')
            : 'No se pudo enviar la prueba.';
    });
});
</script>
<?php doctor_layout_end();
