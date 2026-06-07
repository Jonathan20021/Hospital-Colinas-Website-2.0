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
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Mi cuenta</p>
        <h1>Seguridad de tu cuenta</h1>
        <p class="doctor-subtitle">Tu información profesional es administrada por el hospital. Aquí solo puedes gestionar tu contraseña de acceso.</p>
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

<div class="doctor-grid-2 mt-6">
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
<?php doctor_layout_end();
