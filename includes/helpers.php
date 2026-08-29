<?php

// Zona horaria de toda la app pública y los portales: RD = GMT-4 (sin horario de verano).
date_default_timezone_set('America/Santo_Domingo');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function service_count(array $services): int
{
    return array_reduce($services, static fn (int $carry, array $service): int => $carry + count($service['items']), 0);
}

function search_key(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        // Si el script vive en /admin/*, /portal/* o /portal-medico/*, la base
        // "lógica" del sitio público sigue siendo la raíz (un nivel arriba).
        // (portal-medico antes que portal para que el ancla case el segmento completo.)
        if (preg_match('#^(.*?)/(admin|portal-medico|portal)$#', $dir, $m)) {
            $dir = $m[1] === '' ? '/' : $m[1];
        }
        $base = ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') ? '/' : $dir . '/';
    }
    if ($path === '') {
        return $base;
    }
    // Devolver tal cual URLs absolutas, data URLs, mailto, tel, etc.
    if (preg_match('#^(?:[a-z][a-z0-9+\-.]*:|//)#i', $path)) {
        return $path;
    }
    if ($path[0] === '#' || $path[0] === '?') {
        return $base . $path;
    }
    return $base . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'colinashospital.com';
    return $scheme . '://' . $host . base_url($path);
}

/**
 * Valida un destino de redirección (post-login). Devuelve $next solo si es una
 * ruta INTERNA del propio sitio; si no, devuelve $fallback. Previene "open
 * redirect" (https://malicioso, //malicioso.com, javascript:, data:, …) y la
 * inyección de cabeceras por saltos de línea.
 */
function safe_next($next, string $fallback): string
{
    $next = trim((string)$next);
    if ($next === '') return $fallback;
    if (preg_match('/[\x00-\x1f\x7f]/', $next)) return $fallback;        // control / CR-LF (header injection)
    if (strpos($next, '\\') !== false) return $fallback;                 // backslash (algunos navegadores lo tratan como "/")
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $next)) return $fallback;  // esquema: javascript:, http:, data:, mailto:…
    if (strncmp($next, '//', 2) === 0) return $fallback;                 // URL relativa al protocolo (//host)
    return $next;
}

function canonical_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'colinashospital.com';
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $scheme . '://' . $host . $uri;
}

function content_slug(string $value): string
{
    $value = trim($value);
    $value = strtr($value, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
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

    return $value !== '' ? $value : 'pagina';
}

function service_url(string $name): string
{
    return base_url('servicios/' . content_slug($name));
}

/**
 * Atributos width/height con las dimensiones REALES del archivo, para que el
 * navegador pueda reservar el hueco antes de descargar la imagen y no haya
 * salto de maquetación (CLS). Devuelve '' si no se pueden leer.
 *
 * Solo hace falta en las imágenes cuyo tamaño depende de la proporción de la
 * propia imagen: el logo (height fijo + width:auto) y los logos de las
 * aseguradoras (width/height auto dentro de un max-height). Las que llevan
 * object-fit:cover dentro de una caja de tamaño fijo por CSS NO lo necesitan:
 * ahí el CSS ya reserva el hueco y el atributo no aporta nada.
 *
 * Se cachea por request: son pocos archivos y siempre los mismos.
 */
function img_dimensions(string $relativePath): string
{
    static $cache = [];

    $rel = ltrim($relativePath, '/');
    if (array_key_exists($rel, $cache)) {
        return $cache[$rel];
    }

    $abs = __DIR__ . '/../' . $rel;
    if (!is_file($abs)) {
        // Algunas rutas llegan ya en forma de URL: $insurersDir vale
        // 'assets/LOGO%20ASEGURADORAS/', que en disco no existe con el %20.
        $decoded = __DIR__ . '/../' . rawurldecode($rel);
        if (is_file($decoded)) {
            $abs = $decoded;
        }
    }
    $out = '';

    if (is_file($abs)) {
        if (strtolower((string) pathinfo($abs, PATHINFO_EXTENSION)) === 'svg') {
            $out = svg_dimensions($abs);
        } else {
            $size = @getimagesize($abs);
            if ($size && (int) $size[0] > 0 && (int) $size[1] > 0) {
                $out = ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
            }
        }
    }

    return $cache[$rel] = $out;
}

/**
 * Dimensiones de un SVG: getimagesize() no los entiende. Se leen del propio
 * <svg> (width/height en px) y, si no los trae o vienen en %, del viewBox.
 */
function svg_dimensions(string $absolutePath): string
{
    $head = @file_get_contents($absolutePath, false, null, 0, 8192);
    if ($head === false || !preg_match('/<svg\b[^>]*>/i', $head, $tag)) {
        return '';
    }
    $svg = $tag[0];

    $w = preg_match('/\bwidth="([0-9.]+)(?:px)?"/i', $svg, $mw) ? (float) $mw[1] : 0.0;
    $h = preg_match('/\bheight="([0-9.]+)(?:px)?"/i', $svg, $mh) ? (float) $mh[1] : 0.0;

    if ($w <= 0 || $h <= 0) {
        // viewBox="minX minY ancho alto"
        if (preg_match('/\bviewBox="\s*[-0-9.eE]+[\s,]+[-0-9.eE]+[\s,]+([-0-9.eE]+)[\s,]+([-0-9.eE]+)/i', $svg, $mv)) {
            $w = (float) $mv[1];
            $h = (float) $mv[2];
        }
    }

    if ($w <= 0 || $h <= 0) {
        return '';
    }

    return ' width="' . (int) round($w) . '" height="' . (int) round($h) . '"';
}
