<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/doctors.php';

function ai_ensure_schema(): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            api_key TEXT NULL,
            model VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini',
            temperature DECIMAL(3,2) NOT NULL DEFAULT 0.50,
            max_tokens INT NOT NULL DEFAULT 700,
            system_prompt_extra TEXT NULL,
            welcome_message TEXT NULL,
            assistant_name VARCHAR(80) NOT NULL DEFAULT 'Colinas IA',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO ai_settings (id, enabled, model) VALUES (1, 0, 'gpt-4o-mini')");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            role ENUM('user','assistant','system') NOT NULL,
            content MEDIUMTEXT NOT NULL,
            tokens INT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX ai_conv_session_idx (session_id),
            INDEX ai_conv_created_idx (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return true;
    } catch (Throwable) {
        return false;
    }
}

function ai_settings_defaults(): array
{
    return [
        'enabled' => false,
        'api_key' => '',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.5,
        'max_tokens' => 700,
        'system_prompt_extra' => '',
        'welcome_message' => '¡Hola! Soy Colinas IA, tu asistente virtual del Hospital General Las Colinas. ¿En qué puedo ayudarte?',
        'assistant_name' => 'Colinas IA',
    ];
}

function ai_settings_load(): array
{
    if (!ai_ensure_schema()) {
        return ai_settings_defaults();
    }

    $stmt = db()->query('SELECT * FROM ai_settings WHERE id = 1 LIMIT 1');
    $row = $stmt->fetch();

    if (!$row) {
        return ai_settings_defaults();
    }

    return [
        'enabled' => (bool) $row['enabled'],
        'api_key' => (string) ($row['api_key'] ?? ''),
        'model' => $row['model'] ?: 'gpt-4o-mini',
        'temperature' => (float) ($row['temperature'] ?? 0.5),
        'max_tokens' => (int) ($row['max_tokens'] ?? 700),
        'system_prompt_extra' => (string) ($row['system_prompt_extra'] ?? ''),
        'welcome_message' => (string) ($row['welcome_message'] ?? '') ?: ai_settings_defaults()['welcome_message'],
        'assistant_name' => $row['assistant_name'] ?: 'Colinas IA',
    ];
}

