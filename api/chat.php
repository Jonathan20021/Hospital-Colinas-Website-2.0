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

$systemPrompt = ai_build_system_prompt($settings, $services, $assets, $contact);

$openaiMessages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $cleaned
);

$result = ai_run_conversation($openaiMessages, $settings, 6);

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'El asistente no pudo responder en este momento.',
        'detail' => $result['error'] ?? null,
    ]);
    exit;
}

$reply = (string) $result['content'];

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
        $stmt->execute([$sessionId, 'assistant', $reply, $tokens, $ip]);
    } catch (Throwable) {
        // Persistence is best-effort — never block the response.
    }
}

echo json_encode([
    'ok' => true,
    'reply' => $reply,
    'usage' => $result['usage'] ?? null,
]);
