<?php
/**
 * Asistente de IA para el visor de imágenes (apoyo a la lectura, NO diagnóstico).
 *
 * El navegador (visor) envía —mismo origen— la imagen ya renderizada (solo píxeles,
 * SIN datos del paciente) + contexto no identificante (modalidad, región, sexo, edad)
 * y opcionalmente una pregunta de seguimiento. Este endpoint reenvía a la API de
 * OpenAI usando la API key que vive SOLO en el servidor (config.local.php). La key
 * nunca cruza al navegador.
 *
 * Seguridad: requiere sesión del médico + CSRF; límite de uso por sesión; solo POST.
 * Privacidad: jamás recibe ni reenvía nombre, cédula ni fecha de nacimiento.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

header('Content-Type: application/json; charset=utf-8');

function ai_out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Orientación de lectura según la modalidad DICOM (no identificante). */
function ai_modality_guidance(string $m): string {
    $m = strtoupper(trim($m));
    if ($m === '') return '';
    if (strpos($m, 'CT') !== false) return 'Orientación TC: considera densidades / escala Hounsfield y evalúa por planos.';
    if ($m === 'CR' || $m === 'DX' || strpos($m, 'XR') !== false || strpos($m, 'RX') !== false)
        return 'Orientación radiografía: valora proyección y técnica; en tórax revisa campos pulmonares, silueta cardiomediastínica, senos costofrénicos, hilios y estructuras óseas.';
    if (strpos($m, 'MR') !== false) return 'Orientación RM: indica la secuencia si es evaluable y describe la señal (hiper/hipointensa).';
    if (strpos($m, 'US') !== false) return 'Orientación ecografía: describe ecogenicidad, márgenes y sombra acústica.';
    if (strpos($m, 'MG') !== false) return 'Orientación mamografía: evalúa densidad, asimetrías, calcificaciones y distorsión.';
    return '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ai_out(405, ['success' => false, 'message' => 'Método no permitido.']);
}

doctor_csrf_check();
if (!doctor_is_logged_in()) {
    ai_out(401, ['success' => false, 'message' => 'Sesión del médico expirada. Recarga la página.']);
}

$key = defined('OPENAI_API_KEY') ? trim((string)OPENAI_API_KEY) : '';
if ($key === '') {
    ai_out(503, ['success' => false, 'message' => 'El asistente de IA aún no está configurado en el servidor.']);
}
$model = defined('OPENAI_IMAGING_MODEL') ? trim((string)OPENAI_IMAGING_MODEL) : 'gpt-4.1-mini';

// ── Límite de uso por sesión (backstop de costo) ─────────────────────────────
doctor_portal_session_start();
$now = time();
$WIN = 600;   // 10 min
$MAX = 40;    // máx. llamadas por ventana
$calls = array_values(array_filter(
    (array)($_SESSION['ai_img_calls'] ?? []),
    static fn($t) => is_int($t) && $t > $now - $WIN
));
if (count($calls) >= $MAX) {
    ai_out(429, ['success' => false, 'message' => 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo de nuevo.']);
}

// ── Entrada ──────────────────────────────────────────────────────────────────
$payload  = json_decode(file_get_contents('php://input'), true) ?: [];
$image    = (string)($payload['image'] ?? '');
$question = trim((string)($payload['question'] ?? ''));
$ctxIn    = is_array($payload['context'] ?? null) ? $payload['context'] : [];
$histIn   = is_array($payload['history'] ?? null) ? $payload['history'] : [];
$mode     = (string)($payload['mode'] ?? 'analyze');
if (!in_array($mode, ['analyze', 'followup', 'compare', 'patient'], true)) $mode = 'analyze';
$image2   = (string)($payload['image2'] ?? '');                // 2.ª imagen (solo modo comparar)
$hasImg2  = (bool)preg_match('#^data:image/(jpeg|png|webp);base64,#', $image2) && strlen($image2) <= 9 * 1024 * 1024;

if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $image)) {
    ai_out(400, ['success' => false, 'message' => 'Imagen inválida.']);
}
if (strlen($image) > 9 * 1024 * 1024) {
    ai_out(413, ['success' => false, 'message' => 'La imagen es demasiado grande.']);
}

