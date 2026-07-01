<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/public-layout.php';

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js'),
    @filemtime(__DIR__ . '/assets/css/ver-resultados.css') ?: 0
);

$portalUrl = base_url('portal/login');
$heroPhoto = base_url($assets['reception'] ?? $assets['hero']);
$title = 'Ver resultados en el Portal del Paciente';
$description = 'Aprende a ver tus resultados de laboratorio, imágenes y recetas en el Portal del Paciente del Hospital General Las Colinas. Automático, seguro y disponible siempre.';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Hospital General Las Colinas</title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hospital General Las Colinas">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <meta property="og:locale" content="es_DO">
    <meta property="og:image" content="<?= e(absolute_url($heroPhoto)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/ver-resultados.css')) ?>?v=<?= e($assetVersion) ?>">
    <script>document.documentElement.className += ' vr-js';</script>
</head>

<body class="bg-white font-sans text-slate-950 antialiased content-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="vr">

        <!-- ===== HERO ===== -->
        <section class="vr-hero">
            <div class="vr-shell">
                <div class="vr-hero-grid">
                    <div class="vr-hero-copy">
                        <span class="vr-eyebrow"><i data-lucide="heart-pulse"></i> Portal del paciente</span>
                        <h1>Tus resultados, <em>listos sin pedirlos</em></h1>
                        <p class="vr-hero-sub">
                            Tu laboratorio, imágenes y recetas aparecen en tu portal apenas el hospital los valida.
                            Míralos desde tu teléfono, cuando quieras.
                        </p>
                        <div class="vr-hero-actions">
                            <a href="<?= e($portalUrl) ?>" class="btn btn-green btn-lg">
                                <i data-lucide="log-in" class="h-4 w-4"></i> Entrar al portal
                            </a>
                            <a href="#pasos" class="vr-link">
                                <i data-lucide="list-ordered" class="h-4 w-4"></i> Ver el paso a paso
                            </a>
                        </div>
                    </div>
                    <figure class="vr-hero-photo">
                        <img src="<?= e($heroPhoto) ?>" alt="Recepción del Hospital General Las Colinas" loading="eager">
                    </figure>
                </div>
            </div>
        </section>

        <!-- ===== STEPS ===== -->
        <section class="vr-section vr-steps" id="pasos">
            <div class="vr-shell">
                <div class="vr-section-head is-center vr-reveal">
                    <h2>Verlos toma tres pasos</h2>
                    <p>Sin contraseñas que recordar y sin instalar nada. Solo necesitas tu cédula.</p>
                </div>
                <div class="vr-steplist">
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-n">1</div>
                        <div class="vr-step-body">
                            <h3><i data-lucide="log-in"></i> Entra al portal</h3>
                            <p>Escribe tu cédula o tu correo. Te enviamos un código para entrar, sin contraseñas que recordar.</p>
                            <span class="vr-step-note"><i data-lucide="smartphone"></i> Funciona en el teléfono o la computadora</span>
                        </div>
                    </article>
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-n">2</div>
                        <div class="vr-step-body">
                            <h3><i data-lucide="shield-check"></i> Confirma que eres tú</h3>
                            <p>Recibes un código de 6 dígitos en tu correo. Lo escribes y entras de forma segura.</p>
                            <span class="vr-step-note"><i data-lucide="timer"></i> El código vence en 10 minutos</span>
                        </div>
                    </article>
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-n">3</div>
                        <div class="vr-step-body">
                            <h3><i data-lucide="folder-open"></i> Abre tus resultados</h3>
                            <p>En “Resultados de laboratorio” o “Mis imágenes” consultas cada estudio. Lo descargas o lo compartes con tu médico.</p>
                            <span class="vr-step-note"><i data-lucide="download"></i> Descarga o comparte en un toque</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ===== CAPABILITIES ===== -->
        <section class="vr-section vr-caps">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <h2>Todo lo que tienes en tu portal</h2>
                    <p>Mucho más que resultados: tu salud, organizada y a tu alcance.</p>
                </div>
                <div class="vr-caps-grid">
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="flask-conical"></i></span>
                        <div><h4>Resultados de laboratorio</h4>
                            <p>Hemograma, química y perfiles con tus valores y rangos de referencia.</p></div>
                    </div>
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="scan"></i></span>
                        <div><h4>Imágenes médicas</h4>
                            <p>Radiografías, tomografías y sonografías, con el informe del radiólogo.</p></div>
                    </div>
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="file-text"></i></span>
                        <div><h4>Recetas</h4>
                            <p>Tus recetas digitales, listas para ver y descargar cuando las necesites.</p></div>
                    </div>
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="stethoscope"></i></span>
                        <div><h4>Consultas</h4>
                            <p>El resumen de cada consulta con tu médico, siempre disponible.</p></div>
                    </div>
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="messages-square"></i></span>
                        <div><h4>Mensajes con tu médico</h4>
                            <p>Escríbele por un canal privado y recibe respuesta segura.</p></div>
                    </div>
                    <div class="vr-cap vr-reveal">
                        <span class="ic"><i data-lucide="calendar-check"></i></span>
                        <div><h4>Tus citas</h4>
                            <p>Agenda, revisa o cancela tus citas en segundos, sin llamar.</p></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== AUTOMATIC ===== -->
        <section class="vr-section vr-auto">
            <div class="vr-shell">
                <div class="vr-section-head is-center vr-reveal">
                    <span class="vr-eyebrow"><i data-lucide="zap"></i> Todo automático</span>
                    <h2>No tienes que pedir nada</h2>
                    <p>Tu portal se conecta con el laboratorio, radiología y tu expediente. En cuanto un resultado se valida, llega a tu portal y te avisamos.</p>
                </div>
                <div class="vr-auto-points">
                    <div class="vr-auto-point vr-reveal">
                        <span class="ic"><i data-lucide="clipboard-check"></i></span>
                        <h4>El hospital valida</h4>
                        <p>El laboratorio o radiología confirma tu resultado.</p>
                    </div>
                    <div class="vr-auto-point vr-reveal">
                        <span class="ic"><i data-lucide="smartphone"></i></span>
                        <h4>Llega a tu portal</h4>
                        <p>Aparece al instante, sin que tengas que hacer nada.</p>
                    </div>
                    <div class="vr-auto-point vr-reveal">
                        <span class="ic"><i data-lucide="bell"></i></span>
                        <h4>Te avisamos</h4>
                        <p>Recibes una notificación para entrar a verlo.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== ACCESS ===== -->
        <section class="vr-section vr-access" id="acceso">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <h2>Dos formas de entrar</h2>
                    <p>Elige la que más fácil te quede. Las dos son seguras.</p>
                </div>
                <div class="vr-access-grid">
                    <article class="vr-access-card is-primary vr-reveal">
                        <span class="tag">Lo más rápido</span>
                        <h3>Ya tienes correo</h3>
                        <p>Entra con tu cédula o tu correo y te enviamos un código. Sin contraseñas.</p>
                        <ol>
                            <li>Escribe tu cédula o correo</li>
                            <li>Recibe el código en tu correo</li>
                            <li>Entra y mira tus resultados</li>
                        </ol>
                    </article>
                    <article class="vr-access-card vr-reveal">
                        <span class="tag">Primera vez</span>
                        <h3>Aún no tienes correo</h3>
                        <p>Actívate con tu cédula y el número de celular que registraste en el hospital. Luego agregas tu correo desde tu perfil.</p>
                        <ol>
                            <li>Usa tu cédula y tu celular registrado</li>
                            <li>Crea tu contraseña</li>
                            <li>Agrega tu correo para recibir códigos</li>
                        </ol>
                    </article>
                </div>
            </div>
        </section>

        <!-- ===== FINAL CTA ===== -->
        <section class="vr-final">
            <div class="vr-shell">
                <div class="vr-final-card vr-reveal">
                    <h2>Tus resultados te esperan</h2>
                    <p>Entra a tu portal y míralos ahora. Es gratis, seguro y toma menos de un minuto.</p>
                    <div class="vr-final-actions">
                        <a href="<?= e($portalUrl) ?>" class="btn btn-green btn-lg">
                            <i data-lucide="log-in" class="h-4 w-4"></i> Entrar al portal
                        </a>
                        <a href="#pasos" class="btn btn-outline-white btn-lg">
                            <i data-lucide="list-ordered" class="h-4 w-4"></i> Ver el paso a paso
                        </a>
                    </div>
                    <p class="vr-final-help">
                        ¿Necesitas ayuda para entrar? Llama al <a href="tel:18098060444"><?= e($contact['phone']) ?></a>
                    </p>
                </div>
            </div>
        </section>

    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php require __DIR__ . '/includes/widget-colinas-ai.php'; ?>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script>
        (function () {
            var els = document.querySelectorAll('.vr-reveal');
            function showAll() { els.forEach(function (el) { el.classList.add('is-in'); }); }
            if (!('IntersectionObserver' in window) || !els.length) { showAll(); return; }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
                });
            }, { threshold: 0, rootMargin: '0px 0px -10% 0px' });
            els.forEach(function (el) { io.observe(el); });
            // Red de seguridad: si algo no se reveló (observer inactivo, etc.), mostrarlo.
            setTimeout(showAll, 1800);
        })();
    </script>
    <script defer src="/assets/js/track.js"></script>
</body>

</html>
