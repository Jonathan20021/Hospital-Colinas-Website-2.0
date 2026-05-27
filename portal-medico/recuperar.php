<?php
require_once __DIR__ . '/_layout.php';

if (doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/dashboard.php'));
    exit;
}

$sent = false; $message = null; $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    doctor_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $res = portal_api_call('POST', '/portal-doctor/auth/forgot', ['email' => $email]);
    $sent = true;
    $message = $res['message'] ?: 'Si la cuenta existe, te enviamos instrucciones.';
}

doctor_layout_begin('Recuperar contrasena', 'recuperar');
?>
<div class="doctor-auth-wrap">
    <div class="doctor-auth-card">
        <div class="doctor-auth-brand">
            <i data-lucide="mail-search" class="h-7 w-7"></i>
            <div>
                <p class="doctor-auth-eyebrow">Hospital Las Colinas</p>
                <h1>Recuperar acceso</h1>
            </div>
        </div>
        <p class="doctor-auth-subtitle">Indicanos tu correo institucional y te enviaremos un enlace para crear una nueva contrasena.</p>

        <?php if ($sent): ?>
            <div class="doctor-flash doctor-flash-success">
                <i data-lucide="mail-check" class="h-4 w-4"></i>
                <span><?= e($message) ?></span>
            </div>
            <p class="doctor-auth-help mt-4">
                Si no recibes el correo en unos minutos, revisa tu carpeta de spam o contacta al administrador del hospital.<br>
                <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="doctor-text-link">Volver al inicio de sesion</a>
            </p>
        <?php else: ?>
            <form method="POST" class="doctor-form">
                <input type="hidden" name="_csrf" value="<?= e(doctor_csrf_token()) ?>">
                <label class="doctor-label" for="email">Correo institucional</label>
                <div class="doctor-input-icon">
                    <i data-lucide="mail" class="h-4 w-4"></i>
                    <input type="email" name="email" id="email" class="doctor-input" required value="<?= e($email) ?>" placeholder="usted@hospital.com">
                </div>
                <button type="submit" class="doctor-btn doctor-btn-primary mt-6">
                    <i data-lucide="send" class="h-4 w-4"></i> Enviar enlace
                </button>
                <p class="doctor-auth-help">
                    <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="doctor-text-link">Volver al inicio de sesion</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <aside class="doctor-auth-aside">
        <div class="doctor-auth-aside-inner">
            <i data-lucide="lock-keyhole" class="h-10 w-10"></i>
            <h2>Acceso seguro</h2>
            <ul class="doctor-auth-points">
                <li><i data-lucide="clock" class="h-4 w-4"></i> El enlace es valido por 1 hora.</li>
                <li><i data-lucide="shield-check" class="h-4 w-4"></i> Solo tu puedes usarlo, desde el correo que recibes.</li>
                <li><i data-lucide="key-round" class="h-4 w-4"></i> Crearas una nueva contrasena al hacer clic.</li>
            </ul>
            <p class="doctor-auth-aside-foot">¿Problemas? Contacta al administrador del hospital.</p>
        </div>
    </aside>
</div>
<?php doctor_layout_end();
