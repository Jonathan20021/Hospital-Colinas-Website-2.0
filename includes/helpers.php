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

/**
 * Anchos de los derivados que genera tools/optimize-images.php.
 * Si se cambia ahí, cambiar aquí.
 */
const IMG_ANCHOS_OPT = [360, 720, 768, 1280, 1920];

/**
 * Devuelve los derivados optimizados de una foto, o [] si no se han generado.
 * Estructura: ['webp' => [ancho => rutaRelativa], 'jpg' => [...]].
 */
function optimized_variants(string $relativePath): array
{
    static $cache = [];
    if (array_key_exists($relativePath, $cache)) {
        return $cache[$relativePath];
    }

    $base = pathinfo($relativePath, PATHINFO_FILENAME);
    $dirRel = 'assets/site/assets/opt';
    // El respaldo conserva el formato del original: PNG si lo era (el logo
    // necesita la transparencia), JPEG para las fotos.
    $fallbackFmt = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
    $out = ['webp' => [], $fallbackFmt => [], 'fallback' => $fallbackFmt];

    foreach (IMG_ANCHOS_OPT as $w) {
        foreach (['webp', $fallbackFmt] as $fmt) {
            $rel = "$dirRel/$base-$w.$fmt";
            if (is_file(__DIR__ . '/../' . $rel)) {
                $out[$fmt][$w] = $rel;
            }
        }
    }

    return $cache[$relativePath] = $out;
}

/**
 * <picture> con WebP + respaldo JPEG y srcset por ancho, para no mandarle a un
 * telefono la foto de 11 MB pensada para escritorio.
 *
 * Si todavía no se han generado los derivados (tools/optimize-images.php),
 * degrada solo: devuelve un <img> normal apuntando al original.
 *
 * @param array $opts class, sizes, loading, fetchpriority, decoding, id, style
 */
function picture_tag(string $relativePath, string $alt, array $opts = []): string
{
    $variants = optimized_variants($relativePath);
    $sizes    = (string) ($opts['sizes'] ?? '100vw');

    $attrs = '';
    foreach (['id', 'class', 'style', 'loading', 'fetchpriority', 'decoding'] as $k) {
        if (!empty($opts[$k])) {
            $attrs .= ' ' . $k . '="' . e((string) $opts[$k]) . '"';
        }
    }

    $fb = $variants['fallback'] ?? 'jpg';

    // Sin derivados: el original, tal cual estaba antes.
    if (empty($variants[$fb])) {
        $dims = img_dimensions($relativePath);
        return '<img src="' . e(base_url($relativePath)) . '"' . $dims . ' alt="' . e($alt) . '"' . $attrs . '>';
    }

    $srcset = function (array $mapa): string {
        $parts = [];
        foreach ($mapa as $w => $rel) {
            $parts[] = e(base_url($rel)) . ' ' . (int) $w . 'w';
        }
        return implode(', ', $parts);
    };

    // El <img> apunta al JPEG intermedio: es el respaldo si no hay WebP.
    $fallbacks = $variants[$fb];
    $fallback  = $fallbacks[1280] ?? $fallbacks[720] ?? end($fallbacks);
    // Se mide el DERIVADO: al redimensionar, la proporcion redondea y no es
    // exactamente la del original (4933x1783 -> 360x130). Si se reservase con
    // la del original, la caja bailaria 1px al cargar.
    $dims = img_dimensions($fallback);

    $html  = '<picture>';
    if ($variants['webp']) {
        $html .= '<source type="image/webp" srcset="' . $srcset($variants['webp']) . '" sizes="' . e($sizes) . '">';
    }
    $html .= '<img src="' . e(base_url($fallback)) . '"'
        . ' srcset="' . $srcset($fallbacks) . '"'
        . ' sizes="' . e($sizes) . '"'
        . $dims
        . ' alt="' . e($alt) . '"' . $attrs . '>';
    $html .= '</picture>';

    return $html;
}

/**
 * Ruta de un derivado concreto (ancho + formato), o el original si no existe.
 * Para sitios donde no cabe un <picture>: og:image, JSON-LD, poster de video.
 */
function optimized_src(string $relativePath, int $width = 1280, ?string $fmt = null): string
{
    $v = optimized_variants($relativePath);
    $fmt = $fmt ?? ($v['fallback'] ?? 'jpg');
    return $v[$fmt][$width] ?? $relativePath;
}

/**
 * <link rel="preload"> de una imagen responsiva. Sin imagesrcset el navegador
 * precargaria el original a tamaño completo (el hero eran 11 MB), que ademas
 * no es el archivo que acabaria usando.
 */
