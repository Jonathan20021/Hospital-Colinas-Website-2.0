<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

if (!empty($_POST['website'] ?? '')) {
    echo json_encode(['ok' => true, 'message' => 'Solicitud recibida.']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$specialty = trim((string) ($_POST['specialty'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $phone === '' || $date === '' || $specialty === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Completa los campos requeridos.']);
    exit;
}

$storageDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

$file = $storageDir . DIRECTORY_SEPARATOR . 'appointments.csv';
$isNew = !file_exists($file);
$handle = fopen($file, 'ab');

if (!$handle) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo registrar la solicitud.']);
    exit;
}

if ($isNew) {
    fputcsv($handle, ['created_at', 'name', 'phone', 'date', 'specialty', 'message', 'ip']);
}

fputcsv($handle, [
    date('c'),
    $name,
    $phone,
    $date,
    $specialty,
    $message,
    $_SERVER['REMOTE_ADDR'] ?? '',
]);

fclose($handle);

echo json_encode(['ok' => true, 'message' => 'Solicitud enviada. Te contactaremos para confirmar tu cita.']);

