<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/users-admin.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

$currentUser = require_admin_permission('users');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$adminUser = $id ? admin_get_user($id) : null;
if ($id && !$adminUser) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $postedRole = $_POST['role'] ?? 'admin';
        $postedPermissions = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
        if ($id && $id === (int) $currentUser['id'] && $postedRole !== 'admin' && !in_array('users', $postedPermissions, true)) {
            throw new RuntimeException('No puedes quitarte tu propio acceso a Usuarios admin.');
        }
        $savedId = admin_save_user($_POST, $id);
        header('Location: usuarios.php?saved=' . $savedId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $adminUser = array_merge($adminUser ?: [], $_POST);
    }
}

function user_value(array $user, string $key, string $default = ''): string
{
    return (string) ($user[$key] ?? $default);
}

$permissionDefinitions = admin_permission_definitions();
$selectedPermissions = $adminUser
    ? admin_user_permissions($adminUser)
    : admin_default_permissions_for_role('admin');

admin_header($id ? 'Editar usuario' : 'Nuevo usuario', 'usuarios');
?>
<form method="post" class="admin-panel user-editor">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="admin-panel-head">
        <div>
            <span>Cuenta administrativa</span>
            <h2><?= $id ? 'Editar acceso' : 'Crear acceso' ?></h2>
        </div>
        <button type="submit" class="admin-primary-action">Guardar usuario</button>
    </div>

    <div class="editor-grid two-cols">
        <label>
            Nombre completo
            <input type="text" name="name" required value="<?= e(user_value($adminUser ?: [], 'name')) ?>">
        </label>
        <label>
            Correo
            <input type="email" name="email" required value="<?= e(user_value($adminUser ?: [], 'email')) ?>">
        </label>
        <label>
            Rol
            <select name="role">
                <?php foreach (['admin' => 'Administrador', 'editor' => 'Editor'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= user_value($adminUser ?: [], 'role', 'admin') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Contraseña <?= $id ? '(dejar vacío para no cambiar)' : '' ?>
            <input type="password" name="password" <?= $id ? '' : 'required' ?> minlength="10" autocomplete="new-password">
        </label>
    </div>

    <section class="permission-editor">
        <div>
            <span>Permisos por módulo</span>
            <h3>Acceso al menú administrativo</h3>
            <p>Selecciona las páginas del panel que este usuario puede abrir. El rol Administrador siempre conserva acceso total.</p>
        </div>
        <div class="permission-grid">
            <?php foreach ($permissionDefinitions as $permission => $definition): ?>
                <?php $checked = in_array($permission, $selectedPermissions, true); ?>
                <label class="permission-card">
                    <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>" <?= $checked ? 'checked' : '' ?>>
                    <span><i data-lucide="<?= e($definition['icon']) ?>"></i></span>
                    <strong><?= e($definition['label']) ?></strong>
                    <small><?= e($definition['description']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <label class="check-row user-active-row">
        <input type="checkbox" name="is_active" value="1" <?= user_value($adminUser ?: [], 'is_active', '1') !== '0' ? 'checked' : '' ?>>
        Usuario activo
    </label>
</form>
<?php admin_footer(); ?>
