<?php
/**
 * Pagina 404.
 *
 * Antes, cualquier ruta desconocida caia en el home CON HTTP 200. Venia de un
 * fallback del .htaccess pensado para "deep links tipo SPA", pero este sitio no
 * es una SPA: son rutas PHP explicitas. Efectos que tenia:
 *   - buscadores indexando URLs inventadas como copias de la portada
 *   - un enlace roto llevaba a la portada sin decir que estaba roto
 *   - 364 KB servidos por cada URL basura, y el access_log esta lleno de bots
 *
 * Ahora responde 404 de verdad y ofrece por donde seguir. Lleva `noindex` para
 * que ninguna de esas URLs entre al indice.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';

http_response_code(404);

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    @filemtime(__DIR__ . '/assets/css/app-core.css') ?: 0,
    filemtime(__DIR__ . '/assets/js/app.js')
);

$title       = 'Página no encontrada';
$description = 'La página que buscas no existe o cambió de dirección. Te dejamos los accesos más usados del Hospital General Las Colinas.';

/** Lo que la gente viene a hacer de verdad, por orden de uso. */
$atajos = [
    ['icon' => 'calendar-plus',  'url' => base_url('agendar'),           'title' => 'Agendar una cita',       'text' => 'Con cualquier especialista, en menos de dos minutos.'],
    ['icon' => 'stethoscope',    'url' => base_url('directorio-medico'), 'title' => 'Directorio médico',      'text' => 'Busca por especialidad o por nombre.'],
    ['icon' => 'file-text',      'url' => base_url('ver-resultados'),    'title' => 'Ver mis resultados',     'text' => 'Laboratorio, imágenes y recetas en el portal.'],
    ['icon' => 'heart-pulse',    'url' => base_url('servicios'),         'title' => 'Servicios del hospital', 'text' => 'Especialidades, diagnóstico y atención inmediata.'],
    ['icon' => 'shield-check',   'url' => base_url('seguros-aceptados'), 'title' => 'Seguros aceptados',      'text' => 'Las ARS con las que trabajamos.'],
    ['icon' => 'map-pin',        'url' => base_url('contacto'),          'title' => 'Cómo llegar',            'text' => 'Dirección, horarios y teléfonos.'],
];
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Hospital General Las Colinas</title>
    <meta name="description" content="<?= e($description) ?>">
    <?php /* Ninguna URL inexistente debe acabar en el indice de un buscador. */ ?>
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#262161">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <?php /* tailwind + app-core: sin ellos se rompe el modal de cita del encabezado. */ ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app-core.css')) ?>?v=<?= e($assetVersion) ?>">
    <style>
        /* Suficiente para esta pagina; no merece una hoja aparte. */
        .e404 { padding: clamp(2.5rem, 7vw, 5rem) 0 clamp(3rem, 8vw, 6rem); background: var(--ice, #f7f3ec); }
        .e404-inner { max-width: 62rem; margin: 0 auto; padding: 0 1.25rem; }
        .e404-code { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem .85rem;
            border-radius: 999px; background: rgba(38, 33, 97, .07); color: var(--navy, #262161);
            font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .e404 h1 { margin: 1rem 0 .65rem; color: var(--navy, #262161); font-weight: 900; line-height: 1.1;
            letter-spacing: -0.02em; font-size: clamp(1.7rem, 5.5vw, 2.8rem); }
        .e404-lead { max-width: 34rem; color: #57534c; font-size: clamp(.98rem, 2.4vw, 1.08rem); line-height: 1.6; }
        .e404-acciones { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 1.6rem; }
        /* 44 px de alto: el minimo comodo con el dedo. */
        .e404-acciones .btn { min-height: 44px; }
        .e404-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
            gap: .85rem; margin-top: clamp(2rem, 5vw, 3rem); }
        .e404-card { display: flex; gap: .85rem; align-items: flex-start; padding: 1.1rem;
            border: 1px solid #e8e1d6; border-radius: 14px; background: #fff; color: inherit;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
        .e404-card:hover { border-color: var(--green-2, #5da334); transform: translateY(-2px);
            box-shadow: 0 10px 26px -16px rgba(38, 33, 97, .3); }
        .e404-card i { flex: 0 0 auto; width: 22px; height: 22px; color: var(--green-2, #5da334); margin-top: .1rem; }
        .e404-card strong { display: block; color: var(--navy, #262161); font-weight: 800; font-size: .98rem; }
        .e404-card span { display: block; margin-top: .2rem; color: #6f6d68; font-size: .87rem; line-height: 1.5; }
        .e404-ayuda { margin-top: clamp(2rem, 5vw, 2.8rem); padding-top: 1.5rem; border-top: 1px solid #e8e1d6;
            color: #6f6d68; font-size: .92rem; line-height: 1.7; }
        .e404-ayuda a { color: var(--navy, #262161); font-weight: 800; }
    </style>
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="e404">
        <div class="e404-inner">
            <p class="e404-code"><i data-lucide="search-x" class="h-4 w-4"></i> Error 404</p>
            <h1>Esta página no existe</h1>
            <p class="e404-lead">
                Puede que el enlace esté roto o que la página haya cambiado de dirección.
                Estos son los accesos que más se usan.
            </p>

            <div class="e404-acciones">
                <a href="<?= e(base_url('')) ?>" class="btn btn-navy">
                    <i data-lucide="house" class="h-4 w-4"></i> Ir al inicio
                </a>
                <a href="tel:18098060444" class="btn btn-outline">
                    <i data-lucide="phone" class="h-4 w-4"></i> <?= e($contact['phone']) ?>
                </a>
            </div>

            <div class="e404-grid">
                <?php foreach ($atajos as $a): ?>
                    <a class="e404-card" href="<?= e($a['url']) ?>">
                        <i data-lucide="<?= e($a['icon']) ?>"></i>
                        <span>
                            <strong><?= e($a['title']) ?></strong>
                            <span><?= e($a['text']) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <p class="e404-ayuda">
                ¿Buscabas algo que no aparece aquí? Llámanos al
                <a href="tel:18098060444"><?= e($contact['phone']) ?></a>
                o escríbenos a <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>.
                También puedes revisar el <a href="<?= e(base_url('mapa-del-sitio')) ?>">mapa del sitio</a>.
            </p>
        </div>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>
    <script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script defer src="<?= e(base_url('assets/js/track.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/track.js') ?: 1)) ?>"></script>
</body>

</html>
