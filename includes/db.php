<?php

function db_config(): array
{
    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'hospital_colinas',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ];
}

function db_connect(?string $database = null): ?PDO
{
    $config = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=utf8mb4%s',
        $config['host'],
        $config['port'],
        $database ? ';dbname=' . $database : ''
    );

    try {
        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable) {
        return null;
    }
}

function db(): ?PDO
{
    static $pdo = false;

    if ($pdo === false) {
        $pdo = db_connect(db_config()['name']);
    }

    return $pdo ?: null;
}

function db_ready(): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM doctors LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function slugify(string $value): string
{
    $value = trim($value);
    $value = strtr($value, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c',
    ]);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'registro';
}

function unique_slug(PDO $pdo, string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$candidate];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $counter;
        $counter++;
    }
}
