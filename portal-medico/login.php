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
    $trustedTok = doctor_trusted_device_token();

    $res = portal_api_call('POST', '/portal-doctor/auth/login', [
        'email'                => $emailInput,
        'password'             => $password,
        'trusted_device_token' => $trustedTok,
    ]);

    if ($res['ok'] && ($res['data']['step'] ?? '') === 'authenticated') {
        // Dispositivo ya confiable o 2FA desactivado → login directo
        doctor_portal_login_session($res['data']);
        $next = $_GET['next'] ?? base_url('portal-medico/dashboard.php');
        header('Location: ' . $next);
        exit;
    }

    if ($res['ok'] && ($res['data']['step'] ?? '') === 'verify') {
        // Necesita 2FA — guardar credenciales temporales en sesion para el verificar.php
        doctor_portal_session_start();
        $_SESSION['doctor_pending_login'] = [
            'email'        => $emailInput,
            'password'     => $password,        // se elimina al verificar o expira la sesion
            'email_masked' => $res['data']['email_masked'] ?? '',
            'expires_at'   => time() + (int)($res['data']['expires_in'] ?? 600),
            'next'         => $_GET['next'] ?? '',
        ];
        header('Location: ' . base_url('portal-medico/verificar.php'));
        exit;
    }

    $message = $res['message'] ?? 'No se pudo iniciar sesion.';
    $errors  = $res['errors'];
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

        <form method="POST" autocomplete="on" class="doctor-form" id="login-form">
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
            <p id="capslock-hint" class="doctor-capslock-hint" hidden>
                <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i> Bloq Mayus esta activado
            </p>

            <div class="doctor-auth-row">
                <a href="<?= e(base_url('portal-medico/recuperar.php')) ?>" class="doctor-text-link">¿Olvidaste tu contrasena?</a>
            </div>

            <button type="submit" class="doctor-btn doctor-btn-primary mt-6" id="login-submit">
                <span class="doctor-btn-content"><i data-lucide="log-in" class="h-4 w-4"></i> Iniciar sesion</span>
                <span class="doctor-btn-loading" hidden><i data-lucide="loader-2" class="h-4 w-4 doctor-spin"></i> Verificando...</span>
            </button>

            <div class="doctor-security-badges">
                <span class="doctor-security-badge"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Conexion TLS 1.3</span>
                <span class="doctor-security-badge"><i data-lucide="key-round" class="h-3.5 w-3.5"></i> 2FA por email</span>
                <span class="doctor-security-badge"><i data-lucide="lock" class="h-3.5 w-3.5"></i> Solo personal autorizado</span>
            </div>
        </form>
    </div>

    <aside class="doctor-auth-aside">
        <div class="doctor-auth-aside-inner">
            <i data-lucide="shield-check" class="h-10 w-10"></i>
            <h2>Tu trabajo, mas simple.</h2>
            <ul class="doctor-auth-points">
                <li><i data-lucide="calendar-check" class="h-4 w-4"></i> Tu agenda del dia y la semana.</li>
                <li><i data-lucide="file-text" class="h-4 w-4"></i> Notas medicas, recetas y constancias.</li>
                <li><i data-lucide="users" class="h-4 w-4"></i> Historial clinico de tus pacientes.</li>
                <li><i data-lucide="bar-chart-3" class="h-4 w-4"></i> Indicadores de tu consulta.</li>
            </ul>
            <p class="doctor-auth-aside-foot">Protegido con verificacion en dos pasos y conexion cifrada.</p>
        </div>
    </aside>
</div>

<script>
(function () {
    const form = document.getElementById('login-form');
    const pwd  = document.getElementById('password');
    const hint = document.getElementById('capslock-hint');
    const btn  = document.getElementById('login-submit');

    // Caps Lock detector
    function updateCaps(e) {
        const on = e.getModifierState && e.getModifierState('CapsLock');
        if (hint) hint.hidden = !on;
    }
    pwd?.addEventListener('keydown', updateCaps);
    pwd?.addEventListener('keyup', updateCaps);
    pwd?.addEventListener('blur', () => { if (hint) hint.hidden = true; });

    // Loading state
    form?.addEventListener('submit', () => {
        if (!btn) return;
        btn.disabled = true;
        btn.querySelector('.doctor-btn-content').hidden = true;
        btn.querySelector('.doctor-btn-loading').hidden = false;
        if (window.lucide) window.lucide.createIcons();
    });
})();
</script>
<?php doctor_layout_end();
