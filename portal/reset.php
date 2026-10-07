<?php
require_once __DIR__ . '/_layout.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$errors = null;
$message = null;
$done = false;

if ($token === '') {
    $message = 'Enlace de restablecimiento inválido.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    $pass    = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    if ($pass !== $confirm) {
        $errors = ['password_confirm' => ['Las contraseñas no coinciden.']];
    } else {
        $res = portal_api_call('POST', '/portal/auth/reset', ['token' => $token, 'password' => $pass]);
        if ($res['ok']) {
            $done = true;
            $message = $res['message'] ?: 'Contraseña actualizada.';
        } else {
            $message = $res['message'] ?? 'No se pudo cambiar la contraseña.';
            $errors  = $res['errors'];
        }
    }
}

portal_layout_begin('Restablecer contraseña', 'reset');
?>
<div class="portal-auth-shell">
    <?php portal_auth_intro('Elige una contraseña nueva.', 'Usa al menos 8 caracteres. Una frase fácil de recordar para ti y difícil de adivinar para otros es la mejor opción.'); ?>
<div class="portal-auth-card">
    <h1>Restablecer contraseña</h1>

    <?php if ($done): ?>
        <div class="portal-flash portal-flash-success">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i><span><?= e($message) ?></span>
        </div>
        <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-green w-full justify-center mt-4">Iniciar sesión</a>
    <?php else: ?>
        <?php if ($message): ?>
            <div class="portal-flash portal-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span></div>
        <?php endif; ?>
        <?= portal_render_errors($errors) ?>

        <p class="portal-subtitle">Escribe tu nueva contraseña dos veces para confirmarla.</p>
        <form method="POST" class="portal-form">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="portal-password-field">
                <label class="form-label" for="password">Nueva contraseña</label>
                <div class="pa-input-wrap"><i data-lucide="lock" aria-hidden="true"></i>
                    <input type="password" name="password" id="password" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
                <button type="button" class="portal-password-toggle" data-target="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
            </div>

            <div class="portal-password-field">
                <label class="form-label" for="password_confirm">Confirmar contraseña</label>
                <div class="pa-input-wrap"><i data-lucide="lock" aria-hidden="true"></i>
                    <input type="password" name="password_confirm" id="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
                <button type="button" class="portal-password-toggle" data-target="password_confirm" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
            </div>

            <button type="submit" class="btn btn-green w-full justify-center py-3 mt-4">Guardar nueva contraseña</button>
        </form>
    <?php endif; ?>
    <p class="pa-auth-secure"><i data-lucide="lock-keyhole" aria-hidden="true"></i> Conexión cifrada · Tus datos son privados</p>
</div>
</div>
<?php portal_layout_end();
