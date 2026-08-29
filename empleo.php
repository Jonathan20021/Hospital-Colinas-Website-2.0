<?php
/**
 * /empleos/{id} — Detalle de una vacante + formulario de postulación (con CV).
 * Usa la línea gráfica del sitio (tokens/patrones de app.css). La postulación se
 * envía a api/empleos-postular.php, que relaya por el puente de JENOFONTE.
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

$vid = trim((string) ($_GET['id'] ?? ''));
$res = portal_api_call('GET', '/portal/empleos');
$vacancies = ($res['ok'] && isset($res['data']['vacancies']) && is_array($res['data']['vacancies']))
    ? $res['data']['vacancies'] : [];

$vac = null;
foreach ($vacancies as $v) {
    if ((string) ($v['id'] ?? '') === $vid && $vid !== '') { $vac = $v; break; }
}

$EMPLOYMENT_LABEL = [
    'FULL_TIME' => 'Tiempo completo', 'PART_TIME' => 'Medio tiempo',
    'TEMPORARY' => 'Temporal', 'CONTRACT' => 'Por contrato', 'INTERN' => 'Pasantía',
];

$vTitle = $vac ? (string) ($vac['title'] ?? 'Vacante') : 'Vacante no disponible';
$title = $vTitle . ' | Empleos — Hospital General Las Colinas';
$description = $vac
    ? mb_substr(trim((string) ($vac['description'] ?? ('Postúlate a la vacante ' . $vTitle . ' en el Hospital General Las Colinas.'))), 0, 180, 'UTF-8')
    : 'Esta vacante ya no está disponible.';

/** Texto plano (con saltos de línea / viñetas) → HTML seguro con listas. */
function emp_render_block(?string $text): string {
    $text = trim((string) $text);
    if ($text === '') return '';
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $html = '';
    $inList = false;
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '') { if ($inList) { $html .= '</ul>'; $inList = false; } continue; }
        if (preg_match('/^[-*•·]\s*(.+)$/u', $ln, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . e($m[1]) . '</li>';
        } else {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . e($ln) . '</p>';
        }
    }
    if ($inList) $html .= '</ul>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= $vac ? 'index, follow' : 'noindex, follow' ?>">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="<?= e($vTitle) ?> | Empleos HGLC">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">

    <?php /* Fuentes auto-hospedadas (Inter + Outfit + Plus Jakarta Sans, VARIABLES):
             mismo origen, sin DNS/TLS a Google ni CSS render-blocking. */ ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/empleos.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased empleos-page">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php render_public_header($assets, $contact, $active); ?>

