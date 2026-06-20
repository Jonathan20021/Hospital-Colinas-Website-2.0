<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/doctors.php';
require __DIR__ . '/includes/news.php';
require __DIR__ . '/includes/public-layout.php';

news_ensure_schema();

$year = date('Y');
$featuredDoctors = array_slice(public_doctors($services, $assets), 0, 4);
$latestNews = news_query_published(3, 0);
$totalServices = service_count($services);
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

$clinicalCategories = [
    ['label' => 'Cardiología', 'icon' => 'heart-pulse', 'query' => 'Cardiología'],
    ['label' => 'Oncología', 'icon' => 'ribbon', 'query' => 'Oncología'],
    ['label' => 'Ortopedia', 'icon' => 'bone', 'query' => 'Ortopedia'],
    ['label' => 'Ginecología', 'icon' => 'venus', 'query' => 'Ginecología'],
    ['label' => 'Neurociencias', 'icon' => 'brain', 'query' => 'Neurología'],
    ['label' => 'Urología', 'icon' => 'droplets', 'query' => 'Urología'],
    ['label' => 'Pediatría', 'icon' => 'baby', 'query' => 'Pediatría'],
    ['label' => 'Ver todas', 'icon' => 'layout-grid', 'query' => ''],
];

$taskCards = [
    [
        'icon' => 'user-round-search',
        'title' => 'Buscar especialista',
        'text' => 'Encuentra atención por especialidad, servicio o necesidad clínica.',
        'action' => 'Buscar ahora',
        'href' => '#buscar-atencion',
    ],
    [
        'icon' => 'ambulance',
        'title' => 'Emergencias 24/7',
        'text' => 'Atención inmediata para adultos y pediátricos.',
        'action' => 'Llamar ahora',
        'href' => 'tel:18098060444',
        'urgent' => true,
    ],
    [
        'icon' => 'map-pin',
        'title' => 'Ubicación y contacto',
        'text' => 'Visítanos en Santiago y conoce nuestras vías de acceso.',
        'action' => 'Ver ubicación',
        'href' => $contact['maps'],
        'external' => true,
    ],
    [
        'icon' => 'calendar-check',
        'title' => 'Agendar cita',
        'text' => 'Programa tu consulta presencial de forma rápida y segura.',
        'action' => 'Agendar ahora',
        'href' => '#agenda',
        'modal' => true,
    ],
];

$featuredServices = [
    ['icon' => 'ambulance', 'title' => 'Emergencias 24/7', 'text' => 'Atención inmediata para adultos y pediátricos.', 'query' => 'Emergencia', 'href' => service_url('Emergencia Adulto y Pediátrica')],
    ['icon' => 'scan-line', 'title' => 'Diagnóstico avanzado', 'text' => 'Imágenes, laboratorio y pruebas especializadas.', 'query' => 'Tomografía', 'href' => base_url('servicios/diagnostico-avanzado')],
    ['icon' => 'scissors', 'title' => 'Cirugías', 'text' => 'Procedimientos de mínima invasión y alta complejidad.', 'query' => 'Cirugía', 'href' => base_url('servicios/cirugias')],
    ['icon' => 'bed', 'title' => 'Hospitalización', 'text' => 'Habitaciones confortables y atención integral.', 'query' => 'Hospitalización', 'href' => service_url('Hospitalización')],
    ['icon' => 'stethoscope', 'title' => 'Consulta especializada', 'text' => 'Amplia red de especialistas en todas las áreas.', 'query' => 'Cardiología', 'href' => base_url('servicios/consulta-especializada')],
    ['icon' => 'activity', 'title' => 'Rehabilitación', 'text' => 'Terapias físicas, respiratorias y de recuperación.', 'query' => 'Rehabilitación', 'href' => service_url('Medicina Física y Rehabilitación')],
];

$infrastructure = [
    ['icon' => 'clipboard-plus', 'title' => 'Quirófanos modernos', 'text' => 'Bloque preparado para procedimientos seguros y recuperación coordinada.', 'href' => base_url('servicios/cirugias')],
    ['icon' => 'heart-pulse', 'title' => 'UCI equipada', 'text' => 'Soporte crítico con monitoreo continuo y equipos interdisciplinarios.', 'href' => service_url('Cuidados Intensivos')],
    ['icon' => 'scan-line', 'title' => 'Imágenes avanzadas', 'text' => 'Tomografía, mamografía, radiografía y estudios especializados.', 'href' => base_url('servicios/diagnostico-avanzado')],
    ['icon' => 'flask-conical', 'title' => 'Laboratorio especializado', 'text' => 'Pruebas clínicas integradas a emergencia, consulta e internamiento.', 'href' => service_url('Laboratorio Clínico')],
    ['icon' => 'pill', 'title' => 'Farmacia hospitalaria', 'text' => 'Apoyo farmacéutico para continuidad de tratamiento y hospitalización.', 'href' => service_url('Farmacia')],
];

$patientGuide = [
    ['icon' => 'clipboard-check', 'title' => 'Admisión y registro', 'href' => base_url('tu-visita')],
    ['icon' => 'calendar-clock', 'title' => 'Preparación para tu cita', 'href' => base_url('preparacion-para-tu-cita')],
    ['icon' => 'shield-check', 'title' => 'Seguros aceptados', 'href' => base_url('seguros-aceptados')],
    ['icon' => 'circle-parking', 'title' => 'Estacionamiento', 'href' => $contact['maps']],
    ['icon' => 'circle-help', 'title' => 'Preguntas frecuentes', 'href' => base_url('preguntas-frecuentes')],
];

$careHighlights = [
    ['label' => 'Respuesta inmediata', 'value' => '24/7', 'text' => 'Emergencias adulto y pediátrica.'],
    ['label' => 'Capacidad clínica', 'value' => '65+', 'text' => 'Habitaciones para internamiento.'],
    ['label' => 'Consulta externa', 'value' => '55+', 'text' => 'Consultorios especializados.'],
];

