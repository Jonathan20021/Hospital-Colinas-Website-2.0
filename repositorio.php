<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/repository.php';

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js'),
    filemtime(__DIR__ . '/assets/js/repositorio.js')
);

repo_ensure_schema();
$documents = repo_all_public();
$categories = repo_categories();

$categoryCounts = [];
$yearsAvailable = [];
$nationalCount = 0;
$internationalCount = 0;
foreach ($documents as $doc) {
    $categoryCounts[$doc['category']] = ($categoryCounts[$doc['category']] ?? 0) + 1;
    if (!empty($doc['year'])) {
        $yearsAvailable[(int) $doc['year']] = true;
    }
    if ($doc['scope'] === 'internacional') {
        $internationalCount++;
    } else {
        $nationalCount++;
    }
}
krsort($yearsAvailable);
$totalDocs = count($documents);
$totalCategories = count($categoryCounts);

$searchNormalize = static function (string $value): string {
    $value = mb_strtolower($value);
    return strtr($value, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
};

$description = 'Repositorio Digital del Hospital General Las Colinas: ' . $totalDocs . ' protocolos médicos oficiales de República Dominicana (MISPAS) y guías clínicas internacionales, organizados para la consulta, la investigación y el aprendizaje clínico.';

$researchSources = [
    ['name' => 'Repositorio MISPAS', 'desc' => 'Biblioteca institucional del Ministerio de Salud Pública de RD: protocolos, guías, normas y reglamentos.', 'url' => 'https://repositorio.msp.gob.do/', 'icon' => 'landmark'],
    ['name' => 'PubMed', 'desc' => 'Más de 38 millones de citas de literatura biomédica de MEDLINE, revistas y libros.', 'url' => 'https://pubmed.ncbi.nlm.nih.gov/', 'icon' => 'book-open'],
    ['name' => 'Cochrane Library', 'desc' => 'Revisiones sistemáticas de referencia para la práctica clínica basada en evidencia.', 'url' => 'https://www.cochranelibrary.com/es/', 'icon' => 'search-check'],
    ['name' => 'BVS — Biblioteca Virtual en Salud', 'desc' => 'Red de fuentes de información en salud de América Latina y el Caribe (LILACS, SciELO y más).', 'url' => 'https://bvsalud.org/', 'icon' => 'library'],
    ['name' => 'IRIS — OPS', 'desc' => 'Repositorio institucional de la Organización Panamericana de la Salud para las Américas.', 'url' => 'https://iris.paho.org/', 'icon' => 'globe'],
    ['name' => 'SciELO', 'desc' => 'Colección de revistas científicas de acceso abierto de Iberoamérica.', 'url' => 'https://scielo.org/es/', 'icon' => 'newspaper'],
];
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repositorio Digital de Protocolos Médicos | Hospital General Las Colinas</title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="Repositorio Digital de Protocolos Médicos | Hospital General Las Colinas">
    <meta property="og:description" content="<?= e($description) ?>">
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
        "@type": "CollectionPage",
        "name": "Repositorio Digital de Protocolos Médicos",
        "description": <?= json_encode($description, JSON_UNESCAPED_UNICODE) ?>,
        "url": <?= json_encode(canonical_url()) ?>,
        "inLanguage": "es-DO",
        "isPartOf": {
            "@type": "WebSite",
            "name": "Hospital General Las Colinas",
            "url": <?= json_encode(absolute_url()) ?>
        },
        "about": {
            "@type": "MedicalWebPage",
            "audience": { "@type": "MedicalAudience", "audienceType": "Clinician" }
        }
    }
    </script>
</head>

