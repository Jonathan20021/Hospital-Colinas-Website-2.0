<?php
/**
 * /encuesta/{token} — Encuesta de satisfacción de una charla.
 *
 * Se abre desde el enlace que llega por correo al terminar la actividad. Vive
 * aquí, en el sitio público, por lo mismo que `/registro/{token}`: el correo se
 * lee desde el teléfono, fuera de la red del hospital, y `atenea` no se alcanza
 * desde fuera.
 *
 * Todo pasa por el puente de JENOFONTE. Esta página no toca ninguna base.
 *
 * > [!note] Son dos encuestas, no una.
 * > A quien **asistió** se le pregunta qué le pareció (cuatro estrellas y un
 * > comentario). A quien **se inscribió y no fue** se le pregunta una sola cosa:
 * > qué se lo impidió. Pedirle que puntúe una charla que no vio daría datos
 * > falsos, y es la clase de encuesta que nadie termina.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/portal_client.php';

$token = trim((string) ($_GET['token'] ?? ''));
$assetVersion = (string) max(
    @filemtime(__DIR__ . '/assets/css/app-core.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/bienestar.css') ?: 0
);

$enc = null;
if ($token !== '') {
    $res = portal_api_call('GET', '/portal/charlas/encuesta?token=' . rawurlencode($token));
    if (!empty($res['ok']) && is_array($res['data'] ?? null)) $enc = $res['data'];
}

$asistio   = !empty($enc['attended']);
$contestada = !empty($enc['answered']);
$saludo    = (string) ($enc['greeting'] ?? '');
$act       = is_array($enc['activity'] ?? null) ? $enc['activity'] : [];

$MOTIVOS = [
    'Me surgió algo del trabajo',
    'El horario no me convenía',
    'Estaba fuera del hospital',
    'Se me olvidó',
    'Otro motivo',
];
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Encuesta de satisfacción | Hospital General Las Colinas</title>
    <?php /* Una encuesta personal no debe acabar en un buscador. */ ?>
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
    <p class="reg-evento">Semana de Salud y Bienestar HGLC</p>
    <p class="reg-lema">«Cuidando a quienes nos necesitan»</p>
</header>

<main class="reg-wrap">
    <?php if (!$enc): ?>
        <div class="reg-card">
            <div class="reg-card-aviso">
                <span class="reg-estado-ico"><i data-lucide="scan-line"></i></span>
                <h1>No reconocemos este enlace</h1>
                <p>
                    Puede que lo hayas copiado a medias. Ábrelo desde el correo que te enviamos,
                    o acércate a Recursos Humanos.
                </p>
            </div>
        </div>
    <?php else: ?>

        <div class="reg-card">
            <div class="reg-card-cuerpo">
                <h1><?= e((string) ($act['title'] ?? '')) ?></h1>
                <?php if (!empty($act['speakers'])): ?>
                    <p class="enc-ponentes"><?= e((string) $act['speakers']) ?></p>
                <?php endif; ?>
                <ul class="reg-meta">
                    <li><i data-lucide="calendar"></i><span><?= e((string) ($act['when'] ?? '')) ?></span></li>
                    <?php if (!empty($act['location'])): ?>
                        <li><i data-lucide="map-pin"></i><span><?= e((string) $act['location']) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="reg-accion">
                <?php /* Ya contestó: se le agradece y no se le vuelve a preguntar. */ ?>
                <div class="reg-ok" id="enc-gracias" <?= $contestada ? '' : 'hidden' ?>>
                    <div class="reg-ok-ico"><i data-lucide="check"></i></div>
                    <h2>¡Gracias<?= $saludo !== '' ? ', ' . e($saludo) : '' ?>!</h2>
                    <p id="enc-gracias-texto">
                        <?= $asistio
                            ? 'Tu opinión ya quedó registrada. Con esto decidimos qué charlas repetimos el año que viene.'
                            : 'Anotado. Nos sirve para poner las próximas a una hora que te convenga.' ?>
                    </p>
                </div>

                <form id="enc-form" class="reg-form" <?= $contestada ? 'hidden' : '' ?> novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <?php if ($asistio): ?>
                        <p class="enc-intro">
                            <b>Hola<?= $saludo !== '' ? ', ' . e($saludo) : '' ?>.</b>
                            ¿Qué te pareció? Cuatro preguntas; solo la primera es obligatoria.
                        </p>

                        <?php
                        $PREGUNTAS = [
                            'overall'   => ['En general, la charla', true],
                            'content'   => ['El contenido: ¿te sirvió?', false],
                            'speakers'  => ['Quien la impartió', false],
                            'logistics' => ['El lugar y la organización', false],
                        ];
                        foreach ($PREGUNTAS as $campo => [$etiqueta, $obligatoria]): ?>
                            <fieldset class="enc-grupo" data-campo="<?= e($campo) ?>">
                                <legend>
                                    <?= e($etiqueta) ?><?= $obligatoria ? ' <span class="enc-req">*</span>' : '' ?>
                                </legend>
                                <div class="enc-estrellas">
                                    <?php for ($n = 1; $n <= 5; $n++): ?>
                                        <button type="button" class="enc-estrella" data-valor="<?= $n ?>"
                                                aria-label="<?= $n ?> de 5" aria-pressed="false">
                                            <i data-lucide="star"></i>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="<?= e($campo) ?>" value="">
                            </fieldset>
                        <?php endforeach; ?>

                        <fieldset class="enc-grupo">
                            <legend>¿Se la recomendarías a un compañero?</legend>
                            <div class="enc-si-no">
                                <button type="button" class="enc-opcion" data-reco="1" aria-pressed="false">
                                    <i data-lucide="thumbs-up"></i> Sí
                                </button>
                                <button type="button" class="enc-opcion" data-reco="0" aria-pressed="false">
                                    <i data-lucide="x"></i> No
                                </button>
                            </div>
                            <input type="hidden" name="recommends" value="">
                        </fieldset>

                        <label class="enc-campo">
                            <span>Comentario <small>(opcional)</small></span>
                            <textarea name="comment" rows="3" maxlength="2000"
                                      placeholder="¿Qué repetirías? ¿Qué cambiarías?"></textarea>
                        </label>

                    <?php else: ?>
                        <p class="enc-intro">
                            <b>Hola<?= $saludo !== '' ? ', ' . e($saludo) : '' ?>. Te esperábamos.</b>
                            Reservaste tu lugar y no pudimos verte. ¿Qué te lo impidió?
                        </p>

                        <div class="enc-motivos">
                            <?php foreach ($MOTIVOS as $m): ?>
                                <button type="button" class="enc-motivo" data-motivo="<?= e($m) ?>" aria-pressed="false">
                                    <?= e($m) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="absenceReason" value="">

                        <label class="enc-campo">
                            <span>¿Algo más? <small>(opcional)</small></span>
                            <textarea name="comment" rows="3" maxlength="2000"
                                      placeholder="Lo que quieras contarnos…"></textarea>
                        </label>
                    <?php endif; ?>

                    <div class="reg-aviso is-error" id="enc-error" hidden>
                        <i data-lucide="alert-circle"></i><span></span>
                    </div>

                    <button type="submit" class="btn btn-primary" id="enc-enviar">
                        <span><?= $asistio ? 'Enviar mi opinión' : 'Enviar' ?></span>
                    </button>
                    <?php if ($asistio): ?>
                        <p class="reg-ayuda">
                            Tu respuesta es para mejorar las charlas. No se comparte con tu supervisor.
                        </p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <p class="reg-pie">
        Hospital General Las Colinas &middot; Gerencia de Recursos Humanos<br>
        <a href="<?= e(base_url('bienestar')) ?>">Ver el programa de la semana</a>
    </p>
