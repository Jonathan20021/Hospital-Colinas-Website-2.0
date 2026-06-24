<?php
/**
 * Proxy público del catálogo de estudios (imágenes/laboratorio) para el
 * formulario de solicitud. Reenvía a la API interna y cachea 5 min.
 * El catálogo NO contiene PHI (solo nombres de estudios + códigos).
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$res = portal_api_call('GET', '/portal/study-catalog', []);
http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode(['success' => false, 'message' => 'Catálogo no disponible.']);
