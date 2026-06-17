<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$status = (string)($_GET['status'] ?? '');
$query = [];
if ($status !== '') $query['status'] = $status;

$res = portal_api_call('GET', '/portal/me/appointments', $query, portal_token());
$list = is_array($res['data'] ?? null) ? $res['data'] : [];

$estadoEs = ['scheduled' => 'Programada', 'completed' => 'Atendida', 'cancelled' => 'Cancelada', 'pending' => 'Pendiente'];
$mesesES  = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];

portal_layout_begin('Mis citas', 'mis-citas');
?>
<div class="portal-page-title portal-page-title-row">
    <div><h1>Mis citas</h1><p>Tus próximas citas y las que ya tuviste.</p></div>
    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="pa-btn pa-btn-green"><i data-lucide="calendar-plus"></i> Nueva cita</a>
</div>

<div class="portal-filters">
    <a href="?" class="portal-chip <?= $status === '' ? 'is-active' : '' ?>">Todas</a>
    <a href="?status=scheduled" class="portal-chip <?= $status === 'scheduled' ? 'is-active' : '' ?>">Programadas</a>
    <a href="?status=completed" class="portal-chip <?= $status === 'completed' ? 'is-active' : '' ?>">Atendidas</a>
    <a href="?status=cancelled" class="portal-chip <?= $status === 'cancelled' ? 'is-active' : '' ?>">Canceladas</a>
</div>

<?php if (!$list): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="calendar-x"></i></div>
        <h2>No hay citas para mostrar</h2>
        <p>Cuando agendes una cita aparecerá aquí. Puedes reservar con el especialista que necesites.</p>
        <a href="<?= e(base_url('portal/agendar.php')) ?>" class="pa-btn pa-btn-green portal-empty-action"><i data-lucide="calendar-plus"></i> Agendar una cita</a>
    </div>
<?php else: ?>
    <div class="pa-list">
        <?php foreach ($list as $a): $ts = strtotime($a['appointment_time']); $st = $a['status']; ?>
            <div class="pa-item">
                <span class="pa-item-ic portal-date-icon">
                    <strong><?= (int)date('j', $ts) ?></strong>
                    <span><?= e($mesesES[(int)date('n', $ts)]) ?></span>
                </span>
                <div class="pa-item-main">
                    <div class="t"><?= e($a['doctor_name'] ?? 'Médico') ?></div>
                    <div class="s"><i data-lucide="clock" class="portal-inline-icon"></i> <?= e(date('H:i', $ts)) ?><?php if (!empty($a['specialty'])): ?> · <?= e($a['specialty']) ?><?php endif; ?></div>
                    <div class="pa-chips"><span class="portal-status portal-status-<?= e($st) ?>"><?= e($estadoEs[$st] ?? $st) ?></span></div>
                </div>
                <?php if ($st === 'scheduled'): ?>
                    <div class="pa-item-actions">
                        <button type="button" class="pa-btn pa-btn-soft pa-btn-sm js-cancel-appt" data-appt-id="<?= (int)$a['id'] ?>"><i data-lucide="x"></i> Cancelar</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php portal_layout_end();
