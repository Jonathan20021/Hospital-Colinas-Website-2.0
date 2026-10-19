<?php
/**
 * /registro/{token} — Registro de asistencia desde el QR de una actividad.
 *
 * Es lo que escanea el colaborador en el cartel pegado a la puerta. Vive aquí,
 * en el sitio público, porque el QR lo abre un teléfono con datos móviles:
 * `atenea` está en la red interna del hospital y no la alcanza.
 *
 * Todo pasa por el puente de JENOFONTE, igual que las vacantes y las
 * inscripciones. Esta página no toca ninguna base de datos.
 *
 * Pantalla de pasillo: de pie, con una mano, a veces con mala señal y con
 * gente esperando detrás. Una sola cosa que hacer y dicha sin rodeos — por eso
 * cuando el registro no está abierto, se dice **a qué hora abre**, no solo que
 * no lo está.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/portal_client.php';

$token = trim((string) ($_GET['token'] ?? ''));
$assetVersion = (string) max(
    @filemtime(__DIR__ . '/assets/css/app-core.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/bienestar.css') ?: 0
);

$act = null;
if ($token !== '') {
    $res = portal_api_call('GET', '/portal/charlas/registro?token=' . rawurlencode($token));
    if (!empty($res['ok']) && is_array($res['data'] ?? null)) $act = $res['data'];
}

$DIAS  = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
          'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];
$KIND  = ['CHARLA' => 'Charla', 'ESTACION' => 'Estación', 'FERIA' => 'Feria'];
// Mismo acento por día que la agenda: la pantalla del QR y el programa son la
// misma jornada y deben reconocerse.
$COLOR_DIA = [
    1 => ['#d6468c', 'rgba(214,70,140,.10)'], 2 => ['#5da334', 'rgba(93,163,52,.12)'],
    3 => ['#2f6fb5', 'rgba(47,111,181,.10)'], 4 => ['#d4584a', 'rgba(212,88,74,.10)'],
    5 => ['#6b4fa8', 'rgba(107,79,168,.10)'], 6 => ['#262161', 'rgba(38,33,97,.08)'],
    0 => ['#262161', 'rgba(38,33,97,.08)'],
];

/** Hora dominicana `8:00 a. m.` — el sitio ya corre en America/Santo_Domingo. */
function reg_hora(int $ts): string {
    $h = (int) date('g', $ts);
    return $h . ':' . date('i', $ts) . ' ' . (date('a', $ts) === 'am' ? 'a. m.' : 'p. m.');
}

$ventana = (string) ($act['window'] ?? '');
$abierta = $ventana === 'abierta';
$tsIni = $act ? (int) strtotime((string) $act['startsAt']) : 0;
$tsFin = $act ? (int) strtotime((string) $act['endsAt']) : 0;
// La ventana abre 30 minutos antes y cierra 30 después, igual que en el servidor.
$GRACIA = 1800;
$abreEn = $act ? ($tsIni - $GRACIA) - time() : 0;

$c = $act ? ($COLOR_DIA[(int) date('w', $tsIni)] ?? $COLOR_DIA[1]) : $COLOR_DIA[1];

$ESTADO = [
    'aun_no'   => ['clock-3',   'Todavía no abre el registro'],
    // `clock-off` no esta en `lucide-subset.js` y saldria como un hueco.
    'cerrada'  => ['calendar-off', 'El registro ya cerró'],
    'inactiva' => ['circle-dashed', 'Actividad no habilitada'],
];
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Registro de asistencia | Hospital General Las Colinas</title>
    <?php /* Un registro de asistencia no debe acabar en un buscador. */ ?>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#262161">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app-core.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bienestar.css')) ?>?v=<?= e($assetVersion) ?>">
</head>

<body class="registro-page">

<header class="reg-top">
    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas" class="reg-logo">
    <?php if ($act): ?>
        <p class="reg-evento"><?= e((string) ($act['eventName'] ?? '')) ?></p>
        <?php if (!empty($act['tagline'])): ?>
            <p class="reg-lema">«<?= e((string) $act['tagline']) ?>»</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="reg-evento">Registro de asistencia</p>
    <?php endif; ?>
</header>

