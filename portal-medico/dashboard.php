<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$dashRes = portal_api_call('GET', '/portal-doctor/me/dashboard', [], doctor_token());

$stats    = $dashRes['data']['stats']    ?? ['today_count'=>0,'pending_count'=>0,'completed_count'=>0,'week_count'=>0];
$upcoming = $dashRes['data']['upcoming'] ?? [];
$events   = $dashRes['data']['events']   ?? [];

$doctor = doctor_current() ?? [];

// Saludo según la hora
$h = (int)date('H');
$greeting = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

// Fecha larga en español
$diasES  = ['Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado','Sunday'=>'domingo'];
$mesesES = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$fechaLarga = $diasES[date('l')] . ' ' . (int)date('j') . ' de ' . $mesesES[(int)date('n')];

// Citas de hoy / próximas
$today = date('Y-m-d');
$todays   = array_values(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) === $today));
$nextOnes = array_slice(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) !== $today), 0, 5);

// Días del mes con citas (para el mini-calendario)
$eventDays = [];
foreach ($events as $ev) {
    $d = substr((string)($ev['start'] ?? $ev['date'] ?? ''), 0, 10);
    if ($d !== '') $eventDays[$d] = true;
}

// Sparkline determinista (textura visual en los KPIs)
$sparkPath = function (int $seed, int $points = 12): array {
    $vals = []; srand($seed * 7919);
    for ($i = 0; $i < $points; $i++) $vals[] = rand(20, 90);
    srand();
    $W = 100; $H = 24; $step = $W / max(1, ($points - 1)); $d = '';
    foreach ($vals as $i => $v) {
        $x = $i * $step; $y = $H - (($v / 100) * $H);
        $d .= ($i === 0 ? 'M' : 'L') . round($x, 1) . ',' . round($y, 1) . ' ';
    }
    return ['line' => trim($d), 'fill' => trim($d) . 'L' . $W . ',' . $H . ' L0,' . $H . ' Z'];
};
$sp = [
    $sparkPath((int)$stats['today_count'] + 3),
    $sparkPath((int)$stats['pending_count'] + 5),
    $sparkPath((int)$stats['completed_count'] + 11),
    $sparkPath((int)$stats['week_count'] + 7),
];
$kpis = [
    ['Citas de hoy','calendar-clock','indigo','#4f46e5','Hoy',         (int)$stats['today_count'],     $sp[0]],
    ['Pendientes','clock','amber','#b45309','Por atender',             (int)$stats['pending_count'],   $sp[1]],
    ['Completadas','check-circle-2','green','#059669','Acumulado',     (int)$stats['completed_count'], $sp[2]],
    ['Esta semana','calendar-range','violet','#7c3aed','7 días',       (int)$stats['week_count'],      $sp[3]],
];

