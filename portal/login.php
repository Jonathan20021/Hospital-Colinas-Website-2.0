<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
    exit;
}

global $assets;
portal_session_start();

// Guarda a dónde volver tras entrar (solo rutas internas — anti open redirect)
if (!empty($_GET['next'])) { $sn = safe_next($_GET['next'], ''); if ($sn !== '') $_SESSION['otp_next'] = $sn; }

$step = !empty($_SESSION['otp_id']) ? 'verify' : 'request';
$msg = null; $msgType = 'error'; $idInput = '';
$openPassword = false;
$openActivate = false;

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
            // Si la cuenta no tiene correo, guiar a la activación por cédula + teléfono.
            if (!empty($res['errors']['no_email']) || !empty($res['errors']['no_contact'])) {
                $openActivate = true;
            }
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
            'identifier' => trim((string)($_POST['identifier'] ?? '')),
            'password'   => (string)($_POST['password'] ?? ''),
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
    } elseif ($action === 'activate') {
        if ((string)($_POST['new_password'] ?? '') !== (string)($_POST['new_password_confirm'] ?? '')) {
            $step = 'request'; $openActivate = true;
            $msg = 'Las contraseñas no coinciden.';
        } else {
            $res = portal_api_call('POST', '/portal/auth/activate', [
                'identifier'   => trim((string)($_POST['identifier'] ?? '')),
                'phone'        => trim((string)($_POST['phone'] ?? '')),
                'new_password' => (string)($_POST['new_password'] ?? ''),
            ]);
            if ($res['ok']) {
                portal_login_session($res['data']);
                $next = $_SESSION['otp_next'] ?? base_url('portal/dashboard.php');
                unset($_SESSION['otp_next']);
                header('Location: ' . $next);
                exit;
            } else {
                $step = 'request'; $openActivate = true;
                $msg = $res['message'] ?? 'No se pudo activar la cuenta.';
            }
        }
    }
}

$mask = (string)($_SESSION['otp_mask'] ?? '');

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
            <form method="POST" class="portal-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="request">
                <input type="hidden" name="identifier" value="<?= e((string)($_SESSION['otp_id'] ?? '')) ?>">
                <button type="submit" class="portal-link-button" data-loading-label="Reenviando…">Reenviar código</button>
            </form>
            &nbsp;·&nbsp;
            <form method="POST" class="portal-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="change">
                <button type="submit" class="portal-link-button">Usar otro correo o cédula</button>
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

        <details class="pa-auth-alt portal-auth-password" <?= $openPassword ? 'open' : '' ?>>
            <summary>Tengo contraseña y prefiero usarla</summary>
            <form method="POST" autocomplete="on" class="portal-password-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="password">
                <div class="pa-field">
                    <label class="pa-label" for="identifier_pw">Cédula o correo electrónico</label>
                    <input class="pa-input" type="text" name="identifier" id="identifier_pw" required value="<?= e($idInput) ?>" autocomplete="username" placeholder="001-1234567-8  o  nombre@correo.com">
                </div>
                <div class="pa-field portal-password-field">
                    <label class="pa-label" for="password">Contraseña</label>
                    <input class="pa-input" type="password" name="password" id="password" required autocomplete="current-password">
                    <button type="button" class="portal-password-toggle" data-target="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
                <button type="submit" class="pa-btn pa-btn-soft pa-btn-block">Iniciar sesión con contraseña</button>
                <p class="portal-auth-recovery"><a href="<?= e(base_url('portal/recuperar.php')) ?>" class="portal-text-link">¿Olvidaste tu contraseña?</a></p>
            </form>
        </details>

        <details class="pa-auth-alt portal-auth-password" <?= $openActivate ? 'open' : '' ?>>
            <summary>Primera vez · No tengo correo</summary>
            <p class="pa-hint">Si no tienes correo registrado, activa tu cuenta con tu <strong>cédula</strong> y el <strong>número de celular</strong> que diste en el hospital. No necesitas contraseña previa.</p>
            <form method="POST" autocomplete="off" class="portal-password-form">
                <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
                <input type="hidden" name="step" value="activate">
                <div class="pa-field">
                    <label class="pa-label" for="act_cedula">Cédula</label>
                    <input class="pa-input" type="text" name="identifier" id="act_cedula" required placeholder="001-1234567-8">
                </div>
                <div class="pa-field">
                    <label class="pa-label" for="act_phone">Celular registrado en el hospital</label>
                    <input class="pa-input" type="tel" name="phone" id="act_phone" required placeholder="(809) 000-0000">
                </div>
                <div class="pa-field portal-password-field">
                    <label class="pa-label" for="act_pass">Crea tu contraseña</label>
                    <input class="pa-input" type="password" name="new_password" id="act_pass" minlength="8" required autocomplete="new-password">
                    <button type="button" class="portal-password-toggle" data-target="act_pass" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
                <div class="pa-field portal-password-field">
                    <label class="pa-label" for="act_pass2">Repite tu contraseña</label>
                    <input class="pa-input" type="password" name="new_password_confirm" id="act_pass2" minlength="8" required autocomplete="new-password">
                    <button type="button" class="portal-password-toggle" data-target="act_pass2" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
                </div>
                <button type="submit" class="pa-btn pa-btn-green pa-btn-block"><i data-lucide="shield-check"></i> Activar y entrar</button>
                <p class="pa-hint">¿Tu número cambió? Llámanos para actualizarlo y poder activarte.</p>
            </form>
        </details>

        <p class="pa-auth-alt portal-auth-register">¿No tienes cuenta? <a href="<?= e(base_url('portal/registro.php')) ?>">Crear cuenta</a></p>
    <?php endif; ?>
</div>
</div>
<?php portal_layout_end();