// Contexto NO identificante: lista blanca estricta. Cualquier intento de mandar
// nombre / cédula / fecha de nacimiento se descarta aquí.
$modality  = substr(preg_replace('/[^\p{L}\p{N}\s.\-\/]/u', '', (string)($ctxIn['modality']  ?? '')), 0, 40);
$bodyPart  = substr(preg_replace('/[^\p{L}\p{N}\s.\-\/]/u', '', (string)($ctxIn['bodyPart']  ?? '')), 0, 80);
$studyDesc = substr(preg_replace('/[^\p{L}\p{N}\s.\-\/]/u', '', (string)($ctxIn['studyDesc'] ?? '')), 0, 100);
$sex       = strtoupper(substr(preg_replace('/[^MFOmfo]/', '', (string)($ctxIn['sex'] ?? '')), 0, 1));
$age       = (int)($ctxIn['age'] ?? 0);
if ($age < 0 || $age > 120) $age = 0;

$ctxParts = [];
if ($modality)  $ctxParts[] = 'modalidad ' . $modality;
if ($studyDesc) $ctxParts[] = 'estudio: ' . $studyDesc;
if ($bodyPart && stripos($studyDesc, $bodyPart) === false) $ctxParts[] = 'región: ' . $bodyPart;
if ($sex === 'M') $ctxParts[] = 'sexo masculino';
elseif ($sex === 'F') $ctxParts[] = 'sexo femenino';
if ($age > 0)   $ctxParts[] = 'edad ' . $age . ' años';
$ctxLine = $ctxParts ? ('Contexto del estudio: ' . implode(', ', $ctxParts) . '.') : '';

// ── Prompt del sistema ───────────────────────────────────────────────────────
$critico = "Si observas un hallazgo que podría requerir ATENCIÓN URGENTE (por ejemplo neumotórax, fractura inestable, luxación, hemorragia, perforación de víscera, neumoperitoneo o signos de isquemia), comienza tu respuesta con esta línea EXACTA, en su propio renglón:\n⚠️ POSIBLE HALLAZGO CRÍTICO — requiere correlación clínica inmediata.\nLuego continúa con el análisis.";
$modGuide = ai_modality_guidance($modality);

if ($mode === 'patient') {
    $system = <<<SYS
Eres un asistente de IA que ayuda a EXPLICAR AL PACIENTE, en español sencillo y empático, una imagen médica que le acaban de tomar. NO eres su médico y NO das diagnósticos.

Reglas:
- Lenguaje muy claro, sin tecnicismos (y si usas alguno, explícalo en palabras simples). Tono calmado y respetuoso.
- Explica qué tipo de estudio es y, en general, qué se observa, sin afirmar enfermedades ni dar certezas.
- No alarmes. Si algo parece relevante, indica que su médico lo valorará con el resto de su información.
- No incluyas datos personales (no los tienes).
- Sé breve (4–8 líneas), dirígete al paciente ("tu radiografía…").

Termina SIEMPRE con esta línea exacta:
_Información orientativa generada por IA. No reemplaza la valoración de tu médico._
SYS;
} elseif ($mode === 'compare') {
    $system = <<<SYS
Eres un asistente de IA que APOYA a un médico COMPARANDO dos imágenes del mismo paciente. NO emites diagnósticos: produces un BORRADOR que el médico confirma.

Recibirás DOS imágenes: la PRIMERA es el estudio MÁS RECIENTE (actual) y la SEGUNDA es un estudio PREVIO. Si no es posible determinar la correspondencia o no son comparables, dilo con claridad.

$critico

Estructura la respuesta así:
**Comparabilidad** — si los estudios son comparables (misma región/técnica/proyección) o no.
**Cambios** — qué apareció, desapareció, aumentó o disminuyó (viñetas).
**Impresión de evolución** — mejoría, estabilidad o progresión, con cautela.
**Sugerencias** — correlación clínica o seguimiento.

Responde en español, claro y clínico, breve, en markdown. Describe SOLO lo observable.

Termina SIEMPRE con esta línea exacta:
_Borrador generado por IA — no sustituye el criterio médico ni el informe radiológico oficial._
SYS;
} else {
    $system = <<<SYS
Eres un asistente de inteligencia artificial que APOYA a un médico en la lectura de imágenes médicas (radiografía, TC, RM, ecografía, etc.) en un hospital de República Dominicana. NO eres radiólogo y NO emites diagnósticos: produces un BORRADOR de apoyo que el médico tratante revisa y confirma. La decisión clínica es siempre del médico.

Reglas:
- Responde en español, claro y clínico, breve, con formato markdown.
- Describe SOLO lo que realmente se observa en la imagen. No inventes hallazgos ni des certezas diagnósticas.
- Si la imagen es insuficiente, de baja calidad o un aspecto no es evaluable, dilo de forma explícita.
- No incluyas datos de identificación del paciente (no los tienes).
- Ante hallazgos potencialmente graves, recomienda correlación clínica y valoración por radiólogo/especialista.

$critico

Para una solicitud de ANÁLISIS estructura la respuesta así:
**Estudio y calidad** — tipo/proyección y si la calidad permite evaluar.
**Hallazgos** — viñetas de lo observado (o "Sin hallazgos evidentes" si aplica).
**Impresión preliminar** — interpretación tentativa; señala la incertidumbre cuando corresponda.
**Sugerencias** — siguiente paso, correlación o proyecciones adicionales.

Para PREGUNTAS de seguimiento responde directo y conciso, con la misma cautela.

Termina SIEMPRE con esta línea exacta:
_Borrador generado por IA — no sustituye el criterio médico ni el informe radiológico oficial._
SYS;
}
if ($modGuide !== '') { $system .= "\n\n" . $modGuide; }

