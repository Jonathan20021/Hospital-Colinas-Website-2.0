<?php
/**
 * /empleos/confirmar?token=… — confirma la suscripción al newsletter (doble opt-in).
 * Relaya el token por el puente de JENOFONTE y muestra el resultado.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';

$active = 'empleos';
$year = date('Y');
$assetVersion = (string) (@filemtime(__DIR__ . '/assets/css/empleos.css') ?: 0);

$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['token'] ?? ''));
$r = $token !== '' ? portal_api_call('GET', '/portal/empleos/confirmar?token=' . rawurlencode($token)) : ['ok' => false];
$ok = !empty($r['ok']);
$title = ($ok ? 'Suscripción confirmada' : 'No pudimos confirmar') . ' | Empleos — Hospital General Las Colinas';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#262161">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/empleos.css')) ?>?v=<?= e($assetVersion) ?>">
</head>

<body class="bg-white font-sans text-slate-950 antialiased empleos-page">
<?php render_public_header($assets, $contact, $active); ?>
<main class="emp-v2">
    <div class="emp-wrap">
        <div class="emp-empty">
            <?php if ($ok): ?>
                <span class="emp-empty-icon" style="background:rgba(93,163,52,.14);color:#4a8a28"><i data-lucide="badge-check"></i></span>
                <h2>¡Suscripción confirmada!</h2>
                <p>Listo. Te avisaremos por correo apenas publiquemos una nueva vacante. Puedes darte de baja cuando quieras desde cualquiera de esos correos.</p>
                <a class="emp-btn emp-btn-primary" href="<?= e(base_url('empleos')) ?>"><i data-lucide="briefcase"></i> Ver vacantes</a>
            <?php else: ?>
                <span class="emp-empty-icon"><i data-lucide="alert-circle"></i></span>
                <h2>No pudimos confirmar</h2>
                <p>El enlace no es válido o ya expiró. Vuelve a suscribirte y te enviaremos uno nuevo.</p>
                <a class="emp-btn emp-btn-primary" href="<?= e(base_url('empleos')) ?>#suscribir"><i data-lucide="bell-plus"></i> Suscribirme de nuevo</a>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php render_public_footer($assets, $contact, $year); ?>
<script src="https://unpkg.com/lucide@latest"></script>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
