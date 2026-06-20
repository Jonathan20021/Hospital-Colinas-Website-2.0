<?php
require __DIR__ . '/includes/auth.php';

$user = admin_current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$codes = $_SESSION['admin_recovery_codes'] ?? null;
unset($_SESSION['admin_recovery_codes']); // Mostrar una sola vez.

if (!$codes || !is_array($codes)) {
    header('Location: ' . admin_first_allowed_url());
    exit;
}

$panelUrl = admin_first_allowed_url();
$codesText = implode("\n", $codes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Códigos de recuperación | Administración Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .rc-wrap { max-width: 560px; margin: 4vh auto; padding: 0 1rem; }
        .rc-card { background:#fff; border:1px solid #e5e7eb; border-radius:18px; padding:1.75rem; }
        .rc-card h1 { font-size:1.35rem; margin:0 0 .35rem; }
        .rc-card p { color:#475569; font-size:.92rem; }
        .rc-grid { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin:1.1rem 0; }
        .rc-code { font-family: ui-monospace, monospace; font-size:1.05rem; letter-spacing:.08em; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem; text-align:center; }
        .rc-warn { display:flex; gap:.5rem; align-items:flex-start; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:10px; padding:.7rem .85rem; font-size:.85rem; margin-bottom:1rem; }
        .rc-actions { display:flex; gap:.6rem; flex-wrap:wrap; margin-top:1.1rem; }
        .rc-btn { flex:1; min-width:150px; text-align:center; padding:.7rem 1rem; border-radius:10px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; font-weight:600; cursor:pointer; text-decoration:none; }
        .rc-btn.is-primary { background:#16a34a; border-color:#16a34a; color:#fff; }
    </style>
</head>
<body class="admin-login-body">
    <div class="rc-wrap">
        <div class="rc-card">
            <h1>Guarda tus códigos de recuperación</h1>
            <p>Si pierdes tu teléfono, estos códigos te permitirán entrar al panel. <strong>Cada uno sirve una sola vez.</strong> Guárdalos en un lugar seguro — no volverán a mostrarse.</p>

            <div class="rc-grid">
                <?php foreach ($codes as $c): ?>
                    <div class="rc-code"><?= e($c) ?></div>
                <?php endforeach; ?>
            </div>

            <div class="rc-warn">
                <span>⚠️</span>
                <span>Esta es la única vez que verás estos códigos. Si los pierdes y no tienes tu teléfono, otro administrador deberá restablecer tu verificación.</span>
            </div>

            <div class="rc-actions">
                <button type="button" class="rc-btn" id="rc-copy">Copiar</button>
                <button type="button" class="rc-btn" id="rc-download">Descargar .txt</button>
                <a class="rc-btn is-primary" href="<?= e($panelUrl) ?>">Ya los guardé · Ir al panel</a>
            </div>
        </div>
    </div>

    <script>
        var CODES = <?= json_encode($codesText) ?>;
        document.getElementById('rc-copy').addEventListener('click', function () {
            navigator.clipboard.writeText(CODES).then(function () { this.textContent = 'Copiado ✓'; }.bind(this));
        });
        document.getElementById('rc-download').addEventListener('click', function () {
            var blob = new Blob(['Códigos de recuperación - Administración Las Colinas\n\n' + CODES + '\n'], { type: 'text/plain' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'codigos-recuperacion-colinas.txt';
            a.click();
        });
    </script>
</body>
</html>
