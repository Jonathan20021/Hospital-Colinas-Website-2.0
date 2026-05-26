<?php
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/db.php';

$messages = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = db_connect(null);
    if (!$server) {
        $error = 'No se pudo conectar a MySQL. Revisa que MySQL esté activo en XAMPP y que las credenciales sean correctas.';
    } else {
        try {
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $server->exec($statement);
            }

            $pdo = db_connect(db_config()['name']);
            foreach (['insurances' => 'TEXT NULL', 'associations' => 'TEXT NULL'] as $column => $definition) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors' AND COLUMN_NAME = ?");
                $check->execute([$column]);
                if ((int) $check->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE doctors ADD COLUMN {$column} {$definition} AFTER services");
                }
            }

            foreach ($services['consultas']['items'] as $index => $name) {
                $slug = slugify($name);
                $stmt = $pdo->prepare("
                    INSERT INTO specialties (name, slug, sort_order, is_active)
                    SELECT ?, ?, ?, 1
                    WHERE NOT EXISTS (SELECT 1 FROM specialties WHERE slug = ?)
                ");
                $stmt->execute([$name, $slug, ($index + 1) * 10, $slug]);
            }

            $messages[] = 'Base de datos creada o actualizada correctamente.';
            $messages[] = 'Usuario inicial: admin@colinashospital.com';
            $messages[] = 'Clave inicial: ColinasAdmin2026!';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación | Admin Las Colinas</title>
    <link rel="icon" type="image/png" href="../assets/site/favicon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="install-card">
        <img src="../assets/site/logo.png" alt="Hospital General Las Colinas">
        <h1>Instalar panel administrativo</h1>
        <p>Crea la base MySQL, las tablas del directorio médico y el usuario inicial del panel.</p>

        <?php if ($error): ?>
            <div class="admin-alert is-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <div class="admin-alert is-success"><?= e($message) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <button type="submit" class="admin-primary-action">Crear / actualizar base de datos</button>
        </form>
        <a href="login.php" class="install-link">Ir al login</a>
    </main>
</body>
</html>
