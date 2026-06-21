<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$dashRes = portal_api_call('GET', '/portal-doctor/me/dashboard', [], doctor_token());

$stats    = $dashRes['data']['stats']    ?? ['today_count'=>0,'pending_count'=>0,'completed_count'=>0,'week_count'=>0];
$upcoming = $dashRes['data']['upcoming'] ?? [];
$events   = $dashRes['data']['events']   ?? [];

$doctor = doctor_current() ?? [];
$friendly = trim(mb_convert_case(mb_strtolower((string)($doctor['name'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));

// Saludo según la hora
$h = (int)date('H');
$greeting = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

// Fecha larga en español
$diasES  = ['Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado','Sunday'=>'domingo'];
$mesesES = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$fechaLarga = $diasES[date('l')] . ' ' . (int)date('j') . ' de ' . $mesesES[(int)date('n')] . ' de ' . date('Y');

// Citas de hoy / próximas
$today = date('Y-m-d');
$todays   = array_values(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) === $today));
usort($todays, fn($a, $b) => strcmp($a['appointment_time'], $b['appointment_time']));
$nextOnes = array_slice(array_values(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) !== $today)), 0, 6);

// Próxima cita (la más cercana desde ahora) para destacarla en el encabezado
$nowTs  = time();
$future = array_values(array_filter($upcoming, fn($a) => strtotime($a['appointment_time']) >= $nowTs));
usort($future, fn($a, $b) => strcmp($a['appointment_time'], $b['appointment_time']));
$nextAppt = $future[0] ?? null;

// Días del mes con citas (mini-calendario, render inicial sin JS)
$eventDays = [];
foreach ($events as $ev) {
    $d = substr((string)($ev['start'] ?? $ev['date'] ?? ''), 0, 10);
    if ($d !== '') $eventDays[$d] = true;
}

// Conteo por estado (fallback del doughnut sin JS; el JS lo refina con la serie de 30 d)
$byStatus = ['completed'=>0,'scheduled'=>0,'cancelled'=>0];
foreach ($events as $ev) { $s = $ev['status'] ?? 'scheduled'; if (isset($byStatus[$s])) $byStatus[$s]++; }
$totalAppts = array_sum($byStatus);

// Eje del horario del día (07:00–19:00)
$schedStart = 7; $schedEnd = 19; $schedSpan = $schedEnd - $schedStart;
$schedHours = [8, 10, 12, 14, 16, 18];
$schedPos = function (int $ts) use ($schedStart, $schedSpan): float {
    $hour = (int)date('G', $ts) + ((int)date('i', $ts)) / 60;
    return max(0.0, min(100.0, ($hour - $schedStart) / $schedSpan * 100));
};

// Sparkline de respaldo (sin JS/API): forma plana; el JS la sustituye por datos reales.
$flatSpark = ['line' => 'M0,18 L100,18', 'fill' => 'M0,18 L100,18 L100,24 L0,24 Z'];
$kpis = [
    ['k'=>'today',     'tone'=>'navy',   'lbl'=>'Citas de hoy',  'val'=>(int)$stats['today_count'],     'sub'=>'Programadas para hoy',  'series'=>'total'],
    ['k'=>'pending',   'tone'=>'amber',  'lbl'=>'Pendientes',    'val'=>(int)$stats['pending_count'],   'sub'=>'Por atender',          'series'=>'scheduled'],
    ['k'=>'completed', 'tone'=>'green',  'lbl'=>'Completadas',   'val'=>(int)$stats['completed_count'], 'sub'=>'Histórico acumulado',  'series'=>'completed'],
    ['k'=>'week',      'tone'=>'violet', 'lbl'=>'Esta semana',   'val'=>(int)$stats['week_count'],      'sub'=>'Últimos 7 días',       'series'=>'total'],
];

