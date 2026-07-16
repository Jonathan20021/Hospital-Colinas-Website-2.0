<?php
/**
 * Pacientes del HIS (SIGMA/SGC): los que este médico atendió en el hospital
 * — emergencia, hospitalización, recetas, evoluciones e interconsultas —
 * aunque nunca hayan pasado por su agenda de citas.
 *
 * El alcance lo decide el backend con doctors.sgc_medicoid (probado y UNIQUE en la BD).
 * Al abrir uno se incorpora al portal (patients) y a partir de ahí funciona igual
 * que cualquier otro paciente: historial, expediente, laboratorio e imágenes.
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

// ── Abrir un paciente del HIS: lo incorpora (o reutiliza) y lleva a su ficha ──
$abrir = (int)($_GET['abrir'] ?? 0);
if ($abrir > 0) {
    $r = portal_api_call('POST', '/portal-doctor/me/sgc-patients/' . $abrir . '/open', [], doctor_token());
    $pid = (int)($r['data']['patient_id'] ?? 0);
    if ($pid > 0) {
        header('Location: ' . base_url('portal-medico/paciente.php?id=' . $pid));
        exit;
    }
    $errorAbrir = $r['message'] ?? 'No se pudo abrir el paciente.';
}

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$res = portal_api_call('GET', '/portal-doctor/me/sgc-patients', [
    'q'        => $q,
    'page'     => $page,
    'per_page' => 25,
], doctor_token());

$items      = $res['data']['items'] ?? [];
$pag        = $res['data']['pagination'] ?? ['page'=>1,'pages'=>1,'total'=>0];
$mapped     = (bool)($res['data']['mapped'] ?? false);
$sgcOffline = (bool)($res['data']['sgc_offline'] ?? false);

doctor_layout_begin('Pacientes SIGMA', 'pacientes');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Pacientes SIGMA</p>
        <h1>Pacientes del hospital</h1>
        <p class="doctor-subtitle">Los que atendiste en emergencia, hospitalización o interconsulta, aunque no hayan pasado por tu agenda.</p>
    </div>
    <?php if ($mapped && !$sgcOffline): ?>
    <form method="GET" class="doctor-search-form">
        <i data-lucide="search" class="h-4 w-4"></i>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, cédula, teléfono..." class="doctor-input doctor-input-search">
        <?php if ($q !== ''): ?>
            <a href="<?= e(base_url('portal-medico/pacientes-sigma.php')) ?>" class="doctor-search-clear" aria-label="Limpiar"><i data-lucide="x" class="h-4 w-4"></i></a>
        <?php endif; ?>
        <button type="submit" class="doctor-btn doctor-btn-primary doctor-search-submit">Buscar</button>
    </form>
    <?php endif; ?>
</header>

<?php doctor_patient_tabs('sigma'); ?>

<?php if (!empty($errorAbrir)): ?>
    <div class="doctor-alert doctor-alert-error" data-reveal>
        <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= e($errorAbrir) ?>
    </div>
<?php endif; ?>

<section class="doctor-card" data-reveal data-reveal-d="1">
    <?php if (!$mapped): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration"><i data-lucide="unlink" class="h-7 w-7"></i></div>
            <p class="doctor-empty-title">Tu usuario aún no está vinculado con SIGMA</p>
            <p>Por seguridad, solo mostramos pacientes del hospital cuando podemos confirmar con certeza tu identidad en SIGMA.
               Escribe a <a href="<?= e(base_url('portal-medico/soporte.php')) ?>" class="doctor-text-link">Soporte TI</a> y lo revisamos.</p>
        </div>
    <?php elseif ($sgcOffline): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration"><i data-lucide="server-off" class="h-7 w-7"></i></div>
            <p class="doctor-empty-title">SIGMA no está disponible</p>
            <p>No pudimos conectar con el sistema del hospital. Vuelve a intentarlo en unos minutos.</p>
        </div>
    <?php else: ?>
        <header class="doctor-card-header">
            <h2><i data-lucide="hospital" class="h-5 w-5"></i> <?= (int)$pag['total'] ?> pacientes en SIGMA</h2>
            <?php if ($q): ?><span class="doctor-text-link">resultados de "<?= e($q) ?>"</span><?php endif; ?>
        </header>

        <?php if (!$items): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration"><i data-lucide="<?= $q ? 'search-x' : 'hospital' ?>" class="h-7 w-7"></i></div>
                <p class="doctor-empty-title"><?= $q ? 'Sin resultados' : 'No tienes pacientes en SIGMA' ?></p>
                <p><?= $q ? 'Prueba con otro nombre, cédula o teléfono.' : 'Aquí aparecerán los pacientes que atiendas en el hospital.' ?></p>
            </div>
        <?php else: ?>
            <div class="doctor-table-wrap">
                <table class="doctor-table">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Cédula</th>
                            <th>Teléfono</th>
                            <th>Última atención</th>
                            <th class="text-right">Atenciones</th>
                            <th class="text-center">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $p): ?>
                            <?php
                                $yaEsta = !empty($p['patient_id']);
                                // Si ya está en el portal vamos directo; si no, se incorpora al abrirlo.
                                $href = $yaEsta
                                    ? base_url('portal-medico/paciente.php?id=' . (int)$p['patient_id'])
                                    : base_url('portal-medico/pacientes-sigma.php?abrir=' . (int)$p['sgc_pacienteid']);
                            ?>
                            <tr>
                                <td>
                                    <div class="doctor-table-patient">
                                        <?= doctor_avatar_html($p['name']) ?>
                                        <div>
                                            <a href="<?= e($href) ?>" class="doctor-link-strong doctor-table-patient-name"><?= e($p['name']) ?></a>
                                            <?php if (!empty($p['dob'])):
                                                // Edad, no fecha de nacimiento: aquí una fecha suelta se confunde con una visita.
                                                $edad = (new DateTime($p['dob']))->diff(new DateTime())->y;
                                            ?>
                                                <div class="doctor-cell-muted"><?= (int)$edad ?> años</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="doctor-nowrap"><?= e($p['cedula'] ?: '—') ?></td>
                                <td class="doctor-nowrap"><?= e($p['phone'] ?: '—') ?></td>
                                <td class="doctor-nowrap">
                                    <?php if (!empty($p['last_visit_at'])): ?>
                                        <?= e(doctor_fecha_corta(strtotime($p['last_visit_at']))) ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-right"><span class="doctor-visit-chip"><?= (int)$p['encounters'] ?></span></td>
                                <td class="text-center">
                                    <?php if ($yaEsta): ?>
                                        <span class="doctor-src-badge is-portal" title="Ya está entre tus pacientes">En el portal</span>
                                    <?php else: ?>
                                        <span class="doctor-src-badge is-his" title="Solo existe en SIGMA — se incorporará al abrirlo">Solo SIGMA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= e($href) ?>" class="doctor-table-action" title="<?= $yaEsta ? 'Ver historial' : 'Abrir expediente' ?>">
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
    <?php endif; ?>
</section>
<?php doctor_layout_end();
