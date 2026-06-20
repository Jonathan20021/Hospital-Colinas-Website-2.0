<?php

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/doctors.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '[]', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

if (!empty($payload['honeypot'] ?? '')) {
    echo json_encode(['ok' => true, 'reply' => 'OK']);
    exit;
}

$settings = ai_settings_load();
if (!$settings['enabled'] || $settings['api_key'] === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'El asistente no está disponible en este momento.',
    ]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateDir = dirname(__DIR__) . '/storage/ai-rate';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0775, true);
}
$rateFile = $rateDir . '/' . md5($ip) . '.json';
$now = time();
$window = 60;
$maxPerWindow = 12;
$bucket = ['count' => 0, 'reset' => $now + $window];
if (is_file($rateFile)) {
    $stored = json_decode((string) @file_get_contents($rateFile), true);
    if (is_array($stored) && ($stored['reset'] ?? 0) > $now) {
        $bucket = $stored;
    }
}
$bucket['count']++;
if ($bucket['reset'] < $now) {
    $bucket = ['count' => 1, 'reset' => $now + $window];
}
@file_put_contents($rateFile, json_encode($bucket));
if ($bucket['count'] > $maxPerWindow) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'Estás enviando mensajes muy rápido. Espera un momento e intenta de nuevo.',
    ]);
    exit;
}

$incomingMessages = $payload['messages'] ?? [];
if (!is_array($incomingMessages) || count($incomingMessages) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Mensaje vacío.']);
    exit;
}

$cleaned = [];
foreach (array_slice($incomingMessages, -20) as $message) {
    if (!is_array($message)) continue;
    $role = $message['role'] ?? '';
    if (!in_array($role, ['user', 'assistant'], true)) continue;
    $content = trim((string) ($message['content'] ?? ''));
    if ($content === '') continue;
    if (mb_strlen($content) > 2000) {
        $content = mb_substr($content, 0, 2000);
    }
    $cleaned[] = ['role' => $role, 'content' => $content];
}

if (count($cleaned) === 0 || end($cleaned)['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el mensaje del usuario.']);
    exit;
}

$systemPrompt = ai_build_system_prompt($settings, $services, $assets, $contact, $insurers);

$openaiMessages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $cleaned
);

$result = ai_run_conversation($openaiMessages, $settings, 10);

if (!$result['ok']) {
    // Loggear tool_log y error en ai_conversations para depurar
    if (db_ready()) {
        try {
            $sessionId = $_SESSION['ai_session'] ?? bin2hex(random_bytes(16));
            $_SESSION['ai_session'] = $sessionId;
            $stmt = db()->prepare('INSERT INTO ai_conversations (session_id, role, content, tokens, ip_address) VALUES (?, ?, ?, ?, ?)');
            $userMessage = end($cleaned);
            $stmt->execute([$sessionId, 'user', $userMessage['content'], null, $ip]);
            $errSummary = '❌ ERROR: ' . ($result['error'] ?? 'desconocido');
            if (!empty($result['tool_log'])) {
                $errSummary .= "\nTools ejecutados:\n";
                foreach ($result['tool_log'] as $t) {
                    $errSummary .= '  - ' . $t['name'] . ' args=' . json_encode($t['args'], JSON_UNESCAPED_UNICODE)
                                . ' result=' . substr(json_encode($t['result'], JSON_UNESCAPED_UNICODE), 0, 300) . "\n";
                }
            }
            $stmt->execute([$sessionId, 'system', $errSummary, null, $ip]);
        } catch (Throwable) {}
    }
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'El asistente no pudo responder en este momento.',
        'detail' => $result['error'] ?? null,
        'tools' => array_map(fn($t) => ['name' => $t['name'], 'ok' => $t['result']['ok'] ?? false, 'error' => $t['result']['error'] ?? null], $result['tool_log'] ?? []),
    ]);
    exit;
}

$reply = (string) $result['content'];
$toolLog = $result['tool_log'] ?? [];

if (db_ready()) {
    try {
        $sessionId = $_SESSION['ai_session'] ?? null;
        if (!$sessionId) {
            $sessionId = bin2hex(random_bytes(16));
            $_SESSION['ai_session'] = $sessionId;
        }
        $stmt = db()->prepare('INSERT INTO ai_conversations (session_id, role, content, tokens, ip_address) VALUES (?, ?, ?, ?, ?)');
        $userMessage = end($cleaned);
        $stmt->execute([$sessionId, 'user', $userMessage['content'], null, $ip]);
        $tokens = $result['usage']['total_tokens'] ?? null;
        // Si hubo tool calls, guardamos un resumen como mensaje 'system' para depurar
        if (!empty($toolLog)) {
            $toolSummary = '🔧 TOOL CALLS: ' . implode(' → ', array_map(fn($t) => $t['name'] . '(' . substr(json_encode($t['args'], JSON_UNESCAPED_UNICODE), 0, 80) . ') ' . ($t['result']['ok'] ?? false ? 'OK' : 'ERR'), $toolLog));
            $stmt->execute([$sessionId, 'system', $toolSummary, null, $ip]);
        }
        $stmt->execute([$sessionId, 'assistant', $reply, $tokens, $ip]);
    } catch (Throwable) {
        // Persistence is best-effort — never block the response.
    }
}

echo json_encode([
    'ok' => true,
    'reply' => $reply,
    'usage' => $result['usage'] ?? null,
    'tools' => array_map(fn($t) => ['name' => $t['name'], 'ok' => $t['result']['ok'] ?? false], $toolLog),
]);
