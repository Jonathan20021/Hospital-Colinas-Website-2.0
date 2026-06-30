<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/doctors-admin.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_directory.php';
require_once __DIR__ . '/../includes/doctor_avatar.php';
require __DIR__ . '/includes/layout.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

$currentUser = require_admin();
if (!admin_can('dashboard', $currentUser)) {
    header('Location: ' . admin_first_allowed_url($currentUser));
    exit;
}

// Colinas IA ahora es determinista (no requiere OpenAI): su estado depende del
// toggle de activación, no de la credencial de OpenAI.
$assistantEnabled = (bool) ai_public_config()['enabled'];

// Estadísticas de médicos: live desde la API del hospital (con cache 1h)
$apiRes = portal_directory_doctors();
$apiDoctors = $apiRes['ok'] ? $apiRes['data'] : [];

$stats = [
    'total'    => count($apiDoctors),
    'active'   => count($apiDoctors),
    'draft'    => 0,
    'featured' => count(array_filter($apiDoctors, fn($d) => !empty($d['is_featured']))),
    'photo'    => count(array_filter($apiDoctors, fn($d) => !empty($d['photo_url']))),
];

// Últimos modificados: tomamos los destacados primero + alfabético
$recentDoctors = array_slice($apiDoctors, 0, 5);

