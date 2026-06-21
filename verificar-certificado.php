<?php
/**
 * Verificación pública de autenticidad de CERTIFICADOS MÉDICOS.
 * Se abre desde el QR impreso en el PDF: /verificar-certificado?token=XXXX
 * Consulta el endpoint público del API interno. No expone datos del paciente.
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/portal_client.php';

$token = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['token'] ?? ''));
$data = null;
$error = null;

if (strlen($token) < 16) {
    $error = 'No se proporcionó un código de verificación válido.';
} else {
    $res = portal_api_call('GET', '/portal-doctor/verify-cert/' . $token, []);
    if (!empty($res['ok'])) {
        $data = $res['data'] ?? null;
    } else {
        $error = $res['message'] ?? 'No se pudo verificar el documento en este momento.';
    }
}
$valid   = is_array($data) && !empty($data['valid']);
$revoked = is_array($data) && !empty($data['revoked']);
$logo    = base_url('assets/site/logo.png');
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de certificado · Hospital General Las Colinas</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="<?= e(base_url('assets/site/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,sans-serif;
            background:
              radial-gradient(1100px 600px at -10% -20%, rgba(38,33,97,.12), transparent 60%),
              radial-gradient(900px 600px at 110% 0%, rgba(93,163,52,.12), transparent 55%), #eef1f7;
            color:#101728;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{background:#fff;border-radius:22px;max-width:470px;width:100%;overflow:hidden;
            box-shadow:0 30px 80px -34px rgba(20,18,60,.45),0 8px 24px -16px rgba(20,18,60,.2)}
        .bar{height:6px;background:linear-gradient(90deg,#262161,#5da334)}
        .top{padding:24px 28px 0;text-align:center}
        .top img{height:44px;width:auto}
        .state{padding:22px 28px;text-align:center}
        .ico{width:74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
        .ico svg{width:38px;height:38px;stroke-width:2.4}
        .ok .ico{background:#eaf6e1}.ok .ico svg{color:#5da334}
        .warn .ico{background:#fef3e2}.warn .ico svg{color:#b45309}
        .bad .ico{background:#fdeaea}.bad .ico svg{color:#dc2626}
        .state h1{font-family:'Outfit',sans-serif;font-size:1.4rem;font-weight:800;letter-spacing:-.02em}
        .ok h1{color:#3f7a1f}.warn h1{color:#b45309}.bad h1{color:#b91c1c}
        .state p{color:#64748b;font-size:.92rem;margin-top:6px;line-height:1.5}
        .details{margin:6px 28px 0;border-top:1px solid #eef1f6}
        .row{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #f1f3f8;font-size:.92rem}
        .row .k{color:#8a93a6;font-weight:600}
        .row .v{color:#1e293b;font-weight:700;text-align:right}
        .foot{padding:18px 28px 24px;text-align:center;color:#94a3b8;font-size:.74rem;line-height:1.5}
        .foot strong{color:#475569}
    </style>
</head>
<body>
    <div class="card">
        <div class="bar"></div>
        <div class="top"><img src="<?= e($logo) ?>" alt="Hospital General Las Colinas"></div>
        <?php if ($valid): ?>
            <div class="state ok">
                <div class="ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                <h1>Documento auténtico</h1>
                <p>Este certificado fue emitido por el Hospital General Las Colinas.</p>
            </div>
            <div class="details">
                <?php if (!empty($data['type'])): ?><div class="row"><span class="k">Tipo</span><span class="v"><?= e($data['type']) ?></span></div><?php endif; ?>
                <?php if (!empty($data['folio'])): ?><div class="row"><span class="k">Folio</span><span class="v"><?= e($data['folio']) ?></span></div><?php endif; ?>
                <?php if (!empty($data['doctor'])): ?><div class="row"><span class="k">Médico</span><span class="v"><?= e($data['doctor']) ?></span></div><?php endif; ?>
                <?php if (!empty($data['specialty'])): ?><div class="row"><span class="k">Especialidad</span><span class="v"><?= e($data['specialty']) ?></span></div><?php endif; ?>
                <?php if (!empty($data['exequatur'])): ?><div class="row"><span class="k">Exequátur</span><span class="v"><?= e($data['exequatur']) ?></span></div><?php endif; ?>
                <?php if (!empty($data['issued_at'])): ?><div class="row"><span class="k">Fecha de emisión</span><span class="v"><?= e($data['issued_at']) ?></span></div><?php endif; ?>
            </div>
            <div class="foot">Por privacidad, no se muestran datos del paciente ni el contenido clínico.<br><strong>Hospital General Las Colinas</strong> · Santiago, RD</div>
        <?php elseif ($revoked): ?>
            <div class="state warn">
                <div class="ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg></div>
                <h1>Certificado anulado</h1>
                <p>Este certificado existe pero fue <strong>anulado</strong> por el médico emisor y ya no es válido.</p>
            </div>
            <?php if (!empty($data['folio'])): ?><div class="details"><div class="row"><span class="k">Folio</span><span class="v"><?= e($data['folio']) ?></span></div></div><?php endif; ?>
            <div class="foot"><strong>Hospital General Las Colinas</strong> · Santiago, RD</div>
        <?php else: ?>
            <div class="state bad">
                <div class="ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                <h1>No verificado</h1>
                <p><?= e($error ?: 'No encontramos un certificado con este código. Verifica que escaneaste el QR correcto.') ?></p>
            </div>
            <div class="foot"><strong>Hospital General Las Colinas</strong> · Santiago, RD</div>
        <?php endif; ?>
    </div>
</body>
</html>
