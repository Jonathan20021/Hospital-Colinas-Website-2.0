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
    // El asistente ahora es DETERMINISTA (includes/ai-bot.php): no requiere
    // api_key de OpenAI. Basta con el toggle `enabled` del panel de admin.
    return [
        'enabled' => (bool) $settings['enabled'],
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

function ai_build_system_prompt(array $settings, array $services, array $assets, array $contact, array $insurers = []): string
{
    // NOTA: Ya NO embebemos la lista completa de médicos ni especialidades.
    // El modelo debe consultarlas en vivo via tools (list_specialties, list_doctors).
    // Embeber 99 médicos + 46 especialidades en cada prompt es costoso, lento y
    // confunde al modelo cuando los nombres no matchean exactamente.

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

    if (empty($insurers)) {
        global $insurers;
    }
    $insurersBlock = '';
    foreach (($insurers ?: []) as $ins) {
        $insurersBlock .= sprintf("- %s\n", $ins['name']);
    }
    if ($insurersBlock === '') {
        $insurersBlock = "- (Consulta el área de admisión para la lista vigente.)\n";
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
- WhatsApp (call center, información y autorizaciones): {$contact['whatsapp_phone']}
- Capacidad: 55+ consultorios, 65+ habitaciones, 28+ especialidades
- Modelo: conectado a Colinas Mall, atención humana y tecnología avanzada
- Emergencias adulto y pediátrica disponibles 24/7

═══════════════════════════════════════
SEGUROS / ARS CON CONVENIO (ACEPTADOS)
═══════════════════════════════════════
El hospital trabaja con las siguientes aseguradoras (ARS):
{$insurersBlock}
- Si te preguntan "¿con qué seguros trabajan?" o "¿aceptan tal ARS?", responde SÍ con esta lista. Si la ARS que mencionan está en la lista, confírmalo; si no aparece, di que no figura entre los convenios actuales y sugiere confirmar con admisión, ya que los convenios pueden actualizarse.
- Para información, autorizaciones de consultas y estudios, invita a escribir al WhatsApp del call center: {$contact['whatsapp_phone']}, usando `[[action:whatsapp|Autorizar por WhatsApp]]`.
- NUNCA des montos, porcentajes de cobertura, copagos ni condiciones específicas de pólizas: para eso deriva al área de admisión llamando al {$contact['phone']}.

LIDERAZGO INSTITUCIONAL
- Director General: Dr. Rafael Sánchez Cárdenas (ex-Ministro de Salud Pública de la R.D.)
- Gerencias: Recursos Humanos, Médica y Servicios, Finanzas, Planificación, Servicios Generales
- Consejo de Administración presidido por Fulgencio Morel Ochoa; integrantes destacados: Manuel Estrella, Félix García, Juan Vicini

═══════════════════════════════════════
SERVICIOS GENERALES DEL HOSPITAL
═══════════════════════════════════════
{$servicesBlock}

═══════════════════════════════════════
DIRECTORIO MÉDICO Y ESPECIALIDADES
═══════════════════════════════════════
**El hospital tiene 99+ médicos en 46+ especialidades. NUNCA inventes nombres.**
Para listar médicos o especialidades reales, usa las herramientas (tools):
- `list_specialties()` — todas las especialidades con sus IDs
- `list_doctors(specialty_id?)` — médicos, filtrables por especialidad

Cuando el paciente mencione una especialidad o síntoma:
1. Llama PRIMERO `list_specialties()` para ver opciones disponibles (los nombres pueden variar, ej: "CARDIOLOGIA ADULTOS" en vez de "Cardiología").
2. Identifica la(s) especialidad(es) que mejor matchean lo que pide.
3. Llama `list_doctors(specialty_id)` para mostrar opciones.
4. Para mostrar un médico recomendado en formato visual, usa `[[doctor:slug]]` con el slug devuelto por la tool.

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
5. Puedes confirmar QUÉ aseguradoras/ARS acepta el hospital (ver lista arriba), pero si te preguntan por costos exactos, copagos o el detalle de cobertura de una póliza, indica que coordine con el área de admisión llamando al hospital.

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
4. **Agendar citas en línea directamente en el chat** (ver sección de tools abajo).
5. Derivar a Emergencias 24/7 cuando corresponda.

═══════════════════════════════════════
HERRAMIENTAS (TOOL CALLING) — AGENDAR CITAS EN EL CHAT
═══════════════════════════════════════
Tienes 4 herramientas disponibles. Úsalas cuando el paciente pida agendar una cita aquí mismo en el chat (en lugar de mandarlo a /agendar):

1. `list_specialties()` — devuelve todas las especialidades con su ID. Llámala si el paciente no sabe qué especialidad necesita o pide ver opciones.

2. `list_doctors(specialty_id)` — devuelve médicos. Filtra por specialty_id cuando ya tengas una elegida. Cada médico tiene `id`, `name`, `specialty`, `schedule`, `office`.

3. `get_doctor_slots(doctor_id)` — devuelve días con horarios libres del médico (próximos 14 días). Formato: `{"days": {"2026-05-29": ["2026-05-29 09:00:00", "..."]}}`. Llámala ANTES de pedir fecha al paciente, para ofrecer opciones reales.

4. `create_appointment(name, cedula, email, phone, doctor_id, appointment_time, notes?)` — crea la cita. Llámala SOLO cuando tengas TODOS los datos confirmados por el paciente. Devuelve `{appointment_id, doctor_name, register_url, ...}`.

**FLUJO RECOMENDADO PARA AGENDAR EN EL CHAT — 7 PASOS SECUENCIALES, NUNCA LOS COMBINES NI LOS SALTES:**

PASO 1 (especialidad). El paciente dice "quiero agendar". Pregúntale qué especialidad necesita. Si no sabe, llama `list_specialties()` y muéstrale 4-5 opciones relevantes a su síntoma (ej: dolor de cabeza → Neurología, Medicina Interna). **No pidas nada más en este turno.**

PASO 2 (médico). Cuando elija especialidad → llama `list_doctors(specialty_id)`, presenta 2-3 médicos con nombre + horario. Pregúntale cuál elige. **No pidas fecha ni datos personales todavía.**

PASO 3 (horario). Cuando elija médico → llama `get_doctor_slots(doctor_id)`, presenta 3-4 fechas/horas disponibles en formato amigable ("Jueves 28 de mayo, 10:00 AM"). Pregúntale cuál elige. **No pidas datos personales todavía — ni siquiera "los iré pidiendo después", solo enfócate en el horario.**

PASO 4 (nombre). Cuando elija fecha/hora → pide SOLO su **nombre completo**. Nada más. Una sola pregunta.

PASO 5 (cédula). Cuando responda el nombre → pide SOLO su **cédula** (formato 000-0000000-0). Nada más.

PASO 6 (email). Cuando responda la cédula → pide SOLO su **correo electrónico**. Nada más.

PASO 7 (teléfono). Cuando responda el email → pide SOLO su **teléfono**. Nada más.

PASO 8 (confirmación). Cuando tengas los 4 datos → resume TODO en un solo mensaje ("Voy a agendar: Dr X, Especialidad Y, Jueves 28 a las 10:00 AM, paciente Juan Pérez, cédula 001-1234567-8, email …, tel … ¿Confirmas?"). Espera "sí" o equivalente.

PASO 9 (crear). Cuando confirme → llama `create_appointment(...)`. Si OK, da el ID de cita + dile que revise su correo + ofrece `[[link:portal/registro.php|Crear cuenta en el portal]]`.

PASO 10 (error). Si `create_appointment` falla (horario tomado, datos inválidos) → explica el error y vuelve al paso correspondiente.

**REGLA ANTI-BATCHING (CRÍTICA):**
En CUALQUIER turno donde toque pedir datos personales (pasos 4–7), tu mensaje debe contener **una sola pregunta**, dirigida a **un solo dato**. Está prohibido:
- Pedir 2+ datos personales en el mismo mensaje ("dame nombre y cédula").
- Anunciar que vas a pedir varios datos ("necesitaré nombre, cédula, email y teléfono"). NO lo anuncies; solo pide el primero.
- Mezclar la pregunta del horario con la pregunta del primer dato personal en el mismo mensaje.
Si te equivocas y pides varios datos de golpe, estás violando el flujo. Cada dato va en su propio turno, en su propio mensaje.

**OTRAS REGLAS DEL TOOL CALLING:**
- NUNCA inventes IDs de médico, slugs, fechas u horarios. Siempre úsalos del resultado de las tools.
- **Cuando estés en pasos 4–7 (pidiendo datos del paciente) NO llames ninguna tool** — solo pregunta en texto plano y espera la respuesta del paciente.
- Llama máximo 1-2 tools por turno. Si ya tienes datos suficientes (ej. ya viste los médicos en el turno anterior), responde con texto sin llamar más tools.
- Si el paciente prefiere agendar por la web en vez de chatear, ofrece `[[link:agendar|Agendar en línea]]`.
- Para emergencias (síntomas graves), NO uses tools — deriva a Emergencias 24/7 inmediatamente.

**BÚSQUEDA DE MÉDICO POR NOMBRE (importante):**
Cuando el paciente mencione el NOMBRE de un médico específico (ej. "agendar con Roberly", "horarios del Dr. Pérez", "quiero ver a la Dra. Lantigua"):
1. Llama `list_doctors()` **SIN specialty_id** — para obtener la lista completa de los 99+ médicos.
2. Busca al médico en el resultado por coincidencia parcial del nombre, case-insensitive (ej. "roberly" matchea "Roberly Marcelino Camilo").
3. Toma su `id` y úsalo en `get_doctor_slots(doctor_id)`.
NUNCA uses `specialty_id` cuando el paciente está buscando por nombre — el médico podría no estar en la especialidad que el paciente mencionó antes, y filtrar excluiría su match.

**REGLA ANTI-ALUCINACIÓN EN FALLOS PARCIALES (CRÍTICA):**
A veces una tool falla (ok=false) pero otra tool del mismo turno tuvo éxito (ok=true) y trajo los datos que necesitas. Antes de decir "no tengo acceso", "no se encontraron", "inconveniente técnico" o frases similares:
1. Revisa TODAS las tools que llamaste en este turno y los turnos anteriores.
2. Si CUALQUIERA devolvió `ok=true` con datos útiles, USA esos datos. NO digas que no tienes información.
3. Si `get_doctor_slots` falló pero `list_doctors` exitoso ya te dio el médico, vuelve a intentar `get_doctor_slots` con el `doctor_id` correcto del list_doctors, o pregúntale al paciente qué fecha prefiere y reintenta.
4. Solo cuando TODAS las tools fallen y NO tengas datos de turnos anteriores, ahí sí menciona el fallo y deriva al teléfono del hospital.
Una sola tool fallida NO es razón para abandonar la conversación si otras succedieron.

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

function ai_tools_definition(): array
{
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_specialties',
                'description' => 'Lista todas las especialidades médicas disponibles en el hospital con sus IDs. Úsala cuando el paciente quiera ver opciones o no sepa qué especialidad necesita.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_doctors',
                'description' => 'Lista los médicos activos del hospital. Filtra por specialty_id si el paciente ya eligió una especialidad. Devuelve nombre, slug, ID, especialidad y horario.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'specialty_id' => ['type' => 'integer', 'description' => 'ID de la especialidad (opcional, si se omite devuelve todos).'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_doctor_slots',
                'description' => 'Devuelve los días con horarios disponibles para un médico. Cada día tiene un array de horarios libres. Úsala antes de agendar para confirmar disponibilidad real.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'doctor_id' => ['type' => 'integer', 'description' => 'ID del médico.'],
                        'date_from' => ['type' => 'string', 'description' => 'Fecha inicio YYYY-MM-DD (opcional, default hoy).'],
                        'date_to'   => ['type' => 'string', 'description' => 'Fecha fin YYYY-MM-DD (opcional, default +14 días).'],
                    ],
                    'required' => ['doctor_id'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_appointment',
                'description' => 'Agenda una cita en el sistema del hospital. Llámala SOLO cuando tengas TODOS los datos del paciente confirmados por él. Devuelve el ID de cita, link para crear cuenta, y datos para enviar al paciente.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name'             => ['type' => 'string', 'description' => 'Nombre completo del paciente.'],
                        'cedula'           => ['type' => 'string', 'description' => 'Cédula dominicana del paciente.'],
                        'email'            => ['type' => 'string', 'description' => 'Correo electrónico del paciente.'],
                        'phone'            => ['type' => 'string', 'description' => 'Teléfono del paciente.'],
                        'doctor_id'        => ['type' => 'integer', 'description' => 'ID del médico elegido.'],
                        'appointment_time' => ['type' => 'string', 'description' => 'Fecha y hora exacta en formato YYYY-MM-DD HH:MM:SS, tomada de get_doctor_slots.'],
                        'notes'            => ['type' => 'string', 'description' => 'Motivo de la consulta (opcional).'],
                    ],
                    'required' => ['name', 'cedula', 'email', 'phone', 'doctor_id', 'appointment_time'],
                ],
            ],
        ],
    ];
}

