<?php

function admin_specialties(): array
{
    if (!db_ready()) {
        return [];
    }

    return db()->query('SELECT id, name, slug FROM specialties WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();
}

function admin_doctor_stats(): array
{
    if (!db_ready()) {
        return ['total' => 0, 'active' => 0, 'draft' => 0, 'featured' => 0];
    }

    $row = db()->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'active') AS active,
            SUM(status = 'draft') AS draft,
            SUM(is_featured = 1) AS featured
        FROM doctors
    ")->fetch();

    return [
        'total' => (int) ($row['total'] ?? 0),
        'active' => (int) ($row['active'] ?? 0),
        'draft' => (int) ($row['draft'] ?? 0),
        'featured' => (int) ($row['featured'] ?? 0),
    ];
}

function admin_doctors(string $query = ''): array
{
    $sql = "
        SELECT d.*, s.name AS specialty
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
    ";
    $params = [];

    if ($query !== '') {
        $sql .= " WHERE CONCAT_WS(' ', d.first_name, d.last_name, d.title, d.exequatur, s.name) LIKE ? ";
        $params[] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY d.is_featured DESC, d.sort_order ASC, d.updated_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function admin_get_doctor(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM doctors WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $doctor = $stmt->fetch();

    return $doctor ?: null;
}

function admin_upload_doctor_photo(array $file, string $existing = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la foto.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La foto no debe superar 5 MB.');
    }

    $tmp = $file['tmp_name'];
    $mime = mime_content_type($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('La foto debe ser JPG, PNG o WEBP.');
    }

    $directory = __DIR__ . '/../../storage/uploads/doctors';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $filename = 'doctor-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('No se pudo guardar la foto.');
    }

    return 'storage/uploads/doctors/' . $filename;
}

function admin_save_doctor(array $data, array $file, ?int $id = null): int
{
    $pdo = db();
    $existing = $id ? admin_get_doctor($id) : null;
    $photo = admin_upload_doctor_photo($file, $existing['photo_path'] ?? '');
    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');

    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException('Nombre y apellido son obligatorios.');
    }

    $slug = unique_slug($pdo, 'doctors', $firstName . ' ' . $lastName, $id);
    $payload = [
        'specialty_id' => (int) ($data['specialty_id'] ?? 0) ?: null,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'slug' => $slug,
        'title' => trim($data['title'] ?? ''),
        'exequatur' => trim($data['exequatur'] ?? ''),
        'photo_path' => $photo,
        'biography' => trim($data['biography'] ?? ''),
        'education' => trim($data['education'] ?? ''),
        'languages' => trim($data['languages'] ?? ''),
        'services' => trim($data['services'] ?? ''),
        'insurances' => trim($data['insurances'] ?? ''),
        'associations' => trim($data['associations'] ?? ''),
        'schedule' => trim($data['schedule'] ?? ''),
        'office' => trim($data['office'] ?? ''),
        'phone' => trim($data['phone'] ?? ''),
        'email' => trim($data['email'] ?? ''),
        'status' => in_array(($data['status'] ?? 'draft'), ['draft', 'active', 'inactive'], true) ? $data['status'] : 'draft',
        'is_featured' => !empty($data['is_featured']) ? 1 : 0,
        'sort_order' => (int) ($data['sort_order'] ?? 100),
    ];

    if ($id) {
        $sets = [];
        foreach ($payload as $key => $value) {
            $sets[] = "{$key} = :{$key}";
        }
        $payload['id'] = $id;
        $stmt = $pdo->prepare('UPDATE doctors SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($payload);

        return $id;
    }

    $columns = array_keys($payload);
    $stmt = $pdo->prepare(
        'INSERT INTO doctors (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
    );
    $stmt->execute($payload);

    return (int) $pdo->lastInsertId();
}
