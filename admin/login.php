<?php
require __DIR__ . '/includes/auth.php';

if (admin_current_user()) {
    header('Location: ' . admin_first_allowed_url());
    exit;
}

$error = '';
$stage = 'credentials';   // credentials | enroll | verify
$enrollSecret = '';
$enrollUri = '';

function admin_2fa_pending_id(): ?int
{
    if (empty($_SESSION['admin_2fa_user_id']) || empty($_SESSION['admin_2fa_time'])) {
        return null;
    }
    if (time() - (int) $_SESSION['admin_2fa_time'] > ADMIN_2FA_TTL) {
        unset($_SESSION['admin_2fa_user_id'], $_SESSION['admin_2fa_time']);
        return null;
    }
    return (int) $_SESSION['admin_2fa_user_id'];
}

if (isset($_GET['reset'])) {
    unset($_SESSION['admin_2fa_user_id'], $_SESSION['admin_2fa_time']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $step = $_POST['step'] ?? 'credentials';

    if ($step === 'credentials') {
        $res = admin_check_credentials($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($res['status'] === 'locked') {
            $error = 'Cuenta bloqueada temporalmente por intentos fallidos. Intenta de nuevo en ' . (int) $res['retry_minutes'] . ' minutos.';
        } elseif ($res['status'] !== 'ok') {
            $error = 'Credenciales inválidas.';
        } else {
            $_SESSION['admin_2fa_user_id'] = (int) $res['user']['id'];
            $_SESSION['admin_2fa_time']    = time();
            $stage = admin_totp_is_enabled((int) $res['user']['id']) ? 'verify' : 'enroll';
        }
    } elseif ($step === 'verify') {
        $pid = admin_2fa_pending_id();
        if (!$pid) {
            $error = 'La sesión de acceso expiró. Vuelve a iniciar.';
        } elseif (admin_verify_login_totp($pid, $_POST['code'] ?? '')) {
            admin_complete_login(['id' => $pid]);
            header('Location: ' . admin_first_allowed_url());
            exit;
        } else {
            $error = 'Código incorrecto. Intenta de nuevo.';
            $stage = 'verify';
        }
    } elseif ($step === 'enroll') {
        $pid = admin_2fa_pending_id();
        if (!$pid) {
            $error = 'La sesión de acceso expiró. Vuelve a iniciar.';
        } elseif (admin_confirm_enrollment($pid, $_POST['code'] ?? '')) {
            $_SESSION['admin_recovery_codes'] = admin_generate_recovery_codes($pid);
            admin_complete_login(['id' => $pid]);
            header('Location: recovery-codes.php');
            exit;
        } else {
            $error = 'Código incorrecto. Asegúrate de haber escaneado el QR y vuelve a intentar.';
            $stage = 'enroll';
        }
    } elseif ($step === 'recovery') {
        $pid = admin_2fa_pending_id();
        if (!$pid) {
            $error = 'La sesión de acceso expiró. Vuelve a iniciar.';
        } elseif (admin_consume_recovery_code($pid, $_POST['code'] ?? '')) {
            admin_complete_login(['id' => $pid]);
            header('Location: ' . admin_first_allowed_url());
            exit;
        } else {
            $error = 'Código de recuperación inválido o ya usado.';
            $stage = 'recovery';
        }
    }
}

// Si hay un acceso pendiente de 2FA, mostrar el paso correspondiente
// (sobrevive recargas y permite alternar entre código del teléfono y recuperación).
$pendingId = admin_2fa_pending_id();
if ($stage === 'credentials' && $pendingId) {
    if (isset($_GET['recovery']) && $_GET['recovery'] !== '0') {
        $stage = 'recovery';
    } else {
        $stage = admin_totp_is_enabled($pendingId) ? 'verify' : 'enroll';
    }
}

// Preparar datos de inscripción si toca mostrarla.
if ($stage === 'enroll') {
    $pid = admin_2fa_pending_id();
    if ($pid) {
        $secret = admin_totp_secret($pid);
        if (!$secret) {
            $r = admin_begin_enrollment($pid);
            $secret = $r['secret'];
        }
        $enrollSecret = $secret;
        $st = db()->prepare('SELECT email FROM admin_users WHERE id = ? LIMIT 1');
        $st->execute([$pid]);
        $email = (string) $st->fetchColumn();
        $enrollUri = totp_provisioning_uri($secret, $email !== '' ? $email : ('admin#' . $pid), 'Colinas Admin');
    } else {
        $stage = 'credentials';
    }
}

$secretGroups = $enrollSecret !== '' ? trim(chunk_split($enrollSecret, 4, ' ')) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso Admin | Hospital General Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .login-2fa-code { letter-spacing: .5em; text-align: center; font-size: 1.4rem; font-weight: 700; }
        .login-qr { display: flex; justify-content: center; padding: 1rem; background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; margin: .5rem auto; width: max-content; }
        .login-qr img, .login-qr canvas { display: block; }
        .login-steps { display: flex; gap: .4rem; margin-bottom: .25rem; }
        .login-steps span { height: 4px; flex: 1; border-radius: 99px; background: #e5e7eb; }
        .login-steps span.is-on { background: #16a34a; }
        .login-secret { font-family: ui-monospace, monospace; font-size: .95rem; letter-spacing: .12em; background: #f1f5f9; border-radius: 8px; padding: .55rem .7rem; text-align: center; word-break: break-all; }
        .login-help { font-size: .82rem; color: #64748b; }
        .login-back { background: none; border: none; color: #64748b; font-size: .85rem; cursor: pointer; text-decoration: underline; padding: 0; }
    </style>
</head>
<body class="admin-login-body">
    <main class="login-panel">
        <section class="login-brand">
            <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
            <h1>Administración Las Colinas</h1>
            <p>Gestiona el directorio médico, perfiles profesionales, artículos clínicos y la inteligencia artificial desde una plataforma administrativa unificada y de alto rendimiento.</p>
        </section>

        <?php if ($stage === 'credentials'): ?>
        <form method="post" class="login-form" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="credentials">

            <div class="login-steps"><span class="is-on"></span><span></span></div>
            <div>
                <h2>Iniciar sesión</h2>
                <p>Ingresa tus credenciales para acceder al centro de control.</p>
            </div>

            <?php if (!db_ready()): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span>La base de datos no está instalada. <a href="install.php">Instalar ahora</a>.</span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <label>
                <span>Correo Electrónico</span>
                <input type="email" name="email" required autocomplete="username" placeholder="nombre@colinashospital.com" autofocus>
            </label>
            <label>
                <span>Contraseña</span>
                <input type="password" name="password" required placeholder="••••••••••••" autocomplete="current-password">
            </label>
            <button type="submit">
                <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                Continuar
            </button>
        </form>

        <?php elseif ($stage === 'enroll'): ?>
        <form method="post" class="login-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="enroll">

            <div class="login-steps"><span class="is-on"></span><span class="is-on"></span></div>
            <div>
                <h2>Configura tu verificación</h2>
                <p>Escanea este código con <strong>Google Authenticator</strong> o <strong>Authy</strong> y escribe el código de 6 dígitos que aparece.</p>
            </div>

            <?php if ($error): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="login-qr" id="qrcode"></div>
            <p class="login-help">¿No puedes escanear? Ingresa esta clave manualmente en la app:</p>
            <div class="login-secret"><?= e($secretGroups) ?></div>

            <label>
                <span>Código de 6 dígitos</span>
                <input class="login-2fa-code" type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="······">
            </label>
            <button type="submit">
                <i data-lucide="shield-check" style="width: 18px; height: 18px;"></i>
                Activar y entrar
            </button>
            <a class="login-back" href="login.php?reset=1" style="text-align:center;">Cancelar</a>
        </form>

        <?php elseif ($stage === 'verify'): ?>
        <form method="post" class="login-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="verify">

            <div class="login-steps"><span class="is-on"></span><span class="is-on"></span></div>
            <div>
                <h2>Verificación en dos pasos</h2>
                <p>Escribe el código de 6 dígitos de tu app autenticadora.</p>
            </div>

            <?php if ($error): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <label>
                <span>Código de acceso</span>
                <input class="login-2fa-code" type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="······">
            </label>
            <button type="submit">
                <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                Entrar al panel
            </button>
            <p class="login-help" style="text-align:center;margin:.25rem 0 0;">
                ¿Perdiste tu teléfono? <a href="login.php?recovery=1">Usar un código de recuperación</a>
            </p>
            <a class="login-back" href="login.php?reset=1" style="text-align:center;">Volver</a>
        </form>

        <?php else: /* recovery */ ?>
        <form method="post" class="login-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="recovery">

            <div class="login-steps"><span class="is-on"></span><span class="is-on"></span></div>
            <div>
                <h2>Código de recuperación</h2>
                <p>Escribe uno de los códigos de un solo uso que guardaste al configurar tu verificación.</p>
            </div>

            <?php if ($error): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <label>
                <span>Código de recuperación</span>
                <input class="login-secret" type="text" name="code" required autofocus autocomplete="off" placeholder="XXXXX-XXXXX" style="text-transform:uppercase;">
            </label>
            <button type="submit">
                <i data-lucide="key-round" style="width: 18px; height: 18px;"></i>
                Entrar con código de recuperación
            </button>
            <p class="login-help" style="text-align:center;margin:.25rem 0 0;">
                Cada código sirve una sola vez. Tras entrar podrás reconfigurar tu app autenticadora.
            </p>
            <a class="login-back" href="login.php?recovery=0" style="text-align:center;">Volver al código del teléfono</a>
        </form>
        <?php endif; ?>
    </main>

    <script src="../assets/js/qrcode.min.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <script>
        if (window.lucide) window.lucide.createIcons();
        <?php if ($stage === 'enroll' && $enrollUri !== ''): ?>
        (function () {
            var el = document.getElementById('qrcode');
            if (el && window.QRCode) {
                new QRCode(el, { text: <?= json_encode($enrollUri) ?>, width: 188, height: 188, correctLevel: QRCode.CorrectLevel.M });
            }
        })();
        <?php endif; ?>
    </script>
</body>
</html>
