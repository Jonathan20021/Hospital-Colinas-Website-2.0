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
        <p class="doctor-subtitle">Tu lista completa, con la fecha de su última consulta.</p>
    </div>
    <form method="GET" class="doctor-search-form">
        <i data-lucide="search" class="h-4 w-4"></i>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, cédula, teléfono..." class="doctor-input doctor-input-search">
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
            <p class="doctor-empty-title"><?= $q ? 'Sin resultados' : 'Aún no tienes pacientes' ?></p>
            <p><?= $q ? 'Prueba con otro nombre, cédula o teléfono.' : 'Tus pacientes aparecerán aquí cuando agendes tu primera cita.' ?></p>
        </div>
    <?php else: ?>
        <div class="doctor-table-wrap">
            <table class="doctor-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Cédula</th>
                        <th>Teléfono</th>
                        <th>Seguro</th>
                        <th>Última visita</th>
                        <th class="text-right">Visitas</th>
                        <th class="text-center">Imagen</th>
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
                                    <?= e(doctor_fecha_corta(strtotime($p['last_visit_at']))) ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-right"><span class="doctor-visit-chip"><?= (int)$p['visits_total'] ?></span></td>
                            <td class="text-center" data-img-cell="<?= (int)$p['id'] ?>"><span class="doctor-img-load" title="Consultando PACS…">·</span></td>
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
            <nav class="doctor-pagination" aria-label="Paginación">
                <?php
                    $prev = max(1, $page - 1); $next = min($pag['pages'], $page + 1);
                    $qbase = http_build_query(['q' => $q]);
                ?>
                <a href="?<?= e($qbase) ?>&page=<?= $prev ?>" class="doctor-page-btn <?= $page<=1?'is-disabled':'' ?>" aria-disabled="<?= $page<=1?'true':'false' ?>"><i data-lucide="chevron-left" class="h-4 w-4"></i> Anterior</a>
                <span>Página <?= $page ?> de <?= (int)$pag['pages'] ?></span>
                <a href="?<?= e($qbase) ?>&page=<?= $next ?>" class="doctor-page-btn <?= $page>=$pag['pages']?'is-disabled':'' ?>" aria-disabled="<?= $page>=$pag['pages']?'true':'false' ?>">Siguiente <i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<style>
.doctor-img-badge{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:#e7f0ff;color:#1d4ed8;border:1px solid #c9ddff;text-decoration:none;transition:background .12s}
.doctor-img-badge:hover{background:#d6e6ff}
.doctor-img-badge svg{width:17px;height:17px}
.doctor-img-badge.is-posible{background:#fff7ed;color:#b45309;border-color:#fed7aa}
.doctor-img-badge.is-posible:hover{background:#ffedd5}
.doctor-img-none{color:#cbd5e1}
.doctor-img-load{color:#cbd5e1;display:inline-block;animation:imgpulse 1s ease-in-out infinite}
@keyframes imgpulse{0%,100%{opacity:.25}50%{opacity:1}}
</style>
<script>
(function () {
    var cells = Array.prototype.slice.call(document.querySelectorAll('[data-img-cell]'));
    if (!cells.length || !window.doctorApi) return;
    var ids = cells.map(function (c) { return parseInt(c.getAttribute('data-img-cell'), 10); });
    var ptBase = <?= json_encode(base_url('portal-medico/paciente.php'), JSON_UNESCAPED_SLASHES) ?>;
    function clearAll() { cells.forEach(function (c) { c.innerHTML = '<span class="doctor-img-none">—</span>'; }); }
    window.doctorApi('POST', '/portal-doctor/me/patients/imaging-flags', { ids: ids }).then(function (r) {
        var flags = (r && r.ok && r.data && r.data.flags) || null;
        if (!flags) { clearAll(); return; }
        cells.forEach(function (c) {
            var id = c.getAttribute('data-img-cell');
            var f = flags[id] || {};
            if (f.c) {
                c.innerHTML = '<a class="doctor-img-badge" title="Tiene estudios de imagen — ver" href="' + ptBase + '?id=' + id + '#imaging-card"><i data-lucide="scan"></i></a>';
            } else if (f.n) {
                c.innerHTML = '<a class="doctor-img-badge is-posible" title="Posibles estudios por nombre — verifica identidad" href="' + ptBase + '?id=' + id + '#imaging-card"><i data-lucide="scan-search"></i></a>';
            } else {
                c.innerHTML = '<span class="doctor-img-none">—</span>';
            }
        });
        if (window.lucide) lucide.createIcons();
    }).catch(clearAll);
})();
</script>
<?php doctor_layout_end();