$messages = [['role' => 'system', 'content' => $system]];

// Historial (solo texto) de la conversación visible — últimas 8 intervenciones.
$histIn = array_slice($histIn, -8);
foreach ($histIn as $h) {
    $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = substr(trim((string)($h['text'] ?? '')), 0, 4000);
    if ($text !== '') $messages[] = ['role' => $role, 'content' => $text];
}

// Turno actual: texto + imagen(es). La imagen viaja en cada llamada (modelo sin estado).
if ($mode === 'patient') {
    $userText = trim('Explica al paciente, en lenguaje sencillo, qué muestra esta imagen. ' . $ctxLine);
} elseif ($mode === 'compare') {
    $userText = trim('Compara estas dos imágenes (la primera es la ACTUAL, la segunda la PREVIA) e indica los cambios y la evolución. ' . $ctxLine);
} elseif ($question !== '') {
    $userText = substr($question, 0, 1500);
} else {
    $userText = trim('Analiza esta imagen médica. ' . $ctxLine);
}
$content = [
    ['type' => 'text', 'text' => $userText],
    ['type' => 'image_url', 'image_url' => ['url' => $image, 'detail' => 'high']],
];
if ($mode === 'compare' && $hasImg2) {
    $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image2, 'detail' => 'high']];
}
$messages[] = ['role' => 'user', 'content' => $content];

// ── Cuerpo de la petición (adapta parámetros según familia del modelo) ─────────
$reqBody = ['model' => $model, 'messages' => $messages];
if (preg_match('/^o\d/i', $model)) {           // modelos de razonamiento (o3/o4…)
    $reqBody['max_completion_tokens'] = 1300;
} else {
    $reqBody['max_tokens']   = 1100;
    $reqBody['temperature']  = 0.2;
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_POSTFIELDS     => json_encode($reqBody, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$raw    = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr   = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    error_log('[ai-imaging] conexión OpenAI: ' . $cerr);
    ai_out(502, ['success' => false, 'message' => 'No se pudo contactar al servicio de IA.']);
}

$decoded = json_decode($raw, true);
if ($status < 200 || $status >= 300) {
    $em = $decoded['error']['message'] ?? ('HTTP ' . $status);
    error_log('[ai-imaging] OpenAI ' . $status . ': ' . substr((string)$em, 0, 300));
    $msg = ($status === 401)
        ? 'La API key de IA es inválida o expiró.'
        : 'El servicio de IA respondió con un error.';
    ai_out(502, ['success' => false, 'message' => $msg]);
}

$text  = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
$usage = $decoded['usage'] ?? null;
if ($text === '') {
    ai_out(502, ['success' => false, 'message' => 'El servicio de IA no devolvió contenido.']);
}

// Registrar la llamada (para el límite) solo si fue exitosa.
$calls[] = $now;
$_SESSION['ai_img_calls'] = $calls;

ai_out(200, ['success' => true, 'text' => $text, 'model' => $model, 'usage' => $usage]);
