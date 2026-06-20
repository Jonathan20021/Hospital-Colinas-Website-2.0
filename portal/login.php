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
        // Canal preferido opcional: '', 'sms' o 'email' (para "enviar por otro medio").
        $reqChannel = in_array(($_POST['channel'] ?? ''), ['sms', 'email'], true) ? $_POST['channel'] : '';
        $payload = ['identifier' => $idInput];
        if ($reqChannel !== '') $payload['channel'] = $reqChannel;
        $res = portal_api_call('POST', '/portal/auth/otp-request', $payload);
        if ($res['ok']) {
            $channel = $res['data']['channel'] ?? 'email';
            $_SESSION['otp_id']       = $idInput;
            $_SESSION['otp_channel']  = $channel;
            $_SESSION['otp_mask']     = $res['data']['destination_masked'] ?? ($res['data']['email_masked'] ?? '');
            $_SESSION['otp_channels'] = $res['data']['available_channels'] ?? [];
            $step = 'verify';
            $msg = $channel === 'sms'
                ? 'Te enviamos un código por SMS a tu celular.'
                : 'Te enviamos un código a tu correo. Revisa también la carpeta de spam.';
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
        unset($_SESSION['otp_id'], $_SESSION['otp_mask'], $_SESSION['otp_channel'], $_SESSION['otp_channels']);
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

$mask     = (string)($_SESSION['otp_mask'] ?? '');
$channel  = (string)($_SESSION['otp_channel'] ?? 'email');
$channels = (array)($_SESSION['otp_channels'] ?? []);
$isSms    = $channel === 'sms';
// Canal alternativo disponible (para "enviar por otro medio")
$altChannel = $isSms
    ? (in_array('email', $channels, true) ? 'email' : '')
    : (in_array('sms', $channels, true) ? 'sms' : '');

portal_layout_begin('Iniciar sesión', 'login');
?>
<div class="portal-auth-shell">
    <?php portal_auth_intro(); ?>
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
        <p class="lead">Enviamos un código de 6 dígitos <?= $isSms ? 'por SMS a tu celular' : 'a tu correo' ?><?php if ($mask): ?>:<br><span class="pa-mask"><?= e($mask) ?></span><?php endif; ?></p>
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
            <form method="POST" class="portal-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="request">
                <input type="hidden" name="identifier" value="<?= e((string)($_SESSION['otp_id'] ?? '')) ?>">
                <input type="hidden" name="channel" value="<?= e($channel) ?>">
                <button type="submit" class="portal-link-button" data-loading-label="Reenviando…">Reenviar código</button>
            </form>
            <?php if ($altChannel !== ''): ?>
            &nbsp;·&nbsp;
            <form method="POST" class="portal-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="request">
                <input type="hidden" name="identifier" value="<?= e((string)($_SESSION['otp_id'] ?? '')) ?>">
                <input type="hidden" name="channel" value="<?= e($altChannel) ?>">
                <button type="submit" class="portal-link-button"><?= $altChannel === 'sms' ? 'Enviar por SMS' : 'Enviar por correo' ?></button>
            </form>
            <?php endif; ?>
            &nbsp;·&nbsp;
            <form method="POST" class="portal-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="change">
                <button type="submit" class="portal-link-button">Usar otro dato</button>
            </form>
        </div>

    <?php else: ?>
        <h1>Entrar al portal</h1>
        <p class="lead">Escribe tu <strong>cédula</strong>, tu <strong>número de celular</strong> o tu <strong>correo</strong> y te enviaremos un código para entrar. Sin contraseñas.</p>
        <form method="POST" autocomplete="on">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <input type="hidden" name="step" value="request">
            <div class="pa-field">
                <label class="pa-label" for="identifier">Cédula, celular o correo electrónico</label>
                <input class="pa-input" type="text" name="identifier" id="identifier" required autofocus
                       value="<?= e($idInput) ?>" placeholder="Ej.: 001-1234567-8 · 809-123-4567 · nombre@correo.com">
            </div>
            <button type="submit" class="pa-btn pa-btn-green pa-btn-block">
                <i data-lucide="send"></i> Enviarme un código
            </button>
        </form>

        <details class="pa-auth-alt portal-auth-password" <?= $openPassword ? 'open' : '' ?>>
            <summary>Tengo contraseña y prefiero usarla</summary>
            <form method="POST" autocomplete="on" class="portal-password-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="password">
                <div class="pa-field">
                    <label class="pa-label" for="email">Correo electrónico</label>
                    <input class="pa-input" type="email" name="email" id="email" autocomplete="username">
                </div>
                <div class="pa-field portal-password-field">
                    <label class="pa-label" for="password">Contraseña</label>
                    <input class="pa-input" type="password" name="password" id="password" autocomplete="current-password">
                    <button type="button" class="portal-password-toggle" data-target="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
                <button type="submit" class="pa-btn pa-btn-soft pa-btn-block">Iniciar sesión con contraseña</button>
                <p class="portal-auth-recovery"><a href="<?= e(base_url('portal/recuperar.php')) ?>" class="portal-text-link">¿Olvidaste tu contraseña?</a></p>
            </form>
        </details>

        <p class="pa-auth-alt portal-auth-register">¿No tienes cuenta? <a href="<?= e(base_url('portal/registro.php')) ?>">Crear cuenta</a></p>
    <?php endif; ?>
</div>
</div>
<?php portal_layout_end();
