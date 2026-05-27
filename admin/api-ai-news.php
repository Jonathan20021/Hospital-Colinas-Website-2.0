<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = admin_current_user();
    if (!$user) {
        throw new RuntimeException('No autorizado.');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $action = trim((string) ($data['action'] ?? ''));
    $text = trim((string) ($data['text'] ?? ''));

    if ($action === '') {
        throw new RuntimeException('Acción no especificada.');
    }

    $settings = ai_settings_load();
    if ($settings['api_key'] === '') {
        throw new RuntimeException('La API Key de OpenAI no está configurada en Colinas IA.');
    }

    $prompt = '';
    if ($action === 'titles') {
        $prompt = "Genera 3 títulos atractivos, profesionales y periodísticos para la siguiente noticia del Hospital General Las Colinas. Responde solo con una lista numerada, sin introducciones ni conclusiones:\n\n" . $text;
    } elseif ($action === 'excerpt') {
        $prompt = "Genera un resumen o bajada de 1 a 2 oraciones (máximo 150 caracteres) para la siguiente noticia del Hospital General Las Colinas. Debe ser directo y profesional, adecuado para una tarjeta de noticias. Responde únicamente con el resumen sin comillas ni textos adicionales:\n\n" . $text;
    } elseif ($action === 'grammar') {
        $prompt = "Corrige la ortografía, gramática y mejora el estilo de redacción del siguiente texto, manteniendo un tono formal e institucional para el Hospital General Las Colinas. Responde únicamente con el texto corregido, sin notas explicativas:\n\n" . $text;
    } elseif ($action === 'expand') {
        $prompt = "Expande el siguiente párrafo para una noticia del Hospital General Las Colinas, aportando un tono profesional, institucional y cercano. Responde únicamente con el texto expandido:\n\n" . $text;
    } else {
        throw new RuntimeException('Acción no válida.');
    }

    $messages = [
        ['role' => 'system', 'content' => 'Eres un redactor profesional de comunicaciones del Hospital General Las Colinas. Responde de forma directa, sin textos introductorios.'],
        ['role' => 'user', 'content' => $prompt]
    ];

    $result = ai_call_openai($messages, $settings);
    if (!$result['ok']) {
        throw new RuntimeException($result['error'] ?? 'Error desconocido en OpenAI.');
    }

    echo json_encode([
        'success' => true,
        'result' => trim($result['content'])
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
