<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$dashRes = portal_api_call('GET', '/portal-doctor/me/dashboard', [], doctor_token());

$stats    = $dashRes['data']['stats']    ?? ['today_count'=>0,'pending_count'=>0,'completed_count'=>0,'week_count'=>0];
$upcoming = $dashRes['data']['upcoming'] ?? [];
$events   = $dashRes['data']['events']   ?? [];

$doctor = doctor_current() ?? [];

// Greeting basado en hora
$h = (int)date('H');
$greeting = $h < 12 ? 'Buenos dias' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

// Citas de hoy (filtrar del upcoming)
$today = date('Y-m-d');
$todays = array_values(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) === $today));
$nextOnes = array_slice(array_filter($upcoming, fn($a) => substr($a['appointment_time'], 0, 10) !== $today), 0, 4);

doctor_layout_begin('Inicio', 'dashboard');
?>

<section class="doctor-hero">
    <div class="doctor-hero-text">
        <p class="doctor-hero-eyebrow"><?= e($greeting) ?> · <?= e(date('l j \d\e F', time())) ?></p>
        <h1>Dr/a. <?= e($doctor['name'] ?? '') ?></h1>
        <p class="doctor-hero-subtitle">
            <?php if (count($todays) > 0): ?>
                Tienes <strong><?= count($todays) ?></strong> cita<?= count($todays) === 1 ? '' : 's' ?> programada<?= count($todays) === 1 ? '' : 's' ?> hoy.
            <?php else: ?>
                No tienes citas para hoy. Es un buen momento para revisar pacientes o actualizar tu disponibilidad.
            <?php endif; ?>
        </p>
    </div>
    <div class="doctor-hero-actions">
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-btn doctor-btn-outline">
            <i data-lucide="calendar-days" class="h-4 w-4"></i> Ver agenda
        </a>
        <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-btn doctor-btn-primary">
            <i data-lucide="user-search" class="h-4 w-4"></i> Buscar paciente
        </a>
    </div>
</section>

<section class="doctor-kpis">
    <article class="doctor-kpi doctor-kpi-blue">
        <div class="doctor-kpi-top">
            <span class="doctor-kpi-icon"><i data-lucide="calendar-clock" class="h-5 w-5"></i></span>
            <span class="doctor-kpi-trend doctor-kpi-trend-flat"><i data-lucide="minus" class="h-3 w-3"></i> Hoy</span>
        </div>
        <p class="doctor-kpi-label">Citas de hoy</p>
        <p class="doctor-kpi-value"><?= (int)$stats['today_count'] ?></p>
        <p class="doctor-kpi-foot">Programadas para esta jornada</p>
    </article>
    <article class="doctor-kpi doctor-kpi-amber">
        <div class="doctor-kpi-top">
            <span class="doctor-kpi-icon"><i data-lucide="clock" class="h-5 w-5"></i></span>
            <?php if ((int)$stats['pending_count'] > 0): ?>
                <span class="doctor-kpi-trend doctor-kpi-trend-up"><i data-lucide="arrow-up" class="h-3 w-3"></i> Activo</span>
            <?php else: ?>
                <span class="doctor-kpi-trend doctor-kpi-trend-flat">—</span>
            <?php endif; ?>
        </div>
        <p class="doctor-kpi-label">Pendientes</p>
        <p class="doctor-kpi-value"><?= (int)$stats['pending_count'] ?></p>
        <p class="doctor-kpi-foot">Por completar</p>
    </article>
    <article class="doctor-kpi doctor-kpi-green">
        <div class="doctor-kpi-top">
            <span class="doctor-kpi-icon"><i data-lucide="check-circle-2" class="h-5 w-5"></i></span>
            <span class="doctor-kpi-trend doctor-kpi-trend-up"><i data-lucide="trending-up" class="h-3 w-3"></i> Acumulado</span>
        </div>
        <p class="doctor-kpi-label">Completadas</p>
        <p class="doctor-kpi-value"><?= number_format((int)$stats['completed_count']) ?></p>
        <p class="doctor-kpi-foot">Total historico</p>
    </article>
    <article class="doctor-kpi doctor-kpi-violet">
        <div class="doctor-kpi-top">
            <span class="doctor-kpi-icon"><i data-lucide="calendar-range" class="h-5 w-5"></i></span>
            <span class="doctor-kpi-trend doctor-kpi-trend-flat">7 dias</span>
        </div>
        <p class="doctor-kpi-label">Esta semana</p>
        <p class="doctor-kpi-value"><?= (int)$stats['week_count'] ?></p>
        <p class="doctor-kpi-foot">Citas programadas</p>
    </article>
</section>

