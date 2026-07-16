<?php
/**
 * Admin → Soporte a médicos (modo soporte / impersonación auditada).
 * Gateado por: permiso `doctor_support` + 2FA (ya exigido en el login del admin)
 * + re-ingreso de la propia contraseña del admin ("modo sudo") + CSRF.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/../includes/doctor_support.php';

if (!db_ready()) { header('Location: install.php'); exit; }

$admin = require_admin_permission('doctor_support');
$error = '';

// ── POST: abrir soporte (CSRF + re-auth con la contraseña del admin) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $pwd      = (string)($_POST['admin_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int)$admin['id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($pwd, (string)$hash)) {
        $error = 'Contraseña incorrecta. Vuelve a intentarlo.';
    } elseif ($doctorId <= 0) {
        $error = 'Selecciona un médico.';
    } else {
        $r = doctor_support_open($doctorId, ['id' => (int)$admin['id'], 'name' => (string)$admin['name']]);
        if (!empty($r['ok'])) {
            header('Location: ' . base_url('portal-medico/dashboard.php'));
            exit;
        }
        $error = $r['message'] ?? 'No se pudo abrir la sesión de soporte.';
    }
}

// ── GET: buscador + lista de médicos (desde el API interno) ──────────────────
$q = trim((string)($_GET['q'] ?? ''));
$doctors = [];
$listErr = '';
$res = doctor_support_api('GET', 'portal-doctor/support/doctors', $q !== '' ? ['q' => $q] : []);
if (!empty($res['ok'])) $doctors = $res['data']['doctors'] ?? [];
else $listErr = $res['message'] ?? 'No se pudo cargar la lista de médicos.';

admin_header('Soporte a médicos', 'soporte_medico');
?>
<section class="admin-panel">
  <div style="border-left:4px solid #dc2626;background:#fef2f2;border-radius:12px;margin-bottom:1.2rem;padding:1rem 1.2rem">
    <strong style="color:#b91c1c">🛟 Acceso de soporte (impersonación)</strong>
    <p style="margin:.4rem 0 0;color:#7f1d1d;font-size:.9rem;max-width:70ch">
      Abrir soporte te da <strong>acceso completo</strong> al portal del médico, como si fueras él, durante <strong>30 minutos</strong>.
      Cada acceso queda <strong>auditado</strong> (visible en Auditoría Web) y el médico verá un aviso permanente de que estás en su portal.
      Debes confirmar con <strong>tu propia contraseña</strong>. Úsalo solo para dar soporte.
    </p>
  </div>

  <?php if ($error): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($listErr): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem"><?= e($listErr) ?></div>
  <?php endif; ?>

  <form method="GET" style="margin-bottom:1rem;display:flex;gap:.5rem;max-width:560px">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar médico por nombre o correo…"
           style="flex:1;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.95rem">
    <button type="submit" style="background:#262161;color:#fff;border:0;border-radius:8px;padding:.6rem 1.1rem;font-weight:600;cursor:pointer">Buscar</button>
  </form>

  <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px">
    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
      <thead>
        <tr style="text-align:left;color:#64748b;background:#f8fafc">
          <th style="padding:.7rem .9rem">Médico</th>
          <th style="padding:.7rem .9rem">Especialidad</th>
          <th style="padding:.7rem .9rem">Correo</th>
          <th style="padding:.7rem .9rem;text-align:right">Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($doctors as $d): $dname = trim((string)$d['name']); ?>
        <tr style="border-top:1px solid #f1f5f9">
          <td style="padding:.6rem .9rem;font-weight:600;color:#0f172a"><?= e($dname) ?></td>
          <td style="padding:.6rem .9rem;color:#475569"><?= e((string)($d['specialty'] ?? '')) ?></td>
          <td style="padding:.6rem .9rem;color:#475569"><?= e((string)($d['email'] ?? '')) ?></td>
          <td style="padding:.6rem .9rem;text-align:right">
            <details>
              <summary style="cursor:pointer;color:#262161;font-weight:600;list-style:none">Abrir soporte…</summary>
              <form method="POST" style="margin-top:.5rem;display:flex;gap:.4rem;align-items:center;justify-content:flex-end;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">
                <input type="password" name="admin_password" placeholder="Tu contraseña" required autocomplete="current-password"
                       style="padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px">
                <button type="submit"
                        onclick="return confirm('¿Abrir el portal del Dr. <?= e(str_replace("'", '', $dname)) ?> en modo soporte por 30 min? Quedará auditado.')"
                        style="background:#dc2626;color:#fff;border:0;border-radius:8px;padding:.45rem .9rem;font-weight:700;cursor:pointer">Entrar</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$doctors && !$listErr): ?>
        <tr><td colspan="4" style="padding:1rem .9rem;color:#94a3b8;font-style:italic">Sin médicos que coincidan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_footer();
