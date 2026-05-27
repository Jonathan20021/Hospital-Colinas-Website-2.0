<?php

function admin_header(string $title, string $active = 'dashboard'): void
{
    $user = admin_current_user();
    $primaryActions = [
        'usuarios' => ['href' => 'usuario-form.php', 'label' => 'Nuevo usuario', 'icon' => 'user-plus', 'permission' => 'users'],
        'medicos' => ['href' => 'medico-form.php', 'label' => 'Nuevo medico', 'icon' => 'plus', 'permission' => 'doctors'],
        'noticias' => ['href' => 'noticia-form.php', 'label' => 'Nueva noticia', 'icon' => 'plus', 'permission' => 'news'],
        'dashboard' => ['href' => 'medico-form.php', 'label' => 'Nuevo medico', 'icon' => 'plus', 'permission' => 'doctors'],
    ];
    $primaryAction = $primaryActions[$active] ?? null;
    if ($primaryAction && !admin_can($primaryAction['permission'], $user)) {
        $primaryAction = null;
    }
    if ($primaryAction && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $primaryAction['href']) {
        $primaryAction = null;
    }

    $menuItems = admin_permission_definitions();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Admin Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a href="<?= e(admin_first_allowed_url($user)) ?>" class="admin-brand">
                <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
            </a>
            <nav class="admin-nav" aria-label="Administracion">
                <?php foreach ($menuItems as $permission => $item): ?>
                    <?php if (!admin_can($permission, $user)) continue; ?>
                    <a href="<?= e($item['href']) ?>" class="<?= $active === $item['active'] ? 'is-active' : '' ?>">
                        <i data-lucide="<?= e($item['icon']) ?>"></i>
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <span class="admin-nav-divider"></span>
                <a href="../directorio-medico" target="_blank" rel="noopener">
                    <i data-lucide="external-link"></i>
                    Ver directorio
                </a>
                <a href="../" target="_blank" rel="noopener">
                    <i data-lucide="globe-2"></i>
                    Ver website
                </a>
            </nav>
            <div class="admin-user">
                <span><?= e($user['name'] ?? 'Administrador') ?></span>
                <small><?= e(($user['role'] ?? 'admin') === 'admin' ? 'Acceso total' : 'Acceso limitado') ?></small>
                <a href="logout.php">Cerrar sesion</a>
            </div>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <span>Hospital General Las Colinas</span>
                    <h1><?= e($title) ?></h1>
                </div>
                <?php if ($primaryAction): ?>
                    <a href="<?= e($primaryAction['href']) ?>" class="admin-primary-action">
                        <i data-lucide="<?= e($primaryAction['icon']) ?>"></i>
                        <?= e($primaryAction['label']) ?>
                    </a>
                <?php endif; ?>
            </header>
    <?php
}

function admin_footer(): void
{
    ?>
        </div>
    </div>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
    <?php
}
