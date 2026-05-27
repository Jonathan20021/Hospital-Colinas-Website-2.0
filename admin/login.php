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
    <title>Acceso admin | Hospital General Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="login-panel">
        <section class="login-brand">
            <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
            <h1>Administración Las Colinas</h1>
            <p>Gestiona el directorio médico, perfiles profesionales y contenido clínico desde un panel moderno.</p>
        </section>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <h2>Iniciar sesión</h2>
            <?php if (!db_ready()): ?>
                <div class="admin-alert is-error">
                    La base de datos no está instalada. <a href="install.php">Instalar ahora</a>.
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="admin-alert is-error"><?= e($error) ?></div>
            <?php endif; ?>
            <label>
                Correo
                <input type="email" name="email" required value="admin@colinashospital.com">
            </label>
            <label>
                Contraseña
                <input type="password" name="password" required placeholder="Contraseña">
            </label>
            <button type="submit">Entrar al panel</button>
        </form>
    </main>
</body>
</html>
