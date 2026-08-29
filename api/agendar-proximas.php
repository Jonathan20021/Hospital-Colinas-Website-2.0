<?php
/**
 * Próxima fecha con cupo de VARIOS médicos a la vez.
 *
 * En el paso 2 el paciente elegía entre hasta 12 especialistas sin ningún dato
 * para decidir: los veía en orden arbitrario y tenía que entrar en cada uno para
 * saber quién lo podía atender antes. Esto devuelve, por médico, el primer día
 * con hueco y cuántas horas tiene.
 *
 * Va en UNA sola petición del navegador: `portal_api_multi` (curl_multi) hace
 * las N llamadas al hospital en paralelo, así que tarda lo que la más lenta y no
 * la suma. Ventana corta (21 días) porque solo interesa el PRIMER hueco.
 *
 * Solo devuelve fechas y conteos — ningún dato personal del médico ni del
 * paciente. Endpoint público, igual que `agendar-slots.php`.
 */
require_once __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
// El navegador lo reutiliza si el paciente va y viene entre especialidades.
header('Cache-Control: private, max-age=120');

const AGP_MAX_MEDICOS = 20;   // anestesiología, la más grande, tiene 12
const AGP_DIAS        = 21;

/* Limite por IP. Sin esto el endpoint es un amplificador: UNA peticion se
   convierte en hasta 20 llamadas al hospital, asi que un flujo modesto de
   peticiones bastaria para castigar a JENOFONTE. Mismo patron que api/track.php.
   30/min sobra: el cliente memoriza por especialidad y un paciente hace 3 o 4. */
$ip = function_exists('portal_client_real_ip') ? portal_client_real_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
$dirLimite = dirname(__DIR__) . '/storage/agendar-rate';
if (!is_dir($dirLimite)) { @mkdir($dirLimite, 0775, true); }
$archivo = $dirLimite . '/' . md5($ip !== '' ? $ip : 'x') . '.json';
$ahora = time(); $ventana = 60; $tope = 30;
$cubo = ['t' => $ahora, 'n' => 0];
if (is_file($archivo)) {
    $st = json_decode((string) @file_get_contents($archivo), true);
    if (is_array($st) && ($ahora - (int) ($st['t'] ?? 0)) < $ventana) { $cubo = $st; }
}
$cubo['n'] = (int) $cubo['n'] + 1;
@file_put_contents($archivo, json_encode($cubo));
if ($cubo['n'] > $tope) {
    // El cliente esconde las lineas al ver success:false; el paso 2 sigue igual.
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Demasiadas consultas seguidas.']);
    exit;
}

$crudos = (string) ($_GET['doctor_ids'] ?? '');
$ids = [];
foreach (explode(',', $crudos) as $trozo) {
    $id = (int) trim($trozo);
    if ($id > 0 && !in_array($id, $ids, true)) { $ids[] = $id; }
}
$ids = array_slice($ids, 0, AGP_MAX_MEDICOS);

if (!$ids) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'doctor_ids requerido.']);
    exit;
}

$desde = date('Y-m-d');
$hasta = date('Y-m-d', strtotime('+' . AGP_DIAS . ' days'));

$llamadas = [];
foreach ($ids as $id) {
    $llamadas[$id] = ['GET', "/portal/doctors/$id/slots", [
        'date_from'    => $desde,
        'date_to'      => $hasta,
        'slot_minutes' => 30,
    ]];
}

$respuestas = portal_api_multi($llamadas);

$salida = [];
foreach ($ids as $id) {
    $r = $respuestas[$id] ?? null;
    // Si la llamada de UN médico falla, ese se queda sin dato y los demás siguen:
    // esto es información de apoyo, no puede tumbar el paso 2.
    if (!$r || empty($r['ok']) || !is_array($r['data']['days'] ?? null)) {
        continue;
    }

    $dias = [];
    foreach ($r['data']['days'] as $fecha => $horas) {
        if (is_array($horas) && $horas && $fecha >= $desde) { $dias[$fecha] = count($horas); }
    }
    if (!$dias) { $salida[$id] = null; continue; }   // null = buscado y sin cupo

    ksort($dias);
    $primera = array_key_first($dias);
    $salida[$id] = ['date' => $primera, 'slots' => $dias[$primera]];
}

echo json_encode([
    'success' => true,
    'data'    => ['doctors' => (object) $salida, 'days' => AGP_DIAS],
], JSON_UNESCAPED_UNICODE);
