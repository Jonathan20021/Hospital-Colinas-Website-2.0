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
$page = site_page_by_slug($slug, $services, $assets, $contact, $patientRights, $patientDuties, $floors);

if (!$page) {
    http_response_code(404);
    $page = [
        'title' => 'Página no encontrada',
        'nav' => 'No encontrada',
        'active' => '',
        'kicker' => 'Hospital General Las Colinas',
        'summary' => 'La página solicitada no está disponible. Puedes volver al inicio o explorar los servicios del hospital.',
        'image' => $assets['hero'],
        'sections' => [
            ['icon' => 'home', 'title' => 'Volver al inicio', 'text' => 'Regresa a la página principal para continuar navegando.'],
            ['icon' => 'stethoscope', 'title' => 'Explorar servicios', 'text' => 'Consulta el directorio clínico y las páginas de servicios disponibles.'],
        ],
        'links' => [
            ['label' => 'Ir al inicio', 'href' => base_url(), 'icon' => 'home'],
            ['label' => 'Ver servicios', 'href' => base_url('servicios'), 'icon' => 'stethoscope'],
        ],
    ];
}

$description = $page['summary'];
$type = $page['type'] ?? 'standard';
$serviceCatalog = service_pages_catalog($services, $assets);
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page['title']) ?> | Hospital General Las Colinas</title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="<?= e($page['title']) ?> | Hospital General Las Colinas">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($page['image'])) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
</head>

