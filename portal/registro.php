<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
    exit;
}

$errors = null;
$message = null;
$success = null;
$values = [
    'name'   => '',
    'email'  => trim((string)($_GET['email']  ?? '')),
    'phone'  => '',
    'cedula' => trim((string)($_GET['cedula'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    foreach ($values as $k => $_) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if ($password !== $confirm) {
        $errors = ['password_confirm' => ['Las contraseñas no coinciden.']];
    } else {
        $res = portal_api_call('POST', '/portal/auth/register', array_merge($values, ['password' => $password]));
        if ($res['ok']) {
            $success = $res['message'] ?: 'Cuenta creada. Revisa tu correo para verificarla.';
        } else {
            $message = $res['message'] ?? 'No se pudo crear la cuenta.';
            $errors  = $res['errors'];
        }
    }
}

portal_layout_begin('Crear cuenta', 'registro');
?>
<div class="portal-auth-shell">
    <?php portal_auth_intro(); ?>
<div class="portal-auth-card portal-auth-wide">
    <h1>Crear cuenta de paciente</h1>
    <p class="portal-subtitle">Necesitamos tus datos básicos para crear tu expediente y poder agendar citas en línea.</p>

    <?php if ($success): ?>
        <div class="portal-flash portal-flash-success">
            <i data-lucide="mail-check" class="h-4 w-4"></i>
            <span><?= e($success) ?></span>
        </div>
        <p class="portal-auth-secondary">
            <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-green">Ir a iniciar sesión</a>
        </p>
    <?php else: ?>
        <?php if ($message): ?>
            <div class="portal-flash portal-flash-error">
                <i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span>
            </div>
        <?php endif; ?>
        <?= portal_render_errors($errors) ?>

        <form method="POST" autocomplete="on" class="portal-form">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">

            <div class="portal-grid-2">
                <div>
                    <label class="form-label" for="name">Nombre completo</label>
                    <input type="text" name="name" id="name" class="form-input" required value="<?= e($values['name']) ?>" autocomplete="name">
                </div>
                <div>
                    <label class="form-label" for="cedula">Cédula</label>
                    <input type="text" name="cedula" id="cedula" class="form-input" required value="<?= e($values['cedula']) ?>" placeholder="000-0000000-0">
                </div>
            </div>

            <div class="portal-grid-2">
                <div>
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input type="email" name="email" id="email" class="form-input" required value="<?= e($values['email']) ?>" autocomplete="email">
                </div>
                <div>
                    <label class="form-label" for="phone">Teléfono</label>
                    <input type="tel" name="phone" id="phone" class="form-input" required value="<?= e($values['phone']) ?>" placeholder="(809) 000-0000" autocomplete="tel">
                </div>
            </div>

            <div class="portal-grid-2">
                <div class="portal-password-field">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" name="password" id="password" class="form-input" required minlength="8" autocomplete="new-password">
                    <button type="button" class="portal-password-toggle" data-target="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
                <div class="portal-password-field">
                    <label class="form-label" for="password_confirm">Confirmar contraseña</label>
                    <input type="password" name="password_confirm" id="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                    <button type="button" class="portal-password-toggle" data-target="password_confirm" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
            </div>

            <p class="portal-hint">Al crear tu cuenta aceptas la <a href="<?= e(base_url('politica-de-privacidad')) ?>" class="portal-text-link">política de privacidad</a> del hospital.</p>

            <button type="submit" class="btn btn-green w-full justify-center py-3">Crear mi cuenta</button>

            <p class="portal-auth-secondary">
                ¿Ya tienes cuenta? <a href="<?= e(base_url('portal/login.php')) ?>" class="portal-text-link">Iniciar sesión</a>
            </p>
        </form>
    <?php endif; ?>
</div>
</div>
<?php portal_layout_end();
