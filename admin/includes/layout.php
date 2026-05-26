<?php

function admin_header(string $title, string $active = 'dashboard'): void
{
    $user = admin_current_user();
    $primaryActions = [
        'usuarios' => ['href' => 'usuario-form.php', 'label' => 'Nuevo usuario', 'icon' => 'user-plus'],
        'medicos' => ['href' => 'medico-form.php', 'label' => 'Nuevo médico', 'icon' => 'plus'],
        'noticias' => ['href' => 'noticia-form.php', 'label' => 'Nueva noticia', 'icon' => 'plus'],
        'dashboard' => ['href' => 'medico-form.php', 'label' => 'Nuevo médico', 'icon' => 'plus'],
    ];
    $primaryAction = $primaryActions[$active] ?? null;
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
            <a href="index.php" class="admin-brand">
                <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
            </a>
            <nav class="admin-nav" aria-label="Administración">
                <a href="index.php" class="<?= $active === 'dashboard' ? 'is-active' : '' ?>">
                    <i data-lucide="layout-dashboard"></i>
                    Dashboard
                </a>
                <a href="medicos.php" class="<?= $active === 'medicos' ? 'is-active' : '' ?>">
                    <i data-lucide="user-round-search"></i>
                    Médicos
                </a>
                <a href="noticias.php" class="<?= $active === 'noticias' ? 'is-active' : '' ?>">
                    <i data-lucide="newspaper"></i>
                    Noticias
                </a>
                <a href="usuarios.php" class="<?= $active === 'usuarios' ? 'is-active' : '' ?>">
                    <i data-lucide="shield-user"></i>
                    Usuarios admin
                </a>
                <a href="ai-settings.php" class="<?= $active === 'ai' ? 'is-active' : '' ?>">
                    <i data-lucide="sparkles"></i>
                    Colinas IA
                </a>
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
                <a href="logout.php">Cerrar sesión</a>
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