<body class="bg-white font-sans text-slate-950 antialiased content-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, $page['active'] ?? ''); ?>

    <main id="contenido">
        <section class="content-hero">
            <div class="content-hero-shell">
                <div class="content-hero-copy">
                    <nav class="content-breadcrumb" aria-label="Breadcrumb">
                        <a href="<?= e(base_url()) ?>">Inicio</a>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                        <span><?= e($page['nav']) ?></span>
                    </nav>
                    <p class="section-label"><?= e($page['kicker']) ?></p>
                    <h1><?= e($page['title']) ?></h1>
                    <p><?= e($page['summary']) ?></p>
                    <?php if (!empty($page['links'])): ?>
                        <div class="content-hero-actions">
                            <?php foreach ($page['links'] as $link): ?>
                                <a href="<?= e($link['href']) ?>" class="btn btn-outline btn-lg" <?= !empty($link['external']) ? 'target="_blank" rel="noopener"' : '' ?>>
                                    <i data-lucide="<?= e($link['icon']) ?>" class="h-4 w-4"></i>
                                    <?= e($link['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <figure class="content-hero-media">
                    <img src="<?= e(base_url($page['image'])) ?>" alt="<?= e($page['title']) ?>" loading="eager">
                </figure>
            </div>
            <?php if (!empty($page['stats'])): ?>
                <div class="content-stat-strip" aria-label="Indicadores">
                    <?php foreach ($page['stats'] as $stat): ?>
                        <article>
                            <strong><?= e($stat['value']) ?></strong>
                            <span><?= e($stat['label']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="content-body">
            <?php if (!empty($page['sections'])): ?>
                <div class="content-section-grid">
                    <?php foreach ($page['sections'] as $section): ?>
                        <article class="content-info-card">
                            <span><i data-lucide="<?= e($section['icon']) ?>" class="h-5 w-5"></i></span>
                            <h2><?= e($section['title']) ?></h2>
                            <p><?= e($section['text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($type === 'legal' && !empty($page['articles'])): ?>
                <div class="legal-doc">
                    <aside class="legal-toc">
                        <p class="legal-toc-title">Contenido</p>
                        <nav aria-label="Índice del documento">
                            <ol>
                                <?php foreach ($page['articles'] as $article): ?>
                                    <li><a href="#<?= e($article['id']) ?>"><?= e($article['num']) ?>. <?= e($article['title']) ?></a></li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                        <?php if (!empty($page['updated'])): ?>
                            <p class="legal-toc-meta">
                                Última actualización: <strong><?= e($page['updated']) ?></strong>
                                <?php if (!empty($page['version'])): ?><br>Versión <?= e($page['version']) ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </aside>

                    <div class="legal-content">
                        <?php if (!empty($page['legal_intro'])): ?>
                            <p class="legal-intro"><?= e($page['legal_intro']) ?></p>
                        <?php endif; ?>

                        <?php foreach ($page['articles'] as $article): ?>
                            <article class="legal-article" id="<?= e($article['id']) ?>">
                                <h2><span class="legal-num"><?= e($article['num']) ?>.</span> <?= e($article['title']) ?></h2>
                                <?php foreach ($article['blocks'] as $block): ?>
                                    <?php if (isset($block['sub'])): ?>
                                        <h3><?= e($block['sub']) ?></h3>
                                    <?php elseif (isset($block['p'])): ?>
                                        <p><?= e($block['p']) ?></p>
                                    <?php elseif (isset($block['note'])): ?>
                                        <div class="legal-note"><i data-lucide="alert-triangle" class="h-4 w-4"></i><span><?= e($block['note']) ?></span></div>
                                    <?php elseif (isset($block['list'])): ?>
                                        <ul><?php foreach ($block['list'] as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul>
                                    <?php elseif (isset($block['olist'])): ?>
                                        <ol class="legal-olist"><?php foreach ($block['olist'] as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ol>
                                    <?php elseif (isset($block['deflist'])): ?>
                                        <dl class="legal-deflist"><?php foreach ($block['deflist'] as $term => $def): ?><dt><?= e($term) ?></dt><dd><?= e($def) ?></dd><?php endforeach; ?></dl>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </article>
                        <?php endforeach; ?>

                        <p class="legal-disclaimer">
                            Este documento es de carácter informativo. Para consultas relacionadas, escríbenos a
                            <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($slug === 'seguros-aceptados' && !empty($insurers)): ?>
                <section class="content-insurers" aria-labelledby="insurersListTitle">
                    <div class="content-insurers-head">
                        <p class="section-label"><i data-lucide="shield-check" class="h-4 w-4"></i> Convenios y aseguradoras</p>
                        <h2 id="insurersListTitle">Seguros que aceptamos</h2>
                        <p>Trabajamos con las principales ARS del país. Si no ves la tuya, escríbenos y te orientamos sobre cobertura y autorizaciones.</p>
                    </div>
                    <div class="content-insurers-grid" role="list">
                        <?php foreach ($insurers as $insurer): ?>
                            <article class="content-insurer-card" role="listitem">
                                <div class="content-insurer-logo">
                                    <img src="<?= e(base_url($insurersDir . $insurer['file'])) ?>" alt="<?= e($insurer['name']) ?>" loading="lazy">
                                </div>
                                <span><?= e($insurer['name']) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="content-insurers-cta">
                        <span><i data-lucide="badge-check" class="h-4 w-4"></i> Cobertura ambulatoria y hospitalaria</span>
                        <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="btn btn-green">
                            <i data-lucide="message-circle" class="h-4 w-4"></i>
                            ¿No ves tu ARS? Escríbenos
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($type === 'services-index'): ?>
                <div class="content-service-directory">
                    <?php foreach ($services as $group): ?>
                        <article class="content-service-group">
                            <div>
                                <span><i data-lucide="<?= e($group['icon']) ?>" class="h-5 w-5"></i></span>
                                <h2><?= e($group['label']) ?></h2>
                                <p><?= e($group['description']) ?></p>
                            </div>
                            <ul>
                                <?php foreach ($group['items'] as $item): ?>
                                    <li>
                                        <a href="<?= e(service_url($item)) ?>">
                                            <?= e($item) ?>
                                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($type === 'sitemap'): ?>
                <div class="content-sitemap">
                    <article>
                        <h2>El Hospital</h2>
                        <a href="<?= e(base_url('nosotros')) ?>">Nosotros</a>
                        <a href="<?= e(base_url('liderazgo-institucional')) ?>">Liderazgo institucional</a>
                        <a href="<?= e(base_url('instalaciones')) ?>">Instalaciones</a>
                        <a href="<?= e(base_url('pacientes')) ?>">Pacientes y visitantes</a>
                        <a href="<?= e(base_url('contacto')) ?>">Contacto</a>
                    </article>
                    <article>
                        <h2>Pacientes y visitantes</h2>
                        <a href="<?= e(base_url('tu-visita')) ?>">Tu visita</a>
                        <a href="<?= e(base_url('preparacion-para-tu-cita')) ?>">Preparación para tu cita</a>
                        <a href="<?= e(base_url('seguros-aceptados')) ?>">Seguros aceptados</a>
                        <a href="<?= e(base_url('derechos-y-deberes')) ?>">Derechos y deberes</a>
                        <a href="<?= e(base_url('preguntas-frecuentes')) ?>">Preguntas frecuentes</a>
                    </article>
                    <article>
                        <h2>Contenido</h2>
                        <a href="<?= e(base_url('servicios')) ?>">Servicios</a>
                        <a href="<?= e(base_url('directorio-medico')) ?>">Directorio médico</a>
                        <a href="<?= e(base_url('noticias')) ?>">Noticias</a>
                        <a href="<?= e(base_url('repositorio')) ?>">Repositorio digital</a>
                    </article>
                    <article>
                        <h2>Servicios en línea</h2>
                        <a href="<?= e(base_url('agendar')) ?>">Agendar cita</a>
                        <a href="<?= e(base_url('teleconsulta')) ?>">Teleconsulta</a>
                        <a href="<?= e(base_url('solicitar-estudios')) ?>">Solicitar estudios</a>
                        <a href="<?= e(base_url('ver-resultados')) ?>">Ver resultados</a>
                        <a href="<?= e(base_url('verificar-certificado')) ?>">Verificar certificado</a>
                        <a href="<?= e(base_url('verificar-receta')) ?>">Verificar receta</a>
                    </article>
                    <article>
                        <h2>Portales</h2>
                        <a href="<?= e(base_url('portal/')) ?>">Portal del Paciente</a>
                        <a href="<?= e(base_url('portal-medico/')) ?>">Portal Médico</a>
                    </article>
                    <article>
                        <h2>Legal</h2>
                        <a href="<?= e(base_url('politica-de-privacidad')) ?>">Política de privacidad</a>
                        <a href="<?= e(base_url('terminos-de-uso')) ?>">Términos de uso</a>
                        <a href="<?= e(base_url('mapa-del-sitio')) ?>">Mapa del sitio</a>
                    </article>
                    <article class="content-sitemap-wide">
                        <h2>Servicios individuales</h2>
                        <div>
                            <?php foreach ($serviceCatalog as $service): ?>
                                <a href="<?= e(base_url('servicios/' . $service['slug'])) ?>"><?= e($service['title']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
            <?php endif; ?>

            <?php if ($slug === 'contacto'): ?>
                <div class="content-map-panel">
                    <div>
                        <h2>Cómo llegar</h2>
                        <p><?= e($contact['address']) ?></p>
                        <a href="<?= e($contact['maps']) ?>" target="_blank" rel="noopener" class="btn btn-green">
                            <i data-lucide="navigation" class="h-4 w-4"></i>
                            Abrir mapa
                        </a>
                    </div>
                    <iframe title="Ubicación del Hospital General Las Colinas" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps?q=Hospital%20General%20Las%20Colinas%20Santiago&output=embed"></iframe>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php render_appointment_modal($services); ?>
    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>

</html>