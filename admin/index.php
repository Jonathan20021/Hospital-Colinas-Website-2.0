<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/doctors-admin.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin();
$stats = admin_doctor_stats();
$recentDoctors = array_slice(admin_doctors(), 0, 6);

admin_header('Dashboard', 'dashboard');
?>
<section class="admin-stats">
    <article>
        <span>Total médicos</span>
        <strong><?= e((string) $stats['total']) ?></strong>
    </article>
    <article>
        <span>Publicados</span>
        <strong><?= e((string) $stats['active']) ?></strong>
    </article>
    <article>
        <span>Borradores</span>
        <strong><?= e((string) $stats['draft']) ?></strong>
    </article>
    <article>
        <span>Destacados</span>
        <strong><?= e((string) $stats['featured']) ?></strong>
    </article>
</section>

<section class="admin-dashboard-grid">
    <div class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <span>Directorio médico</span>
                <h2>Últimos perfiles editados</h2>
            </div>
            <a href="medicos.php">Gestionar</a>
        </div>
        <div class="admin-list">
            <?php if (!$recentDoctors): ?>
                <p class="admin-empty">Aún no hay médicos cargados. Crea el primer perfil para activar el directorio real.</p>
            <?php endif; ?>
            <?php foreach ($recentDoctors as $doctor): ?>
                <a href="medico-form.php?id=<?= e((string) $doctor['id']) ?>" class="admin-list-row">
                    <img src="../<?= e($doctor['photo_path'] ?: 'assets/site/assets/DSC00177-DrupFA59.jpg') ?>" alt="">
                    <span>
                        <strong><?= e(trim(($doctor['title'] ? $doctor['title'] . ' ' : '') . $doctor['first_name'] . ' ' . $doctor['last_name'])) ?></strong>
                        <small><?= e($doctor['specialty'] ?: 'Sin especialidad') ?></small>
                    </span>
                    <em><?= e($doctor['status']) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="admin-panel admin-next-panel">
        <span>Siguiente fase</span>
        <h2>Panel del paciente</h2>
        <p>La estructura ya separa administración, directorio público y perfiles individuales. La próxima fase puede usar el mismo patrón para pacientes, citas, documentos y resultados.</p>
        <a href="medico-form.php" class="admin-secondary-action">Cargar médico</a>
    </aside>
</section>
<?php admin_footer(); ?>
