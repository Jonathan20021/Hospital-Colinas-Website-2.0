<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function news_ensure_schema(): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(220) NOT NULL UNIQUE,
            title VARCHAR(220) NOT NULL,
            excerpt VARCHAR(360) NULL,
            content MEDIUMTEXT NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Institucional',
            cover_image VARCHAR(255) NULL,
            source_url VARCHAR(360) NULL,
            author VARCHAR(160) NULL,
            published_at DATETIME NOT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX news_status_idx (status),
            INDEX news_published_idx (published_at),
            INDEX news_category_idx (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        news_seed_if_empty();
        return true;
    } catch (Throwable) {
        return false;
    }
}

function news_categories(): array
{
    return [
        'Inauguración' => 'sparkles',
        'Servicio' => 'stethoscope',
        'Alianza' => 'handshake',
        'Reconocimiento' => 'award',
        'Institucional' => 'landmark',
        'Empleo' => 'briefcase',
        'Evento' => 'calendar',
        'Comunidad' => 'heart-handshake',
    ];
}

function news_category_icon(string $category): string
{
    $map = news_categories();
    return $map[$category] ?? 'newspaper';
}

function news_seed_if_empty(): void
{
    $pdo = db();
    if (!$pdo) return;
    $count = (int) $pdo->query('SELECT COUNT(*) FROM news_posts')->fetchColumn();
    if ($count > 0) return;

    $items = news_seed_data();
    $stmt = $pdo->prepare(
        'INSERT INTO news_posts (slug, title, excerpt, content, category, source_url, author, published_at, status, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $i => $item) {
        $slug = unique_slug($pdo, 'news_posts', $item['title']);
        $stmt->execute([
            $slug,
            $item['title'],
            $item['excerpt'],
            $item['content'],
            $item['category'],
            $item['source_url'] ?? null,
            $item['author'] ?? 'Comunicaciones Las Colinas',
            $item['published_at'],
            'published',
            $i === 0 ? 1 : 0, // first item featured
        ]);
    }
}

