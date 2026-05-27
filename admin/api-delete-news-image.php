<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = admin_current_user();
    if (!$user || !admin_can('news', $user)) {
        throw new RuntimeException('No autorizado.');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $galleryToken = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['gallery_token'] ?? '');
    if ($galleryToken === '' || (!str_starts_with($galleryToken, 'gallery-') && !str_starts_with($galleryToken, 'gallery-temp-'))) {
        throw new RuntimeException('Token de galería inválido o no especificado.');
    }

    $filename = basename($data['filename'] ?? '');
    if ($filename === '' || !preg_match('/^news-media-.*?\.(jpg|jpeg|png|webp)$/i', $filename)) {
        throw new RuntimeException('Nombre de archivo inválido.');
    }

    $dir = __DIR__ . '/../storage/uploads/news/' . $galleryToken;
    $target = $dir . '/' . $filename;

    if (file_exists($target)) {
        if (@unlink($target)) {
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } else {
            throw new RuntimeException('No se pudo eliminar el archivo del servidor.');
        }
    } else {
        echo json_encode(['success' => true, 'note' => 'El archivo no existía en el servidor.'], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
