<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$meRes   = portal_api_call('GET', '/portal/me', [], portal_token());
$apptRes = portal_api_call('GET', '/portal/me/appointments', ['date_from' => date('Y-m-d')], portal_token());

if ($meRes['ok']) {
    portal_set_verified(!empty($meRes['data']['email_verified_at']));
}

$patient   = $meRes['data']  ?? [];
$upcoming  = is_array($apptRes['data'] ?? null) ? $apptRes['data'] : [];
$verified  = !empty($patient['email_verified_at']);

portal_layout_begin('Inicio', 'dashboard');
?>
<header class="portal-header">
    <div>
        <p class="section-label">Bienvenido</p>
        <h1><?= e($patient['name'] ?? 'Paciente') ?></h1>
        <p class="portal-subtitle">Aquí puedes ver tus próximas citas y agendar nuevas.</p>
    </div>
    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-green">
        <i data-lucide="calendar-plus" class="h-4 w-4"></i> Agendar nueva cita
    </a>
</header>

<?php if (!$verified): ?>
    <div class="portal-banner portal-banner-warning">
        <i data-lucide="mail-warning" class="h-5 w-5"></i>
        <div>
            <strong>Verifica tu correo</strong>
            <p>Te enviamos un enlace a <strong><?= e($patient['email'] ?? '') ?></strong>. Sin verificación no podrás agendar citas.</p>
            <button type="button" id="btn-resend-verify" class="portal-text-link mt-1" data-email="<?= e($patient['email'] ?? '') ?>">Reenviar enlace</button>
            <span id="resend-status" class="ml-2 text-sm"></span>
        </div>
    </div>
<?php endif; ?>

<section class="portal-card">
    <header class="portal-card-header">
        <h2><i data-lucide="calendar" class="h-5 w-5"></i> Próximas citas</h2>
        <a href="<?= e(base_url('portal/mis-citas.php')) ?>" class="portal-text-link">Ver todas</a>
    </header>

    <?php if (!$upcoming): ?>
        <div class="portal-empty">
            <i data-lucide="calendar-x" class="h-10 w-10"></i>
            <p>No tienes citas próximas.</p>
            <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-green mt-3">Agendar mi primera cita</a>
        </div>
    <?php else: ?>
        <div class="portal-appointments">
            <?php foreach (array_slice($upcoming, 0, 5) as $a): ?>
                <article class="portal-appointment">
                    <div class="portal-appt-date">
                        <strong><?= e(date('d M', strtotime($a['appointment_time']))) ?></strong>
                        <span><?= e(date('Y', strtotime($a['appointment_time']))) ?></span>
                        <em><?= e(date('H:i', strtotime($a['appointment_time']))) ?></em>
                    </div>
                    <div class="portal-appt-body">
                        <h3><?= e($a['doctor_name'] ?? 'Médico') ?></h3>
                        <p><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($a['specialty'] ?? '') ?></p>
                        <?php if (!empty($a['office_name'])): ?>
                            <p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i> <?= e($a['office_name']) ?></p>
                        <?php endif; ?>
                        <span class="portal-status portal-status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span>
                    </div>
                    <?php if ($a['status'] === 'scheduled'): ?>
                        <button type="button" class="portal-text-link js-cancel-appt" data-appt-id="<?= (int)$a['id'] ?>">Cancelar</button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="portal-grid-2 mt-6">
    <div class="portal-card">
        <header class="portal-card-header"><h2><i data-lucide="user-round" class="h-5 w-5"></i> Mis datos</h2></header>
        <dl class="portal-dl">
            <dt>Cédula</dt><dd><?= e($patient['cedula'] ?? '—') ?></dd>
            <dt>Teléfono</dt><dd><?= e($patient['phone'] ?? '—') ?></dd>
            <dt>Email</dt><dd><?= e($patient['email'] ?? '—') ?></dd>
            <dt>Seguro</dt><dd><?= e($patient['insurance_provider'] ?? 'No registrado') ?></dd>
        </dl>
        <a href="<?= e(base_url('portal/perfil.php')) ?>" class="portal-text-link">Editar mis datos →</a>
    </div>

    <div class="portal-card portal-card-cta">
        <h2><i data-lucide="hospital" class="h-5 w-5"></i> Servicios del hospital</h2>
        <p>Conoce nuestros servicios y especialidades.</p>
        <a href="<?= e(base_url('servicios')) ?>" class="btn btn-outline">Ver servicios</a>
    </div>
</section>
<?php portal_layout_end();