$journeySteps = [
    [
        'step' => '01',
        'title' => 'Antes de tu visita',
        'text' => 'Agenda, confirma documentos, prepara estudios previos y revisa indicaciones.',
        'icon' => 'calendar-clock',
    ],
    [
        'step' => '02',
        'title' => 'Admisión',
        'text' => 'Registro, orientación, seguros y acompañamiento inicial del paciente.',
        'icon' => 'clipboard-check',
    ],
    [
        'step' => '03',
        'title' => 'Atención médica',
        'text' => 'Evaluación por especialistas, diagnóstico y plan terapéutico coordinado.',
        'icon' => 'stethoscope',
    ],
    [
        'step' => '04',
        'title' => 'Seguimiento',
        'text' => 'Continuidad, resultados, rehabilitación y próximas citas según tu caso.',
        'icon' => 'heart-handshake',
    ],
];

$serviceDirectory = [
    ['group' => 'Atención inmediata', 'icon' => 'zap', 'text' => 'Respuestas para urgencias, internamiento y soporte hospitalario.', 'items' => ['Emergencia Adulto y Pediátrica', 'Cuidados Intensivos', 'Hospitalización', 'Farmacia']],
    ['group' => 'Diagnóstico', 'icon' => 'microscope', 'text' => 'Estudios que aceleran decisiones clínicas y seguimiento médico.', 'items' => ['Laboratorio Clínico', 'Tomografía', 'Mamografía', 'Sonografía']],
    ['group' => 'Especialidades', 'icon' => 'stethoscope', 'text' => 'Equipos médicos para consulta, prevención y continuidad.', 'items' => ['Cardiología', 'Ginecología', 'Pediatría', 'Medicina Interna']],
    ['group' => 'Procedimientos', 'icon' => 'scissors', 'text' => 'Áreas preparadas para procedimientos y recuperación.', 'items' => ['Cirugía General', 'Cirugía Laparoscópica', 'Unidad Endoscópica', 'Hemodinamia']],
];

$leadershipDirector = [
    'name' => 'Dr. Rafael Sánchez Cárdenas',
    'role' => 'Director General',
    'bio' => 'Lidera la operación clínica y administrativa del hospital con un enfoque en calidad, ética y atención humanizada.',
    'initials' => 'RS',
];

$leadershipGerencias = [
    ['area' => 'Recursos Humanos', 'icon' => 'users-round', 'desc' => 'Talento, formación continua y bienestar del equipo clínico y administrativo.'],
    ['area' => 'Médica y Servicios', 'icon' => 'stethoscope', 'desc' => 'Coordinación clínica, especialidades, calidad asistencial y seguridad del paciente.'],
    ['area' => 'Finanzas', 'icon' => 'trending-up', 'desc' => 'Control financiero, sostenibilidad y transparencia de la operación hospitalaria.'],
    ['area' => 'Planificación', 'icon' => 'target', 'desc' => 'Estrategia institucional, proyectos de expansión y mejora continua.'],
    ['area' => 'Servicios Generales', 'icon' => 'building-2', 'desc' => 'Infraestructura, mantenimiento, abastecimiento y operación de soporte.'],
];

?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital General Las Colinas | Atención médica avanzada en Santiago, RD</title>
    <meta name="description"
        content="Hospital General Las Colinas en Santiago, Rep. Dominicana: emergencias 24/7, 28+ especialidades médicas, 55+ consultorios, tecnología de diagnóstico avanzada y hospitalización integral.">
    <meta name="keywords"
        content="hospital Santiago, hospital colinas, emergencias 24/7 Santiago, especialistas médicos República Dominicana, cardiología, pediatría, ginecología, tomografía Santiago">
    <meta name="author" content="Hospital General Las Colinas">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <meta name="geo.region" content="DO-25">
    <meta name="geo.placename" content="Santiago de los Caballeros">
    <meta name="geo.position" content="19.451010;-70.687126">
    <meta name="ICBM" content="19.451010, -70.687126">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="Hospital General Las Colinas | Atención médica avanzada en Santiago, RD">
    <meta property="og:description"
        content="Emergencias 24/7, 28+ especialidades, 55+ consultorios y tecnología de diagnóstico avanzada en Santiago, República Dominicana.">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($assets['hero'])) ?>">
    <meta property="og:image:alt" content="Fachada del Hospital General Las Colinas">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Hospital General Las Colinas | Atención médica avanzada en Santiago, RD">
    <meta name="twitter:description"
        content="Emergencias 24/7, 28+ especialidades, 55+ consultorios y tecnología de diagnóstico avanzada.">
    <meta name="twitter:image" content="<?= e(absolute_url($assets['hero'])) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="image" href="<?= e(base_url($assets['hero'])) ?>" fetchpriority="high">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Hospital",
                "@id": "<?= e(absolute_url()) ?>#hospital",
                "name": "Hospital General Las Colinas",
                "alternateName": "Colinas Hospital General",
                "url": "<?= e(absolute_url()) ?>",
                "logo": "<?= e(absolute_url($assets['logo'])) ?>",
                "image": "<?= e(absolute_url($assets['hero'])) ?>",
                "description": "Hospital privado en Santiago, Rep. Dominicana con emergencias 24/7, 28+ especialidades, 55+ consultorios y tecnología de diagnóstico avanzada.",
                "telephone": "<?= e($contact['phone']) ?>",
                "email": "<?= e($contact['email']) ?>",
                "priceRange": "$$",
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": "Av. 27 de Febrero, Plaza Colinas Mall",
                    "addressLocality": "Santiago de los Caballeros",
                    "addressRegion": "Santiago",
                    "postalCode": "51000",
                    "addressCountry": "DO"
                },
                "geo": {
                    "@type": "GeoCoordinates",
                    "latitude": 19.451010,
                    "longitude": -70.687126
                },
                "openingHoursSpecification": {
                    "@type": "OpeningHoursSpecification",
                    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"],
                    "opens": "00:00",
                    "closes": "23:59"
                },
                "sameAs": [
                    "<?= e($contact['facebook']) ?>",
                    "<?= e($contact['instagram']) ?>"
                ],
                "medicalSpecialty": [
                    <?php $specialtyCount = count($services['consultas']['items']);
                    foreach ($services['consultas']['items'] as $i => $sp): ?>"<?= e($sp) ?>"<?= $i < $specialtyCount - 1 ? ',' : '' ?><?php endforeach; ?>
                ],
                "availableService": [
                    {"@type": "MedicalProcedure", "name": "Emergencias 24/7"},
                    {"@type": "MedicalProcedure", "name": "Cirugía"},
                    {"@type": "MedicalProcedure", "name": "Hospitalización"},
                    {"@type": "MedicalProcedure", "name": "Diagnóstico por imagen"},
                    {"@type": "MedicalProcedure", "name": "Laboratorio clínico"}
                ]
            },
            {
                "@type": "WebSite",
                "@id": "<?= e(absolute_url()) ?>#website",
                "url": "<?= e(absolute_url()) ?>",
                "name": "Hospital General Las Colinas",
                "publisher": {"@id": "<?= e(absolute_url()) ?>#hospital"},
                "inLanguage": "es-DO",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": {
                        "@type": "EntryPoint",
                        "urlTemplate": "<?= e(absolute_url('directorio-medico')) ?>?q={search_term_string}"
                    },
                    "query-input": "required name=search_term_string"
                }
            }
        ]
    }
    </script>
