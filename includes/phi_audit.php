<?php
/**
 * Bitácora de auditoría de PHI (acceso a datos de pacientes vía los portales).
 *
 * Registra el HECHO del acceso (quién accedió, a qué paciente, cuándo y desde
 * dónde), NUNCA el contenido clínico. Se invoca desde los proxies de mismo
 * origen (doctor-proxy / portal-proxy), por donde pasa todo el acceso a PHI del
 * portal médico y del portal del paciente.
 *
 * Diseño: best-effort. Si la BD de auditoría falla, jamás bloquea ni rompe la
 * respuesta del proxy. Vive en la BD del sitio público (no toca JENOFONTE).
 */

require_once __DIR__ . '/db.php';

function phi_audit_ensure_table(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    $pdo = db();
    if (!$pdo) return $ok = false;
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS phi_audit_log ('
            . ' id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
            . ' created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' actor_type VARCHAR(12) NOT NULL,'
            . ' actor_id INT NULL,'
            . ' actor_label VARCHAR(160) NULL,'
            . ' method VARCHAR(8) NOT NULL,'
            . ' path VARCHAR(255) NOT NULL,'
            . ' target_patient_id INT NULL,'
            . ' status SMALLINT NULL,'
            . ' ip VARCHAR(45) NULL,'
            . ' user_agent VARCHAR(255) NULL,'
            . ' INDEX idx_created (created_at),'
            . ' INDEX idx_actor (actor_type, actor_id),'
            . ' INDEX idx_target (target_patient_id)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        return $ok = true;
    } catch (Throwable) {
        return $ok = false;
    }
}

/** Nombre legible del actor a partir de los campos disponibles en sesión. */
function phi_audit_actor_label(?array $actor): ?string
{
    if (!$actor) return null;
    foreach (['name', 'full_name', 'display_name'] as $k) {
        if (!empty($actor[$k])) return (string) $actor[$k];
    }
    $compound = trim((string) ($actor['first_name'] ?? '') . ' ' . (string) ($actor['last_name'] ?? ''));
    if ($compound !== '') return $compound;
    return !empty($actor['email']) ? (string) $actor['email'] : null;
}

function phi_audit_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return substr($ip, 0, 45);
}

/**
 * Registra un acceso a PHI. $target fuerza el id de paciente (p.ej. el propio
 * paciente accediendo a sus datos); si es null se intenta extraer de la ruta
 * (/patients/{id}). Nunca lanza.
 */
function phi_audit_record(string $actorType, ?int $actorId, ?string $actorLabel, string $method, string $path, ?int $status, ?int $target = null): void
{
    try {
        if (!phi_audit_ensure_table()) return;
        $pdo = db();
        if (!$pdo) return;
        if ($target === null && preg_match('#/patients/(\d+)#', $path, $m)) {
            $target = (int) $m[1];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO phi_audit_log (actor_type, actor_id, actor_label, method, path, target_patient_id, status, ip, user_agent)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            substr($actorType, 0, 12),
            $actorId,
            $actorLabel !== null ? substr($actorLabel, 0, 160) : null,
            substr($method, 0, 8),
            substr($path, 0, 255),
            $target,
            $status,
            phi_audit_client_ip(),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable) {
        // best-effort: la auditoría nunca debe afectar la respuesta.
    }
}