<main id="contenido">
    <section class="dir-section" style="padding-top: clamp(2rem, 4vw, 3rem)">
        <div class="dir-section-shell">
        <?php if (!$vac): ?>
            <div class="emp-empty">
                <span class="emp-empty-icon"><i data-lucide="briefcase"></i></span>
                <h2>Esta vacante ya no está disponible</h2>
                <p>Es posible que se haya cerrado o llenado. Consulta nuestras vacantes abiertas actuales.</p>
                <a class="btn btn-green" href="<?= e(base_url('empleos')) ?>"><i data-lucide="arrow-left" class="h-4 w-4"></i> Ver vacantes abiertas</a>
            </div>
        <?php else:
            $etype = (string) ($vac['employmentType'] ?? '');
            $etypeLabel = $EMPLOYMENT_LABEL[$etype] ?? $etype;
            $dept = trim((string) ($vac['department'] ?? ''));
            $loc = trim((string) ($vac['location'] ?? ''));
            $seats = (int) ($vac['seats'] ?? 0);
            $skills = (isset($vac['skills']) && is_array($vac['skills'])) ? $vac['skills'] : [];
        ?>
            <a class="emp-back" href="<?= e(base_url('empleos')) ?>"><i data-lucide="arrow-left"></i> Volver a vacantes</a>

            <div class="emp-detail-grid">
                <article class="emp-detail-main">
                    <header class="emp-detail-head">
                        <div class="emp-detail-badges">
                            <?php if ($dept !== ''): ?><span class="emp-vac-tag"><i data-lucide="building-2"></i><?= e($dept) ?></span><?php endif; ?>
                            <?php if ($etypeLabel !== ''): ?><span class="emp-vac-type"><?= e($etypeLabel) ?></span><?php endif; ?>
                        </div>
                        <h1><?= e($vTitle) ?></h1>
                        <ul class="emp-detail-meta">
                            <?php if ($loc !== ''): ?><li><i data-lucide="map-pin"></i><?= e($loc) ?></li><?php endif; ?>
                            <?php if ($seats > 0): ?><li><i data-lucide="users"></i><?= e((string) $seats) ?> plaza<?= $seats === 1 ? '' : 's' ?></li><?php endif; ?>
                            <li><i data-lucide="building-2"></i>Hospital General Las Colinas</li>
                        </ul>
                    </header>

                    <?php $descHtml = emp_render_block($vac['description'] ?? ''); if ($descHtml !== ''): ?>
                        <section class="emp-detail-block"><h2>Descripción del puesto</h2><div class="emp-prose"><?= $descHtml ?></div></section>
                    <?php endif; ?>

                    <?php $reqHtml = emp_render_block($vac['requirements'] ?? ''); if ($reqHtml !== ''): ?>
                        <section class="emp-detail-block"><h2>Requisitos</h2><div class="emp-prose"><?= $reqHtml ?></div></section>
                    <?php endif; ?>

                    <?php if (!empty($skills)): ?>
                        <section class="emp-detail-block">
                            <h2>Habilidades deseadas</h2>
                            <ul class="emp-skills">
                                <?php foreach ($skills as $sk): if (!is_string($sk)) continue; ?><li><?= e($sk) ?></li><?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <a href="#postular" class="btn btn-green btn-lg"><i data-lucide="send" class="h-4 w-4"></i> Postularme a esta vacante</a>
                </article>

                <aside class="emp-apply" id="postular">
                    <div class="emp-apply-card">
                        <h2><i data-lucide="user-round-plus"></i> Postúlate</h2>
                        <p class="emp-apply-sub">Completa tus datos y adjunta tu CV en PDF. Te contactaremos si avanzas en el proceso.</p>

                        <form id="empApplyForm" novalidate>
                            <input type="hidden" name="vacancyId" value="<?= e($vid) ?>">
                            <div class="emp-hp" aria-hidden="true"><label>No llenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                            <div class="emp-field-row">
                                <div class="emp-field"><label for="ap_first">Nombre <span>*</span></label><input id="ap_first" name="firstName" type="text" required maxlength="60" autocomplete="given-name"></div>
                                <div class="emp-field"><label for="ap_last">Apellido <span>*</span></label><input id="ap_last" name="lastName" type="text" required maxlength="60" autocomplete="family-name"></div>
                            </div>
                            <div class="emp-field"><label for="ap_email">Correo electrónico <span>*</span></label><input id="ap_email" name="email" type="email" required maxlength="120" autocomplete="email" inputmode="email"></div>
                            <div class="emp-field"><label for="ap_phone">Teléfono / WhatsApp <span>*</span></label><input id="ap_phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" inputmode="tel" placeholder="(809) 000-0000"></div>
                            <div class="emp-field"><label for="ap_headline">Puesto o profesión actual</label><input id="ap_headline" name="headline" type="text" maxlength="160" placeholder="Ej. Licenciada en Enfermería"></div>
                            <div class="emp-field"><label for="ap_location">Ciudad / ubicación</label><input id="ap_location" name="location" type="text" maxlength="80" placeholder="Ej. Santiago"></div>

                            <div class="emp-field">
                                <label>Currículum (PDF) <span>*</span></label>
                                <label class="emp-drop" id="empDrop" for="ap_cv">
                                    <input id="ap_cv" name="cv" type="file" accept="application/pdf,.pdf" required hidden>
                                    <span class="emp-drop-icon"><i data-lucide="file-up"></i></span>
                                    <span class="emp-drop-text" id="empDropText">Arrastra tu CV aquí o <b>haz clic para elegir</b></span>
                                    <span class="emp-drop-hint">Solo PDF · máx. 8 MB</span>
                                </label>
                            </div>

                            <label class="emp-consent">
                                <input type="checkbox" name="consent" value="1" required>
                                <span>Autorizo al Hospital General Las Colinas a tratar mis datos personales para este proceso de selección, conforme a la Ley 172-13 de Protección de Datos.</span>
                            </label>

                            <button type="submit" class="btn btn-green emp-submit" id="empSubmit">
                                <span class="emp-submit-label"><i data-lucide="send" class="h-4 w-4"></i> Enviar postulación</span>
                                <span class="emp-submit-loading" hidden><i data-lucide="loader-2" class="h-4 w-4 emp-spin"></i> Enviando…</span>
                            </button>

                            <p class="emp-msg" id="empFormMsg" role="alert" hidden></p>
                        </form>

                        <div class="emp-success" id="empSuccess" hidden>
                            <span class="emp-success-icon"><i data-lucide="badge-check"></i></span>
                            <h3>¡Postulación recibida!</h3>
                            <p>Gracias por tu interés. Revisaremos tu perfil y te escribiremos por correo si avanzas en el proceso.</p>
                            <a class="btn btn-outline" href="<?= e(base_url('empleos')) ?>">Ver otras vacantes</a>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
        </div>
    </section>