doctor_layout_begin('Inicio', 'dashboard');
?>
<!-- ENCABEZADO -->
<div class="dm-headrow" data-reveal>
    <div>
        <p class="dm-hello"><?= e($greeting) ?>, <span><?= e($friendly ?: 'Doctor/a') ?></span></p>
        <p class="dm-hello-sub"><i data-lucide="calendar"></i> <?= e(ucfirst($fechaLarga)) ?></p>
    </div>
    <?php if ($nextAppt): $nts = strtotime($nextAppt['appointment_time']); $esHoy = substr($nextAppt['appointment_time'], 0, 10) === $today; ?>
    <a class="dm-next" href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$nextAppt['id'])) ?>">
        <span class="dm-next-ic"><i data-lucide="<?= $esHoy ? 'clock' : 'calendar-clock' ?>"></i></span>
        <span class="dm-next-info">
            <span class="dm-next-lbl">Próxima cita</span>
            <span class="dm-next-name"><?= e($nextAppt['patient_name']) ?></span>
            <span class="dm-next-when"><?= e($esHoy ? 'Hoy' : doctor_fecha_corta($nts)) ?> · <?= e(date('H:i', $nts)) ?></span>
        </span>
        <span class="dm-next-go"><i data-lucide="arrow-right"></i></span>
    </a>
    <?php else: ?>
    <div class="dm-headcta">
        <a href="<?= e(base_url('portal-medico/consulta.php')) ?>" class="doctor-btn doctor-btn-primary"><i data-lucide="file-edit"></i> Nueva consulta</a>
    </div>
    <?php endif; ?>
</div>

<!-- KPIs -->
<div class="dm-kpirow">
    <?php foreach ($kpis as $i => $kpi): ?>
    <article class="dm-k tone-<?= e($kpi['tone']) ?>" data-k="<?= e($kpi['k']) ?>" data-reveal data-reveal-d="<?= min(4, $i + 1) ?>">
        <div class="dm-k-top">
            <span class="dm-k-lbl"><?= e($kpi['lbl']) ?></span>
            <svg class="dm-k-spark dm-kpi-spark" data-series="<?= e($kpi['series']) ?>" viewBox="0 0 100 24" preserveAspectRatio="none">
                <path class="fill" fill="currentColor" d="<?= e($flatSpark['fill']) ?>"/>
                <path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="<?= e($flatSpark['line']) ?>"/>
            </svg>
        </div>
        <div class="dm-k-val"><?= number_format($kpi['val']) ?></div>
        <div class="dm-k-foot">
            <span class="dm-k-sub"><?= e($kpi['sub']) ?></span>
            <span class="dm-k-delta" data-k-delta hidden></span>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<!-- OVERVIEW + DOUGHNUT -->
<div class="dm-grid">
    <section class="dm-panel" data-reveal>
        <div class="dm-panel-h">
            <div>
                <h2 class="ttl">Actividad de consultas</h2>
                <p class="sub">Citas atendidas, agendadas y canceladas por día</p>
            </div>
            <div class="dm-seg" id="dm-activity-range">
                <button type="button" class="dm-seg-btn" data-days="7">7 d</button>
                <button type="button" class="dm-seg-btn on" data-days="14">14 d</button>
                <button type="button" class="dm-seg-btn" data-days="30">30 d</button>
            </div>
        </div>
        <div class="dm-legend">
            <span><i class="c2"></i> Completadas</span>
            <span><i class="c1"></i> Agendadas</span>
            <span><i style="background:#be123c"></i> Canceladas</span>
        </div>
        <div class="dm-chartbox"><canvas id="dm-activity-chart"></canvas></div>
    </section>

    <section class="dm-panel" data-reveal data-reveal-d="1">
        <div class="dm-panel-h">
            <div>
                <h2 class="ttl">Estado de citas</h2>
                <p class="sub">Distribución del período</p>
            </div>
        </div>
        <div class="dm-donut-wrap">
            <canvas id="dm-diag-chart"
                data-completed="<?= (int)$byStatus['completed'] ?>"
                data-scheduled="<?= (int)$byStatus['scheduled'] ?>"
                data-cancelled="<?= (int)$byStatus['cancelled'] ?>"></canvas>
            <div class="dm-donut-center">
                <span class="k">Total citas</span>
                <span class="v" id="dm-donut-total"><?= number_format($totalAppts) ?></span>
            </div>
        </div>
        <div class="dm-donut-legend">
            <span><i style="background:var(--hg-green)"></i> Completadas <b id="dm-dl-completed"><?= (int)$byStatus['completed'] ?></b></span>
            <span><i style="background:var(--hg-navy-600)"></i> Agendadas <b id="dm-dl-scheduled"><?= (int)$byStatus['scheduled'] ?></b></span>
            <span><i style="background:#be123c"></i> Canceladas <b id="dm-dl-cancelled"><?= (int)$byStatus['cancelled'] ?></b></span>
        </div>
    </section>
