<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/repository.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('repository');
repo_ensure_schema();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = $id ? repo_by_id($id) : null;
if ($id && !$item) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $savedId = repo_save($_POST, $id);
        header('Location: repositorio.php?saved=' . $savedId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $item = array_merge($item ?: [], $_POST);
    }
}

function repo_value(array $item, string $key, string $default = ''): string
{
    return (string) ($item[$key] ?? $default);
}

admin_header($id ? 'Editar documento' : 'Nuevo documento', 'repositorio');
?>
<form method="post" enctype="multipart/form-data" class="doctor-editor news-editor">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-panel editor-main">
        <div class="admin-panel-head">
            <div>
                <span>Repositorio Digital</span>
                <h2>Protocolo o guía clínica</h2>
            </div>
            <button type="submit" class="admin-primary-action">
                <i data-lucide="save"></i>
                Guardar documento
            </button>
        </div>

        <label class="editor-wide">
            Título
            <input type="text" name="title" required maxlength="280" value="<?= e(repo_value($item ?: [], 'title')) ?>"
                placeholder="Ej. Protocolo de atención para el manejo del dengue (actualización)">
        </label>

        <label class="editor-wide">
            Resumen
            <textarea name="summary" rows="2" maxlength="500"
                placeholder="1–2 oraciones: qué cubre el documento y a quién va dirigido."><?= e(repo_value($item ?: [], 'summary')) ?></textarea>
        </label>

        <div class="editor-grid">
            <label>
                Especialidad
                <select name="category">
                    <?php foreach (repo_categories() as $cat => $icon): ?>
                        <option value="<?= e($cat) ?>" <?= repo_value($item ?: [], 'category', 'Medicina interna') === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Origen
                <select name="scope">
                    <option value="nacional" <?= repo_value($item ?: [], 'scope', 'nacional') === 'nacional' ? 'selected' : '' ?>>
                        Nacional (República Dominicana)</option>
                    <option value="internacional" <?= repo_value($item ?: [], 'scope') === 'internacional' ? 'selected' : '' ?>>
                        Internacional</option>
                </select>
            </label>
            <label>
                Organización / fuente
                <input type="text" name="org" maxlength="140" value="<?= e(repo_value($item ?: [], 'org', 'MISPAS')) ?>"
                    placeholder="Ej. MISPAS, OMS, AHA">
            </label>
            <label>
                Tipo de documento
                <select name="doc_type">
                    <?php foreach (repo_doc_types() as $type): ?>
                        <option value="<?= e($type) ?>" <?= repo_value($item ?: [], 'doc_type', 'Protocolo') === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Año
                <input type="number" name="year" min="1950" max="2100"
                    value="<?= e(repo_value($item ?: [], 'year', date('Y'))) ?>">
            </label>
            <label>
                Idioma
                <select name="language">
                    <?php foreach (repo_languages() as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= repo_value($item ?: [], 'language', 'es') === $code ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label class="editor-wide">
            Enlace oficial del documento
            <input type="url" name="external_url" value="<?= e(repo_value($item ?: [], 'external_url')) ?>"
                placeholder="https://repositorio.msp.gob.do/handle/123456789/...">
            <small style="color:#64748b;font-size:.78rem;">Recomendado: enlazar siempre a la fuente oficial. Si subes un
                PDF, el enlace queda como referencia secundaria.</small>
        </label>

        <label class="editor-wide">
            Palabras clave (búsqueda)
            <input type="text" name="tags" maxlength="400" value="<?= e(repo_value($item ?: [], 'tags')) ?>"
                placeholder="Ej. dengue, signos de alarma, fiebre, arbovirosis">
        </label>
    </section>

    <aside class="admin-panel editor-side">
        <span>Archivo y publicación</span>

        <?php if (!empty($item['file_path'])): ?>
            <div class="admin-alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;">
                <i data-lucide="file-check" style="width:16px;height:16px;"></i>
                PDF cargado:
                <a href="../<?= e($item['file_path']) ?>" target="_blank" rel="noopener" style="font-weight:800;">ver
                    archivo</a>
            </div>
        <?php endif; ?>

        <label class="file-dropzone">
            <input type="file" name="document_file" accept="application/pdf">
            <i data-lucide="file-up"></i>
            <strong><?= !empty($item['file_path']) ? 'Reemplazar PDF' : 'Subir PDF (opcional)' ?></strong>
            <small>Solo PDF. Máx 25 MB. Úsalo para protocolos internos del hospital o documentos sin enlace
                estable.</small>
        </label>

        <label class="check-row">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($item['is_featured']) ? 'checked' : '' ?>>
            Marcar como referencia clave
        </label>

        <label>
            Estado
            <select name="status">
                <option value="published" <?= repo_value($item ?: [], 'status', 'published') === 'published' ? 'selected' : '' ?>>Publicado</option>
                <option value="draft" <?= repo_value($item ?: [], 'status') === 'draft' ? 'selected' : '' ?>>Borrador
                </option>
            </select>
        </label>

        <a href="../repositorio" target="_blank" rel="noopener" class="admin-secondary-action"
            style="margin-top:.75rem;width:100%;">
            <i data-lucide="external-link"></i>
            Ver repositorio público
        </a>
    </aside>
</form>
<?php admin_footer(); ?>
