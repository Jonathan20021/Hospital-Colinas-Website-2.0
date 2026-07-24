<?php

/**
 * Módulo de TESTIMONIOS + reseñas.
 * Vive por completo en el sitio público (BD hospital_colinas). No toca JENOFONTE
 * ni datos clínicos. Los testimonios que carga el hospital salen publicados; los
 * que envía un paciente desde la web caen en 'pending' y NADA se publica sin que
 * un admin lo apruebe.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ─────────────────────────── Esquema ─────────────────────────── */

function testimonials_ensure_schema(): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_name VARCHAR(160) NOT NULL,
            author_role VARCHAR(160) NULL,
            author_location VARCHAR(120) NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            body TEXT NOT NULL,
            source ENUM('web','google','facebook','hospital') NOT NULL DEFAULT 'hospital',
            status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            consent TINYINT(1) NOT NULL DEFAULT 1,
            contact_email VARCHAR(160) NULL,
            submitted_ip VARCHAR(45) NULL,
            published_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX t_status_idx (status),
            INDEX t_featured_idx (is_featured),
            INDEX t_created_idx (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Clave-valor para la insignia de Google (calificación manual) y textos.
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        testimonials_seed_if_empty();
        return true;
    } catch (Throwable) {
        return false;
    }
}

/* ─────────────────────── Ajustes (clave-valor) ─────────────────────── */

/** Caché compartido entre get/set para que un cambio se vea en la misma petición. */
function &_site_settings_cache(): array
{
    static $cache = [];
    return $cache;
}

function site_setting_get(string $key, ?string $default = null): ?string
{
    $cache = &_site_settings_cache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key] ?? $default;
    }
    $pdo = db();
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
    } catch (Throwable) {
        return $default;
    }
    $cache[$key] = $val === false ? null : (string) $val;
    return $cache[$key] ?? $default;
}

function site_setting_set(string $key, ?string $value): void
{
    $pdo = db();
    if (!$pdo) return;
    $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
    $cache = &_site_settings_cache();
    $cache[$key] = $value;
}

/** Config de la insignia de Google (todo manual; migrable a la API luego). */
function testimonials_google(): array
{
    return [
        'rating'  => (float) (site_setting_get('google_rating', '') ?: 0),
        'total'   => (int) (site_setting_get('google_reviews_total', '') ?: 0),
        'url'     => (string) (site_setting_get('google_reviews_url', '') ?: ''),
        'enabled' => site_setting_get('google_badge_enabled', '1') === '1',
    ];
}

/* ─────────────────────────── Semilla ─────────────────────────── */

/**
 * En una instalación nueva NO sembramos testimonios: un hospital no debe publicar
 * reseñas ficticias (ni marcarlas como reales en el JSON-LD). Solo dejamos el
 * enlace de Google por defecto. La sección del home se oculta sola mientras esté
 * vacía y el hospital carga testimonios reales desde el admin. Para previsualizar,
 * el admin puede cargar los ejemplos con testimonials_load_demo().
 */
function testimonials_seed_if_empty(): void
{
    if (site_setting_get('google_reviews_url') === null) {
        site_setting_set('google_reviews_url', 'https://www.google.com/maps/search/?api=1&query=Hospital+General+Las+Colinas+Santiago');
    }
}

