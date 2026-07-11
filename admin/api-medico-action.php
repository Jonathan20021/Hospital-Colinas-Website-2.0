<?php
/**
 * Proxy server-side desde el admin de la landing hacia la API del hospital.
 * Maneja: upload foto, eliminar foto, actualizar campos del directorio.
 *
 * Requiere:
 *   - Sesión admin válida (require_admin_permission)
 *   - HOSPITAL_API_KEY definida en includes/config.local.php (X-API-Key header)
 *   - CSRF token válido
 */
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/portal_client.php';

require_admin_permission('doctors');

// Si el POST excede post_max_size, PHP descarta $_POST y $_FILES enteros: sin este
// guard el CSRF fallaría y el admin vería una pantalla en blanco sin explicación.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['admin_flash'] = [
        'type'    => 'danger',
        'message' => 'La imagen es demasiado pesada para el servidor (límite: ' . ini_get('post_max_size')
                   . '). Usa una foto más liviana.',
    ];
    header('Location: medicos.php');
    exit;
}

verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$apiKey = defined('HOSPITAL_API_KEY') ? HOSPITAL_API_KEY : '';
if ($apiKey === '') {
    $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'HOSPITAL_API_KEY no está definida en config.local.php'];
    header('Location: medicos.php');
    exit;
}

function flash_and_redirect(string $type, string $message): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    // En caso de error NO usamos el ancla: el navegador saltaría a la ficha del
    // médico y el aviso (que se pinta arriba del listado) quedaría fuera de vista.
    $anchor = $type === 'success' ? '#doc-' . (int)($_POST['id'] ?? 0) : '';
    header('Location: medicos.php' . $anchor);
    exit;
}

/** Traduce el código de error de PHP a algo que el admin pueda accionar. */
function upload_error_message(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'La imagen supera el tamaño máximo que acepta el servidor ('
                 . ini_get('upload_max_filesize') . '). Usa una foto más liviana.';
        case UPLOAD_ERR_PARTIAL:
            return 'La imagen se subió incompleta. Vuelve a intentarlo.';
        case UPLOAD_ERR_NO_FILE:
            return 'No seleccionaste ninguna imagen.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'El servidor no pudo guardar la imagen temporalmente. Avisa a soporte.';
        default:
            return 'No se recibió la imagen (error ' . $code . ').';
    }
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if (!$id) {
    flash_and_redirect('danger', 'ID inválido.');
}

function hospital_api_call_admin(string $method, string $path, array $payload = [], array $file = []): array {
    $apiKey = HOSPITAL_API_KEY;
    $url = portal_api_base() . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'X-API-Key: ' . $apiKey];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
        CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
    ];

    if (!empty($file)) {
        // Multipart upload
        $opts[CURLOPT_POSTFIELDS] = ['photo' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])];
    } elseif (!empty($payload)) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'message' => 'Error de conexión: ' . $err, 'data' => null];
    }

    $decoded = json_decode($raw, true);
    return [
        'ok'      => $status >= 200 && $status < 300 && !empty($decoded['success']),
        'status'  => $status,
        'message' => $decoded['message'] ?? null,
        'errors'  => $decoded['errors']  ?? null,
        'data'    => $decoded['data']    ?? null,
    ];
}

function bust_directory_cache(): void {
    $cacheDir = __DIR__ . '/../storage/cache/directory';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.json') as $f) @unlink($f);
    }
}

if ($action === 'upload_photo') {
    if (empty($_FILES['photo'])) {
        flash_and_redirect('danger', 'No se recibió la imagen.');
    }
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        flash_and_redirect('danger', upload_error_message((int)$_FILES['photo']['error']));
    }
    $r = hospital_api_call_admin('POST', '/doctors/' . $id . '/photo', [], $_FILES['photo']);
    bust_directory_cache();
    if ($r['ok']) {
        flash_and_redirect('success', 'Foto actualizada correctamente.');
    } else {
        flash_and_redirect('danger', 'No se pudo subir: ' . ($r['message'] ?? 'error desconocido'));
    }
}

if ($action === 'delete_photo') {
    $r = hospital_api_call_admin('DELETE', '/doctors/' . $id . '/photo');
    bust_directory_cache();
    if ($r['ok']) {
        flash_and_redirect('success', 'Foto eliminada.');
    } else {
        flash_and_redirect('danger', 'No se pudo eliminar: ' . ($r['message'] ?? 'error'));
    }
}

if ($action === 'toggle_featured') {
    // Marca/quita "Destacado en portada" con un solo clic (PUT parcial, igual contrato que update_directory).
    $featured = !empty($_POST['featured']) ? 1 : 0;
    $r = hospital_api_call_admin('PUT', '/doctors/' . $id, ['is_featured' => $featured]);
    bust_directory_cache();
    if ($r['ok']) {
        flash_and_redirect('success', $featured ? 'Médico destacado en la portada.' : 'Médico quitado de la portada.');
    } else {
        flash_and_redirect('danger', 'No se pudo actualizar: ' . ($r['message'] ?? 'error'));
    }
}

if ($action === 'update_directory') {
    $payload = [];
    foreach (['biography', 'education', 'languages', 'services', 'insurances', 'associations'] as $f) {
        if (array_key_exists($f, $_POST)) {
            $payload[$f] = trim((string)$_POST[$f]);
        }
    }
    foreach (['is_featured', 'show_in_directory'] as $b) {
        $payload[$b] = isset($_POST[$b]) ? 1 : 0;
    }
    if (isset($_POST['sort_order'])) {
        $payload['sort_order'] = (int)$_POST['sort_order'];
    }

    $r = hospital_api_call_admin('PUT', '/doctors/' . $id, $payload);
    bust_directory_cache();
    if ($r['ok']) {
        flash_and_redirect('success', 'Datos actualizados.');
    } else {
        flash_and_redirect('danger', 'No se pudo actualizar: ' . ($r['message'] ?? 'error'));
    }
}

flash_and_redirect('danger', 'Acción desconocida.');
