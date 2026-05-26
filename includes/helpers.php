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
        $base = ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') ? '/' : $dir . '/';
    }
    if ($path === '') {
        return $base;
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
