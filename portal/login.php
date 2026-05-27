<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
    exit;
}

$errors = null;
$message = null;
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    $emailInput = trim((string)($_POST['email'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    $res = portal_api_call('POST', '/portal/auth/login', [
        'email'    => $emailInput,
        'password' => $password,
    ]);

    if ($res['ok']) {
        portal_login_session($res['data']);
        $next = $_GET['next'] ?? base_url('portal/dashboard.php');
        header('Location: ' . $next);
        exit;
    } else {
        $message = $res['message'] ?? 'No se pudo iniciar sesión.';
        $errors  = $res['errors'];
    }
}

portal_layout_begin('Iniciar sesión', 'login');
?>
<div class="portal-auth-card">
    <h1>Portal de Pacientes</h1>
    <p class="portal-subtitle">Accede a tu cuenta para agendar y gestionar tus citas.</p>

    <?php if ($message): ?>
        <div class="portal-flash portal-flash-error">
            <i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span>
        </div>
    <?php endif; ?>
    <?= portal_render_errors($errors) ?>

    <form method="POST" autocomplete="on" class="portal-form">
        <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">

        <label class="form-label" for="email">Correo electrónico</label>
        <input type="email" name="email" id="email" class="form-input" required autofocus value="<?= e($emailInput) ?>">

        <label class="form-label mt-3" for="password">Contraseña</label>
        <input type="password" name="password" id="password" class="form-input" required autocomplete="current-password">

        <div class="portal-auth-actions">
            <a href="<?= e(base_url('portal/recuperar.php')) ?>" class="portal-text-link">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn btn-green w-full justify-center py-3">Iniciar sesión</button>

        <p class="portal-auth-secondary">
            ¿No tienes cuenta? <a href="<?= e(base_url('portal/registro.php')) ?>" class="portal-text-link">Crear cuenta</a>
        </p>
    </form>
</div>
<?php portal_layout_end();
