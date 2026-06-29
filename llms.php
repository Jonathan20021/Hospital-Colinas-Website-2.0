<?php
/**
 * llms.txt / llms-full.txt — Guía para asistentes y buscadores con IA.
 *
 * Sigue la convención emergente https://llmstxt.org : un documento en Markdown,
 * legible por modelos de lenguaje, que resume la institución y enlaza el
 * contenido más relevante. Se genera de forma DINÁMICA (igual que sitemap.php)
 * para reflejar siempre los servicios, especialidades, médicos y noticias
 * vigentes del Hospital General Las Colinas.
 *
 * Rutas (vía .htaccess):
 *   /llms.txt        -> índice conciso y curado
 *   /llms-full.txt   -> versión completa (más detalle para ingestión por IA)
 */

require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/doctors.php';
require __DIR__ . '/includes/news.php';
require __DIR__ . '/includes/content.php';

// Detecta el modo "completo" por query (?full=1) o por la URL solicitada.
$full = (($_GET['full'] ?? '') === '1')
    || (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'llms-full') !== false);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: index, follow');
header('Cache-Control: public, max-age=3600');

// --- Datos vivos -----------------------------------------------------------
news_ensure_schema();
$doctors       = public_doctors($services, $assets);
$specialties   = public_specialties($services);
$newsItems     = news_query_published($full ? 25 : 6, 0);
$servicePages  = service_pages_catalog($services, $assets);
$today         = date('Y-m-d');

$site      = rtrim(absolute_url(), '/');                 // https://colinashospital.com
$u         = static fn (string $path = ''): string => rtrim(absolute_url($path), '/');
$nDoctors  = count($doctors);
$nSpec     = max(count($specialties), count($services['consultas']['items']));

// Pequeño helper para imprimir líneas.
$line = static function (string $text = ''): void { echo $text . "\n"; };

// ===========================================================================
// ENCABEZADO COMÚN
// ===========================================================================
$line('# Hospital General Las Colinas (HGLC)');
$line();
$line('> Hospital privado en Santiago de los Caballeros, República Dominicana. '
    . 'Ofrece emergencias 24/7 para adultos y niños, ' . $nSpec . '+ especialidades médicas, '
    . '55+ consultorios, bloque quirúrgico, cuidados intensivos (adulto, pediátrico y neonatal), '
    . 'diagnóstico por imagen (tomografía, resonancia, mamografía, sonografía) y laboratorio clínico. '
    . 'Acepta las principales aseguradoras (ARS) del país y dispone de servicios en línea: '
    . 'agendar citas, teleconsulta, autorización de estudios, consulta de resultados y portales '
    . 'del paciente y del médico.');
$line();
$line('Datos institucionales clave:');
$line('- Nombre: Hospital General Las Colinas (HGLC). Razón social: HOSPITAL GENERAL LAS COLINAS, SAS — RNC 131293281.');
$line('- Tipo: Hospital general privado de tercer nivel.');
$line('- Ubicación: ' . $contact['address'] . ' Conectado a Colinas Mall.');
$line('- Ciudad / Región: Santiago de los Caballeros, provincia Santiago, región Cibao, República Dominicana.');
$line('- Teléfono y emergencias (24/7): ' . $contact['phone'] . '.');
$line('- WhatsApp / Call center: ' . $contact['whatsapp_phone'] . ' (' . $contact['whatsapp'] . ').');
$line('- Correo: ' . $contact['email'] . '.');
$line('- Sitio web: ' . $site . '.');
$line('- Idioma de atención y contenido: español (es-DO).');
$line('- Horario de emergencias: 24 horas, los 7 días de la semana.');
$line('- Mapa: ' . $contact['maps']);
$line('- Redes: Instagram ' . $contact['instagram'] . ' · Facebook ' . $contact['facebook']);
$line();

// ===========================================================================
// PÁGINAS PRINCIPALES
// ===========================================================================
$line('## Páginas principales');
$line('- [Inicio](' . $u() . '/): visión general del hospital, búsqueda de atención, servicios y accesos rápidos.');
$line('- [Servicios](' . $u('servicios') . '): catálogo completo de consulta especializada, diagnóstico, cirugía y servicios clínicos.');
$line('- [Directorio médico](' . $u('directorio-medico') . '): ' . $nDoctors . ' especialistas en ' . $nSpec . ' especialidades, con perfiles y agenda en línea.');
$line('- [Noticias](' . $u('noticias') . '): sala de prensa institucional, alianzas y novedades de servicios.');
$line('- [Repositorio digital](' . $u('repositorio') . '): protocolos clínicos y guías de referencia.');
$line('- [Nosotros](' . $u('nosotros') . '): historia, misión, visión y valores del hospital.');
$line('- [Instalaciones](' . $u('instalaciones') . '): infraestructura, niveles, tecnología y capacidad clínica.');
$line('- [Liderazgo institucional](' . $u('liderazgo-institucional') . '): dirección general y equipo gerencial.');
$line('- [Contacto](' . $u('contacto') . '): dirección, teléfono, WhatsApp, mapa y cómo llegar.');
$line();

// ===========================================================================
// SERVICIOS EN LÍNEA
// ===========================================================================
$line('## Servicios en línea (pacientes)');
$line('- [Agendar cita](' . $u('agendar') . '): solicitud de cita con especialista; el equipo confirma disponibilidad.');
$line('- [Teleconsulta](' . $u('teleconsulta') . '): consulta médica por video.');
$line('- [Autorizar / solicitar estudios](' . $u('solicitar-estudios') . '): gestión de autorización de imágenes y laboratorio con tu ARS.');
$line('- [Ver resultados](' . $u('ver-resultados') . '): acceso a resultados de estudios.');
$line('- [Verificar receta](' . $u('verificar-receta') . '): validación de recetas emitidas por el hospital.');
$line('- [Verificar certificado](' . $u('verificar-certificado') . '): validación de certificados médicos con firma y QR.');
$line('- [Portal del paciente](' . $u('portal/login.php') . '): citas, recetas, resultados de laboratorio y mensajería segura.');
$line('- [Portal médico](' . $u('portal-medico/login.php') . '): herramientas clínicas para profesionales del hospital.');
$line();