function news_seed_data(): array
{
    return [
        [
            'title' => 'Hospital General Las Colinas abre sus puertas en Santiago con la presencia de la vicepresidenta Raquel Peña',
            'excerpt' => 'El acto inaugural fue encabezado por la vicepresidenta Raquel Peña y el ministro de Salud Pública, Víctor Atallah, marcando el inicio operativo del centro de tercer nivel más relevante del Cibao.',
            'content' => <<<TXT
Santiago de los Caballeros. El **Hospital General Las Colinas** inauguró oficialmente sus instalaciones en la avenida Imbert, en un acto encabezado por la vicepresidenta de la República, Raquel Peña, y el ministro de Salud Pública, Víctor Atallah. La ceremonia reunió a autoridades nacionales, líderes empresariales, médicos y representantes del sector salud.

El nuevo centro de tercer nivel se posiciona como **el proyecto privado de salud más relevante de la región Norte–Noroeste**, con siete niveles de construcción, más de 7,000 metros cuadrados y áreas dedicadas a hospitalización, emergencia, cirugía, diagnóstico por imagen, maternidad y cuidados intensivos.

Durante el discurso institucional, el Director General, Dr. Rafael Sánchez Cárdenas, resaltó el compromiso del hospital con la atención humanizada, la calidad clínica y la búsqueda de la **acreditación internacional Joint Commission (JCI)**.

> "Hoy entregamos al país un hospital que combina inversión privada con vocación de servicio, pensado para los pacientes y las familias de toda la región."

El Consejo de Administración, presidido por Fulgencio Morel Ochoa, también participó del acto junto a representantes del sector salud y empresarial dominicano.
TXT,
            'category' => 'Inauguración',
            'source_url' => 'https://vicepresidencia.gob.do/inauguran-el-hospital-general-las-colinas-en-santiago-con-la-presencia-de-la-vicepresidenta-raquel-pena/',
            'published_at' => '2025-12-18 11:00:00',
        ],
        [
            'title' => 'Las Colinas inicia operaciones con 120 camas, 9 quirófanos y tecnología de última generación',
            'excerpt' => 'La institución abrió sus servicios al público con infraestructura para hospitalización, cirugía, cuidados intensivos, maternidad e imagenología avanzada.',
            'content' => <<<TXT
El Hospital General Las Colinas inició operaciones plenas con una infraestructura preparada para responder a la demanda creciente de servicios hospitalarios en la región del Cibao. Sus instalaciones incluyen:

- **120 camas** para hospitalización y cuidados especializados
- **9 quirófanos** de alta complejidad
- Unidad de Cuidados Intensivos (UCI) adulto, pediátrica y neonatal
- Maternidad con atención integral
- Imagenología diagnóstica avanzada (tomografía, resonancia, mamografía)
- Áreas especializadas de cardiodiagnóstico, hemodiálisis, oftalmología y pediatría

El hospital fortalece la oferta de atención especializada para Santiago y toda la región del Cibao bajo un modelo que integra **inversión privada con vocación de servicio**, alineado a los más altos estándares internacionales de seguridad y calidad asistencial.
TXT,
            'category' => 'Servicio',
            'source_url' => 'https://www.diariodesalud.com.do/texto-diario/mostrar/5709805/santiago-fortalece-sistema-salud-nuevo-hospital-colinas',
            'published_at' => '2025-12-18 16:30:00',
        ],
        [
            'title' => 'Dr. Rafael Sánchez Cárdenas asume la dirección general del Hospital General Las Colinas',
            'excerpt' => 'El ex ministro de Salud Pública lidera la etapa operativa del nuevo centro hospitalario de Santiago, designado por el Consejo de Directores por su trayectoria ética y técnica.',
            'content' => <<<TXT
El Consejo de Administración del Hospital General Las Colinas anunció la designación del **Dr. Rafael Sánchez Cárdenas** como Director General de la institución. Su nombramiento marca el inicio formal de la etapa operativa del nuevo centro hospitalario de Santiago.

El Dr. Sánchez Cárdenas se desempeñó como **Ministro de Salud Pública de la República Dominicana**, etapa en la que impulsó programas de fortalecimiento de la red pública y de gestión técnica del sector. Su trayectoria fue destacada por colegas y autoridades como un ejemplo de gestión ética y humana.

"Tenemos la responsabilidad de ofrecer al país una institución que sea referencia en calidad, ética y atención centrada en el paciente", expresó el directivo al asumir el cargo.

El equipo gerencial incluye las áreas de Recursos Humanos, Médica y Servicios, Finanzas, Planificación y Servicios Generales, con un Consejo de Administración presidido por **Fulgencio Morel Ochoa**.
TXT,
            'category' => 'Institucional',
            'source_url' => 'https://panorama.com.do/rafael-sanchez-cardenas-designado-director-del-hospital-general-las-colinas-un-hombre-etico/',
            'published_at' => '2025-12-19 09:00:00',
        ],
        [
            'title' => 'Las Colinas habilita servicio de Emergencias 24/7 y Emergencia Pediátrica diferenciada',
            'excerpt' => 'El hospital amplía la capacidad de respuesta del Cibao ante urgencias clínicas y traumatológicas con dos áreas independientes operativas las 24 horas.',
            'content' => <<<TXT
El Hospital General Las Colinas puso en funcionamiento su servicio de **Emergencias las 24 horas** y un **área independiente de Emergencia Pediátrica**, ampliando la capacidad de respuesta de la zona norte ante urgencias clínicas y traumatológicas.

La unidad opera con personal médico de guardia permanente, soporte de imagenología, laboratorio integrado y conexión directa a las unidades de cuidados intensivos y al bloque quirúrgico. La Emergencia Pediátrica cuenta con personal especializado en atención infantil y un entorno diseñado para niños y familias.

Para reportar una emergencia o solicitar orientación, comuníquese al **(809) 806-0444**.
TXT,
            'category' => 'Servicio',
            'source_url' => null,
            'published_at' => '2026-01-15 10:00:00',
        ],
        [
            'title' => 'Hospital General Las Colinas anuncia plan de incorporación de talento clínico para el Cibao',
            'excerpt' => 'El centro hospitalario abre proceso de reclutamiento para fortalecer las áreas de hospitalización, emergencias, quirófano, maternidad y servicios de apoyo diagnóstico.',
            'content' => <<<TXT
El Hospital General Las Colinas abrió un proceso de **reclutamiento de personal de salud** para sus áreas clínicas y de apoyo, como parte del plan de consolidación operativa de la institución en Santiago.

La convocatoria busca incorporar:

- Médicos especialistas en distintas áreas clínicas
- Licenciadas y licenciados en enfermería
- Técnicos de imagenología, laboratorio y emergencias
- Personal administrativo y de soporte

Los interesados pueden remitir su hoja de vida al correo institucional o presentarla en la oficina de Recursos Humanos del hospital. La institución reafirma su compromiso con el desarrollo profesional, la formación continua y un entorno laboral basado en el respeto y la calidad.
TXT,
            'category' => 'Empleo',
            'source_url' => 'https://resumendesalud.net/colinas-hospital-general-solicita-personal-de-salud/',
            'published_at' => '2026-02-20 09:30:00',
        ],
        [
            'title' => 'Las Colinas inaugura unidades de Hemodiálisis y Cuidado del Pie Diabético',
            'excerpt' => 'Dos servicios de alta demanda en la región se suman al portafolio asistencial del hospital, con equipamiento de última generación y protocolos internacionales.',
            'content' => <<<TXT
El Hospital General Las Colinas oficializó la apertura de sus **unidades de Hemodiálisis** y de **Cuidado de Heridas y Pie Diabético**, sumando dos servicios de alta demanda en la región del Cibao.

La unidad de Hemodiálisis opera con máquinas modernas y un equipo multidisciplinario que acompaña al paciente con enfermedad renal crónica en cada sesión, ofreciendo confort, seguridad y monitoreo permanente.

La unidad de Cuidado del Pie Diabético atiende a personas con diabetes que presentan heridas o riesgo vascular, con un enfoque preventivo y terapéutico orientado a evitar complicaciones mayores.

Ambas áreas operan con **equipamiento de última generación** y protocolos orientados a la seguridad del paciente, en línea con los estándares internacionales que el hospital se ha trazado en su ruta hacia la acreditación JCI.
TXT,
            'category' => 'Servicio',
            'source_url' => null,
            'published_at' => '2026-03-10 10:00:00',
        ],
        [
            'title' => 'ARS SEMMA incorpora al Hospital General Las Colinas a su red nacional de prestadores',
            'excerpt' => 'La Administradora de Riesgos de Salud del Magisterio amplía el acceso de los maestros afiliados a servicios hospitalarios de tercer nivel en la región Norte.',
            'content' => <<<TXT
La **Administradora de Riesgos de Salud del Magisterio (ARS SEMMA)** firmó la incorporación del Hospital General Las Colinas a su red de prestadores, junto a HEMA y la Clínica Unión Médica del Norte.

El acuerdo fue presentado por el director ejecutivo de SEMMA, Dr. Luis René Canaán Rojas, y el Director General del Hospital General Las Colinas, Dr. Rafael Sánchez Cárdenas. La alianza garantiza a los maestros afiliados acceso a servicios hospitalarios de tercer nivel en la región Norte, fortaleciendo la cobertura sanitaria del Magisterio Dominicano.

"Esta alianza permite ampliar el acceso a salud de calidad para nuestros maestros y sus familias en el Cibao", indicó el Dr. Canaán Rojas durante la firma del acuerdo.

Para consultas sobre cobertura y procedimientos cubiertos por ARS SEMMA, los afiliados pueden contactar al área de admisión del hospital.
TXT,
            'category' => 'Alianza',
            'source_url' => 'https://www.tenarenses.com/ars-semma-incorpora-a-su-red-de-prestadores-al-hospital-general-las-colinas-al-hema-y-a-la-clinica-union-medica-del-norte/',
            'published_at' => '2026-05-18 11:00:00',
        ],
        [
            'title' => 'Hospital General Las Colinas reafirma su ruta hacia la acreditación Joint Commission International',
            'excerpt' => 'La institución avanza en la implementación de protocolos de seguridad del paciente, gestión clínica y calidad asistencial alineados con los estándares de la JCI.',
            'content' => <<<TXT
El Hospital General Las Colinas reiteró su objetivo institucional de convertirse en el **primer hospital de la República Dominicana acreditado bajo los estándares de la Joint Commission International (JCI)**, organismo internacional de referencia en calidad y seguridad del paciente.

La institución avanza en la implementación de:

- Protocolos de **seguridad del paciente** en todas las áreas clínicas
- **Gestión clínica integrada** entre especialidades, diagnóstico y hospitalización
- **Calidad asistencial** con métricas, auditoría y mejora continua
- **Capacitación permanente** del personal clínico y de apoyo

La acreditación JCI no solo ratifica los más altos estándares internacionales de atención hospitalaria, sino que también posiciona al hospital y a la región del Cibao como referente de **turismo médico** en el Caribe.
TXT,
            'category' => 'Reconocimiento',
            'source_url' => null,
            'published_at' => '2026-05-22 09:00:00',
        ],
    ];
}