<section class="doctor-grid-2 mt-6">
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="sun" class="h-4 w-4"></i> Hoy · <?= e(date('d \d\e F', time())) ?></h2>
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Ver agenda <i data-lucide="arrow-right" class="h-3 w-3"></i></a>
        </header>

        <?php if (!$todays): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration">
                    <i data-lucide="coffee" class="h-7 w-7"></i>
                </div>
                <p class="doctor-empty-title">Dia tranquilo</p>
                <p>No tienes citas hoy. Tomate un cafe o adelanta papeleria.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-timeline-today">
                <?php
                $now = time();
                foreach ($todays as $a):
                    $ts = strtotime($a['appointment_time']);
                    $isPast = $ts < $now;
                ?>
                    <li class="doctor-timeline-today-item <?= $isPast ? 'is-past' : '' ?>">
                        <div class="doctor-timeline-time">
                            <strong><?= e(date('H:i', $ts)) ?></strong>
                            <span><?= $isPast ? 'completada' : 'proxima' ?></span>
                        </div>
                        <div class="doctor-timeline-marker"></div>
                        <a class="doctor-timeline-card" href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>">
                            <p class="doctor-timeline-patient"><?= e($a['patient_name']) ?></p>
                            <p class="doctor-timeline-meta">
                                <?php if (!empty($a['patient_cedula'])): ?>
                                    <i data-lucide="id-card" class="h-3.5 w-3.5"></i> <?= e($a['patient_cedula']) ?>
                                <?php endif; ?>
                                <?php if (!empty($a['patient_phone'])): ?>
                                    <?= !empty($a['patient_cedula']) ? '·' : '' ?>
                                    <i data-lucide="phone" class="h-3.5 w-3.5"></i> <?= e($a['patient_phone']) ?>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="list-checks" class="h-4 w-4"></i> Proximas citas</h2>
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Ver todas <i data-lucide="arrow-right" class="h-3 w-3"></i></a>
        </header>

        <?php if (!$nextOnes): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration">
                    <i data-lucide="calendar-check" class="h-7 w-7"></i>
                </div>
                <p class="doctor-empty-title">Sin citas proximas</p>
                <p>Cuando agendes nuevas citas apareceran aqui.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-appt-list">
                <?php foreach ($nextOnes as $a):
                    $ts = strtotime($a['appointment_time']);
                ?>
                    <li class="doctor-appt-row">
                        <div class="doctor-appt-date">
                            <strong><?= e(date('d', $ts)) ?></strong>
                            <span><?= e(strtoupper(date('M', $ts))) ?></span>
                        </div>
                        <div class="doctor-appt-info">
                            <p class="doctor-appt-name"><?= e($a['patient_name']) ?></p>
                            <p class="doctor-appt-meta">
                                <i data-lucide="clock" class="h-3.5 w-3.5"></i> <?= e(date('H:i', $ts)) ?>
                                <?php if (!empty($a['patient_phone'])): ?>
                                    · <i data-lucide="phone" class="h-3.5 w-3.5"></i> <?= e($a['patient_phone']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$a['id'])) ?>" class="doctor-appt-action" title="Abrir consulta">
                            <i data-lucide="chevron-right" class="h-5 w-5"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="doctor-card mt-6">
    <header class="doctor-card-header">
        <h2><i data-lucide="calendar" class="h-4 w-4"></i> Vista mensual</h2>
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Vista completa <i data-lucide="arrow-right" class="h-3 w-3"></i></a>
    </header>
    <div id="doctor-calendar" data-events='<?= e(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
    <div class="doctor-calendar-legend">
        <span><i class="doctor-dot" style="background:#2563eb"></i> Agendada</span>
        <span><i class="doctor-dot" style="background:#16a34a"></i> Completada</span>
        <span><i class="doctor-dot" style="background:#dc2626"></i> Cancelada</span>
    </div>
</section>

<section class="doctor-grid-3 mt-6">
    <a class="doctor-quick" href="<?= e(base_url('portal-medico/consulta.php')) ?>">
        <i data-lucide="file-edit" class="h-6 w-6"></i>
        <div>
            <h3>Nueva consulta</h3>
            <p>Registrar nota medica y receta</p>
        </div>
    </a>
    <a class="doctor-quick" href="<?= e(base_url('portal-medico/disponibilidad.php')) ?>">
        <i data-lucide="calendar-off" class="h-6 w-6"></i>
        <div>
            <h3>Marcar ausencia</h3>
            <p>Bloquear fechas no disponibles</p>
        </div>
    </a>
    <a class="doctor-quick" href="<?= e(base_url('portal-medico/analytics.php')) ?>">
        <i data-lucide="trending-up" class="h-6 w-6"></i>
        <div>
            <h3>Mis indicadores</h3>
            <p>KPIs y desempeno</p>
        </div>
    </a>
</section>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<?php doctor_layout_end();
