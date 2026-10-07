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
<div class="portal-auth-shell">
    <?php portal_auth_intro('Recupera tu acceso.', 'Te enviamos un enlace seguro a tu correo para que elijas una contraseña nueva. Tu información sigue protegida.'); ?>
<div class="portal-auth-card">
    <h1>Recuperar contraseña</h1>
    <p class="portal-subtitle">Indícanos tu correo y te enviaremos un enlace para restablecerla.</p>

    <?php if ($sent): ?>
        <div class="portal-flash portal-flash-success">
            <i data-lucide="mail-check" class="h-4 w-4"></i>
            <span><?= e($message) ?></span>
        </div>
        <p class="portal-hint">Si no lo ves en unos minutos, revisa también la carpeta de spam.</p>
        <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-green w-full justify-center py-3 mt-4">Volver a iniciar sesión</a>
    <?php else: ?>
        <form method="POST" class="portal-form">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <label class="form-label" for="email">Correo electrónico</label>
            <div class="pa-input-wrap"><i data-lucide="mail" aria-hidden="true"></i>
                <input type="email" name="email" id="email" class="form-input" required value="<?= e($email) ?>" autocomplete="email" placeholder="nombre@correo.com">
            </div>
            <button type="submit" class="btn btn-green w-full justify-center py-3 mt-4"><i data-lucide="mail" aria-hidden="true"></i> Enviar enlace</button>
        </form>

        <p class="pa-auth-or"><span>¿La recordaste?</span></p>
        <a class="pa-auth-option pa-auth-option-link" href="<?= e(base_url('portal/login.php')) ?>">
            <span class="pa-auth-option-icon" aria-hidden="true"><i data-lucide="arrow-left"></i></span>
            <span class="pa-auth-option-text">
                <strong>Volver a iniciar sesión</strong>
                <small>También puedes entrar sin contraseña, con un código a tu correo</small>
            </span>
            <i data-lucide="chevron-right" class="pa-auth-option-chevron" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
    <p class="pa-auth-secure"><i data-lucide="lock-keyhole" aria-hidden="true"></i> Conexión cifrada · Tus datos son privados</p>
</div>
</div>
<?php portal_layout_end();
