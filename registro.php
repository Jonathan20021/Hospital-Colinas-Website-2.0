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
 * Deliberadamente mínimo y sin la cabecera del sitio: quien escanea está de pie
 * en un pasillo con el teléfono en la mano. Nada que distraiga del único paso
 * que tiene que dar.
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

/** Hora dominicana `8:00 a. m.` — el sitio ya corre en America/Santo_Domingo. */
function reg_hora(string $iso): string {
    $ts = (int) strtotime($iso);
    $h = (int) date('g', $ts);
    return $h . ':' . date('i', $ts) . ' ' . (date('a', $ts) === 'am' ? 'a. m.' : 'p. m.');
}
$DIAS  = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
          'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];

$ventana = (string) ($act['window'] ?? '');
$abierta = $ventana === 'abierta';
$CERRADA = [
    'aun_no'   => 'El registro para esta actividad aún no ha abierto. Vuelve a escanear cuando comience.',
    'cerrada'  => 'El registro para esta actividad ya cerró. Si asististe, acércate a Recursos Humanos.',
    'inactiva' => 'Esta actividad todavía no está habilitada para registro.',
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
<main class="reg-wrap">
    <header class="reg-marca">
        <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas" class="reg-logo">
    </header>

    <?php if (!$act): ?>
        <div class="reg-card reg-card-aviso">
            <i data-lucide="scan-line"></i>
            <h1>No reconocemos este código</h1>
            <p>
                Comprueba que escaneaste el código del cartel de la actividad. Si el problema
                sigue, acércate a Recursos Humanos.
            </p>
            <a href="<?= e(base_url('bienestar')) ?>" class="btn btn-navy">Ver el programa</a>
        </div>
    <?php else: ?>
        <div class="reg-card">
            <p class="reg-evento"><?= e((string) ($act['eventName'] ?? '')) ?></p>
            <?php if (!empty($act['tagline'])): ?>
                <p class="reg-lema">«<?= e((string) $act['tagline']) ?>»</p>
            <?php endif; ?>

            <h1><?= e((string) ($act['title'] ?? '')) ?></h1>
            <?php if (!empty($act['speakers'])): ?>
                <p class="reg-ponentes"><?= e((string) $act['speakers']) ?></p>
            <?php endif; ?>

            <?php $ts = (int) strtotime((string) $act['startsAt']); ?>
            <ul class="reg-meta">
                <li>
                    <i data-lucide="calendar-days"></i>
                    <?= e(ucfirst($DIAS[date('l', $ts)] ?? '')) ?>, <?= e(date('j', $ts)) ?>
                    de <?= e($MESES[(int) date('n', $ts)]) ?>
                </li>
                <li>
                    <i data-lucide="clock"></i>
                    <span class="tabular"><?= e(reg_hora((string) $act['startsAt'])) ?> – <?= e(reg_hora((string) $act['endsAt'])) ?></span>
                </li>
                <?php if (!empty($act['location'])): ?>
                    <li><i data-lucide="map-pin"></i><?= e((string) $act['location']) ?></li>
                <?php endif; ?>
            </ul>

            <?php if (!$abierta): ?>
                <p class="reg-aviso is-espera">
                    <i data-lucide="clock-3"></i>
                    <?= e($CERRADA[$ventana] ?? 'El registro no está disponible en este momento.') ?>
                </p>
            <?php else: ?>
                <form id="reg-form" class="reg-form" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="reg-campo">
                        <span>Escribe tu cédula para registrarte</span>
                        <input type="text" name="cedula" inputmode="numeric" autocomplete="off"
                            placeholder="001-0000000-0" required maxlength="16">
                    </label>
                    <div class="reg-aviso is-error" id="reg-error" hidden></div>
                    <button type="submit" class="btn btn-green" id="reg-enviar">
                        <span class="reg-label">Registrar mi asistencia</span>
                        <span class="reg-loading" hidden>Registrando…</span>
                    </button>
                </form>

                <div class="reg-ok" id="reg-ok" hidden>
                    <i data-lucide="check-circle-2"></i>
                    <h2 id="reg-ok-titulo"></h2>
                    <p id="reg-ok-texto"></p>
                </div>
            <?php endif; ?>
        </div>

        <p class="reg-pie">
            Hospital General Las Colinas · Recursos Humanos<br>
            <a href="<?= e(base_url('bienestar')) ?>">Ver todo el programa</a>
        </p>
    <?php endif; ?>
</main>

<script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>
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

    function fallo(msg) { error.textContent = msg; error.hidden = false; }

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
                if (j && j.ok) {
                    var faltan = Math.max(0, (j.minActivities || 0) - (j.count || 0));
                    document.getElementById('reg-ok-titulo').textContent =
                        j.alreadyIn ? ('Ya estabas registrado, ' + (j.greeting || '')) : ('¡Listo, ' + (j.greeting || '') + '!');
                    var t = j.alreadyIn
                        ? 'No hace falta registrarse dos veces en la misma actividad. '
                        : 'Tu asistencia quedó registrada. ';
                    t += 'Llevas ' + (j.count || 0) + ' actividad' + ((j.count === 1) ? '' : 'es') + ' esta semana.';
                    if (faltan > 0) t += ' Te falta' + (faltan === 1 ? '' : 'n') + ' ' + faltan + ' para entrar en la rifa.';
                    else t += ' Ya calificas para la rifa.';
                    document.getElementById('reg-ok-texto').textContent = t;
                    form.hidden = true; ok.hidden = false;
                    if (window.lucide) lucide.createIcons();
                } else {
                    fallo((j && j.error) ? j.error : 'No pudimos registrar tu asistencia. Acércate a Recursos Humanos.');
                }
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
