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
$logo = base_url($assets['logo'] ?? 'assets/site/logo.png');
$title = 'Ver resultados — Portal del Paciente';
$description = 'Aprende a ver tus resultados de laboratorio, imágenes y recetas en el Portal del Paciente del Hospital General Las Colinas. Automático, seguro y disponible 24/7.';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/ver-resultados.css')) ?>?v=<?= e($assetVersion) ?>">
</head>

<body class="bg-white font-sans text-slate-950 antialiased content-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, ''); ?>

    <main id="contenido" class="vr">

        <!-- ================= HERO ================= -->
        <section class="vr-hero">
            <div class="vr-shell">
                <span class="vr-eyebrow"><i data-lucide="heart-pulse"></i> Portal del paciente</span>
                <h1 class="vr-display">Tus resultados, <span class="vr-hl">listos sin pedirlos</span></h1>
                <p class="vr-hero-sub">
                    Cuando el hospital valida tu laboratorio, imágenes o recetas, aparecen solos en tu portal.
                    Entra con tu cédula, confirma con un código y míralos desde tu teléfono — sin llamadas ni filas.
                </p>
                <div class="vr-hero-actions">
                    <a href="<?= e($portalUrl) ?>" class="btn btn-green btn-lg">
                        <i data-lucide="log-in" class="h-4 w-4"></i> Entrar al portal
                    </a>
                    <a href="#pasos" class="vr-ghost-link">
                        <i data-lucide="list-ordered" class="h-4 w-4"></i> Ver el paso a paso
                    </a>
                </div>

                <div class="vr-preview vr-reveal">
                    <div class="vr-float vr-float-tr">
                        <span class="f-dot"><i data-lucide="check"></i></span>
                        <span>Resultado listo<small>Laboratorio · hoy</small></span>
                    </div>
                    <div class="vr-float vr-float-bl">
                        <span class="f-dot" style="background:var(--vr-navy)"><i data-lucide="shield-check"></i></span>
                        <span>Solo tú lo ves<small>Acceso con código</small></span>
                    </div>

                    <div class="vr-screen">
                        <div class="vr-screen-bar">
                            <img class="vr-logo" src="<?= e($logo) ?>" alt="Hospital General Las Colinas">
                            <span class="vr-screen-title">Portal de Pacientes</span>
                            <span class="vr-screen-secure"><i data-lucide="lock-keyhole"></i> Conexión segura</span>
                        </div>
                        <div class="vr-screen-body">
                            <div class="vr-screen-greet">
                                <div>
                                    <div class="g-name">Hola, María</div>
                                    <div class="g-sub">Tienes 3 resultados nuevos</div>
                                </div>
                                <span class="g-pill"><i data-lucide="sparkles"></i> Al día</span>
                            </div>
                            <div class="vr-reslist">
                                <div class="vr-resrow">
                                    <span class="r-ic is-lab"><i data-lucide="flask-conical"></i></span>
                                    <div class="r-main">
                                        <div class="r-title">Hemograma y química sanguínea</div>
                                        <div class="r-meta">23 de mayo · 86 resultados</div>
                                    </div>
                                    <span class="r-action"><i data-lucide="eye"></i> Ver</span>
                                </div>
                                <div class="vr-resrow">
                                    <span class="r-ic is-img"><i data-lucide="scan"></i></span>
                                    <div class="r-main">
                                        <div class="r-title">Sonografía abdominal</div>
                                        <div class="r-meta">18 de mayo · con informe</div>
                                    </div>
                                    <span class="r-action"><i data-lucide="eye"></i> Ver</span>
                                </div>
                                <div class="vr-resrow">
                                    <span class="r-ic is-rx"><i data-lucide="file-text"></i></span>
                                    <div class="r-main">
                                        <div class="r-title">Receta médica</div>
                                        <div class="r-meta">18 de mayo · Dra. Fabián</div>
                                    </div>
                                    <span class="r-action"><i data-lucide="eye"></i> Ver</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= PROMISES ================= -->
        <section class="vr-promises">
            <div class="vr-shell">
                <div class="vr-promises-grid">
                    <div class="vr-promise vr-reveal">
                        <span class="p-ic"><i data-lucide="zap"></i></span>
                        <div>
                            <h3>Automático</h3>
                            <p>Tus estudios aparecen apenas el laboratorio o radiología los validan.</p>
                        </div>
                    </div>
                    <div class="vr-promise vr-reveal">
                        <span class="p-ic"><i data-lucide="shield-check"></i></span>
                        <div>
                            <h3>Privado</h3>
                            <p>Entras con un código de un solo uso. Nadie más ve tu información.</p>
                        </div>
                    </div>
                    <div class="vr-promise vr-reveal">
                        <span class="p-ic"><i data-lucide="clock"></i></span>
                        <div>
                            <h3>Siempre a mano</h3>
                            <p>Desde tu teléfono o computadora, a cualquier hora, los 365 días.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= STEPS (signature) ================= -->
        <section class="vr-steps" id="pasos">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <span class="vr-eyebrow"><i data-lucide="route"></i> Cómo funciona</span>
                    <h2 class="vr-display">Mira tus resultados en 3 pasos</h2>
                    <p>Sin contraseñas que recordar y sin instalar nada. Solo necesitas tu cédula.</p>
                </div>

                <div class="vr-journey">
                    <!-- Paso 1 -->
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-node">1</div>
                        <div class="vr-step-copy">
                            <div class="vr-step-num"><span>Paso 01</span></div>
                            <h3>Entra al portal</h3>
                            <p>Escribe tu cédula o tu correo. Te enviamos un código para entrar — sin contraseñas que recordar.</p>
                            <span class="vr-step-hint"><i data-lucide="smartphone"></i> Funciona en el teléfono o la computadora</span>
                        </div>
                        <div class="vr-step-visual">
                            <div class="vr-mini">
                                <div class="vr-screen-bar">
                                    <img class="vr-logo" src="<?= e($logo) ?>" alt="">
                                    <span class="vr-screen-title">Entrar</span>
                                </div>
                                <div class="vr-mini-body">
                                    <span class="vr-mini-eyebrow">Sin contraseñas</span>
                                    <h4>Entrar al portal</h4>
                                    <div class="vr-field">
                                        <label>Cédula o correo electrónico</label>
                                        <div class="box is-focus">001-1234567-8</div>
                                    </div>
                                    <div class="vr-fakebtn"><i data-lucide="mail"></i> Enviarme un código</div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Paso 2 -->
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-node">2</div>
                        <div class="vr-step-copy">
                            <div class="vr-step-num"><span>Paso 02</span></div>
                            <h3>Confirma que eres tú</h3>
                            <p>Recibes un código de 6 dígitos en tu correo. Escríbelo y listo: estás dentro, de forma segura.</p>
                            <span class="vr-step-hint"><i data-lucide="timer"></i> El código vence en 10 minutos</span>
                        </div>
                        <div class="vr-step-visual">
                            <div class="vr-mini">
                                <div class="vr-screen-bar">
                                    <img class="vr-logo" src="<?= e($logo) ?>" alt="">
                                    <span class="vr-screen-title">Tu código</span>
                                </div>
                                <div class="vr-mini-body">
                                    <span class="vr-mini-eyebrow">Te lo enviamos al correo</span>
                                    <h4>Escribe tu código</h4>
                                    <div class="vr-otp">
                                        <span class="on">4</span>
                                        <span class="on">8</span>
                                        <span class="on">2</span>
                                        <span class="cur">9</span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                    <div class="vr-fakebtn is-navy"><i data-lucide="log-in"></i> Entrar</div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Paso 3 -->
                    <article class="vr-step vr-reveal">
                        <div class="vr-step-node">3</div>
                        <div class="vr-step-copy">
                            <div class="vr-step-num"><span>Paso 03</span></div>
                            <h3>Mira tus resultados</h3>
                            <p>Abre “Resultados de laboratorio” o “Mis imágenes” y consulta cada estudio. Descárgalo o compártelo con tu médico.</p>
                            <span class="vr-step-hint"><i data-lucide="download"></i> Descarga o comparte en un toque</span>
                        </div>
                        <div class="vr-step-visual">
                            <div class="vr-mini">
                                <div class="vr-screen-bar">
                                    <img class="vr-logo" src="<?= e($logo) ?>" alt="">
                                    <span class="vr-screen-title">Mis resultados</span>
                                </div>
                                <div class="vr-mini-body">
                                    <div class="vr-reslist">
                                        <div class="vr-resrow">
                                            <span class="r-ic is-lab"><i data-lucide="flask-conical"></i></span>
                                            <div class="r-main">
                                                <div class="r-title">Laboratorio</div>
                                                <div class="r-meta">23 may · 86 resultados</div>
                                            </div>
                                            <span class="r-action"><i data-lucide="eye"></i> Ver</span>
                                        </div>
                                        <div class="vr-resrow">
                                            <span class="r-ic is-img"><i data-lucide="scan"></i></span>
                                            <div class="r-main">
                                                <div class="r-title">Imágenes</div>
                                                <div class="r-meta">18 may · con informe</div>
                                            </div>
                                            <span class="r-action"><i data-lucide="eye"></i> Ver</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ================= FEATURES ================= -->
        <section class="vr-features">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <span class="vr-eyebrow"><i data-lucide="layout-grid"></i> Todo en un solo lugar</span>
                    <h2 class="vr-display">Lo que tienes en tu portal</h2>
                    <p>Mucho más que resultados: tu salud, organizada y a tu alcance.</p>
                </div>
                <div class="vr-features-grid">
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="flask-conical"></i></span>
                        <h3>Resultados de laboratorio</h3>
                        <p>Hemograma, química, perfiles y más — con tus valores, unidades y rangos de referencia.</p>
                    </article>
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="scan"></i></span>
                        <h3>Imágenes médicas</h3>
                        <p>Radiografías, tomografías y sonografías, con el informe del radiólogo cuando está listo.</p>
                    </article>
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="file-text"></i></span>
                        <h3>Recetas</h3>
                        <p>Tus recetas digitales, listas para ver y descargar cuando las necesites.</p>
                    </article>
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="stethoscope"></i></span>
                        <h3>Consultas</h3>
                        <p>El resumen de cada consulta con tu médico, siempre disponible para repasarlo.</p>
                    </article>
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="messages-square"></i></span>
                        <h3>Mensajes con tu médico</h3>
                        <p>Escríbele a tu médico y recibe respuesta por un canal privado y seguro.</p>
                    </article>
                    <article class="vr-feature vr-reveal">
                        <span class="f-ic"><i data-lucide="calendar-check"></i></span>
                        <h3>Tus citas</h3>
                        <p>Agenda, revisa o cancela tus citas en segundos, sin llamar.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ================= AUTO BAND ================= -->
        <section class="vr-auto" id="automatico">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <span class="vr-eyebrow"><i data-lucide="zap"></i> Todo automático</span>
                    <h2 class="vr-display">No tienes que pedir nada</h2>
                    <p>Tu portal se conecta directo con el laboratorio, radiología y tu expediente. En cuanto un resultado se valida, llega a tu portal — y te avisamos.</p>
                </div>
                <div class="vr-flow vr-reveal">
                    <div class="vr-flow-card">
                        <span class="c-ic"><i data-lucide="clipboard-check"></i></span>
                        <h4>El hospital valida tu estudio</h4>
                        <p>El laboratorio o radiología confirma tu resultado.</p>
                    </div>
                    <div class="vr-flow-arrow"><i data-lucide="arrow-right"></i></div>
                    <div class="vr-flow-card is-live">
                        <span class="c-ic"><i data-lucide="smartphone"></i></span>
                        <h4>Aparece en tu portal</h4>
                        <p>Al instante, sin que tengas que hacer nada.</p>
                    </div>
                    <div class="vr-flow-arrow"><i data-lucide="arrow-right"></i></div>
                    <div class="vr-flow-card">
                        <span class="c-ic"><i data-lucide="bell"></i></span>
                        <h4>Recibes un aviso</h4>
                        <p>Te notificamos para que entres a verlo.</p>
                    </div>
                </div>
                <p class="vr-auto-note">Sin llamar, sin venir por el papel, sin esperar.</p>
            </div>
        </section>

        <!-- ================= ACCESS ================= -->
        <section class="vr-access" id="acceso">
            <div class="vr-shell">
                <div class="vr-section-head vr-reveal">
                    <span class="vr-eyebrow"><i data-lucide="key-round"></i> Cómo entrar</span>
                    <h2 class="vr-display">Dos formas de entrar</h2>
                    <p>Elige la que más fácil te quede. Ambas son seguras.</p>
                </div>
                <div class="vr-access-grid">
                    <article class="vr-access-card is-primary vr-reveal">
                        <span class="a-tag"><i data-lucide="zap"></i> Lo más rápido</span>
                        <h3>Ya tienes correo registrado</h3>
                        <p>Entra con tu cédula o tu correo y te enviamos un código. Sin contraseñas.</p>
                        <ul>
                            <li><i data-lucide="check-circle-2"></i> Escribe tu cédula o correo</li>
                            <li><i data-lucide="check-circle-2"></i> Recibe el código en tu correo</li>
                            <li><i data-lucide="check-circle-2"></i> Entra y mira tus resultados</li>
                        </ul>
                    </article>
                    <article class="vr-access-card vr-reveal">
                        <span class="a-tag"><i data-lucide="user-plus"></i> Primera vez</span>
                        <h3>Aún no tienes correo</h3>
                        <p>Actívate con tu cédula y el número de celular que diste en el hospital. Luego agregas tu correo desde tu perfil.</p>
                        <ul>
                            <li><i data-lucide="check-circle-2"></i> Usa tu cédula y tu celular registrado</li>
                            <li><i data-lucide="check-circle-2"></i> Crea tu contraseña</li>
                            <li><i data-lucide="check-circle-2"></i> Agrega tu correo para recibir códigos</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <!-- ================= FINAL CTA ================= -->
        <section class="vr-final">
            <div class="vr-shell">
                <div class="vr-final-card vr-reveal">
                    <h2 class="vr-display">Tus resultados te esperan</h2>
                    <p>Entra a tu portal y míralos ahora mismo. Es gratis, seguro y toma menos de un minuto.</p>
                    <div class="vr-final-actions">
                        <a href="<?= e($portalUrl) ?>" class="btn btn-green btn-lg">
                            <i data-lucide="log-in" class="h-4 w-4"></i> Entrar al portal
                        </a>
                        <a href="#pasos" class="btn btn-outline-white btn-lg">
                            <i data-lucide="list-ordered" class="h-4 w-4"></i> Ver el paso a paso
                        </a>
                    </div>
                    <p class="vr-final-help">
                        ¿Necesitas ayuda para entrar? Llama al
                        <a href="tel:18098060444"><?= e($contact['phone']) ?></a>
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
            if (!('IntersectionObserver' in window) || !els.length) {
                els.forEach(function (el) { el.classList.add('is-in'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
            els.forEach(function (el) { io.observe(el); });
        })();
    </script>
</body>

</html>
