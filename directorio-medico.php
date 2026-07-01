<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/doctors.php';
require __DIR__ . '/includes/public-layout.php';

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

$consultSpecialties = $services['consultas']['items'];
$medicalProfiles = public_doctors($services, $assets);
$directorySpecialties = public_specialties($services);
$directoryHeroCss = preg_replace('#^assets/#', '../', $assets['hero']);
$directoryStats = [
    ['value' => count($medicalProfiles), 'label' => 'Especialistas', 'icon' => 'user-round-search'],
    ['value' => count($directorySpecialties), 'label' => 'Especialidades', 'icon' => 'stethoscope'],
    ['value' => '24/7', 'label' => 'Emergencias', 'icon' => 'ambulance'],
];
$directoryValues = [
    ['icon' => 'badge-check', 'title' => 'Perfiles verificados', 'text' => 'Datos actualizados por el equipo del hospital.'],
    ['icon' => 'calendar-check', 'title' => 'Cita rápida', 'text' => 'Solicita atención en menos de 1 minuto.'],
    ['icon' => 'building-2', 'title' => 'Una sola sede', 'text' => 'Av. 27 de Febrero, Plaza Colinas Mall.'],
];
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio médico | Hospital General Las Colinas, Santiago</title>
    <meta name="description"
        content="Directorio médico del Hospital General Las Colinas: <?= e((string) count($medicalProfiles)) ?> especialistas en <?= e((string) count($directorySpecialties)) ?> especialidades. Consulta horarios, consultorios y agenda tu cita en Santiago, RD.">
    <meta name="keywords"
        content="directorio medico Santiago, especialistas hospital colinas, médicos República Dominicana, agendar cita Santiago">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="Directorio médico | Hospital General Las Colinas">
    <meta property="og:description"
        content="Encuentra a tu especialista entre <?= e((string) count($medicalProfiles)) ?> médicos del Hospital General Las Colinas en Santiago, RD.">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($assets['hero'])) ?>">
    <meta property="og:image:alt" content="Directorio médico Hospital General Las Colinas">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Directorio médico | Hospital General Las Colinas">
    <meta name="twitter:description" content="Encuentra a tu especialista en el Hospital General Las Colinas.">
    <meta name="twitter:image" content="<?= e(absolute_url($assets['hero'])) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "<?= e(absolute_url()) ?>"},
            {"@type": "ListItem", "position": 2, "name": "Directorio médico", "item": "<?= e(absolute_url('directorio-medico')) ?>"}
        ]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "MedicalOrganization",
        "name": "Hospital General Las Colinas — Directorio médico",
        "url": "<?= e(absolute_url('directorio-medico')) ?>",
        "telephone": "<?= e($contact['phone']) ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Av. 27 de Febrero, Plaza Colinas Mall",
            "addressLocality": "Santiago",
            "addressCountry": "DO"
        },
        "medicalSpecialty": [
            <?php $totalSp = count($directorySpecialties);
            foreach ($directorySpecialties as $i => $sp): ?>"<?= e($sp['name']) ?>"<?= $i < $totalSp - 1 ? ',' : '' ?><?php endforeach; ?>
        ]
    }
    </script>
</head>

