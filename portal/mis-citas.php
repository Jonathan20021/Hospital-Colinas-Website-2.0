<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$status = (string)($_GET['status'] ?? '');
$query = [];
if ($status !== '') $query['status'] = $status;

$res = portal_api_call('GET', '/portal/me/appointments', $query, portal_token());
$list = is_array($res['data'] ?? null) ? $res['data'] : [];

portal_layout_begin('Mis citas', 'mis-citas');
?>
<header class="portal-header">
    <div>
        <p class="section-label">Historial</p>
        <h1>Mis citas</h1>
    </div>
    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="btn btn-green"><i data-lucide="calendar-plus" class="h-4 w-4"></i> Nueva cita</a>
</header>

<div class="portal-filters">
    <a href="?" class="portal-chip <?= $status === '' ? 'is-active' : '' ?>">Todas</a>
    <a href="?status=scheduled" class="portal-chip <?= $status === 'scheduled' ? 'is-active' : '' ?>">Programadas</a>
    <a href="?status=completed" class="portal-chip <?= $status === 'completed' ? 'is-active' : '' ?>">Atendidas</a>
    <a href="?status=cancelled" class="portal-chip <?= $status === 'cancelled' ? 'is-active' : '' ?>">Canceladas</a>
</div>

<section class="portal-card">
    <?php if (!$list): ?>
        <div class="portal-empty">
            <i data-lucide="calendar-x" class="h-10 w-10"></i>
            <p>No hay citas para mostrar.</p>
        </div>
    <?php else: ?>
        <table class="portal-table">
            <thead><tr><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($a['appointment_time']))) ?></td>
                    <td><?= e($a['doctor_name'] ?? '') ?></td>
                    <td><?= e($a['specialty'] ?? '') ?></td>
                    <td><span class="portal-status portal-status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'scheduled'): ?>
                            <button type="button" class="portal-text-link js-cancel-appt" data-appt-id="<?= (int)$a['id'] ?>">Cancelar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php portal_layout_end();
