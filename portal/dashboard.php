<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$apptRes = portal_api_call('GET', '/portal/me/appointments', ['date_from' => date('Y-m-d')], $token);
if ($meRes['ok']) portal_set_verified(!empty($meRes['data']['email_verified_at']));

$patient  = $meRes['data'] ?? [];
$upcoming = is_array($apptRes['data'] ?? null) ? $apptRes['data'] : [];
$next     = $upcoming[0] ?? null;

$consultRes = portal_api_call('GET', '/portal/me/consultations', [], $token);
$rxRes      = portal_api_call('GET', '/portal/me/prescriptions', [], $token);
$labRes     = portal_api_call('GET', '/portal/me/lab', [], $token);

$consultas = is_array($consultRes['data']['consultations'] ?? null) ? $consultRes['data']['consultations'] : [];
$recetas   = is_array($rxRes['data']['prescriptions'] ?? null) ? $rxRes['data']['prescriptions'] : [];
$labs      = is_array($labRes['data']['orders'] ?? null) ? $labRes['data']['orders'] : [];
$nConsultas = $consultRes['ok'] ? (int)($consultRes['data']['count'] ?? count($consultas)) : null;
$nRecetas   = $rxRes['ok'] ? (int)($rxRes['data']['count'] ?? count($recetas)) : null;
$nLab       = $labRes['ok'] ? (int)($labRes['data']['count'] ?? count($labs)) : null;

$pName   = (string)($patient['name'] ?? (portal_patient()['name'] ?? ''));
$friendly= trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$first   = trim(explode(' ', $friendly)[0] ?? '');
$parts   = preg_split('/\s+/', trim($pName)) ?: [];
$initials= '';
foreach ($parts as $p) { if ($p !== '' && mb_strlen($initials) < 2) $initials .= mb_substr($p, 0, 1, 'UTF-8'); }
$initials= $initials !== '' ? mb_strtoupper($initials, 'UTF-8') : '?';

$h = (int)date('H');
$saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

// edad
$edad = '';
if (!empty($patient['dob'])) { $t = strtotime((string)$patient['dob']); if ($t) $edad = (int)((time() - $t) / 31557600) . ' años'; }
$sexo = ['Male' => 'M', 'Female' => 'F'][$patient['gender'] ?? ''] ?? '';
$mesesES = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$diasES = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];

$activity = [];
foreach (array_slice($consultas, 0, 2) as $c) {
    $activity[] = [
        'ts' => strtotime((string)($c['date'] ?? '')) ?: 0,
        'icon' => 'stethoscope',
        'title' => 'Consulta registrada',
        'detail' => trim((string)($c['doctor'] ?? '') . (!empty($c['specialty']) ? ' · ' . $c['specialty'] : '')),
        'url' => base_url('portal/consultas.php'),
    ];
}
foreach (array_slice($recetas, 0, 2) as $r) {
    $activity[] = [
        'ts' => strtotime((string)($r['date'] ?? '')) ?: 0,
        'icon' => 'file-text',
        'title' => 'Receta disponible',
        'detail' => (string)($r['doctor'] ?? 'Emitida por tu médico'),
        'url' => base_url('portal/recetas.php'),
    ];
}
foreach (array_slice($labs, 0, 2) as $o) {
    if ((int)($o['num_resultados'] ?? 0) < 1) continue;
    $activity[] = [
        'ts' => strtotime((string)($o['fecha'] ?? '')) ?: 0,
        'icon' => 'flask-conical',
        'title' => 'Resultado de laboratorio disponible',
        'detail' => implode(', ', array_slice((array)($o['examenes'] ?? []), 0, 2)) ?: 'Orden de laboratorio',
        'url' => base_url('portal/resultado-lab.php?order=' . (int)$o['id']),
    ];
}
usort($activity, fn(array $a, array $b): int => $b['ts'] <=> $a['ts']);
$activity = array_slice($activity, 0, 4);

