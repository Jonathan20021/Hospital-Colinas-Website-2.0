<?php
/**
 * Cliente del Directorio Médico Público.
 * Fetch live desde la API interna del hospital, con caché de 1 hora.
 * Si la API cae, devuelve el cache viejo (o vacío si no hay).
 */

require_once __DIR__ . '/portal_client.php';

const PORTAL_DIRECTORY_CACHE_TTL = 3600;          // 1 hora
const PORTAL_DIRECTORY_STALE_MAX = 86400 * 30;    // 30 días para fallback en caso de outage prolongado

function portal_directory_cache_path(string $key): string {
    $dir = __DIR__ . '/../storage/cache/directory';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
}

/**
 * Lee desde cache si está fresco, sino llama a la API.
 * @return array{ok:bool, data:array, stale:bool, source:string}
 */
function portal_directory_cached(string $key, string $path, array $query = []): array {
    $cachePath = portal_directory_cache_path($key);

    // Cache fresco
    if (is_file($cachePath) && (time() - filemtime($cachePath)) < PORTAL_DIRECTORY_CACHE_TTL) {
        $raw = file_get_contents($cachePath);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return ['ok' => true, 'data' => $data, 'stale' => false, 'source' => 'cache'];
        }
    }

    // Fetch live
    $res = portal_api_call('GET', $path, $query);
    if ($res['ok'] && is_array($res['data'])) {
        @file_put_contents($cachePath, json_encode($res['data']));
        @chmod($cachePath, 0644);
        return ['ok' => true, 'data' => $res['data'], 'stale' => false, 'source' => 'api'];
    }

    // Fallback al cache viejo si la API falla
    if (is_file($cachePath) && (time() - filemtime($cachePath)) < PORTAL_DIRECTORY_STALE_MAX) {
        $raw = file_get_contents($cachePath);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return ['ok' => true, 'data' => $data, 'stale' => true, 'source' => 'cache_stale'];
        }
    }

    return ['ok' => false, 'data' => [], 'stale' => true, 'source' => 'none'];
}

function portal_directory_doctors(): array {
    return portal_directory_cached('doctors', '/portal/directory');
}

function portal_directory_specialties(): array {
    return portal_directory_cached('specialties', '/portal/directory/specialties');
}

/**
 * Construye URL absoluta de foto del médico. La API devuelve photo_url
 * apuntando a la VIP interna; lo enmascaramos via proxy local para que
 * el navegador del paciente no vea la IP del hospital.
 */
function portal_directory_photo_url(?string $apiPhotoUrl): ?string {
    if (!$apiPhotoUrl) return null;
    // Extraer el path relativo (uploads/doctors/xxx.jpg) y pasarlo por el proxy local
    if (preg_match('#/api/(uploads/doctors/[^/]+\.(jpg|jpeg|png|webp))$#i', $apiPhotoUrl, $m)) {
        return '/api/portal-asset.php?p=' . urlencode($m[1]);
    }
    return $apiPhotoUrl;
}
