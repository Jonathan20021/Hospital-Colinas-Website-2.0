<?php
require_once __DIR__ . '/_layout.php';

$sent = false; $message = null; $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $res = portal_api_call('POST', '/portal/auth/forgot', ['email' => $email]);
    $sent = true;
    $message = $res['message'] ?: 'Si la cuenta existe, te enviamos instrucciones.';
}

portal_layout_begin('Recuperar contraseña', 'recuperar');
?>
<div class="portal-auth-card">
    <h1>Recuperar contraseña</h1>
    <p class="portal-subtitle">Indícanos tu correo y te enviaremos un enlace para restablecerla.</p>

    <?php if ($sent): ?>
        <div class="portal-flash portal-flash-success">
            <i data-lucide="mail-check" class="h-4 w-4"></i>
            <span><?= e($message) ?></span>
        </div>
        <a href="<?= e(base_url('portal/login.php')) ?>" class="portal-text-link block mt-4">Volver al inicio</a>
    <?php else: ?>
        <form method="POST" class="portal-form">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" class="form-input" required value="<?= e($email) ?>">
            <button type="submit" class="btn btn-green w-full justify-center py-3 mt-4">Enviar enlace</button>
            <p class="portal-auth-secondary">
                <a href="<?= e(base_url('portal/login.php')) ?>" class="portal-text-link">Cancelar</a>
            </p>
        </form>
    <?php endif; ?>
</div>
<?php portal_layout_end();
