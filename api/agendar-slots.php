<?php
/**
 * Proxy GET de slots de un médico para el wizard de agendamiento como invitado.
 * Endpoint público (sin auth), igual que el endpoint upstream del hospital.
 */
require_once __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$from     = $_GET['date_from'] ?? date('Y-m-d');
$to       = $_GET['date_to']   ?? date('Y-m-d', strtotime('+30 days'));
$slot     = (int)($_GET['slot_minutes'] ?? 30);

if (!$doctorId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'doctor_id requerido.']);
    exit;
}

$res = portal_api_call('GET', "/portal/doctors/$doctorId/slots", [
    'date_from'    => $from,
    'date_to'      => $to,
    'slot_minutes' => $slot,
]);
http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode(['success' => false]);
