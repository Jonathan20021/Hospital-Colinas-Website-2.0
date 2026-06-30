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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (!ai_settings_save($_POST)) {
            throw new RuntimeException('No se pudo guardar la configuración.');
        }
        $saved = true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$settings = ai_settings_load();

admin_header('Colinas IA', 'ai');
?>
<div class="admin-panel ai-settings-panel">
    <?php if ($saved): ?>
        <div class="admin-alert is-success">Configuración guardada correctamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert is-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="ai-form" id="aiSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="admin-panel-head">
            <div>
                <span>Asistente virtual</span>
                <h2>Configuración de Colinas IA</h2>
            </div>
            <div class="ai-form-actions">
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
            <span class="ai-status <?= $settings['enabled'] ? 'is-ok' : 'is-off' ?>">
                <i data-lucide="<?= $settings['enabled'] ? 'circle-check' : 'circle-x' ?>"></i>
                <?= $settings['enabled'] ? 'Operativo' : 'Desactivado' ?>
            </span>
        </div>

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
        </fieldset>

        <div class="ai-info-box">
            <i data-lucide="cpu"></i>
            <div>
                <strong>Asistente automático (sin IA externa)</strong>
                <p>Colinas IA responde con reglas internas y datos en vivo del directorio médico (especialidades y médicos reales). No depende de servicios externos ni de claves de API, y nunca inventa información. Para agendar, deriva al formulario de citas en línea.</p>
            </div>
        </div>

        <div class="ai-info-box">
            <i data-lucide="shield-check"></i>
            <div>
                <strong>Reglas de seguridad activas</strong>
                <p>El asistente NUNCA da diagnósticos ni recomendaciones de tratamiento. Para síntomas orienta hacia la especialidad correspondiente del directorio. Para emergencias indica llamar al <?= e($contact['phone']) ?> o acudir a Emergencias 24/7.</p>
            </div>
        </div>
    </form>
</div>
<?php admin_footer(); ?>
