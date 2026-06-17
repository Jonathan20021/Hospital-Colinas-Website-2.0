<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$meRes   = portal_api_call('GET', '/portal/me', [], portal_token());
$apptRes = portal_api_call('GET', '/portal/me/appointments', ['date_from' => date('Y-m-d')], portal_token());
if ($meRes['ok']) portal_set_verified(!empty($meRes['data']['email_verified_at']));

$patient  = $meRes['data'] ?? [];
$upcoming = is_array($apptRes['data'] ?? null) ? $apptRes['data'] : [];
$pName = (string)($patient['name'] ?? (portal_patient()['name'] ?? ''));
$friendly = trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$first = trim(explode(' ', $friendly)[0] ?? '');
$h = (int)date('H');
$saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

$cards = [
    ['agendar.php',     'calendar-plus',  'green',  'Agendar una cita',          'Reserva con el especialista que necesites.'],
    ['mis-citas.php',   'calendar-check', 'blue',   'Mis citas',                 'Tus próximas citas y las anteriores.'],
    ['consultas.php',   'stethoscope',    'violet', 'Mis consultas',             'Lo que te indicó el médico en cada visita.'],
    ['recetas.php',     'file-text',      'green',  'Mis recetas',               'Verlas o descargarlas en PDF.'],
    ['laboratorio.php', 'flask-conical',  'teal',   'Resultados de laboratorio', 'Tus análisis de sangre, orina y más.'],
    ['estudios.php',    'scan',           'amber',  'Mis imágenes',              'Radiografías, sonografías y otros estudios.'],
    ['perfil.php',      'user-cog',       'blue',   'Mi perfil',                 'Tus datos personales y de contacto.'],
];

portal_layout_begin('Inicio', 'dashboard');
?>
<section class="pa-hello">
    <h1><?= e($saludo) ?><?= $first !== '' ? ', ' . e($first) : '' ?></h1>
    <p>Bienvenido a tu portal. Aquí tienes todo tu historial médico en un solo lugar.</p>
</section>

<div class="pa-grid">
    <?php foreach ($cards as [$href, $icon, $tone, $title, $desc]): ?>
        <a class="pa-card" href="<?= e(base_url('portal/' . $href)) ?>">
            <span class="pa-card-ic ic-<?= $tone ?>"><i data-lucide="<?= $icon ?>"></i></span>
            <h2><?= e($title) ?></h2>
            <p><?= e($desc) ?></p>
            <span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($upcoming): ?>
<h2 class="pa-head" style="margin-top:30px"><span style="font-family:'Outfit';font-size:1.4rem;font-weight:800;color:var(--pa-ink);display:flex;align-items:center;gap:10px"><i data-lucide="calendar-clock"></i> Tu próxima cita</span></h2>
<div class="pa-list">
    <?php foreach (array_slice($upcoming, 0, 2) as $a): $ts = strtotime($a['appointment_time']); ?>
        <div class="pa-item">
            <span class="pa-item-ic"><i data-lucide="calendar-check"></i></span>
            <div class="pa-item-main">
                <div class="t"><?= e(date('d', $ts)) ?> de <?= e(['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int)date('n',$ts)]) ?>, <?= e(date('H:i', $ts)) ?></div>
                <div class="s"><strong><?= e($a['doctor_name'] ?? 'Médico') ?></strong><?= !empty($a['specialty']) ? ' · ' . e($a['specialty']) : '' ?></div>
            </div>
            <div class="pa-item-actions">
                <a class="pa-btn pa-btn-soft pa-btn-sm" href="<?= e(base_url('portal/mis-citas.php')) ?>">Ver mis citas</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php portal_layout_end();
