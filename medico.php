<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/doctors.php';

$slug = $_GET['slug'] ?? '';
$doctor = public_doctor_by_slug($slug, $services, $assets);
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

if (!$doctor) {
    http_response_code(404);
}

$associationLines = $doctor ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $doctor['associations'] ?? ''))) : [];

// En los perfiles SIEMPRE se muestra el número del CALL CENTER del hospital
// (no el teléfono directo del médico, por privacidad). El call center coincide
// con el WhatsApp institucional: (809) 501-2002.
$callCenterDisplay = $contact['whatsapp_phone'];                                 // (809) 501-2002
$callCenterTel     = '1' . preg_replace('/\D/', '', $contact['whatsapp_phone']); // 18095012002
$doctorEmail = $doctor['email'] ?? '';

// "Agendar cita" desde el perfil lleva DIRECTO al asistente con este médico
// preseleccionado (paso 3). Si no hay id (fallback sin API), va al asistente general.
$doctorId   = $doctor ? (int) ($doctor['id'] ?? 0) : 0;
$doctorSpec = $doctor ? (int) ($doctor['specialty_id'] ?? 0) : 0;
$agendarUrl = $doctorId > 0
    ? base_url('agendar') . '?specialty_id=' . $doctorSpec . '&doctor_id=' . $doctorId
    : base_url('agendar');
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $doctor ? e($doctor['name'] . ' — ' . $doctor['specialty']) : 'Médico no encontrado' ?> | Hospital
        General Las Colinas</title>
    <meta name="description"
        content="<?= $doctor ? e($doctor['name'] . ', especialista en ' . $doctor['specialty'] . ' en el Hospital General Las Colinas, Santiago. ' . ($doctor['office'] ? 'Consultorio: ' . $doctor['office'] . '. ' : '') . 'Agenda tu cita.') : 'Perfil médico no encontrado.' ?>">
    <meta name="robots" content="<?= $doctor ? 'index, follow, max-image-preview:large' : 'noindex, follow' ?>">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url($assets['favicon'])) ?>">

    <?php if ($doctor): ?>
        <meta property="og:type" content="profile">
        <meta property="og:site_name" content="Hospital General Las Colinas">
        <meta property="og:title" content="<?= e($doctor['name'] . ' — ' . $doctor['specialty']) ?>">
        <meta property="og:description"
            content="<?= e('Especialista en ' . $doctor['specialty'] . ' del Hospital General Las Colinas, Santiago, RD.') ?>">
        <meta property="og:url" content="<?= e(canonical_url()) ?>">
        <meta property="og:locale" content="es_DO">
        <meta property="og:image" content="<?= e(absolute_url($doctor['photo'])) ?>">
        <meta property="og:image:alt" content="<?= e($doctor['name']) ?>">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= e($doctor['name'] . ' — ' . $doctor['specialty']) ?>">
        <meta name="twitter:description"
            content="<?= e('Especialista en ' . $doctor['specialty'] . ' del Hospital General Las Colinas.') ?>">
        <meta name="twitter:image" content="<?= e(absolute_url($doctor['photo'])) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">

    <?php if ($doctor): ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "<?= e(absolute_url()) ?>"},
                {"@type": "ListItem", "position": 2, "name": "Directorio médico", "item": "<?= e(absolute_url('directorio-medico')) ?>"},
                {"@type": "ListItem", "position": 3, "name": <?= json_encode($doctor['name'], JSON_UNESCAPED_UNICODE) ?>, "item": "<?= e(canonical_url()) ?>"}
            ]
        }
        </script>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Physician",
            "name": <?= json_encode($doctor['name'], JSON_UNESCAPED_UNICODE) ?>,
            "url": "<?= e(canonical_url()) ?>",
            "image": "<?= e(absolute_url($doctor['photo'])) ?>",
            "medicalSpecialty": <?= json_encode($doctor['specialty'], JSON_UNESCAPED_UNICODE) ?>,
            "telephone": "<?= e($callCenterDisplay) ?>",
            <?php if ($doctorEmail): ?>"email": <?= json_encode($doctorEmail, JSON_UNESCAPED_UNICODE) ?>,<?php endif; ?>
            "hospitalAffiliation": {
                "@type": "Hospital",
                "name": "Hospital General Las Colinas",
                "url": "<?= e(absolute_url()) ?>",
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": "Av. 27 de Febrero, Plaza Colinas Mall",
                    "addressLocality": "Santiago",
                    "addressCountry": "DO"
                }
            },
            "worksFor": {
                "@type": "Hospital",
                "name": "Hospital General Las Colinas",
                "url": "<?= e(absolute_url()) ?>"
            }
        }
        </script>
    <?php endif; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased profile-shell">
    <header class="profile-topbar">
        <div class="profile-topbar-inner">
            <a href="<?= e(base_url('#inicio')) ?>" class="brand-link" aria-label="Hospital General Las Colinas">
                <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas"
                    class="brand-logo h-14 w-auto max-w-[260px] object-contain">
            </a>
            <nav aria-label="Navegación del perfil">
                <a href="<?= e(base_url('directorio-medico')) ?>">
                    <i data-lucide="users-round" class="h-4 w-4"></i>
                    Directorio médico
                </a>
                <a href="<?= e(base_url('#servicios')) ?>">
                    <i data-lucide="stethoscope" class="h-4 w-4"></i>
                    Servicios
                </a>
                <a href="<?= e(base_url('#contacto')) ?>">
                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                    Contacto
                </a>
                <a href="<?= e($agendarUrl) ?>" class="profile-cta">
                    <i data-lucide="calendar-days" class="h-4 w-4"></i>
                    Agendar cita
                </a>
            </nav>
        </div>
    </header>

    <?php if (!$doctor): ?>
        <main class="profile-empty">
            <h1>Perfil no encontrado</h1>
            <p>El médico solicitado no está disponible o fue removido del directorio.</p>
            <a href="<?= e(base_url('directorio-medico')) ?>" class="btn btn-green">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Volver al directorio
            </a>
        </main>
    <?php else: ?>
        <main>
            <nav class="profile-crumbs" aria-label="Migas de pan">
                <a href="<?= e(base_url('#inicio')) ?>">Inicio</a>
                <span><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></span>
                <a href="<?= e(base_url('directorio-medico')) ?>">Directorio médico</a>
                <span><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></span>
                <span><?= e($doctor['name']) ?></span>
            </nav>

            <section class="profile-hero">
                <div class="profile-hero-card">
                    <div class="profile-portrait">
                        <img src="<?= e(base_url($doctor['photo'])) ?>" alt="<?= e($doctor['name']) ?>">
                        <span class="profile-portrait-badge">
                            <i data-lucide="badge-check" class="h-4 w-4"></i>
                            Médico Las Colinas
                        </span>
                    </div>
                    <div class="profile-identity">
                        <span class="profile-pills">
                            <span class="profile-specialty-pill">
                                <i data-lucide="stethoscope" class="h-3.5 w-3.5"></i>
                                <?= e($doctor['specialty']) ?>
                            </span>
                            <?php if (!empty($doctor['subspecialty'])): ?>
                                <span class="profile-subspecialty-pill"><?= e($doctor['subspecialty']) ?></span>
                            <?php endif; ?>
                        </span>
                        <h1><?= e($doctor['name']) ?></h1>
                        <p><?= e($doctor['biography'] ?: 'Atención especializada en ' . $doctor['specialty'] . ' del Hospital General Las Colinas.') ?>
                        </p>

                        <div class="profile-quick-actions">
                            <a href="<?= e($agendarUrl) ?>" class="btn btn-green">
                                <i data-lucide="calendar-days" class="h-4 w-4"></i>
                                Agendar cita
                            </a>
                            <a href="tel:<?= e($callCenterTel) ?>" class="btn btn-ghost">
                                <i data-lucide="phone" class="h-4 w-4"></i>
                                Llamar
                            </a>
                            <?php if ($doctorEmail): ?>
                                <a href="mailto:<?= e($doctorEmail) ?>" class="btn btn-ghost">
                                    <i data-lucide="mail" class="h-4 w-4"></i>
                                    Escribir
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-meta-strip">
                    <div>
                        <span><i data-lucide="building-2" class="h-4 w-4"></i></span>
                        <div>
                            <small>Consultorio</small>
                            <strong><?= e($doctor['office']) ?></strong>
                        </div>
                    </div>
                    <div>
                        <span><i data-lucide="clock-3" class="h-4 w-4"></i></span>
                        <div>
                            <small>Horario</small>
                            <strong><?= e($doctor['schedule']) ?></strong>
                        </div>
                    </div>
                    <div>
                        <span><i data-lucide="phone" class="h-4 w-4"></i></span>
                        <div>
                            <small>Call center</small>
                            <strong><a
                                    href="tel:<?= e($callCenterTel) ?>"><?= e($callCenterDisplay) ?></a></strong>
                        </div>
                    </div>
                    <div>
                        <span><i data-lucide="languages" class="h-4 w-4"></i></span>
                        <div>
                            <small>Idiomas</small>
                            <strong><?= e($doctor['languages']) ?></strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="profile-body">
                <div>
                    <article class="profile-card">
                        <h2><i data-lucide="briefcase-medical" class="h-5 w-5"></i> Trayectoria profesional</h2>
                        <span
                            class="profile-card-lead"><?= e($doctor['title'] ? $doctor['title'] . ' ' . $doctor['specialty'] : 'Especialista en ' . $doctor['specialty']) ?></span>
                        <?php $profileNarrative = trim((string) ($doctor['biography'] ?: $doctor['education'])); ?>
                        <?php if ($profileNarrative !== ''): ?>
                            <p><?= nl2br(e($profileNarrative)) ?></p>
                        <?php elseif (empty($doctor['services'])): ?>
                            <p>Este especialista forma parte del equipo de <?= e($doctor['specialty']) ?> del Hospital
                                General Las Colinas. Para conocer disponibilidad, seguros aceptados y agendar una consulta,
                                utiliza el botón de cita o comunícate con nuestro equipo de atención.</p>
                        <?php endif; ?>
                        <?php if ($doctor['services']): ?>
                            <p><?= nl2br(e($doctor['services'])) ?></p>
                        <?php endif; ?>
                    </article>

                    <?php if ($associationLines): ?>
                        <article class="profile-card">
                            <h2><i data-lucide="users-round" class="h-5 w-5"></i> Asociaciones</h2>
                            <div class="profile-associations">
                                <?php foreach ($associationLines as $association): ?>
                                    <p><i data-lucide="users-round" class="h-4 w-4"></i><?= e($association) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>

                <aside class="profile-aside">
                    <div class="profile-info-card">
                        <h3><i data-lucide="shield-check" class="h-5 w-5"></i> Información clínica</h3>
                        <dl>
                            <div>
                                <dt><i data-lucide="building-2" class="h-4 w-4"></i> Consultorio</dt>
                                <dd><?= e($doctor['office']) ?></dd>
                            </div>
                            <div>
                                <dt><i data-lucide="clock-3" class="h-4 w-4"></i> Horario de consulta</dt>
                                <dd><?= nl2br(e($doctor['schedule'])) ?></dd>
                            </div>
                            <div>
                                <dt><i data-lucide="phone" class="h-4 w-4"></i> Call center</dt>
                                <dd><a
                                        href="tel:<?= e($callCenterTel) ?>"><?= e($callCenterDisplay) ?></a>
                                </dd>
                            </div>
                            <?php if ($doctorEmail): ?>
                                <div>
                                    <dt><i data-lucide="mail" class="h-4 w-4"></i> Correo electrónico</dt>
                                    <dd><a href="mailto:<?= e($doctorEmail) ?>"><?= e($doctorEmail) ?></a></dd>
                                </div>
                            <?php endif; ?>
                            <div>
                                <dt><i data-lucide="shield-check" class="h-4 w-4"></i> Seguros aceptados</dt>
                                <dd>
                                    <?= e(implode(', ', array_column($insurers, 'name'))) ?>.
                                    <a href="<?= e(base_url('seguros-aceptados')) ?>">Ver todos los seguros</a>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="profile-cta-card">
                        <span><i data-lucide="calendar-check" class="h-4 w-4"></i> Solicita cita</span>
                        <h3>¿Necesitas atención con este especialista?</h3>
                        <p>Coordina tu consulta con el equipo del hospital. Te confirmaremos disponibilidad y orientaremos
                            sobre seguros.</p>
                        <a href="<?= e($agendarUrl) ?>" class="btn btn-green">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i>
                            Agendar ahora
                        </a>
                    </div>
                </aside>

                <a href="<?= e(base_url('directorio-medico')) ?>" class="profile-back">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Volver al directorio médico
                </a>
            </section>
        </main>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
</body>

</html>