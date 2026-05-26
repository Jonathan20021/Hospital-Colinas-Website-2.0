<?php
// Renders the Colinas IA floating widget.
// Requires: $assets, $services, $contact in scope.
// Includes ai.php (which auto-creates the schema).

require_once __DIR__ . '/ai.php';

$aiConfig = ai_public_config();
$aiDoctors = $aiConfig['enabled'] ? ai_doctors_for_widget($services, $assets) : [];

// Normalize doctor photo URLs through base_url so the widget cards work from any URL depth.
foreach ($aiDoctors as &$doc) {
    if (!empty($doc['photo']) && strpos($doc['photo'], 'http') !== 0 && $doc['photo'][0] !== '/') {
        $doc['photo'] = base_url($doc['photo']);
    }
}
unset($doc);

$widgetData = [
    'endpoint' => base_url('api/chat.php'),
    'basePath' => base_url(),
    'assistantName' => $aiConfig['assistant_name'],
    'welcomeMessage' => $aiConfig['welcome_message'],
    'doctors' => $aiDoctors,
];
?>

<?php if ($aiConfig['enabled']): ?>
<div id="colinasAi" class="cai-root" data-cai>
    <button type="button" class="cai-fab" aria-label="Abrir asistente Colinas IA" aria-expanded="false">
        <span class="cai-fab-badge"><i data-lucide="sparkles" class="h-3 w-3"></i></span>
        <i data-lucide="sparkles" class="cai-fab-closed"></i>
        <i data-lucide="x" class="cai-fab-open"></i>
    </button>

    <div class="cai-panel" role="dialog" aria-modal="false" aria-hidden="true" aria-label="Asistente Colinas IA">
        <header class="cai-header">
            <span class="cai-header-avatar"><i data-lucide="sparkles"></i></span>
            <div class="cai-header-title">
                <strong><?= e($aiConfig['assistant_name']) ?></strong>
                <small>En línea · Hospital Las Colinas</small>
            </div>
            <button type="button" class="cai-reset" aria-label="Nueva conversación" title="Nueva conversación">
                <i data-lucide="refresh-cw"></i>
            </button>
            <button type="button" class="cai-close" aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>

        <div class="cai-messages" role="log" aria-live="polite"></div>

        <div class="cai-quick" aria-label="Sugerencias"></div>

        <form class="cai-form" autocomplete="off">
            <textarea class="cai-input" rows="1" placeholder="Escribe tu pregunta..." aria-label="Tu mensaje"></textarea>
            <button type="submit" class="cai-send" aria-label="Enviar mensaje">
                <i data-lucide="send"></i>
            </button>
        </form>

        <p class="cai-footnote">
            <i data-lucide="shield-check"></i>
            Colinas IA no brinda diagnóstico médico. Para emergencias llama al <a href="tel:18098060444" class="cai-link" style="font-size:.7rem;">(809) 806-0444</a>.
        </p>
    </div>

    <script id="colinasAiData" type="application/json"><?= json_encode($widgetData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</div>
<script src="<?= e(base_url('assets/js/colinas-ai.js')) ?>?v=<?= e($assetVersion) ?>" defer></script>

<?php else: ?>
<a href="<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener" class="whatsapp-float" aria-label="Contactar por WhatsApp">
    <i data-lucide="message-circle" class="h-7 w-7"></i>
</a>
<?php endif; ?>
