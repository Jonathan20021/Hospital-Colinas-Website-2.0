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
// Solo iconos presentes en `assets/js/lucide-subset.js`: el sitio NO carga
// la libreria completa, y uno que falte sale como un hueco vacio.
$KIND_ICON  = ['CHARLA' => 'message-square-quote', 'ESTACION' => 'stethoscope', 'FERIA' => 'handshake'];

/**
 * Acento por dia de la semana: [color, fondo suave, linea].
 *
 * Son los del poster oficial —rosa, verde, azul, coral, violeta—, pero aqui
 * solo tinen el rail del dia. La tipografia y las tarjetas se quedan en el
 * navy y el warm del sitio: el evento pone el acento, no la identidad.
 */
$COLOR_DIA = [
    1 => ['#d6468c', 'rgba(214,70,140,.07)', 'rgba(214,70,140,.22)'],  // lunes
    2 => ['#5da334', 'rgba(93,163,52,.08)',  'rgba(93,163,52,.24)'],   // martes
    3 => ['#2f6fb5', 'rgba(47,111,181,.07)', 'rgba(47,111,181,.22)'],  // miercoles
    4 => ['#d4584a', 'rgba(212,88,74,.07)',  'rgba(212,88,74,.22)'],   // jueves
    5 => ['#6b4fa8', 'rgba(107,79,168,.07)', 'rgba(107,79,168,.22)'],  // viernes
    6 => ['#262161', 'rgba(38,33,97,.06)',   'rgba(38,33,97,.18)'],
    0 => ['#262161', 'rgba(38,33,97,.06)',   'rgba(38,33,97,.18)'],
];

/**
 * Parte el texto libre de ponentes en nombre + especialidad.
 *
 * Viene como «Dra. Neyda Minaya & Dra. Madeline Zapata» o
 * «Dra. Franceska Martinez (dermatologa tricologa) - The Beauty Duo». Lo que
 * va entre parentesis es la especialidad y se muestra mas tenue, como en el
 * poster. Si no hay separadores, devuelve una sola entrada.
 */
function bien_ponentes(string $crudo): array {
    $crudo = trim($crudo);
    if ($crudo === '') return [];
    // Solo & y ·, NO la coma: una especialidad como «psicologa, INFOTEP»
    // se partiria en dos ponentes y el segundo saldria sin nombre.
    $partes = preg_split('/\s*(?:&|·)\s*/u', $crudo) ?: [$crudo];
    $out = [];
    foreach ($partes as $x) {
        $x = trim($x);
        if ($x === '') continue;
        $esp = '';
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*(.*)$/u', $x, $m)) {
            $x = trim($m[1]);
            $esp = trim($m[2]);
            if (trim($m[3]) !== '') $esp .= ' ' . trim($m[3]);
        }
        if ($x !== '') $out[] = [$x, $esp];
    }
    return $out;
}
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

