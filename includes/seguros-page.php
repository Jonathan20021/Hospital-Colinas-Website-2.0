<?php
/**
 * Página dedicada de Seguros aceptados (/seguros-aceptados).
 * Diseño propio (scope .seg-*), no la plantilla genérica de contenido.
 * Usa $insurers / $insurersDir (data.php) y $contact. Marca: navy #262161 + verde #5da334.
 */
$segPasos = [
    ['n' => '01', 'icon' => 'id-card',      'title' => 'Trae tu documentación', 'text' => 'Cédula, carnet o número de afiliado y, si tu ARS lo pide, tu referimiento médico.'],
    ['n' => '02', 'icon' => 'search-check', 'title' => 'Verificamos tu cobertura', 'text' => 'En admisión confirmamos qué cubre tu seguro para el servicio que necesitas.'],
    ['n' => '03', 'icon' => 'file-check-2',  'title' => 'Autorización, si aplica', 'text' => 'Algunos estudios o procedimientos requieren aprobación previa de la aseguradora. Te ayudamos a gestionarla.'],
    ['n' => '04', 'icon' => 'circle-check',  'title' => 'Recibe tu atención', 'text' => 'Con todo en orden, pasas a tu consulta, estudio o procedimiento.'],
];
$segTraer = [
    ['icon' => 'contact',       'text' => 'Cédula de identidad (o pasaporte)'],
    ['icon' => 'credit-card',   'text' => 'Carnet del seguro o número de afiliado'],
    ['icon' => 'file-text',     'text' => 'Referimiento médico, si tu ARS lo requiere'],
    ['icon' => 'folder-open',   'text' => 'Estudios o récords previos relacionados'],
];
$segCobertura = [
    ['icon' => 'stethoscope', 'title' => 'Ambulatorio', 'text' => 'Consultas con especialistas, estudios de imagen y laboratorio, y procedimientos que no requieren internamiento.'],
    ['icon' => 'bed-double',  'title' => 'Hospitalario', 'text' => 'Emergencias, cirugías, maternidad e internamiento, según la cobertura de tu póliza.'],
];
$segFaq = [
    ['q' => '¿No ves tu ARS en la lista?', 'a' => 'Seguimos ampliando convenios. Escríbenos por WhatsApp con el nombre de tu aseguradora y te confirmamos si ya trabajamos con ella.'],
    ['q' => '¿Qué es una autorización previa?', 'a' => 'Es la aprobación que tu aseguradora da para cubrir ciertos estudios o procedimientos. En admisión te decimos si tu servicio la necesita y te ayudamos a tramitarla.'],
    ['q' => '¿Tengo que pagar copago?', 'a' => 'Depende de tu póliza. Algunos servicios tienen un copago que asume el afiliado. Te informamos el monto antes de atenderte.'],
    ['q' => '¿Puedo atenderme sin seguro?', 'a' => 'Sí. Ofrecemos atención privada; consulta nuestras tarifas en admisión o por WhatsApp antes de tu visita.'],
];
?>
<main id="contenido" class="seg-main">

    <!-- HERO + pared de logos (la prueba) -->
    <section class="seg-hero">
        <div class="seg-shell">
            <nav class="seg-breadcrumb" aria-label="Ruta de navegación">
                <a href="<?= e(base_url()) ?>">Inicio</a>
                <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                <span>Seguros aceptados</span>
            </nav>

            <div class="seg-hero-grid">
                <div class="seg-hero-copy">
                    <p class="seg-eyebrow"><i data-lucide="shield-check" class="h-4 w-4"></i> Convenios y aseguradoras</p>
                    <h1>Tu seguro es bienvenido en Las&nbsp;Colinas</h1>
                    <p class="seg-hero-lead">Trabajamos con las principales ARS del país, con cobertura ambulatoria y hospitalaria. Te acompañamos en cada paso: cobertura, autorizaciones y lo que necesites.</p>
                    <div class="seg-hero-actions">
                        <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="seg-btn seg-btn-green">
                            <i data-lucide="message-circle" class="h-4 w-4"></i> Verificar mi cobertura
                        </a>
                        <a href="<?= e(base_url('agendar')) ?>" class="seg-btn seg-btn-ghost">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i> Agendar cita
                        </a>
                    </div>
                </div>

                <div class="seg-hero-wall" aria-label="Aseguradoras aceptadas">
                    <p class="seg-wall-label"><i data-lucide="badge-check" class="h-4 w-4"></i> ARS con las que trabajamos</p>
                    <div class="seg-logo-grid">
                        <?php foreach ($insurers as $insurer): ?>
                            <div class="seg-logo-card" title="<?= e($insurer['name']) ?>">
                                <img src="<?= e(base_url($insurersDir . $insurer['file'])) ?>"<?= img_dimensions($insurersDir . $insurer['file']) ?> alt="<?= e($insurer['name']) ?>" loading="eager">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cómo usar tu seguro: proceso real (secuencia → numerado) -->
    <section class="seg-section">
        <div class="seg-shell">
            <header class="seg-head">
                <p class="seg-kicker">Paso a paso</p>
                <h2>Cómo usar tu seguro</h2>
                <p>Un proceso claro para que tu visita fluya sin sorpresas administrativas.</p>
            </header>
            <ol class="seg-steps">
                <?php foreach ($segPasos as $p): ?>
                    <li class="seg-step">
                        <span class="seg-step-num"><?= e($p['n']) ?></span>
                        <span class="seg-step-icon"><i data-lucide="<?= e($p['icon']) ?>" class="h-5 w-5"></i></span>
                        <h3><?= e($p['title']) ?></h3>
                        <p><?= e($p['text']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- Qué traer + Cobertura -->
    <section class="seg-section seg-section-soft">
        <div class="seg-shell seg-split">
            <div class="seg-bring">
                <p class="seg-kicker">Antes de venir</p>
                <h2>Qué necesitas traer</h2>
                <ul class="seg-checklist">
                    <?php foreach ($segTraer as $t): ?>
                        <li>
                            <span class="seg-check"><i data-lucide="check" class="h-4 w-4"></i></span>
                            <i data-lucide="<?= e($t['icon']) ?>" class="h-4 w-4 seg-check-ic"></i>
                            <?= e($t['text']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="seg-note"><i data-lucide="info" class="h-4 w-4"></i> Según tu póliza puede aplicar un copago. Te informamos el monto antes de atenderte.</p>
            </div>

            <div class="seg-coverage">
                <p class="seg-kicker">Tu cobertura</p>
                <h2>Ambulatorio y hospitalario</h2>
                <div class="seg-cover-cards">
                    <?php foreach ($segCobertura as $c): ?>
                        <article class="seg-cover-card">
                            <span class="seg-cover-ic"><i data-lucide="<?= e($c['icon']) ?>" class="h-5 w-5"></i></span>
                            <h3><?= e($c['title']) ?></h3>
                            <p><?= e($c['text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="seg-section">
        <div class="seg-shell seg-shell-narrow">
            <header class="seg-head">
                <p class="seg-kicker">Dudas comunes</p>
                <h2>Preguntas frecuentes</h2>
            </header>
            <div class="seg-faq">
                <?php foreach ($segFaq as $i => $f): ?>
                    <details class="seg-faq-item"<?= $i === 0 ? ' open' : '' ?>>
                        <summary>
                            <span><?= e($f['q']) ?></span>
                            <i data-lucide="chevron-down" class="h-5 w-5"></i>
                        </summary>
                        <p><?= e($f['a']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA band -->
    <section class="seg-cta-band">
        <div class="seg-shell seg-cta-inner">
            <div>
                <h2>¿Dudas con tu cobertura? Hablemos.</h2>
                <p>Nuestro equipo de admisión te orienta sobre convenios, autorizaciones y copagos antes de tu visita.</p>
            </div>
            <div class="seg-cta-actions">
                <a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="seg-btn seg-btn-green seg-btn-lg">
                    <i data-lucide="message-circle" class="h-4 w-4"></i> Escríbenos por WhatsApp
                </a>
                <a href="tel:18098060444" class="seg-cta-phone">
                    <i data-lucide="phone" class="h-4 w-4"></i> <?= e($contact['phone']) ?>
                </a>
            </div>
        </div>
    </section>

</main>
