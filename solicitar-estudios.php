<?php
/**
 * Página pública: "Solicitar autorización de estudios" (Imágenes / Laboratorio).
 * Pensada para pacientes externos (referidos de otro centro) que llegan con su
 * orden médica y su seguro. Si ya hay sesión del portal, redirige a la versión
 * autenticada (datos precargados). Si no, muestra el formulario de invitado:
 * al enviar se crea una cuenta ligera y el paciente queda dentro del portal.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';
require __DIR__ . '/includes/portal_session.php';
require __DIR__ . '/includes/study_request_form.php';

portal_session_start();
if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/solicitar-estudios.php'));
    exit;
}

// Catálogo de estudios desde la fuente única (Apoyo Diagnóstico).
$diag = $services['diagnostico']['items'] ?? [];
$labNames = ['Laboratorio Clínico y Banco de Sangre', 'Anatomía Patológica'];
$labList = array_values(array_filter($diag, fn($n) => in_array($n, $labNames, true)));
$imgList = array_values(array_filter($diag, fn($n) => !in_array($n, $labNames, true)));

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    @filemtime(__DIR__ . '/assets/css/portal.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/estudios.css') ?: 0,
    @filemtime(__DIR__ . '/assets/js/solicitar-estudios.js') ?: 0
);
$hcaptchaSiteKey = defined('HCAPTCHA_SITE_KEY') ? HCAPTCHA_SITE_KEY : '';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar autorización de estudios | Hospital General Las Colinas</title>
    <meta name="description"
        content="Solicita en línea la autorización de tus estudios de imágenes y laboratorio con tu seguro. Sube tu orden médica y carnet; gestionamos tu copago. Hospital General Las Colinas, Santiago, RD.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/estudios.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php if ($hcaptchaSiteKey): ?>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <?php endif; ?>
</head>

<body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="portal-shell portal-shell-app" style="grid-template-columns: 1fr; max-width: 980px">
        <div class="portal-main">
            <header class="portal-header se-hero">
                <div>
                    <p class="section-label">Imágenes y Laboratorio</p>
                    <h1>Solicita la autorización de tus estudios</h1>
                    <p class="portal-subtitle">¿Vienes referido de otro centro con tu orden médica? Súbela aquí con tu seguro y
                        nuestro equipo gestiona la autorización y te dice tu copago. Sin filas.</p>
                </div>
                <a href="<?= e(base_url('portal/login.php?next=' . urlencode(base_url('portal/solicitar-estudios.php')))) ?>" class="btn btn-outline">
                    <i data-lucide="user-round" class="h-4 w-4"></i> Ya tengo cuenta
                </a>
            </header>

            <ol class="se-progress" data-se-progress aria-hidden="true"></ol>

            <?php
            render_study_request_form([
                'mode'             => 'guest',
                'prefill'          => [],
                'imaging'          => $imgList,
                'lab'              => $labList,
                'insurers'         => $insurers,
                'hcaptcha_sitekey' => $hcaptchaSiteKey,
            ]);
            ?>
        </div>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>

    <script>
        window.SE_CONFIG = {
            mode: 'guest',
            catalogUrl: <?= json_encode(base_url('api/study-catalog.php'), JSON_UNESCAPED_SLASHES) ?>,
            guestSubmitUrl: <?= json_encode(base_url('api/guest-study-request.php'), JSON_UNESCAPED_SLASHES) ?>,
            proxyUrl: <?= json_encode(base_url('api/portal-proxy.php'), JSON_UNESCAPED_SLASHES) ?>,
            portalHome: <?= json_encode(base_url('portal/mis-solicitudes.php'), JSON_UNESCAPED_SLASHES) ?>,
            loginUrl: <?= json_encode(base_url('portal/login.php?next=' . urlencode(base_url('portal/mis-solicitudes.php'))), JSON_UNESCAPED_SLASHES) ?>,
            csrfToken: ''
        };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>if (window.lucide) lucide.createIcons();</script>
    <script src="<?= e(base_url('assets/js/solicitar-estudios.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script defer src="/assets/js/track.js"></script>
</body>

</html>