// Estadísticas de Colinas IA
$aiStats = ['queries' => 0, 'sessions' => 0, 'tokens' => 0];
$recentAiQueries = [];
try {
    $pdo = db();
    if ($pdo) {
        $queriesCount = (int) $pdo->query("SELECT COUNT(*) FROM ai_conversations WHERE role = 'user'")->fetchColumn();
        $sessionsCount = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM ai_conversations")->fetchColumn();
        $tokensSum = (int) $pdo->query("SELECT SUM(tokens) FROM ai_conversations")->fetchColumn();
        $aiStats = [
            'queries' => $queriesCount,
            'sessions' => $sessionsCount,
            'tokens' => $tokensSum,
        ];
        
        $recentAiQueries = $pdo->query("SELECT session_id, content, created_at FROM ai_conversations WHERE role = 'user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    }
} catch (Throwable $e) {
    // Si no existen las tablas de IA se omiten silenciosamente
}

// Obtener datos del hospital (contacto) para mostrar
$contactFile = __DIR__ . '/../includes/data.php';
$contact = [
    'phone' => '(809) 806-0444',
    'email' => 'info@colinashospital.com'
];
if (is_file($contactFile)) {
    $data = require $contactFile;
    if (isset($data['contact'])) {
        $contact = $data['contact'];
    }
}

admin_header('Dashboard', 'dashboard');
?>

<!-- Banner de Bienvenida Premium -->
<div class="welcome-banner">
    <div class="welcome-text">
        <h2>¡Te damos la bienvenida, <?= e(explode(' ', $currentUser['name'])[0]) ?>!</h2>
        <p>Estás en el centro de control del Hospital General Las Colinas. Desde aquí puedes gestionar el directorio de especialistas, moderar las noticias de la sala de prensa y ajustar el asistente virtual Colinas IA.</p>
    </div>
    <div class="welcome-stats-overview">
        <div class="welcome-stat-pill">
            <i data-lucide="shield-check"></i>
            <div>
                <strong><?= e($currentUser['role'] === 'admin' ? 'Administrador' : 'Editor') ?></strong>
                <span>Rol de acceso</span>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas del Directorio Médico -->
<div class="admin-panel-head" style="margin-top: 1rem; border-bottom: none; padding-bottom: 0;">
    <div>
        <span>Directorio Médico</span>
        <h2>Resumen de especialistas</h2>
    </div>
</div>
<section class="admin-stats">
    <article>
        <div class="admin-stats-info">
            <span>Total Médicos</span>
            <strong><?= e((string) $stats['total']) ?></strong>
        </div>
        <div class="admin-stats-icon">
            <i data-lucide="users"></i>
        </div>
    </article>
    <article>
        <div class="admin-stats-info">
            <span>Publicados</span>
            <strong><?= e((string) $stats['active']) ?></strong>
        </div>
        <div class="admin-stats-icon" style="color: var(--success); background-color: var(--success-light);">
            <i data-lucide="check-circle-2"></i>
        </div>
    </article>
    <article>
        <div class="admin-stats-info">
            <span>Con foto</span>
            <strong><?= e((string) $stats['photo']) ?></strong>
        </div>
        <div class="admin-stats-icon" style="color: #2563eb; background-color: #eff6ff;">
            <i data-lucide="image"></i>
        </div>
    </article>
    <article>
        <div class="admin-stats-info">
            <span>Destacados</span>
            <strong><?= e((string) $stats['featured']) ?></strong>
        </div>
        <div class="admin-stats-icon" style="color: #3b82f6; background-color: #eff6ff;">
            <i data-lucide="star"></i>
        </div>
    </article>
</section>

<!-- Estadísticas de Asistente Virtual Colinas IA -->
<div class="admin-panel-head" style="border-bottom: none; padding-bottom: 0;">
    <div>
        <span>Colinas IA</span>
        <h2>Rendimiento de asistente virtual</h2>
    </div>
</div>
<section class="admin-stats">
    <article class="ai-card-stat">
        <div class="admin-stats-info">
            <span>Consultas Pacientes</span>
            <strong><?= e((string) $aiStats['queries']) ?></strong>
        </div>
        <div class="admin-stats-icon">
            <i data-lucide="message-square-more"></i>
        </div>
    </article>
    <article class="ai-card-stat">
        <div class="admin-stats-info">
            <span>Chats Únicos</span>
            <strong><?= e((string) $aiStats['sessions']) ?></strong>
        </div>
        <div class="admin-stats-icon">
            <i data-lucide="sparkles"></i>
        </div>
    </article>
    <article class="ai-card-stat">
        <div class="admin-stats-info">
            <span>Tokens Procesados</span>
            <strong><?= number_format($aiStats['tokens']) ?></strong>
        </div>
        <div class="admin-stats-icon">
            <i data-lucide="zap"></i>
        </div>
    </article>
    <article class="ai-card-stat">
        <div class="admin-stats-info">
            <span>Estado Asistente</span>
            <strong style="font-size: 1.35rem; margin-top: 0.8rem; font-family: 'Inter', sans-serif;">
                <?= $assistantEnabled ? 'Operativo' : 'Inactivo' ?>
            </strong>
        </div>
        <div class="admin-stats-icon" style="<?= $assistantEnabled ? 'color: var(--success); background-color: var(--success-light);' : 'color: var(--danger); background-color: var(--danger-light);' ?>">
            <i data-lucide="<?= $assistantEnabled ? 'bolt' : 'bolt-off' ?>"></i>
        </div>
    </article>
</section>

<!-- Grid Principal del Dashboard -->
<section class="admin-dashboard-grid">
    <!-- Columna Izquierda: Listados y feeds -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Últimos Médicos Editados -->
        <div class="admin-panel">
            <div class="admin-panel-head" style="border-bottom: none; margin-bottom: 1rem; padding-bottom: 0;">
                <div>
                    <span>Directorio</span>
                    <h2>Últimos perfiles modificados</h2>
                </div>
                <?php if (admin_can('doctors', $currentUser)): ?>
                    <a href="medicos.php">Gestionar <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i></a>
                <?php endif; ?>
            </div>
            
            <div class="admin-list">
                <?php if (!$recentDoctors): ?>
                    <div class="admin-empty-state" style="padding: 2rem 1rem;">
                        <span><i data-lucide="user-round-plus"></i></span>
                        <strong>No hay perfiles de médicos</strong>
                        <p>Crea el primer perfil para activar el directorio médico público.</p>
                        <a href="medico-form.php" style="margin-top: 0.5rem;">Crear médico</a>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($recentDoctors as $doctor):
                    $photo = !empty($doctor['photo_url'])
                        ? portal_directory_photo_url($doctor['photo_url'])
                        : doctor_avatar_svg($doctor['name'] ?? 'Médico');
                ?>
                    <a href="medicos.php#doc-<?= (int)$doctor['id'] ?>" class="admin-list-row">
                        <img src="<?= e($photo) ?>" alt="" style="background:#f1f5f9;border-radius:50%">
                        <span>
                            <strong><?= e($doctor['name']) ?></strong>
                            <small><?= e($doctor['specialty'] ?? 'Sin especialidad') ?></small>
                        </span>
                        <em class="active">publicado</em>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Últimas consultas de Colinas IA -->
        <div class="admin-panel">
            <div class="admin-panel-head" style="border-bottom: none; margin-bottom: 1rem; padding-bottom: 0;">
                <div>
                    <span>Sala de control de IA</span>
                    <h2>Consultas recientes de pacientes</h2>
                </div>
                <?php if (admin_can('ai', $currentUser)): ?>
                    <a href="ai-settings.php">Ajustar IA <i data-lucide="sliders" style="width: 14px; height: 14px;"></i></a>
                <?php endif; ?>
            </div>

            <div class="ai-activity-list">
                <?php if (!$recentAiQueries): ?>
                    <div class="admin-empty-state" style="padding: 2rem 1rem;">
                        <span><i data-lucide="message-square-off"></i></span>
                        <strong>Sin consultas registradas</strong>
                        <p>Las preguntas de los pacientes a Colinas IA aparecerán aquí una vez activado el widget.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($recentAiQueries as $query): ?>
                    <div class="ai-activity-row">
                        <div class="ai-activity-meta">
                            <span class="ai-activity-session">
                                <i data-lucide="message-square"></i>
                                Sesión: <?= e(substr($query['session_id'], 0, 8)) ?>...
                            </span>
                            <span><?= e(date('d/m/Y H:i', strtotime($query['created_at']))) ?></span>
                        </div>
                        <div class="ai-activity-content">
                            <?= e(mb_strimwidth($query['content'], 0, 180, '...')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Columna Derecha: Estado de Servidores y Siguiente Fase -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Estado de Conexión -->
        <div class="admin-panel admin-next-panel">
            <span>Diagnóstico</span>
            <h2>Estado del sistema</h2>
            <div class="status-indicator-list">
                <div class="status-indicator-item">
                    <span>Base de datos (MySQL)</span>
                    <div class="status-dot-wrap">
                        <span class="status-dot is-online"></span>
                        <span>Activa</span>
                    </div>
                </div>
                <div class="status-indicator-item">
                    <span>Conexión OpenAI</span>
                    <div class="status-dot-wrap">
                        <?php if (ai_is_ready()): ?>
                            <span class="status-dot is-online"></span>
                            <span style="color: var(--success);">Operativa</span>
                        <?php else: ?>
                            <span class="status-dot" style="background-color: var(--danger);"></span>
                            <span style="color: var(--danger);">Sin credencial</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="status-indicator-item">
                    <span>Versión de Sistema</span>
                    <div class="status-dot-wrap" style="color: var(--slate-500);">
                        <strong>v2.0-Premium</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Portal de Pacientes (ya operativo) -->
        <div class="admin-panel admin-next-panel">
            <span>Portal de Pacientes</span>
            <h2>🩺 Sistema operativo</h2>
            <p>Los pacientes ya pueden registrarse, verificar su correo y agendar citas con cualquiera de los <?= (int)$stats['total'] ?> médicos del hospital desde el portal público.</p>
            <a href="<?= e(base_url('portal/login.php')) ?>" target="_blank" rel="noopener" class="admin-secondary-action">
                <i data-lucide="external-link" style="width: 16px; height: 16px;"></i>
                Abrir portal del paciente
            </a>
        </div>

    </div>
</section>

<?php admin_footer(); ?>
