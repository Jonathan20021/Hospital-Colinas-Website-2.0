<?php
require_once __DIR__ . '/_layout.php';

$token = (string)($_GET['token'] ?? '');
$message = null;
$success = false;

if ($token === '') {
    $message = 'Falta el token de verificación.';
} else {
    $res = portal_api_call('GET', '/portal/auth/verify-email', ['token' => $token]);
    $success = $res['ok'];
    $message = $res['message'] ?: ($success ? 'Correo verificado.' : 'No se pudo verificar el correo.');
    if ($success && portal_is_logged_in()) {
        portal_set_verified(true);
    }
}

portal_layout_begin('Verificar correo', 'verificar');
?>
<div class="portal-auth-card">
    <?php if ($success): ?>
        <i data-lucide="mail-check" class="h-12 w-12 text-emerald-600 mx-auto"></i>
        <h1 class="mt-3">¡Listo!</h1>
        <p class="portal-subtitle"><?= e($message) ?></p>
        <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-green w-full justify-center mt-4">Ir a iniciar sesión</a>
    <?php else: ?>
        <i data-lucide="alert-circle" class="h-12 w-12 text-rose-600 mx-auto"></i>
        <h1 class="mt-3">No pudimos verificar tu correo</h1>
        <p class="portal-subtitle"><?= e($message) ?></p>
        <a href="<?= e(base_url('portal/login.php')) ?>" class="portal-text-link block mt-4">Volver al inicio de sesión</a>
    <?php endif; ?>
</div>
<?php portal_layout_end();
