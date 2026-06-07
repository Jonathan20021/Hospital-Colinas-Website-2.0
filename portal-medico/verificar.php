<?php
require_once __DIR__ . '/_layout.php';

if (doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/dashboard.php'));
    exit;
}

doctor_portal_session_start();
$pending = $_SESSION['doctor_pending_login'] ?? null;

// Si no hay sesion pendiente o expiro, volver al login
if (!$pending || ($pending['expires_at'] ?? 0) < time()) {
    unset($_SESSION['doctor_pending_login']);
    doctor_flash_set('error', 'La sesión de verificación expiró. Inicia sesión de nuevo.');
    header('Location: ' . base_url('portal-medico/login.php'));
    exit;
}

$message = null; $errors = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    doctor_csrf_check();

    if (isset($_POST['action']) && $_POST['action'] === 'resend') {
        // Re-enviar codigo
        $res = portal_api_call('POST', '/portal-doctor/auth/send-code', [
            'email'    => $pending['email'],
            'password' => $pending['password'],
        ]);
        $pending['expires_at'] = time() + 600;
        $_SESSION['doctor_pending_login'] = $pending;
        doctor_flash_set('info', 'Te enviamos un nuevo código a tu correo.');
        header('Location: ' . base_url('portal-medico/verificar.php'));
        exit;
    }

    $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
    $remember = !empty($_POST['remember_device']);

    if (strlen($code) !== 6) {
        $message = 'Ingresa los 6 dígitos del código.';
    } else {
        $res = portal_api_call('POST', '/portal-doctor/auth/verify', [
            'email'           => $pending['email'],
            'password'        => $pending['password'],
            'code'            => $code,
            'remember_device' => $remember,
        ]);

        if ($res['ok'] && ($res['data']['step'] ?? '') === 'authenticated') {
            // Set trusted device cookie si la API la emitio
            if (!empty($res['data']['trusted_device_token'])) {
                doctor_set_trusted_device(
                    $res['data']['trusted_device_token'],
                    $res['data']['trusted_device_exp'] ?? null
                );
            }
            // Login session
            doctor_portal_login_session($res['data']);
            unset($_SESSION['doctor_pending_login']);
            $next = $pending['next'] ?: base_url('portal-medico/dashboard.php');
            header('Location: ' . $next);
            exit;
        }
        $message = $res['message'] ?? 'No se pudo verificar el código.';
        $errors  = $res['errors'];
    }
}

doctor_layout_begin('Verificar acceso', 'login');
?>
<div class="doctor-auth-wrap">
    <div class="doctor-auth-card">
        <div class="doctor-auth-brand">
            <i data-lucide="shield-check" class="h-7 w-7"></i>
            <div>
                <p class="doctor-auth-eyebrow">Verificación en dos pasos</p>
                <h1>Código de acceso</h1>
            </div>
        </div>
        <p class="doctor-auth-subtitle">
            Te enviamos un código de 6 dígitos a <strong><?= e($pending['email_masked'] ?? $pending['email']) ?></strong>.
            Revisa tu correo (puede tardar 1-2 minutos).
        </p>

        <?php if ($message): ?>
            <div class="doctor-flash doctor-flash-error">
                <i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span>
            </div>
        <?php endif; ?>
        <?= doctor_render_errors($errors) ?>

        <form method="POST" class="doctor-form" id="verify-form" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= e(doctor_csrf_token()) ?>">

            <label class="doctor-label" for="code">Código</label>
            <div class="doctor-otp" data-target="code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autofocus autocomplete="one-time-code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                <input class="doctor-otp-slot" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
            </div>
            <input type="hidden" name="code" id="code" required>

            <label class="doctor-check mt-4">
                <input type="checkbox" name="remember_device" value="1" checked>
                <span>Confiar en este dispositivo por 30 días (omitir verificación en futuros accesos desde este navegador).</span>
            </label>

            <button type="submit" class="doctor-btn doctor-btn-primary mt-6" id="verify-submit">
                <span class="doctor-btn-content"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Verificar y acceder</span>
                <span class="doctor-btn-loading" hidden><i data-lucide="loader-2" class="h-4 w-4 doctor-spin"></i> Verificando...</span>
            </button>

            <p class="doctor-auth-help">
                ¿No recibiste el correo? Revisa tu carpeta de spam o
                <button type="submit" name="action" value="resend" form="verify-form" class="doctor-text-link doctor-btn-inline">solicita un nuevo código</button>.
                <br>
                <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="doctor-text-link">Cancelar y volver al inicio</a>
            </p>
        </form>
    </div>

    <aside class="doctor-auth-aside">
        <div class="doctor-auth-aside-inner">
            <i data-lucide="mail-check" class="h-10 w-10"></i>
            <h2>¿Por qué este paso?</h2>
            <ul class="doctor-auth-points">
                <li><i data-lucide="user-check" class="h-4 w-4"></i> Confirma que eres tú en este dispositivo nuevo.</li>
                <li><i data-lucide="shield" class="h-4 w-4"></i> Protege la información clínica de tus pacientes.</li>
                <li><i data-lucide="clock" class="h-4 w-4"></i> El código vence en 10 minutos.</li>
                <li><i data-lucide="bookmark" class="h-4 w-4"></i> Una vez confiado, este navegador no pedirá código por 30 días.</li>
            </ul>
            <p class="doctor-auth-aside-foot">Nunca compartas tu código con nadie, ni siquiera con el personal del hospital.</p>
        </div>
    </aside>
</div>

<script>
(function () {
    const wrap = document.querySelector('.doctor-otp');
    const target = document.getElementById('code');
    const form = document.getElementById('verify-form');
    const btn  = document.getElementById('verify-submit');
    if (!wrap || !target) return;

    const slots = wrap.querySelectorAll('.doctor-otp-slot');
    function sync() {
        let v = '';
        slots.forEach(s => v += (s.value || ''));
        target.value = v;
    }

    slots.forEach((s, i) => {
        s.addEventListener('input', (e) => {
            s.value = (s.value || '').replace(/\D/g, '').slice(0, 1);
            sync();
            if (s.value && slots[i + 1]) slots[i + 1].focus();
        });
        s.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !s.value && slots[i - 1]) {
                slots[i - 1].focus();
                slots[i - 1].value = '';
                sync();
                e.preventDefault();
            }
        });
        s.addEventListener('paste', (e) => {
            const data = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            if (!data) return;
            e.preventDefault();
            for (let k = 0; k < slots.length; k++) {
                slots[k].value = data[k] || '';
            }
            sync();
            slots[Math.min(data.length, slots.length - 1)].focus();
        });
    });

    form?.addEventListener('submit', (e) => {
        // Si el submit es por el boton resend, no activar loading
        if (e.submitter && e.submitter.name === 'action') return;
        if (!btn) return;
        btn.disabled = true;
        btn.querySelector('.doctor-btn-content').hidden = true;
        btn.querySelector('.doctor-btn-loading').hidden = false;
        if (window.lucide) window.lucide.createIcons();
    });
})();
</script>
<?php doctor_layout_end();