/** Carga 4 testimonios de EJEMPLO (solo bajo demanda, para previsualizar). */
function testimonials_load_demo(): int
{
    $pdo = db();
    if (!$pdo) return 0;
    if ((int) $pdo->query('SELECT COUNT(*) FROM testimonials')->fetchColumn() > 0) return 0;

    $seed = [
        [
            'author_name' => 'María Fernández',
            'author_role' => 'Paciente de Cardiología',
            'author_location' => 'Santiago',
            'rating' => 5,
            'body' => 'La atención fue excelente de principio a fin. El equipo médico me explicó cada paso con paciencia y me sentí acompañada en todo momento. Instalaciones impecables.',
            'source' => 'google',
            'is_featured' => 1,
        ],
        [
            'author_name' => 'José Antonio Cruz',
            'author_role' => 'Familiar de paciente',
            'author_location' => 'Moca',
            'rating' => 5,
            'body' => 'Mi padre fue hospitalizado de emergencia y la rapidez con que lo atendieron marcó la diferencia. Personal humano y profesional. Gracias al equipo de emergencias.',
            'source' => 'google',
            'is_featured' => 1,
        ],
        [
            'author_name' => 'Yaneris Peralta',
            'author_role' => 'Paciente de Maternidad',
            'author_location' => 'Santiago',
            'rating' => 5,
            'body' => 'Di a luz a mi bebé aquí y la experiencia fue maravillosa. Las enfermeras estuvieron pendientes de nosotros día y noche. Me sentí segura y bien cuidada.',
            'source' => 'web',
            'is_featured' => 1,
        ],
        [
            'author_name' => 'Rafael Gómez',
            'author_role' => 'Paciente de Consulta Externa',
            'author_location' => 'Puerto Plata',
            'rating' => 4,
            'body' => 'Muy buena organización y tiempos de espera razonables. Los especialistas son de primera. Volveré para mis chequeos.',
            'source' => 'hospital',
            'is_featured' => 0,
        ],
    ];
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO testimonials (author_name, author_role, author_location, rating, body, source, status, is_featured, consent, published_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, "published", ?, 1, ?, ?)'
    );
    foreach ($seed as $t) {
        $stmt->execute([
            $t['author_name'], $t['author_role'], $t['author_location'], $t['rating'],
            $t['body'], $t['source'], $t['is_featured'], $now, $now,
        ]);
    }
    return count($seed);
}

/* ─────────────────────────── Consultas ─────────────────────────── */

function testimonials_published(int $limit = 0, bool $featuredFirst = true): array
{
    $pdo = db();
    if (!$pdo) return [];
    $sql = "SELECT * FROM testimonials WHERE status = 'published' ORDER BY "
        . ($featuredFirst ? 'is_featured DESC, ' : '')
        . 'COALESCE(published_at, created_at) DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . (int) $limit;
    return $pdo->query($sql)->fetchAll();
}

