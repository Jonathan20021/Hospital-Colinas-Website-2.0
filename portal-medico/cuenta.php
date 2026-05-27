<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$doctor = doctor_current() ?? [];
$dName  = (string)($doctor['name'] ?? '');
[$avc1, $avc2] = doctor_avatar_palette($dName);
$avInitials = doctor_initials($dName);

doctor_layout_begin('Mi cuenta', 'cuenta');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Mi cuenta</p>
        <h1>Seguridad de tu cuenta</h1>
        <p class="doctor-subtitle">Tu informacion profesional es administrada por el hospital. Aqui solo puedes gestionar tu contrasena de acceso.</p>
    </div>
</header>

<div class="doctor-grid-2">
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
            <p>Para cambiar tu nombre, especialidad o cualquier otro dato profesional, contacta al administrador del hospital.</p>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="key-round" class="h-4 w-4"></i> Cambiar contrasena</h2>
        </header>
        <form id="pwd-form" class="doctor-form-pad">
            <label class="doctor-label" for="current_password">Contrasena actual</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock" class="h-4 w-4"></i>
                <input type="password" name="current_password" id="current_password" class="doctor-input" required autocomplete="current-password" placeholder="********">
                <button type="button" class="doctor-input-toggle" data-toggle="#current_password" aria-label="Mostrar/ocultar"><i data-lucide="eye" class="h-4 w-4"></i></button>
            </div>

            <label class="doctor-label mt-4" for="new_password">Nueva contrasena</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                <input type="password" name="new_password" id="new_password" class="doctor-input" required minlength="8" autocomplete="new-password" placeholder="minimo 8 caracteres">
                <button type="button" class="doctor-input-toggle" data-toggle="#new_password" aria-label="Mostrar/ocultar"><i data-lucide="eye" class="h-4 w-4"></i></button>
            </div>

            <label class="doctor-label mt-4" for="new_password_confirm">Confirmar nueva contrasena</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                <input type="password" name="new_password_confirm" id="new_password_confirm" class="doctor-input" required minlength="8" autocomplete="new-password" placeholder="repite tu nueva contrasena">
            </div>

            <p id="pwd-status" class="doctor-save-status mt-4"></p>

            <button type="submit" class="doctor-btn doctor-btn-primary mt-4">
                <i data-lucide="save" class="h-4 w-4"></i> Actualizar contrasena
            </button>
        </form>
    </div>
</div>

<div class="doctor-card mt-6 doctor-card-warning">
    <header class="doctor-card-header">
        <h2><i data-lucide="shield-alert" class="h-4 w-4"></i> Consejos de seguridad</h2>
    </header>
    <ul class="doctor-tips">
        <li><i data-lucide="check" class="h-4 w-4"></i> Usa al menos 8 caracteres combinando letras, numeros y simbolos.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> No reutilices la contrasena que usas en otros sitios.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> No la compartas por correo, WhatsApp ni la pongas en notas visibles.</li>
        <li><i data-lucide="check" class="h-4 w-4"></i> Cierra sesion al terminar, sobre todo en computadoras compartidas.</li>
    </ul>
</div>

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
        status.textContent = '⚠ Las contrasenas nuevas no coinciden.';
        return;
    }
    if (next.length < 8) {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ Minimo 8 caracteres.';
        return;
    }
    if (current === next) {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ La nueva contrasena debe ser distinta.';
        return;
    }

    window.doctorAutoSaveHint(status, 'saving');
    const r = await window.doctorApi('PUT', '/portal-doctor/me/password', {
        current_password: current,
        new_password: next,
    });
    if (r.ok) {
        window.doctorAutoSaveHint(status, 'saved');
        status.textContent = '✓ Contrasena actualizada correctamente.';
        e.target.reset();
    } else {
        window.doctorAutoSaveHint(status, 'error');
        status.textContent = '⚠ ' + (r.message || 'Error al cambiar la contrasena.');
    }
});
</script>
<?php doctor_layout_end();