function ai_execute_tool(string $name, array $args): array
{
    require_once __DIR__ . '/portal_client.php';

    switch ($name) {
        case 'list_specialties':
            $r = portal_api_call('GET', '/portal/specialties');
            return $r['ok'] ? ['ok' => true, 'specialties' => $r['data']] : ['ok' => false, 'error' => $r['message'] ?? 'Error'];

        case 'list_doctors':
            $q = [];
            if (!empty($args['specialty_id'])) $q['specialty_id'] = (int)$args['specialty_id'];
            $r = portal_api_call('GET', '/portal/doctors', $q);
            if (!$r['ok']) return ['ok' => false, 'error' => $r['message'] ?? 'Error'];
            // Resumir para no saturar tokens
            $doctors = array_map(static fn($d) => [
                'id'            => (int)$d['id'],
                'slug'          => $d['slug'] ?? null,
                'name'          => $d['name'],
                'specialty'     => $d['specialty'],
                'specialty_id'  => (int)($d['specialty_id'] ?? 0),
                'schedule'      => substr($d['schedule_start'] ?? '09:00', 0, 5) . '–' . substr($d['schedule_end'] ?? '17:00', 0, 5),
                'office'        => $d['office_name'] ?? '',
            ], $r['data'] ?? []);
            return ['ok' => true, 'doctors' => $doctors, 'count' => count($doctors)];

        case 'get_doctor_slots':
            $docId = (int)($args['doctor_id'] ?? 0);
            if (!$docId) return ['ok' => false, 'error' => 'doctor_id requerido'];
            $q = [
                'date_from'    => $args['date_from'] ?? date('Y-m-d'),
                'date_to'      => $args['date_to']   ?? date('Y-m-d', strtotime('+14 days')),
                'slot_minutes' => 30,
            ];
            $r = portal_api_call('GET', "/portal/doctors/$docId/slots", $q);
            return $r['ok'] ? ['ok' => true, 'slots' => $r['data']] : ['ok' => false, 'error' => $r['message'] ?? 'Error'];

        case 'create_appointment':
            $payload = array_intersect_key($args, array_flip(['name','cedula','email','phone','doctor_id','appointment_time','notes']));
            // Bypass de hCaptcha para llamadas server-to-server desde el chat IA.
            // El paciente no puede resolver un captcha dentro del chat; el secreto
            // se compara con `ai_chat_secret` del settings en la API interna.
            // Se define en includes/config.local.php; si no existe, la API rechaza
            // por captcha igual que cualquier otro cliente.
            if (defined('PORTAL_AI_CHAT_SECRET') && PORTAL_AI_CHAT_SECRET !== '') {
                $payload['_ai_secret'] = PORTAL_AI_CHAT_SECRET;
            }
            $r = portal_api_call('POST', '/portal/guest/appointments', $payload);
            if (!$r['ok']) {
                $errs = $r['errors'] ? ' · ' . json_encode($r['errors'], JSON_UNESCAPED_UNICODE) : '';
                return ['ok' => false, 'error' => ($r['message'] ?? 'Error') . $errs];
            }
            return ['ok' => true, 'appointment' => $r['data']];
    }
    return ['ok' => false, 'error' => "Tool '$name' no implementado."];
}

