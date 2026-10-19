<?php
/**
 * /bienestar — Jornada de salud y bienestar del Hospital General Las Colinas.
 *
 * Muestra el programa publicado en HGLC PEOPLE (módulo de bienestar), traído
 * por el puente seguro de JENOFONTE (`GET /portal/charlas`), y permite que el
 * público general se inscriba sin salir del sitio (`api/bienestar-inscribir.php`).
 *
 * Gemela de /empleos y con la misma arquitectura: el sitio en cPanel no alcanza
 * atenea (LAN), así que todo pasa por el puente. Usa la línea gráfica del sitio
 * (tokens de app.css, .dir-section-shell, .section-label, .btn).
 *
 * El personal del hospital NO se inscribe aquí: lo hace desde la intranet con
 * su cédula y su fecha de nacimiento. Esos datos no deben viajar por un sitio
 * público.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';

$active = 'bienestar';
$year = date('Y');
$assetVersion = (string) max(
    @filemtime(__DIR__ . '/assets/css/app.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/app-core.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/bienestar.css') ?: 0
);

$res = portal_api_call('GET', '/portal/charlas');
$data = ($res['ok'] && is_array($res['data'] ?? null)) ? $res['data'] : [];
$event = is_array($data['event'] ?? null) ? $data['event'] : null;
$activities = is_array($data['activities'] ?? null) ? $data['activities'] : [];

$KIND_LABEL = ['CHARLA' => 'Charla', 'ESTACION' => 'Estación', 'FERIA' => 'Feria'];
$DIAS = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
         'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** `2026-10-19T12:00:00.000Z` → marca de tiempo. Las horas llegan en UTC. */
function bien_ts(?string $iso): int {
    return $iso ? (int) strtotime($iso) : 0;
}
/** Hora dominicana `8:00 a. m.` — el sitio ya corre en America/Santo_Domingo. */
function bien_hora(int $ts): string {
    $h = (int) date('g', $ts);
    $m = date('i', $ts);
    $ap = date('a', $ts) === 'am' ? 'a. m.' : 'p. m.';
    return $h . ':' . $m . ' ' . $ap;
}

// Agrupar por día dominicano, conservando el orden que ya trae la API.
$porDia = [];
foreach ($activities as $a) {
    $ts = bien_ts($a['startsAt'] ?? null);
    if ($ts === 0) continue;
    $porDia[date('Y-m-d', $ts)][] = $a + ['_ts' => $ts, '_tsFin' => bien_ts($a['endsAt'] ?? null)];
}
ksort($porDia);

$abiertas = 0;
foreach ($activities as $a) {
    if (($a['state'] ?? '') === 'abierta' && !empty($a['publicOpen'])) $abiertas++;
}
$total = count($activities);

$nombreEvento = $event['name'] ?? 'Jornada de salud y bienestar';
$lema = trim((string) ($event['tagline'] ?? ''));

$title = $event
    ? e_strip($nombreEvento) . ' | Hospital General Las Colinas'
    : 'Salud y bienestar | Hospital General Las Colinas, Santiago';
$description = $event
    ? 'Programa de charlas, estaciones y ferias de salud del Hospital General Las Colinas en Santiago. Entrada libre: inscríbete en línea.'
    : 'Jornadas de salud y bienestar del Hospital General Las Colinas, Santiago. Consulta aquí el próximo programa.';

/** Texto plano para <title> y meta (sin HTML). */
function e_strip(string $s): string { return trim(strip_tags($s)); }
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="keywords" content="charlas de salud Santiago, semana de bienestar hospital, prevención salud República Dominicana, Hospital Las Colinas">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">

    <?php /* Fuentes auto-hospedadas (mismo origen, sin render-blocking). */ ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app-core.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php /* Trozo de app.css con .dir-section* / .section-label, que esta pagina reutiliza. */ ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app-directorio.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/app-directorio.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bienestar.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased bienestar-page">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php render_public_header($assets, $contact, $active); ?>

