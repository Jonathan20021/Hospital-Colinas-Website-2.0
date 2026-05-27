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

$galleryToken = $id ? "gallery-{$id}" : "gallery-temp-" . session_id();
$galleryDir = __DIR__ . "/../storage/uploads/news/{$galleryToken}";
$existingImages = [];
if (is_dir($galleryDir)) {
    $files = glob($galleryDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            $existingImages[] = 'storage/uploads/news/' . $galleryToken . '/' . basename($file);
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $isNew = ($id === null);
        $savedId = news_save($_POST, $id);
        
        // Si es una nueva noticia y se subieron imágenes, renombrar la carpeta temporal
        if ($isNew) {
            $tempToken = "gallery-temp-" . session_id();
            $tempDir = __DIR__ . "/../storage/uploads/news/{$tempToken}";
            $newDir = __DIR__ . "/../storage/uploads/news/gallery-{$savedId}";
            if (is_dir($tempDir)) {
                if (is_dir($newDir)) {
                    $files = glob($tempDir . '/*');
                    if ($files) {
                        foreach ($files as $file) {
                            @rename($file, $newDir . '/' . basename($file));
                        }
                    }
                    @rmdir($tempDir);
                } else {
                    @rename($tempDir, $newDir);
                }
                
                // Actualizar las referencias de la imagen en el contenido de la base de datos
                $db = db();
                $stmt = $db->prepare("SELECT content FROM news_posts WHERE id = ?");
                $stmt->execute([$savedId]);
                $content = $stmt->fetchColumn();
                if ($content && str_contains($content, $tempToken)) {
                    $updatedContent = str_replace($tempToken, "gallery-{$savedId}", $content);
                    $db->prepare("UPDATE news_posts SET content = ? WHERE id = ?")->execute([$updatedContent, $savedId]);
                }
            }
        }
        
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
    <input type="hidden" id="galleryToken" value="<?= e($galleryToken) ?>">

    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-panel editor-main">
        <div class="admin-panel-head">
            <div>
                <span>Sala de prensa</span>
                <h2>Noticia institucional</h2>
            </div>
            <button type="submit" class="admin-primary-action">
                <i data-lucide="save"></i>
                Guardar noticia
            </button>
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
        <div class="photo-preview news-cover-preview" id="coverPreviewContainer">
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
            <input type="file" id="coverInput" name="cover_image" accept="image/jpeg,image/png,image/webp">
            <i data-lucide="image-plus"></i>
            <strong>Subir portada</strong>
            <small>JPG, PNG o WEBP. Ideal 1200×675 (16:9). Máx 6 MB.</small>
        </label>
        <label class="check-row">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($item['is_featured']) ? 'checked' : '' ?>>
            Destacar en home y listado
        </label>
        <?php if (!empty($item['slug']) && ($item['status'] ?? '') === 'published'): ?>
            <a href="../noticias/<?= e($item['slug']) ?>" target="_blank" rel="noopener" class="admin-secondary-action" style="margin-bottom: 0.5rem; width: 100%;">
                <i data-lucide="external-link"></i>
                Ver página pública
            </a>
        <?php endif; ?>

        <!-- ✨ Asistente de Redacción IA -->
        <div class="admin-panel-sub" style="border-top: 1px solid var(--line); padding-top: 1.25rem; margin-top: 1.25rem;">
            <span style="color: var(--green); font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">Inteligencia Artificial</span>
            <h3 style="font-size: 1rem; color: var(--navy); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                <i data-lucide="sparkles" style="width: 16px; height: 16px; color: var(--green);"></i>
                Redacción Colinas IA
            </h3>
            <p style="font-size: 0.75rem; color: var(--slate-500); margin: 0.4rem 0 0.8rem;">Optimiza el contenido del artículo usando IA.</p>
            
            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                <select id="aiAction" style="padding: 0.5rem; font-size: 0.85rem; border-radius: var(--radius-sm);">
                    <option value="titles">Sugerir 3 Títulos</option>
                    <option value="excerpt">Generar Resumen (Excerpt)</option>
                    <option value="grammar">Corregir Ortografía y Estilo</option>
                    <option value="expand">Expandir Párrafo Seleccionado</option>
                </select>
                
                <button type="button" class="admin-primary-action" id="runAiBtn" style="padding: 0.5rem 1rem; font-size: 0.85rem; width: 100%; min-height: 38px; border-radius: var(--radius-md);">
                    <i data-lucide="zap" style="width: 14px; height: 14px;"></i>
                    Ejecutar Asistente
                </button>
            </div>
            
            <div id="aiResultArea" style="display: none; margin-top: 1rem; border: 1px solid var(--line); padding: 0.75rem; border-radius: var(--radius-md); background-color: var(--ice);">
                <label style="font-size: 0.76rem; color: var(--slate-600); font-weight: 700; margin-bottom: 0.35rem; display: block;">Resultado Generado:</label>
                <textarea id="aiResultText" rows="6" style="font-size: 0.82rem; padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--line); width:100%; resize:vertical; background-color: var(--white); font-family: inherit; line-height: 1.45;"></textarea>
                <div style="display: flex; gap: 0.4rem; margin-top: 0.5rem;">
                    <button type="button" class="admin-primary-action" id="aiUseResult" style="padding: 0.4rem; font-size: 0.76rem; flex: 1.2; min-height: 30px; border-radius: var(--radius-sm);">
                        Insertar/Usar
                    </button>
                    <button type="button" class="admin-secondary-action" id="aiDiscardResult" style="padding: 0.4rem; font-size: 0.76rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.2); flex: 0.8; min-height: 30px; border-radius: var(--radius-sm);">
                        Descartar
                    </button>
                </div>
            </div>
        </div>

        <!-- 🖼️ Galería de Imágenes de Apoyo -->
        <div class="admin-panel-sub" style="border-top: 1px solid var(--line); padding-top: 1.25rem; margin-top: 1.25rem;">
            <span style="color: var(--green); font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">Multimedia</span>
            <h3 style="font-size: 1rem; color: var(--navy); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                <i data-lucide="images" style="width: 16px; height: 16px; color: var(--green);"></i>
                Imágenes de apoyo
            </h3>
            <p style="font-size: 0.75rem; color: var(--slate-500); margin: 0.4rem 0 0.8rem;">Sube imágenes de apoyo e insértalas en tu noticia.</p>
            
            <label class="file-dropzone" style="padding: 0.75rem 0.5rem; border-style: dotted; border-width: 1.5px; margin-bottom: 0.5rem;">
                <input type="file" id="mediaUploadInput" multiple accept="image/jpeg,image/png,image/webp">
                <i data-lucide="upload-cloud" style="width: 20px; height: 20px;"></i>
                <strong style="font-size: 0.8rem;">Subir imágenes auxiliares</strong>
                <small style="font-size: 0.68rem;">Puedes seleccionar varias. Máx. 5 MB c/u (JPG, PNG, WEBP)</small>
            </label>
            
            <!-- Spinner de carga -->
            <div id="mediaUploadSpinner" style="display: none; text-align: center; margin: 0.5rem 0; font-size: 0.8rem; color: var(--slate-500);">
                <span style="display: inline-block; animation: spin 1s linear infinite; margin-right: 0.25rem;">⏳</span> Subiendo...
            </div>

            <!-- Lista de miniaturas subidas -->
            <div id="mediaGalleryList" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.85rem;">
                <?php foreach ($existingImages as $imgUrl): ?>
                    <?php $filename = basename($imgUrl); ?>
                    <div class="media-gallery-item" data-url="<?= e($imgUrl) ?>" data-filename="<?= e($filename) ?>">
                        <img src="../<?= e($imgUrl) ?>" alt="<?= e($filename) ?>">
                        <div class="media-gallery-overlay">
                            <button type="button" class="insert-btn" title="Insertar en la noticia">
                                <i data-lucide="plus-circle" style="width: 12px; height: 12px;"></i>
                                Insertar
                            </button>
                            <button type="button" class="copy-btn" title="Copiar URL directa">
                                <i data-lucide="link" style="width: 12px; height: 12px;"></i>
                                Enlace
                            </button>
                            <button type="button" class="delete-btn" title="Eliminar imagen" style="background-color: var(--danger);">
                                <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
                                Eliminar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</form>

<script>
(function () {
    // Previsualización de imagen de portada
    const coverInput = document.getElementById('coverInput');
    const coverPreviewContainer = document.getElementById('coverPreviewContainer');

    if (coverInput && coverPreviewContainer) {
        coverInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    let img = coverPreviewContainer.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        coverPreviewContainer.innerHTML = '';
                        coverPreviewContainer.appendChild(img);
                    }
                    img.setAttribute('src', this.result);
                });
                reader.readAsDataURL(file);
            }
        });
    }

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

    // Control del Asistente Colinas IA
    const runAiBtn = document.getElementById('runAiBtn');
    const aiAction = document.getElementById('aiAction');
    const aiResultArea = document.getElementById('aiResultArea');
    const aiResultText = document.getElementById('aiResultText');
    const aiUseResult = document.getElementById('aiUseResult');
    const aiDiscardResult = document.getElementById('aiDiscardResult');

    if (runAiBtn && aiAction && aiResultArea && aiResultText) {
        runAiBtn.addEventListener('click', async function () {
            const action = aiAction.value;
            let sourceText = '';

            if (action === 'grammar' || action === 'expand') {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                sourceText = textarea.value.substring(start, end);
                
                if (!sourceText && action === 'grammar') {
                    sourceText = textarea.value; // Si no hay selección, corregir todo
                }
                
                if (!sourceText) {
                    alert('Por favor, selecciona primero un párrafo o fragmento de texto en el editor de contenido para aplicar esta acción de IA.');
                    return;
                }
            } else {
                sourceText = textarea.value;
                if (!sourceText) {
                    alert('El editor está vacío. Escribe algo de contenido en el artículo antes de generar resúmenes o títulos.');
                    return;
                }
            }

            runAiBtn.disabled = true;
            runAiBtn.innerHTML = '⏳ Procesando con IA...';
            aiResultArea.style.display = 'none';

            try {
                const res = await fetch('api-ai-news.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, text: sourceText })
                });
                const data = await res.json();

                if (data.success) {
                    aiResultText.value = data.result;
                    aiResultArea.style.display = 'block';
                } else {
                    alert('Error en el asistente: ' + data.error);
                }
            } catch (e) {
                alert('No se pudo establecer conexión con el asistente: ' + e.message);
            } finally {
                runAiBtn.disabled = false;
                runAiBtn.innerHTML = '<i data-lucide="zap" style="width: 14px; height: 14px;"></i> Ejecutar Asistente';
                if (window.lucide) window.lucide.createIcons();
            }
        });

        aiDiscardResult.addEventListener('click', () => {
            aiResultArea.style.display = 'none';
        });

        aiUseResult.addEventListener('click', () => {
            const action = aiAction.value;
            const result = aiResultText.value;
            if (!result) return;

            if (action === 'titles') {
                navigator.clipboard.writeText(result);
                alert('Títulos copiados al portapapeles. Pégalos en el campo de Título superior.');
            } else if (action === 'excerpt') {
                const excerptField = document.querySelector('textarea[name="excerpt"]');
                if (excerptField) {
                    excerptField.value = result;
                    alert('Resumen insertado con éxito en el campo "Resumen / bajada".');
                }
            } else {
                // Reemplazar o insertar en el contenido principal
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const oldVal = textarea.value;
                if (start !== end) {
                    textarea.value = oldVal.substring(0, start) + result + oldVal.substring(end);
                    textarea.focus();
                    textarea.selectionStart = start;
                    textarea.selectionEnd = start + result.length;
                } else {
                    textarea.value = oldVal.substring(0, start) + "\n" + result + "\n" + oldVal.substring(end);
                    textarea.focus();
                }
                alert('Texto insertado en el editor.');
            }
            aiResultArea.style.display = 'none';
        });
    }

    // Control de Carga de Imágenes de Apoyo (Galería AJAX)
    const mediaUploadInput = document.getElementById('mediaUploadInput');
    const mediaUploadSpinner = document.getElementById('mediaUploadSpinner');
    const mediaGalleryList = document.getElementById('mediaGalleryList');

    if (mediaUploadInput && mediaGalleryList) {
        mediaUploadInput.addEventListener('change', async function () {
            const files = this.files;
            if (!files || files.length === 0) return;

            const galleryTokenInput = document.getElementById('galleryToken');
            const token = galleryTokenInput ? galleryTokenInput.value : '';

            mediaUploadSpinner.style.display = 'block';
            mediaUploadInput.disabled = true;

            // Procesar cargas en paralelo
            const promises = Array.from(files).map(async (file) => {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('gallery_token', token);

                try {
                    const res = await fetch('api-upload-news-image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        addGalleryThumbnail(data.url, data.filename);
                    } else {
                        alert('Error al subir ' + file.name + ': ' + data.error);
                    }
                } catch (e) {
                    alert('Error de conexión al subir ' + file.name);
                }
            });

            try {
                await Promise.all(promises);
            } finally {
                mediaUploadSpinner.style.display = 'none';
                mediaUploadInput.disabled = false;
                mediaUploadInput.value = '';
            }
        });
    }

    function addGalleryThumbnail(url, filename) {
        const item = document.createElement('div');
        item.className = 'media-gallery-item';
        item.dataset.url = url;
        item.dataset.filename = filename;
        item.innerHTML = `
            <img src="../${url}" alt="${filename}">
            <div class="media-gallery-overlay">
                <button type="button" class="insert-btn" title="Insertar en la noticia">
                    <i data-lucide="plus-circle" style="width: 12px; height: 12px;"></i>
                    Insertar
                </button>
                <button type="button" class="copy-btn" title="Copiar URL directa">
                    <i data-lucide="link" style="width: 12px; height: 12px;"></i>
                    Enlace
                </button>
                <button type="button" class="delete-btn" title="Eliminar imagen" style="background-color: var(--danger);">
                    <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
                    Eliminar
                </button>
            </div>
        `;
        mediaGalleryList.appendChild(item);
        if (window.lucide) window.lucide.createIcons();
    }

    // Delegación de eventos para la galería de imágenes
    if (mediaGalleryList) {
        mediaGalleryList.addEventListener('click', async function (e) {
            const insertBtn = e.target.closest('.insert-btn');
            const copyBtn = e.target.closest('.copy-btn');
            const deleteBtn = e.target.closest('.delete-btn');
            const item = e.target.closest('.media-gallery-item');
            if (!item) return;

            const url = item.dataset.url;
            const filename = item.dataset.filename;
            const galleryTokenInput = document.getElementById('galleryToken');
            const token = galleryTokenInput ? galleryTokenInput.value : '';

            if (insertBtn) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const insertTag = `\n![Descripción de la imagen](../${url})\n`;
                textarea.value = textarea.value.substring(0, start) + insertTag + textarea.value.substring(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + insertTag.length;
            } else if (copyBtn) {
                const absoluteUrl = window.location.origin + '/' + url;
                navigator.clipboard.writeText(absoluteUrl);
                alert('URL directa de la imagen copiada al portapapeles.');
            } else if (deleteBtn) {
                if (!confirm('¿Estás seguro de que deseas eliminar esta imagen de apoyo?')) {
                    return;
                }

                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '⏳...';

                try {
                    const res = await fetch('api-delete-news-image.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ gallery_token: token, filename: filename })
                    });
                    const data = await res.json();
                    if (data.success) {
                        item.remove();
                    } else {
                        alert('Error al eliminar la imagen: ' + data.error);
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<i data-lucide="trash-2" style="width: 12px; height: 12px;"></i> Eliminar';
                        if (window.lucide) window.lucide.createIcons();
                    }
                } catch (err) {
                    alert('Error en la conexión al servidor.');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i data-lucide="trash-2" style="width: 12px; height: 12px;"></i> Eliminar';
                    if (window.lucide) window.lucide.createIcons();
                }
            }
        });
    }
})();
</script>
<?php admin_footer(); ?>