portal_layout_begin('Inicio', 'dashboard');
?>
<header class="portal-page-title portal-page-title-row">
    <div>
        <h1><?= e($saludo) ?><?= $first !== '' ? ', ' . e($first) : '' ?></h1>
        <p>Aquí tienes un resumen claro de tu información y actividad reciente.</p>
    </div>
    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-green"><i data-lucide="calendar-plus"></i> Agendar una cita</a>
</header>

<div class="portal-dashboard-layout">
    <div class="portal-dashboard-main">
        <section class="portal-surface portal-next-appointment">
            <div class="portal-next-head">
                <h2><i data-lucide="calendar-clock"></i> Tu próxima cita</h2>
                <a href="<?= e(base_url('portal/mis-citas.php')) ?>" class="portal-text-link">Ver todas</a>
            </div>
            <?php if ($next): $ts = strtotime($next['appointment_time']); ?>
                <div class="portal-next-body">
                    <div class="portal-date-block">
                        <span class="day-name"><?= e($diasES[(int)date('N', $ts)]) ?></span>
                        <strong><?= (int)date('j', $ts) ?></strong>
                        <span class="month"><?= e(ucfirst($mesesES[(int)date('n', $ts)])) ?> <?= e(date('Y', $ts)) ?></span>
                    </div>
                    <div class="portal-appt-details">
                        <div class="portal-appt-detail"><i data-lucide="clock-3"></i><div><strong><?= e(date('h:i a', $ts)) ?></strong><br><span class="portal-status portal-status-<?= e($next['status'] ?? 'scheduled') ?>"><?= e(($next['status'] ?? '') === 'scheduled' ? 'Confirmada' : ($next['status'] ?? 'Programada')) ?></span></div></div>
                        <div class="portal-appt-detail"><i data-lucide="user-round"></i><div><strong><?= e($next['doctor_name'] ?? 'Médico') ?></strong><br><?= e($next['specialty'] ?? 'Consulta médica') ?></div></div>
                        <?php if (!empty($next['office_name'])): ?><div class="portal-appt-detail"><i data-lucide="map-pin"></i><div><?= e($next['office_name']) ?></div></div><?php endif; ?>
                    </div>
                    <div class="portal-prep">
                        <h3>Prepárate para tu cita</h3>
                        <ul class="portal-checklist">
                            <li><i data-lucide="circle-check"></i> Llega 15 minutos antes.</li>
                            <li><i data-lucide="circle-check"></i> Lleva tu cédula y carnet del seguro.</li>
                            <li><i data-lucide="circle-check"></i> Anota tus dudas o síntomas importantes.</li>
                        </ul>
                    </div>
                </div>
                <div class="portal-next-actions">
                    <a href="<?= e(base_url('portal/mis-citas.php?status=scheduled')) ?>" class="btn btn-green"><i data-lucide="calendar-check"></i> Ver detalle de la cita</a>
                    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-outline"><i data-lucide="calendar-plus"></i> Agendar otra</a>
                </div>
            <?php else: ?>
                <div class="pa-empty">
                    <div class="ic"><i data-lucide="calendar-plus"></i></div>
                    <h2>No tienes citas próximas</h2>
                    <p>Elige una especialidad, un médico y el horario que te convenga.</p>
                    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-green"><i data-lucide="calendar-plus"></i> Agendar una cita</a>
                </div>
            <?php endif; ?>
        </section>

        <div class="portal-metrics">
            <a class="portal-metric" href="<?= e(base_url('portal/consultas.php')) ?>">
                <span class="portal-metric-icon"><i data-lucide="stethoscope"></i></span>
                <span><strong><?= $nConsultas !== null ? $nConsultas : '—' ?></strong>Consultas</span>
                <i class="portal-metric-arrow" data-lucide="arrow-right"></i>
            </a>
            <a class="portal-metric" href="<?= e(base_url('portal/recetas.php')) ?>">
                <span class="portal-metric-icon"><i data-lucide="file-text"></i></span>
                <span><strong><?= $nRecetas !== null ? $nRecetas : '—' ?></strong>Recetas</span>
                <i class="portal-metric-arrow" data-lucide="arrow-right"></i>
            </a>
            <a class="portal-metric" href="<?= e(base_url('portal/laboratorio.php')) ?>">
                <span class="portal-metric-icon"><i data-lucide="flask-conical"></i></span>
                <span><strong><?= $nLab !== null ? $nLab : '—' ?></strong>Órdenes de laboratorio</span>
                <i class="portal-metric-arrow" data-lucide="arrow-right"></i>
            </a>
        </div>

        <section class="portal-surface portal-activity">
            <h2><i data-lucide="activity"></i> Actividad reciente</h2>
            <?php if ($activity): ?>
                <div class="portal-activity-list">
                    <?php foreach ($activity as $item): ?>
                        <a class="portal-activity-row" href="<?= e($item['url']) ?>">
                            <span class="portal-activity-icon"><i data-lucide="<?= e($item['icon']) ?>"></i></span>
                            <span class="portal-activity-copy"><strong><?= e($item['title']) ?></strong><span><?= e($item['detail']) ?></span></span>
                            <time datetime="<?= e(date('Y-m-d', $item['ts'])) ?>"><?= e(date('d/m/Y', $item['ts'])) ?></time>
                            <i data-lucide="chevron-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="portal-subtitle">Tu actividad clínica reciente aparecerá aquí.</p>
            <?php endif; ?>
        </section>
    </div>

    <aside class="portal-dashboard-side">
        <section class="portal-surface portal-health-summary">
            <h2><i data-lucide="user-round"></i> Resumen de tu cuenta</h2>
            <dl class="portal-health-list">
                <div class="portal-health-row"><dt>Nombre</dt><dd><strong><?= e($friendly ?: 'Paciente') ?></strong></dd></div>
                <?php if ($edad): ?><div class="portal-health-row"><dt>Edad</dt><dd><strong><?= e($edad) ?></strong></dd></div><?php endif; ?>
                <div class="portal-health-row"><dt>Seguro médico</dt><dd><strong><?= e(($patient['insurance_provider'] ?? '') ?: 'No registrado') ?></strong></dd></div>
                <div class="portal-health-row"><dt>Correo</dt><dd><strong><?= !empty($patient['email_verified_at']) ? 'Verificado' : 'Pendiente' ?></strong></dd></div>
            </dl>
            <a href="<?= e(base_url('portal/perfil.php')) ?>" class="btn btn-outline w-full"><i data-lucide="user-cog"></i> Ver perfil completo</a>
        </section>

        <section class="portal-surface portal-quick-links">
            <h2><i data-lucide="layout-list"></i> Accesos rápidos</h2>
            <nav class="portal-link-list" aria-label="Accesos rápidos">
                <a class="portal-link-row" href="<?= e(base_url('portal/agendar.php')) ?>"><span class="portal-link-icon"><i data-lucide="calendar-plus"></i></span><span>Agendar una cita</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/mis-citas.php')) ?>"><span class="portal-link-icon"><i data-lucide="calendar-days"></i></span><span>Mis citas</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/consultas.php')) ?>"><span class="portal-link-icon"><i data-lucide="stethoscope"></i></span><span>Mis consultas</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/recetas.php')) ?>"><span class="portal-link-icon"><i data-lucide="file-text"></i></span><span>Mis recetas</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/laboratorio.php')) ?>"><span class="portal-link-icon"><i data-lucide="flask-conical"></i></span><span>Resultados</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/estudios.php')) ?>"><span class="portal-link-icon"><i data-lucide="scan-line"></i></span><span>Mis imágenes</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/solicitar-estudios.php')) ?>"><span class="portal-link-icon"><i data-lucide="clipboard-plus"></i></span><span>Solicitar estudios</span><i data-lucide="chevron-right"></i></a>
                <a class="portal-link-row" href="<?= e(base_url('portal/mis-solicitudes.php')) ?>"><span class="portal-link-icon"><i data-lucide="clipboard-list"></i></span><span>Mis solicitudes</span><i data-lucide="chevron-right"></i></a>
            </nav>
        </section>
    </aside>
</div>
<?php portal_layout_end();
