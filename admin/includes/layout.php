<?php

function admin_header(string $title, string $active = 'dashboard'): void
{
    $user = admin_current_user();
    $primaryActions = [
        'usuarios' => ['href' => 'usuario-form.php', 'label' => 'Nuevo usuario', 'icon' => 'user-plus', 'permission' => 'users'],
        'medicos' => ['href' => 'medico-form.php', 'label' => 'Nuevo médico', 'icon' => 'plus', 'permission' => 'doctors'],
        'noticias' => ['href' => 'noticia-form.php', 'label' => 'Nueva noticia', 'icon' => 'plus', 'permission' => 'news'],
        'dashboard' => ['href' => 'medico-form.php', 'label' => 'Nuevo médico', 'icon' => 'plus', 'permission' => 'doctors'],
    ];
    
    $primaryAction = $primaryActions[$active] ?? null;
    if ($primaryAction && !admin_can($primaryAction['permission'], $user)) {
        $primaryAction = null;
    }
    if ($primaryAction && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $primaryAction['href']) {
        $primaryAction = null;
    }

    $menuItems = admin_permission_definitions();

    // Saludo y fecha dinámicos
    date_default_timezone_set('America/Santo_Domingo');
    $hour = (int) date('H');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'Buenos días';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'Buenas tardes';
    } else {
        $greeting = 'Buenas noches';
    }

    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $dayOfWeek = $days[(int) date('w')];
    $day = (int) date('d');
    $month = $months[(int) date('n')];
    $year = date('Y');
    $dateString = "{$dayOfWeek}, {$day} de {$month} de {$year}";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <!-- Script inline para prevenir destellos del layout shift al cargar el menú colapsado -->
    <script>
        (function() {
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>
</head>
<body class="admin-body">
    <!-- Overlay para cerrar el menú móvil en clics externos -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <!-- Botón toggle del menú desktop (Jira/Notion style floating button) -->
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" title="Contraer/Expandir menú">
                <i data-lucide="chevron-left"></i>
            </button>

            <a href="<?= e(admin_first_allowed_url($user)) ?>" class="admin-brand">
                <img src="../assets/site/logo.png" alt="Hospital General Las Colinas" class="full-logo">
                <img src="../assets/site/favicon.png" alt="Colinas" class="mini-logo">
            </a>

            <nav class="admin-nav" aria-label="Administración">
                <?php foreach ($menuItems as $permission => $item): ?>
                    <?php if (!admin_can($permission, $user)) continue; ?>
                    <a href="<?= e($item['href']) ?>" class="<?= $active === $item['active'] ? 'is-active' : '' ?>">
                        <i data-lucide="<?= e($item['icon']) ?>"></i>
                        <span class="nav-label"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
                <span class="admin-nav-divider"></span>
                <a href="../directorio-medico" target="_blank" rel="noopener">
                    <i data-lucide="external-link"></i>
                    <span class="nav-label">Ver directorio</span>
                </a>
                <a href="../" target="_blank" rel="noopener">
                    <i data-lucide="globe"></i>
                    <span class="nav-label">Ver website</span>
                </a>
            </nav>

            <div class="admin-user">
                <!-- Wrap del avatar con opción a logout directo al colapsar -->
                <a href="logout.php" class="admin-user-avatar-wrap" title="Cerrar sesión (<?= e($user['name'] ?? 'Admin') ?>)">
                    <div class="admin-user-avatar">
                        <?= e(strtoupper(substr($user['name'] ?? 'A', 0, 1))) ?>
                        <i data-lucide="log-out" class="avatar-logout-icon"></i>
                    </div>
                </a>
                <div class="admin-user-info">
                    <span><?= e($user['name'] ?? 'Administrador') ?></span>
                    <small><?= e(($user['role'] ?? 'admin') === 'admin' ? 'Acceso total' : 'Editor') ?></small>
                </div>
                <a href="logout.php" class="admin-logout-btn" title="Cerrar sesión">
                    <i data-lucide="log-out"></i>
                </a>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Botón Hamburguesa Móvil (sólo visible en max-width: 1200px) -->
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">
                        <i data-lucide="menu"></i>
                    </button>
                    
                    <div class="admin-topbar-title">
                        <span><?= e($greeting) ?> · <?= e($dateString) ?></span>
                        <h1><?= e($title) ?></h1>
                    </div>
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
        // Inicializar iconos de Lucide
        if (window.lucide) window.lucide.createIcons();

        // Control del menú colapsable (Escritorio)
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                const isCollapsed = document.documentElement.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
            });
        }

        // Control del menú móvil deslizante (Off-canvas Drawer)
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (mobileMenuToggle && sidebarOverlay) {
            mobileMenuToggle.addEventListener('click', () => {
                document.body.classList.toggle('mobile-sidebar-open');
            });
            sidebarOverlay.addEventListener('click', () => {
                document.body.classList.remove('mobile-sidebar-open');
            });
        }
    </script>
</body>
</html>
    <?php
}
