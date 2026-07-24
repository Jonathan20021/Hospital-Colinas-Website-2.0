<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/testimonials.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('testimonials');
testimonials_ensure_schema();

// Guardar la configuración de la insignia de Google (calificación manual).
$configSaved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_google') {
    verify_csrf();
    site_setting_set('google_rating', trim((string) ($_POST['google_rating'] ?? '')));
    site_setting_set('google_reviews_total', preg_replace('/\D/', '', (string) ($_POST['google_reviews_total'] ?? '')));
    site_setting_set('google_reviews_url', trim((string) ($_POST['google_reviews_url'] ?? '')));
    site_setting_set('google_badge_enabled', empty($_POST['google_badge_enabled']) ? '0' : '1');
    header('Location: testimonios.php?gsaved=1');
    exit;
}

require __DIR__ . '/includes/layout.php';

$filter = $_GET['status'] ?? 'all';
$items = testimonials_all_admin($filter === 'all' ? null : $filter);
$counts = testimonials_count_by_status();
$google = testimonials_google();

admin_header('Testimonios y reseñas', 'testimonios');
?>
<section class="admin-panel">
    <?php if (isset($_GET['gsaved'])): ?>
        <div class="admin-alert is-success"><i data-lucide="check-circle-2"></i> Insignia de Google actualizada.</div>
    <?php elseif (isset($_GET['saved'])): ?>
        <div class="admin-alert is-success"><i data-lucide="check-circle-2"></i> Testimonio guardado.</div>
    <?php elseif (isset($_GET['approved'])): ?>
        <div class="admin-alert is-success"><i data-lucide="check-circle-2"></i> Testimonio publicado.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="admin-alert is-success"><i data-lucide="check-circle-2"></i> Testimonio eliminado.</div>
    <?php endif; ?>

    <div class="admin-panel-head">
        <div>
            <span>Prueba social</span>
            <h2>Testimonios y reseñas</h2>
        </div>
        <a class="admin-primary-action" href="testimonio-form.php"><i data-lucide="plus"></i> Nuevo testimonio</a>
    </div>

    <!-- Insignia de Google (calificación manual) -->
    <form class="tm-google-config" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_google">
        <div class="tm-google-config-head">
            <h3><i data-lucide="star"></i> Insignia de reseñas de Google</h3>
            <p>Escribe tu calificación y el total de reseñas. Aparecerá en el inicio y en la página de testimonios. Actualízalo cuando cambien.</p>
        </div>
        <div class="tm-google-config-grid">
            <label>Calificación (0–5)
                <input type="number" name="google_rating" step="0.1" min="0" max="5" value="<?= e((string) ($google['rating'] ?: '')) ?>" placeholder="4.8">
            </label>
            <label>Total de reseñas
                <input type="number" name="google_reviews_total" min="0" value="<?= e((string) ($google['total'] ?: '')) ?>" placeholder="1240">
            </label>
            <label>Enlace a tus reseñas en Google
                <input type="url" name="google_reviews_url" value="<?= e($google['url']) ?>" placeholder="https://g.page/...">
            </label>
            <label class="tm-check">
                <input type="checkbox" name="google_badge_enabled" value="1" <?= $google['enabled'] ? 'checked' : '' ?>>
                Mostrar la insignia en el sitio
            </label>
        </div>
        <div class="tm-google-config-foot">
            <button type="submit" class="admin-primary-action"><i data-lucide="save"></i> Guardar insignia</button>
            <span class="tm-hint">Para reseñas en vivo (API de Google) se puede migrar después sin rehacer nada.</span>
        </div>
    </form>

    <!-- Filtros por estado -->
    <div class="tm-filters">
        <?php
        $tabs = [
            'all' => 'Todos (' . array_sum($counts) . ')',
            'pending' => 'Pendientes (' . $counts['pending'] . ')',
            'published' => 'Publicados (' . $counts['published'] . ')',
            'rejected' => 'Rechazados (' . $counts['rejected'] . ')',
        ];
        foreach ($tabs as $key => $label): ?>
            <a href="?status=<?= e($key) ?>" class="tm-filter <?= $filter === $key ? 'is-active' : '' ?> <?= $key === 'pending' && $counts['pending'] > 0 ? 'has-badge' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Testimonio</th>
                    <th>Origen</th>
                    <th>Estado</th>
                    <th>Recibido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="5">
                        <div class="admin-empty-state">
                            <span><i data-lucide="message-square-quote"></i></span>
                            <strong>No hay testimonios <?= $filter !== 'all' ? 'en este estado' : '' ?>.</strong>
                            <p>Carga uno tú mismo o espera a que un paciente lo envíe desde la web.</p>
                            <a href="testimonio-form.php">Cargar un testimonio</a>
                            <?php if ($filter === 'all' && array_sum($counts) === 0): ?>
                                <form action="testimonio-action.php" method="post" style="margin-top:.75rem;">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="do" value="load_demo">
                                    <button type="submit" class="admin-btn-ghost">Cargar 4 ejemplos para previsualizar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($items as $t): ?>
                    <tr>
                        <td>
                            <div class="admin-doctor-cell">
                                <span class="tm-admin-avatar" style="background:linear-gradient(135deg,<?= e(testimonials_avatar_palette($t['author_name'])[0]) ?>,<?= e(testimonials_avatar_palette($t['author_name'])[1]) ?>)">
                                    <?= e(testimonials_initials($t['author_name'])) ?>
                                </span>
                                <span>
                                    <strong><?= e($t['author_name']) ?><?= $t['is_featured'] ? ' <small style="color:#5da334;">★</small>' : '' ?></strong>
                                    <small><?= e(mb_strimwidth($t['body'], 0, 110, '…')) ?></small>
                                    <small style="color:#94a3b8;"><?= (int) $t['rating'] ?>★ · <?= e(trim(($t['author_role'] ?? '') . ' · ' . ($t['author_location'] ?? ''), ' ·')) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><span class="admin-news-cat"><?= e(testimonials_source_label($t['source'])) ?></span></td>
                        <td>
                            <?php
                            $map = ['published' => ['active', 'publicado'], 'pending' => ['draft', 'pendiente'], 'rejected' => ['off', 'rechazado']];
                            [$cls, $lbl] = $map[$t['status']] ?? ['draft', $t['status']];
                            ?>
                            <span class="status-pill is-<?= e($cls) ?>"><?= e($lbl) ?></span>
                        </td>
                        <td><?= e(testimonials_format_date($t['created_at'])) ?></td>
                        <td class="admin-actions">
                            <?php if ($t['status'] !== 'published'): ?>
                                <form action="testimonio-action.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="do" value="publish">
                                    <button type="submit" class="tm-approve">Publicar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($t['status'] !== 'rejected'): ?>
                                <form action="testimonio-action.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="do" value="reject">
                                    <button type="submit">Rechazar</button>
                                </form>
                            <?php endif; ?>
                            <a href="testimonio-form.php?id=<?= (int) $t['id'] ?>">Editar</a>
                            <form action="testimonio-delete.php" method="post" onsubmit="return confirm('¿Eliminar este testimonio? No se puede deshacer.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
