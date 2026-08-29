<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';
require __DIR__ . '/includes/portal_directory.php';
require __DIR__ . '/includes/doctor_avatar.php';

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js'),
    @filemtime(__DIR__ . '/assets/css/portal.css') ?: 0,
    @filemtime(__DIR__ . '/assets/js/portal.js') ?: 0,
    @filemtime(__DIR__ . '/assets/js/agendar.js') ?: 0,
    @filemtime(__DIR__ . '/assets/js/agendar-wizard.js') ?: 0,
    @filemtime(__DIR__ . '/assets/css/agendar.css') ?: 0
);

// Paso actual
$specId = (int) ($_GET['specialty_id'] ?? 0);
$docId = (int) ($_GET['doctor_id'] ?? 0);

// Cargar catálogos via API (con cache 1h)
$specsRes = portal_directory_specialties();
$specs = $specsRes['ok'] ? $specsRes['data'] : [];

// Orden alfabético insensible a acentos/mayúsculas: agrupa variantes juntas
// (p.ej. CIRUGÍA GENERAL / CIRUGÍA MAXILOFACIAL / CIRUGÍA VISCERAL). Solo afecta
// la presentación aquí; no cambia los datos en JENOFONTE.
usort($specs, static function ($a, $b) {
    $norm = static fn($s) => strtr(
        mb_strtoupper(trim((string) ($s['name'] ?? '')), 'UTF-8'),
        ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']
    );
    return strcmp($norm($a), $norm($b));
});

$docsRes = portal_directory_doctors();
$allDocs = $docsRes['ok'] ? $docsRes['data'] : [];

// Si vienen specialty/doctor, filtrar
$doctors = [];
$selectedDoctor = null;
if ($specId) {
    $doctors = array_values(array_filter($allDocs, fn($d) => (int) ($d['specialty_id'] ?? 0) === $specId));
}
if ($docId) {
    foreach ($allDocs as $d) {
        if ((int) $d['id'] === $docId) {
            $selectedDoctor = $d;
            break;
        }
    }
}

// hCaptcha site key (opcional - sirve si está configurado en el hospital)
$hcaptchaSiteKey = defined('HCAPTCHA_SITE_KEY') ? HCAPTCHA_SITE_KEY : '';

$step = $docId ? 3 : ($specId ? 2 : 1);

// Datos de medicos para el wizard de una sola pagina. SOLO lo que la agenda
// necesita: nada de correo, telefono ni exequatur (eso es dato personal del
// medico y no tiene por que viajar al navegador de un paciente).
$docsForJs = array_map(static function (array $d) {
    return [
        'id'          => (int) ($d['id'] ?? 0),
        'specialtyId' => (int) ($d['specialty_id'] ?? 0),
        'name'        => (string) ($d['name'] ?? ''),
        'specialty'   => (string) ($d['specialty'] ?? ''),
        'subspecialty'=> (string) ($d['subspecialty'] ?? ''),
        'office'      => (string) ($d['office_name'] ?? ''),
        'photo'       => !empty($d['photo_url']) ? portal_directory_photo_url($d['photo_url']) : doctor_avatar_svg($d['name'] ?? 'Medico'),
        'from'        => substr((string) ($d['schedule']['start'] ?? '09:00'), 0, 5),
        'to'          => substr((string) ($d['schedule']['end'] ?? '17:00'), 0, 5),
    ];
}, $allDocs);

// Cuantos medicos hay por especialidad: se muestra en la tarjeta para que el
// paciente sepa si tiene donde elegir antes de entrar.
$docsPorSpec = [];
foreach ($allDocs as $d) {
    $sid = (int) ($d['specialty_id'] ?? 0);
    if ($sid) { $docsPorSpec[$sid] = ($docsPorSpec[$sid] ?? 0) + 1; }
}

/**
 * true si el nombre trae una palabra que no cabe de una pieza en la tarjeta.
 *
 * OTORRINOLARINGOLOGIA pide 199 px y solo hay 167, asi que siempre parte. El
 * guionado automatico (hyphens:auto + lang="es-DO") depende de que el navegador
 * tenga cargado el diccionario espanol, y no en todos esta: sin el sale
 * "GASTROENTEROLOGI / A". Bajar la letra SOLO en esos nombres es determinista y
 * no encoge las otras 23 tarjetas.
 */