</main>

<?php render_public_footer($assets, $contact, $year); ?>
<?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

<script src="<?= e(base_url('assets/js/lucide.min.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide.min.js') ?: 1)) ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<script>
(function () {
    var form = document.getElementById('empApplyForm');
    if (!form) return;
    var fileInput = document.getElementById('ap_cv');
    var drop = document.getElementById('empDrop');
    var dropText = document.getElementById('empDropText');
    var submit = document.getElementById('empSubmit');
    var msg = document.getElementById('empFormMsg');
    var successBox = document.getElementById('empSuccess');
    var MAX = 8 * 1024 * 1024;
    var endpoint = <?= json_encode(base_url('api/empleos-postular.php'), JSON_UNESCAPED_SLASHES) ?>;

    function showMsg(text, kind) { msg.textContent = text; msg.className = 'emp-msg ' + (kind || 'error'); msg.hidden = false; }
    function pickFile(file) {
        if (!file) { dropText.innerHTML = 'Arrastra tu CV aquí o <b>haz clic para elegir</b>'; drop.classList.remove('has-file'); return; }
        if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) { showMsg('El currículum debe ser un archivo PDF.', 'error'); fileInput.value = ''; return; }
        if (file.size > MAX) { showMsg('El CV pesa ' + (file.size / 1048576).toFixed(1) + ' MB; el tope son 8 MB.', 'error'); fileInput.value = ''; return; }
        msg.hidden = true; dropText.textContent = file.name; drop.classList.add('has-file');
    }
    fileInput.addEventListener('change', function () { pickFile(fileInput.files[0]); });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) { fileInput.files = e.dataTransfer.files; pickFile(fileInput.files[0]); } });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        msg.hidden = true;
        var required = ['firstName', 'lastName', 'email', 'phone'];
        for (var i = 0; i < required.length; i++) {
            if (!form.elements[required[i]].value.trim()) { showMsg('Por favor completa todos los campos obligatorios.', 'error'); form.elements[required[i]].focus(); return; }
        }
        if (!form.elements['consent'].checked) { showMsg('Debes autorizar el tratamiento de tus datos para postularte.', 'error'); return; }
        if (!fileInput.files || !fileInput.files[0]) { showMsg('Adjunta tu currículum en PDF.', 'error'); return; }

        var data = new FormData(form);
        submit.disabled = true;
        submit.querySelector('.emp-submit-label').hidden = true;
        submit.querySelector('.emp-submit-loading').hidden = false;
        if (window.lucide) lucide.createIcons();

        fetch(endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta inválida del servidor.' }; }); })
            .then(function (j) {
                if (j && j.ok) {
                    form.hidden = true; successBox.hidden = false;
                    if (window.lucide) lucide.createIcons();
                    successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else { showMsg((j && j.error) ? j.error : 'No pudimos enviar tu postulación. Inténtalo más tarde.', 'error'); }
            })
            .catch(function () { showMsg('No pudimos conectar. Revisa tu conexión e inténtalo de nuevo.', 'error'); })
            .finally(function () {
                submit.disabled = false;
                submit.querySelector('.emp-submit-label').hidden = false;
                submit.querySelector('.emp-submit-loading').hidden = true;
                if (window.lucide) lucide.createIcons();
            });
    });
})();
</script>
</body>
</html>
