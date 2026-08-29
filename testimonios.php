<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/testimonials.php';
require __DIR__ . '/includes/public-layout.php';

testimonials_ensure_schema();

$year = date('Y');
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/css/app.css'),
    filemtime(__DIR__ . '/assets/js/app.js')
);

/* ── Envío público (honeypot + trampa de tiempo + límite por IP + hCaptcha opcional) ── */
$sent = null;
$sendError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'testimonial') {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $trap = (string) ($_POST['website'] ?? '');            // honeypot: humanos lo dejan vacío
    $ts = (int) ($_POST['ts'] ?? 0);                       // trampa de tiempo
    $tooFast = $ts > 0 && (time() - $ts) < 3;

    if ($trap !== '') {
        // Bot: fingimos éxito sin guardar nada.
        $sent = true;
    } elseif ($tooFast) {
        $sendError = 'Tómate un momento para escribir tu experiencia.';
    } elseif (testimonials_rate_limited($ip)) {
        $sendError = 'Has enviado varios testimonios recientemente. Inténtalo más tarde.';
    } elseif (!testimonials_captcha_ok($_POST['h-captcha-response'] ?? null)) {
        $sendError = 'No pudimos verificar que no eres un robot. Intenta de nuevo.';
    } elseif (empty($_POST['consent'])) {
        $sendError = 'Necesitamos tu autorización para publicar el testimonio.';
    } else {
        try {
            testimonials_submit_public($_POST, $ip);
            $sent = true;
        } catch (Throwable $e) {
            $sendError = $e->getMessage();
        }
    }
    // PRG: evita reenvío al recargar cuando fue exitoso.
    if ($sent) {
        header('Location: ' . base_url('testimonios') . '?enviado=1#enviar');
        exit;
    }
}
if (isset($_GET['enviado'])) {
    $sent = true;
}

$testimonials = testimonials_published();
$google = testimonials_google();
$siteAvg = testimonials_average();
$siteCount = testimonials_count_published();
$hcaptchaSiteKey = defined('HCAPTCHA_SITE_KEY') ? HCAPTCHA_SITE_KEY : '';
$showCaptcha = $hcaptchaSiteKey !== '' && defined('HCAPTCHA_SECRET') && HCAPTCHA_SECRET !== '';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonios y reseñas de pacientes | Hospital General Las Colinas</title>
    <meta name="description"
        content="Lo que dicen nuestros pacientes del Hospital General Las Colinas en Santiago. Experiencias reales de atención, emergencias, hospitalización y consulta.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#262161">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <?php /* Fuentes auto-hospedadas (Inter + Outfit + Plus Jakarta Sans, VARIABLES):
             mismo origen, sin DNS/TLS a Google ni CSS render-blocking. */ ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
    <?php if ($showCaptcha): ?>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <?php endif; ?>
    <?php if ($siteCount > 0 && $siteAvg > 0): ?>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'MedicalOrganization',
        'name' => 'Hospital General Las Colinas',
        'url' => absolute_url(),
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $siteAvg,
            'reviewCount' => (string) $siteCount,
            'bestRating' => '5',
            'worstRating' => '1',
        ],
        'review' => array_map(static fn (array $t): array => [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => $t['author_name']],
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (string) (int) $t['rating'], 'bestRating' => '5'],
            'reviewBody' => $t['body'],
        ], array_slice($testimonials, 0, 12)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/analytics.php'; ?>
</head>

