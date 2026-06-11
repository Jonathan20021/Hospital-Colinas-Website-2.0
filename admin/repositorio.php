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

$query = trim($_GET['q'] ?? '');
$items = repo_all_admin($query ?: null);

admin_header('Repositorio Digital', 'repositorio');
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Repositorio Digital</span>
            <h2>Protocolos y guías publicados</h2>
        </div>
        <form class="admin-table-search" method="get">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar por título, organización o tema">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Especialidad</th>
                    <th>Origen</th>
                    <th>Año</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="6">
                            <div class="admin-empty-state">
                                <span><i data-lucide="library"></i></span>
                                <strong>No hay documentos en el repositorio.</strong>
                                <p>Agrega un protocolo o guía con su enlace oficial, o sube directamente el PDF.</p>
                                <a href="repositorio-form.php">Agregar primer documento</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="admin-doctor-cell">
                                <span class="admin-news-thumb"><i
                                        data-lucide="<?= e(repo_category_icon($item['category'])) ?>"></i></span>
                                <span>
                                    <strong><?= e(mb_strimwidth($item['title'], 0, 90, '…')) ?></strong>
                                    <small><?= e($item['org']) ?> · <?= e($item['doc_type']) ?>
                                        <?= $item['file_path'] ? '· PDF local' : '· Enlace externo' ?></small>
                                </span>
                            </div>
                        </td>
                        <td><span class="admin-news-cat"><?= e($item['category']) ?></span></td>
                        <td><?= $item['scope'] === 'internacional' ? 'Internacional' : 'Nacional (RD)' ?></td>
                        <td><?= e((string) ($item['year'] ?? '—')) ?></td>
                        <td>
                            <span class="status-pill is-<?= $item['status'] === 'published' ? 'active' : 'draft' ?>">
                                <?= $item['status'] === 'published' ? 'publicado' : 'borrador' ?>
                            </span>
                            <?php if ($item['is_featured']): ?>
                                <small style="display:block;margin-top:.25rem;color:#6fb43f;font-weight:900;">★ Referencia
                                    clave</small>
                            <?php endif; ?>
                        </td>
                        <td class="admin-actions">
                            <a href="<?= e(repo_document_url($item)) ?>" target="_blank" rel="noopener">Ver</a>
                            <a href="repositorio-form.php?id=<?= e((string) $item['id']) ?>">Editar</a>
                            <form action="repositorio-delete.php" method="post"
                                onsubmit="return confirm('¿Eliminar este documento del repositorio? Esta acción no se puede deshacer.');">
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
