<?php
/**
 * App de iOS — registro de tokens de APNs del Portal del Paciente (trait de PortalController).
 *
 * Mismo patrón que CycleTrait/VitalsTrait. La app no habla con la API interna:
 * manda el token por el proxy del sitio (/api/portal-proxy.php), que ya permite
 * el prefijo /portal/me, valida la sesión y el CSRF y lo audita como PHI.
 * Todo acotado por patient_id del JWT (aud=patient) → anti-IDOR.
 * Solo medical_call_center (tabla portal_push_apns, ver schema.sql).
 *
 * Rutas (en index.php $portalRoutes):
 *   POST /portal/me/push/apns/subscribe     apnsSubscribe
 *   POST /portal/me/push/apns/unsubscribe   apnsUnsubscribe
 *
 * Compatible con PHP 7.4+.
 */
trait ApnsTrait
{
    public function apnsSubscribe(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();

        $token = $this->apnsToken($in['token'] ?? null);
        if ($token === null) Response::error('Token inválido.', 422);

        $bundle = (string) ($in['bundle_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9.-]{3,120}$/', $bundle)) Response::error('Aplicación inválida.', 422);

        $entorno = ($in['entorno'] ?? '') === 'sandbox' ? 'sandbox' : 'production';
        $corto = function ($v, int $max) {
            $v = trim((string) $v);
            return $v === '' ? null : mb_substr($v, 0, $max);
        };

        // Un token es de un DISPOSITIVO: si otro paciente inicia sesión en ese
        // iPhone, el token pasa a ser suyo (UNIQUE en token). Así un teléfono
        // compartido nunca recibe avisos de dos pacientes.
        // VALUES(col) y sin repetir placeholders (EMULATE_PREPARES=false).
        $this->db->prepare(
            'INSERT INTO portal_push_apns (patient_id, token, environment, bundle_id, app_version, os_version, device)
             VALUES (:p, :t, :e, :b, :av, :ov, :d)
             ON DUPLICATE KEY UPDATE patient_id = VALUES(patient_id), environment = VALUES(environment),
                                     bundle_id = VALUES(bundle_id), app_version = VALUES(app_version),
                                     os_version = VALUES(os_version), device = VALUES(device),
                                     last_error = NULL, updated_at = CURRENT_TIMESTAMP'
        )->execute([
            ':p'  => $pid,
            ':t'  => $token,
            ':e'  => $entorno,
            ':b'  => $bundle,
            ':av' => $corto($in['app_version'] ?? '', 20),
            ':ov' => $corto($in['os_version'] ?? '', 20),
            ':d'  => $corto($in['dispositivo'] ?? '', 40),
        ]);

        // Tope de 10 dispositivos por paciente: se descartan los menos recientes.
        $st = $this->db->prepare('SELECT id FROM portal_push_apns WHERE patient_id = ?
                                   ORDER BY updated_at DESC, id DESC LIMIT 100 OFFSET 10');
        $st->execute([$pid]);
        $sobran = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($sobran) {
            $marcas = implode(',', array_fill(0, count($sobran), '?'));
            $this->db->prepare("DELETE FROM portal_push_apns WHERE patient_id = ? AND id IN ($marcas)")
                     ->execute(array_merge([$pid], array_map('intval', $sobran)));
        }

        $this->logAudit('push_apns', 'Avisos de la app de iOS activados en un dispositivo.');
        Response::success(['subscribed' => true]);
    }

    public function apnsUnsubscribe(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $token = $this->apnsToken($this->body()['token'] ?? null);
        if ($token === null) Response::error('Token inválido.', 422);

        // Solo el dueño puede darlo de baja.
        $st = $this->db->prepare('DELETE FROM portal_push_apns WHERE token = ? AND patient_id = ?');
        $st->execute([$token, $pid]);
        Response::success(['removed' => $st->rowCount() > 0]);
    }

    /** Token de APNs en hexadecimal (hoy 64 caracteres; Apple dice que puede crecer). */
    private function apnsToken($valor): ?string
    {
        $token = strtolower(trim((string) $valor));
        return preg_match('/^[0-9a-f]{64,200}$/', $token) ? $token : null;
    }
}
