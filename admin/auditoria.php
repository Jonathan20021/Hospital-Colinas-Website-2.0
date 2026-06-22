<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/../includes/phi_audit.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('audit');
phi_audit_ensure_table();

// ── Filtros ──────────────────────────────────────────────────────────────────
$fActor = trim($_GET['actor'] ?? '');          // doctor | patient | ''
$fQ     = trim($_GET['q'] ?? '');              // busca en actor_label / path / ip
$fPid   = trim($_GET['pid'] ?? '');            // id del paciente accedido
$fFrom  = trim($_GET['from'] ?? '');
$fTo    = trim($_GET['to'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 50;

$where = [];
$params = [];
if ($fActor === 'doctor' || $fActor === 'patient') { $where[] = 'actor_type = ?'; $params[] = $fActor; }
if ($fPid !== '' && ctype_digit($fPid))            { $where[] = 'target_patient_id = ?'; $params[] = (int) $fPid; }
if ($fQ !== '') { $where[] = '(actor_label LIKE ? OR path LIKE ? OR ip LIKE ?)'; $like = '%' . $fQ . '%'; array_push($params, $like, $like, $like); }
if ($fFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFrom)) { $where[] = 'created_at >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTo))     { $where[] = 'created_at <= ?'; $params[] = $fTo . ' 23:59:59'; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$rows = [];
try {
    $cs = db()->prepare('SELECT COUNT(*) FROM phi_audit_log' . $wsql);
    $cs->execute($params);
    $total = (int) $cs->fetchColumn();
    $st = db()->prepare('SELECT * FROM phi_audit_log' . $wsql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (Throwable) {}
$pages = max(1, (int) ceil($total / max(1, $per)));

// Resumen rápido (24 h)
$last24 = 0;
try {
    $h = db()->query("SELECT COUNT(*) FROM phi_audit_log WHERE created_at >= (NOW() - INTERVAL 1 DAY)");
    $last24 = (int) $h->fetchColumn();
} catch (Throwable) {}

// Traduce una ruta del API a algo legible.
function audit_readable(string $path): string
{
    $p = $path;
    $p = preg_replace('#^/portal-doctor/me#', '', $p);
    $p = preg_replace('#^/portal/me#', '', $p);
    $map = [
        '#^/patients/(\d+)/imaging.*#'      => 'Imágenes · paciente #$1',
        '#^/patients/(\d+)/prescriptions.*#'=> 'Recetas · paciente #$1',
        '#^/patients/(\d+)/notes.*#'        => 'Notas clínicas · paciente #$1',
        '#^/patients/(\d+)/lab.*#'          => 'Laboratorio · paciente #$1',
        '#^/patients/(\d+).*#'              => 'Expediente · paciente #$1',
        '#^/patients\b.*#'                  => 'Lista de pacientes',
        '#^/appointments.*#'               => 'Citas',
        '#^/imaging.*#'                     => 'Imágenes',
        '#^/prescriptions.*#'              => 'Recetas',
        '#^/certificates.*#'              => 'Certificados',
        '#^/messages.*#'                  => 'Mensajes',
        '#^/profile.*#'                   => 'Mi perfil',
        '#^$#'                            => 'Mis datos',
    ];
    foreach ($map as $re => $label) {
        if (preg_match($re, $p)) return preg_replace($re, $label, $p);
    }
    return $path;
}

function audit_qs(array $over = []): string
{
    $base = ['actor' => $GLOBALS['fActor'], 'q' => $GLOBALS['fQ'], 'pid' => $GLOBALS['fPid'], 'from' => $GLOBALS['fFrom'], 'to' => $GLOBALS['fTo']];
    return http_build_query(array_merge($base, $over));
}

admin_header('Auditoría de accesos', 'auditoria');
?>
<style>
.audit-meta{display:flex;gap:18px;flex-wrap:wrap;margin:0 0 18px;color:#64748b;font-size:.86rem}
.audit-meta b{color:#0f172a}
.audit-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0 0 16px}
.audit-filters label{display:flex;flex-direction:column;font-size:.72rem;color:#64748b;gap:3px}
.audit-filters input,.audit-filters select{padding:8px 10px;border:1px solid #d6dae4;border-radius:8px;font:inherit;font-size:.85rem;background:#fff}
.audit-filters .btn{padding:9px 16px;border:0;border-radius:8px;background:#2a2566;color:#fff;font:inherit;font-size:.85rem;cursor:pointer;text-decoration:none}
.audit-filters .btn.ghost{background:#eef0f6;color:#2a2566}
.audit-table{width:100%;border-collapse:collapse;font-size:.84rem}
.audit-table th,.audit-table td{padding:9px 10px;text-align:left;border-bottom:1px solid #eef0f4;vertical-align:top}
.audit-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#7a8499;background:#f8fafc;position:sticky;top:0}
.audit-table tbody tr:hover{background:#f9fafe}
.audit-when{white-space:nowrap;color:#475569;font-variant-numeric:tabular-nums}
.audit-actor .t{font-weight:600;color:#0f172a}
.audit-actor .s{font-size:.74rem;color:#94a3b8}
.aud-chip{display:inline-block;padding:2px 7px;border-radius:999px;font-size:.68rem;font-weight:700}
.aud-doctor{background:#e7edff;color:#2a3fa0}
.aud-patient{background:#e7f7ee;color:#1d7a45}
.aud-m{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.72rem;color:#64748b}
.aud-status-ok{color:#1d7a45;font-weight:700}
.aud-status-bad{color:#b3424f;font-weight:700}
.audit-pager{display:flex;gap:6px;align-items:center;margin-top:16px;flex-wrap:wrap}
.audit-pager a,.audit-pager span{padding:7px 12px;border-radius:8px;border:1px solid #e2e6ef;color:#2a2566;text-decoration:none;font-size:.85rem}
.audit-pager .on{background:#2a2566;color:#fff;border-color:#2a2566}
.audit-empty{padding:40px 16px;text-align:center;color:#94a3b8}
@media(max-width:720px){.audit-table .hide-sm{display:none}}
</style>
<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Cumplimiento · Ley 172-13</span>
            <h2>Auditoría de accesos a datos de pacientes</h2>
        </div>
    </div>

    <div class="audit-meta">
        <span><b><?= number_format($total) ?></b> registros<?= $wsql ? ' (filtrados)' : '' ?></span>
        <span><b><?= number_format($last24) ?></b> en las últimas 24 h</span>
        <span>Se registra el <b>hecho del acceso</b> (quién, qué paciente, cuándo, desde dónde) — nunca el contenido clínico.</span>
    </div>

    <form class="audit-filters" method="GET">
        <label>Actor
            <select name="actor">
                <option value="">Todos</option>
                <option value="doctor" <?= $fActor === 'doctor' ? 'selected' : '' ?>>Médicos</option>
                <option value="patient" <?= $fActor === 'patient' ? 'selected' : '' ?>>Pacientes</option>
            </select>
        </label>
        <label>Buscar (nombre / ruta / IP)
            <input type="text" name="q" value="<?= e($fQ) ?>" placeholder="Dr. Pérez, imaging, 10.0…">
        </label>
        <label>ID paciente
            <input type="text" name="pid" value="<?= e($fPid) ?>" inputmode="numeric" style="width:110px">
        </label>
        <label>Desde
            <input type="date" name="from" value="<?= e($fFrom) ?>">
        </label>
        <label>Hasta
            <input type="date" name="to" value="<?= e($fTo) ?>">
        </label>
        <button type="submit" class="btn">Filtrar</button>
        <a href="auditoria.php" class="btn ghost">Limpiar</a>
    </form>

    <?php if (!$rows): ?>
        <div class="audit-empty">
            <?= $total === 0 && !$wsql ? 'Aún no hay accesos registrados. La bitácora empezará a llenarse en cuanto los médicos usen el portal.' : 'Sin resultados para los filtros aplicados.' ?>
        </div>
    <?php else: ?>
        <div style="overflow:auto">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Actor</th>
                        <th>Acción</th>
                        <th>Paciente</th>
                        <th class="hide-sm">IP</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $ts = strtotime((string) $r['created_at']);
                        $st = (int) $r['status'];
                        $okClass = ($st >= 200 && $st < 400) ? 'aud-status-ok' : 'aud-status-bad';
                        ?>
                        <tr>
                            <td class="audit-when"><?= e(date('d/m/Y H:i:s', $ts ?: time())) ?></td>
                            <td class="audit-actor">
                                <span class="aud-chip <?= $r['actor_type'] === 'doctor' ? 'aud-doctor' : 'aud-patient' ?>"><?= $r['actor_type'] === 'doctor' ? 'Médico' : 'Paciente' ?></span>
                                <div class="t"><?= e($r['actor_label'] ?: '—') ?></div>
                                <?php if ($r['actor_id']): ?><div class="s">#<?= (int) $r['actor_id'] ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?= e(audit_readable((string) $r['path'])) ?>
                                <div class="aud-m"><?= e($r['method']) ?> <?= e($r['path']) ?></div>
                            </td>
                            <td><?= $r['target_patient_id'] ? ('#' . (int) $r['target_patient_id']) : '<span style="color:#cbd5e1">—</span>' ?></td>
                            <td class="hide-sm aud-m"><?= e($r['ip'] ?: '—') ?></td>
                            <td class="<?= $okClass ?>"><?= $st ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="audit-pager">
                <?php if ($page > 1): ?><a href="?<?= e(audit_qs(['page' => $page - 1])) ?>">‹ Anterior</a><?php endif; ?>
                <span class="on">Página <?= $page ?> de <?= $pages ?></span>
                <?php if ($page < $pages): ?><a href="?<?= e(audit_qs(['page' => $page + 1])) ?>">Siguiente ›</a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