<main id="contenido">
    <!-- Hero (panel navy; el boton va VERDE, navy sobre navy seria invisible) -->
    <section class="bien-hero">
        <div class="bien-hero-grid">
            <div class="bien-hero-copy">
                <nav class="bien-hero-crumbs" aria-label="Ruta de navegación">
                    <a href="<?= e(base_url()) ?>">Inicio</a>
                    <span aria-hidden="true">/</span>
                    <span>Bienestar</span>
                </nav>
                <h1><?= e($nombreEvento) ?></h1>
                <?php if ($lema !== ''): ?>
                    <p class="bien-hero-lema">«<?= e($lema) ?>»</p>
                <?php endif; ?>
                <p>
                    <?php if ($event): ?>
                        Charlas, estaciones y ferias de salud abiertas a la comunidad. La entrada es
                        libre y la inscripción toma menos de un minuto.
                    <?php else: ?>
                        El hospital organiza jornadas de salud abiertas a la comunidad. Cuando se
                        anuncie la próxima, el programa completo aparecerá aquí.
                    <?php endif; ?>
                </p>

                <?php if ($total > 0): ?>
                    <div class="bien-hero-actions">
                        <a href="#programa" class="btn btn-green btn-lg">
                            <i data-lucide="calendar-heart" class="h-4 w-4"></i>
                            Ver el programa
                        </a>
                    </div>
                    <dl class="bien-hero-summary" aria-label="Resumen de la jornada">
                        <div><dt>Actividades</dt><dd><?= e((string) $total) ?></dd></div>
                        <div><dt>Entrada</dt><dd>Libre</dd></div>
                    </dl>
                <?php endif; ?>
            </div>

            <figure class="bien-hero-visual">
                <img<?= img_srcset_attrs($assets['doctors'], '100vw') ?>
                    alt="Equipo médico del Hospital General Las Colinas" fetchpriority="high">
            </figure>
        </div>
    </section>

    <!-- Programa -->
    <section id="programa" class="dir-section">
        <div class="dir-section-shell">
            <div class="dir-section-head">
                <div>
                    <p class="section-label"><i data-lucide="heart-pulse" class="h-4 w-4"></i> Programa</p>
                    <h2>Actividades</h2>
                    <span>
                        <?php if ($total > 0): ?>
                            <?= e((string) $total) ?> actividad<?= $total === 1 ? '' : 'es' ?> en el programa<?php
                                if ($abiertas > 0) { echo ', ' . e((string) $abiertas) . ' con inscripción abierta'; }
                            ?>.
                        <?php else: ?>
                            No hay ninguna jornada publicada en este momento.
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if ($total === 0): ?>
                <div class="bien-vacio">
                    <i data-lucide="calendar-heart"></i>
                    <h2>Todavía no hay un programa publicado</h2>
                    <p>
                        Cuando el hospital anuncie su próxima jornada de salud y bienestar, el
                        programa completo aparecerá aquí y podrás inscribirte en línea.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($porDia as $dia => $acts): ?>
                    <?php $tsDia = strtotime($dia . ' 12:00:00'); ?>
                    <div class="bien-dia">
                        <div class="bien-dia-head">
                            <h2><?= e(ucfirst($DIAS[date('l', $tsDia)] ?? '')) ?>,
                                <?= e(date('j', $tsDia)) ?> de <?= e($MESES[(int) date('n', $tsDia)]) ?></h2>
                            <span><?= e((string) count($acts)) ?> actividad<?= count($acts) === 1 ? '' : 'es' ?></span>
                        </div>

                        <div class="bien-grid">
                            <?php foreach ($acts as $a):
                                $estado   = (string) ($a['state'] ?? 'abierta');
                                $pasada   = $estado === 'terminada';
                                $llena    = $estado === 'llena';
                                $abierta  = !empty($a['publicOpen']) && !$pasada && !$llena;
                                $plazas   = $a['seatsLeft'] ?? null;
                            ?>
                            <article class="bien-card<?= $pasada ? ' is-past' : '' ?>">
                                <div class="bien-card-tags">
                                    <span class="bien-tag">
                                        <i data-lucide="sparkles"></i>
                                        <?= e($KIND_LABEL[$a['kind'] ?? ''] ?? (string) ($a['kind'] ?? '')) ?>
                                    </span>
                                    <?php if (!empty($a['publicOpen']) && !$pasada): ?>
                                        <span class="bien-tag is-green">Entrada libre</span>
                                    <?php endif; ?>
                                    <?php if ($pasada): ?>
                                        <span class="bien-tag is-muted">Terminada</span>
                                    <?php elseif ($llena): ?>
                                        <span class="bien-tag is-muted">Cupo lleno</span>
                                    <?php elseif ($estado === 'en_curso'): ?>
                                        <span class="bien-tag is-green">En curso</span>
                                    <?php endif; ?>
                                </div>

                                <h3><?= e((string) ($a['title'] ?? '')) ?></h3>

                                <?php if (!empty($a['speakers'])): ?>
                                    <p class="bien-card-speakers"><?= e((string) $a['speakers']) ?></p>
                                <?php endif; ?>

                                <ul class="bien-card-meta">
                                    <li>
                                        <i data-lucide="clock"></i>
                                        <?= e(bien_hora((int) $a['_ts'])) ?><?php
                                            if (!empty($a['_tsFin'])) echo ' – ' . e(bien_hora((int) $a['_tsFin']));
                                        ?>
                                    </li>
                                    <?php if (!empty($a['location'])): ?>
                                        <li><i data-lucide="map-pin"></i><?= e((string) $a['location']) ?></li>
                                    <?php endif; ?>
                                    <?php if ($plazas !== null && $abierta): ?>
                                        <li><i data-lucide="users"></i><?= e((string) $plazas) ?> plaza<?= (int) $plazas === 1 ? '' : 's' ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($a['sponsor'])): ?>
                                        <li><i data-lucide="handshake"></i><?= e((string) $a['sponsor']) ?></li>
                                    <?php endif; ?>
                                </ul>

                                <div class="bien-card-foot">
                                    <?php if ($abierta): ?>
                                        <button type="button" class="btn btn-green"
                                            data-bien-abrir
                                            data-id="<?= e((string) ($a['id'] ?? '')) ?>"
                                            data-titulo="<?= e((string) ($a['title'] ?? '')) ?>"
                                            data-cuando="<?= e(ucfirst($DIAS[date('l', $tsDia)] ?? '') . ' ' . date('j', $tsDia) . ', ' . bien_hora((int) $a['_ts'])) ?>">
                                            <i data-lucide="user-plus" class="h-4 w-4"></i>
                                            Inscribirme
                                        </button>
                                    <?php elseif ($pasada): ?>
                                        <span class="bien-card-cerrada">Esta actividad ya terminó</span>
                                    <?php elseif ($llena): ?>
                                        <span class="bien-card-cerrada">Sin plazas disponibles</span>
                                    <?php else: ?>
                                        <span class="bien-card-cerrada">Actividad interna del hospital</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="bien-nota">
                    <strong>¿Trabajas en el hospital?</strong> No te inscribas aquí: hazlo desde la
                    intranet, donde el sistema te reconoce con tu cédula y tu fecha de nacimiento y
                    tu participación cuenta para la rifa de fin de jornada.
                    <br>
                    <strong>¿Vienes de fuera?</strong> Inscríbete en las actividades de entrada
                    libre y preséntate unos minutos antes en la recepción del hospital.
                </p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php if ($abiertas > 0): ?>
