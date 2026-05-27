<?php
/**
 * Proxy de assets del directorio (fotos de médicos).
 * El navegador pide la foto a este endpoint; nosotros la traemos
 * server-to-server desde la VIP y la servimos con cache HTTP.
 *
 * Uso: <img src="/api/portal-asset.php?p=uploads/doctors/doctor-12-abcd1234.jpg">
 *
 * Validamos path para evitar SSRF: solo permitimos uploads/doctors/*.
 */

require_once __DIR__ . '/../includes/portal_client.php';

$path = $_GET['p'] ?? '';
$path = ltrim((string)$path, '/');

if (!preg_match('#^uploads/doctors/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#i', $path)) {
    http_response_code(400);
    exit('Invalid path');
}

// Cache local: ./storage/cache/doctor-photos/
$cacheDir = __DIR__ . '/../storage/cache/doctor-photos';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
$cacheFile = $cacheDir . '/' . basename($path);
$cacheTtl  = 3600 * 24; // 24h

$serve = function (string $file): void {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
    readfile($file);
    exit;
};

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $serve($cacheFile);
}

// Pedir a la VIP. portal_api_base() devuelve algo como https://VIP:8443/api/v1
// La foto está en https://VIP:8443/api/uploads/doctors/xxx (sin /v1)
$base = portal_api_base();
$assetBase = preg_replace('#/v1/?$#', '', $base);
$url = $assetBase . '/' . $path;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
    CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
]);
$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $status !== 200) {
    if (is_file($cacheFile)) { $serve($cacheFile); } // stale cache si la API falla
    http_response_code(502);
    exit('Asset upstream error');
}

@file_put_contents($cacheFile, $body);
@chmod($cacheFile, 0644);
$serve($cacheFile);
