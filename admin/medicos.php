<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/doctors-admin.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin();
$query = trim($_GET['q'] ?? '');
$doctors = admin_doctors($query);

admin_header('Médicos', 'medicos');
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Directorio médico</span>
            <h2>Perfiles profesionales</h2>
        </div>
        <form class="admin-table-search" method="get">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar médico o especialidad">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Estado</th>
                    <th>Consultorio / horario</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$doctors): ?>
                    <tr>
                        <td colspan="5">
                            <div class="admin-empty-state">
                                <span><i data-lucide="user-round-plus"></i></span>
                                <strong>No hay perfiles médicos cargados.</strong>
                                <p>Crea el primer médico con foto, especialidad, biografía, horario y consultorio para activar el directorio real.</p>
                                <a href="medico-form.php">Crear primer médico</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td>
                            <div class="admin-doctor-cell">
                                <img src="../<?= e($doctor['photo_path'] ?: 'assets/site/assets/DSC00177-DrupFA59.jpg') ?>" alt="">
                                <span>
                                    <strong><?= e(trim(($doctor['title'] ? $doctor['title'] . ' ' : '') . $doctor['first_name'] . ' ' . $doctor['last_name'])) ?></strong>
                                    <small><?= e($doctor['exequatur'] ?: 'Sin exequatur') ?></small>
                                </span>
                            </div>
                        </td>
                        <td><?= e($doctor['specialty'] ?: 'Sin especialidad') ?></td>
                        <td><span class="status-pill is-<?= e($doctor['status']) ?>"><?= e($doctor['status']) ?></span></td>
                        <td><?= e($doctor['office'] ?: 'Por definir') ?><br><small><?= e($doctor['schedule'] ?: 'Por coordinación') ?></small></td>
                        <td class="admin-actions">
                            <a href="../medico/<?= e($doctor['slug']) ?>" target="_blank" rel="noopener">Ver</a>
                            <a href="medico-form.php?id=<?= e((string) $doctor['id']) ?>">Editar</a>
                            <form action="medico-delete.php" method="post" onsubmit="return confirm('¿Eliminar este médico?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $doctor['id']) ?>">
                                <button type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_footer(); ?>
