<?php
/**
 * /empleos — Vacantes del Hospital General Las Colinas.
 *
 * Muestra las vacantes OPEN publicadas en la app de reclutamiento (HGLC PEOPLE),
 * traídas por el puente seguro de JENOFONTE (`GET /portal/empleos`). Nada toca la
 * BD interna: el sitio solo renderiza lo publicable. La postulación (con CV) se
 * hace en `/empleos/{id}`.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';

$active = 'empleos';
$year = date('Y');
$assetVersion = (string) max(
    @filemtime(__DIR__ . '/assets/css/app.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/empleos.css') ?: 0
);

$res = portal_api_call('GET', '/portal/empleos');
$vacancies = ($res['ok'] && isset($res['data']['vacancies']) && is_array($res['data']['vacancies']))
    ? $res['data']['vacancies'] : [];

$EMPLOYMENT_LABEL = [
    'FULL_TIME' => 'Tiempo completo',
    'PART_TIME' => 'Medio tiempo',
    'TEMPORARY' => 'Temporal',
    'CONTRACT'  => 'Por contrato',
    'INTERN'    => 'Pasantía',
];

// Áreas (departamentos) con vacantes, para el filtro.
$departments = [];
foreach ($vacancies as $v) {
    $d = trim((string) ($v['department'] ?? ''));
    if ($d !== '') $departments[$d] = true;
}
$departments = array_keys($departments);
sort($departments, SORT_NATURAL | SORT_FLAG_CASE);

$title = 'Empleos y vacantes | Hospital General Las Colinas, Santiago';
$description = 'Trabaja con nosotros. Vacantes abiertas en el Hospital General Las Colinas, Santiago (RD): postúlate en línea de forma rápida y segura.';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="keywords" content="empleos hospital Santiago, vacantes HGLC, trabajar hospital República Dominicana, reclutamiento Las Colinas">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="Empleos y vacantes | Hospital General Las Colinas">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/empleos.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased empleos-page">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php render_public_header($assets, $contact, $active); ?>

<main id="contenido" class="emp-v2">
    <!-- Hero -->
    <section class="emp-hero">
        <div class="emp-hero-inner">
            <p class="emp-eyebrow"><i data-lucide="briefcase"></i> Trabaja con nosotros</p>
            <h1>Súmate al equipo del <em>Hospital General Las Colinas</em></h1>
            <p class="emp-hero-lead">
                Construimos una atención médica de excelencia en Santiago. Si compartes ese propósito,
                postúlate a nuestras vacantes abiertas — en línea, en minutos y de forma segura.
            </p>
            <div class="emp-hero-stats">
                <span><strong><?= e((string) count($vacancies)) ?></strong> vacante<?= count($vacancies) === 1 ? '' : 's' ?> abierta<?= count($vacancies) === 1 ? '' : 's' ?></span>
                <span aria-hidden="true">•</span>
                <span>Santiago, Rep. Dom.</span>
            </div>
        </div>
    </section>

    <div class="emp-wrap">
        <?php if (empty($vacancies)): ?>
            <!-- Estado vacío -->
            <div class="emp-empty">
                <span class="emp-empty-icon"><i data-lucide="briefcase"></i></span>
                <h2>No hay vacantes abiertas por ahora</h2>
                <p>En este momento no tenemos posiciones publicadas. Vuelve pronto: actualizamos nuestras
                    vacantes con frecuencia.</p>
                <a class="emp-btn emp-btn-primary" href="#suscribir">
                    <i data-lucide="bell-plus"></i> Avísame de nuevas vacantes
                </a>
            </div>
        <?php else: ?>
            <!-- Filtros -->
            <section class="emp-filters" aria-label="Filtrar vacantes">
                <div class="emp-search">
                    <i data-lucide="search"></i>
                    <input type="search" id="empSearch" placeholder="Busca por cargo, área o palabra clave…"
                        autocomplete="off" aria-label="Buscar vacantes">
                </div>
                <?php if (count($departments) > 1): ?>
                    <div class="emp-chips" id="empChips" role="tablist" aria-label="Filtrar por área">
                        <button type="button" class="emp-chip is-active" data-dept="">Todas</button>
                        <?php foreach ($departments as $d): ?>
                            <button type="button" class="emp-chip" data-dept="<?= e($d) ?>"><?= e($d) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Grid de vacantes -->
            <section class="emp-grid" id="empGrid" aria-label="Vacantes disponibles">
                <?php foreach ($vacancies as $v):
                    $vid = (string) ($v['id'] ?? '');
                    if ($vid === '') continue;
                    $etype = (string) ($v['employmentType'] ?? '');
                    $etypeLabel = $EMPLOYMENT_LABEL[$etype] ?? $etype;
                    $dept = trim((string) ($v['department'] ?? ''));
                    $loc = trim((string) ($v['location'] ?? ''));
                    $seats = (int) ($v['seats'] ?? 0);
                    $desc = trim((string) ($v['description'] ?? ''));
                    $snippet = mb_substr($desc, 0, 160, 'UTF-8');
                    if (mb_strlen($desc, 'UTF-8') > 160) $snippet .= '…';
                    $haystack = mb_strtolower(trim(($v['title'] ?? '') . ' ' . $dept . ' ' . $loc . ' ' . $etypeLabel . ' ' . $desc), 'UTF-8');
                ?>
                    <article class="emp-card" data-dept="<?= e($dept) ?>" data-search="<?= e($haystack) ?>">
                        <div class="emp-card-top">
                            <?php if ($dept !== ''): ?><span class="emp-card-dept"><?= e($dept) ?></span><?php endif; ?>
                            <?php if ($etypeLabel !== ''): ?><span class="emp-card-type"><?= e($etypeLabel) ?></span><?php endif; ?>
                        </div>
                        <h3 class="emp-card-title"><?= e((string) ($v['title'] ?? 'Vacante')) ?></h3>
                        <ul class="emp-card-meta">
                            <?php if ($loc !== ''): ?><li><i data-lucide="map-pin"></i><?= e($loc) ?></li><?php endif; ?>
                            <?php if ($seats > 0): ?><li><i data-lucide="users"></i><?= e((string) $seats) ?> plaza<?= $seats === 1 ? '' : 's' ?></li><?php endif; ?>
                        </ul>
                        <?php if ($snippet !== ''): ?><p class="emp-card-desc"><?= e($snippet) ?></p><?php endif; ?>
                        <a class="emp-card-cta" href="<?= e(base_url('empleos/' . rawurlencode($vid))) ?>">
                            Ver y postular <i data-lucide="arrow-right"></i>
                        </a>
                    </article>
                <?php endforeach; ?>
            </section>

            <p class="emp-noresults" id="empNoResults" hidden>
                <i data-lucide="search-x"></i> No encontramos vacantes con ese criterio.
            </p>
        <?php endif; ?>
    </div>

    <!-- Alertas de empleo (newsletter) -->
    <section class="emp-subscribe" id="suscribir" aria-label="Alertas de empleo">
        <div class="emp-subscribe-card">
            <span class="emp-subscribe-icon"><i data-lucide="bell-ring"></i></span>
            <div class="emp-subscribe-body">
                <h2>Recibe nuestras vacantes por correo</h2>
                <p>Suscríbete y te avisamos automáticamente apenas publiquemos una nueva posición. Puedes darte de baja cuando quieras.</p>
                <form id="empSubscribeForm" novalidate>
                    <div class="emp-subscribe-hp" aria-hidden="true">
                        <label>No llenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>
                    <div class="emp-subscribe-row">
                        <input type="email" name="email" id="empSubEmail" required maxlength="160"
                            placeholder="tu@correo.com" autocomplete="email" inputmode="email" aria-label="Tu correo">
                        <button type="submit" class="emp-btn emp-btn-primary" id="empSubBtn">
                            <span class="emp-sub-label"><i data-lucide="bell-plus"></i> Suscribirme</span>
                            <span class="emp-sub-loading" hidden><i data-lucide="loader-2" class="emp-spin"></i> Enviando…</span>
                        </button>
                    </div>
                    <p class="emp-subscribe-msg" id="empSubMsg" role="alert" hidden></p>
                    <p class="emp-subscribe-legal">Al suscribirte aceptas recibir correos sobre vacantes del Hospital General Las Colinas conforme a la Ley 172-13. Te enviaremos un correo para confirmar.</p>
                </form>
            </div>
        </div>
    </section>
</main>

<?php render_public_footer($assets, $contact, $year); ?>
<?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

<script src="https://unpkg.com/lucide@latest"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<script>
(function () {
    var search = document.getElementById('empSearch');
    var chips = document.getElementById('empChips');
    var grid = document.getElementById('empGrid');
    var noRes = document.getElementById('empNoResults');
    if (!grid) return;
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.emp-card'));
    var dept = '';

    function norm(s) { return (s || '').toLowerCase(); }
    function apply() {
        var q = norm(search ? search.value : '').trim();
        var shown = 0;
        cards.forEach(function (c) {
            var okDept = !dept || c.getAttribute('data-dept') === dept;
            var okQ = !q || (c.getAttribute('data-search') || '').indexOf(q) !== -1;
            var vis = okDept && okQ;
            c.style.display = vis ? '' : 'none';
            if (vis) shown++;
        });
        if (noRes) noRes.hidden = shown !== 0;
    }
    if (search) search.addEventListener('input', apply);
    if (chips) chips.addEventListener('click', function (e) {
        var btn = e.target.closest('.emp-chip');
        if (!btn) return;
        chips.querySelectorAll('.emp-chip').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        dept = btn.getAttribute('data-dept') || '';
        apply();
    });
})();
</script>
<script>
(function () {
    var form = document.getElementById('empSubscribeForm');
    if (!form) return;
    var btn = document.getElementById('empSubBtn');
    var msg = document.getElementById('empSubMsg');
    var email = document.getElementById('empSubEmail');
    var endpoint = <?= json_encode(base_url('api/empleos-suscribir.php'), JSON_UNESCAPED_SLASHES) ?>;
    function show(text, kind) { msg.textContent = text; msg.className = 'emp-subscribe-msg ' + (kind || 'error'); msg.hidden = false; }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        msg.hidden = true;
        var val = email.value.trim();
        if (!val || val.indexOf('@') === -1) { show('Escribe un correo válido.', 'error'); return; }
        var data = new FormData(form);
        btn.disabled = true;
        btn.querySelector('.emp-sub-label').hidden = true;
        btn.querySelector('.emp-sub-loading').hidden = false;
        if (window.lucide) lucide.createIcons();
        fetch(endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
            .then(function (j) {
                if (j && j.ok) {
                    form.reset();
                    show('¡Listo! Te enviamos un correo para confirmar tu suscripción. Revisa tu bandeja (y el spam).', 'ok');
                } else {
                    show((j && j.error) ? j.error : 'No pudimos completar la suscripción. Inténtalo más tarde.', 'error');
                }
            })
            .catch(function () { show('No pudimos conectar. Inténtalo de nuevo.', 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.querySelector('.emp-sub-label').hidden = false;
                btn.querySelector('.emp-sub-loading').hidden = true;
                if (window.lucide) lucide.createIcons();
            });
    });
})();
</script>
</body>
</html>
