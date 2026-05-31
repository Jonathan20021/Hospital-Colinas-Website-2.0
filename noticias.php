<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/news.php';
require __DIR__ . '/includes/public-layout.php';

news_ensure_schema();

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 9;
$activeCategory = trim($_GET['cat'] ?? 'all');
$searchQuery = trim($_GET['q'] ?? '');

$total = news_count_published($activeCategory, $searchQuery ?: null);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$items = news_query_published($perPage, $offset, $activeCategory, $searchQuery ?: null);
$featured = ($activeCategory === 'all' && $searchQuery === '' && $page === 1) ? news_query_published(1, 0, null, null) : [];
$featuredItem = $featured[0] ?? null;
if ($featuredItem && $items && (int) $items[0]['id'] === (int) $featuredItem['id']) {
    array_shift($items);
}
$categories = news_distinct_categories();
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias y sala de prensa | Hospital General Las Colinas</title>
    <meta name="description"
        content="Sala de prensa del Hospital General Las Colinas. Noticias institucionales, alianzas, servicios y novedades del hospital en Santiago, RD.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="Noticias y sala de prensa | Hospital General Las Colinas">
    <meta property="og:description"
        content="Sigue las noticias institucionales del Hospital General Las Colinas en Santiago, RD.">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($assets['hero'])) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "<?= e(absolute_url()) ?>"},
            {"@type": "ListItem", "position": 2, "name": "Noticias", "item": "<?= e(absolute_url('noticias')) ?>"}
        ]
    }
    </script>
</head>

