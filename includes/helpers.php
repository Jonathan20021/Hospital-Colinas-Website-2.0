<?php

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