<body class="bg-white font-sans text-slate-950 antialiased">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?php render_public_header($assets, $contact, 'testimonios'); ?>

    <main id="contenido" class="tm-page">
        <!-- Hero -->
        <section class="tm-hero">
            <div class="tm-shell">
                <nav class="tm-breadcrumb" aria-label="Ruta de navegación">
                    <a href="<?= e(base_url()) ?>">Inicio</a>
                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                    <span>Testimonios</span>
                </nav>
                <p class="tm-eyebrow">La voz de nuestros pacientes</p>
                <h1>Historias de quienes confiaron su salud a Las Colinas</h1>
                <p class="tm-hero-lead">Experiencias reales de atención, cuidado y recuperación. Gracias por permitirnos acompañarte.</p>

                <div class="tm-hero-badges">
                    <?php if ($google['enabled'] && $google['rating'] > 0): ?>
                        <a class="tm-google-badge" <?= $google['url'] ? 'href="' . e($google['url']) . '" target="_blank" rel="noopener"' : '' ?>>
                            <span class="tm-google-g" aria-hidden="true">G</span>
                            <span class="tm-google-meta">
                                <strong><?= e(number_format($google['rating'], 1)) ?></strong>
                                <?= testimonials_stars_html((int) round($google['rating'])) ?>
                                <small><?= $google['total'] > 0 ? number_format($google['total']) . ' reseñas en Google' : 'Reseñas en Google' ?></small>
                            </span>
                        </a>
                    <?php endif; ?>
                    <a class="tm-hero-cta" href="#enviar">
                        <i data-lucide="pen-line" class="h-4 w-4"></i> Comparte tu experiencia
                    </a>
                </div>
            </div>
        </section>

        <!-- Muro de testimonios -->
        <section class="tm-wall-section">
            <div class="tm-shell">
                <?php if (!$testimonials): ?>
                    <div class="tm-empty">
                        <i data-lucide="message-square-quote"></i>
                        <p>Pronto compartiremos aquí las experiencias de nuestros pacientes.</p>
                    </div>
                <?php else: ?>
                    <div class="tm-wall">
                        <?php foreach ($testimonials as $t): [$c1, $c2] = testimonials_avatar_palette($t['author_name']); ?>
                            <article class="tm-card">
                                <div class="tm-card-top">
                                    <?= testimonials_stars_html((int) $t['rating']) ?>
                                    <?php if ($t['source'] === 'google'): ?>
                                        <span class="tm-card-source" title="Reseña de Google"><span class="tm-google-g sm" aria-hidden="true">G</span></span>
                                    <?php endif; ?>
                                </div>
                                <blockquote><?= e($t['body']) ?></blockquote>
                                <footer class="tm-card-author">
                                    <span class="tm-avatar" style="background:linear-gradient(135deg,<?= e($c1) ?>,<?= e($c2) ?>)"><?= e(testimonials_initials($t['author_name'])) ?></span>
                                    <span>
                                        <strong><?= e($t['author_name']) ?></strong>
                                        <small><?= e(trim(($t['author_role'] ?? '') . ($t['author_location'] ? ' · ' . $t['author_location'] : ''), ' ·')) ?></small>
                                    </span>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Formulario de envío -->
        <section id="enviar" class="tm-submit-section">
            <div class="tm-shell tm-submit-grid">
                <div class="tm-submit-intro">
                    <p class="tm-eyebrow">Cuéntanos</p>
                    <h2>Comparte tu experiencia</h2>
                    <p>Tu testimonio ayuda a otras familias a decidir con confianza. Lo revisamos antes de publicarlo.</p>
                    <ul class="tm-submit-notes">
                        <li><i data-lucide="shield-check" class="h-4 w-4"></i> Revisamos cada testimonio antes de publicarlo.</li>
                        <li><i data-lucide="eye-off" class="h-4 w-4"></i> No publicamos tu correo ni datos de contacto.</li>
                    </ul>
                </div>

                <div class="tm-submit-card">
                    <?php if ($sent): ?>
                        <div class="tm-sent">
                            <span class="tm-sent-icon"><i data-lucide="check-circle-2"></i></span>
                            <h3>¡Gracias por compartir tu experiencia!</h3>
                            <p>Lo recibimos y lo revisaremos antes de publicarlo. Agradecemos tu confianza.</p>
                        </div>
                    <?php else: ?>
                        <?php if ($sendError): ?>
                            <div class="tm-form-error"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= e($sendError) ?></div>
                        <?php endif; ?>
                        <form method="post" class="tm-form-public" action="<?= e(base_url('testimonios')) ?>#enviar">
                            <input type="hidden" name="form" value="testimonial">
                            <input type="hidden" name="ts" value="<?= time() ?>">
                            <!-- honeypot (oculto para humanos) -->
                            <div class="tm-hp" aria-hidden="true">
                                <label>No llenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                            </div>

                            <label>Tu nombre *
                                <input type="text" name="author_name" required maxlength="160" placeholder="Nombre y apellido" value="<?= e((string) ($_POST['author_name'] ?? '')) ?>">
                            </label>
                            <div class="tm-form-row">
                                <label>Ciudad
                                    <input type="text" name="author_location" maxlength="120" placeholder="Santiago" value="<?= e((string) ($_POST['author_location'] ?? '')) ?>">
                                </label>
                                <label>¿Por qué nos visitaste? (opcional)
                                    <input type="text" name="author_role" maxlength="160" placeholder="Consulta, emergencia..." value="<?= e((string) ($_POST['author_role'] ?? '')) ?>">
                                </label>
                            </div>

                            <fieldset class="tm-rating-field">
                                <legend>Tu calificación</legend>
                                <div class="tm-rating-input" role="radiogroup" aria-label="Calificación">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                                        <label for="star<?= $i ?>" title="<?= $i ?> estrellas"><i data-lucide="star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </fieldset>

                            <label>Tu experiencia *
                                <textarea name="body" rows="4" required minlength="15" maxlength="1500" placeholder="Cuéntanos cómo fue tu atención en el hospital..."><?= e((string) ($_POST['body'] ?? '')) ?></textarea>
                            </label>
                            <label>Correo (opcional, no se publica)
                                <input type="email" name="contact_email" maxlength="160" placeholder="Solo por si necesitamos confirmarte algo" value="<?= e((string) ($_POST['contact_email'] ?? '')) ?>">
                            </label>

                            <label class="tm-consent">
                                <input type="checkbox" name="consent" value="1" required>
                                Autorizo al Hospital General Las Colinas a publicar mi testimonio en su sitio web.
                            </label>

                            <?php if ($showCaptcha): ?>
                                <div class="h-captcha" data-sitekey="<?= e($hcaptchaSiteKey) ?>"></div>
                            <?php endif; ?>

                            <button type="submit" class="tm-form-submit">
                                <i data-lucide="send" class="h-4 w-4"></i> Enviar mi testimonio
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <?php render_public_footer($assets, $contact, $year); ?>
    <?php render_appointment_modal($services); ?>

    <script src="<?= e(base_url('assets/js/lucide.min.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide.min.js') ?: 1)) ?>"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    <script defer src="<?= e(base_url('assets/js/track.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/track.js') ?: 1)) ?>"></script>
</body>

</html>