// Patrocinadores: salen de las propias actividades, no se escriben a mano, asi
// que la proxima jornada trae los suyos sin tocar codigo.
$patrocinadores = [];
foreach ($activities as $a) {
    foreach (preg_split('/\s*·\s*/u', trim((string) ($a['sponsor'] ?? ''))) ?: [] as $pt) {
        $pt = trim($pt);
        if ($pt !== '') $patrocinadores[$pt] = true;
    }
}
$patrocinadores = array_keys($patrocinadores);
sort($patrocinadores, SORT_NATURAL | SORT_FLAG_CASE);

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
                <?php /* Octubre es el mes de sensibilizacion sobre el cancer de mama, y
                         asi lo encabeza la agenda oficial del hospital. */ ?>
                <?php if ($event && (int) date('n', strtotime($event['startDate'] . ' 12:00:00')) === 10): ?>
                    <span class="bien-cinta">
                        <i data-lucide="ribbon"></i>
                        Mes de sensibilización sobre el cáncer de mama
                    </span>
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

    <!-- Programa: raíl del día + actividades, como la tabla del póster oficial -->
    <section id="programa" class="dir-section">
        <div class="dir-section-shell">
            <div class="dir-section-head">
                <div>
                    <p class="section-label"><i data-lucide="heart-pulse" class="h-4 w-4"></i> Programa</p>
                    <h2>Agenda de la jornada</h2>
                    <span>
                        <?php if ($total > 0): ?>
                            <?= e((string) $total) ?> actividad<?= $total === 1 ? '' : 'es' ?> en cinco días<?php
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
                <div class="bien-agenda">
                <?php foreach ($porDia as $dia => $acts): ?>
                    <?php
                        $tsDia = strtotime($dia . ' 12:00:00');
                        $dow   = date('w', $tsDia);          // 0=domingo
                        $c     = $COLOR_DIA[$dow] ?? $COLOR_DIA[1];
                        $esHoy = $dia === date('Y-m-d');
                        // La charla del día manda; estaciones y ferias acompañan.
                        usort($acts, static fn($a, $b) => [$a['kind'] !== 'CHARLA', $a['_ts']] <=> [$b['kind'] !== 'CHARLA', $b['_ts']]);
                    ?>
                    <div class="bien-dia" style="--dia: <?= e($c[0]) ?>; --dia-suave: <?= e($c[1]) ?>; --dia-linea: <?= e($c[2]) ?>;">
                        <div class="bien-rail">
                            <div class="bien-rail-inner">
                                <span class="bien-rail-dow"><?= e(mb_substr($DIAS[date('l', $tsDia)] ?? '', 0, 3, 'UTF-8')) ?></span>
                                <span class="bien-rail-num"><?= e(date('j', $tsDia)) ?></span>
                                <span class="bien-rail-mes"><?= e($MESES[(int) date('n', $tsDia)]) ?></span>
                                <?php if ($esHoy): ?><span class="bien-rail-hoy">Hoy</span><?php endif; ?>
                                <span class="bien-rail-n"><?= e((string) count($acts)) ?> actividad<?= count($acts) === 1 ? '' : 'es' ?></span>
                            </div>
                        </div>

                        <div class="bien-dia-lista">
                            <?php foreach ($acts as $a):
                                $estado   = (string) ($a['state'] ?? 'abierta');
                                $pasada   = $estado === 'terminada';
                                $curso    = $estado === 'en_curso';
                                $llena    = $estado === 'llena';
                                $abierta  = !empty($a['publicOpen']) && !$pasada && !$llena;
                                $plazas   = $a['seatsLeft'] ?? null;
                                $sec      = ($a['kind'] ?? '') !== 'CHARLA';
                            ?>
                            <article class="bien-card<?= $sec ? ' is-sec' : '' ?><?= $pasada ? ' is-past' : '' ?>">
                                <div class="bien-card-grid">
                                <div class="bien-card-main">
                                <div class="bien-card-top">
                                    <span class="bien-tag">
                                        <i data-lucide="<?= e($KIND_ICON[$a['kind'] ?? ''] ?? 'sparkles') ?>"></i>
                                        <?= e($KIND_LABEL[$a['kind'] ?? ''] ?? (string) ($a['kind'] ?? '')) ?>
                                    </span>
                                    <?php if ($curso): ?>
                                        <span class="bien-tag is-live">En curso</span>
                                    <?php elseif ($pasada): ?>
                                        <span class="bien-tag is-muted">Terminada</span>
                                    <?php elseif ($llena): ?>
                                        <span class="bien-tag is-muted">Cupo lleno</span>
                                    <?php elseif (!empty($a['publicOpen'])): ?>
                                        <span class="bien-tag is-green">Entrada libre</span>
                                    <?php endif; ?>
                                </div>

                                <h3><?= e((string) ($a['title'] ?? '')) ?></h3>

                                <?php $ponentes = bien_ponentes((string) ($a['speakers'] ?? '')); ?>
                                <?php if ($ponentes): ?>
                                    <p class="bien-ponentes">
                                        <?php foreach ($ponentes as $pn): ?>
                                            <span class="bien-ponente">
                                                <b><?= e($pn[0]) ?></b><?php if ($pn[1] !== ''): ?><span><?= e($pn[1]) ?></span><?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>

                                </div>

                                <div class="bien-card-side">
                                <ul class="bien-meta">
                                    <li>
                                        <i data-lucide="clock"></i>
                                        <span class="bien-hora"><?= e(bien_hora((int) $a['_ts'])) ?></span>
                                        <?php if (!empty($a['_tsFin'])): ?>– <?= e(bien_hora((int) $a['_tsFin'])) ?><?php endif; ?>
                                    </li>
                                    <?php if (!empty($a['location'])): ?>
                                        <li><i data-lucide="map-pin"></i><?= e((string) $a['location']) ?></li>
                                    <?php endif; ?>
                                    <?php if ($plazas !== null && $abierta): ?>
                                        <li><i data-lucide="users"></i><?= e((string) $plazas) ?> plaza<?= (int) $plazas === 1 ? '' : 's' ?></li>
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
                                        <span class="bien-cerrada">Esta actividad ya terminó</span>
                                    <?php elseif ($llena): ?>
                                        <span class="bien-cerrada">Sin plazas disponibles</span>
                                    <?php else: ?>
                                        <span class="bien-cerrada">Actividad interna del hospital</span>
                                    <?php endif; ?>
                                </div>
                                </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php if ($patrocinadores): ?>
                    <div class="bien-patro">
                        <p>Con el apoyo de</p>
                        <ul>
                            <?php foreach ($patrocinadores as $pt): ?>
                                <li><?= e($pt) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

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

<?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

<?php /* Cola estandar del sitio: lucide dibuja los iconos y app.js mueve el
         chasis (menu movil, desplegables). Sin app.js el boton del menu no
         responde en el telefono. */ ?>
<script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
<script defer src="<?= e(base_url('assets/js/track.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/track.js') ?: 1)) ?>"></script>


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