<!-- Diálogo de inscripción -->
<div class="bien-modal" id="bien-modal" role="dialog" aria-modal="true" aria-labelledby="bien-modal-titulo" hidden>
    <div class="bien-modal-card">
        <button type="button" class="bien-modal-close" data-bien-cerrar aria-label="Cerrar">
            <i data-lucide="x" class="h-5 w-5"></i>
        </button>

        <div id="bien-form-wrap">
            <h2 id="bien-modal-titulo">Inscribirme</h2>
            <p class="bien-modal-sub" id="bien-modal-sub"></p>

            <div class="bien-aviso is-error" id="bien-error" hidden></div>

            <form id="bien-form" novalidate>
                <input type="hidden" name="activityId" id="bien-activity">
                <label class="bien-field">
                    <span>Nombre y apellido</span>
                    <input type="text" name="fullName" autocomplete="name" required maxlength="120">
                </label>
                <label class="bien-field">
                    <span>Correo electrónico</span>
                    <input type="email" name="email" autocomplete="email" inputmode="email" required maxlength="180">
                    <small>Ahí te enviamos la confirmación.</small>
                </label>
                <label class="bien-field">
                    <span>Teléfono</span>
                    <input type="tel" name="phone" autocomplete="tel" inputmode="tel" required maxlength="30"
                        placeholder="809-000-0000">
                </label>

                <?php /* Señuelo anti-bot: fuera de pantalla y no enfocable. */ ?>
                <div class="bien-hp" aria-hidden="true">
                    <label>Sitio web <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="bien-modal-foot">
                    <button type="submit" class="btn btn-green" id="bien-enviar">
                        <span class="bien-enviar-label">Confirmar mi inscripción</span>
                        <span class="bien-enviar-loading" hidden>Enviando…</span>
                    </button>
                </div>
                <p class="bien-legal">
                    Al inscribirte autorizas al Hospital General Las Colinas a usar estos datos
                    únicamente para organizar esta actividad (Ley 172-13). No te suscribimos a nada.
                </p>
            </form>
        </div>

        <div class="bien-ok" id="bien-ok" hidden>
            <i data-lucide="check-circle-2"></i>
            <h3 id="bien-ok-titulo">¡Listo! Tu lugar está reservado</h3>
            <p id="bien-ok-texto"></p>
            <div class="bien-modal-foot">
                <button type="button" class="btn btn-navy" data-bien-cerrar>Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_public_footer($assets, $contact, $year); ?>

