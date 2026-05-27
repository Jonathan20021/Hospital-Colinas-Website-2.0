<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$dashRes = portal_api_call('GET', '/portal-doctor/me/dashboard', [], doctor_token());

$stats    = $dashRes['data']['stats']    ?? ['today_count'=>0,'pending_count'=>0,'completed_count'=>0,'week_count'=>0];
$upcoming = $dashRes['data']['upcoming'] ?? [];
$events   = $dashRes['data']['events']   ?? [];

$doctor = doctor_current() ?? [];

doctor_layout_begin('Inicio', 'dashboard');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Bienvenido/a, Dr/a.</p>
        <h1><?= e($doctor['name'] ?? '') ?></h1>
        <?php if (!empty($doctor['specialty'])): ?>
            <p class="doctor-subtitle"><i data-lucide="stethoscope" class="h-4 w-4 inline-block align-text-bottom"></i> <?= e($doctor['specialty']) ?></p>
        <?php endif; ?>
    </div>
    <div class="doctor-header-actions">
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-btn doctor-btn-outline">
            <i data-lucide="calendar-days" class="h-4 w-4"></i> Ver agenda
        </a>
        <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-btn doctor-btn-primary">
            <i data-lucide="user-search" class="h-4 w-4"></i> Buscar paciente
        </a>
    </div>
</header>

<section class="doctor-kpis">
    <article class="doctor-kpi doctor-kpi-blue">
        <span class="doctor-kpi-icon"><i data-lucide="calendar-clock" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Citas hoy</p>
            <p class="doctor-kpi-value"><?= (int)$stats['today_count'] ?></p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-amber">
        <span class="doctor-kpi-icon"><i data-lucide="clock" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Pendientes</p>
            <p class="doctor-kpi-value"><?= (int)$stats['pending_count'] ?></p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-green">
        <span class="doctor-kpi-icon"><i data-lucide="check-circle-2" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Completadas</p>
            <p class="doctor-kpi-value"><?= (int)$stats['completed_count'] ?></p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-violet">
        <span class="doctor-kpi-icon"><i data-lucide="calendar-range" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Esta semana</p>
            <p class="doctor-kpi-value"><?= (int)$stats['week_count'] ?></p>
        </div>
    </article>
</section>

<section class="doctor-grid-2 mt-6">
    <div class="doctor-card doctor-card-calendar">
        <header class="doctor-card-header">
            <h2><i data-lucide="calendar" class="h-5 w-5"></i> Calendario</h2>
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Vista completa →</a>
        </header>
        <div id="doctor-calendar" data-events='<?= e(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
        <div class="doctor-calendar-legend">
            <span><i class="doctor-dot" style="background:#2563eb"></i> Agendada</span>
            <span><i class="doctor-dot" style="background:#16a34a"></i> Completada</span>
            <span><i class="doctor-dot" style="background:#dc2626"></i> Cancelada</span>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="list-checks" class="h-5 w-5"></i> Proximas citas</h2>
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Ver todas →</a>
        </header>

        <?php if (!$upcoming): ?>
            <div class="doctor-empty">
                <i data-lucide="calendar-x" class="h-10 w-10"></i>
                <p>No tienes citas proximas.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-appt-list">
                <?php foreach ($upcoming as $a):
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
