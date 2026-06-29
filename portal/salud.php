<?php
/**
 * Mi Salud — hub de herramientas de bienestar del Portal del Paciente.
 * Aloja "Mi Ciclo" (control menstrual) y queda abierto para sumar más
 * herramientas. Visible para todos; "Mi Ciclo" se destaca para pacientes
 * con sexo femenino en el expediente.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);

$gender   = strtolower(trim((string) ($patient['gender'] ?? '')));
$isFemale = in_array($gender, ['female', 'f', 'femenino'], true);

$pName    = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$friendly = trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$first    = trim(explode(' ', $friendly)[0] ?? '');

// Herramientas del módulo (además de la estrella "Mi Ciclo"). active=disponible.
$tools = [
    ['icon' => 'heart-pulse',  'name' => 'Mis signos vitales',       'desc' => 'Tendencias de presión, peso, glucosa, pulso y más, con interpretación clara.', 'url' => 'portal/vitales.php', 'active' => true],
    ['icon' => 'notebook-pen', 'name' => 'Diario de síntomas',       'desc' => 'Registra cómo te sientes día a día y compártelo con tu médico en la consulta.', 'url' => 'portal/sintomas.php', 'active' => true],
    ['icon' => 'pill',         'name' => 'Mis medicamentos',         'desc' => 'Lleva el control de tus medicinas, horarios y tomas del día.', 'url' => 'portal/medicamentos.php', 'active' => true],
    ['icon' => 'bell-ring',    'name' => 'Recordatorios de prevención', 'desc' => 'Chequeos y tamizajes según tu edad y sexo: Papanicolaou, mamografía, próstata y más.', 'url' => 'portal/prevencion.php', 'active' => true],
    ['icon' => 'baby',         'name' => 'Embarazo semana a semana', 'desc' => 'Sigue el crecimiento de tu bebé, su tamaño, la fecha de parto y consejos por trimestre.', 'url' => 'portal/embarazo.php', 'active' => true],
];
$activeTools = array_values(array_filter($tools, fn($t) => $t['active']));
$soonTools   = array_values(array_filter($tools, fn($t) => !$t['active']));

$GLOBALS['portal_extra_css'] = ['portal-salud.css'];
portal_layout_begin('Mi Salud', 'salud');
?>
<header class="portal-page-title">
    <div>
        <h1>Mi Salud</h1>
        <p>Herramientas para cuidarte entre una consulta y la otra<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
    </div>
</header>

<div class="salud-wrap">
    <!-- Herramienta estrella: Mi Ciclo -->
    <a class="salud-feature<?= $isFemale ? ' is-recommended' : '' ?>" href="<?= e(base_url('portal/ciclo.php')) ?>">
        <div class="salud-feature-glow" aria-hidden="true"></div>
        <div class="salud-feature-body">
            <div class="salud-feature-icon"><i data-lucide="venus"></i></div>
            <div class="salud-feature-copy">
                <?php if ($isFemale): ?><span class="salud-chip">Recomendado para ti</span><?php endif; ?>
                <h2>Mi Ciclo</h2>
                <p>Predice tu periodo, tu ventana fértil y la ovulación. Registra síntomas y ánimo, y lleva un resumen claro a tu ginecólogo.</p>
                <ul class="salud-feature-points">
                    <li><i data-lucide="circle-check"></i> Predicción inteligente del periodo y la ovulación</li>
                    <li><i data-lucide="circle-check"></i> Calendario y registro diario de síntomas</li>
                    <li><i data-lucide="circle-check"></i> Resumen para tu cita en el hospital</li>
                </ul>
                <span class="salud-feature-cta">Abrir Mi Ciclo <i data-lucide="arrow-right"></i></span>
            </div>
        </div>
        <div class="salud-feature-ring" aria-hidden="true">
            <svg viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" class="ring-track"></circle>
                <circle cx="60" cy="60" r="52" class="ring-period"></circle>
                <circle cx="60" cy="60" r="52" class="ring-fertile"></circle>
            </svg>
            <div class="salud-feature-ring-center"><i data-lucide="flower-2"></i></div>
        </div>
    </a>

    <!-- Herramientas disponibles -->
    <?php if ($activeTools): ?>
    <section class="salud-section">
        <div class="salud-section-head">
            <h2>Más herramientas</h2>
            <p>Disponibles para ti ahora mismo.</p>
        </div>
        <div class="salud-grid">
            <?php foreach ($activeTools as $t): ?>
                <a class="salud-card is-tool" href="<?= e(base_url($t['url'])) ?>">
                    <div class="salud-card-icon"><i data-lucide="<?= e($t['icon']) ?>"></i></div>
                    <h3><?= e($t['name']) ?></h3>
                    <p><?= e($t['desc']) ?></p>
                    <span class="salud-card-cta">Abrir <i data-lucide="arrow-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Próximamente -->
    <?php if ($soonTools): ?>
    <section class="salud-section">
        <div class="salud-section-head">
            <h2>Próximamente</h2>
            <p>Estamos sumando más herramientas a tu portal.</p>
        </div>
        <div class="salud-grid">
            <?php foreach ($soonTools as $t): ?>
                <div class="salud-card is-soon" aria-disabled="true">
                    <span class="salud-card-soon">Pronto</span>
                    <div class="salud-card-icon"><i data-lucide="<?= e($t['icon']) ?>"></i></div>
                    <h3><?= e($t['name']) ?></h3>
                    <p><?= e($t['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <p class="salud-privacy">
        <i data-lucide="shield-check"></i>
        Tu información de salud es privada y se guarda protegida en el hospital. Solo tú puedes verla desde tu cuenta.
    </p>
</div>
<?php portal_layout_end();