</main>

<script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e($assetVersion) ?>"></script>
<?php /* Sin `defer` y con la llamada explicita: el subset NO se auto-invoca,
       y con `defer` las estrellas salian como cajas vacias. */ ?>
<script>if (window.lucide) lucide.createIcons();</script>
<script>
(function () {
    'use strict';
    var form = document.getElementById('enc-form');
    if (!form) return;

    var error = document.getElementById('enc-error');
    var gracias = document.getElementById('enc-gracias');
    var boton = document.getElementById('enc-enviar');

    function marcar(grupo, valor) {
        var campo = grupo.querySelector('input[type=hidden]');
        campo.value = String(valor);
        grupo.querySelectorAll('.enc-estrella').forEach(function (b) {
            var n = Number(b.dataset.valor);
            b.classList.toggle('is-on', n <= valor);
            b.setAttribute('aria-pressed', n === valor ? 'true' : 'false');
        });
    }

    form.querySelectorAll('.enc-grupo[data-campo]').forEach(function (grupo) {
        grupo.querySelectorAll('.enc-estrella').forEach(function (b) {
            b.addEventListener('click', function () { marcar(grupo, Number(b.dataset.valor)); });
        });
    });

    var reco = form.querySelector('input[name=recommends]');
    form.querySelectorAll('.enc-opcion').forEach(function (b) {
        b.addEventListener('click', function () {
            reco.value = b.dataset.reco;
            form.querySelectorAll('.enc-opcion').forEach(function (o) {
                var on = o === b;
                o.classList.toggle('is-on', on);
                o.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
    });

    var motivo = form.querySelector('input[name=absenceReason]');
    form.querySelectorAll('.enc-motivo').forEach(function (b) {
        b.addEventListener('click', function () {
            motivo.value = b.dataset.motivo;
            form.querySelectorAll('.enc-motivo').forEach(function (o) {
                var on = o === b;
                o.classList.toggle('is-on', on);
                o.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
    });

    function fallo(msg) {
        error.querySelector('span').textContent = msg;
        error.hidden = false;
        if (window.lucide) lucide.createIcons();
        boton.disabled = false;
        boton.querySelector('span').textContent = form.querySelector('[name=absenceReason]') ? 'Enviar' : 'Enviar mi opinión';
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        error.hidden = true;

        var general = form.querySelector('input[name=overall]');
        if (general && !general.value) return fallo('Dinos al menos la valoración general.');
        if (motivo && !motivo.value) return fallo('Elige qué te lo impidió.');

        boton.disabled = true;
        boton.querySelector('span').textContent = 'Enviando…';

        fetch('<?= e(base_url('api/encuesta-responder.php')) ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form)
        })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
            .then(function (d) {
                if (!d || !d.ok) return fallo((d && d.error) || 'No pudimos guardar tu respuesta. Inténtalo de nuevo.');
                form.hidden = true;
                gracias.hidden = false;
                if (window.lucide) lucide.createIcons();
                gracias.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(function () { fallo('Sin conexión. Revisa tus datos e inténtalo de nuevo.'); });
    });
})();
</script>
</body>
</html>
