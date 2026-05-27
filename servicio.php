<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

$slug = trim((string) ($_GET['slug'] ?? ''));
$service = service_page_by_slug($slug, $services, $assets);
$catalog = service_pages_catalog($services, $assets);

if (!$service) {
    http_response_code(404);
    $service = [
        'slug' => $slug,
        'title' => 'Servicio no encontrado',
        'group' => 'Servicios médicos',
        'icon' => 'search-x',
        'image' => $assets['hero'],
        'summary' => 'No encontramos el servicio solicitado. Puedes volver al directorio clínico para explorar las opciones disponibles.',
        'lead' => 'El directorio de servicios te permite navegar por especialidades, diagnóstico, procedimientos y atención inmediata.',
        'bullets' => ['Consulta el listado completo de servicios.', 'Usa el directorio médico para encontrar especialistas.', 'Contacta al hospital para orientación.'],
        'steps' => ['Volver al directorio de servicios.', 'Elegir el servicio correspondiente.', 'Solicitar orientación o cita.'],
    ];
}

$related = array_values(array_filter($catalog, static function (array $item) use ($service): bool {
    return ($item['group'] ?? '') === ($service['group'] ?? '') && ($item['slug'] ?? '') !== ($service['slug'] ?? '');
}));
$related = array_slice($related, 0, 4);
$isSpecialty = ($service['group'] ?? '') === 'Especialidades' || ($service['group'] ?? '') === 'Consulta Especializada';
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($service['title']) ?> | Hospital General Las Colinas</title>
    <meta name="description" content="<?= e($service['summary']) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="<?= e($service['title']) ?> | Hospital General Las Colinas">
    <meta property="og:description" content="<?= e($service['summary']) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($service['image'])) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "MedicalWebPage",
        "name": "<?= e($service['title']) ?>",
        "description": "<?= e($service['summary']) ?>",
        "url": "<?= e(canonical_url()) ?>",
        "about": {
            "@type": "MedicalProcedure",
            "name": "<?= e($service['title']) ?>"
        },
        "provider": {
            "@type": "Hospital",
            "name": "Hospital General Las Colinas",
            "telephone": "<?= e($contact['phone']) ?>"
        }
    }
    </script>
</head>
<body class="bg-white font-sans text-slate-950 antialiased service-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, 'servicios'); ?>

    <main id="contenido">
        <section class="service-hero">
            <div class="service-hero-shell">
                <div class="service-hero-copy">
                    <nav class="content-breadcrumb" aria-label="Breadcrumb">
                        <a href="<?= e(base_url()) ?>">Inicio</a>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                        <a href="<?= e(base_url('servicios')) ?>">Servicios</a>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                        <span><?= e($service['title']) ?></span>
                    </nav>
                    <span class="service-chip">
                        <i data-lucide="<?= e($service['icon']) ?>" class="h-4 w-4"></i>
                        <?= e($service['group']) ?>
                    </span>
                    <h1><?= e($service['title']) ?></h1>
                    <p><?= e($service['summary']) ?></p>
                    <div class="service-actions">
                        <button type="button" class="js-open-appointment btn btn-green btn-lg">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i>
                            Agendar u orientar
                        </button>
                        <a href="tel:18098060444" class="btn btn-outline btn-lg">
                            <i data-lucide="phone" class="h-4 w-4"></i>
                            Llamar
                        </a>
                    </div>
                </div>
                <figure class="service-hero-media">
                    <img src="<?= e(base_url($service['image'])) ?>" alt="<?= e($service['title']) ?>" loading="eager">
                    <figcaption>
                        <strong>Hospital General Las Colinas</strong>
                        <span><?= e($contact['phone']) ?> · Santiago, RD</span>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="service-detail">
            <div class="service-detail-main">
                <article class="service-story">
                    <p class="section-label">Qué puedes esperar</p>
                    <h2>Atención coordinada desde el primer contacto</h2>
                    <p><?= e($service['lead']) ?></p>
                    <ul>
                        <?php foreach ($service['bullets'] as $bullet): ?>
                            <li><i data-lucide="check" class="h-4 w-4"></i><?= e($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>

                <div class="service-process">
                    <?php foreach ($service['steps'] as $index => $step): ?>
                        <article>
                            <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <p><?= e($step) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="service-aside">
                <div class="service-contact-card">
                    <span><i data-lucide="calendar-check" class="h-4 w-4"></i> Solicita orientación</span>
                    <h2>¿Necesitas este servicio?</h2>
                    <p>Déjanos tus datos y el equipo de atención te orientará con disponibilidad, preparación y documentos.</p>
                    <button type="button" class="js-open-appointment btn btn-green">
                        Agendar ahora
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                    <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="service-whatsapp">
                        <i data-lucide="message-circle" class="h-4 w-4"></i>
                        WhatsApp
                    </a>
                </div>
            </aside>
        </section>

        <?php if (!empty($related)): ?>
            <section class="related-services">
                <div class="related-services-head">
                    <p class="section-label">También puede interesarte</p>
                    <h2>Servicios relacionados</h2>
                </div>
                <div class="related-services-grid">
                    <?php foreach ($related as $item): ?>
                        <a href="<?= e(base_url('servicios/' . $item['slug'])) ?>" class="related-service-card">
                            <span><i data-lucide="<?= e($item['icon']) ?>" class="h-5 w-5"></i></span>
                            <strong><?= e($item['title']) ?></strong>
                            <small><?= e($item['group']) ?></small>
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php render_appointment_modal($services, $isSpecialty ? $service['title'] : ''); ?>
    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>
</html>
