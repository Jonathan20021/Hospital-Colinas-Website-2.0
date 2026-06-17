<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
    exit;
}

global $assets;
portal_session_start();

// Guarda a dónde volver tras entrar
if (!empty($_GET['next'])) $_SESSION['otp_next'] = (string)$_GET['next'];

$step = !empty($_SESSION['otp_id']) ? 'verify' : 'request';
$msg = null; $msgType = 'error'; $idInput = '';
$openPassword = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    $action = (string)($_POST['step'] ?? '');

    if ($action === 'request') {
        $idInput = trim((string)($_POST['identifier'] ?? ''));
        $res = portal_api_call('POST', '/portal/auth/otp-request', ['identifier' => $idInput]);
        if ($res['ok']) {
            $_SESSION['otp_id']   = $idInput;
            $_SESSION['otp_mask'] = $res['data']['email_masked'] ?? '';
            $step = 'verify';
            $msg = 'Te enviamos un código a tu correo. Revisa también la carpeta de spam.';
            $msgType = 'ok';
        } else {
            $step = 'request';
            $msg = $res['message'] ?? 'No pudimos enviar el código.';
        }
    } elseif ($action === 'verify') {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        $res = portal_api_call('POST', '/portal/auth/otp-verify', [
            'identifier' => (string)($_SESSION['otp_id'] ?? ''),
            'code'       => $code,
        ]);
        if ($res['ok']) {
            portal_login_session($res['data']);
            $next = $_SESSION['otp_next'] ?? base_url('portal/dashboard.php');
            unset($_SESSION['otp_id'], $_SESSION['otp_mask'], $_SESSION['otp_next']);
            header('Location: ' . $next);
            exit;
        } else {
            $step = 'verify';
            $msg = $res['message'] ?? 'Código incorrecto.';
        }
    } elseif ($action === 'change') {
        unset($_SESSION['otp_id'], $_SESSION['otp_mask']);
        $step = 'request';
    } elseif ($action === 'password') {
        $res = portal_api_call('POST', '/portal/auth/login', [
            'email'    => trim((string)($_POST['email'] ?? '')),
            'password' => (string)($_POST['password'] ?? ''),
        ]);
        if ($res['ok']) {
            portal_login_session($res['data']);
            $next = $_SESSION['otp_next'] ?? base_url('portal/dashboard.php');
            unset($_SESSION['otp_next']);
            header('Location: ' . $next);
            exit;
        } else {
            $step = 'request'; $openPassword = true;
            $msg = $res['message'] ?? 'No se pudo iniciar sesión.';
        }
    }
}

$mask = (string)($_SESSION['otp_mask'] ?? '');

portal_layout_begin('Iniciar sesión', 'login');
?>
<div class="pa-auth">
    <img class="pa-auth-logo" src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas">

    <?php if ($msg): ?>
        <div class="pa-msg pa-msg-<?= e($msgType) ?>">
            <i data-lucide="<?= $msgType === 'ok' ? 'check-circle-2' : 'alert-circle' ?>"></i>
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($step === 'verify'): ?>
        <h1>Escribe tu código</h1>
        <p class="lead">Enviamos un código de 6 dígitos a tu correo<?php if ($mask): ?>:<br><span class="pa-mask"><?= e($mask) ?></span><?php endif; ?></p>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <input type="hidden" name="step" value="verify">
            <div class="pa-field">
                <label class="pa-label" for="code">Código de acceso</label>
                <input class="pa-input pa-input-code" type="text" inputmode="numeric" pattern="[0-9]*"
                       maxlength="6" name="code" id="code" autocomplete="one-time-code" required autofocus
                       placeholder="••••••">
                <p class="pa-hint">El código vence en 10 minutos. No lo compartas con nadie.</p>
            </div>
            <button type="submit" class="pa-btn pa-btn-green pa-btn-block">
                <i data-lucide="log-in"></i> Entrar
            </button>
        </form>
        <div class="pa-auth-alt">
            <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="request">
                <input type="hidden" name="identifier" value="<?= e((string)($_SESSION['otp_id'] ?? '')) ?>">
                <button type="submit" class="pa-link-btn" style="background:none;border:0;color:var(--pa-brand2);font-weight:800;cursor:pointer;font-size:1rem">Reenviar código</button>
            </form>
            &nbsp;·&nbsp;
            <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="change">
                <button type="submit" style="background:none;border:0;color:var(--pa-brand2);font-weight:800;cursor:pointer;font-size:1rem">Usar otro correo o cédula</button>
            </form>
        </div>

    <?php else: ?>
        <h1>Entrar al portal</h1>
        <p class="lead">Escribe tu <strong>cédula</strong> o tu <strong>correo</strong> y te enviaremos un código para entrar. Sin contraseñas.</p>
        <form method="POST" autocomplete="on">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <input type="hidden" name="step" value="request">
            <div class="pa-field">
                <label class="pa-label" for="identifier">Cédula o correo electrónico</label>
                <input class="pa-input" type="text" name="identifier" id="identifier" required autofocus
                       value="<?= e($idInput) ?>" placeholder="Ej.: 001-1234567-8  o  nombre@correo.com">
            </div>
            <button type="submit" class="pa-btn pa-btn-green pa-btn-block">
                <i data-lucide="mail"></i> Enviarme un código
            </button>
        </form>

        <details class="pa-auth-alt" style="margin-top:22px" <?= $openPassword ? 'open' : '' ?>>
            <summary style="cursor:pointer;font-weight:800;color:var(--pa-brand2)">Tengo contraseña y prefiero usarla</summary>
            <form method="POST" autocomplete="on" style="margin-top:14px;text-align:left">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="password">
                <div class="pa-field">
                    <label class="pa-label" for="email">Correo electrónico</label>
                    <input class="pa-input" type="email" name="email" id="email" autocomplete="username">
                </div>
                <div class="pa-field">
                    <label class="pa-label" for="password">Contraseña</label>
                    <input class="pa-input" type="password" name="password" id="password" autocomplete="current-password">
                </div>
                <button type="submit" class="pa-btn pa-btn-soft pa-btn-block">Iniciar sesión con contraseña</button>
                <p style="text-align:center;margin-top:10px"><a href="<?= e(base_url('portal/recuperar.php')) ?>" style="color:var(--pa-brand2);font-weight:700">¿Olvidaste tu contraseña?</a></p>
            </form>
        </details>

        <p class="pa-auth-alt" style="margin-top:18px">¿No tienes cuenta? <a href="<?= e(base_url('portal/registro.php')) ?>">Crear cuenta</a></p>
    <?php endif; ?>
</div>
<?php portal_layout_end();
