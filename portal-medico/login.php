<?php
require_once __DIR__ . '/_layout.php';

if (doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/dashboard.php'));
    exit;
}

$errors  = null;
$message = null;
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    doctor_csrf_check();
    $emailInput = trim((string)($_POST['email'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    $res = portal_api_call('POST', '/portal-doctor/auth/login', [
        'email'    => $emailInput,
        'password' => $password,
    ]);

    if ($res['ok']) {
        doctor_portal_login_session($res['data']);
        $next = $_GET['next'] ?? base_url('portal-medico/dashboard.php');
        header('Location: ' . $next);
        exit;
    } else {
        $message = $res['message'] ?? 'No se pudo iniciar sesion.';
        $errors  = $res['errors'];
    }
}

doctor_layout_begin('Iniciar sesion', 'login');
?>
<div class="doctor-auth-wrap">
    <div class="doctor-auth-card">
        <div class="doctor-auth-brand">
            <i data-lucide="stethoscope" class="h-7 w-7"></i>
            <div>
                <p class="doctor-auth-eyebrow">Hospital Las Colinas</p>
                <h1>Portal del Medico</h1>
            </div>
        </div>
        <p class="doctor-auth-subtitle">Accede para gestionar tu agenda, consultas y pacientes.</p>

        <?php if ($message): ?>
            <div class="doctor-flash doctor-flash-error">
                <i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span>
            </div>
        <?php endif; ?>
        <?= doctor_render_errors($errors) ?>

        <form method="POST" autocomplete="on" class="doctor-form">
            <input type="hidden" name="_csrf" value="<?= e(doctor_csrf_token()) ?>">

            <label class="doctor-label" for="email">Correo institucional</label>
            <div class="doctor-input-icon">
                <i data-lucide="mail" class="h-4 w-4"></i>
                <input type="email" name="email" id="email" class="doctor-input" required autofocus value="<?= e($emailInput) ?>" placeholder="usted@hospital.com">
            </div>

            <label class="doctor-label mt-4" for="password">Contrasena</label>
            <div class="doctor-input-icon">
                <i data-lucide="lock" class="h-4 w-4"></i>
                <input type="password" name="password" id="password" class="doctor-input" required autocomplete="current-password" placeholder="********">
                <button type="button" class="doctor-input-toggle" data-toggle="#password" aria-label="Mostrar/ocultar contrasena">
                    <i data-lucide="eye" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="doctor-auth-row">
                <a href="<?= e(base_url('portal-medico/recuperar.php')) ?>" class="doctor-text-link">¿Olvidaste tu contrasena?</a>
            </div>

            <button type="submit" class="doctor-btn doctor-btn-primary mt-6">
                <i data-lucide="log-in" class="h-4 w-4"></i> Iniciar sesion
            </button>

            <p class="doctor-auth-help">
                Solo personal medico autorizado.<br>
                ¿Problemas para entrar? Contacta al administrador del hospital.
            </p>
        </form>
    </div>

    <aside class="doctor-auth-aside">
        <div class="doctor-auth-aside-inner">
            <i data-lucide="shield-check" class="h-10 w-10"></i>
            <h2>Tu trabajo, mas simple.</h2>
            <ul class="doctor-auth-points">
                <li><i data-lucide="calendar-check" class="h-4 w-4"></i> Tu agenda del dia y la semana.</li>
                <li><i data-lucide="file-text" class="h-4 w-4"></i> Notas medicas y recetas en linea.</li>
                <li><i data-lucide="users" class="h-4 w-4"></i> Historial clinico de tus pacientes.</li>
                <li><i data-lucide="bar-chart-3" class="h-4 w-4"></i> Indicadores de tu consulta.</li>
            </ul>
            <p class="doctor-auth-aside-foot">Conexion cifrada extremo a extremo con la red del hospital.</p>
        </div>
    </aside>
</div>
<?php doctor_layout_end();
