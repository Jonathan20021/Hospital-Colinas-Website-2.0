<?php
/**
 * Teleconsulta — página pública del paciente. Se abre con el enlace-token que
 * envía el médico: /teleconsulta?t=XXXX. Aislada del sitio público, noindex.
 * Obtiene el token de LiveKit del API interno (endpoint público por join_token)
 * y avisa al médico (push) que el paciente entró.
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/portal_client.php';

$t = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['t'] ?? ''));
$data = null; $error = null;

if (strlen($t) < 16) {
    $error = 'Enlace de teleconsulta inválido.';
} else {
    $res = portal_api_call('GET', '/portal-doctor/teleconsult/' . $t, []);
    if (!empty($res['ok'])) {
        $data = $res['data'] ?? null;
        // Avisar al médico que el paciente entró (no bloqueante)
        try { portal_api_call('POST', '/portal-doctor/teleconsult/' . $t . '/here', []); } catch (Throwable $e) {}
    } else {
        $error = $res['message'] ?? 'No se pudo abrir la teleconsulta.';
    }
}
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Teleconsulta · Hospital General Las Colinas</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2a2566">
    <link rel="icon" type="image/png" href="<?= e(base_url('assets/site/favicon.png')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/teleconsulta.css')) ?>?v=<?= @filemtime(__DIR__ . '/assets/css/teleconsulta.css') ?>">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,sans-serif;background:#eef1f7;color:#101728;min-height:100dvh}
        .tele-top{background:#fff;border-bottom:1px solid #e8ecf4;padding:12px 18px;display:flex;align-items:center;gap:12px}
        .tele-top img{height:30px}
        .tele-top .t{font-weight:800;color:#2a2566;font-size:.95rem}
        .tele-top .s{margin-left:auto;font-size:.78rem;color:#64748b;display:inline-flex;align-items:center;gap:5px}
        .tele-main{max-width:1000px;margin:0 auto;padding:24px 16px}
        .tele-input{width:100%;padding:.75rem 1rem;border:1px solid #e3e6f1;border-radius:12px;font:inherit}
        .doctor-input{width:100%;padding:.75rem 1rem;border:1px solid #e3e6f1;border-radius:12px;font:inherit}
        .consent{max-width:480px;margin:2vh auto;background:#fff;border:1px solid #e8ecf4;border-radius:20px;padding:28px;text-align:center;box-shadow:0 24px 60px -30px rgba(20,18,60,.4)}
        .consent .ic{width:64px;height:64px;border-radius:18px;margin:0 auto 16px;display:grid;place-items:center;background:linear-gradient(135deg,#eef0ff,#f3e9fb);color:#4f46e5}
        .consent .ic svg{width:32px;height:32px}
        .consent h1{font-family:'Outfit',sans-serif;font-size:1.4rem;color:#2a2566;margin-bottom:6px}
        .consent .doc{color:#475569;font-weight:600;margin-bottom:16px}
        .consent ul{text-align:left;list-style:none;margin:0 0 20px;font-size:.9rem;color:#475569;line-height:1.5}
        .consent li{display:flex;gap:9px;margin:9px 0}
        .consent li svg{flex:none;width:18px;height:18px;color:#0a7a52;margin-top:1px}
        .errbox{max-width:440px;margin:6vh auto;background:#fff;border-radius:20px;padding:32px;text-align:center;box-shadow:0 24px 60px -30px rgba(20,18,60,.4)}
        .errbox .ic{width:70px;height:70px;border-radius:50%;background:#fdeaea;color:#dc2626;display:grid;place-items:center;margin:0 auto 14px}
        .errbox h1{font-family:'Outfit',sans-serif;font-size:1.3rem;color:#b91c1c;margin-bottom:6px}
        .errbox p{color:#64748b;font-size:.92rem}
    </style>
</head>
<body>
    <header class="tele-top">
        <img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas">
        <span class="t">Teleconsulta</span>
        <span class="s"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Conexión segura</span>
    </header>

    <main class="tele-main">
    <?php if ($error || !$data): ?>
        <div class="errbox">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg></div>
            <h1>No se pudo abrir</h1>
            <p><?= e($error ?: 'La teleconsulta no está disponible. Pide al hospital un enlace nuevo.') ?></p>
        </div>
    <?php else: ?>
        <div id="patient-consent" class="consent">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2"/></svg></div>
            <h1>Tu teleconsulta</h1>
            <p class="doc">con Dr/a. <?= e($data['doctor_name'] ?? '') ?><?= !empty($data['specialty']) ? ' · ' . e($data['specialty']) : '' ?></p>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg> Vas a iniciar una videollamada médica privada.</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg> La conexión es cifrada y <strong>no se graba</strong>.</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg> Permite el acceso a tu cámara y micrófono cuando el navegador lo pida.</li>
            </ul>
            <button type="button" class="tele-btn-primary" id="patient-accept">Acepto y entrar</button>
        </div>

        <div id="tele-container" hidden>
            <?php require __DIR__ . '/includes/teleconsulta_stage.php'; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/livekit-client@2/dist/livekit-client.umd.min.js"></script>
        <script src="<?= e(base_url('assets/js/portal-medico-teleconsult.js')) ?>?v=<?= @filemtime(__DIR__ . '/assets/js/portal-medico-teleconsult.js') ?>"></script>
        <script>
            document.getElementById('patient-accept').addEventListener('click', () => {
                document.getElementById('patient-consent').hidden = true;
                document.getElementById('tele-container').hidden = false;
                HGLCTele.setup({
                    url: <?= json_encode($data['url'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
                    token: <?= json_encode($data['token'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
                    role: 'patient'
                });
            });
        </script>
    <?php endif; ?>
    </main>
    <script defer src="/assets/js/track.js"></script>
</body>
</html>
