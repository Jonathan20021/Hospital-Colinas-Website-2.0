<?php
/**
 * Colinas IA — Motor de respuestas DETERMINISTA (sin LLM / sin OpenAI).
 *
 * Reemplaza el cerebro de IA del widget por un sistema de reglas/intenciones
 * en PHP que consulta los MISMOS datos reales (directorio de especialidades y
 * médicos) que usan la agenda y el directorio público. Nunca alucina: si no
 * entiende, ofrece opciones y deriva a atención humana.
 *
 * Contrato: bot_reply() devuelve un string de texto que el frontend
 * (assets/js/colinas-ai.js) ya sabe renderizar, con estas etiquetas:
 *   [[doctor:slug]]            → tarjeta visual del médico (slug del directorio)
 *   [[link:destino|texto]]     → enlace interno
 *   [[action:tipo|texto]]      → botón (appointment|call|directory|whatsapp|email)
 *   [[scroll:#seccion]]        → scroll a una sección de la home
 *   [[suggest:a|b|c]]          → chips de seguimiento (máx 4)
 */

require_once __DIR__ . '/doctors.php'; // public_doctors() / public_specialties()

/* ============================================================
 * Normalización y utilidades de texto
 * ============================================================ */

function bot_norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);
    $s = preg_replace('/[^a-z0-9ñ\s]/u', ' ', $s); // signos fuera
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/** ¿Alguna de las agujas aparece en el texto (ya normalizado)? */
function bot_has(string $hayNorm, array $needles): bool
{
    foreach ($needles as $n) {
        if ($n !== '' && mb_strpos($hayNorm, $n) !== false) return true;
    }
    return false;
}

