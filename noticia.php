<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/news.php';

news_ensure_schema();

$slug = $_GET['slug'] ?? '';
$item = $slug ? news_by_slug($slug) : null;
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);
$year = date('Y');

if (!$item) {
    http_response_code(404);
}

if ($item) {
    news_increment_views((int) $item['id']);
}

$related = $item ? array_filter(news_query_published(4, 0, $item['category']), fn($r) => (int) $r['id'] !== (int) $item['id']) : [];
$related = array_slice($related, 0, 3);
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $item ? e($item['title']) : 'Noticia no encontrada' ?> | Hospital General Las Colinas</title>
    <meta name="description" content="<?= $item ? e($item['excerpt']) : 'Noticia no disponible.' ?>">
    <meta name="robots" content="<?= $item ? 'index, follow, max-image-preview:large' : 'noindex, follow' ?>">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">

    <?php if ($item): ?>
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="Hospital General Las Colinas">
        <meta property="og:title" content="<?= e($item['title']) ?>">
        <meta property="og:description" content="<?= e($item['excerpt']) ?>">
        <meta property="og:url" content="<?= e(canonical_url()) ?>">
        <meta property="og:locale" content="es_DO">
        <meta property="og:image" content="<?= e(absolute_url($item['cover_image'] ?: $assets['hero'])) ?>">
        <meta property="article:published_time" content="<?= e(date('c', strtotime($item['published_at']))) ?>">
        <meta property="article:section" content="<?= e($item['category']) ?>">
        <meta property="article:author" content="<?= e($item['author']) ?>">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= e($item['title']) ?>">
        <meta name="twitter:description" content="<?= e($item['excerpt']) ?>">
        <meta name="twitter:image" content="<?= e(absolute_url($item['cover_image'] ?: $assets['hero'])) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">

    <?php if ($item): ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "NewsArticle",
            "headline": <?= json_encode($item['title'], JSON_UNESCAPED_UNICODE) ?>,
            "description": <?= json_encode($item['excerpt'], JSON_UNESCAPED_UNICODE) ?>,
            "image": "<?= e(absolute_url($item['cover_image'] ?: $assets['hero'])) ?>",
            "datePublished": "<?= e(date('c', strtotime($item['published_at']))) ?>",
            "dateModified": "<?= e(date('c', strtotime($item['updated_at'] ?? $item['published_at']))) ?>",
            "articleSection": <?= json_encode($item['category'], JSON_UNESCAPED_UNICODE) ?>,
            "author": {"@type": "Organization", "name": <?= json_encode($item['author'], JSON_UNESCAPED_UNICODE) ?>},
            "publisher": {
                "@type": "Organization",
                "name": "Hospital General Las Colinas",
                "logo": {"@type": "ImageObject", "url": "<?= e(absolute_url($assets['logo'])) ?>"}
            },
            "mainEntityOfPage": "<?= e(canonical_url()) ?>"
        }
        </script>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "<?= e(absolute_url()) ?>"},
                {"@type": "ListItem", "position": 2, "name": "Noticias", "item": "<?= e(absolute_url('noticias')) ?>"},
                {"@type": "ListItem", "position": 3, "name": <?= json_encode($item['title'], JSON_UNESCAPED_UNICODE) ?>, "item": "<?= e(canonical_url()) ?>"}
            ]
        }
        </script>
    <?php endif; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased">
    <header class="profile-topbar">
        <div class="profile-topbar-inner">
            <a href="<?= e(base_url('#inicio')) ?>" class="brand-link" aria-label="Hospital General Las Colinas">
                <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas"
                    class="brand-logo h-14 w-auto max-w-[260px] object-contain">
            </a>
            <nav aria-label="Navegación">
                <a href="<?= e(base_url('noticias')) ?>">
                    <i data-lucide="newspaper" class="h-4 w-4"></i>
                    Sala de prensa
                </a>
                <a href="<?= e(base_url('directorio-medico')) ?>">
                    <i data-lucide="users-round" class="h-4 w-4"></i>
                    Directorio médico
                </a>
                <a href="<?= e(base_url('#contacto')) ?>">
                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                    Contacto
                </a>
                <button type="button" class="js-open-appointment profile-cta">
                    <i data-lucide="calendar-days" class="h-4 w-4"></i>
                    Agendar cita
                </button>
            </nav>
        </div>
    </header>

    <?php if (!$item): ?>
        <main class="profile-empty">
            <h1>Noticia no encontrada</h1>
            <p>La noticia que buscas no está disponible o fue removida.</p>
            <a href="<?= e(base_url('noticias')) ?>" class="btn btn-green">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Volver a la sala de prensa
            </a>
        </main>
    <?php else: ?>
        <main>
            <nav class="profile-crumbs" aria-label="Migas de pan">
                <a href="<?= e(base_url('#inicio')) ?>">Inicio</a>
                <span><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></span>
                <a href="<?= e(base_url('noticias')) ?>">Noticias</a>
                <span><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></span>
                <span><?= e(mb_strimwidth($item['title'], 0, 60, '…')) ?></span>
            </nav>

            <article class="news-article">
                <header class="news-article-header">
                    <span class="news-cat-pill">
                        <i data-lucide="<?= e(news_category_icon($item['category'])) ?>" class="h-3.5 w-3.5"></i>
                        <?= e($item['category']) ?>
                    </span>
                    <h1><?= e($item['title']) ?></h1>
                    <p class="news-article-lead"><?= e($item['excerpt']) ?></p>
                    <div class="news-article-meta">
                        <span><i data-lucide="calendar" class="h-4 w-4"></i>
                            <?= e(news_format_date($item['published_at'])) ?></span>
                        <span><i data-lucide="clock" class="h-4 w-4"></i> <?= news_reading_time($item['content']) ?> min
                            lectura</span>
                        <span><i data-lucide="user-round" class="h-4 w-4"></i> <?= e($item['author']) ?></span>
                    </div>
                </header>

                <?php if (!empty($item['cover_image'])): ?>
                    <figure class="news-article-cover">
                        <img src="<?= e(base_url($item['cover_image'])) ?>" alt="<?= e($item['title']) ?>" loading="eager">
                    </figure>
                <?php endif; ?>

                <div class="news-article-body">
                    <?= news_render_markdown($item['content']) ?>
                </div>

                <?php if ($item['source_url']): ?>
                    <p class="news-article-source">
                        <i data-lucide="link" class="h-4 w-4"></i>
                        Fuente original:
                        <a href="<?= e($item['source_url']) ?>" target="_blank"
                            rel="noopener nofollow"><?= e(parse_url($item['source_url'], PHP_URL_HOST) ?: $item['source_url']) ?></a>
                    </p>
                <?php endif; ?>

                <footer class="news-article-share">
                    <span>Comparte esta noticia</span>
                    <div>
                        <a href="https://wa.me/?text=<?= e(rawurlencode($item['title'] . ' — ' . canonical_url())) ?>"
                            target="_blank" rel="noopener" aria-label="WhatsApp">
                            <i data-lucide="message-circle"></i>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode(canonical_url())) ?>"
                            target="_blank" rel="noopener" aria-label="Facebook">
                            <i data-lucide="thumbs-up"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode(canonical_url())) ?>&text=<?= e(rawurlencode($item['title'])) ?>"
                            target="_blank" rel="noopener" aria-label="X / Twitter">
                            <i data-lucide="send"></i>
                        </a>
                        <a href="mailto:?subject=<?= e(rawurlencode($item['title'])) ?>&body=<?= e(rawurlencode(canonical_url())) ?>"
                            aria-label="Email">
                            <i data-lucide="mail"></i>
                        </a>
                    </div>
                </footer>
            </article>

            <?php if (!empty($related)): ?>
                <section class="news-related">
                    <div class="news-related-head">
                        <p class="section-label">Más en <?= e($item['category']) ?></p>
                        <h2>Noticias relacionadas</h2>
                    </div>
                    <div class="news-grid">
                        <?php foreach ($related as $r): ?>
                            <article class="news-card">
                                <a href="<?= e(base_url('noticias/' . $r['slug'])) ?>" class="news-card-media">
                                    <?php if (!empty($r['cover_image'])): ?>
                                        <img src="<?= e(base_url($r['cover_image'])) ?>" alt="<?= e($r['title']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="news-card-fallback"><i
                                                data-lucide="<?= e(news_category_icon($r['category'])) ?>"></i></span>
                                    <?php endif; ?>
                                    <span class="news-card-cat"><?= e($r['category']) ?></span>
                                </a>
                                <div class="news-card-body">
                                    <time><?= e(news_format_date($r['published_at'])) ?></time>
                                    <h3><a href="<?= e(base_url('noticias/' . $r['slug'])) ?>"><?= e($r['title']) ?></a></h3>
                                    <p><?= e($r['excerpt']) ?></p>
                                    <a href="<?= e(base_url('noticias/' . $r['slug'])) ?>" class="news-card-link">
                                        Leer más <i data-lucide="arrow-right" class="h-4 w-4"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <a href="<?= e(base_url('noticias')) ?>" class="profile-back" style="display:flex;margin:2.5rem auto;">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Volver a noticias
            </a>
        </main>
    <?php endif; ?>

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