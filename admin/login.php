<?php
require __DIR__ . '/includes/auth.php';

if (admin_current_user()) {
    header('Location: ' . admin_first_allowed_url());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (admin_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: ' . admin_first_allowed_url());
        exit;
    }
    $error = 'Credenciales inválidas o base de datos no instalada.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin | Hospital General Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
</head>
<body class="admin-login-body">
    <main class="login-panel">
        <section class="login-brand">
            <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
            <h1>Administración Las Colinas</h1>
            <p>Gestiona el directorio médico, perfiles profesionales, artículos clínicos y la inteligencia artificial desde una plataforma administrativa unificada y de alto rendimiento.</p>
        </section>
        
        <form method="post" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            
            <div>
                <h2>Iniciar sesión</h2>
                <p>Ingresa tus credenciales para acceder al centro de control.</p>
            </div>
            
            <?php if (!db_ready()): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span>La base de datos no está instalada. <a href="install.php">Instalar ahora</a>.</span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="admin-alert is-error">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink:0;"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>
            
            <label>
                <span>Correo Electrónico</span>
                <input type="email" name="email" required value="admin@colinashospital.com" autocomplete="email" placeholder="nombre@colinashospital.com">
            </label>
            
            <label>
                <span>Contraseña</span>
                <input type="password" name="password" required placeholder="••••••••••••" autocomplete="current-password">
            </label>
            
            <button type="submit">
                <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                Entrar al panel
            </button>
        </form>
    </main>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
