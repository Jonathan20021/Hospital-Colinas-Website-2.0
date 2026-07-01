<?php
/**
 * Receptor same-origin del beacon de analítica del sitio público (Auditoría Web).
 *
 * El navegador envía aquí un "pageview" (via navigator.sendBeacon); este script
 * añade la IP real + el User-Agent del visitante y lo reenvía server-to-server a
 * la ingesta interna de JENOFONTE (POST /track). El navegador NUNCA ve la URL
 * interna. Fire-and-forget: responde 204 de inmediato y nunca bloquea la página.
 *
 * Solo acepta mismo origen + rate-limit por IP. No maneja PHI (solo la ruta y
 * metadatos de navegación). Ver includes/portal_client.php (reenvío) y, en
 * JENOFONTE, api/track_ingest.php (escritura en web_activity_log).
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';

// Solo mismo origen: si llega un Origin/Referer de OTRO host, se descarta.
$src = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($src !== '') {
    $host = parse_url($src, PHP_URL_HOST);
    $self = $_SERVER['HTTP_HOST'] ?? '';
    if ($host && $self && strcasecmp($host, $self) !== 0) { http_response_code(204); exit; }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(204); exit; }

$ip = portal_client_real_ip();

// Rate-limit ligero por IP (evita floods de telemetría).
$rateDir = dirname(__DIR__) . '/storage/track-rate';
if (!is_dir($rateDir)) @mkdir($rateDir, 0775, true);
$rateFile = $rateDir . '/' . md5($ip !== '' ? $ip : 'x') . '.json';
$now = time(); $win = 60; $max = 90; // 90 pageviews/min por IP
$bucket = ['t' => $now, 'n' => 0];
if (is_file($rateFile)) {
    $st = json_decode((string) @file_get_contents($rateFile), true);
    if (is_array($st) && ($now - (int) ($st['t'] ?? 0)) < $win) $bucket = $st;
}
$bucket['n'] = (int) $bucket['n'] + 1;
@file_put_contents($rateFile, json_encode($bucket));
if ($bucket['n'] > $max) { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw !== false ? $raw : '', true);
if (!is_array($in)) { http_response_code(204); exit; }

$payload = [
    'v' => isset($in['v']) ? substr((string) $in['v'], 0, 64) : null,
    'events' => [[
        'path'  => substr((string) ($in['path']  ?? '/'), 0, 255),
        'ref'   => substr((string) ($in['ref']   ?? ''),  0, 255),
        'title' => substr((string) ($in['title'] ?? ''),  0, 160),
        'sw'    => (int) ($in['sw'] ?? 0),
        'sh'    => (int) ($in['sh'] ?? 0),
        'tz'    => substr((string) ($in['tz']   ?? ''), 0, 40),
        'lang'  => substr((string) ($in['lang'] ?? ''), 0, 12),
    ]],
];

// Reenvío a la API interna con timeout corto (no bloquea; el navegador ya siguió).
if (function_exists('portal_api_base')) {
    $ch = curl_init(portal_api_base() . '/track');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], portal_client_fwd_headers()),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
        CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}

http_response_code(204);