function news_query_published(int $limit = 0, int $offset = 0, ?string $category = null, ?string $search = null): array
{
    $pdo = db();
    if (!$pdo) return [];

    $sql = "SELECT * FROM news_posts WHERE status = 'published'";
    $params = [];
    if ($category && $category !== 'all') {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    if ($search) {
        $sql .= ' AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= ' ORDER BY is_featured DESC, published_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function news_count_published(?string $category = null, ?string $search = null): int
{
    $pdo = db();
    if (!$pdo) return 0;
    $sql = "SELECT COUNT(*) FROM news_posts WHERE status = 'published'";
    $params = [];
    if ($category && $category !== 'all') {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    if ($search) {
        $sql .= ' AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function news_by_slug(string $slug): ?array
{
    $pdo = db();
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT * FROM news_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function news_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo) return null;
    $stmt = $pdo->prepare('SELECT * FROM news_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function news_all_admin(?string $search = null): array
{
    $pdo = db();
    if (!$pdo) return [];
    $sql = 'SELECT * FROM news_posts';
    $params = [];
    if ($search) {
        $sql .= ' WHERE title LIKE ? OR excerpt LIKE ?';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like;
    }
    $sql .= ' ORDER BY published_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function news_increment_views(int $id): void
{
    $pdo = db();
    if (!$pdo) return;
    try {
        $pdo->prepare('UPDATE news_posts SET views = views + 1 WHERE id = ?')->execute([$id]);
    } catch (Throwable) { /* ignore */ }
}

function news_save(array $data, ?int $id = null): int
{
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException('Base de datos no disponible.');
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('El título es obligatorio.');
    }
    $content = trim((string) ($data['content'] ?? ''));
    if ($content === '') {
        throw new RuntimeException('El contenido es obligatorio.');
    }

    $slugBase = trim((string) ($data['slug'] ?? '')) ?: $title;
    $slug = unique_slug($pdo, 'news_posts', $slugBase, $id);

    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = mb_substr(strip_tags($content), 0, 220);
    }

    $publishedAt = trim((string) ($data['published_at'] ?? ''));
    if ($publishedAt === '') {
        $publishedAt = date('Y-m-d H:i:s');
    } else {
        // Accept datetime-local "Y-m-d\TH:i" format
        $publishedAt = str_replace('T', ' ', $publishedAt);
        if (strlen($publishedAt) === 16) $publishedAt .= ':00';
    }

    $payload = [
        'slug' => $slug,
        'title' => $title,
        'excerpt' => mb_substr($excerpt, 0, 360),
        'content' => $content,
        'category' => trim((string) ($data['category'] ?? 'Institucional')) ?: 'Institucional',
        'source_url' => trim((string) ($data['source_url'] ?? '')) ?: null,
        'author' => trim((string) ($data['author'] ?? '')) ?: 'Comunicaciones Las Colinas',
        'published_at' => $publishedAt,
        'status' => ($data['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        'is_featured' => !empty($data['is_featured']) ? 1 : 0,
    ];

    // Cover image upload
    if (!empty($_FILES['cover_image']['tmp_name'])) {
        $upload = news_handle_cover_upload($_FILES['cover_image']);
        if ($upload) {
            $payload['cover_image'] = $upload;
        }
    } elseif (!empty($data['cover_image'])) {
        $payload['cover_image'] = (string) $data['cover_image'];
    }

    if ($id) {
        $existing = news_by_id($id);
        if (!$existing) throw new RuntimeException('Noticia no encontrada.');
        if (!isset($payload['cover_image'])) {
            $payload['cover_image'] = $existing['cover_image'];
        }
        $sets = [];
        $values = [];
        foreach ($payload as $key => $value) {
            $sets[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $sql = 'UPDATE news_posts SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return $id;
    }

    if (!isset($payload['cover_image'])) {
        $payload['cover_image'] = null;
    }

    $columns = array_keys($payload);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO news_posts (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($payload));
    return (int) $pdo->lastInsertId();
}

function news_delete(int $id): bool
{
    $pdo = db();
    if (!$pdo) return false;
    $existing = news_by_id($id);
    if (!$existing) return false;
    $pdo->prepare('DELETE FROM news_posts WHERE id = ?')->execute([$id]);
    if (!empty($existing['cover_image']) && str_starts_with($existing['cover_image'], 'storage/uploads/news/')) {
        $path = __DIR__ . '/../' . $existing['cover_image'];
        if (is_file($path)) @unlink($path);
    }
    return true;
}

function news_handle_cover_upload(array $file): ?string
{
    if (!is_uploaded_file($file['tmp_name'])) return null;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 6 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera los 6 MB.');
    }
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido (jpg, png, webp).');
    }
    $dir = __DIR__ . '/../storage/uploads/news';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $filename = 'news-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    return 'storage/uploads/news/' . $filename;
}

/**
 * Minimal server-side markdown renderer for news content.
 * Supports: paragraphs, lists, blockquotes, bold, italic, links, headings.
 */
function news_render_markdown(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $lines = explode("\n", $text);
    $out = [];
    $listKind = null; $listBuffer = [];
    $paragraphBuffer = [];
    $inQuote = false; $quoteBuffer = [];

    $flushList = function () use (&$listKind, &$listBuffer, &$out) {
        if ($listKind && $listBuffer) {
            $items = '';
            foreach ($listBuffer as $li) $items .= '<li>' . news_render_inline($li) . '</li>';
            $out[] = "<$listKind>$items</$listKind>";
        }
        $listKind = null; $listBuffer = [];
    };
    $flushParagraph = function () use (&$paragraphBuffer, &$out) {
        if ($paragraphBuffer) {
            $out[] = '<p>' . news_render_inline(implode(' ', $paragraphBuffer)) . '</p>';
            $paragraphBuffer = [];
        }
    };
    $flushQuote = function () use (&$inQuote, &$quoteBuffer, &$out) {
        if ($inQuote && $quoteBuffer) {
            $out[] = '<blockquote>' . news_render_inline(implode(' ', $quoteBuffer)) . '</blockquote>';
        }
        $inQuote = false; $quoteBuffer = [];
    };

    foreach ($lines as $raw) {
        $line = rtrim($raw);
        if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m)) {
            $flushParagraph(); $flushList(); $flushQuote();
            $level = strlen($m[1]) + 1; // h2-h4
            $out[] = "<h$level>" . news_render_inline($m[2]) . "</h$level>";
            continue;
        }
        if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $m)) {
            $flushParagraph(); $flushQuote();
            if ($listKind && $listKind !== 'ul') $flushList();
            $listKind = 'ul';
            $listBuffer[] = $m[1];
            continue;
        }
        if (preg_match('/^\s*(\d+)[.)]\s+(.+)$/', $line, $m)) {
            $flushParagraph(); $flushQuote();
            if ($listKind && $listKind !== 'ol') $flushList();
            $listKind = 'ol';
            $listBuffer[] = $m[2];
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushParagraph(); $flushList();
            $inQuote = true;
            $quoteBuffer[] = $m[1];
            continue;
        }
        if (trim($line) === '') {
            $flushParagraph(); $flushList(); $flushQuote();
            continue;
        }
        $flushList(); $flushQuote();
        $paragraphBuffer[] = $line;
    }
    $flushParagraph(); $flushList(); $flushQuote();

    return implode("\n", $out);
}

function news_render_inline(string $text): string
{
    $out = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Markdown links [text](url)
    $out = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|tel:[^\s)]+|mailto:[^\s)]+|\/[^\s)]+)\)/', function ($m) {
        $href = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
        $label = $m[1];
        $external = str_starts_with($m[2], 'http');
        $rel = $external ? ' target="_blank" rel="noopener"' : '';
        return '<a href="' . $href . '"' . $rel . '>' . $label . '</a>';
    }, $out);
    // Bold **x**
    $out = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $out);
    // Italic _x_
    $out = preg_replace('/(^|\s)_([^_\n]+)_(?=[\s.,!?]|$)/', '$1<em>$2</em>', $out);
    return $out;
}

function news_format_date(string $datetime, bool $withTime = false): string
{
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($datetime);
    if (!$ts) return $datetime;
    $formatted = (int) date('j', $ts) . ' de ' . $months[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    if ($withTime) $formatted .= ' · ' . date('H:i', $ts);
    return $formatted;
}

function news_reading_time(string $content): int
{
    $words = max(1, str_word_count(strip_tags($content)));
    return max(1, (int) ceil($words / 200));
}

function news_distinct_categories(): array
{
    $pdo = db();
    if (!$pdo) return [];
    $rows = $pdo->query("SELECT category, COUNT(*) AS cnt FROM news_posts WHERE status = 'published' GROUP BY category ORDER BY cnt DESC")->fetchAll();
    return $rows ?: [];
}