function testimonials_featured(int $limit = 6): array
{
    $pdo = db();
    if (!$pdo) return [];
    // Destacados primero; si no alcanzan, se completan con los más recientes.
    $stmt = $pdo->prepare(
        "SELECT * FROM testimonials WHERE status = 'published'
         ORDER BY is_featured DESC, COALESCE(published_at, created_at) DESC LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function testimonials_count_published(): int
{
    $pdo = db();
    if (!$pdo) return 0;
    return (int) $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'published'")->fetchColumn();
}

/** Promedio de estrellas de los testimonios publicados en el sitio (no el de Google). */
function testimonials_average(): float
{
    $pdo = db();
    if (!$pdo) return 0.0;
    $avg = $pdo->query("SELECT AVG(rating) FROM testimonials WHERE status = 'published'")->fetchColumn();
    return $avg ? round((float) $avg, 1) : 0.0;
}

function testimonials_all_admin(?string $status = null): array
{
    $pdo = db();
    if (!$pdo) return [];
    $sql = 'SELECT * FROM testimonials';
    $params = [];
    if ($status && in_array($status, ['pending', 'published', 'rejected'], true)) {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    // Pendientes arriba siempre; luego lo más nuevo.
    $sql .= " ORDER BY FIELD(status,'pending','published','rejected'), created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function testimonials_count_by_status(): array
{
    $pdo = db();
    if (!$pdo) return ['pending' => 0, 'published' => 0, 'rejected' => 0];
    $rows = $pdo->query('SELECT status, COUNT(*) c FROM testimonials GROUP BY status')->fetchAll();
    $out = ['pending' => 0, 'published' => 0, 'rejected' => 0];
    foreach ($rows as $r) $out[$r['status']] = (int) $r['c'];
    return $out;
}

function testimonials_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo) return null;
    $stmt = $pdo->prepare('SELECT * FROM testimonials WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ─────────────────────────── Escritura ─────────────────────────── */

/** Guarda un testimonio desde el ADMIN (crear/editar). */
function testimonials_save(array $data, ?int $id = null): int
{
    $pdo = db();
    if (!$pdo) throw new RuntimeException('Base de datos no disponible.');

    $name = trim((string) ($data['author_name'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    if ($name === '') throw new RuntimeException('El nombre es obligatorio.');
    if (mb_strlen($body) < 10) throw new RuntimeException('El testimonio es demasiado corto.');

    $rating = (int) ($data['rating'] ?? 5);
    $rating = max(1, min(5, $rating));
    $status = in_array($data['status'] ?? '', ['pending', 'published', 'rejected'], true) ? $data['status'] : 'published';
    $source = in_array($data['source'] ?? '', ['web', 'google', 'facebook', 'hospital'], true) ? $data['source'] : 'hospital';

    $payload = [
        'author_name' => mb_substr($name, 0, 160),
        'author_role' => trim((string) ($data['author_role'] ?? '')) ?: null,
        'author_location' => trim((string) ($data['author_location'] ?? '')) ?: null,
        'rating' => $rating,
        'body' => mb_substr($body, 0, 1500),
        'source' => $source,
        'status' => $status,
        'is_featured' => !empty($data['is_featured']) ? 1 : 0,
        'consent' => !empty($data['consent']) ? 1 : (isset($data['consent']) ? 0 : 1),
        'contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
        'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
    ];

    if ($id) {
        $existing = testimonials_by_id($id);
        if (!$existing) throw new RuntimeException('Testimonio no encontrado.');
        // Conservar la fecha de publicación original si ya estaba publicado.
        if ($status === 'published' && $existing['status'] === 'published' && $existing['published_at']) {
            $payload['published_at'] = $existing['published_at'];
        }
        $sets = [];
        $vals = [];
        foreach ($payload as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
        $vals[] = $id;
        $pdo->prepare('UPDATE testimonials SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        return $id;
    }

    $cols = array_keys($payload);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $pdo->prepare('INSERT INTO testimonials (' . implode(',', $cols) . ') VALUES (' . $ph . ')')
        ->execute(array_values($payload));
    return (int) $pdo->lastInsertId();
}

/** Alta desde el FORMULARIO PÚBLICO. Siempre entra como 'pending'. */
function testimonials_submit_public(array $data, string $ip): int
{
    $pdo = db();
    if (!$pdo) throw new RuntimeException('Base de datos no disponible.');

    $name = trim((string) ($data['author_name'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    if ($name === '' || mb_strlen($name) < 3) throw new RuntimeException('Escribe tu nombre.');
    if (mb_strlen($body) < 15) throw new RuntimeException('Cuéntanos un poco más sobre tu experiencia.');
    if (mb_strlen($body) > 1500) $body = mb_substr($body, 0, 1500);

    $rating = max(1, min(5, (int) ($data['rating'] ?? 5)));
    $email = trim((string) ($data['contact_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

    $stmt = $pdo->prepare(
        'INSERT INTO testimonials (author_name, author_role, author_location, rating, body, source, status, consent, contact_email, submitted_ip)
         VALUES (?, ?, ?, ?, ?, "web", "pending", 1, ?, ?)'
    );
    $stmt->execute([
        mb_substr($name, 0, 160),
        trim((string) ($data['author_role'] ?? '')) ?: null,
        trim((string) ($data['author_location'] ?? '')) ?: null,
        $rating,
        $body,
        $email ?: null,
        mb_substr($ip, 0, 45),
    ]);
    return (int) $pdo->lastInsertId();
}

function testimonials_set_status(int $id, string $status): bool
{
    if (!in_array($status, ['pending', 'published', 'rejected'], true)) return false;
    $pdo = db();
    if (!$pdo) return false;
    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
    // Al publicar por primera vez estampa la fecha; al despublicar la conserva.
    if ($status === 'published') {
        $pdo->prepare('UPDATE testimonials SET status = ?, published_at = COALESCE(published_at, ?) WHERE id = ?')
            ->execute([$status, $publishedAt, $id]);
    } else {
        $pdo->prepare('UPDATE testimonials SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    return true;
}

function testimonials_toggle_featured(int $id): bool
{
    $pdo = db();
    if (!$pdo) return false;
    $pdo->prepare('UPDATE testimonials SET is_featured = 1 - is_featured WHERE id = ?')->execute([$id]);
    return true;
}

function testimonials_delete(int $id): bool
{
    $pdo = db();
    if (!$pdo) return false;
    $pdo->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$id]);
    return true;
}

/* ─────────────────────── Anti-spam del envío público ─────────────────────── */

/**
 * Límite por IP: máximo N envíos por ventana de tiempo. Devuelve true si se
 * excede (hay que bloquear). Usa la propia tabla, sin dependencias externas.
 */
function testimonials_rate_limited(string $ip, int $maxPerHour = 3): bool
{
    $pdo = db();
    if (!$pdo || $ip === '') return false;
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM testimonials WHERE submitted_ip = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)'
        );
        $stmt->execute([$ip]);
        return ((int) $stmt->fetchColumn()) >= $maxPerHour;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Verifica hCaptcha SOLO si el sitio público tiene el secreto configurado
 * (HCAPTCHA_SECRET en config.local.php). Si no está, no bloquea: el envío
 * queda protegido por honeypot + límite por IP + moderación.
 * Devuelve true si se puede continuar.
 */
function testimonials_captcha_ok(?string $token): bool
{
    if (!defined('HCAPTCHA_SECRET') || HCAPTCHA_SECRET === '') {
        return true; // hCaptcha no configurado en el sitio público → se omite
    }
    if (!$token) return false;
    $ch = curl_init('https://hcaptcha.com/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => HCAPTCHA_SECRET,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string) $res, true);
    return is_array($j) && !empty($j['success']);
}

/* ─────────────────────────── Presentación ─────────────────────────── */

function testimonials_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts));
    if (!$parts) return '?';
    $first = mb_substr($parts[0], 0, 1);
    $second = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $second);
}

/** Gradiente determinista para el avatar, en la paleta de marca. */
function testimonials_avatar_palette(string $seed): array
{
    $pairs = [
        ['#262161', '#4f46e5'],
        ['#5da334', '#2f8f57'],
        ['#1d4ed8', '#4f46e5'],
        ['#0a7a52', '#5da334'],
        ['#6d4bd8', '#262161'],
    ];
    $h = 0;
    for ($i = 0, $n = strlen($seed); $i < $n; $i++) $h = ($h * 31 + ord($seed[$i])) % 100000;
    return $pairs[$h % count($pairs)];
}

/** Estrellas en SVG (llenas y vacías) para una calificación 1-5. */
function testimonials_stars_html(int $rating, string $class = 'tm-stars'): string
{
    $rating = max(0, min(5, $rating));
    $star = static function (bool $filled): string {
        $fill = $filled ? 'currentColor' : 'none';
        return '<svg viewBox="0 0 24 24" fill="' . $fill . '" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.12 4.3a.56.56 0 0 0 .42.3l4.75.69c.5.07.7.69.34 1.04l-3.44 3.35a.56.56 0 0 0-.16.5l.81 4.73c.09.5-.44.88-.89.65l-4.25-2.23a.56.56 0 0 0-.52 0l-4.25 2.23c-.45.23-.98-.15-.89-.65l.81-4.73a.56.56 0 0 0-.16-.5L4.09 9.83c-.36-.35-.16-.97.34-1.04l4.75-.69a.56.56 0 0 0 .42-.3l2.12-4.3Z"/></svg>';
    };
    $out = '<span class="' . e($class) . '" role="img" aria-label="' . $rating . ' de 5 estrellas">';
    for ($i = 1; $i <= 5; $i++) $out .= $star($i <= $rating);
    return $out . '</span>';
}

function testimonials_source_label(string $source): string
{
    return [
        'google' => 'Reseña de Google',
        'facebook' => 'Facebook',
        'web' => 'Enviado por el paciente',
        'hospital' => 'Testimonio',
    ][$source] ?? 'Testimonio';
}

function testimonials_format_date(?string $datetime): string
{
    if (!$datetime) return '';
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($datetime);
    if (!$ts) return '';
    return (int) date('j', $ts) . ' de ' . $months[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}