</div>

<!-- AGENDA DEL DÍA (timeline) + ÚLTIMAS VISITAS -->
<div class="dm-grid">
    <section class="dm-panel" data-reveal>
        <div class="dm-sched-h">
            <h2 class="ttl" style="font-family:'Outfit',sans-serif;font-size:1.06rem;font-weight:700;color:var(--hg-ink);margin:0">Agenda de hoy</h2>
            <span class="dm-sched-date"><i data-lucide="calendar-check"></i> <?= e((int)date('j') . ' de ' . $mesesES[(int)date('n')]) ?></span>
        </div>
        <?php if (!$todays): ?>
            <div class="dm-empty" style="padding:30px 18px">
                <div class="dm-empty-ic"><i data-lucide="coffee"></i></div>
                <p class="t">Día tranquilo</p>
                <p>No tienes citas hoy. Aprovecha para revisar pacientes o adelantar papeleo.</p>
            </div>
        <?php else: ?>
        <div class="dm-sched">
            <div class="dm-sched-track">
                <div class="dm-sched-axis"></div>
                <?php foreach ($schedHours as $hh): $p = ($hh - $schedStart) / $schedSpan * 100; ?>
                    <span class="dm-sched-hour" style="left:<?= round($p, 2) ?>%"><?= sprintf('%02d:00', $hh) ?></span>
                <?php endforeach; ?>
                <?php foreach ($todays as $a): $ts = strtotime($a['appointment_time']);
                    $pos = $schedPos($ts);
                    $done = $ts < time();
                    $tone = $done ? 'is-done' : (((int)date('G', $ts)) % 2 ? 'is-green' : ''); ?>
                    <a class="dm-sched-ev <?= $tone ?>" style="left:<?= round($pos, 2) ?>%"
                       href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>">
                        <span class="t"><?= e(date('H:i', $ts)) ?></span>
                        <span class="n"><?= e($a['patient_name']) ?></span>
                        <span class="m"><?= e($done ? 'Atendida' : 'Programada') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="dm-panel dm-card dm-live" data-reveal data-reveal-d="1">
        <header class="dm-card-h">
            <h2><i data-lucide="history"></i> Próximas visitas</h2>
            <div class="dm-card-h-actions">
                <button type="button" class="dm-refresh" data-dm-refresh title="Actualizar" aria-label="Actualizar"><i data-lucide="refresh-cw"></i></button>
                <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="dm-clink">Ver agenda <i data-lucide="arrow-right"></i></a>
            </div>
        </header>
        <div class="dm-visits" id="dm-upcoming">
            <?php if (!$nextOnes): ?>
                <div class="dm-empty">
                    <div class="dm-empty-ic"><i data-lucide="calendar-check"></i></div>
                    <p class="t">Sin citas próximas</p>
                    <p>Cuando agendes nuevas citas aparecerán aquí.</p>
                </div>
            <?php else: foreach ($nextOnes as $a): $ts = strtotime($a['appointment_time']); ?>
                <a class="dm-vrow" href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>">
                    <?= doctor_avatar_html($a['patient_name'], 'md') ?>
                    <div class="info">
                        <div class="n"><?= e($a['patient_name']) ?></div>
                        <div class="s"><?= e(doctor_fecha_corta($ts)) ?><?php if (!empty($a['patient_phone'])): ?> · <?= e($a['patient_phone']) ?><?php endif; ?></div>
                    </div>
                    <span class="when"><b><?= e(date('H:i', $ts)) ?></b><?= e(doctor_mes_corto_es($ts)) . ' ' . date('d', $ts) ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </section>
</div>