<body class="bg-white font-sans text-slate-950 antialiased directory-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header id="siteHeader" class="site-header">
        <div class="utility-bar">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="tel:18098060444" class="utility-link">
                    <i data-lucide="phone" class="h-4 w-4"></i>
                    <?= e($contact['phone']) ?>
                </a>
                <div class="hidden items-center gap-7 md:flex">
                    <a href="<?= e(base_url('#contacto')) ?>" class="utility-link utility-emergency">
                        <i data-lucide="cross" class="h-4 w-4"></i>
                        Emergencias 24/7
                    </a>
                    <a href="<?= e(base_url('portal/login.php')) ?>" class="utility-link">
                        <i data-lucide="users-round" class="h-4 w-4"></i>
                        Portal del Paciente
                    </a>
                    <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="utility-link">
                        <i data-lucide="user-round-check" class="h-4 w-4"></i>
                        Portal Médico
                    </a>
                    <a href="<?= e(base_url('#buscar-atencion')) ?>" class="utility-link">
                        <i data-lucide="search" class="h-4 w-4"></i>
                        Buscar
                    </a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="main-nav-inner mx-auto flex h-[110px] max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <a href="<?= e(base_url('#inicio')) ?>" class="brand-link" aria-label="Hospital General Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas"
                        class="brand-logo">
                </a>

                <nav class="nav-primary" aria-label="Navegación principal">
                    <a href="<?= e(base_url('#inicio')) ?>" class="nav-link">Inicio</a>
                    <div class="nav-dropdown" data-nav-dropdown>
                        <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true"
                            aria-expanded="false">
                            Hospital
                            <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="nav-dropdown-menu" role="menu">
                            <a href="<?= e(base_url('#nosotros')) ?>" role="menuitem"><i data-lucide="building-2"
                                    class="h-4 w-4"></i>Nosotros</a>
                            <a href="<?= e(base_url('#liderazgo')) ?>" role="menuitem"><i data-lucide="users-round"
                                    class="h-4 w-4"></i>Liderazgo institucional</a>
                            <a href="<?= e(base_url('#instalaciones')) ?>" role="menuitem"><i data-lucide="hospital"
                                    class="h-4 w-4"></i>Instalaciones</a>
                            <a href="<?= e(base_url('#pacientes')) ?>" role="menuitem"><i data-lucide="heart-handshake"
                                    class="h-4 w-4"></i>Pacientes</a>
                            <a href="<?= e(base_url('#contacto')) ?>" role="menuitem"><i data-lucide="map-pin"
                                    class="h-4 w-4"></i>Contacto</a>
                        </div>
                    </div>
                    <a href="<?= e(base_url('#servicios')) ?>" class="nav-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="nav-link is-active">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="nav-link">Noticias</a>
                </nav>

                <div class="nav-actions">
                    <a href="<?= e(base_url('#buscar-atencion')) ?>" class="nav-search" aria-label="Buscar">
                        <i data-lucide="search" class="h-4 w-4"></i>
                    </a>
                    <a href="<?= e(base_url('ver-resultados')) ?>" class="btn btn-navy nav-cta">
                        <i data-lucide="file-text" class="h-4 w-4"></i>
                        Ver resultados
                    </a>
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
                    <a href="<?= e(base_url('#inicio')) ?>" class="mobile-link">Inicio</a>
                    <details class="mobile-group">
                        <summary>Hospital <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="mobile-sub">
                            <a href="<?= e(base_url('#nosotros')) ?>" class="mobile-sub-link">Nosotros</a>
                            <a href="<?= e(base_url('#liderazgo')) ?>" class="mobile-sub-link">Liderazgo
                                institucional</a>
                            <a href="<?= e(base_url('#instalaciones')) ?>" class="mobile-sub-link">Instalaciones</a>
                            <a href="<?= e(base_url('#pacientes')) ?>" class="mobile-sub-link">Pacientes</a>
                            <a href="<?= e(base_url('#contacto')) ?>" class="mobile-sub-link">Contacto</a>
                        </div>
                    </details>
                    <a href="<?= e(base_url('#servicios')) ?>" class="mobile-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="mobile-link">Directorio médico</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="mobile-link">Noticias</a>
                    <a href="<?= e(base_url('ver-resultados')) ?>" class="mt-3 btn btn-navy w-full justify-center">
                        <i data-lucide="file-text" class="h-4 w-4"></i>
                        Ver resultados
                    </a>
                    <button type="button" class="js-open-appointment mt-2 btn btn-green w-full justify-center">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </button>
                </nav>
            </div>
        </div>
    </header>

    <main id="contenido">
        <section class="dir-hero" style="--dir-hero-bg: url('<?= e($directoryHeroCss) ?>');">
            <div class="dir-hero-grid">
                <div class="dir-hero-copy">
                    <span class="dir-hero-kicker">
                        <i data-lucide="stethoscope" class="h-4 w-4"></i>
                        Directorio médico
                    </span>
                    <h1>Encuentra el especialista indicado</h1>
                    <p>Profesionales con experiencia clínica, atención humana y tecnología avanzada. Filtra por nombre,
                        especialidad o servicio.</p>

                    <div class="dir-hero-actions">
                        <button type="button" class="btn btn-green btn-lg js-open-appointment">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i>
                            Agendar cita
                        </button>
                        <a href="#directorio" class="btn btn-outline-white btn-lg">
                            Explorar directorio
                            <i data-lucide="arrow-down" class="h-4 w-4"></i>
                        </a>
                    </div>

                    <dl class="dir-hero-stats" aria-label="Indicadores del directorio">
                        <?php foreach ($directoryStats as $stat): ?>
                            <div>
                                <dt><i data-lucide="<?= e($stat['icon']) ?>" class="h-4 w-4"></i></dt>
                                <dd>
                                    <strong><?= e((string) $stat['value']) ?></strong>
                                    <small><?= e($stat['label']) ?></small>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
        </section>

        <section class="dir-search-stage" aria-label="Buscar especialistas">
            <form id="doctorSearchForm" class="dir-search-card" role="search" autocomplete="off">
                <div class="dir-search-head">
                    <div class="dir-search-title">
                        <span><i data-lucide="user-round-search" class="h-6 w-6"></i></span>
                        <div>
                            <strong>Encuentra tu especialista</strong>
                            <small>Escribe el nombre del médico, especialidad o servicio.</small>
                        </div>
                    </div>
                    <span class="dir-result-count">
                        <i data-lucide="users-round" class="h-4 w-4"></i>
                        <span id="doctorResultCount"><?= e((string) count($medicalProfiles)) ?> Resultado/s</span>
                    </span>
                </div>

                <div class="dir-search-row">
                    <i data-lucide="search" class="h-5 w-5"></i>
                    <input id="doctorSearch" type="search" placeholder="Ej. Cardiología, García, Pediatría..."
                        autocomplete="off" aria-controls="doctorLivePanel">
                    <button id="doctorClear" type="button" class="dir-search-clear hidden"
                        aria-label="Limpiar búsqueda">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                    <button type="submit" class="dir-search-submit">
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        Buscar
                    </button>
                </div>

                <div id="doctorLivePanel" class="dir-live-panel hidden">
                    <div class="dir-live-head">
                        <span>Coincidencias en tiempo real</span>
                    </div>
                    <div id="doctorLiveResults" class="dir-live-grid">
                        <?php foreach ($medicalProfiles as $profile): ?>
                            <?php $search = search_key($profile['name'] . ' ' . $profile['specialty'] . ' ' . $profile['subspecialty'] . ' ' . $profile['specialty_slug'] . ' ' . $profile['office'] . ' ' . $profile['services']); ?>
                            <a href="<?= e(base_url('medico/' . $profile['slug'])) ?>" class="dir-live-result"
                                data-live-result data-search="<?= e($search) ?>">
                                <img src="<?= e(base_url($profile['photo'])) ?>" alt="<?= e($profile['name']) ?>"
                                    loading="lazy">
                                <span>
                                    <strong><?= e($profile['name']) ?></strong>
                                    <small><?= e($profile['specialty']) ?><?php if (!empty($profile['subspecialty'])): ?> · <?= e($profile['subspecialty']) ?><?php endif; ?></small>
                                </span>
                                <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </section>

        <section id="directorio" class="dir-section">
            <div class="dir-section-shell">
                <div class="dir-section-head">
                    <div>
                        <p class="section-label">Profesionales médicos</p>
                        <h2>Equipo clínico de Las Colinas</h2>
                        <span>Selecciona la especialidad para filtrar el equipo. Cada perfil muestra horarios,
                            consultorio y seguros vigentes.</span>
                    </div>
                    <div class="dir-filter-bar" aria-label="Filtros del directorio">
                        <button type="button" class="is-active" data-doctor-filter="all">
                            <i data-lucide="layers" class="h-4 w-4"></i>
                            Todos
                        </button>
                        <?php foreach (array_slice($directorySpecialties, 0, 7) as $specialty): ?>
                            <button type="button"
                                data-doctor-filter="<?= e($specialty['slug']) ?>"><?= e($specialty['name']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="doctorGrid" class="dir-doctor-grid">
                    <?php foreach ($medicalProfiles as $profile): ?>
                        <?php $search = search_key($profile['name'] . ' ' . $profile['specialty'] . ' ' . $profile['subspecialty'] . ' ' . $profile['specialty_slug'] . ' ' . $profile['office'] . ' ' . $profile['services']); ?>
                        <article class="dir-doctor-card" data-doctor-card data-search="<?= e($search) ?>">
                            <a href="<?= e(base_url('medico/' . $profile['slug'])) ?>" class="dir-doctor-photo">
                                <img src="<?= e(base_url($profile['photo'])) ?>" alt="<?= e($profile['name']) ?>"
                                    loading="lazy">
                                <span class="dir-doctor-tag"><?= e($profile['specialty']) ?></span>
                            </a>
                            <div class="dir-doctor-body">
                                <h3><a
                                        href="<?= e(base_url('medico/' . $profile['slug'])) ?>"><?= e($profile['name']) ?></a>
                                </h3>
                                <?php if (!empty($profile['subspecialty'])): ?>
                                    <p class="dir-doctor-sub"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i><?= e($profile['subspecialty']) ?></p>
                                <?php endif; ?>
                                <p class="dir-doctor-office"><i data-lucide="map-pin"
                                        class="h-3.5 w-3.5"></i><?= e($profile['office']) ?></p>
                                <p class="dir-doctor-schedule"><i data-lucide="clock-3"
                                        class="h-3.5 w-3.5"></i><?= e($profile['schedule']) ?></p>
                            </div>
                            <div class="dir-doctor-actions">
                                <a href="<?= e(base_url('medico/' . $profile['slug'])) ?>" class="dir-action-primary">
                                    Ver perfil
                                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                                </a>
                                <button type="button" class="js-open-appointment dir-action-secondary"
                                    aria-label="Agendar cita">
                                    <i data-lucide="calendar-days" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <p id="doctorEmpty" class="dir-doctor-empty hidden">
                    <i data-lucide="search-x" class="h-5 w-5"></i>
                    No encontramos coincidencias. Intenta con otro nombre o especialidad.
                </p>
            </div>
        </section>

        <section class="dir-value-band" aria-label="Por qué elegir Las Colinas">
            <div class="dir-value-shell">
                <?php foreach ($directoryValues as $value): ?>
                    <article>
                        <span><i data-lucide="<?= e($value['icon']) ?>" class="h-5 w-5"></i></span>
                        <div>
                            <strong><?= e($value['title']) ?></strong>
                            <small><?= e($value['text']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="dir-location" aria-label="Cómo llegar">
            <div class="dir-location-shell">
                <div class="dir-location-info">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas">
                    <h2>Visítanos en Santiago</h2>
                    <p>Acceso conectado a Colinas Mall, con áreas de estacionamiento y orientación.</p>
                    <ul>
                        <li><i data-lucide="map-pin" class="h-4 w-4"></i><?= e($contact['address']) ?></li>
                        <li><i data-lucide="phone" class="h-4 w-4"></i><a
                                href="tel:18098060444"><?= e($contact['phone']) ?></a></li>
                        <li><i data-lucide="mail" class="h-4 w-4"></i><a
                                href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></li>
                    </ul>
                    <div class="dir-location-actions">
                        <a href="<?= e($contact['maps']) ?>" target="_blank" rel="noopener" class="btn btn-green">
                            <i data-lucide="navigation" class="h-4 w-4"></i>
                            Cómo llegar
                        </a>
                        <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="btn btn-outline">
                            <i data-lucide="message-circle" class="h-4 w-4"></i>
                            WhatsApp
                        </a>
                    </div>
                </div>
                <iframe title="Ubicación del Hospital General Las Colinas" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=Hospital%20General%20Las%20Colinas%20Santiago&output=embed"></iframe>
            </div>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>

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
                        <?php foreach ($consultSpecialties as $specialty): ?>
                            <option value="<?= e($specialty) ?>"><?= e($specialty) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="appointmentStatus" class="hidden rounded-md px-4 py-3 text-sm font-bold"></div>
                <button type="submit" class="btn btn-green w-full justify-center">
                    Enviar solicitud
                    <i data-lucide="send" class="h-4 w-4"></i>
                </button>
            </form>
        </div>
    </div>

    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script defer src="/assets/js/track.js"></script>
</body>

</html>