// ===========================================================================
// ESPECIALIDADES Y SERVICIOS CLÍNICOS
// ===========================================================================
$line('## Especialidades y servicios clínicos');
foreach ($services as $group) {
    $line();
    $line('### ' . $group['label']);
    $line($group['description']);
    foreach ($group['items'] as $item) {
        $line('- ' . $item);
    }
}
$line();

// ===========================================================================
// SEGUROS / ARS
// ===========================================================================
$line('## Seguros y aseguradoras (ARS) aceptadas');
$line('El hospital trabaja con las principales ARS del país, con cobertura ambulatoria y hospitalaria. '
    . 'Para dudas de cobertura: WhatsApp ' . $contact['whatsapp_phone'] . '.');
foreach ($insurers as $insurer) {
    $line('- ' . $insurer['name']);
}
$line('Más información: [Seguros aceptados](' . $u('seguros-aceptados') . ').');
$line();

// ===========================================================================
// DIRECTORIO MÉDICO
// ===========================================================================
$line('## Directorio médico');
if ($full) {
    $line('Listado completo de especialistas (' . $nDoctors . '):');
    foreach ($doctors as $doc) {
        $office = !empty($doc['office']) ? ' — ' . $doc['office'] : '';
        $line('- ' . $doc['name'] . ' — ' . ($doc['specialty'] ?? '') . $office
            . ' — ' . $u('medico/' . $doc['slug']));
    }
} else {
    $line('Directorio con ' . $nDoctors . ' especialistas. Algunos perfiles:');
    foreach (array_slice($doctors, 0, 12) as $doc) {
        $line('- [' . $doc['name'] . ' (' . ($doc['specialty'] ?? '') . ')](' . $u('medico/' . $doc['slug']) . ')');
    }
    $line('Ver todos: [Directorio médico](' . $u('directorio-medico') . ').');
}
$line();

// ===========================================================================
// NOTICIAS
// ===========================================================================
if (!empty($newsItems)) {
    $line('## Noticias recientes');
    foreach ($newsItems as $n) {
        $date = date('Y-m-d', strtotime($n['published_at']));
        $line('- [' . $n['title'] . '](' . $u('noticias/' . $n['slug']) . ') — ' . $date
            . ($full && !empty($n['excerpt']) ? ' — ' . trim((string) $n['excerpt']) : ''));
    }
    $line();
}

// ===========================================================================
// SECCIÓN AMPLIADA (solo en llms-full.txt)
// ===========================================================================
if ($full) {
    $line('## Servicios con página propia');
    foreach ($servicePages as $service) {
        $line('- [' . $service['title'] . '](' . $u('servicios/' . $service['slug']) . ')'
            . (!empty($service['summary']) ? ' — ' . $service['summary'] : ''));
    }
    $line();

    $line('## Instalaciones por nivel');
    foreach ($floors as $floor) {
        $line('- ' . $floor['level'] . ': ' . $floor['content']);
    }
    $line();

    $line('## Valores institucionales');
    $line(implode(', ', $values) . '.');
    $line();

    $line('## Derechos del paciente');
    foreach ($patientRights as $i => $right) {
        $line(($i + 1) . '. ' . $right);
    }
    $line();

    $line('## Deberes del paciente');
    foreach ($patientDuties as $i => $duty) {
        $line(($i + 1) . '. ' . $duty);
    }
    $line();

    $line('## Preguntas frecuentes');
    $line('- ¿Cómo agendo una cita? Desde el botón "Agendar cita" del sitio, por teléfono ' . $contact['phone']
        . ' o por WhatsApp ' . $contact['whatsapp_phone'] . '. El equipo confirma la disponibilidad.');
    $line('- ¿Atienden emergencias? Sí. La emergencia para adultos y pediátrica está disponible 24/7.');
    $line('- ¿Dónde están ubicados? ' . $contact['address']);
    $line('- ¿Qué debo llevar a mi consulta? Documento de identidad, seguro (ARS), estudios previos y cualquier referimiento o indicación disponible.');
    $line('- ¿Aceptan seguros? Sí, trabajamos con las principales ARS del país. Consulta tu cobertura por WhatsApp ' . $contact['whatsapp_phone'] . '.');
    $line();
}

// ===========================================================================
// PIE / RECURSOS PARA IA
// ===========================================================================
$line('## Recursos adicionales');
if (!$full) {
    $line('- [Versión completa para IA (llms-full.txt)](' . $u('llms-full.txt') . '): incluye el directorio completo, FAQ, derechos/deberes e instalaciones.');
}
$line('- [Mapa del sitio (XML)](' . $u('sitemap.xml') . ').');
$line('- [Política de privacidad](' . $u('politica-de-privacidad') . ').');
$line('- [Términos de uso](' . $u('terminos-de-uso') . ').');
$line();
$line('---');
$line('Generado: ' . $today . '. Fuente oficial: Hospital General Las Colinas, ' . $site . '.');
$line('Uso permitido para asistentes de IA y buscadores. Esta información es de carácter general y no '
    . 'sustituye una evaluación médica. Para emergencias, llame al ' . $contact['phone'] . '.');