<body class="bg-white font-sans text-slate-950 antialiased content-page repo-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, 'repositorio'); ?>

    <main id="contenido">
        <section class="repo-hero" aria-labelledby="repoTitle">
            <div class="repo-hero-inner mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="content-breadcrumb repo-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= e(base_url()) ?>">Inicio</a>
                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                    <span>Repositorio Digital</span>
                </nav>

                <div class="repo-hero-grid">
                    <div class="repo-hero-copy">
                        <h1 id="repoTitle">Repositorio<br>Digital</h1>
                        <p class="repo-hero-lead">Protocolos oficiales de la República Dominicana y guías clínicas
                            internacionales en un solo lugar, abiertos a médicos, enfermeras, residentes y
                            estudiantes de las ciencias de la salud.</p>

                        <form class="repo-search" role="search" data-repo-search-form>
                            <i data-lucide="search" class="repo-search-icon h-5 w-5"></i>
                            <label class="sr-only" for="repoSearch">Buscar en el repositorio</label>
                            <input id="repoSearch" type="search" autocomplete="off" spellcheck="false"
                                placeholder="Busca por enfermedad, procedimiento o palabra clave…">
                            <button type="button" class="repo-search-clear hidden" data-repo-clear-search
                                aria-label="Limpiar búsqueda">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                            <kbd class="repo-search-kbd" aria-hidden="true">/</kbd>
                        </form>

                        <div class="repo-hero-chips" aria-label="Búsquedas frecuentes">
                            <span>Frecuentes:</span>
                            <?php foreach (['Dengue', 'Embarazo', 'RCP', 'Diabetes', 'Trauma', 'Neonato'] as $chip): ?>
                                <button type="button" data-repo-chip="<?= e($chip) ?>"><?= e($chip) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="repo-hero-panel" aria-label="Resumen del repositorio">
                        <div class="repo-hero-stat">
                            <strong><?= e((string) $totalDocs) ?></strong>
                            <span>documentos curados y verificados</span>
                        </div>
                        <ul class="repo-hero-facts">
                            <li>
                                <i data-lucide="flag" class="h-4 w-4"></i>
                                <?= e((string) $nationalCount) ?> protocolos nacionales (MISPAS)
                            </li>
                            <li>
                                <i data-lucide="globe" class="h-4 w-4"></i>
                                <?= e((string) $internationalCount) ?> guías internacionales
                            </li>
                            <li>
                                <i data-lucide="layout-grid" class="h-4 w-4"></i>
                                <?= e((string) $totalCategories) ?> especialidades clínicas
                            </li>
                            <li>
                                <i data-lucide="link" class="h-4 w-4"></i>
                                Enlaces directos a las fuentes oficiales
                            </li>
                        </ul>
                    </aside>
                </div>
            </div>
        </section>

        <div class="repo-toolbar" data-repo-toolbar>
            <div class="repo-toolbar-inner mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="repo-scope" role="group" aria-label="Filtrar por origen">
                    <button type="button" data-repo-scope="" aria-pressed="true">Todos</button>
                    <button type="button" data-repo-scope="nacional" aria-pressed="false">
                        República Dominicana
                    </button>
                    <button type="button" data-repo-scope="internacional" aria-pressed="false">
                        Internacionales
                    </button>
                </div>
                <div class="repo-toolbar-selects">
                    <label>
                        <span class="sr-only">Tipo de documento</span>
                        <select data-repo-filter="type">
                            <option value="">Todos los tipos</option>
                            <?php foreach (repo_doc_types() as $type): ?>
                                <option value="<?= e($type) ?>"><?= e($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Ordenar resultados</span>
                        <select data-repo-sort>
                            <option value="relevance">Relevancia</option>
                            <option value="recent">Más recientes</option>
                            <option value="alpha">A – Z</option>
                        </select>
                    </label>
                    <button type="button" class="repo-reset hidden" data-repo-reset>
                        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                        Limpiar
                    </button>
                </div>
                <p class="repo-count" data-repo-count aria-live="polite"><?= e((string) $totalDocs) ?> documentos</p>
            </div>
        </div>

        <section class="repo-body mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Catálogo de documentos">
            <div class="repo-shell">
                <aside class="repo-aside">
                    <h2>Especialidades</h2>
                    <div class="repo-cat-list" role="group" aria-label="Filtrar por especialidad">
                        <button type="button" data-repo-cat="" aria-pressed="true">
                            <i data-lucide="layers" class="h-4 w-4"></i>
                            <span>Todas</span>
                            <em><?= e((string) $totalDocs) ?></em>
                        </button>
                        <?php foreach ($categories as $cat => $icon): ?>
                            <?php if (empty($categoryCounts[$cat])) continue; ?>
                            <button type="button" data-repo-cat="<?= e($cat) ?>" aria-pressed="false">
                                <i data-lucide="<?= e($icon) ?>" class="h-4 w-4"></i>
                                <span><?= e($cat) ?></span>
                                <em><?= e((string) $categoryCounts[$cat]) ?></em>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="repo-aside-note">
                        <i data-lucide="graduation-cap" class="h-5 w-5"></i>
                        <p>Material de referencia para estudio e investigación. Cada documento enlaza a su fuente
                            oficial vigente.</p>
                    </div>
                </aside>

                <div class="repo-results">
                    <ol class="repo-list" data-repo-list>
                        <?php foreach ($documents as $doc): ?>
                            <?php
                            $url = repo_document_url($doc);
                            $isLocal = !empty($doc['file_path']);
                            $haystack = $searchNormalize(implode(' ', [
                                $doc['title'],
                                $doc['summary'] ?? '',
                                $doc['org'],
                                $doc['tags'] ?? '',
                                $doc['category'],
                            ]));
                            ?>
                            <li class="repo-row" data-repo-item data-search="<?= e($haystack) ?>"
                                data-cat="<?= e($doc['category']) ?>" data-scope="<?= e($doc['scope']) ?>"
                                data-type="<?= e($doc['doc_type']) ?>" data-year="<?= e((string) ($doc['year'] ?? '')) ?>"
                                data-featured="<?= (int) $doc['is_featured'] ?>" data-title="<?= e($doc['title']) ?>">
                                <span class="repo-row-icon" aria-hidden="true">
                                    <i data-lucide="<?= e(repo_category_icon($doc['category'])) ?>" class="h-5 w-5"></i>
                                </span>
                                <div class="repo-row-main">
                                    <p class="repo-row-meta">
                                        <span class="repo-org"><?= e($doc['org']) ?></span>
                                        <?php if (!empty($doc['year'])): ?>
                                            <span aria-hidden="true">·</span>
                                            <span><?= e((string) $doc['year']) ?></span>
                                        <?php endif; ?>
                                        <span aria-hidden="true">·</span>
                                        <span><?= e($doc['doc_type']) ?></span>
                                        <span class="repo-lang"><?= e(strtoupper(str_replace('-', '/', $doc['language']))) ?></span>
                                        <?php if ($doc['is_featured']): ?>
                                            <span class="repo-key">
                                                <i data-lucide="bookmark-check" class="h-3 w-3"></i>
                                                Referencia clave
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <h3>
                                        <a href="<?= e($url) ?>" <?= $isLocal ? '' : 'target="_blank" rel="noopener"' ?>>
                                            <?= e($doc['title']) ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($doc['summary'])): ?>
                                        <p class="repo-row-summary"><?= e($doc['summary']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <a class="repo-row-action" href="<?= e($url) ?>"
                                    <?= $isLocal ? '' : 'target="_blank" rel="noopener"' ?>
                                    aria-label="<?= e(($isLocal ? 'Descargar PDF: ' : 'Ver documento: ') . $doc['title']) ?>">
                                    <?php if ($isLocal): ?>
                                        <i data-lucide="download" class="h-4 w-4"></i>
                                        <span>PDF</span>
                                    <?php else: ?>
                                        <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                        <span>Ver</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                    <div class="repo-more hidden" data-repo-more>
                        <button type="button">
                            <i data-lucide="chevrons-down" class="h-4 w-4"></i>
                            <span data-repo-more-label>Mostrar más documentos</span>
                        </button>
                    </div>

                    <div class="repo-empty hidden" data-repo-empty>
                        <i data-lucide="file-search" class="h-8 w-8"></i>
                        <h3>Sin resultados<span data-repo-empty-query></span></h3>
                        <p>Prueba con un término más general (por ejemplo «dengue», «parto» o «trauma») o limpia los
                            filtros activos.</p>
                        <button type="button" class="btn btn-outline" data-repo-reset>
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                            Limpiar búsqueda y filtros
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="repo-research" aria-labelledby="repoResearchTitle">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="repo-research-head">
                    <h2 id="repoResearchTitle">Para investigar más</h2>
                    <p>Bases de datos y bibliotecas abiertas que complementan este repositorio cuando necesitas
                        evidencia primaria, revisiones sistemáticas o literatura regional.</p>
                </div>
                <ul class="repo-research-grid">
                    <?php foreach ($researchSources as $source): ?>
                        <li>
                            <a href="<?= e($source['url']) ?>" target="_blank" rel="noopener">
                                <span><i data-lucide="<?= e($source['icon']) ?>" class="h-5 w-5"></i></span>
                                <strong><?= e($source['name']) ?>
                                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i></strong>
                                <p><?= e($source['desc']) ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="repo-footer-band">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="repo-disclaimer" role="note">
                    <i data-lucide="shield-alert" class="h-5 w-5"></i>
                    <p><strong>Uso clínico responsable.</strong> Este repositorio es material de referencia educativa
                        y de investigación: no sustituye el juicio clínico ni las políticas internas de cada centro.
                        Verifica siempre la vigencia del documento en su fuente oficial antes de aplicarlo.</p>
                </div>
                <div class="repo-request">
                    <div>
                        <h2>¿No encuentras un protocolo?</h2>
                        <p>Escríbenos y el equipo de docencia e investigación lo localizará y lo sumará al
                            repositorio.</p>
                    </div>
                    <a class="btn btn-green btn-lg" href="mailto:<?= e($contact['email']) ?>?subject=Solicitud%20de%20documento%20%E2%80%94%20Repositorio%20Digital">
                        <i data-lucide="mail" class="h-4 w-4"></i>
                        Solicitar un documento
                    </a>
                </div>
            </div>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php render_appointment_modal($services); ?>
    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script src="<?= e(base_url('assets/js/repositorio.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>

</html>