doctor_layout_begin('Inicio', 'dashboard');
?>
<div class="dm-dash">
    <div class="dm-dash-main">

        <!-- HERO -->
        <section class="dm-hero">
            <span class="dm-hero-ey"><i data-lucide="activity"></i> <?= e($greeting) ?> · <?= e($fechaLarga) ?></span>
            <h1>Dr/a. <?= e($doctor['name'] ?? '') ?></h1>
            <p>
                <?php if (count($todays) > 0): ?>
                    Tienes <strong><?= count($todays) ?></strong> cita<?= count($todays) === 1 ? '' : 's' ?> programada<?= count($todays) === 1 ? '' : 's' ?> para hoy. Revisa tu agenda y prepara tus consultas.
                <?php else: ?>
                    No tienes citas para hoy. Es un buen momento para revisar pacientes o actualizar tu disponibilidad.
                <?php endif; ?>
            </p>
            <div class="dm-hero-actions">
                <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="dm-hero-btn primary"><i data-lucide="calendar-days"></i> Ver agenda</a>
                <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="dm-hero-btn ghost"><i data-lucide="user-search"></i> Buscar paciente</a>
            </div>
        </section>

        <!-- KPIs -->
        <div class="dm-kpis">
            <?php foreach ($kpis as [$lbl,$ic,$tone,$col,$tag,$val,$spk]): ?>
            <article class="dm-kpi <?= $tone ?>">
                <div class="dm-kpi-top">
                    <span class="dm-kpi-ic"><i data-lucide="<?= $ic ?>"></i></span>
                    <span class="dm-kpi-tag"><?= e($tag) ?></span>
                </div>
                <div class="v"><?= number_format($val) ?></div>
                <div class="l"><?= e($lbl) ?></div>
                <svg class="dm-kpi-spark" viewBox="0 0 100 24" preserveAspectRatio="none" style="color:<?= $col ?>">
                    <path class="fill" fill="currentColor" d="<?= e($spk['fill']) ?>"/>
                    <path fill="none" stroke="currentColor" stroke-width="2" d="<?= e($spk['line']) ?>"/>
                </svg>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- PRÓXIMAS CITAS -->
        <section class="dm-card">
            <header class="dm-card-h">
                <h2><i data-lucide="list-checks"></i> Próximas citas</h2>
                <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="dm-clink">Ver agenda <i data-lucide="arrow-right"></i></a>
            </header>
            <div class="dm-list">
                <?php if (!$nextOnes): ?>
                    <div class="dm-empty">
                        <div class="dm-empty-ic"><i data-lucide="calendar-check"></i></div>
                        <p class="t">Sin citas próximas</p>
                        <p>Cuando agendes nuevas citas aparecerán aquí.</p>
                    </div>
                <?php else: foreach ($nextOnes as $a): $ts = strtotime($a['appointment_time']); ?>
                    <a class="dm-row" href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>">
                        <div class="dm-rdate"><strong><?= e(date('d', $ts)) ?></strong><span><?= e(strtoupper(strtr(date('M', $ts), ['Apr'=>'ABR','Aug'=>'AGO','Dec'=>'DIC','Jan'=>'ENE']))) ?></span></div>
                        <div class="dm-rinfo">
                            <div class="n"><?= e($a['patient_name']) ?></div>
                            <div class="m"><i data-lucide="clock"></i> <?= e(date('H:i', $ts)) ?><?php if (!empty($a['patient_phone'])): ?> · <i data-lucide="phone"></i> <?= e($a['patient_phone']) ?><?php endif; ?></div>
                        </div>
                        <span class="dm-rgo"><i data-lucide="chevron-right"></i></span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- ACCESOS RÁPIDOS -->
        <div class="dm-quick">
            <a href="<?= e(base_url('portal-medico/consulta.php')) ?>">
                <span class="qic"><i data-lucide="file-edit"></i></span>
                <div><h3>Nueva consulta</h3><p>Registrar nota médica y receta</p></div>
            </a>
            <a href="<?= e(base_url('portal-medico/disponibilidad.php')) ?>">
                <span class="qic"><i data-lucide="calendar-off"></i></span>
                <div><h3>Marcar ausencia</h3><p>Bloquear fechas no disponibles</p></div>
            </a>
            <a href="<?= e(base_url('portal-medico/analytics.php')) ?>">
                <span class="qic"><i data-lucide="trending-up"></i></span>
                <div><h3>Mis indicadores</h3><p>KPIs y desempeño</p></div>
            </a>
        </div>
    </div>

    <!-- ASIDE -->
    <aside class="dm-dash-aside">
        <!-- mini-calendario -->
        <section class="dm-card">
            <header class="dm-cal-h">
                <span class="mo"><?= e($mesesES[(int)date('n')] . ' de ' . date('Y')) ?></span>
                <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="dm-clink">Agenda</a>
            </header>
            <div class="dm-cal">
                <table>
                    <thead><tr><th>L</th><th>M</th><th>X</th><th>J</th><th>V</th><th>S</th><th>D</th></tr></thead>
                    <tbody>
                    <?php
                        $cy = (int)date('Y'); $cm = (int)date('n'); $todayDay = (int)date('j');
                        $firstDow = (int)date('N', mktime(0,0,0,$cm,1,$cy)); // 1=lun..7=dom
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
        </section>

        <!-- pacientes de hoy -->
        <section class="dm-card">
            <header class="dm-card-h">
                <h2><i data-lucide="users"></i> Pacientes de hoy</h2>
                <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="dm-clink">Ver</a>
            </header>
            <div style="padding:8px 0 10px">
                <?php if (!$todays): ?>
                    <div class="dm-empty">
                        <div class="dm-empty-ic"><i data-lucide="coffee"></i></div>
                        <p class="t">Día tranquilo</p>
                        <p>No tienes citas hoy. Tómate un café o adelanta papeleo.</p>
                    </div>
                <?php else: foreach ($todays as $a): $ts = strtotime($a['appointment_time']); ?>
                    <a class="dm-visit" href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>">
                        <?= doctor_avatar_html($a['patient_name'], 'sm') ?>
                        <div style="min-width:0;flex:1">
                            <div class="n"><?= e($a['patient_name']) ?></div>
                            <div class="m"><?= e(date('H:i', $ts)) ?> · <?= e($ts < time() ? 'completada' : 'próxima') ?></div>
                        </div>
                        <span class="dm-rgo"><i data-lucide="chevron-right"></i></span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- acción -->
        <section class="dm-promo">
            <div class="ic"><i data-lucide="calendar-clock"></i></div>
            <h3>Actualiza tu disponibilidad</h3>
            <p>Define tus horarios y bloqueos de la semana para que tu agenda quede al día.</p>
            <a href="<?= e(base_url('portal-medico/disponibilidad.php')) ?>"><i data-lucide="arrow-right"></i> Configurar horarios</a>
        </section>
    </aside>
</div>
<?php doctor_layout_end();
