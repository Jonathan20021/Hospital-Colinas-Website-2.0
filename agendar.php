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
    @filemtime(__DIR__ . '/assets/js/agendar.js') ?: 0
);

// Paso actual
$specId = (int)($_GET['specialty_id'] ?? 0);
$docId  = (int)($_GET['doctor_id'] ?? 0);

// Cargar catálogos via API (con cache 1h)
$specsRes = portal_directory_specialties();
$specs    = $specsRes['ok'] ? $specsRes['data'] : [];

$docsRes  = portal_directory_doctors();
$allDocs  = $docsRes['ok'] ? $docsRes['data'] : [];

// Si vienen specialty/doctor, filtrar
$doctors = [];
$selectedDoctor = null;
if ($specId) {
    $doctors = array_values(array_filter($allDocs, fn($d) => (int)($d['specialty_id'] ?? 0) === $specId));
}
if ($docId) {
    foreach ($allDocs as $d) {
        if ((int)$d['id'] === $docId) { $selectedDoctor = $d; break; }
    }
}

// hCaptcha site key (opcional - sirve si está configurado en el hospital)
$hcaptchaSiteKey = defined('HCAPTCHA_SITE_KEY') ? HCAPTCHA_SITE_KEY : '';

$step = $docId ? 3 : ($specId ? 2 : 1);
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar cita en línea | Hospital General Las Colinas</title>
    <meta name="description" content="Agenda tu cita en línea con cualquiera de nuestros especialistas. Sin necesidad de crear cuenta. Hospital General Las Colinas, Santiago, RD.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php if ($hcaptchaSiteKey): ?>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="portal-shell portal-shell-app" style="grid-template-columns: 1fr; max-width: 960px">
        <div class="portal-main">

            <header class="portal-header">
                <div>
                    <p class="section-label">Agendamiento en línea</p>
                    <h1>Agenda tu consulta médica</h1>
                    <p class="portal-subtitle">Reserva tu cita con cualquiera de nuestros especialistas. Sin necesidad de crear cuenta &mdash; te tomará menos de dos minutos.</p>
                </div>
                <a href="<?= e(base_url('portal/login.php')) ?>" class="btn btn-outline">
                    <i data-lucide="user-round" class="h-4 w-4"></i> Ya tengo cuenta
                </a>
            </header>

            <ol class="portal-steps">
                <li class="<?= $step === 1 ? 'is-current' : 'is-done' ?>"><span>1</span> Especialidad</li>
                <li class="<?= $step === 2 ? 'is-current' : ($step > 2 ? 'is-done' : '') ?>"><span>2</span> Médico</li>
                <li class="<?= $step === 3 ? 'is-current' : '' ?>"><span>3</span> Fecha y datos</li>
            </ol>

            <?php if ($step === 1): ?>
                <!-- Paso 1: Especialidad -->
                <form method="GET" class="portal-card" id="step1">
                    <h2 class="portal-section-title"><i data-lucide="stethoscope" class="h-5 w-5" style="display:inline-block;vertical-align:-4px;color:#047857;margin-right:.35rem"></i>¿Qué tipo de atención necesitas?</h2>

                    <div class="agendar-search">
                        <i data-lucide="search" class="h-4 w-4 agendar-search-icon"></i>
                        <input
                            type="search"
                            id="specialty-search"
                            class="form-input agendar-search-input"
                            placeholder="Busca una especialidad (ej. cardiología, pediatría…)"
                            autocomplete="off"
                            aria-controls="specialty-list"
                            aria-label="Buscar especialidad"
                        >
                        <button type="button" class="agendar-search-clear" id="specialty-search-clear" aria-label="Limpiar búsqueda" hidden>
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <ul class="specialty-grid" id="specialty-list" role="list">
                        <?php foreach ($specs as $s): ?>
                            <li>
                                <button type="submit" name="specialty_id" value="<?= (int)$s['id'] ?>"
                                        class="specialty-card"
                                        data-search="<?= e(mb_strtolower($s['name'], 'UTF-8')) ?>">
                                    <span class="specialty-card-icon"><i data-lucide="stethoscope" class="h-5 w-5"></i></span>
                                    <span class="specialty-card-name"><?= e($s['name']) ?></span>
                                    <i data-lucide="arrow-right" class="h-4 w-4 specialty-card-arrow"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="specialty-empty" id="specialty-empty" hidden>
                        <i data-lucide="search-x" class="h-5 w-5"></i>
                        No encontramos especialidades con ese término. Llámanos al <a href="tel:18098060444" class="portal-text-link">(809) 806-0444</a> y te orientamos.
                    </p>

                    <noscript>
                        <div class="agendar-field" style="margin-top:1rem">
                            <label class="form-label" for="specialty_id">O usa el menú desplegable:</label>
                            <select name="specialty_id" id="specialty_id" class="form-input" required>
                                <option value="">— Elige una especialidad —</option>
                                <?php foreach ($specs as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-green mt-3">Continuar</button>
                        </div>
                    </noscript>

                    <p class="agendar-hint" style="margin-top:1rem">
                        <i data-lucide="info" class="h-4 w-4"></i>
                        <?= count($specs) ?> especialidades disponibles. Si no sabes cuál elegir, llámanos al <a href="tel:18098060444" class="portal-text-link">(809) 806-0444</a>.
                    </p>
                </form>

                <script>
                (function () {
                    var input = document.getElementById('specialty-search');
                    var list  = document.getElementById('specialty-list');
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

            <?php elseif ($step === 2): ?>
                <!-- Paso 2: Médico -->
                <div class="portal-card">
                    <h2 class="portal-section-title">Médicos disponibles</h2>
                    <?php if (!$doctors): ?>
                        <div class="portal-empty">
                            <i data-lucide="user-round-x" class="h-10 w-10"></i>
                            <p>No hay médicos registrados para esa especialidad.</p>
                            <a href="<?= e(base_url('agendar')) ?>" class="portal-text-link">Elegir otra especialidad</a>
                        </div>
                    <?php else: ?>
                        <div class="portal-doctors">
                            <?php foreach ($doctors as $d):
                                $photo = !empty($d['photo_url'])
                                    ? portal_directory_photo_url($d['photo_url'])
                                    : doctor_avatar_svg($d['name'] ?? 'Médico');
                            ?>
                                <article class="portal-doctor">
                                    <img src="<?= e($photo) ?>" alt="<?= e($d['name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
                                    <div>
                                        <h3><?= e($d['name']) ?></h3>
                                        <p><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($d['specialty']) ?></p>
                                        <?php if (!empty($d['office_name'])): ?>
                                            <p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i> <?= e($d['office_name']) ?></p>
                                        <?php endif; ?>
                                        <p class="portal-hint">Horario: <?= e(substr($d['schedule']['start'] ?? '09:00', 0, 5)) ?>–<?= e(substr($d['schedule']['end'] ?? '17:00', 0, 5)) ?></p>
                                    </div>
                                    <a href="?specialty_id=<?= $specId ?>&doctor_id=<?= (int)$d['id'] ?>" class="btn btn-green">Ver fechas →</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="<?= e(base_url('agendar')) ?>" class="portal-text-link mt-4 block">← Cambiar especialidad</a>
                </div>

            <?php else: ?>
                <!-- Paso 3: Slot + datos -->
                <?php if ($selectedDoctor): ?>
                    <div class="portal-card portal-doctor-summary">
                        <?php $photo = !empty($selectedDoctor['photo_url']) ? portal_directory_photo_url($selectedDoctor['photo_url']) : doctor_avatar_svg($selectedDoctor['name']); ?>
                        <img src="<?= e($photo) ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
                        <div>
                            <p class="section-label">Agendando con</p>
                            <h2><?= e($selectedDoctor['name']) ?></h2>
                            <p class="portal-hint"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($selectedDoctor['specialty']) ?></p>
                        </div>
                        <a href="?specialty_id=<?= $specId ?>" class="portal-text-link portal-change-link">Cambiar médico</a>
                    </div>
                <?php endif; ?>

                <div class="portal-card" data-doctor-id="<?= $docId ?>">
                    <h2 class="portal-section-title">Selecciona fecha y hora</h2>
                    <div class="portal-slot-loader" id="slot-loader">
                        <i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i>
                        <span class="portal-slot-loader-text">Cargando horarios disponibles…</span>
                    </div>
                    <div id="slot-picker" class="portal-slot-picker hidden"></div>
                </div>

                <form id="guest-form" class="portal-card mt-4 hidden">
                    <h2 class="portal-section-title">Tus datos para la cita</h2>
                    <input type="hidden" name="doctor_id" value="<?= $docId ?>">
                    <input type="hidden" name="appointment_time" id="appointment_time">

                    <div class="portal-grid-2">
                        <div>
                            <label class="form-label" for="g-name">Nombre completo</label>
                            <input type="text" name="name" id="g-name" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" for="g-cedula">Cédula</label>
                            <input type="text" name="cedula" id="g-cedula" class="form-input" required placeholder="000-0000000-0">
                        </div>
                    </div>
                    <div class="portal-grid-2">
                        <div>
                            <label class="form-label" for="g-email">Correo electrónico</label>
                            <input type="email" name="email" id="g-email" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" for="g-phone">Teléfono</label>
                            <input type="tel" name="phone" id="g-phone" class="form-input" required placeholder="(809) 000-0000">
                        </div>
                    </div>

                    <label class="form-label mt-3" for="g-notes">Motivo de la consulta (opcional)</label>
                    <textarea name="notes" id="g-notes" rows="2" class="form-input" placeholder="Síntomas, consulta general, control, etc."></textarea>

                    <div class="portal-confirm-box mt-4">
                        <p>Cita seleccionada:</p>
                        <h3 id="confirm-when">—</h3>
                    </div>

                    <?php if ($hcaptchaSiteKey): ?>
                        <div class="h-captcha mt-4" data-sitekey="<?= e($hcaptchaSiteKey) ?>"></div>
                    <?php endif; ?>

                    <p class="portal-hint mt-3">Al confirmar aceptas la <a href="<?= e(base_url('politica-de-privacidad')) ?>" class="portal-text-link">política de privacidad</a> del hospital.</p>

                    <div id="guest-result"></div>

                    <button type="submit" class="btn btn-green mt-3" id="g-submit">
                        <i data-lucide="check" class="h-4 w-4"></i> Confirmar cita
                    </button>
                </form>

                <script>
                    window.PORTAL_DOCTOR_ID    = <?= $docId ?>;
                    window.AGENDAR_HCAPTCHA    = <?= $hcaptchaSiteKey ? 'true' : 'false' ?>;
                    window.AGENDAR_SLOTS_URL   = <?= json_encode(base_url('api/agendar-slots.php')) ?>;
                    window.AGENDAR_SUBMIT_URL  = <?= json_encode(base_url('api/guest-appointment.php')) ?>;
                </script>
            <?php endif; ?>

        </div>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>if (window.lucide) lucide.createIcons();</script>
    <?php if ($step === 3): ?>
        <script src="<?= e(base_url('assets/js/agendar.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <?php endif; ?>
</body>
</html>
