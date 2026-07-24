<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/testimonials.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('testimonials');
testimonials_ensure_schema();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$item = $id ? testimonials_by_id($id) : null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $savedId = testimonials_save($_POST, $id ?: null);
        header('Location: testimonios.php?saved=' . $savedId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $item = array_merge($item ?: [], $_POST);
    }
}

require __DIR__ . '/includes/layout.php';
admin_header($id ? 'Editar testimonio' : 'Nuevo testimonio', 'testimonios');

$val = static fn (string $k, $d = '') => e((string) ($item[$k] ?? $d));
$rating = (int) ($item['rating'] ?? 5);
$status = $item['status'] ?? 'published';
$source = $item['source'] ?? 'hospital';
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Testimonios</span>
            <h2><?= $id ? 'Editar testimonio' : 'Nuevo testimonio' ?></h2>
        </div>
        <a class="admin-btn-ghost" href="testimonios.php"><i data-lucide="arrow-left"></i> Volver</a>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert is-error"><i data-lucide="alert-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="tm-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($id): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

        <div class="tm-form-grid">
            <label>Nombre del paciente *
                <input type="text" name="author_name" value="<?= $val('author_name') ?>" required maxlength="160" placeholder="María Fernández">
            </label>
            <label>Calificación
                <select name="rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> (<?= $i ?>)</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Rol / contexto
                <input type="text" name="author_role" value="<?= $val('author_role') ?>" maxlength="160" placeholder="Paciente de Cardiología">
            </label>
            <label>Ciudad
                <input type="text" name="author_location" value="<?= $val('author_location') ?>" maxlength="120" placeholder="Santiago">
            </label>
        </div>

        <label class="tm-form-full">Testimonio *
            <textarea name="body" rows="5" required maxlength="1500" placeholder="La atención fue excelente..."><?= $val('body') ?></textarea>
        </label>

        <div class="tm-form-grid">
            <label>Origen
                <select name="source">
                    <?php foreach (['hospital' => 'Testimonio', 'google' => 'Reseña de Google', 'facebook' => 'Facebook', 'web' => 'Enviado por el paciente'] as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= $source === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado
                <select name="status">
                    <?php foreach (['published' => 'Publicado', 'pending' => 'Pendiente', 'rejected' => 'Rechazado'] as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Correo de contacto (opcional, privado)
                <input type="email" name="contact_email" value="<?= $val('contact_email') ?>" maxlength="160" placeholder="no se muestra en el sitio">
            </label>
            <label class="tm-check">
                <input type="checkbox" name="is_featured" value="1" <?= !empty($item['is_featured']) ? 'checked' : '' ?>>
                Destacar en el inicio
            </label>
        </div>

        <div class="tm-form-actions">
            <button type="submit" class="admin-primary-action"><i data-lucide="save"></i> Guardar</button>
            <a class="admin-btn-ghost" href="testimonios.php">Cancelar</a>
        </div>
    </form>
</section>
<?php admin_footer(); ?>