<?php if ($abiertas > 0): ?>
<script>
(function () {
    var modal = document.getElementById('bien-modal');
    if (!modal) return;
    var form = document.getElementById('bien-form');
    var campoId = document.getElementById('bien-activity');
    var titulo = document.getElementById('bien-modal-titulo');
    var sub = document.getElementById('bien-modal-sub');
    var error = document.getElementById('bien-error');
    var wrap = document.getElementById('bien-form-wrap');
    var ok = document.getElementById('bien-ok');
    var okTexto = document.getElementById('bien-ok-texto');
    var btn = document.getElementById('bien-enviar');
    var endpoint = <?= json_encode(base_url('api/bienestar-inscribir.php'), JSON_UNESCAPED_SLASHES) ?>;
    var ultimoFoco = null;

    function abrir(b) {
        ultimoFoco = b;
        campoId.value = b.getAttribute('data-id') || '';
        titulo.textContent = b.getAttribute('data-titulo') || 'Inscribirme';
        sub.textContent = b.getAttribute('data-cuando') || '';
        error.hidden = true; error.textContent = '';
        wrap.hidden = false; ok.hidden = true;
        form.reset(); campoId.value = b.getAttribute('data-id') || '';
        modal.hidden = false; modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
        var primero = form.querySelector('input[name="fullName"]');
        if (primero) primero.focus();
    }

    function cerrar() {
        modal.classList.remove('is-open'); modal.hidden = true;
        document.body.style.overflow = '';
        if (ultimoFoco) ultimoFoco.focus();
    }

    document.querySelectorAll('[data-bien-abrir]').forEach(function (b) {
        b.addEventListener('click', function () { abrir(b); });
    });
    modal.querySelectorAll('[data-bien-cerrar]').forEach(function (b) {
        b.addEventListener('click', cerrar);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) cerrar();
    });

    function fallo(msg) {
        error.textContent = msg; error.hidden = false;
        error.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        error.hidden = true;
        var nombre = form.fullName.value.trim();
        var correo = form.email.value.trim();
        var tel = form.phone.value.trim();
        if (nombre.indexOf(' ') === -1 || nombre.length < 5) { fallo('Escribe tu nombre y tu apellido.'); return; }
        if (correo.indexOf('@') === -1 || correo.indexOf('.') === -1) { fallo('Revisa el correo.'); return; }
        if (tel.replace(/\D/g, '').length < 10) { fallo('Escribe un teléfono de al menos 10 dígitos.'); return; }

        btn.disabled = true;
        btn.querySelector('.bien-enviar-label').hidden = true;
        btn.querySelector('.bien-enviar-loading').hidden = false;

        fetch(endpoint, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
            .then(function (j) {
                if (j && j.ok) {
                    okTexto.textContent = 'Te esperamos en «' + titulo.textContent + '». '
                        + 'Te enviamos la confirmación por correo; preséntate unos minutos antes en la recepción.';
                    wrap.hidden = true; ok.hidden = false;
                    if (window.lucide) lucide.createIcons();
                } else {
                    fallo((j && j.error) ? j.error : 'No pudimos registrar tu inscripción. Inténtalo más tarde.');
                }
            })
            .catch(function () { fallo('No pudimos conectar. Inténtalo de nuevo.'); })
            .finally(function () {
                btn.disabled = false;
                btn.querySelector('.bien-enviar-label').hidden = false;
                btn.querySelector('.bien-enviar-loading').hidden = true;
            });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
