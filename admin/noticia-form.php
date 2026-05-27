<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/news.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('news');
news_ensure_schema();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = $id ? news_by_id($id) : null;
if ($id && !$item) {
    http_response_code(404);
    exit('Noticia no encontrada.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $savedId = news_save($_POST, $id);
        header('Location: noticias.php?saved=' . $savedId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $item = array_merge($item ?: [], $_POST);
    }
}

function news_value(array $item, string $key, string $default = ''): string
{
    return (string) ($item[$key] ?? $default);
}

$publishedValue = '';
if (!empty($item['published_at'])) {
    $publishedValue = date('Y-m-d\TH:i', strtotime($item['published_at']));
}

admin_header($id ? 'Editar noticia' : 'Nueva noticia', 'noticias');
?>
<form method="post" enctype="multipart/form-data" class="doctor-editor news-editor">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-panel editor-main">
        <div class="admin-panel-head">
            <div>
                <span>Sala de prensa</span>
                <h2>Noticia institucional</h2>
            </div>
            <button type="submit" class="admin-primary-action">Guardar noticia</button>
        </div>

        <label class="editor-wide">
            Título
            <input type="text" name="title" required maxlength="220"
                value="<?= e(news_value($item ?: [], 'title')) ?>"
                placeholder="Ej. Hospital Las Colinas inaugura unidad de cardiología avanzada">
        </label>

        <div class="editor-grid">
            <label>
                Categoría
                <select name="category">
                    <?php foreach (news_categories() as $cat => $icon): ?>
                        <option value="<?= e($cat) ?>" <?= news_value($item ?: [], 'category', 'Institucional') === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Fecha de publicación
                <input type="datetime-local" name="published_at" value="<?= e($publishedValue) ?>">
            </label>
            <label>
                Autor / firma
                <input type="text" name="author" value="<?= e(news_value($item ?: [], 'author', 'Comunicaciones Las Colinas')) ?>">
            </label>
            <label>
                Estado
                <select name="status">
                    <option value="published" <?= news_value($item ?: [], 'status', 'published') === 'published' ? 'selected' : '' ?>>Publicada</option>
                    <option value="draft" <?= news_value($item ?: [], 'status') === 'draft' ? 'selected' : '' ?>>Borrador</option>
                </select>
            </label>
        </div>

        <label class="editor-wide">
            Resumen / bajada
            <textarea name="excerpt" rows="2" maxlength="360"
                placeholder="1–2 oraciones que resuman la noticia (se muestra en las tarjetas)."><?= e(news_value($item ?: [], 'excerpt')) ?></textarea>
            <small style="color:#64748b;font-size:.78rem;">Si lo dejas vacío se generará automáticamente desde el contenido.</small>
        </label>

        <label class="editor-wide">
            Contenido
            <div class="news-editor-toolbar" data-news-toolbar>
                <button type="button" data-md="**" data-md-end="**" title="Negrita"><strong>B</strong></button>
                <button type="button" data-md="_" data-md-end="_" title="Cursiva"><em>I</em></button>
                <button type="button" data-md-line="## " title="Encabezado">H</button>
                <button type="button" data-md-line="- " title="Lista">•</button>
                <button type="button" data-md-line="1. " title="Lista numerada">1.</button>
                <button type="button" data-md-line="> " title="Cita">"</button>
                <button type="button" data-md-link title="Enlace">🔗</button>
            </div>
            <textarea id="newsContent" name="content" rows="14" required
                placeholder="Escribe la noticia usando markdown:&#10;&#10;## Subtítulo&#10;Párrafo normal con **negritas** y _cursivas_.&#10;&#10;- Punto de lista&#10;- Otro punto&#10;&#10;> Cita textual o destacado."><?= e(news_value($item ?: [], 'content')) ?></textarea>
            <small style="color:#64748b;font-size:.78rem;">Markdown soportado: **negrita**, _cursiva_, ## subtítulos, - listas, > citas, [texto](url).</small>
        </label>

        <label class="editor-wide">
            URL fuente original (opcional)
            <input type="url" name="source_url" value="<?= e(news_value($item ?: [], 'source_url')) ?>"
                placeholder="https://...">
            <small style="color:#64748b;font-size:.78rem;">Si la noticia fue cubierta por un medio externo, agrega el enlace.</small>
        </label>
    </section>

    <aside class="admin-panel editor-side">
        <span>Portada y publicación</span>
        <div class="photo-preview news-cover-preview">
            <?php if (!empty($item['cover_image'])): ?>
                <img src="../<?= e($item['cover_image']) ?>" alt="">
            <?php else: ?>
                <div class="news-cover-placeholder">
                    <i data-lucide="image"></i>
                    <span>Sin imagen de portada</span>
                </div>
            <?php endif; ?>
        </div>
        <label class="file-dropzone">
            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
            <i data-lucide="image-plus"></i>
            <strong>Subir portada</strong>
            <small>JPG, PNG o WEBP. Ideal 1200×675 (16:9). Máx 6 MB.</small>
        </label>
        <label class="check-row">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($item['is_featured']) ? 'checked' : '' ?>>
            Destacar en home y listado
        </label>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a href="../noticias/<?= e($item['slug']) ?>" target="_blank" rel="noopener" class="admin-secondary-action">Ver página pública</a>
        <?php endif; ?>
    </aside>
</form>

<script>
(function () {
    const toolbar = document.querySelector('[data-news-toolbar]');
    const textarea = document.getElementById('newsContent');
    if (!toolbar || !textarea) return;

    function wrapSelection(before, after) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.substring(start, end) || 'texto';
        textarea.value = textarea.value.substring(0, start) + before + selected + after + textarea.value.substring(end);
        textarea.focus();
        textarea.selectionStart = start + before.length;
        textarea.selectionEnd = start + before.length + selected.length;
    }
    function prependLine(prefix) {
        const start = textarea.selectionStart;
        const before = textarea.value.substring(0, start);
        const lineStart = before.lastIndexOf('\n') + 1;
        textarea.value = textarea.value.substring(0, lineStart) + prefix + textarea.value.substring(lineStart);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + prefix.length;
    }

    toolbar.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.dataset.md && btn.dataset.mdEnd !== undefined) {
                wrapSelection(btn.dataset.md, btn.dataset.mdEnd);
            } else if (btn.dataset.mdLine !== undefined) {
                prependLine(btn.dataset.mdLine);
            } else if (btn.dataset.mdLink !== undefined) {
                const url = prompt('URL del enlace:');
                if (!url) return;
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selected = textarea.value.substring(start, end) || 'texto';
                const insert = `[${selected}](${url})`;
                textarea.value = textarea.value.substring(0, start) + insert + textarea.value.substring(end);
                textarea.focus();
            }
        });
    });
})();
</script>
<?php admin_footer(); ?>