function ai_call_openai(array $messages, array $settings, bool $withTools = true): array
{
    $payload = [
        'model' => $settings['model'],
        'messages' => $messages,
        'temperature' => $settings['temperature'],
        'max_tokens' => $settings['max_tokens'],
    ];
    if ($withTools) {
        $payload['tools'] = ai_tools_definition();
        $payload['tool_choice'] = 'auto';
    }

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

    $msg = $data['choices'][0]['message'] ?? [];
    return [
        'ok'         => true,
        'message'    => $msg, // mensaje completo (puede tener content y/o tool_calls)
        'content'    => $msg['content'] ?? '',
        'tool_calls' => $msg['tool_calls'] ?? null,
        'usage'      => $data['usage'] ?? null,
    ];
}

/**
 * Conversación completa con auto-resolución de tool calls.
 * Max $maxHops llamadas a OpenAI para evitar loops infinitos.
 *
 * @return array { ok, content, usage, tool_log }
 */
function ai_run_conversation(array $messages, array $settings, int $maxHops = 6): array
{
    $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
    $toolLog = [];

    for ($hop = 0; $hop < $maxHops; $hop++) {
        $resp = ai_call_openai($messages, $settings, true);
        if (!$resp['ok']) return $resp;

        if (!empty($resp['usage'])) {
            foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
                $totalUsage[$k] += (int)($resp['usage'][$k] ?? 0);
            }
        }

        $toolCalls = $resp['tool_calls'] ?? null;

        // No hay tools que ejecutar → respuesta final
        if (empty($toolCalls)) {
            return [
                'ok'       => true,
                'content'  => (string)$resp['content'],
                'usage'    => $totalUsage,
                'tool_log' => $toolLog,
            ];
        }

        // Agregar el assistant message con tool_calls al historial
        $messages[] = $resp['message'];

        // Ejecutar cada tool call y agregar resultado
        foreach ($toolCalls as $call) {
            $fnName = $call['function']['name'] ?? '';
            $argsJson = $call['function']['arguments'] ?? '{}';
            $args = json_decode($argsJson, true) ?: [];

            $result = ai_execute_tool($fnName, $args);
            $toolLog[] = ['name' => $fnName, 'args' => $args, 'result' => $result];

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $call['id'],
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    return [
        'ok'      => false,
        'error'   => "Se alcanzó el máximo de iteraciones de tools ($maxHops). Pregunta al paciente que llame al hospital.",
        'usage'   => $totalUsage,
        'tool_log'=> $toolLog,
    ];
}