<main class="reg-wrap">
    <?php if (!$act): ?>
        <div class="reg-card">
            <div class="reg-card-aviso">
                <span class="reg-estado-ico"><i data-lucide="scan-line"></i></span>
                <h1>No reconocemos este código</h1>
                <p>
                    Comprueba que escaneaste el código del cartel de la actividad. Si el problema
                    sigue, acércate a Recursos Humanos.
                </p>
                <a href="<?= e(base_url('bienestar')) ?>" class="btn btn-navy">Ver el programa</a>
            </div>
        </div>
    <?php else: ?>
        <article class="reg-card" style="--dia: <?= e($c[0]) ?>; --dia-suave: <?= e($c[1]) ?>;">
            <div class="reg-card-cuerpo">
                <div class="reg-kicker">
                    <span class="reg-chip">
                        <i data-lucide="message-square-quote"></i>
                        <?= e($KIND[$act['kind'] ?? 'CHARLA'] ?? 'Charla') ?>
                    </span>
                    <span class="reg-chip is-dia">
                        <?= e(ucfirst($DIAS[date('l', $tsIni)] ?? '')) ?> <?= e(date('j', $tsIni)) ?>
                    </span>
                </div>

                <h1><?= e((string) ($act['title'] ?? '')) ?></h1>

                <?php
                    // «Dra. X (ginecóloga) & Dra. Y (endocrinóloga)» → una por línea,
                    // con la especialidad aparte. Dos nombres largos en una sola
                    // línea son ilegibles en un teléfono.
                    $ponentes = [];
                    foreach (preg_split('/\s*(?:&|·)\s*/u', trim((string) ($act['speakers'] ?? ''))) ?: [] as $x) {
                        $x = trim($x);
                        if ($x === '') continue;
                        $esp = '';
                        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*(.*)$/u', $x, $m)) {
                            $x = trim($m[1]);
                            $esp = trim($m[2]) . (trim($m[3]) !== '' ? ' ' . trim($m[3]) : '');
                        }
                        if ($x !== '') $ponentes[] = [$x, $esp];
                    }
                ?>
                <?php if ($ponentes): ?>
                    <ul class="reg-ponentes">
                        <?php foreach ($ponentes as $pn): ?>
                            <li>
                                <b><?= e($pn[0]) ?></b>
                                <?php if ($pn[1] !== ''): ?><span><?= e($pn[1]) ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <ul class="reg-meta">
                    <li>
                        <i data-lucide="calendar-days"></i>
                        <?= e(ucfirst($DIAS[date('l', $tsIni)] ?? '')) ?>, <?= e(date('j', $tsIni)) ?>
                        de <?= e($MESES[(int) date('n', $tsIni)]) ?>
                    </li>
                    <li>
                        <i data-lucide="clock"></i>
                        <span class="tabular"><?= e(reg_hora($tsIni)) ?> – <?= e(reg_hora($tsFin)) ?></span>
                    </li>
                    <?php if (!empty($act['location'])): ?>
                        <li><i data-lucide="map-pin"></i><?= e((string) $act['location']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="reg-accion">
                <?php if (!$abierta): ?>
                    <?php $est = $ESTADO[$ventana] ?? ['circle-help', 'Registro no disponible']; ?>
                    <div class="reg-estado">
                        <span class="reg-estado-ico"><i data-lucide="<?= e($est[0]) ?>"></i></span>
                        <div>
                            <h2><?= e($est[1]) ?></h2>
                            <?php if ($ventana === 'aun_no'): ?>
                                <p>
                                    Abre a las <b><?= e(reg_hora($tsIni - $GRACIA)) ?></b>, media hora antes
                                    de que empiece. Puedes dejar esta pantalla abierta: se activará sola.
                                </p>
                                <span class="reg-cuenta" id="reg-cuenta" data-abre="<?= e((string) max(0, $abreEn)) ?>"></span>
                            <?php elseif ($ventana === 'cerrada'): ?>
                                <p>
                                    El registro cerró a las <b><?= e(reg_hora($tsFin + $GRACIA)) ?></b>.
                                    Si asististe, acércate a Recursos Humanos y lo anotan a mano.
                                </p>
                            <?php else: ?>
                                <p>Esta actividad todavía no está habilitada para registro.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <form id="reg-form" class="reg-form" novalidate>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <label class="reg-campo">
                            <span>Escribe tu cédula para registrarte</span>
                            <input type="text" name="cedula" inputmode="numeric" autocomplete="off"
                                placeholder="001 0000000 0" required maxlength="16">
                        </label>
                        <div class="reg-aviso is-error" id="reg-error" hidden></div>
                        <button type="submit" class="btn btn-green" id="reg-enviar">
                            <span class="reg-label">Registrar mi asistencia</span>
                            <span class="reg-loading" hidden>Registrando…</span>
                        </button>
                        <p class="reg-ayuda">Solo los números. No hace falta cuenta ni contraseña.</p>
                    </form>

                    <div class="reg-ok" id="reg-ok" hidden>
                        <span class="reg-ok-ico"><i data-lucide="check-circle-2"></i></span>
                        <h2 id="reg-ok-titulo"></h2>
                        <p id="reg-ok-texto"></p>
                        <div class="reg-progreso" id="reg-progreso" hidden>
                            <div class="reg-barra"><i id="reg-barra-i" style="width:0"></i></div>
                            <span id="reg-progreso-txt"></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <p class="reg-pie">
            Hospital General Las Colinas · Recursos Humanos<br>
            <a href="<?= e(base_url('bienestar')) ?>">Ver todo el programa</a>
        </p>
    <?php endif; ?>
</main>

<script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>

<?php if ($act && !$abierta && $ventana === 'aun_no'): ?>
<script>
/* Cuenta atrás hasta que abra, y recarga sola al llegar. Quien escanea diez
   minutos antes puede dejar el teléfono y la pantalla se activa sin tocarla. */
(function () {
    var el = document.getElementById('reg-cuenta');
    if (!el) return;
    var quedan = parseInt(el.getAttribute('data-abre'), 10) || 0;
    function pinta() {
        if (quedan <= 0) { el.textContent = 'Abriendo…'; location.reload(); return; }
        var m = Math.floor(quedan / 60), s = quedan % 60;
        el.textContent = m > 0
            ? 'Abre en ' + m + ' min ' + (s < 10 ? '0' : '') + s + ' s'
            : 'Abre en ' + s + ' s';
        quedan--;
        setTimeout(pinta, 1000);
    }
    pinta();
})();
</script>
<?php endif; ?>

<?php if ($act && $abierta): ?>
<script>
(function () {
    var form = document.getElementById('reg-form');
    var error = document.getElementById('reg-error');
    var ok = document.getElementById('reg-ok');
    var btn = document.getElementById('reg-enviar');
    var endpoint = <?= json_encode(base_url('api/registro-asistencia.php'), JSON_UNESCAPED_SLASHES) ?>;
    var campo = form.querySelector('input[name="cedula"]');
    campo.focus();

    function fallo(msg) {
        error.innerHTML = '<i data-lucide="alert-circle"></i><span></span>';
        error.querySelector('span').textContent = msg;
        error.hidden = false;
        if (window.lucide) lucide.createIcons();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        error.hidden = true;
        var ced = campo.value.replace(/\D/g, '');
        if (ced.length < 8) { fallo('Escribe tu cédula completa, solo los números.'); return; }

        btn.disabled = true;
        form.querySelector('.reg-label').hidden = true;
        form.querySelector('.reg-loading').hidden = false;

        fetch(endpoint, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
            .then(function (j) {
                if (!j || !j.ok) {
                    fallo((j && j.error) ? j.error : 'No pudimos registrar tu asistencia. Acércate a Recursos Humanos.');
                    return;
                }
                var n = j.count || 0, min = j.minActivities || 0, faltan = Math.max(0, min - n);
                document.getElementById('reg-ok-titulo').textContent =
                    j.alreadyIn ? ('Ya estabas registrado, ' + (j.greeting || '')) : ('¡Listo, ' + (j.greeting || '') + '!');
                document.getElementById('reg-ok-texto').textContent = j.alreadyIn
                    ? 'No hace falta registrarse dos veces en la misma actividad.'
                    : 'Tu asistencia quedó registrada.';
                if (min > 0) {
                    var p = document.getElementById('reg-progreso');
                    document.getElementById('reg-barra-i').style.width = Math.min(100, (n / min) * 100) + '%';
                    document.getElementById('reg-progreso-txt').innerHTML = faltan > 0
                        ? 'Llevas <b>' + n + '</b> de <b>' + min + '</b> actividades. Te falta' + (faltan === 1 ? '' : 'n') + ' <b>' + faltan + '</b> para entrar en la rifa.'
                        : 'Llevas <b>' + n + '</b> actividades. <b>Ya calificas para la rifa.</b>';
                    p.hidden = false;
                }
                form.hidden = true; ok.hidden = false;
                if (window.lucide) lucide.createIcons();
            })
            .catch(function () { fallo('No pudimos conectar. Revisa tu señal e inténtalo de nuevo.'); })
            .finally(function () {
                btn.disabled = false;
                form.querySelector('.reg-label').hidden = false;
                form.querySelector('.reg-loading').hidden = true;
            });
    });
})();
</script>
<?php endif; ?>
</body>

</html>
