<?php

function admin_header(string $title, string $active = 'dashboard'): void
{
    $user = admin_current_user();
    $primaryActions = [
        'usuarios' => ['href' => 'usuario-form.php', 'label' => 'Nuevo usuario', 'icon' => 'user-plus', 'permission' => 'users'],
        'medicos' => ['href' => 'medico-form.php', 'label' => 'Nuevo médico', 'icon' => 'plus', 'permission' => 'doctors'],
        'noticias' => ['href' => 'noticia-form.php', 'label' => 'Nueva noticia', 'icon' => 'plus', 'permission' => 'news'],
        'repositorio' => ['href' => 'repositorio-form.php', 'label' => 'Nuevo documento', 'icon' => 'plus', 'permission' => 'repository'],
        'testimonios' => ['href' => 'testimonio-form.php', 'label' => 'Nuevo testimonio', 'icon' => 'plus', 'permission' => 'testimonials'],
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
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#262161">
        <?php /* Fuentes auto-hospedadas (Inter + Outfit VARIABLES 400..800), igual que los
                 portales: mismo origen, sin DNS/TLS a Google ni CSS render-blocking. */ ?>
        <link rel="preload" as="font" type="font/woff2" href="../assets/fonts/inter-latin.woff2" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="../assets/fonts/outfit-latin.woff2" crossorigin>
        <link rel="stylesheet" href="../assets/css/fonts-portal.css?v=<?= e(admin_asset_version('css/fonts-portal.css')) ?>">
        <?php /* v = filemtime, NO time(): con time() el navegador nunca cacheaba el CSS. */ ?>
        <link rel="stylesheet" href="../assets/css/admin.css?v=<?= e(admin_asset_version('css/admin.css')) ?>">
        <!-- Script inline para prevenir destellos del layout shift al cargar el menú colapsado -->
        <script>
            (function () {
                if (localStorage.getItem('sidebar-collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            })();
        </script>
    </head>

    <body class="admin-body">
        <a class="admin-skip-link" href="#contenido">Saltar al contenido</a>

        <!-- Overlay para cerrar el menú móvil en clics externos -->
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <div class="admin-shell">
            <aside class="admin-sidebar" id="adminSidebar">
                <!-- Botón toggle del menú desktop (Jira/Notion style floating button) -->
                <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" title="Contraer/Expandir menú"
                    aria-label="Contraer o expandir el menú" aria-controls="adminSidebar" aria-expanded="true">
                    <i data-lucide="chevron-left" aria-hidden="true"></i>
                </button>

                <a href="<?= e(admin_first_allowed_url($user)) ?>" class="admin-brand">
                    <img src="../assets/site/logo.png" alt="Hospital General Las Colinas" class="full-logo">
                    <img src="../assets/site/favicon.png" alt="Colinas" class="mini-logo">
                </a>

                <nav class="admin-nav" aria-label="Administración">
                    <?php foreach ($menuItems as $permission => $item): ?>
                        <?php if (!admin_can($permission, $user))
                            continue; ?>
                        <?php $isCurrent = $active === $item['active']; ?>
                        <a href="<?= e($item['href']) ?>" class="<?= $isCurrent ? 'is-active' : '' ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                            <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
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
                    <a href="logout.php" class="admin-user-avatar-wrap"
                        title="Cerrar sesión (<?= e($user['name'] ?? 'Admin') ?>)">
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
                        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú"
                            aria-controls="adminSidebar" aria-expanded="false">
                            <i data-lucide="menu" aria-hidden="true"></i>
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

                <main id="contenido" class="admin-content" tabindex="-1">
                <?php
}

function admin_footer(): void
{
    ?>
                </main><!-- #contenido -->
            </div>
        </div>

        <?php /* Lucide auto-hospedado: unpkg@latest era un punto unico de falla externo
                 (el panel se quedaba sin iconos si unpkg fallaba) y una version sin fijar. */ ?>
        <script src="../assets/js/lucide-subset.js?v=<?= e(admin_asset_version('js/lucide-subset.js')) ?>"></script>
        <script>
            // Inicializar iconos de Lucide
            if (window.lucide) window.lucide.createIcons();

            // Control del menú colapsable (Escritorio)
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                const syncCollapsed = () => {
                    const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
                    sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                };
                syncCollapsed();
                sidebarToggle.addEventListener('click', () => {
                    const isCollapsed = document.documentElement.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebar-collapsed', isCollapsed);
                    syncCollapsed();
                });
            }

            // Control del menú móvil deslizante (Off-canvas Drawer)
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            if (mobileMenuToggle && sidebarOverlay) {
                const setDrawer = (open) => {
                    document.body.classList.toggle('mobile-sidebar-open', open);
                    mobileMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    mobileMenuToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
                };
                mobileMenuToggle.addEventListener('click', () => {
                    const opening = !document.body.classList.contains('mobile-sidebar-open');
                    setDrawer(opening);
                    if (opening) {
                        const first = document.querySelector('.admin-nav a');
                        if (first) first.focus();
                    }
                });
                sidebarOverlay.addEventListener('click', () => setDrawer(false));
                // Escape cierra el drawer y devuelve el foco al boton que lo abrio.
                document.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Escape' && document.body.classList.contains('mobile-sidebar-open')) {
                        setDrawer(false);
                        mobileMenuToggle.focus();
                    }
                });
                // Al navegar desde el drawer, cerrarlo.
                document.querySelectorAll('.admin-nav a').forEach((a) => {
                    a.addEventListener('click', () => setDrawer(false));
                });
            }
        </script>
    </body>

    </html>
    <?php
}
