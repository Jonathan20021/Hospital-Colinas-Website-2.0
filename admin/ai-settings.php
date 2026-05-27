<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('ai');
ai_ensure_schema();

$error = '';
$saved = false;
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    try {
        if ($action === 'test') {
            $settings = ai_settings_load();
            $rawKey = trim((string) ($_POST['api_key'] ?? ''));
            if ($rawKey !== '') {
                $settings['api_key'] = $rawKey;
            }
            $settings['model'] = trim((string) ($_POST['model'] ?? $settings['model'])) ?: $settings['model'];

            if ($settings['api_key'] === '') {
                throw new RuntimeException('Falta la API key.');
            }

            $result = ai_call_openai([
                ['role' => 'system', 'content' => 'Eres un asistente de prueba. Responde con una sola palabra.'],
                ['role' => 'user', 'content' => 'Di "ok".'],
            ], $settings);

            $testResult = $result['ok']
                ? ['ok' => true, 'message' => 'Conexión OK. Respuesta: ' . substr(trim((string) $result['content']), 0, 200)]
                : ['ok' => false, 'message' => $result['error'] ?? 'Error desconocido.'];
        } else {
            if (!ai_settings_save($_POST)) {
                throw new RuntimeException('No se pudo guardar la configuración.');
            }
            $saved = true;
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$settings = ai_settings_load();
$hasKey = $settings['api_key'] !== '';
$maskedKey = $hasKey ? str_repeat('•', 8) . substr($settings['api_key'], -4) : '';

$availableModels = [
    'gpt-4o-mini' => 'GPT-4o mini · rápido y económico (recomendado)',
    'gpt-4o' => 'GPT-4o · máxima calidad multimodal',
    'gpt-4.1-mini' => 'GPT-4.1 mini · balance velocidad/calidad',
    'gpt-4.1' => 'GPT-4.1 · razonamiento avanzado',
    'gpt-5-mini' => 'GPT-5 mini · siguiente generación, rápido',
    'gpt-5' => 'GPT-5 · siguiente generación, máxima calidad',
    'o4-mini' => 'o4-mini · razonamiento profundo, económico',
];

admin_header('Colinas IA', 'ai');
?>
<div class="admin-panel ai-settings-panel">
    <?php if ($saved): ?>
        <div class="admin-alert is-success">Configuración guardada correctamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($testResult): ?>
        <div class="admin-alert <?= $testResult['ok'] ? 'is-success' : 'is-error' ?>">
            <?= e($testResult['message']) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="ai-form" id="aiSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="admin-panel-head">
            <div>
                <span>Asistente virtual</span>
                <h2>Configuración de Colinas IA</h2>
            </div>
            <div class="ai-form-actions">
                <button type="submit" name="action" value="test" class="admin-secondary-action">
                    <i data-lucide="zap"></i>
                    Probar conexión
                </button>
                <button type="submit" name="action" value="save" class="admin-primary-action">
                    <i data-lucide="check"></i>
                    Guardar
                </button>
            </div>
        </div>

        <div class="ai-toggle-row">
            <label class="ai-switch">
                <input type="checkbox" name="enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?>>
                <span class="ai-switch-track"><span></span></span>
                <span>
                    <strong>Activar Colinas IA</strong>
                    <small>Muestra el widget flotante en lugar del botón de WhatsApp.</small>
                </span>
            </label>
            <span class="ai-status <?= ai_is_ready() ? 'is-ok' : 'is-off' ?>">
                <i data-lucide="<?= ai_is_ready() ? 'circle-check' : 'circle-x' ?>"></i>
                <?= ai_is_ready() ? 'Operativo' : ($hasKey ? 'Desactivado' : 'Falta API key') ?>
            </span>
        </div>

        <fieldset class="ai-fieldset">
            <legend><i data-lucide="key-round"></i> Credenciales de OpenAI</legend>

            <label class="ai-field ai-field-full">
                <span>API Key de OpenAI</span>
                <div class="ai-key-row">
                    <input type="password" name="api_key" autocomplete="off" placeholder="<?= $hasKey ? 'Guardada (' . e($maskedKey) . '). Deja vacío para no cambiar.' : 'sk-...' ?>">
                    <button type="button" class="ai-key-toggle" data-toggle-key aria-label="Mostrar/ocultar">
                        <i data-lucide="eye"></i>
                    </button>
                </div>
                <small>Se guarda cifrada en la base de datos. Nunca se expone al frontend ni se envía al navegador.</small>
            </label>

            <label class="ai-field">
                <span>Modelo</span>
                <select name="model">
                    <?php foreach ($availableModels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $settings['model'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                    <?php if (!isset($availableModels[$settings['model']])): ?>
                        <option value="<?= e($settings['model']) ?>" selected><?= e($settings['model']) ?> (personalizado)</option>
                    <?php endif; ?>
                </select>
                <small>El modelo debe estar habilitado en tu cuenta OpenAI.</small>
            </label>

            <div class="ai-field-row">
                <label class="ai-field">
                    <span>Creatividad (temperature)</span>
                    <input type="number" name="temperature" min="0" max="2" step="0.1" value="<?= e((string) $settings['temperature']) ?>">
                    <small>0.0 = preciso · 2.0 = creativo. Sugerido 0.3-0.6.</small>
                </label>
                <label class="ai-field">
                    <span>Tokens máximos por respuesta</span>
                    <input type="number" name="max_tokens" min="50" max="4000" step="50" value="<?= e((string) $settings['max_tokens']) ?>">
                    <small>Límite por respuesta. 700 suele ser suficiente.</small>
                </label>
            </div>
        </fieldset>

        <fieldset class="ai-fieldset">
            <legend><i data-lucide="message-square-text"></i> Personalización</legend>

            <div class="ai-field-row">
                <label class="ai-field">
                    <span>Nombre del asistente</span>
                    <input type="text" name="assistant_name" maxlength="80" value="<?= e($settings['assistant_name']) ?>">
                </label>
                <label class="ai-field">
                    <span>Mensaje de bienvenida</span>
                    <input type="text" name="welcome_message" maxlength="240" value="<?= e($settings['welcome_message']) ?>">
                </label>
            </div>

            <label class="ai-field ai-field-full">
                <span>Instrucciones adicionales (opcional)</span>
                <textarea name="system_prompt_extra" rows="6" placeholder="Ej: Promociones vigentes, campañas de salud activas, notas especiales sobre seguros..."><?= e($settings['system_prompt_extra']) ?></textarea>
                <small>Se agrega al system prompt automáticamente. El prompt base incluye reglas de seguridad clínica, directorio de médicos y especialidades.</small>
            </label>
        </fieldset>

        <div class="ai-info-box">
            <i data-lucide="shield-check"></i>
            <div>
                <strong>Reglas de seguridad activas</strong>
                <p>El asistente NUNCA da diagnósticos ni recomendaciones de tratamiento. Para síntomas refiere al especialista correspondiente del directorio. Para emergencias indica llamar al <?= e($contact['phone']) ?> o acudir a Emergencias 24/7.</p>
            </div>
        </div>
    </form>
</div>

<script>
    document.querySelector('[data-toggle-key]')?.addEventListener('click', () => {
        const input = document.querySelector('input[name="api_key"]');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    });
</script>
<?php admin_footer(); ?>