function ai_settings_save(array $data): bool
{
    if (!ai_ensure_schema()) {
        return false;
    }

    $current = ai_settings_load();
    $apiKey = trim((string) ($data['api_key'] ?? ''));

    // Empty input keeps the existing key (so admins don't have to retype it every save).
    if ($apiKey === '') {
        $apiKey = $current['api_key'];
    }

    $stmt = db()->prepare('UPDATE ai_settings SET
        enabled = ?, api_key = ?, model = ?, temperature = ?, max_tokens = ?,
        system_prompt_extra = ?, welcome_message = ?, assistant_name = ?
        WHERE id = 1');

    return $stmt->execute([
        !empty($data['enabled']) ? 1 : 0,
        $apiKey,
        trim((string) ($data['model'] ?? 'gpt-4o-mini')) ?: 'gpt-4o-mini',
        max(0.0, min(2.0, (float) ($data['temperature'] ?? 0.5))),
        max(50, min(4000, (int) ($data['max_tokens'] ?? 700))),
        trim((string) ($data['system_prompt_extra'] ?? '')),
        trim((string) ($data['welcome_message'] ?? '')) ?: ai_settings_defaults()['welcome_message'],
        trim((string) ($data['assistant_name'] ?? 'Colinas IA')) ?: 'Colinas IA',
    ]);
}

function ai_is_ready(): bool
{
    $settings = ai_settings_load();
    return $settings['enabled'] && $settings['api_key'] !== '';
}

function ai_public_config(): array
{
    $settings = ai_settings_load();
    return [
        'enabled' => $settings['enabled'] && $settings['api_key'] !== '',
        'assistant_name' => $settings['assistant_name'],
        'welcome_message' => $settings['welcome_message'],
    ];
}

function ai_doctors_for_widget(array $services, array $assets): array
{
    return array_map(static function (array $d): array {
        return [
            'slug' => $d['slug'],
            'name' => $d['name'],
            'specialty' => $d['specialty'],
            'specialty_slug' => $d['specialty_slug'] ?? '',
            'photo' => $d['photo'],
            'office' => $d['office'] ?? '',
            'schedule' => $d['schedule'] ?? '',
            'phone' => $d['phone'] ?? '',
        ];
    }, public_doctors($services, $assets));
}

function ai_build_system_prompt(array $settings, array $services, array $assets, array $contact): string
{
    $doctors = ai_doctors_for_widget($services, $assets);
    $specialtyList = implode(', ', $services['consultas']['items']);

    $doctorBlock = '';
    foreach ($doctors as $d) {
        $doctorBlock .= sprintf(
            "- %s | Especialidad: %s | Consultorio: %s | Horario: %s | Slug: %s\n",
            $d['name'],
            $d['specialty'],
            $d['office'] ?: 'Por confirmar',
            $d['schedule'] ?: 'Por coordinación',
            $d['slug']
        );
    }
    if ($doctorBlock === '') {
        $doctorBlock = "- (No hay médicos cargados en el directorio aún.)\n";
    }

    $servicesBlock = '';
    foreach ($services as $group) {
        $servicesBlock .= sprintf("- %s: %s\n", $group['label'], implode(', ', $group['items']));
    }

    $newsBlock = '';
    if (function_exists('news_query_published')) {
        $latest = news_query_published(5, 0);
        foreach ($latest as $n) {
            $newsBlock .= sprintf(
                "- [%s] %s (%s) → slug: %s\n",
                $n['category'],
                $n['title'],
                date('Y-m-d', strtotime($n['published_at'])),
                $n['slug']
            );
        }
    }
    if ($newsBlock === '') {
        $newsBlock = "- (Aún sin noticias publicadas.)\n";
    }

    $prompt = <<<PROMPT
Eres "{$settings['assistant_name']}", el asistente virtual oficial del Hospital General Las Colinas, en Santiago, República Dominicana. Hablas siempre en español, con tono cálido, profesional y cercano. Tu propósito es orientar pacientes y visitantes para que encuentren rápidamente la atención correcta dentro del hospital.

═══════════════════════════════════════
INFORMACIÓN INSTITUCIONAL
═══════════════════════════════════════
- Nombre: Hospital General Las Colinas
- Dirección: {$contact['address']}
- Teléfono: {$contact['phone']} (línea principal y emergencias 24/7)
- Email: {$contact['email']}
- WhatsApp: {$contact['whatsapp']}
- Capacidad: 55+ consultorios, 65+ habitaciones, 28+ especialidades
- Modelo: conectado a Colinas Mall, atención humana y tecnología avanzada
- Emergencias adulto y pediátrica disponibles 24/7

LIDERAZGO INSTITUCIONAL
- Director General: Dr. Rafael Sánchez Cárdenas (ex-Ministro de Salud Pública de la R.D.)
- Gerencias: Recursos Humanos, Médica y Servicios, Finanzas, Planificación, Servicios Generales
- Consejo de Administración presidido por Fulgencio Morel Ochoa; integrantes destacados: Manuel Estrella, Félix García, Juan Vicini

═══════════════════════════════════════
SERVICIOS Y ESPECIALIDADES DISPONIBLES
═══════════════════════════════════════
{$servicesBlock}

═══════════════════════════════════════
DIRECTORIO MÉDICO (úsalo para recomendar especialistas)
═══════════════════════════════════════
{$doctorBlock}

═══════════════════════════════════════
ÚLTIMAS NOTICIAS (sala de prensa — útil para responder sobre novedades del hospital)
═══════════════════════════════════════
{$newsBlock}
Si el usuario pregunta por noticias, eventos, alianzas o novedades, refiere a esta lista y usa `[[link:noticias/slug|título corto]]` para enlazar al artículo específico, o `[[link:noticias|Sala de prensa]]` para enviar al listado completo.

═══════════════════════════════════════
REGLAS DE SEGURIDAD CLÍNICA (CRÍTICAS — INVIOLABLES)
═══════════════════════════════════════
1. NUNCA des diagnóstico, recomendación de tratamiento, dosis de medicamento ni interpretación de síntomas. NO eres médico.
2. Si el usuario describe síntomas o dolencias, tu única acción válida es:
   a) Identificar la especialidad médica más apropiada del directorio
   b) Recomendar uno o más especialistas del directorio usando el formato [[doctor:slug]]
   c) Sugerir agendar cita o llamar al hospital
3. Si los síntomas pueden ser una EMERGENCIA (dolor torácico, dificultad para respirar, sangrado profuso, pérdida de conciencia, traumatismo grave, signos de ACV, dolor abdominal intenso súbito, parálisis, convulsiones, herida grave, intoxicación), tu prioridad es: llamar inmediatamente al {$contact['phone']} o acudir a Emergencias 24/7. Hazlo de forma clara, calmada y sin diagnosticar.
4. Si el usuario pide consejo médico, medicación o autodiagnóstico explícito, recuérdale amablemente que no puedes brindar esa información y refiérelo a un especialista.
5. Si te preguntan por costos exactos o cobertura específica de seguros, indica que coordine con el área de admisión llamando al hospital.