function preload_image_tag(string $relativePath, string $sizes = '100vw'): string
{
    $v = optimized_variants($relativePath);
    if (!$v['webp']) {
        return '<link rel="preload" as="image" href="' . e(base_url($relativePath)) . '" fetchpriority="high">';
    }

    $srcset = [];
    foreach ($v['webp'] as $w => $rel) {
        $srcset[] = e(base_url($rel)) . ' ' . (int) $w . 'w';
    }
    $href = $v['webp'][1280] ?? end($v['webp']);

    // Sin fetchpriority: la prioridad alta es para el logo, que es el elemento
    // LCP real. El hero se precarga igual, pero por detras.
    return '<link rel="preload" as="image" type="image/webp"'
        . ' href="' . e(base_url($href)) . '"'
        . ' imagesrcset="' . implode(', ', $srcset) . '"'
        . ' imagesizes="' . e($sizes) . '">';
}


/**
 * Atributos src/srcset/sizes/width/height de una foto, usando los derivados,
 * SIN convertir el <img> en <picture>. Para banners y cabeceras donde no
 * compensa tocar la estructura: se pierde el WebP pero se gana casi todo el
 * peso (el hero pasa de 11 MB a 106 KB en movil).
 *
 * Uso: <img<?= img_srcset_attrs($assets['hero'], '100vw') ?> alt="...">
 */
function img_srcset_attrs(string $relativePath, string $sizes = '100vw'): string
{
    $v  = optimized_variants($relativePath);
    $fb = $v['fallback'] ?? 'jpg';

    if (empty($v[$fb])) {
        return ' src="' . e(base_url($relativePath)) . '"' . img_dimensions($relativePath);
    }

    $mapa = $v[$fb];
    $src  = $mapa[1280] ?? $mapa[720] ?? end($mapa);

    $srcset = [];
    foreach ($mapa as $w => $rel) {
        $srcset[] = e(base_url($rel)) . ' ' . (int) $w . 'w';
    }

    return ' src="' . e(base_url($src)) . '"'
        . ' srcset="' . implode(', ', $srcset) . '"'
        . ' sizes="' . e($sizes) . '"'
        . img_dimensions($src);
}

/**
 * Preload del logo del header. Segun el trace de Lighthouse es el UNICO
 * candidato a LCP del sitio (IMG.brand-logo), asi que es el que merece la
 * prioridad alta: lo descubre tarde el navegador porque vive en el <header>
 * y solo se resuelve despues del CSS.
 */
function preload_logo_tag(string $relativePath = 'assets/site/logo.png'): string
{
    $v = optimized_variants($relativePath);
    $fb = $v['fallback'] ?? 'png';

    if (empty($v[$fb])) {
        return '<link rel="preload" as="image" href="' . e(base_url($relativePath)) . '" fetchpriority="high">';
    }

    $srcset = [];
    foreach ($v[$fb] as $w => $rel) {
        $srcset[] = e(base_url($rel)) . ' ' . (int) $w . 'w';
    }
    $href = $v[$fb][720] ?? end($v[$fb]);

    return '<link rel="preload" as="image"'
        . ' href="' . e(base_url($href)) . '"'
        . ' imagesrcset="' . implode(', ', $srcset) . '"'
        . ' imagesizes="360px"'
        . ' fetchpriority="high">';
}
/**
 * Activa la compresión de PHP para las respuestas de texto (HTML, XML).
 *
 * POR QUE: el mod_deflate del hosting está mal afinado y produce gzip casi al
 * doble de tamaño de lo normal — el HTML del home sale a 136 KB cuando el mismo
 * contenido comprimido en condiciones son 32 KB. `DeflateCompressionLevel` y
 * `DeflateWindowSize` son directivas de server/vhost y NO se pueden poner en un
 * .htaccess, así que se comprime desde PHP: Apache respeta el Content-Encoding
 * que ya viene puesto y no vuelve a comprimir.
 *
 * Se llama de forma EXPLÍCITA desde los layouts y desde las páginas con <head>
 * propio. Nunca desde helpers.php a secas: los endpoints que sirven PDF o
 * imágenes (receta-pdf, portal-asset, documento…) fijan su propio
 * Content-Length y comprimir ahí rompería la descarga.
 *
 * Usa zlib.output_compression, que comprime al vuelo, y no ob_gzhandler, que
 * retendría la página entera y empeoraría el TTFB.
 */
function enable_html_compression(): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    if (PHP_SAPI === 'cli' || headers_sent() || !extension_loaded('zlib')) {
        return;
    }
    if (ini_get('zlib.output_compression')) {
        return; // ya viene activa del php.ini
    }
    $acepta = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    if (!str_contains($acepta, 'gzip')) {
        return;
    }

    @ini_set('zlib.output_compression_level', '6');
    @ini_set('zlib.output_compression', '1');
}
