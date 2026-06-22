<?php
/**
 * Asistente de redacción clínica ("escriba") para la consulta del médico.
 *
 * El navegador envía —mismo origen— el TEXTO que el médico escribió en un campo
 * (motivo, diagnóstico, plan, etc.) + contexto NO identificante (edad, sexo,
 * especialidad) y pide reescribirlo en lenguaje clínico formal, o generar un
 * resumen/plan. La API key de OpenAI vive SOLO en el servidor (config.local.php).
 *
 * Seguridad: sesión del médico + CSRF; límite de uso por sesión; solo POST.
 * Privacidad: jamás recibe nombre, cédula ni fecha de nacimiento. No inventa
 * datos clínicos: solo reescribe lo que el médico ya redactó.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

header('Content-Type: application/json; charset=utf-8');

function scribe_out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    scribe_out(405, ['success' => false, 'message' => 'Método no permitido.']);
}

doctor_csrf_check();
if (!doctor_is_logged_in()) {
    scribe_out(401, ['success' => false, 'message' => 'Sesión del médico expirada. Recarga la página.']);
}

$key = defined('OPENAI_API_KEY') ? trim((string) OPENAI_API_KEY) : '';
if ($key === '') {
    scribe_out(503, ['success' => false, 'message' => 'El asistente de redacción aún no está configurado en el servidor.']);
}
$model = defined('OPENAI_SCRIBE_MODEL') ? trim((string) OPENAI_SCRIBE_MODEL)
       : (defined('OPENAI_IMAGING_MODEL') ? trim((string) OPENAI_IMAGING_MODEL) : 'gpt-4.1-mini');

// ── Límite de uso por sesión (backstop de costo) ─────────────────────────────
doctor_portal_session_start();
$now = time();
$WIN = 600;   // 10 min
$MAX = 60;
$calls = array_values(array_filter(
    (array) ($_SESSION['ai_scribe_calls'] ?? []),
    static fn ($t) => is_int($t) && $t > $now - $WIN
));
if (count($calls) >= $MAX) {
    scribe_out(429, ['success' => false, 'message' => 'Demasiadas solicitudes seguidas. Espera un momento.']);
}

// ── Entrada ──────────────────────────────────────────────────────────────────
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$mode    = (string) ($payload['mode'] ?? 'improve');
if (!in_array($mode, ['improve', 'summary'], true)) $mode = 'improve';
$field   = preg_replace('/[^a-z_]/', '', (string) ($payload['field'] ?? 'notes'));
$text    = trim((string) ($payload['text'] ?? ''));
$fieldsIn = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
$ctxIn   = is_array($payload['context'] ?? null) ? $payload['context'] : [];

// Contexto NO identificante (lista blanca estricta).
$sex = strtoupper(substr(preg_replace('/[^MFOmfo]/', '', (string) ($ctxIn['sex'] ?? '')), 0, 1));
$age = (int) ($ctxIn['age'] ?? 0); if ($age < 0 || $age > 120) $age = 0;
$specialty = substr(preg_replace('/[^\p{L}\p{N}\s.\-\/]/u', '', (string) ($ctxIn['specialty'] ?? '')), 0, 60);
$ctxParts = [];
if ($specialty) $ctxParts[] = 'especialidad: ' . $specialty;
if ($sex === 'M') $ctxParts[] = 'sexo masculino'; elseif ($sex === 'F') $ctxParts[] = 'sexo femenino';
if ($age > 0) $ctxParts[] = 'edad ' . $age . ' años';
$ctxLine = $ctxParts ? ('Contexto (no identificante): ' . implode(', ', $ctxParts) . '.') : '';

$fieldLabels = [
    'chief_complaint' => 'motivo de consulta',
    'diagnosis'       => 'diagnóstico',
    'prescription'    => 'receta / indicaciones de medicamentos',
    'lab_orders'      => 'órdenes de laboratorio',
    'imaging_orders'  => 'órdenes de imágenes',
    'procedures'      => 'procedimientos / interconsultas',
    'notes'           => 'notas / evolución / plan',
];

$fieldGuides = [
    'chief_complaint' => 'Redacta el motivo como una narrativa breve y clara.',
    'diagnosis'       => 'Redáctalo de forma clínica y formal; conserva los códigos CIE-10 si aparecen.',
    'prescription'    => 'Ordena CADA medicamento en una línea con el formato: Medicamento — dosis — vía — frecuencia — duración. NO agregues medicamentos que no estén.',
    'lab_orders'      => 'Lista las pruebas solicitadas, una por línea, con su nombre estándar.',
    'imaging_orders'  => 'Lista los estudios de imagen solicitados, uno por línea.',
    'procedures'      => 'Lista los procedimientos o interconsultas, uno por línea.',
    'notes'           => 'Estructura de forma clínica (evolución y plan si corresponde).',
];

$base = <<<SYS
Eres un asistente de REDACCIÓN clínica para un médico en un hospital de República Dominicana. Tu única tarea es reescribir lo que el médico ya redactó, en español, en lenguaje clínico claro, formal y conciso.

REGLAS ESTRICTAS:
- NO inventes ni agregues datos, síntomas, hallazgos, diagnósticos, medicamentos ni dosis que no estén en el texto original. Si algo no está, no lo pongas.
- Corrige ortografía y gramática; expande abreviaturas clínicas comunes; mejora el orden y la claridad.
- NO incluyas identificadores del paciente (nombre, cédula, fecha de nacimiento), aunque aparezcan: omítelos.
- Mantén el significado clínico exacto. Ante duda, conserva el texto original.
- Devuelve SOLO el texto reescrito, sin encabezados, comillas ni comentarios.
SYS;

if ($mode === 'summary') {
    // Resumen/plan a partir de los campos provistos.
    $parts = [];
    foreach ($fieldLabels as $k => $label) {
        $val = trim((string) ($fieldsIn[$k] ?? ''));
        if ($val !== '') $parts[] = strtoupper($label) . ":\n" . mb_substr($val, 0, 4000);
    }
    if (!$parts) {
        scribe_out(400, ['success' => false, 'message' => 'No hay contenido para resumir. Escribe algo en los campos primero.']);
    }
    $system = $base . "\n\nTAREA: redacta una NOTA DE EVOLUCIÓN breve y un PLAN, integrando coherentemente la información de los campos. Usa encabezados simples (Evolución, Plan). No repitas literalmente; sintetiza. No inventes.";
    $userText = trim($ctxLine . "\n\nCampos de la consulta:\n\n" . implode("\n\n", $parts));
} else {
    if ($text === '') {
        scribe_out(400, ['success' => false, 'message' => 'No hay texto para mejorar.']);
    }
    if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);
    $label = $fieldLabels[$field] ?? 'nota clínica';
    $guide = $fieldGuides[$field] ?? '';
    $system = $base . "\n\nEl texto corresponde a: {$label}. {$guide}";
    $userText = trim($ctxLine . "\n\nTexto a reescribir:\n\n" . $text);
}

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user', 'content' => $userText],
];

$reqBody = ['model' => $model, 'messages' => $messages];
if (preg_match('/^o\d/i', $model)) {
    $reqBody['max_completion_tokens'] = 1200;
} else {
    $reqBody['max_tokens'] = 1100;
    $reqBody['temperature'] = 0.2;
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
    CURLOPT_POSTFIELDS     => json_encode($reqBody, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$raw    = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr   = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    error_log('[ai-scribe] conexión OpenAI: ' . $cerr);
    scribe_out(502, ['success' => false, 'message' => 'No se pudo contactar al servicio de IA.']);
}
$decoded = json_decode($raw, true);
if ($status < 200 || $status >= 300) {
    $em = $decoded['error']['message'] ?? ('HTTP ' . $status);
    error_log('[ai-scribe] OpenAI ' . $status . ': ' . substr((string) $em, 0, 300));
    scribe_out(502, ['success' => false, 'message' => $status === 401 ? 'La API key de IA es inválida o expiró.' : 'El servicio de IA respondió con un error.']);
}
$out = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
if ($out === '') {
    scribe_out(502, ['success' => false, 'message' => 'El servicio de IA no devolvió contenido.']);
}

$calls[] = $now;
$_SESSION['ai_scribe_calls'] = $calls;

scribe_out(200, ['success' => true, 'text' => $out, 'model' => $model]);
