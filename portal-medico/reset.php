<?php
require_once __DIR__ . '/_layout.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$message = null; $errors = null; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    doctor_csrf_check();
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if ($password !== $confirm) {
        $message = 'Las contrasenas no coinciden.';
    } elseif (strlen($password) < 8) {
        $message = 'La contrasena debe tener al menos 8 caracteres.';
    } else {
        $res = portal_api_call('POST', '/portal-doctor/auth/reset', [
            'token'    => $token,
            'password' => $password,
        ]);
        if ($res['ok']) {
            $done = true;
            doctor_flash_set('success', 'Contrasena actualizada. Inicia sesion con la nueva.');
        } else {
            $message = $res['message'] ?? 'No se pudo cambiar la contrasena.';
            $errors  = $res['errors'];
        }
    }
}

doctor_layout_begin('Nueva contrasena', 'reset');
?>
<div class="doctor-auth-wrap">
    <div class="doctor-auth-card">
        <div class="doctor-auth-brand">
            <i data-lucide="key-round" class="h-7 w-7"></i>
            <div>
                <p class="doctor-auth-eyebrow">Hospital Las Colinas</p>
                <h1>Nueva contrasena</h1>
            </div>
        </div>

        <?php if ($done): ?>
            <div class="doctor-flash doctor-flash-success">
                <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                <span>Tu contrasena fue actualizada. Ya puedes iniciar sesion con ella.</span>
            </div>
            <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="doctor-btn doctor-btn-primary mt-6"><i data-lucide="log-in" class="h-4 w-4"></i> Ir a iniciar sesion</a>
        <?php elseif (!$token): ?>
            <div class="doctor-flash doctor-flash-error">
                <i data-lucide="alert-circle" class="h-4 w-4"></i>
                <span>Enlace invalido. Solicita un nuevo enlace de recuperacion.</span>
            </div>
            <a href="<?= e(base_url('portal-medico/recuperar.php')) ?>" class="doctor-btn doctor-btn-primary mt-6"><i data-lucide="mail-search" class="h-4 w-4"></i> Solicitar nuevo enlace</a>
        <?php else:
            if ($message): ?>
                <div class="doctor-flash doctor-flash-error">
                    <i data-lucide="alert-circle" class="h-4 w-4"></i>
                    <span><?= e($message) ?></span>
                </div>
            <?php endif; ?>
            <?= doctor_render_errors($errors) ?>

            <p class="doctor-auth-subtitle">Crea una contrasena nueva, minimo 8 caracteres.</p>

            <form method="POST" class="doctor-form">
                <input type="hidden" name="_csrf" value="<?= e(doctor_csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <label class="doctor-label" for="password">Nueva contrasena</label>
                <div class="doctor-input-icon">
                    <i data-lucide="lock" class="h-4 w-4"></i>
                    <input type="password" name="password" id="password" class="doctor-input" required minlength="8" autocomplete="new-password">
                    <button type="button" class="doctor-input-toggle" data-toggle="#password" aria-label="Mostrar/ocultar"><i data-lucide="eye" class="h-4 w-4"></i></button>
                </div>

                <label class="doctor-label mt-4" for="password_confirm">Confirmar contrasena</label>
                <div class="doctor-input-icon">
                    <i data-lucide="lock" class="h-4 w-4"></i>
                    <input type="password" name="password_confirm" id="password_confirm" class="doctor-input" required minlength="8" autocomplete="new-password">
                </div>

                <button type="submit" class="doctor-btn doctor-btn-primary mt-6">
                    <i data-lucide="save" class="h-4 w-4"></i> Cambiar contrasena
                </button>
            </form>
        <?php endif; ?>
    </div>

    <aside class="doctor-auth-aside">
        <div class="doctor-auth-aside-inner">
            <i data-lucide="shield-check" class="h-10 w-10"></i>
            <h2>Una contrasena segura</h2>
            <ul class="doctor-auth-points">
                <li><i data-lucide="key" class="h-4 w-4"></i> Minimo 8 caracteres.</li>
                <li><i data-lucide="zap" class="h-4 w-4"></i> Combina letras, numeros y simbolos.</li>
                <li><i data-lucide="x" class="h-4 w-4"></i> No la reutilices con otros sitios.</li>
            </ul>
        </div>
    </aside>
</div>
<?php doctor_layout_end();