<body class="bg-white font-sans text-slate-950 antialiased">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header id="siteHeader" class="site-header">
        <div class="utility-bar">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="tel:18098060444" class="utility-link">
                    <i data-lucide="phone" class="h-4 w-4"></i>
                    <?= e($contact['phone']) ?>
                </a>
                <div class="hidden items-center gap-7 md:flex">
                    <a href="<?= e(base_url('#contacto')) ?>" class="utility-link utility-emergency">
                        <i data-lucide="cross" class="h-4 w-4"></i>
                        Emergencias 24/7
                    </a>
                    <a href="<?= e(base_url('#pacientes')) ?>" class="utility-link">
                        <i data-lucide="users-round" class="h-4 w-4"></i>
                        Pacientes y visitantes
                    </a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="utility-link">
                        <i data-lucide="user-round-check" class="h-4 w-4"></i>
                        Profesionales médicos
                    </a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="main-nav-inner mx-auto flex h-[110px] max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <a href="<?= e(base_url('#inicio')) ?>" class="brand-link" aria-label="Hospital General Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas"
                        class="brand-logo">
                </a>
                <nav class="nav-primary" aria-label="Navegación principal">
                    <a href="<?= e(base_url('#inicio')) ?>" class="nav-link">Inicio</a>
                    <div class="nav-dropdown" data-nav-dropdown>
                        <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true"
                            aria-expanded="false">
                            Hospital
                            <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="nav-dropdown-menu" role="menu">
                            <a href="<?= e(base_url('#nosotros')) ?>" role="menuitem"><i data-lucide="building-2"
                                    class="h-4 w-4"></i>Nosotros</a>
                            <a href="<?= e(base_url('#liderazgo')) ?>" role="menuitem"><i data-lucide="users-round"
                                    class="h-4 w-4"></i>Liderazgo institucional</a>
                            <a href="<?= e(base_url('#instalaciones')) ?>" role="menuitem"><i data-lucide="hospital"
                                    class="h-4 w-4"></i>Instalaciones</a>
                            <a href="<?= e(base_url('#pacientes')) ?>" role="menuitem"><i data-lucide="heart-handshake"
                                    class="h-4 w-4"></i>Pacientes</a>
                            <a href="<?= e(base_url('#contacto')) ?>" role="menuitem"><i data-lucide="map-pin"
                                    class="h-4 w-4"></i>Contacto</a>
                        </div>
                    </div>
                    <a href="<?= e(base_url('#servicios')) ?>" class="nav-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="nav-link">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="nav-link is-active">Noticias</a>
                </nav>
                <div class="nav-actions">
                    <button type="button" class="js-open-appointment btn btn-green nav-cta">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </button>
                </div>
                <button id="menuToggle" type="button" class="mobile-toggle" aria-label="Abrir menú"
                    aria-expanded="false">
                    <i data-lucide="menu" class="menu-icon h-5 w-5"></i>
                    <i data-lucide="x" class="close-icon hidden h-5 w-5"></i>
                </button>
            </div>
            <div id="mobileMenu" class="mobile-menu hidden">
                <nav class="mobile-menu-inner" aria-label="Navegación móvil">
                    <a href="<?= e(base_url('#inicio')) ?>" class="mobile-link">Inicio</a>
                    <details class="mobile-group">
                        <summary>Hospital <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="mobile-sub">
                            <a href="<?= e(base_url('#nosotros')) ?>" class="mobile-sub-link">Nosotros</a>
                            <a href="<?= e(base_url('#liderazgo')) ?>" class="mobile-sub-link">Liderazgo
                                institucional</a>
                            <a href="<?= e(base_url('#instalaciones')) ?>" class="mobile-sub-link">Instalaciones</a>
                            <a href="<?= e(base_url('#pacientes')) ?>" class="mobile-sub-link">Pacientes</a>
                            <a href="<?= e(base_url('#contacto')) ?>" class="mobile-sub-link">Contacto</a>
                        </div>
                    </details>
                    <a href="<?= e(base_url('#servicios')) ?>" class="mobile-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="mobile-link">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="mobile-link is-active">Noticias</a>
                    <button type="button" class="js-open-appointment mt-3 btn btn-green w-full justify-center">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </button>
                </nav>
            </div>
        </div>
    </header>

    <main id="contenido">
        <section class="news-hero">
            <div class="news-hero-bg" aria-hidden="true"></div>
            <div class="news-hero-shell">
                <span class="news-hero-kicker">
                    <i data-lucide="newspaper" class="h-4 w-4"></i>
                    Sala de prensa
                </span>
                <h1>Noticias del Hospital Las Colinas</h1>
                <p>Información oficial, alianzas estratégicas, servicios nuevos y novedades institucionales del Hospital
                    General Las Colinas.</p>

                <form class="news-search" method="get" action="<?= e(base_url('noticias')) ?>" role="search">
                    <i data-lucide="search" class="h-5 w-5"></i>
                    <input type="search" name="q" placeholder="Buscar en noticias..." value="<?= e($searchQuery) ?>">
                    <?php if ($activeCategory !== 'all'): ?>
                        <input type="hidden" name="cat" value="<?= e($activeCategory) ?>">
                    <?php endif; ?>
                    <button type="submit">
                        Buscar
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </form>
            </div>
        </section>

        <section class="news-shell">
            <div class="news-filters" aria-label="Filtrar por categoría">
                <a href="<?= e(base_url('noticias')) ?>"
                    class="news-filter <?= $activeCategory === 'all' ? 'is-active' : '' ?>">
                    <i data-lucide="layers" class="h-3.5 w-3.5"></i>
                    Todas <span><?= (int) news_count_published() ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= e(base_url('noticias?cat=' . urlencode($cat['category']))) ?>"
                        class="news-filter <?= $activeCategory === $cat['category'] ? 'is-active' : '' ?>">
                        <i data-lucide="<?= e(news_category_icon($cat['category'])) ?>" class="h-3.5 w-3.5"></i>
                        <?= e($cat['category']) ?> <span><?= (int) $cat['cnt'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($featuredItem): ?>
                <article class="news-featured">
                    <a href="<?= e(base_url('noticias/' . $featuredItem['slug'])) ?>" class="news-featured-media">
                        <?php if (!empty($featuredItem['cover_image'])): ?>
                            <img src="<?= e(base_url($featuredItem['cover_image'])) ?>" alt="<?= e($featuredItem['title']) ?>"
                                loading="eager">
                        <?php else: ?>
                            <img src="<?= e(base_url($assets['hero'])) ?>" alt="<?= e($featuredItem['title']) ?>"
                                loading="eager">
                        <?php endif; ?>
                        <span class="news-featured-tag">★ Destacada</span>
                    </a>
                    <div class="news-featured-body">
                        <div class="news-meta">
                            <span class="news-cat-pill"><i
                                    data-lucide="<?= e(news_category_icon($featuredItem['category'])) ?>"
                                    class="h-3.5 w-3.5"></i><?= e($featuredItem['category']) ?></span>
                            <time><?= e(news_format_date($featuredItem['published_at'])) ?></time>
                            <span class="news-reading"><i data-lucide="clock"
                                    class="h-3.5 w-3.5"></i><?= news_reading_time($featuredItem['content']) ?> min
                                lectura</span>
                        </div>
                        <h2><a
                                href="<?= e(base_url('noticias/' . $featuredItem['slug'])) ?>"><?= e($featuredItem['title']) ?></a>
                        </h2>
                        <p><?= e($featuredItem['excerpt']) ?></p>
                        <a href="<?= e(base_url('noticias/' . $featuredItem['slug'])) ?>" class="btn btn-green">
                            Leer artículo
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                </article>
            <?php endif; ?>

            <?php if (!$items && !$featuredItem): ?>
                <div class="news-empty">
                    <i data-lucide="search-x" class="h-6 w-6"></i>
                    <strong>No encontramos noticias</strong>
                    <p>Prueba con otra categoría o término de búsqueda.</p>
                    <a href="<?= e(base_url('noticias')) ?>" class="btn btn-outline">Ver todas las noticias</a>
                </div>
            <?php else: ?>
                <div class="news-grid">
                    <?php foreach ($items as $item): ?>
                        <article class="news-card">
                            <a href="<?= e(base_url('noticias/' . $item['slug'])) ?>" class="news-card-media">
                                <?php if (!empty($item['cover_image'])): ?>
                                    <img src="<?= e(base_url($item['cover_image'])) ?>" alt="<?= e($item['title']) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <span class="news-card-fallback"><i
                                            data-lucide="<?= e(news_category_icon($item['category'])) ?>"></i></span>
                                <?php endif; ?>
                                <span class="news-card-cat"><?= e($item['category']) ?></span>
                            </a>
                            <div class="news-card-body">
                                <time><?= e(news_format_date($item['published_at'])) ?></time>
                                <h3><a href="<?= e(base_url('noticias/' . $item['slug'])) ?>"><?= e($item['title']) ?></a></h3>
                                <p><?= e($item['excerpt']) ?></p>
                                <a href="<?= e(base_url('noticias/' . $item['slug'])) ?>" class="news-card-link">
                                    Leer más
                                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <nav class="news-pagination" aria-label="Paginación">
                    <?php
                    $base = base_url('noticias');
                    $params = [];
                    if ($activeCategory !== 'all')
                        $params['cat'] = $activeCategory;
                    if ($searchQuery)
                        $params['q'] = $searchQuery;
                    $buildUrl = function ($p) use ($base, $params) {
                        $q = array_merge($params, ['p' => $p]);
                        return $base . '?' . http_build_query($q);
                    };
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= e($buildUrl($page - 1)) ?>" class="news-page-link">
                            <i data-lucide="chevron-left" class="h-4 w-4"></i> Anterior
                        </a>
                    <?php endif; ?>
                    <span class="news-page-info">Página <?= $page ?> de <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e($buildUrl($page + 1)) ?>" class="news-page-link">
                            Siguiente <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>

    <div id="appointmentModal" class="modal-shell hidden" role="dialog" aria-modal="true">
        <div class="modal-panel">
            <div class="modal-header">
                <div>
                    <h2>Agendar cita</h2>
                    <p>Completa tus datos y te contactaremos.</p>
                </div>
                <button type="button" class="js-close-appointment modal-close" aria-label="Cerrar">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="appointmentForm" class="space-y-4 p-6" action="<?= e(base_url('api/appointment.php')) ?>"
                method="post">
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div>
                    <label for="name" class="form-label">Nombre completo</label>
                    <input id="name" name="name" type="text" required class="form-input">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="form-label">Teléfono</label>
                        <input id="phone" name="phone" type="tel" required class="form-input">
                    </div>
                    <div>
                        <label for="date" class="form-label">Fecha preferida</label>
                        <input id="date" name="date" type="date" required class="form-input">
                    </div>
                </div>
                <input type="hidden" name="specialty" value="Orientación general">
                <div id="appointmentStatus" class="hidden rounded-md px-4 py-3 text-sm font-bold"></div>
                <button type="submit" class="btn btn-green w-full justify-center">Enviar solicitud</button>
            </form>
        </div>
    </div>

    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>

</html>