</head>

<body class="bg-white font-sans text-slate-950 antialiased">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header id="siteHeader" class="site-header">
        <div class="utility-bar">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="tel:18098060444" class="utility-link">
                    <i data-lucide="phone" class="h-4 w-4"></i>
                    <?= e($contact['phone']) ?>
                </a>
                <div class="hidden items-center gap-7 md:flex">
                    <a href="#contacto" class="utility-link utility-emergency">
                        <i data-lucide="cross" class="h-4 w-4"></i>
                        Emergencias 24/7
                    </a>
                    <a href="<?= e(base_url('portal/login.php')) ?>" class="utility-link">
                        <i data-lucide="users-round" class="h-4 w-4"></i>
                        Pacientes y visitantes
                    </a>
                    <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="utility-link">
                        <i data-lucide="user-round-check" class="h-4 w-4"></i>
                        Profesionales médicos
                    </a>
                    <a href="#buscar-atencion" class="utility-link">
                        <i data-lucide="search" class="h-4 w-4"></i>
                        Buscar
                    </a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="main-nav-inner mx-auto flex h-[110px] max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <a href="#inicio" class="brand-link" aria-label="Hospital General Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas"
                        class="brand-logo">
                </a>

                <nav class="nav-primary" aria-label="Navegación principal">
                    <a href="#inicio" class="nav-link" data-section="inicio">Inicio</a>
                    <div class="nav-dropdown" data-nav-dropdown>
                        <button type="button" class="nav-link nav-dropdown-toggle" data-section="instalaciones"
                            aria-haspopup="true" aria-expanded="false">
                            Hospital
                            <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="nav-dropdown-menu" role="menu">
                            <a href="#nosotros" role="menuitem"><i data-lucide="building-2"
                                    class="h-4 w-4"></i>Nosotros</a>
                            <a href="#liderazgo" role="menuitem"><i data-lucide="users-round"
                                    class="h-4 w-4"></i>Liderazgo institucional</a>
                            <a href="#instalaciones" role="menuitem"><i data-lucide="hospital"
                                    class="h-4 w-4"></i>Instalaciones</a>
                            <a href="#pacientes" role="menuitem"><i data-lucide="heart-handshake"
                                    class="h-4 w-4"></i>Pacientes</a>
                            <a href="#contacto" role="menuitem"><i data-lucide="map-pin"
                                    class="h-4 w-4"></i>Contacto</a>
                        </div>
                    </div>
                    <a href="#servicios" class="nav-link" data-section="servicios">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="nav-link">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="nav-link">Noticias</a>
                </nav>

                <div class="nav-actions">
                    <button type="button" class="nav-search js-open-command" aria-label="Buscar">
                        <i data-lucide="search" class="h-4 w-4"></i>
                    </button>
                    <button type="button" class="js-open-appointment btn btn-green nav-cta">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </button>
                </div>

                <button id="menuToggle" type="button" class="mobile-toggle" aria-label="Abrir menú"
                    aria-expanded="false">
                    <i data-lucide="menu" class="menu-icon h-5 w-5"></i>
                    <i data-lucide="x" class="close-icon hidden h-5 w-5"></i>
                </button>
            </div>

            <div id="mobileMenu" class="mobile-menu hidden">
                <nav class="mobile-menu-inner" aria-label="Navegación móvil">
                    <a href="#inicio" class="mobile-link">Inicio</a>
                    <details class="mobile-group">
                        <summary>Hospital <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="mobile-sub">
                            <a href="#nosotros" class="mobile-sub-link">Nosotros</a>
                            <a href="#liderazgo" class="mobile-sub-link">Liderazgo institucional</a>
                            <a href="#instalaciones" class="mobile-sub-link">Instalaciones</a>
                            <a href="#pacientes" class="mobile-sub-link">Pacientes</a>
                            <a href="#contacto" class="mobile-sub-link">Contacto</a>
                        </div>
                    </details>
                    <a href="#servicios" class="mobile-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="mobile-link">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="mobile-link">Noticias</a>
                    <button type="button" class="mobile-link js-open-command text-left">
                        <i data-lucide="search" class="h-4 w-4"></i> Buscar atención
                    </button>
                    <button type="button" class="js-open-appointment mt-3 btn btn-green w-full justify-center">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </button>
                </nav>
            </div>
        </div>
    </header>

    <main id="contenido">
        <section id="inicio" class="hero-section">
            <div class="hero-shell">
                <div class="hero-copy">
                    <span class="hero-kicker">
                        <i data-lucide="badge-check" class="h-4 w-4"></i>
                        Hospital General · Santiago, RD
                    </span>
                    <h1>Atención médica avanzada para Santiago</h1>
                    <p>
                        Hospital General Las Colinas conecta especialistas, emergencia, diagnóstico y hospitalización en
                        una experiencia clara para pacientes, familias y médicos referidores.
                    </p>

                    <div class="hero-actions">
                        <button type="button" class="js-open-appointment btn btn-green btn-lg">
                            Agendar cita
                            <i data-lucide="calendar-check" class="h-4 w-4"></i>
                        </button>
                        <a href="#servicios" class="btn btn-outline btn-lg">
                            Ver servicios
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="<?= e(base_url('directorio-medico')) ?>" class="btn btn-outline btn-lg">
                            Directorio médico
                            <i data-lucide="user-round-search" class="h-4 w-4"></i>
                        </a>
                        <a href="tel:18098060444" class="btn btn-emergency btn-lg">
                            Emergencias 24/7
                            <i data-lucide="phone-call" class="h-4 w-4"></i>
                        </a>
                    </div>

                    <div class="hero-assist-panel" aria-label="Capacidades principales">
                        <a href="#buscar-atencion" class="hero-assist-item">
                            <i data-lucide="stethoscope" class="h-5 w-5"></i>
                            <span><strong>Especialidades</strong><small>Consulta especializada y servicios
                                    clínicos.</small></span>
                        </a>
                        <a href="<?= e(base_url('directorio-medico')) ?>" class="hero-assist-item">
                            <i data-lucide="users-round" class="h-5 w-5"></i>
                            <span><strong>Profesionales médicos</strong><small>Directorio preparado para perfiles y
                                    horarios.</small></span>
                        </a>
                    </div>
                </div>

                <div class="hero-feature">
                    <div class="hero-feature-image">
                        <img src="<?= e(base_url($assets['hero'])) ?>" alt="Fachada del Hospital General Las Colinas"
                            class="h-full w-full object-cover">
                    </div>
                    <span class="hero-feature-badge" aria-label="Acreditación clínica">
                        <i data-lucide="shield-check" class="h-4 w-4"></i>
                        Calidad clínica acreditada
                    </span>
                    <div class="hero-feature-panel" aria-label="Indicadores institucionales">
                        <span><b>24/7</b> Emergencias adulto y pediátrica</span>
                        <span><b>55+</b> Consultorios especializados</span>
                        <span><b>65+</b> Habitaciones</span>
                    </div>
                </div>
            </div>

            <div class="hero-access-wrap">
                <div class="task-grid" aria-label="Accesos rápidos para pacientes">
                    <?php foreach ($taskCards as $card): ?>
                        <a href="<?= e($card['href']) ?>"
                            class="<?= !empty($card['modal']) ? 'js-open-appointment ' : '' ?>task-card"
                            <?= !empty($card['external']) ? 'target="_blank" rel="noopener"' : '' ?>>
                            <span class="task-icon"><i data-lucide="<?= e($card['icon']) ?>" class="h-8 w-8"></i></span>
                            <span class="task-body">
                                <strong><?= e($card['title']) ?></strong>
                                <small><?= e($card['text']) ?></small>
                                <em><?= e($card['action']) ?> <i data-lucide="arrow-right" class="h-4 w-4"></i></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="buscar-atencion" class="care-finder">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="finder-panel">
                    <div class="finder-head">
                        <span class="finder-kicker">
                            <i data-lucide="search" class="h-4 w-4"></i>
                            Centro de búsqueda clínica
                        </span>
                        <h2>Encuentra atención por especialidad, servicio o médico</h2>
                        <p>Escribe el nombre del especialista, una especialidad o un servicio. Te llevamos directo al
                            perfil o al equipo correcto.</p>
                    </div>

                    <form id="careSearchForm" class="finder-search" role="search">
                        <i data-lucide="search" class="h-5 w-5"></i>
                        <input id="careSearch" type="search"
                            placeholder="Ej. Cardiología, García, Tomografía, Pediatría..." autocomplete="off">
                        <button id="careClear" type="button" class="finder-clear hidden" aria-label="Limpiar búsqueda">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                        <button type="submit" class="finder-submit">
                            Buscar
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </button>
                    </form>

                    <div class="clinical-grid" aria-label="Categorías clínicas">
                        <?php foreach ($clinicalCategories as $category): ?>
                            <button type="button" class="clinical-chip" data-fill-search="<?= e($category['query']) ?>">
                                <i data-lucide="<?= e($category['icon']) ?>" class="h-6 w-6"></i>
                                <span><?= e($category['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div id="careResults" class="care-results">
                        <?php if (!empty($featuredDoctors)): ?>
                            <?php $allDoctors = public_doctors($services, $assets); ?>
                            <div class="care-group" data-care-group="doctors">
                                <header>
                                    <span><i data-lucide="users-round" class="h-4 w-4"></i></span>
                                    <strong>Médicos</strong>
                                    <small>Resultados del directorio</small>
                                </header>
                                <div class="care-group-list care-doctor-list">
                                    <?php foreach ($allDoctors as $doc): ?>
                                        <?php $searchKey = search_key($doc['name'] . ' ' . $doc['specialty'] . ' ' . $doc['office']); ?>
                                        <a href="<?= e(base_url('medico/' . $doc['slug'])) ?>"
                                            class="care-result care-result--doctor" data-care-name="<?= e($searchKey) ?>">
                                            <img src="<?= e(base_url($doc['photo'])) ?>" alt="<?= e($doc['name']) ?>"
                                                loading="lazy">
                                            <span>
                                                <strong><?= e($doc['name']) ?></strong>
                                                <small><?= e($doc['specialty']) ?></small>
                                            </span>
                                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="care-group" data-care-group="services">
                            <header>
                                <span><i data-lucide="stethoscope" class="h-4 w-4"></i></span>
                                <strong>Especialidades y servicios</strong>
                                <small>Agenda una orientación clínica</small>
                            </header>
                            <div class="care-group-list care-service-list">
                                <?php foreach ($services as $group): ?>
                                    <?php foreach ($group['items'] as $item): ?>
                                        <button type="button" class="care-result js-open-appointment"
                                            data-care-name="<?= e(search_key($item . ' ' . $group['label'])) ?>">
                                            <i data-lucide="<?= e($group['icon']) ?>" class="h-4 w-4"></i>
                                            <span><?= e($item) ?></span>
                                            <small><?= e($group['label']) ?></small>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <p id="careEmpty" class="care-empty hidden">
                        <i data-lucide="search-x" class="h-5 w-5"></i>
                        No encontramos coincidencias. Prueba con otro término o
                        <a href="<?= e(base_url('directorio-medico')) ?>">explora el directorio</a>.
                    </p>

                    <div class="care-intelligence">
                        <div>
                            <span>Centro de orientación clínica</span>
                            <strong>Una ruta clara para encontrar la atención correcta.</strong>
                        </div>
                        <?php foreach ($careHighlights as $item): ?>
                            <article>
                                <b><?= e($item['value']) ?></b>
                                <span><?= e($item['label']) ?></span>
                                <small><?= e($item['text']) ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="nosotros" class="section-shell bg-white">
            <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[.78fr_1.22fr] lg:px-8">
                <div class="content-block">
                    <p class="section-label">¿Por qué elegir Las Colinas?</p>
                    <h2 class="section-title">Cuidado experto. Tecnología avanzada. Siempre a tu lado.</h2>
                    <p class="section-copy">
                        El Hospital General Las Colinas integra atención médica especializada, infraestructura moderna y
                        un enfoque humano centrado en pacientes y familias.
                    </p>
                    <ul class="premium-checks">
                        <li>55+ consultorios con especialistas altamente capacitados.</li>
                        <li>65+ habitaciones para hospitalización con altos estándares.</li>
                        <li>Emergencias 24/7 con atención inmediata.</li>
                        <li>Tecnología de diagnóstico y tratamiento de última generación.</li>
                        <li>Modelo conectado a Colinas Mall para una experiencia más accesible.</li>
                    </ul>
                    <a href="#instalaciones" class="btn btn-outline mt-8">
                        Conoce más sobre nosotros
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>
                <div class="clinical-mosaic" aria-label="Vistas del hospital">
                    <figure class="mosaic-main">
                        <img src="<?= e(base_url($assets['doctors'])) ?>"
                            alt="Equipo médico del Hospital General Las Colinas" loading="lazy">
                        <figcaption>Equipo clínico<small>Especialistas con enfoque humano.</small></figcaption>
                    </figure>
                    <figure>
                        <img src="<?= e(base_url($assets['ct'])) ?>" alt="Tomografía del Hospital General Las Colinas"
                            loading="lazy">
                        <figcaption>Tomografía<small>Imágenes diagnósticas avanzadas.</small></figcaption>
                    </figure>
                    <figure>
                        <img src="<?= e(base_url($assets['corridor'])) ?>"
                            alt="Áreas clínicas del Hospital General Las Colinas" loading="lazy">
                        <figcaption>Áreas clínicas<small>Espacios amplios y funcionales.</small></figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section id="liderazgo" class="leadership-section">
            <div class="leadership-bg" aria-hidden="true"></div>
            <div class="leadership-shell">
                <div class="leadership-head">
                    <span class="leadership-kicker">
                        <i data-lucide="badge-check" class="h-4 w-4"></i>
                        Liderazgo institucional
                    </span>
                    <h2>El equipo que respalda tu atención</h2>
                    <p>Profesionales con trayectoria reconocida en el sector salud dominicano lideran cada área del
                        Hospital General Las Colinas, garantizando calidad clínica, ética y servicio humano.</p>
                </div>

                <article class="leadership-feature">
                    <div class="leader-portrait" aria-hidden="true">
                        <span class="leader-initials"><?= e($leadershipDirector['initials']) ?></span>
                        <span class="leader-portrait-badge"><i data-lucide="badge-check" class="h-4 w-4"></i></span>
                    </div>
                    <div class="leader-meta">
                        <span class="leader-role"><i data-lucide="crown" class="h-3.5 w-3.5"></i>
                            <?= e($leadershipDirector['role']) ?></span>
                        <h3><?= e($leadershipDirector['name']) ?></h3>
                        <p class="leader-bio"><?= e($leadershipDirector['bio']) ?></p>
                    </div>
                </article>

                <div class="leadership-bridge" aria-hidden="true">
                    <span class="bridge-line"></span>
                    <span class="bridge-label"><i data-lucide="users-round" class="h-3.5 w-3.5"></i> Equipo
                        gerencial</span>
                    <span class="bridge-line"></span>
                </div>

                <div class="gerencias-grid" aria-label="Gerencias del hospital">
                    <?php foreach ($leadershipGerencias as $g): ?>
                        <article class="gerencia-card">
                            <span class="gerencia-icon"><i data-lucide="<?= e($g['icon']) ?>" class="h-5 w-5"></i></span>
                            <small>Gerencia de</small>
                            <strong><?= e($g['area']) ?></strong>
                            <p><?= e($g['desc']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <section id="servicios" class="section-shell bg-white pt-0">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="section-center">
                    <h2 class="section-title">Servicios destacados</h2>
                </div>
                <div class="featured-service-grid">
                    <?php foreach ($featuredServices as $service): ?>
                        <article class="featured-service">
                            <i data-lucide="<?= e($service['icon']) ?>" class="h-8 w-8"></i>
                            <h3><?= e($service['title']) ?></h3>
                            <p><?= e($service['text']) ?></p>
                            <a href="<?= e($service['href']) ?>" class="link-action">
                                Ver más
                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="service-directory">
                    <div class="directory-intro">
                        <p class="section-label">Directorio clínico</p>
                        <h3>Servicios organizados para resolver más rápido</h3>
                        <span>Una estructura pensada para pacientes, familiares, aseguradoras y médicos
                            referidores.</span>
                        <a href="<?= e(base_url('servicios')) ?>" class="directory-intro-link">
                            Ver todos los servicios
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                    <div class="directory-grid">
                        <?php foreach ($serviceDirectory as $group): ?>
                            <article class="directory-card">
                                <div class="directory-card-head">
                                    <span><i data-lucide="<?= e($group['icon']) ?>" class="h-5 w-5"></i></span>
                                    <div>
                                        <h4><?= e($group['group']) ?></h4>
                                        <p><?= e($group['text']) ?></p>
                                    </div>
                                </div>
                                <ul>
                                    <?php foreach ($group['items'] as $item): ?>
                                        <li>
                                            <a href="<?= e(service_url($item)) ?>">
                                                <?= e($item) ?>
                                                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="especialistas" class="featured-doctors">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="featured-doctors-head">
                    <div>
                        <p class="section-label">Equipo clínico</p>
                        <h2 class="section-title">Especialistas que cuidan de tu salud</h2>
                        <span>Profesionales con experiencia y enfoque humano, listos para acompañarte en cada paso de tu
                            atención.</span>
                    </div>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="btn btn-outline">
                        Ver directorio completo
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>
                <div class="featured-doctors-grid">
                    <?php foreach ($featuredDoctors as $doctor): ?>
                        <article class="featured-doctor">
                            <a href="<?= e(base_url('medico/' . $doctor['slug'])) ?>" class="featured-doctor-photo">
                                <img src="<?= e(base_url($doctor['photo'])) ?>" alt="<?= e($doctor['name']) ?>"
                                    loading="lazy">
                                <span><?= e($doctor['specialty']) ?></span>
                            </a>
                            <div class="featured-doctor-body">
                                <h3><a href="<?= e(base_url('medico/' . $doctor['slug'])) ?>"><?= e($doctor['name']) ?></a>
                                </h3>
                                <p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i><?= e($doctor['office']) ?></p>
                                <a href="<?= e(base_url('medico/' . $doctor['slug'])) ?>" class="featured-doctor-link">
                                    Ver perfil
                                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="instalaciones" class="capability-section">
            <div class="capability-shell">
                <div class="capability-copy">
                    <p class="section-label">Infraestructura y tecnología</p>
                    <h2 class="section-title">Instalaciones diseñadas para tu seguridad y bienestar</h2>
                    <p class="section-copy">
                        Contamos con equipos de última generación, áreas clínicas modernas y espacios pensados para una
                        atención eficiente, segura y confortable.
                    </p>
                    <div class="capability-stats" aria-label="Capacidad de infraestructura">
                        <article><strong>6</strong><span>Niveles clínicos y operativos</span></article>
                        <article><strong>65+</strong><span>Habitaciones de internamiento</span></article>
                        <article><strong>55+</strong><span>Consultorios especializados</span></article>
                    </div>
                    <a href="<?= e(base_url('instalaciones')) ?>" class="btn btn-outline mt-7">
                        Conoce nuestras instalaciones
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>
                <div class="capability-showcase">
                    <figure class="capability-photo">
                        <img src="<?= e(base_url($assets['corridor'])) ?>"
                            alt="Áreas clínicas modernas del Hospital General Las Colinas" loading="eager">
                        <figcaption>
                            <strong>Áreas clínicas conectadas</strong>
                            <span>Diagnóstico, cirugía, UCI, farmacia y hospitalización en una misma sede.</span>
                        </figcaption>
                    </figure>
                    <div class="capability-grid">
                        <?php foreach ($infrastructure as $item): ?>
                            <a href="<?= e($item['href']) ?>" class="capability-item">
                                <span><i data-lucide="<?= e($item['icon']) ?>" class="h-6 w-6"></i></span>
                                <strong><?= e($item['title']) ?></strong>
                                <small><?= e($item['text']) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="journey-section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="journey-head">
                    <div>
                        <p class="section-label">Ruta del paciente</p>
                        <h2 class="section-title">Una experiencia guiada antes, durante y después de tu visita</h2>
                    </div>
                    <button type="button" class="js-open-appointment btn btn-outline">
                        Coordinar orientación
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
                <div class="journey-grid">
                    <?php foreach ($journeySteps as $step): ?>
                        <article class="journey-card">
                            <div class="journey-top">
                                <span><?= e($step['step']) ?></span>
                                <i data-lucide="<?= e($step['icon']) ?>" class="h-6 w-6"></i>
                            </div>
                            <h3><?= e($step['title']) ?></h3>
                            <p><?= e($step['text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="pacientes" class="section-shell bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="section-center">
                    <h2 class="section-title">Guía para pacientes y visitantes</h2>
                </div>
                <div class="guide-rail">
                    <?php foreach ($patientGuide as $guide): ?>
                        <a href="<?= e($guide['href']) ?>" class="guide-link">
                            <i data-lucide="<?= e($guide['icon']) ?>" class="h-5 w-5"></i>
                            <?= e($guide['title']) ?>
                            <i data-lucide="arrow-right" class="ml-auto h-4 w-4"></i>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="patient-panel">
                    <div class="patient-panel-intro">
                        <p class="section-label">Información para el paciente</p>
                        <h3>Tus derechos y deberes</h3>
                        <p>Conoce tus derechos y cumple tus deberes para una atención segura, clara y digna durante todo
                            el proceso.</p>
                    </div>
                    <div class="patient-list compact">
                        <h4><i data-lucide="shield-check" class="h-5 w-5"></i> Derechos del paciente</h4>
                        <ol>
                            <?php foreach (array_slice($patientRights, 0, 7) as $right): ?>
                                <li><?= e($right) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <div class="patient-list compact">
                        <h4><i data-lucide="scale" class="h-5 w-5"></i> Deberes del paciente</h4>
                        <ol>
                            <?php foreach (array_slice($patientDuties, 0, 7) as $duty): ?>
                                <li><?= e($duty) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section id="galeria" class="tour-section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="tour-card">
                    <div class="tour-copy">
                        <p>Recorrido virtual</p>
                        <h2>Conoce nuestro hospital sin salir de casa</h2>
                        <span>Explora nuestras instalaciones, áreas de atención y espacios diseñados para tu
                            comodidad.</span>
                        <button id="videoToggle" type="button" class="btn btn-outline-white mt-7">
                            <i data-lucide="play" class="play-icon h-4 w-4 fill-current"></i>
                            <i data-lucide="pause" class="pause-icon hidden h-4 w-4"></i>
                            Iniciar recorrido
                        </button>
                    </div>
                    <div class="tour-video">
                        <video id="hospitalVideo" class="h-full w-full object-cover" preload="metadata"
                            poster="<?= e(base_url($assets['reception'])) ?>" controls playsinline>
                            <source src="<?= e(base_url($assets['video'])) ?>" type="video/mp4">
                            Tu navegador no soporta video HTML5.
                        </video>
                    </div>
                </div>

                <div class="mt-8 flex items-end justify-between gap-6">
                    <div>
                        <p class="section-label">Galería</p>
                        <h2 class="gallery-title">Instalaciones reales de Las Colinas</h2>
                    </div>
                    <div class="hidden gap-2 sm:flex">
                        <button type="button" class="gallery-nav" data-gallery-scroll="-1" aria-label="Anterior"><i
                                data-lucide="chevron-left" class="h-5 w-5"></i></button>
                        <button type="button" class="gallery-nav" data-gallery-scroll="1" aria-label="Siguiente"><i
                                data-lucide="chevron-right" class="h-5 w-5"></i></button>
                    </div>
                </div>
                <div id="galleryRail" class="gallery-rail">
                    <?php foreach ($gallery as $image): ?>
                        <button type="button" class="gallery-card" data-gallery-src="<?= e(base_url($image['src'])) ?>"
                            data-gallery-title="<?= e($image['title']) ?>" data-gallery-text="<?= e($image['text']) ?>">
                            <img src="<?= e(base_url($image['src'])) ?>" alt="<?= e($image['title']) ?>" loading="lazy">
                            <span><strong><?= e($image['title']) ?></strong><small><?= e($image['text']) ?></small></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if (!empty($latestNews)): ?>
            <section id="noticias" class="home-news-band">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="home-news-head">
                        <div>
                            <p class="section-label">Sala de prensa</p>
                            <h2 class="section-title">Últimas noticias del hospital</h2>
                            <span>Información oficial, alianzas, servicios nuevos y eventos institucionales.</span>
                        </div>
                        <a href="<?= e(base_url('noticias')) ?>" class="btn btn-outline">
                            Ver todas las noticias
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                    <div class="news-grid">
                        <?php foreach ($latestNews as $n): ?>
                            <article class="news-card">
                                <a href="<?= e(base_url('noticias/' . $n['slug'])) ?>" class="news-card-media">
                                    <?php if (!empty($n['cover_image'])): ?>
                                        <img src="<?= e(base_url($n['cover_image'])) ?>" alt="<?= e($n['title']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="news-card-fallback"><i
                                                data-lucide="<?= e(news_category_icon($n['category'])) ?>"></i></span>
                                    <?php endif; ?>
                                    <span class="news-card-cat"><?= e($n['category']) ?></span>
                                </a>
                                <div class="news-card-body">
                                    <time><?= e(news_format_date($n['published_at'])) ?></time>
                                    <h3><a href="<?= e(base_url('noticias/' . $n['slug'])) ?>"><?= e($n['title']) ?></a></h3>
                                    <p><?= e($n['excerpt']) ?></p>
                                    <a href="<?= e(base_url('noticias/' . $n['slug'])) ?>" class="news-card-link">
                                        Leer más <i data-lucide="arrow-right" class="h-4 w-4"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section id="contacto" class="cta-band">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="cta-panel">
                    <div class="cta-panel-glow" aria-hidden="true"></div>
                    <div class="cta-panel-copy">
                        <span class="cta-panel-kicker">
                            <i data-lucide="calendar-check" class="h-4 w-4"></i>
                            Agenda en línea
                        </span>
                        <h2>Tu salud es lo más importante</h2>
                        <p>Agenda tu consulta con un especialista del Hospital General Las Colinas. Nuestro equipo te
                            confirma disponibilidad y te acompaña en cada paso.</p>
                        <ul class="cta-panel-points">
                            <li><i data-lucide="check" class="h-4 w-4"></i> Respuesta del equipo de atención</li>
                            <li><i data-lucide="check" class="h-4 w-4"></i> Orientación de seguros y cobertura</li>
                            <li><i data-lucide="check" class="h-4 w-4"></i> Emergencias 24/7 disponibles</li>
                        </ul>
                    </div>
                    <div class="cta-panel-actions">
                        <button type="button" class="js-open-appointment btn btn-green btn-lg">
                            Agendar cita ahora
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </button>
                        <a href="tel:18098060444" class="cta-phone">
                            <span class="cta-phone-icon"><i data-lucide="phone" class="h-6 w-6"></i></span>
                            <span>
                                <small>Línea de atención</small>
                                <strong><?= e($contact['phone']) ?></strong>
                            </span>
                        </a>
                        <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="cta-secondary">
                            <i data-lucide="message-circle" class="h-4 w-4"></i>
                            WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>

    <div class="mobile-actionbar" aria-label="Acciones rápidas móviles">
        <a href="tel:18098060444">
            <i data-lucide="phone" class="h-4 w-4"></i>
            Llamar
        </a>
        <button type="button" class="js-open-appointment">
            <i data-lucide="calendar-days" class="h-4 w-4"></i>
            Cita
        </button>
        <a href="#buscar-atencion">
            <i data-lucide="search" class="h-4 w-4"></i>
            Buscar
        </a>
    </div>

    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

    <div id="commandCenter" class="command-shell hidden" role="dialog" aria-modal="true" aria-labelledby="commandTitle">
        <div class="command-panel">
            <div class="command-search">
                <i data-lucide="search" class="h-5 w-5"></i>
                <input id="commandInput" type="search" placeholder="Buscar servicios, especialidades, contacto..."
                    autocomplete="off">
                <button type="button" id="commandClose" aria-label="Cerrar búsqueda"><i data-lucide="x"
                        class="h-5 w-5"></i></button>
            </div>
            <div class="command-content">
                <div>
                    <h2 id="commandTitle">Centro de búsqueda Las Colinas</h2>
                    <p>Acceso inmediato a servicios, citas, emergencia y navegación institucional.</p>
                </div>
                <div id="commandResults" class="command-results">
                    <a href="#buscar-atencion" data-command-name="buscar especialista especialidad servicio atención">
                        <i data-lucide="user-round-search" class="h-5 w-5"></i>
                        <span><strong>Buscar especialista</strong><small>Encuentra atención por especialidad o
                                servicio.</small></span>
                    </a>
                    <a href="<?= e(base_url('directorio-medico')) ?>"
                        data-command-name="directorio medico médicos profesionales especialistas doctores">
                        <i data-lucide="users-round" class="h-5 w-5"></i>
                        <span><strong>Directorio médico</strong><small>Explora equipos clínicos, áreas y
                                disponibilidad.</small></span>
                    </a>
                    <button type="button" class="js-open-appointment"
                        data-command-name="agendar cita consulta appointment">
                        <i data-lucide="calendar-check" class="h-5 w-5"></i>
                        <span><strong>Agendar cita</strong><small>Solicita contacto para consulta o
                                procedimiento.</small></span>
                    </button>
                    <a href="tel:18098060444" data-command-name="emergencia telefono llamar urgencia 24/7">
                        <i data-lucide="ambulance" class="h-5 w-5"></i>
                        <span><strong>Emergencias 24/7</strong><small>Llama directamente al hospital.</small></span>
                    </a>
                    <a href="<?= e(base_url('instalaciones')) ?>"
                        data-command-name="instalaciones tecnologia tomografia laboratorio quirurgico">
                        <i data-lucide="building-2" class="h-5 w-5"></i>
                        <span><strong>Instalaciones y tecnología</strong><small>Conoce la infraestructura
                                clínica.</small></span>
                    </a>
                    <a href="<?= e(base_url('pacientes')) ?>"
                        data-command-name="pacientes visitantes derechos deberes guia seguros">
                        <i data-lucide="shield-check" class="h-5 w-5"></i>
                        <span><strong>Pacientes y visitantes</strong><small>Guía, derechos, deberes y
                                preparación.</small></span>
                    </a>
                    <a href="<?= e($contact['maps']) ?>" target="_blank" rel="noopener"
                        data-command-name="ubicacion mapa direccion colinas mall santiago">
                        <i data-lucide="map-pin" class="h-5 w-5"></i>
                        <span><strong>Ubicación</strong><small><?= e($contact['address']) ?></small></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="appointmentModal" class="modal-shell hidden" role="dialog" aria-modal="true"
        aria-labelledby="appointmentTitle">
        <div class="modal-panel">
            <div class="modal-header">
                <div>
                    <h2 id="appointmentTitle">Agendar cita</h2>
                    <p>Completa tus datos y nuestro equipo te contactará.</p>
                </div>
                <button type="button" class="js-close-appointment modal-close" aria-label="Cerrar">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="appointmentForm" class="space-y-4 p-6" action="<?= e(base_url('api/appointment.php')) ?>"
                method="post">
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div>
                    <label for="name" class="form-label">Nombre completo</label>
                    <input id="name" name="name" type="text" required class="form-input" placeholder="Ej. Juan Pérez">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="form-label">Teléfono</label>
                        <input id="phone" name="phone" type="tel" required class="form-input"
                            placeholder="(809) 000-0000">
                    </div>
                    <div>
                        <label for="date" class="form-label">Fecha preferida</label>
                        <input id="date" name="date" type="date" required class="form-input">
                    </div>
                </div>
                <div>
                    <label for="specialty" class="form-label">Especialidad</label>
                    <select id="specialty" name="specialty" required class="form-input">
                        <option value="">Seleccionar</option>
                        <?php foreach ($services['consultas']['items'] as $specialty): ?>
                            <option value="<?= e($specialty) ?>"><?= e($specialty) ?></option>
                        <?php endforeach; ?>
                        <option value="Otra">Otra</option>
                    </select>
                </div>
                <div>
                    <label for="message" class="form-label">Mensaje opcional</label>
                    <textarea id="message" name="message" rows="3" class="form-input"
                        placeholder="Describe brevemente tu necesidad"></textarea>
                </div>
                <button type="submit" class="btn btn-green w-full justify-center py-4">
                    Enviar solicitud
                    <i data-lucide="send" class="h-4 w-4"></i>
                </button>
                <p id="appointmentStatus" class="hidden rounded-md px-4 py-3 text-sm font-bold"></p>
                <p class="text-center text-xs leading-6 text-slate-400">Al enviar, aceptas ser contactado por el equipo
                    de atención del hospital.</p>
            </form>
        </div>
    </div>

    <div id="lightbox" class="lightbox hidden">
        <button type="button" id="lightboxClose" class="lightbox-close" aria-label="Cerrar imagen">
            <i data-lucide="x" class="h-5 w-5"></i>
        </button>
        <figure>
            <img id="lightboxImage" src="" alt="">
            <figcaption>
                <strong id="lightboxTitle"></strong>
                <span id="lightboxText"></span>
            </figcaption>
        </figure>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>

</html>