<?php
$leadershipAreas = [
    [
        'number' => '01',
        'icon' => 'users-round',
        'title' => 'Recursos Humanos',
        'text' => 'Desarrolla el talento, la formación continua y el bienestar de los equipos clínicos y administrativos.',
    ],
    [
        'number' => '02',
        'icon' => 'stethoscope',
        'title' => 'Médica y Servicios',
        'text' => 'Coordina especialidades, calidad asistencial, seguridad del paciente y continuidad de los servicios.',
    ],
    [
        'number' => '03',
        'icon' => 'chart-no-axes-combined',
        'title' => 'Finanzas',
        'text' => 'Gestiona los recursos con disciplina, transparencia y una visión sostenible de la operación hospitalaria.',
    ],
    [
        'number' => '04',
        'icon' => 'target',
        'title' => 'Planificación',
        'text' => 'Convierte la estrategia institucional en proyectos, indicadores y procesos de mejora continua.',
    ],
    [
        'number' => '05',
        'icon' => 'building-2',
        'title' => 'Servicios Generales',
        'text' => 'Asegura infraestructura, mantenimiento, abastecimiento y soporte para una operación clínica confiable.',
    ],
];

$governanceCommitments = [
    ['title' => 'Seguridad del paciente', 'text' => 'Las decisiones se orientan a reducir riesgos y fortalecer una atención clínica segura.'],
    ['title' => 'Calidad asistencial', 'text' => 'Los equipos trabajan con criterios comunes, revisión continua y responsabilidad compartida.'],
    ['title' => 'Continuidad del servicio', 'text' => 'La coordinación clínica y administrativa sostiene la capacidad de respuesta del hospital.'],
    ['title' => 'Gestión responsable', 'text' => 'Los recursos, la infraestructura y el talento se administran con visión de largo plazo.'],
];
?>

<main id="contenido" class="leadership-main">
    <section class="leadership-hero" aria-labelledby="leadershipTitle">
        <div class="leadership-shell">
            <nav class="leadership-breadcrumb" aria-label="Ruta de navegación">
                <a href="<?= e(base_url()) ?>">Inicio</a>
                <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                <span>Liderazgo institucional</span>
            </nav>

            <div class="leadership-hero-grid">
                <div class="leadership-hero-copy">
                    <p class="leadership-eyebrow">Gobernanza hospitalaria</p>
                    <h1 id="leadershipTitle">Liderazgo institucional</h1>
                    <p class="leadership-hero-lead">Una estructura médica y administrativa alineada para proteger la calidad clínica, la seguridad del paciente y la continuidad de cada servicio.</p>

                    <div class="leadership-hero-actions">
                        <a href="#direccion-general" class="btn btn-navy btn-lg">
                            Conocer la dirección
                            <i data-lucide="arrow-down" class="h-4 w-4"></i>
                        </a>
                        <a href="<?= e(base_url('nosotros')) ?>" class="leadership-text-link">
                            Conocer el hospital
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                </div>

                <figure class="leadership-hero-media">
                    <picture>
                        <source type="image/webp"
                            srcset="<?= e(base_url('assets/site/assets/liderazgo-hospital-960.webp')) ?> 960w,
                                    <?= e(base_url('assets/site/assets/liderazgo-hospital-1440.webp')) ?> 1440w,
                                    <?= e(base_url('assets/site/assets/liderazgo-hospital.webp')) ?> 1920w"
                            sizes="(max-width: 900px) calc(100vw - 2rem), min(54vw, 700px)">
                        <img src="<?= e(base_url($assets['hero'])) ?>"
                            alt="Entrada principal del Hospital General Las Colinas"
                            width="1920" height="1371" fetchpriority="high" decoding="async">
                    </picture>
                    <figcaption>
                        <span>Hospital General Las Colinas</span>
                        <strong>Santiago de los Caballeros</strong>
                    </figcaption>
                </figure>
            </div>

            <dl class="leadership-facts" aria-label="Estructura de liderazgo">
                <div>
                    <dt>Dirección</dt>
                    <dd>Gerencia General</dd>
                </div>
                <div>
                    <dt>Estructura</dt>
                    <dd>5 áreas gerenciales</dd>
                </div>
                <div>
                    <dt>Alcance</dt>
                    <dd>Operación clínica 24/7</dd>
                </div>
            </dl>
        </div>
    </section>

    <section id="direccion-general" class="leadership-director" aria-labelledby="directorHeading">
        <div class="leadership-shell leadership-director-grid">
            <figure class="leadership-director-media">
                <img src="<?= e(base_url('assets/Gerente-General-Colinas-premium.png')) ?>"
                    alt="Retrato institucional del Dr. Rafael Sánchez Cárdenas"
                    width="1439" height="1093" loading="lazy" decoding="async">
            </figure>

            <div class="leadership-director-copy">
                <p class="leadership-section-index">01 / Dirección general</p>
                <h2 id="directorHeading">Experiencia para dirigir con criterio clínico y visión institucional.</h2>
                <div class="leadership-director-identity">
                    <strong>Dr. Rafael Sánchez Cárdenas</strong>
                    <span>Gerente General</span>
                </div>
                <p>Médico, docente y gestor de salud pública. Su trayectoria reúne práctica clínica, dirección académica y conducción institucional al servicio de una atención segura, ética y centrada en las personas.</p>
            </div>
        </div>
    </section>

    <section class="leadership-structure" aria-labelledby="structureHeading">
        <div class="leadership-shell leadership-structure-grid">
            <header class="leadership-structure-intro">
                <p class="leadership-section-index">02 / Estructura gerencial</p>
                <h2 id="structureHeading">Responsabilidades claras. Una sola dirección.</h2>
                <p>Cinco áreas especializadas articulan personas, práctica médica, recursos y operación para que cada decisión llegue de forma consistente a la atención.</p>
            </header>

            <ol class="leadership-area-list">
                <?php foreach ($leadershipAreas as $area): ?>
                    <li>
                        <span class="leadership-area-number"><?= e($area['number']) ?></span>
                        <span class="leadership-area-icon"><i data-lucide="<?= e($area['icon']) ?>" class="h-5 w-5"></i></span>
                        <div>
                            <h3><?= e($area['title']) ?></h3>
                            <p><?= e($area['text']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="leadership-governance" aria-labelledby="governanceHeading">
        <div class="leadership-shell">
            <div class="leadership-governance-head">
                <p class="leadership-section-index">03 / Principios de gestión</p>
                <h2 id="governanceHeading">Cómo se gobierna la atención</h2>
                <p>La gestión institucional conecta la estrategia con la práctica diaria mediante prioridades que pueden traducirse en decisiones concretas.</p>
            </div>

            <dl class="leadership-commitments">
                <?php foreach ($governanceCommitments as $index => $commitment): ?>
                    <div>
                        <dt>
                            <span><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <?= e($commitment['title']) ?>
                        </dt>
                        <dd><?= e($commitment['text']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <section class="leadership-closing" aria-labelledby="leadershipClosingHeading">
        <div class="leadership-shell leadership-closing-panel">
            <div>
                <p>Hospital General Las Colinas</p>
                <h2 id="leadershipClosingHeading">Una dirección preparada para cuidar lo que importa.</h2>
            </div>
            <div class="leadership-closing-actions">
                <a href="<?= e(base_url('directorio-medico')) ?>" class="btn btn-green btn-lg">
                    Conocer el equipo médico
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
                <a href="<?= e(base_url('contacto')) ?>" class="leadership-closing-link">Contacto</a>
            </div>
        </div>
    </section>
</main>