/** "CARDIOLOGÍA" / "ginecologia y obstetricia" → "Cardiología" / "Ginecología y Obstetricia" */
function bot_title(string $s): string
{
    $t = mb_convert_case(mb_strtolower(trim($s), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    // Conectores en minúscula (excepto si fueran la primera palabra).
    $t = preg_replace_callback('/\s+(Y|E|De|Del|La|El|Las|Los|En)\b/u',
        fn($m) => ' ' . mb_strtolower($m[1], 'UTF-8'), $t);
    return $t;
}

/* ============================================================
 * Catálogos (con caché 1h vía portal_directory)
 * ============================================================ */

function bot_specialties(array $services): array
{
    $sp = function_exists('public_specialties') ? public_specialties($services) : [];
    return is_array($sp) ? $sp : [];
}

function bot_doctors(array $services, array $assets): array
{
    $d = function_exists('public_doctors') ? public_doctors($services, $assets) : [];
    return is_array($d) ? $d : [];
}

/* ============================================================
 * Matching de especialidad
 * ============================================================ */

/** Raíces buscables del nombre de una especialidad ("cardiologia" → ["cardiologia","cardiolog"]). */
function bot_specialty_needles(string $nameNorm): array
{
    $stop = ['y', 'e', 'de', 'la', 'el', 'del', 'clinica', 'clinico', 'general'];
    $needles = [$nameNorm]; // nombre completo (desempata por longitud)
    foreach (preg_split('/\s+/', $nameNorm) as $w) {
        if (in_array($w, $stop, true) || mb_strlen($w) < 5) continue;
        $root = preg_replace('/(ica|gia|ia|a|o)$/u', '', $w);
        $needles[] = (mb_strlen($root) >= 5) ? $root : $w;
    }
    return array_values(array_unique($needles));
}

/** Sinónimos / síntomas comunes → raíz que debe aparecer en el nombre de la especialidad. */
function bot_specialty_synonyms(): array
{
    return [
        'cardiolog' => 'cardiolog', 'corazon' => 'cardiolog', 'cardiaco' => 'cardiolog',
        'presion alta' => 'cardiolog', 'hipertension' => 'cardiolog', 'palpitacion' => 'cardiolog',
        'pediatr' => 'pediatr', 'nino' => 'pediatr', 'nina' => 'pediatr', 'bebe' => 'pediatr', 'infantil' => 'pediatr',
        'dermatolog' => 'dermatolog', 'piel' => 'dermatolog', 'acne' => 'dermatolog', 'lunar' => 'dermatolog', 'sarpullido' => 'dermatolog',
        'ginecolog' => 'ginecolog', 'obstetr' => 'obstetr', 'embarazo' => 'ginecolog', 'menstrua' => 'ginecolog', 'matriz' => 'ginecolog', 'parto' => 'ginecolog', 'papanicolau' => 'ginecolog',
        'ortoped' => 'ortoped', 'traumatolog' => 'traumatolog', 'hueso' => 'ortoped', 'fractura' => 'ortoped', 'rodilla' => 'ortoped', 'articulacion' => 'ortoped', 'columna' => 'ortoped',
        'oftalmolog' => 'oftalmolog', 'ojo' => 'oftalmolog', 'vista' => 'oftalmolog',
        'neurolog' => 'neurolog', 'migrana' => 'neurolog', 'cerebro' => 'neurolog',
        'urolog' => 'urolog', 'prostata' => 'urolog', 'vejiga' => 'urolog', 'orina' => 'urolog',
        'otorrino' => 'otorrino', 'garganta' => 'otorrino', 'oido' => 'otorrino', 'nariz' => 'otorrino', 'amigdala' => 'otorrino', 'sinusitis' => 'otorrino',
        'gastro' => 'gastro', 'estomago' => 'gastro', 'digestiv' => 'gastro', 'colon' => 'gastro', 'gastritis' => 'gastro',
        'endocrin' => 'endocrin', 'diabetes' => 'endocrin', 'tiroides' => 'endocrin', 'hormona' => 'endocrin',
        'neumolog' => 'neumolog', 'pulmon' => 'neumolog', 'asma' => 'neumolog', 'respirator' => 'neumolog',
        'psicolog' => 'psicolog', 'psiquiatr' => 'psiquiatr', 'ansiedad' => 'psicolog', 'depresion' => 'psicolog', 'estres' => 'psicolog',
        'nefrolog' => 'nefrolog', 'renal' => 'nefrolog',
        'oncolog' => 'oncolog', 'cancer' => 'oncolog', 'tumor' => 'oncolog',
        'reumatolog' => 'reumatolog', 'artritis' => 'reumatolog',
        'hematolog' => 'hematolog',
        'alergolog' => 'alergolog', 'alergia' => 'alergolog', 'inmunolog' => 'alergolog',
        'geriatr' => 'geriatr', 'anciano' => 'geriatr', 'adulto mayor' => 'geriatr',
        'internista' => 'interna', 'medicina interna' => 'interna',
        'nutricion' => 'nutricion', 'nutriolog' => 'nutricion', 'dieta' => 'nutricion',
        'infectolog' => 'infectolog',
        'neonatolog' => 'neonatolog',
        'maxilofacial' => 'maxilofacial',
        'anestesiolog' => 'anestesiolog',
        'neurocirug' => 'neurocirug',
        'rehabilitacion' => 'rehabilitacion', 'fisiatr' => 'rehabilitacion', 'fisioterapia' => 'rehabilitacion',
    ];
}

/** Devuelve la especialidad que mejor matchea el mensaje, o null. */
function bot_match_specialty(string $msgNorm, array $specialties): ?array
{
    $best = null;
    $bestLen = 0;
    foreach ($specialties as $sp) {
        $nameNorm = bot_norm($sp['name'] ?? '');
        if ($nameNorm === '') continue;
        foreach (bot_specialty_needles($nameNorm) as $nd) {
            if (mb_strpos($msgNorm, $nd) !== false && mb_strlen($nd) > $bestLen) {
                $best = $sp;
                $bestLen = mb_strlen($nd);
            }
        }
    }
    if ($best) return $best;

    foreach (bot_specialty_synonyms() as $syn => $root) {
        if (mb_strpos($msgNorm, $syn) !== false) {
            foreach ($specialties as $sp) {
                if (mb_strpos(bot_norm($sp['name'] ?? ''), $root) !== false) return $sp;
            }
        }
    }
    return null;
}

/** Médicos de una especialidad (por specialty_id, fallback por nombre). */
function bot_doctors_in_specialty(array $sp, array $allDocs): array
{
    $id = (int) ($sp['id'] ?? 0);
    $docs = $id ? array_values(array_filter($allDocs, fn($d) => (int) ($d['specialty_id'] ?? 0) === $id)) : [];
    if (!$docs) {
        $spn = bot_norm($sp['name'] ?? '');
        $docs = array_values(array_filter($allDocs, fn($d) => bot_norm($d['specialty'] ?? '') === $spn));
    }
    return $docs;
}

/* ============================================================
 * Matching de médico por nombre
 * ============================================================ */

/**
 * Busca médicos cuyo nombre aparece en el mensaje.
 * $minLen: longitud mínima del token para considerarlo (4 si hay pista "dr/dra", 5 si no).
 */
function bot_match_doctors_by_name(string $msgNorm, array $allDocs, int $minLen): array
{
    $skip = ['de', 'la', 'el', 'los', 'las', 'del', 'dr', 'dra', 'doctor', 'doctora', 'san', 'santa'];
    $matches = [];
    foreach ($allDocs as $d) {
        $nameNorm = bot_norm($d['name'] ?? '');
        if ($nameNorm === '') continue;
        foreach (preg_split('/\s+/', $nameNorm) as $tok) {
            if (mb_strlen($tok) < $minLen || in_array($tok, $skip, true)) continue;
            if (preg_match('/\b' . preg_quote($tok, '/') . '\b/u', $msgNorm)) {
                $matches[] = $d;
                break;
            }
        }
    }
    return $matches;
}

/* ============================================================
 * Constructores de respuesta
 * ============================================================ */

function bot_resp_emergency(array $contact): string
{
    $p = $contact['phone'] ?? '(809) 806-0444';
    return "⚠️ Esto puede ser una **emergencia médica**. Por favor llama de inmediato al **{$p}** "
        . "o acude a nuestra **Emergencia 24/7** (adulto y pediátrica). No esperes.\n\n"
        . "No puedo darte indicaciones clínicas, pero el equipo de emergencias te atenderá enseguida.\n"
        . "[[action:call|Llamar ahora]]"
        . "[[suggest:¿Cómo llego al hospital?|Ver ubicación y mapa]]";
}

function bot_resp_greeting(): string
{
    return "¡Hola! 👋 Soy **Colinas IA**, tu asistente del Hospital General Las Colinas. ¿En qué puedo ayudarte hoy?\n"
        . "[[suggest:Buscar un especialista|¿Qué servicios ofrecen?|¿Cómo agendo una cita?|Horarios y ubicación]]";
}

function bot_resp_thanks(): string
{
    return "¡Con gusto! 💚 Si necesitas algo más, aquí estaré.\n"
        . "[[suggest:Buscar un especialista|Agendar una cita|Hablar por WhatsApp]]";
}

function bot_resp_doctors_for_specialty(array $sp, array $allDocs): string
{
    $name = bot_title($sp['name'] ?? 'esta especialidad');
    $docs = bot_doctors_in_specialty($sp, $allDocs);

    if (!$docs) {
        return "Por ahora no veo especialistas con agenda en línea en **{$name}**. "
            . "Puedo ayudarte con otra área, o llámanos y con gusto te orientamos.\n"
            . "[[action:call|Llamar al hospital]][[link:directorio-medico|Ver directorio completo]]"
            . "[[suggest:Ver otras especialidades|Agendar una cita|¿Con qué seguros trabajan?]]";
    }

    $total = count($docs);
    $show = array_slice($docs, 0, 3);
    $intro = $total === 1
        ? "En **{$name}** contamos con este especialista:"
        : "En **{$name}** contamos con {$total} especialistas. Te muestro algunos:";

    $out = $intro . "\n";
    foreach ($show as $d) {
        if (!empty($d['slug'])) $out .= "[[doctor:{$d['slug']}]]";
    }
    if ($total > count($show)) {
        $out .= "\nHay " . ($total - count($show)) . " más en el directorio.";
    }
    $out .= "\nPuedes ver su perfil o **agendar** directamente. 📅";
    $out .= "[[action:appointment|Agendar cita]][[link:directorio-medico|Ver todos]]";
    $out .= "[[suggest:Ver otra especialidad|¿Con qué seguros trabajan?|Horarios y ubicación]]";
    return $out;
}

function bot_resp_doctor_matches(array $docs): string
{
    $show = array_slice($docs, 0, 3);
    if (count($docs) === 1) {
        $out = "Claro, este es el especialista:\n";
    } else {
        $out = "Encontré estos médicos:\n";
    }
    foreach ($show as $d) {
        if (!empty($d['slug'])) $out .= "[[doctor:{$d['slug']}]]";
    }
    $out .= "\nPuedes ver su perfil o **agendar una cita**. 📅";
    $out .= "[[action:appointment|Agendar cita]]";
    $out .= "[[suggest:Ver más especialistas|¿Con qué seguros trabajan?|Cómo agendar]]";
    return $out;
}

function bot_resp_list_specialties(array $specialties, array $services): string
{
    $names = [];
    foreach ($specialties as $sp) {
        if (!empty($sp['name'])) $names[] = bot_title($sp['name']);
    }
    if (!$names) {
        $names = array_map('bot_title', $services['consultas']['items'] ?? []);
    }
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    $list = '';
    foreach ($names as $n) $list .= "- {$n}\n";

    return "Estas son algunas de nuestras **especialidades** disponibles:\n"
        . $list
        . "\nDime cuál te interesa (por ejemplo: _\"necesito un cardiólogo\"_) y te muestro los médicos disponibles. 🩺"
        . "[[link:directorio-medico|Abrir directorio médico]]"
        . "[[suggest:Necesito un cardiólogo|Pediatría|Ginecología|Agendar una cita]]";
}

function bot_resp_services(array $services): string
{
    $out = "En el **Hospital General Las Colinas** ofrecemos:\n";
    foreach ($services as $group) {
        $label = $group['label'] ?? '';
        $items = array_slice($group['items'] ?? [], 0, 5);
        $sample = implode(', ', $items);
        if ($label) $out .= "- **{$label}**" . ($sample ? ": {$sample}…" : '') . "\n";
    }
    $out .= "[[link:#servicios|Ver todos los servicios]]";
    $out .= "[[suggest:Buscar un especialista|¿Cómo agendo una cita?|Horarios y ubicación]]";
    return $out;
}

function bot_resp_location(array $contact): string
{
    $addr = $contact['address'] ?? 'Plaza Colinas Mall, Santiago';
    $phone = $contact['phone'] ?? '(809) 806-0444';
    return "📍 Estamos en **{$addr}**, conectados a Colinas Mall.\n"
        . "🚑 Nuestra **Emergencia funciona 24/7** (adulto y pediátrica), todos los días.\n"
        . "🕒 Para el horario de consulta de una especialidad o médico, llámanos o revisa el perfil del especialista.\n"
        . "📞 Teléfono: **{$phone}**."
        . "[[link:#contacto|Ver mapa y contacto]][[action:call|Llamar]]"
        . "[[suggest:Buscar un especialista|¿Con qué seguros trabajan?|Cómo agendar]]";
}

function bot_resp_insurers(array $insurers, array $contact): string
{
    $out = "Trabajamos con las siguientes **ARS / seguros**:\n";
    foreach ($insurers as $ins) {
        if (!empty($ins['name'])) $out .= "- {$ins['name']}\n";
    }
    $out .= "\nPara **autorizaciones** o detalles de cobertura y copagos, escríbenos por WhatsApp o llama a admisión. "
        . "No manejo montos ni porcentajes de cobertura por aquí.";
    $out .= "[[action:whatsapp|Autorizar por WhatsApp]][[action:call|Llamar a admisión]]";
    $out .= "[[suggest:Buscar un especialista|Cómo agendar una cita|Horarios y ubicación]]";
    return $out;
}

function bot_resp_appointment(): string
{
    return "Agendar es muy fácil y **no necesitas crear cuenta** (toma menos de 2 minutos):\n"
        . "1. Elige la **especialidad**.\n"
        . "2. Elige el **médico**.\n"
        . "3. Selecciona **fecha y hora** y deja tus datos.\n\n"
        . "¿Quieres que te ayude a encontrar un especialista primero, o prefieres ir directo al formulario?"
        . "[[action:appointment|Agendar ahora]][[link:agendar|Abrir formulario de citas]]"
        . "[[suggest:Buscar un especialista|¿Con qué seguros trabajan?|Horarios y ubicación]]";
}

function bot_resp_contact(array $contact): string
{
    $phone = $contact['phone'] ?? '(809) 806-0444';
    $wa = $contact['whatsapp_phone'] ?? '(809) 501-2002';
    $email = $contact['email'] ?? 'info@colinashospital.com';
    return "Puedes contactarnos por:\n"
        . "- 📞 Teléfono: **{$phone}**\n"
        . "- 💬 WhatsApp (call center): **{$wa}**\n"
        . "- ✉️ Correo: **{$email}**"
        . "[[action:call|Llamar]][[action:whatsapp|WhatsApp]]"
        . "[[suggest:Buscar un especialista|Agendar una cita|Horarios y ubicación]]";
}

function bot_resp_news(): string
{
    $out = '';
    if (function_exists('news_query_published')) {
        $latest = news_query_published(4, 0);
        if ($latest) {
            $out = "Estas son nuestras **últimas noticias**:\n";
            foreach ($latest as $n) {
                $title = $n['title'] ?? 'Noticia';
                $slug = $n['slug'] ?? '';
                $out .= $slug ? "- [[link:noticias/{$slug}|{$title}]]\n" : "- {$title}\n";
            }
        }
    }
    if ($out === '') {
        $out = "Puedes ver todas nuestras novedades en la **sala de prensa**.\n";
    }
    $out .= "[[link:noticias|Ver sala de prensa]]";
    $out .= "[[suggest:Buscar un especialista|¿Qué servicios ofrecen?|Agendar una cita]]";
    return $out;
}

function bot_resp_scroll(string $section, string $label): string
{
    return "Te llevo a la sección de **{$label}**. 👇"
        . "[[scroll:{$section}]]"
        . "[[suggest:Buscar un especialista|Ver servicios|Agendar una cita]]";
}

function bot_resp_fallback(array $contact): string
{
    return "No estoy seguro de haber entendido. 🤔 Puedo ayudarte con:\n"
        . "- 🔎 Buscar un **especialista** por área o nombre.\n"
        . "- 📅 **Agendar** una cita.\n"
        . "- 🏥 Información de **servicios, horarios o seguros**.\n\n"
        . "Si prefieres atención humana, escríbenos por WhatsApp o llámanos."
        . "[[action:whatsapp|WhatsApp]][[action:call|Llamar]]"
        . "[[suggest:Buscar un especialista|¿Qué servicios ofrecen?|¿Cómo agendo una cita?]]";
}

/* ============================================================
 * Router principal
 * ============================================================ */

function bot_last_user_message(array $messages): string
{
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        $m = $messages[$i];
        if (is_array($m) && ($m['role'] ?? '') === 'user') {
            return trim((string) ($m['content'] ?? ''));
        }
    }
    return '';
}

/**
 * Punto de entrada. Devuelve el texto de respuesta (con tags) para el frontend.
 */
function bot_reply(array $messages, array $services, array $assets, array $contact, array $insurers): string
{
    $raw = bot_last_user_message($messages);
    $msg = bot_norm($raw);

    if ($msg === '') {
        return bot_resp_greeting();
    }

    // 1) EMERGENCIA (máxima prioridad — seguridad del paciente)
    $emergency = [
        'dolor de pecho', 'dolor en el pecho', 'dolor toracico', 'me duele el pecho',
        'no puedo respirar', 'dificultad para respirar', 'me falta el aire', 'falta de aire',
        'sangrado', 'sangra mucho', 'hemorragia', 'desmay', 'inconsciente', 'no responde',
        'convulsi', 'derrame', 'infarto', 'paralisis', 'no siento el', 'intoxica', 'envenena',
        'me quiero morir', 'suicid', 'accidente grave', 'fractura expuesta', 'quemadura grave',
        'no se mueve', 'se esta ahogando', 'ataque al corazon',
    ];
    if (bot_has($msg, $emergency)) {
        return bot_resp_emergency($contact);
    }

    // 2) Agradecimiento / despedida (mensaje corto)
    if (bot_has($msg, ['gracias', 'muchas gracias', 'adios', 'hasta luego', 'bye', 'chao'])
        && str_word_count($msg) <= 4) {
        return bot_resp_thanks();
    }

    // 3) Saludo (mensaje corto que es básicamente un saludo)
    if (bot_has($msg, ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'saludos', 'que tal'])
        && str_word_count($msg) <= 4) {
        return bot_resp_greeting();
    }

    $specialties = bot_specialties($services);
    $allDocs = bot_doctors($services, $assets);

    // 4) Médico por NOMBRE con pista explícita (dr/dra/doctor/doctora/"con ")
    $hasNameCue = (bool) preg_match('/\b(dr|dra|doctor|doctora)\b/u', $msg) || mb_strpos($msg, ' con ') !== false;
    if ($hasNameCue) {
        $named = bot_match_doctors_by_name($msg, $allDocs, 4);
        if ($named) return bot_resp_doctor_matches($named);
    }

    // 5) Especialidad concreta (incluye síntomas mapeados) → médicos de esa especialidad
    $sp = bot_match_specialty($msg, $specialties);
    if ($sp) {
        return bot_resp_doctors_for_specialty($sp, $allDocs);
    }

    // 6) Médico por nombre sin pista explícita (match fuerte, token largo)
    $named = bot_match_doctors_by_name($msg, $allDocs, 5);
    if ($named) {
        return bot_resp_doctor_matches($named);
    }

    // 7) Enrutado por palabras clave
    if (bot_has($msg, ['agend', 'cita', 'reserv', 'turno'])) {
        return bot_resp_appointment();
    }
    if (bot_has($msg, ['seguro', 'ars', 'aseguradora', 'cobertura', 'convenio', 'humano', 'senasa', 'primera', 'semma', 'uasd'])) {
        return bot_resp_insurers($insurers, $contact);
    }
    if (bot_has($msg, ['servicio', 'que ofrecen', 'que hacen', 'tratamiento', 'estudio', 'laboratorio', 'resonancia', 'tomografia', 'sonografia', 'mamografia', 'imagenes', 'diagnostic'])) {
        return bot_resp_services($services);
    }
    if (bot_has($msg, ['noticia', 'novedad', 'evento', 'prensa'])) {
        return bot_resp_news();
    }
    if (bot_has($msg, ['horario', 'ubicacion', 'direccion', 'donde estan', 'donde queda', 'como llego', 'como llegar', 'mapa', 'parqueo', 'estacionamiento'])) {
        return bot_resp_location($contact);
    }
    if (bot_has($msg, ['telefono', 'numero', 'contacto', 'whatsapp', 'correo', 'email', 'llamar', 'escribir'])) {
        return bot_resp_contact($contact);
    }
    if (bot_has($msg, ['instalacion', 'instalaciones', 'conocer instalaciones'])) {
        return bot_resp_scroll('#instalaciones', 'Instalaciones');
    }
    if (bot_has($msg, ['nosotros', 'quienes son', 'quienes somos', 'sobre el hospital'])) {
        return bot_resp_scroll('#nosotros', 'Nosotros');
    }
    if (bot_has($msg, ['liderazgo', 'director', 'gerencia'])) {
        return bot_resp_scroll('#liderazgo', 'Liderazgo institucional');
    }
    if (bot_has($msg, ['tour', 'recorrido', 'guia', 'muestrame', 'recorre'])) {
        return bot_resp_scroll('#nosotros', 'Nosotros') ;
    }

    // 8) Genérico: pide médico/especialista/área sin concretar → lista de especialidades
    if (bot_has($msg, ['especial', 'medico', 'doctor', 'area', 'consulta', 'buscar'])) {
        return bot_resp_list_specialties($specialties, $services);
    }

    // 9) Fallback
    return bot_resp_fallback($contact);
}
