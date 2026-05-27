<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = admin_current_user();
    if (!$user) {
        throw new RuntimeException('No autorizado.');
    }

    if (empty($_FILES['image'])) {
        throw new RuntimeException('No se recibió ninguna imagen.');
    }

    $galleryToken = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['gallery_token'] ?? '');
    if ($galleryToken === '' || (!str_starts_with($galleryToken, 'gallery-') && !str_starts_with($galleryToken, 'gallery-temp-'))) {
        throw new RuntimeException('Token de galería inválido o no especificado.');
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir la imagen.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera los 5 MB.');
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido (jpg, png, webp).');
    }

    $dir = __DIR__ . '/../storage/uploads/news/' . $galleryToken;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $filename = 'news-media-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    $url = 'storage/uploads/news/' . $galleryToken . '/' . $filename;

    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
