<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$res = portal_api_call('GET', '/portal-doctor/me/patients', [
    'q'        => $q,
    'page'     => $page,
    'per_page' => 25,
], doctor_token());

$items = $res['data']['items'] ?? [];
$pag   = $res['data']['pagination'] ?? ['page'=>1,'pages'=>1,'total'=>0];

doctor_layout_begin('Mis pacientes', 'pacientes');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Mis pacientes</p>
        <h1>Pacientes atendidos</h1>
        <p class="doctor-subtitle">Tu lista completa, con la fecha de su ultima consulta.</p>
    </div>
    <form method="GET" class="doctor-search-form">
        <i data-lucide="search" class="h-4 w-4"></i>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, cedula, telefono..." class="doctor-input doctor-input-search">
        <?php if ($q !== ''): ?>
            <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-search-clear" aria-label="Limpiar"><i data-lucide="x" class="h-4 w-4"></i></a>
        <?php endif; ?>
        <button type="submit" class="doctor-btn doctor-btn-primary doctor-search-submit">Buscar</button>
    </form>
</header>

<section class="doctor-card">
    <header class="doctor-card-header">
        <h2><i data-lucide="users" class="h-5 w-5"></i> <?= (int)$pag['total'] ?> pacientes</h2>
        <?php if ($q): ?><span class="doctor-text-link">resultados de "<?= e($q) ?>"</span><?php endif; ?>
    </header>

    <?php if (!$items): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration">
                <i data-lucide="<?= $q ? 'search-x' : 'users' ?>" class="h-7 w-7"></i>
            </div>
            <p class="doctor-empty-title"><?= $q ? 'Sin resultados' : 'Aun no tienes pacientes' ?></p>
            <p><?= $q ? 'Prueba con otro nombre, cedula o telefono.' : 'Tus pacientes apareceran aqui cuando agendes tu primera cita.' ?></p>
        </div>
    <?php else: ?>
        <div class="doctor-table-wrap">
            <table class="doctor-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Cedula</th>
                        <th>Telefono</th>
                        <th>Seguro</th>
                        <th>Ultima visita</th>
                        <th class="text-right">Visitas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $p): ?>
                        <tr>
                            <td>
                                <div class="doctor-table-patient">
                                    <?= doctor_avatar_html($p['name']) ?>
                                    <div>
                                        <a href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$p['id'])) ?>" class="doctor-link-strong doctor-table-patient-name">
                                            <?= e($p['name']) ?>
                                        </a>
                                        <?php if (!empty($p['email'])): ?>
                                            <div class="doctor-cell-muted"><?= e($p['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($p['cedula'] ?? '—') ?></td>
                            <td><?= e($p['phone'] ?? '—') ?></td>
                            <td><?= e($p['insurance_provider'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($p['last_visit_at'])): ?>
                                    <?= e(date('d M Y', strtotime($p['last_visit_at']))) ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-right"><span class="doctor-visit-chip"><?= (int)$p['visits_total'] ?></span></td>
                            <td>
                                <a href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$p['id'])) ?>" class="doctor-table-action" title="Ver historial">
                                    <i data-lucide="chevron-right" class="h-5 w-5"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pag['pages'] ?? 1) > 1): ?>
            <nav class="doctor-pagination" aria-label="Paginacion">
                <?php
                    $prev = max(1, $page - 1); $next = min($pag['pages'], $page + 1);
                    $qbase = http_build_query(['q' => $q]);
                ?>
                <a href="?<?= e($qbase) ?>&page=<?= $prev ?>" class="doctor-page-btn <?= $page<=1?'is-disabled':'' ?>" aria-disabled="<?= $page<=1?'true':'false' ?>"><i data-lucide="chevron-left" class="h-4 w-4"></i> Anterior</a>
                <span>Pagina <?= $page ?> de <?= (int)$pag['pages'] ?></span>
                <a href="?<?= e($qbase) ?>&page=<?= $next ?>" class="doctor-page-btn <?= $page>=$pag['pages']?'is-disabled':'' ?>" aria-disabled="<?= $page>=$pag['pages']?'true':'false' ?>">Siguiente <i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php doctor_layout_end();
