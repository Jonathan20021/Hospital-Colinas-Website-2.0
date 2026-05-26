<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/doctors-admin.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$doctor = $id ? admin_get_doctor($id) : null;
if ($id && !$doctor) {
    http_response_code(404);
    exit('Médico no encontrado.');
}

$specialties = admin_specialties();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $savedId = admin_save_doctor($_POST, $_FILES['photo'] ?? [], $id);
        header('Location: medicos.php?saved=' . $savedId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $doctor = array_merge($doctor ?: [], $_POST);
    }
}

function doctor_value(array $doctor, string $key, string $default = ''): string
{
    return (string) ($doctor[$key] ?? $default);
}

admin_header($id ? 'Editar médico' : 'Nuevo médico', 'medicos');
?>
<form method="post" enctype="multipart/form-data" class="doctor-editor">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-panel editor-main">
        <div class="admin-panel-head">
            <div>
                <span>Perfil profesional</span>
                <h2>Datos del médico</h2>
            </div>
            <button type="submit" class="admin-primary-action">Guardar perfil</button>
        </div>

        <div class="editor-grid">
            <label>
                Título
                <select name="title">
                    <?php foreach (['Dr.', 'Dra.', 'Lic.', ''] as $title): ?>
                        <option value="<?= e($title) ?>" <?= doctor_value($doctor ?: [], 'title') === $title ? 'selected' : '' ?>><?= e($title ?: 'Sin título') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Nombre
                <input type="text" name="first_name" required value="<?= e(doctor_value($doctor ?: [], 'first_name')) ?>">
            </label>
            <label>
                Apellido
                <input type="text" name="last_name" required value="<?= e(doctor_value($doctor ?: [], 'last_name')) ?>">
            </label>
            <label>
                Exequatur
                <input type="text" name="exequatur" value="<?= e(doctor_value($doctor ?: [], 'exequatur')) ?>">
            </label>
            <label>
                Especialidad
                <select name="specialty_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?= e((string) $specialty['id']) ?>" <?= (int) doctor_value($doctor ?: [], 'specialty_id') === (int) $specialty['id'] ? 'selected' : '' ?>>
                            <?= e($specialty['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Estado
                <select name="status">
                    <?php foreach (['draft' => 'Borrador', 'active' => 'Publicado', 'inactive' => 'Inactivo'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= doctor_value($doctor ?: [], 'status', 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Consultorio / ubicación
                <input type="text" name="office" value="<?= e(doctor_value($doctor ?: [], 'office')) ?>" placeholder="Centro de Consulta Externa, consultorio 204">
            </label>
            <label>
                Horario
                <input type="text" name="schedule" value="<?= e(doctor_value($doctor ?: [], 'schedule')) ?>" placeholder="Lunes a jueves, 8:00 AM - 12:00 PM">
            </label>
            <label>
                Teléfono
                <input type="text" name="phone" value="<?= e(doctor_value($doctor ?: [], 'phone')) ?>">
            </label>
            <label>
                Correo
                <input type="email" name="email" value="<?= e(doctor_value($doctor ?: [], 'email')) ?>">
            </label>
            <label>
                Idiomas
                <input type="text" name="languages" value="<?= e(doctor_value($doctor ?: [], 'languages', 'Español')) ?>">
            </label>
            <label>
                Orden
                <input type="number" name="sort_order" value="<?= e(doctor_value($doctor ?: [], 'sort_order', '100')) ?>">
            </label>
        </div>

        <label class="editor-wide">
            Biografía
            <textarea name="biography" rows="6" placeholder="Resumen profesional, enfoque clínico, experiencia y trato al paciente."><?= e(doctor_value($doctor ?: [], 'biography')) ?></textarea>
        </label>
        <label class="editor-wide">
            Formación
            <textarea name="education" rows="4" placeholder="Universidad, residencia, certificaciones, sociedades médicas."><?= e(doctor_value($doctor ?: [], 'education')) ?></textarea>
        </label>
        <label class="editor-wide">
            Servicios / procedimientos
            <textarea name="services" rows="4" placeholder="Consulta, procedimientos, condiciones tratadas."><?= e(doctor_value($doctor ?: [], 'services')) ?></textarea>
        </label>
        <label class="editor-wide">
            Seguros aceptados
            <textarea name="insurances" rows="3" placeholder="Ej. Senasa, Humano, Universal, Mapfre, Monumental."><?= e(doctor_value($doctor ?: [], 'insurances')) ?></textarea>
        </label>
        <label class="editor-wide">
            Asociaciones / membresías
            <textarea name="associations" rows="3" placeholder="Una por línea: sociedad médica, colegio, academia o certificación profesional."><?= e(doctor_value($doctor ?: [], 'associations')) ?></textarea>
        </label>
    </section>

    <aside class="admin-panel editor-side">
        <span>Foto y publicación</span>
        <div class="photo-preview">
            <img src="../<?= e(doctor_value($doctor ?: [], 'photo_path', 'assets/site/assets/DSC00177-DrupFA59.jpg')) ?>" alt="">
        </div>
        <label class="file-dropzone">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            <i data-lucide="image-plus"></i>
            <strong>Subir foto profesional</strong>
            <small>JPG, PNG o WEBP. Recomendado: retrato vertical bien iluminado.</small>
        </label>
        <label class="check-row">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($doctor['is_featured']) ? 'checked' : '' ?>>
            Destacar en el directorio
        </label>
        <?php if (!empty($doctor['slug'])): ?>
            <a href="../medico/<?= e($doctor['slug']) ?>" target="_blank" rel="noopener" class="admin-secondary-action">Ver página pública</a>
        <?php endif; ?>
    </aside>
</form>
<?php admin_footer(); ?>