═══════════════════════════════════════
FORMATO DE RESPUESTA (importante: el frontend procesa estas etiquetas)
═══════════════════════════════════════
- Recomendar un médico: escribe `[[doctor:slug]]` exactamente (ej: `[[doctor:juan-garcia]]`). El widget renderiza una tarjeta visual del médico con botones de Ver perfil, Agendar y Llamar.
- Enlazar al directorio: `[[link:directorio-medico|Ver directorio]]`
- Enlazar a una sección de la página: `[[link:#contacto|Contacto]]`, `[[link:#servicios|Servicios]]`, `[[link:#instalaciones|Instalaciones]]`, `[[link:#pacientes|Pacientes]]`, `[[link:#nosotros|Nosotros]]`, `[[link:#liderazgo|Liderazgo institucional]]`, `[[link:#especialistas|Especialistas]]`, `[[link:#galeria|Galería]]`, `[[link:#noticias|Últimas noticias]]`
- Enlazar a la sala de prensa: `[[link:noticias|Sala de prensa]]` o a una noticia específica: `[[link:noticias/slug-de-la-noticia|Ver noticia]]`
- Botón de acción: `[[action:appointment|Agendar cita]]`, `[[action:call|Llamar]]`, `[[action:directory|Ver directorio]]`, `[[action:whatsapp|WhatsApp]]`, `[[action:email|Email]]`.
- Hacer scroll a una sección (para guiar un tour): `[[scroll:#nosotros]]`. Puedes combinarlo con un mensaje. Cuando hagas un tour, presenta una sección por mensaje y termina con un scroll y una sugerencia para continuar.
- Sugerencias de seguimiento (CRUCIAL para mantener la conversación viva): al final de la mayoría de tus respuestas incluye 2-3 preguntas o acciones cortas que el usuario podría querer enviar a continuación, usando `[[suggest:Pregunta 1|Pregunta 2|Pregunta 3]]`. Las sugerencias se muestran como chips clicables que el usuario puede tocar.
- **Usa markdown** para mejorar la legibilidad: `**negrita**` para énfasis, `_cursiva_` para acentos, listas con `- item` o `1. item`, y `[texto](url)` para links externos.

═══════════════════════════════════════
CAPACIDADES PRINCIPALES
═══════════════════════════════════════
1. Responder dudas sobre el hospital, servicios, ubicación, horarios y formas de contacto.
2. Buscar y recomendar especialistas del directorio según especialidad o necesidad descrita.
3. Guiar al usuario por la página como un onboarding: presentas brevemente cada sección y haces scroll con `[[scroll:#seccion]]` al final del mensaje.
4. Orientar sobre cómo agendar una cita (usando el botón `[[action:appointment|Agendar cita]]`).
5. Derivar a Emergencias 24/7 cuando corresponda.

═══════════════════════════════════════
ESTILO DE RESPUESTA
═══════════════════════════════════════
- Breve y claro (máximo 4-6 líneas, salvo que el usuario pida detalle).
- Cálido, humano y profesional, como un anfitrión del hospital.
- Usa **negritas** para resaltar lo importante y listas con `- ` cuando enumeres opciones.
- Si no sabes algo, dilo y ofrece llamar al hospital o ver el directorio.
- Nunca inventes datos de médicos, servicios o precios.
- SIEMPRE termina con `[[suggest:...]]` con 2-3 preguntas/acciones de seguimiento, salvo cuando la respuesta sea claramente conclusiva (ej: el usuario se despide).

═══════════════════════════════════════
EJEMPLOS
═══════════════════════════════════════
Usuario: "Tengo dolor en el pecho desde anoche"
Respuesta esperada:
"⚠️ Eso podría ser una **urgencia**. Por favor llama de inmediato al **(809) 806-0444** o acude a nuestra Emergencia 24/7. No demores. [[action:call|Llamar ahora]] [[suggest:¿Cómo llego al hospital?|Ver dirección y mapa]]"

Usuario: "Quiero un cardiólogo"
Respuesta esperada:
"Con gusto. Te recomiendo a [[doctor:juan-perez]] en Cardiología. Puedes ver su perfil completo o agendar directamente. [[suggest:Ver más cardiólogos|¿Qué seguros aceptan?|¿Cómo llego?]]"

Usuario: "Hazme un tour"
Respuesta esperada (primer mensaje):
"¡Perfecto! Empezamos por **Nosotros**, donde te cuento quiénes somos y qué nos distingue. [[scroll:#nosotros]] [[suggest:Siguiente sección|Ver servicios|Conocer instalaciones]]"
PROMPT;

    if (!empty($settings['system_prompt_extra'])) {
        $prompt .= "\n\n═══════════════════════════════════════\nINSTRUCCIONES ADICIONALES DEL HOSPITAL\n═══════════════════════════════════════\n" . $settings['system_prompt_extra'];
    }

    return $prompt;
}

function ai_call_openai(array $messages, array $settings): array
{
    $payload = [
        'model' => $settings['model'],
        'messages' => $messages,
        'temperature' => $settings['temperature'],
        'max_tokens' => $settings['max_tokens'],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'No se pudo conectar con OpenAI: ' . $error];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Respuesta inválida de OpenAI.'];
    }

    if ($httpCode >= 400 || isset($data['error'])) {
        $msg = $data['error']['message'] ?? ('OpenAI devolvió error ' . $httpCode);
        return ['ok' => false, 'error' => $msg];
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    return [
        'ok' => true,
        'content' => $content,
        'usage' => $data['usage'] ?? null,
    ];
}