function ag_nombre_largo(string $nombre): bool
{
    foreach (preg_split('/\s+/u', trim($nombre)) as $palabra) {
        if (mb_strlen($palabra, 'UTF-8') > 15) { return true; }
    }
    return false;
}

// Las mas solicitadas primero (ver $topSpecialtyNames en includes/data.php).
$topSpecs = [];
foreach (($topSpecialtyNames ?? []) as $nombre) {
    foreach ($specs as $sp) {
        if (mb_strtoupper(trim((string) $sp['name']), 'UTF-8') === mb_strtoupper($nombre, 'UTF-8')) {
            $topSpecs[] = $sp;
            break;
        }
    }
}

$specNames = [];
foreach ($specs as $sp) { $specNames[(int) $sp['id']] = (string) $sp['name']; }
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar cita en línea | Hospital General Las Colinas</title>
    <meta name="description"
        content="Agenda tu cita en línea con cualquiera de nuestros especialistas. Sin necesidad de crear cuenta. Hospital General Las Colinas, Santiago, RD.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <?php /* Fuentes auto-hospedadas (Inter + Outfit + Plus Jakarta Sans, VARIABLES):
             mismo origen, sin DNS/TLS a Google ni CSS render-blocking. */ ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/agendar.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php if ($hcaptchaSiteKey): ?>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="portal-shell portal-shell-app agendar-v2" style="grid-template-columns: 1fr; max-width: 960px">
        <div class="portal-main">

            <?php /* .is-compacta la pone el wizard a partir del paso 2: en el 3 esta
                     cabecera ocupaba 300 px de los 4013 de la pagina en movil. */ ?>
            <header class="portal-header<?= $step > 1 ? ' is-compacta' : '' ?>" id="ag-cabecera">
                <div>
                    <p class="section-label">Agendamiento en línea</p>
                    <h1>Agenda tu consulta médica</h1>
                    <p class="portal-subtitle">Reserva tu cita con cualquiera de nuestros especialistas. Sin necesidad
                        de crear cuenta &mdash; te tomará menos de dos minutos.</p>
                </div>
                <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-outline">
                    <i data-lucide="user-round" class="h-4 w-4"></i> Ya tengo cuenta
                </a>
            </header>

            <ol class="portal-steps">
                <li class="<?= $step === 1 ? 'is-current' : 'is-done' ?>"><span>1</span> Especialidad</li>
                <li class="<?= $step === 2 ? 'is-current' : ($step > 2 ? 'is-done' : '') ?>"><span>2</span> Médico</li>
                <li class="<?= $step === 3 ? 'is-current' : '' ?>"><span>3</span> Fecha y hora</li>
                <li><span>4</span> Tus datos</li>
            </ol>

            <?php /* Los 3 pasos viven en la MISMA pagina; se muestra el que toca. */ ?>
            <section class="ag-paso" id="ag-paso-1" data-paso="1"<?= $step !== 1 ? ' hidden' : '' ?>>
                <!-- Paso 1: Especialidad -->
                <form method="GET" class="portal-card" id="step1">
                    <h2 class="portal-section-title"><i data-lucide="stethoscope" class="h-5 w-5"
                            style="display:inline-block;vertical-align:-4px;color:#047857;margin-right:.35rem"></i>¿Qué tipo
                        de atención necesitas?</h2>

                    <?php if ($topSpecs): ?>
                        <?php /* Las 5 mas pedidas concentran el 81% de las citas web. */ ?>
                        <div class="ag-top">
                            <p class="ag-top-label"><i data-lucide="trending-up" class="h-4 w-4"></i> Las más solicitadas</p>
                            <div class="ag-top-grid">
                                <?php foreach ($topSpecs as $sp): ?>
                                    <button type="submit" name="specialty_id" value="<?= (int) $sp['id'] ?>" class="specialty-card ag-top-card"
                                        data-search="<?= e(mb_strtolower($sp['name'], 'UTF-8')) ?>">
                                        <span class="specialty-card-name<?= ag_nombre_largo((string) $sp['name']) ? ' es-larga' : '' ?>"><?= e($sp['name']) ?></span>
                                        <?php if (!empty($docsPorSpec[(int) $sp['id']])): ?>
                                            <span class="ag-card-count"><?= (int) $docsPorSpec[(int) $sp['id']] ?> especialistas</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="agendar-search">
                        <i data-lucide="search" class="h-4 w-4 agendar-search-icon"></i>
                        <input type="search" id="specialty-search" class="form-input agendar-search-input"
                            placeholder="Busca una especialidad (ej. cardiología, pediatría…)" autocomplete="off"
                            aria-controls="specialty-list" aria-label="Buscar especialidad">
                        <button type="button" class="agendar-search-clear" id="specialty-search-clear"
                            aria-label="Limpiar búsqueda" hidden>
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <ul class="specialty-grid" id="specialty-list" role="list">
                        <?php foreach ($specs as $s): ?>
                            <li>
                                <button type="submit" name="specialty_id" value="<?= (int) $s['id'] ?>" class="specialty-card"
                                    data-search="<?= e(mb_strtolower($s['name'], 'UTF-8')) ?>">
                                    <span class="specialty-card-icon"><i data-lucide="stethoscope" class="h-5 w-5"></i></span>
                                    <span class="specialty-card-name<?= ag_nombre_largo((string) $s['name']) ? ' es-larga' : '' ?>"><?= e($s['name']) ?></span>
                                    <?php if (!empty($docsPorSpec[(int) $s['id']])): ?>
                                        <?php $n = (int) $docsPorSpec[(int) $s['id']]; ?>
                                        <span class="ag-card-count"><?= $n ?> <?= $n === 1 ? 'especialista' : 'especialistas' ?></span>
                                    <?php endif; ?>
                                    <i data-lucide="arrow-right" class="h-4 w-4 specialty-card-arrow"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="specialty-empty" id="specialty-empty" hidden>
                        <i data-lucide="search-x" class="h-5 w-5"></i>
                        No encontramos especialidades con ese término. Llámanos al <a href="tel:18098060444"
                            class="portal-text-link">(809) 806-0444</a> y te orientamos.
                    </p>

                    <noscript>
                        <div class="agendar-field" style="margin-top:1rem">
                            <label class="form-label" for="specialty_id">O usa el menú desplegable:</label>
                            <select name="specialty_id" id="specialty_id" class="form-input" required>
                                <option value="">— Elige una especialidad —</option>
                                <?php foreach ($specs as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-green mt-3">Continuar</button>
                        </div>
                    </noscript>

                    <p class="agendar-hint" style="margin-top:1rem">
                        <i data-lucide="info" class="h-4 w-4"></i>
                        <?= count($specs) ?> especialidades disponibles. Si no sabes cuál elegir, llámanos al <a
                            href="tel:18098060444" class="portal-text-link">(809) 806-0444</a>.
                    </p>
                </form>

                <script>
                    (function () {
                        var input = document.getElementById('specialty-search');
                        var list = document.getElementById('specialty-list');
                        var empty = document.getElementById('specialty-empty');
                        var clear = document.getElementById('specialty-search-clear');
                        if (!input || !list) return;

                        var items = Array.prototype.slice.call(list.querySelectorAll('.specialty-card'));

                        function normalize(str) {
                            return (str || '')
                                .toLowerCase()
                                .normalize('NFD')
                                .replace(/[̀-ͯ]/g, '');
                        }

                        function filter() {
                            var q = normalize(input.value.trim());
                            var visible = 0;
                            items.forEach(function (btn) {
                                var hay = normalize(btn.getAttribute('data-search') || '');
                                var match = !q || hay.indexOf(q) !== -1;
                                btn.parentElement.style.display = match ? '' : 'none';
                                if (match) visible++;
                            });
                            empty.hidden = visible !== 0;
                            clear.hidden = q.length === 0;
                        }

                        input.addEventListener('input', filter);
                        clear.addEventListener('click', function () {
                            input.value = '';
                            filter();
                            input.focus();
                        });
                        input.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                var firstVisible = items.find(function (b) { return b.parentElement.style.display !== 'none'; });
                                if (firstVisible) firstVisible.click();
                            }
                        });
                        input.focus();
                    })();
                </script>

            </section>

            <section class="ag-paso" id="ag-paso-2" data-paso="2"<?= $step !== 2 ? ' hidden' : '' ?>>
                <!-- Paso 2: Médico -->
                <div class="portal-card">
                    <h2 class="portal-section-title">Médicos disponibles</h2>
                    <?php /* Los dos existen siempre: el wizard alterna cual se ve. */ ?>
                    <div class="portal-empty" id="ag-doctors-empty"<?= $doctors ? ' hidden' : '' ?>>
                            <i data-lucide="user-round-x" class="h-10 w-10"></i>
                            <p>No hay médicos registrados para esa especialidad.</p>
                        <a href="<?= e(base_url('agendar')) ?>" class="portal-text-link" data-volver="1">Elegir otra especialidad</a>
                    </div>
                    <div class="portal-doctors" id="ag-doctors"<?= $doctors ? '' : ' hidden' ?>>
                            <?php foreach ($doctors as $d):
                                $photo = !empty($d['photo_url'])
                                    ? portal_directory_photo_url($d['photo_url'])
                                    : doctor_avatar_svg($d['name'] ?? 'Médico');
                                ?>
                                <article class="portal-doctor">
                                    <img src="<?= e($photo) ?>" alt="<?= e($d['name']) ?>"
                                        style="width:56px;height:56px;border-radius:50%;object-fit:cover">
                                    <div>
                                        <h3><?= e($d['name']) ?></h3>
                                        <p><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($d['specialty']) ?><?php if (!empty($d['subspecialty'])): ?> · <?= e($d['subspecialty']) ?><?php endif; ?></p>
                                        <?php if (!empty($d['office_name'])): ?>
                                            <p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i> <?= e($d['office_name']) ?></p>
                                        <?php endif; ?>
                                        <p class="portal-hint">Horario:
                                            <?= e(substr($d['schedule']['start'] ?? '09:00', 0, 5)) ?>–<?= e(substr($d['schedule']['end'] ?? '17:00', 0, 5)) ?>
                                        </p>
                                        <?php /* Lo rellena el JS con la primera fecha con cupo. */ ?>
                                        <p class="ag-prox" data-prox="<?= (int) $d['id'] ?>" hidden></p>
                                    </div>
                                    <a href="?specialty_id=<?= $specId ?>&doctor_id=<?= (int) $d['id'] ?>" class="btn btn-green">Ver
                                        fechas →</a>
                                </article>
                            <?php endforeach; ?>
                    </div>
                    <a href="<?= e(base_url('agendar')) ?>" class="portal-text-link mt-4 block" data-volver="1">← Cambiar especialidad</a>
                </div>

            </section>

            <section class="ag-paso" id="ag-paso-3" data-paso="3"<?= $step !== 3 ? ' hidden' : '' ?>>
                <!-- Paso 3: Slot + datos -->
                <?php if (!$selectedDoctor): ?>
                    <div class="portal-card portal-doctor-summary" id="ag-doctor-summary" hidden></div>
                <?php endif; ?>
                <?php if ($selectedDoctor): ?>
                    <div class="portal-card portal-doctor-summary" id="ag-doctor-summary">
                        <?php $photo = !empty($selectedDoctor['photo_url']) ? portal_directory_photo_url($selectedDoctor['photo_url']) : doctor_avatar_svg($selectedDoctor['name']); ?>
                        <img src="<?= e($photo) ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
                        <div>
                            <p class="section-label">Agendando con</p>
                            <h2><?= e($selectedDoctor['name']) ?></h2>
                            <p class="portal-hint"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i>
                                <?= e($selectedDoctor['specialty']) ?><?php if (!empty($selectedDoctor['subspecialty'])): ?> · <?= e($selectedDoctor['subspecialty']) ?><?php endif; ?></p>
                        </div>
                        <a href="?specialty_id=<?= $specId ?>" class="portal-text-link portal-change-link" data-volver="2">Cambiar médico</a>
                    </div>
                <?php endif; ?>

                <div class="portal-card" id="ag-slot-card" data-doctor-id="<?= $docId ?>">
                    <h2 class="portal-section-title">Selecciona fecha y hora</h2>
                    <div class="portal-slot-loader" id="slot-loader">
                        <i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i>
                        <span class="portal-slot-loader-text">Cargando horarios disponibles…</span>
                    </div>
                    <div id="slot-picker" class="portal-slot-picker hidden"></div>
                </div>

                <?php /* Aparece al elegir hora. Antes el formulario entero se
                         desplegaba aqui mismo; ahora esto es la unica salida. */ ?>
                <div class="ag-continuar" id="ag-continuar" hidden>
                    <div>
                        <p class="ag-continuar-label">Hora seleccionada</p>
                        <p class="ag-continuar-when" id="ag-elegido">&mdash;</p>
                    </div>
                    <button type="button" class="btn btn-green ag-continuar-btn" id="ag-ir-datos">
                        Continuar <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
            </section>

            <section class="ag-paso" id="ag-paso-4" data-paso="4" hidden>
                <!-- Paso 4: los datos del paciente -->
                <?php /* El resumen va ARRIBA, no enterrado sobre el captcha: es
                         lo que el paciente necesita ver mientras escribe. */ ?>
                <div class="ag-resumen" id="ag-resumen">
                    <div class="ag-resumen-icono"><i data-lucide="calendar-check" class="h-6 w-6"></i></div>
                    <div class="ag-resumen-txt">
                        <p class="ag-resumen-label">Tu cita</p>
                        <h2 id="confirm-when">&mdash;</h2>
                        <p class="ag-confirm-medico" id="ag-confirm-medico"></p>
                    </div>
                    <button type="button" class="ag-resumen-cambiar" data-volver="3">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Cambiar
                    </button>
                </div>

                <?php /* novalidate: los campos siguen siendo `required` (semantica y
                         lectores de pantalla), pero la validacion nativa se dispara
                         ANTES del submit y con burbujas del navegador, en su idioma
                         y sin estilo. Con esto manda la nuestra, que cuelga el
                         mensaje del campo. */ ?>
                <form id="guest-form" class="portal-card" novalidate>
                    <h2 class="portal-section-title">Tus datos para la cita</h2>
                    <p class="ag-form-intro">Los necesitamos para registrarte en el expediente y enviarte la confirmación.</p>
                    <input type="hidden" name="doctor_id" value="<?= $docId ?>">
                    <input type="hidden" name="appointment_time" id="appointment_time">

                    <div class="portal-grid-2">
                        <div>
                            <label class="form-label" for="g-name">Nombre completo</label>
                            <input type="text" name="name" id="g-name" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" for="g-cedula">Cédula</label>
                            <input type="text" name="cedula" id="g-cedula" class="form-input" required
                                inputmode="numeric" autocomplete="off" placeholder="000-0000000-0">
                        </div>
                    </div>
                    <div class="portal-grid-2">
                        <div>
                            <label class="form-label" for="g-email">Correo electrónico</label>
                            <input type="email" name="email" id="g-email" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" for="g-phone">Teléfono</label>
                            <input type="tel" name="phone" id="g-phone" class="form-input" required
                                placeholder="(809) 000-0000">
                        </div>
                    </div>

                    <?php
                    // ARS que el hospital acepta — alineadas con el catálogo oficial
                    // de facturación (SGC, tabla `seguro`, entradas activas). Ordenadas
                    // por uso real de los pacientes.
                    $arsOptions = [
                        'SeNaSa', 'ARS Humano', 'ARS Primera', 'ARS Universal', 'ARS Mapfre Salud',
                        'ARS Monumental', 'ARS Futuro', 'ARS Reservas', 'ARS Yunén', 'ARS MetaSalud',
                        'ARS Simag', 'ARS APS', 'ARS Asemap', 'ARS SEMMA',
                        'ARS GMA (Grupo Médico Asociado)', 'ARS CMD (Colegio Médico Dominicano)',
                        'ARS UASD', 'IDOPRIL', 'Plan Salud Banco Central',
                    ];
                    ?>
                    <div class="portal-grid-2 mt-1">
                        <div>
                            <label class="form-label" for="g-ars">ARS / Seguro médico (opcional)</label>
                            <select name="ars_select" id="g-ars" class="form-input">
                                <option value="">— Selecciona tu ARS —</option>
                                <?php foreach ($arsOptions as $ars): ?>
                                    <option value="<?= e($ars) ?>"><?= e($ars) ?></option>
                                <?php endforeach; ?>
                                <option value="__directo__">Pago directo / Privado</option>
                                <option value="__otra__">Otra…</option>
                            </select>
                        </div>
                        <div id="g-ars-otra-wrap" hidden>
                            <label class="form-label" for="g-ars-otra">Nombre de tu ARS</label>
                            <input type="text" id="g-ars-otra" class="form-input" maxlength="100"
                                placeholder="Escribe el nombre de tu ARS">
                        </div>
                    </div>

                    <label class="form-label mt-3" for="g-notes">Motivo de la consulta (opcional)</label>
                    <textarea name="notes" id="g-notes" rows="2" class="form-input"
                        placeholder="Síntomas, consulta general, control, etc."></textarea>

                    <?php if ($hcaptchaSiteKey): ?>
                        <div class="h-captcha mt-4" data-sitekey="<?= e($hcaptchaSiteKey) ?>"></div>
                    <?php endif; ?>

                    <p class="portal-hint mt-3">Al confirmar aceptas la <a
                            href="<?= e(base_url('politica-de-privacidad')) ?>" class="portal-text-link">política de
                            privacidad</a> del hospital.</p>

                    <div id="guest-result"></div>

                    <div class="ag-acciones">
                        <button type="button" class="ag-volver" data-volver="3">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i> Volver
                        </button>
                        <button type="submit" class="btn btn-green ag-submit" id="g-submit">
                            <i data-lucide="check" class="h-4 w-4"></i> Confirmar cita
                        </button>
                    </div>

                    <ul class="ag-trust">
                        <li><i data-lucide="shield-check" class="h-4 w-4"></i> Agendar no tiene costo</li>
                        <li><i data-lucide="mail" class="h-4 w-4"></i> Confirmación por correo</li>
                        <li><i data-lucide="lock" class="h-4 w-4"></i> Datos protegidos</li>
                    </ul>
                </form>

                <script>
                    window.PORTAL_DOCTOR_ID = <?= $docId ?>;
                    window.AGENDAR_DOCTORS = <?= json_encode($docsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                    window.AGENDAR_SPECIALTIES = <?= json_encode($specNames, JSON_UNESCAPED_UNICODE) ?>;
                    window.AGENDAR_BASE_URL = <?= json_encode(base_url('agendar'), JSON_UNESCAPED_SLASHES) ?>;
                    window.AGENDAR_STATE = { specialtyId: <?= (int) $specId ?>, doctorId: <?= (int) $docId ?>, step: <?= (int) $step ?> };
                    window.AGENDAR_HCAPTCHA = <?= $hcaptchaSiteKey ? 'true' : 'false' ?>;
                    window.AGENDAR_TEL = <?= json_encode((string) ($contact['phone'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
                    window.AGENDAR_SLOTS_URL = <?= json_encode(base_url('api/agendar-slots.php')) ?>;
                    window.AGENDAR_PROXIMAS_URL = <?= json_encode(base_url('api/agendar-proximas.php')) ?>;
                    window.AGENDAR_SUBMIT_URL = <?= json_encode(base_url('api/guest-appointment.php')) ?>;
                </script>
            </section>

        </div>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
    <script>if (window.lucide) lucide.createIcons();</script>
    <?php /* Ahora los 3 pasos viven en la misma pagina: los dos scripts van siempre. */ ?>
    <script src="<?= e(base_url('assets/js/agendar.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script src="<?= e(base_url('assets/js/agendar-wizard.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/agendar-wizard.js') ?: 1)) ?>"></script>
    <script defer src="<?= e(base_url('assets/js/track.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/track.js') ?: 1)) ?>"></script>
</body>

</html>
