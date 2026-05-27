<?php

function admin_users(string $query = ''): array
{
    admin_ensure_permissions_schema();

    $sql = 'SELECT id, name, email, role, permissions, is_active, last_login, created_at FROM admin_users';
    $params = [];

    if ($query !== '') {
        $sql .= ' WHERE CONCAT_WS(" ", name, email, role) LIKE ?';
        $params[] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY is_active DESC, name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function admin_get_user(int $id): ?array
{
    admin_ensure_permissions_schema();

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function admin_save_user(array $data, ?int $id = null): int
{
    $pdo = db();
    $name = trim($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $role = in_array(($data['role'] ?? 'admin'), ['admin', 'editor'], true) ? $data['role'] : 'admin';
    $rawPermissions = $data['permissions'] ?? [];
    if ($role !== 'admin' && (!is_array($rawPermissions) || $rawPermissions === [])) {
        throw new RuntimeException('Selecciona al menos un permiso para este usuario.');
    }
    $permissions = admin_normalize_permissions($rawPermissions, $role);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($name === '' || $email === '') {
        throw new RuntimeException('Nombre y correo son obligatorios.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo no tiene un formato válido.');
    }

    if (!$id && strlen($password) < 10) {
        throw new RuntimeException('La contraseña debe tener al menos 10 caracteres.');
    }

    $duplicateSql = 'SELECT id FROM admin_users WHERE email = ?';
    $duplicateParams = [$email];
    if ($id) {
        $duplicateSql .= ' AND id <> ?';
        $duplicateParams[] = $id;
    }
    $stmt = $pdo->prepare($duplicateSql . ' LIMIT 1');
    $stmt->execute($duplicateParams);
    if ($stmt->fetch()) {
        throw new RuntimeException('Ya existe un usuario con ese correo.');
    }

    $payload = [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'permissions' => json_encode($role === 'admin' ? admin_all_permission_keys() : $permissions, JSON_UNESCAPED_SLASHES),
        'is_active' => $isActive,
    ];

    if ($password !== '') {
        if (strlen($password) < 10) {
            throw new RuntimeException('La contraseña debe tener al menos 10 caracteres.');
        }
        $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($id) {
        $sets = [];
        foreach ($payload as $key => $value) {
            $sets[] = "{$key} = :{$key}";
        }
        $payload['id'] = $id;
        $stmt = $pdo->prepare('UPDATE admin_users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($payload);

        return $id;
    }

    $columns = array_keys($payload);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
    );
    $stmt->execute($payload);

    return (int) $pdo->lastInsertId();
}

function admin_delete_user(int $id, int $currentUserId): void
{
    if ($id === $currentUserId) {
        throw new RuntimeException('No puedes eliminar tu propio usuario.');
    }

    $stmt = db()->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);
}
