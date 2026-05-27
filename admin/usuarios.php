<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/users-admin.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('users');
$query = trim($_GET['q'] ?? '');
$users = admin_users($query);
$notice = '';
if (isset($_GET['saved'])) {
    $notice = 'Usuario guardado correctamente.';
} elseif (isset($_GET['deleted'])) {
    $notice = 'Usuario eliminado correctamente.';
}
$error = trim($_GET['error'] ?? '');

admin_header('Usuarios admin', 'usuarios');
?>
<section class="admin-panel">
    <?php if ($notice): ?>
        <div class="admin-alert is-success"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="admin-panel-head">
        <div>
            <span>Seguridad del panel</span>
            <h2>Administradores</h2>
        </div>
        <form class="admin-table-search" method="get">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar usuario o correo">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Permisos</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="admin-user-cell">
                                <span><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($user['name']) ?></strong>
                                    <small><?= e($user['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($user['role'] === 'admin' ? 'Administrador' : 'Editor') ?></td>
                        <td>
                            <div class="permission-pills">
                                <?php foreach (admin_user_permissions($user) as $permission): ?>
                                    <?php $definition = admin_permission_definitions()[$permission] ?? null; ?>
                                    <?php if (!$definition) continue; ?>
                                    <span><?= e($definition['label']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><span class="status-pill <?= $user['is_active'] ? 'is-active' : 'is-inactive' ?>"><?= $user['is_active'] ? 'Activo' : 'Inactivo' ?></span></td>
                        <td><?= e($user['last_login'] ?: 'Sin acceso registrado') ?></td>
                        <td class="admin-actions">
                            <a href="usuario-form.php?id=<?= e((string) $user['id']) ?>">Editar</a>
                            <form action="usuario-delete.php" method="post" onsubmit="return confirm('¿Eliminar este usuario admin?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
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
