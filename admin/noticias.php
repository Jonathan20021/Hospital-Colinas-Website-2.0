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

$query = trim($_GET['q'] ?? '');
$items = news_all_admin($query ?: null);

admin_header('Noticias / Sala de prensa', 'noticias');
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Sala de prensa</span>
            <h2>Noticias publicadas</h2>
        </div>
        <form class="admin-table-search" method="get">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar por título o resumen">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Noticia</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Publicación</th>
                    <th>Vistas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="6">
                            <div class="admin-empty-state">
                                <span><i data-lucide="newspaper"></i></span>
                                <strong>No hay noticias publicadas.</strong>
                                <p>Crea tu primera noticia institucional con título, imagen de portada, resumen y contenido.</p>
                                <a href="noticia-form.php">Crear primera noticia</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="admin-doctor-cell">
                                <?php if ($item['cover_image']): ?>
                                    <img src="../<?= e($item['cover_image']) ?>" alt="" style="border-radius:8px;object-fit:cover;">
                                <?php else: ?>
                                    <span class="admin-news-thumb"><i data-lucide="newspaper"></i></span>
                                <?php endif; ?>
                                <span>
                                    <strong><?= e($item['title']) ?></strong>
                                    <small><?= e(mb_strimwidth($item['excerpt'] ?? '', 0, 120, '…')) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><span class="admin-news-cat"><?= e($item['category']) ?></span></td>
                        <td>
                            <span class="status-pill is-<?= $item['status'] === 'published' ? 'active' : 'draft' ?>">
                                <?= $item['status'] === 'published' ? 'publicada' : 'borrador' ?>
                            </span>
                            <?php if ($item['is_featured']): ?>
                                <small style="display:block;margin-top:.25rem;color:#6fb43f;font-weight:900;">★ Destacada</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(news_format_date($item['published_at'])) ?>
                        </td>
                        <td><?= (int) $item['views'] ?></td>
                        <td class="admin-actions">
                            <?php if ($item['status'] === 'published'): ?>
                                <a href="../noticias/<?= e($item['slug']) ?>" target="_blank" rel="noopener">Ver</a>
                            <?php endif; ?>
                            <a href="noticia-form.php?id=<?= e((string) $item['id']) ?>">Editar</a>
                            <form action="noticia-delete.php" method="post" onsubmit="return confirm('¿Eliminar esta noticia? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
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