<!-- MINI-CALENDARIO + ACCESOS RÁPIDOS -->
<div class="dm-grid">
    <section class="dm-panel dm-card" id="dm-minical" data-reveal>
        <header class="dm-cal-h">
            <span class="mo" id="dm-minical-label"><?= e($mesesES[(int)date('n')] . ' de ' . date('Y')) ?></span>
            <div class="dm-minical-nav">
                <button type="button" id="dm-minical-prev" aria-label="Mes anterior"><i data-lucide="chevron-left"></i></button>
                <button type="button" id="dm-minical-next" aria-label="Mes siguiente"><i data-lucide="chevron-right"></i></button>
            </div>
        </header>
        <div class="dm-cal" id="dm-minical-grid">
            <table>
                <thead><tr><th>L</th><th>M</th><th>X</th><th>J</th><th>V</th><th>S</th><th>D</th></tr></thead>
                <tbody>
                <?php
                    $cy = (int)date('Y'); $cm = (int)date('n'); $todayDay = (int)date('j');
                    $firstDow = (int)date('N', mktime(0,0,0,$cm,1,$cy));
                    $dim = (int)date('t', mktime(0,0,0,$cm,1,$cy));
                    $cell = 1 - ($firstDow - 1);
                    for ($w = 0; $w < 6; $w++):
                        if ($cell > $dim) break; ?>
                        <tr>
                        <?php for ($d = 0; $d < 7; $d++):
                            if ($cell >= 1 && $cell <= $dim):
                                $iso = sprintf('%04d-%02d-%02d', $cy, $cm, $cell);
                                $cls = ($cell === $todayDay ? 'today ' : '') . (isset($eventDays[$iso]) ? 'has' : ''); ?>
                                <td class="<?= trim($cls) ?>"><span><?= $cell ?></span></td>
                            <?php else: ?>
                                <td class="mut"><span><?= $cell < 1 ? ($cell + (int)date('t', mktime(0,0,0,$cm-1,1,$cy))) : ($cell - $dim) ?></span></td>
                            <?php endif; $cell++; endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div class="dm-minical-detail" id="dm-minical-detail" hidden></div>
    </section>

    <section class="dm-panel" data-reveal data-reveal-d="1" style="padding:18px 18px 20px">
        <h2 class="ttl" style="font-family:'Outfit',sans-serif;font-size:1.06rem;font-weight:700;color:var(--hg-ink);margin:0 0 14px">Accesos rápidos</h2>
        <div class="dm-quick" style="grid-template-columns:1fr">
            <a href="<?= e(base_url('portal-medico/consulta.php')) ?>">
                <span class="qic"><i data-lucide="file-edit"></i></span>
                <div><h3>Nueva consulta</h3><p>Registrar nota médica y receta</p></div>
            </a>
            <a href="<?= e(base_url('portal-medico/disponibilidad.php')) ?>">
                <span class="qic"><i data-lucide="calendar-off"></i></span>
                <div><h3>Marcar ausencia</h3><p>Bloquear fechas no disponibles</p></div>
            </a>
            <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>">
                <span class="qic"><i data-lucide="user-search"></i></span>
                <div><h3>Buscar paciente</h3><p>Historial, recetas e imágenes</p></div>
            </a>
        </div>
    </section>
</div>

<?php
$dashJsVer  = (string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-dashboard.js') ?: time());
$chartJsVer = (string)(@filemtime(__DIR__ . '/../assets/vendor/chartjs/chart.umd.min.js') ?: time());
?>
<script>
window.DM_DASH = {
    today: <?= json_encode($today) ?>,
    events: <?= json_encode($events, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
    urls: {
        consulta: <?= json_encode(base_url('portal-medico/consulta.php'), JSON_UNESCAPED_SLASHES) ?>,
        agenda:   <?= json_encode(base_url('portal-medico/agenda.php'),   JSON_UNESCAPED_SLASHES) ?>,
        paciente: <?= json_encode(base_url('portal-medico/paciente.php'), JSON_UNESCAPED_SLASHES) ?>
    }
};
</script>
<script src="<?= e(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>?v=<?= e($chartJsVer) ?>"></script>
<script>window.Chart||document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>');</script>
<script src="<?= e(base_url('assets/js/portal-medico-dashboard.js')) ?>?v=<?= e($dashJsVer) ?>"></script>
<?php doctor_layout_end();
