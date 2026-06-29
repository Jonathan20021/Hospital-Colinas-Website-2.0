<?php
/**
 * Embarazo semana a semana — endpoints del Portal del Paciente (trait de PortalController).
 * Datos en medical_call_center (portal_pregnancy). Acotado por patient_id del JWT.
 *
 * Rutas:
 *   GET /portal/me/pregnancy   pregnancyGet    ({ lmp_date, active })
 *   PUT /portal/me/pregnancy   pregnancyStore  (set FUM / activar / finalizar)
 */
trait PregnancyTrait
{
    public function pregnancyGet(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('SELECT lmp_date, active FROM portal_pregnancy WHERE patient_id = ?');
        $st->execute([$pid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        Response::success([
            'lmp_date' => $r['lmp_date'] ?? null,
            'active'   => $r ? (bool) $r['active'] : false,
        ]);
    }

    public function pregnancyStore(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $active = !empty($in['active']) ? 1 : 0;

        $lmp = trim((string) ($in['lmp_date'] ?? ''));
        if ($lmp !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lmp)) Response::error('Fecha inválida.', 422);
            [$y, $m, $d] = array_map('intval', explode('-', $lmp));
            if (!checkdate($m, $d, $y)) Response::error('Fecha inválida.', 422);
            $ts = strtotime($lmp);
            if ($ts > time()) Response::error('La fecha no puede ser futura.', 422);
            // un embarazo dura ~40 semanas; no aceptamos FUM de hace más de 45 semanas
            if ($ts < time() - 45 * 7 * 86400) Response::error('La fecha es demasiado antigua para un embarazo en curso.', 422);
        } else {
            $lmp = null;
        }

        $this->db->prepare(
            'INSERT INTO portal_pregnancy (patient_id, lmp_date, active)
             VALUES (:p, :l, :a)
             ON DUPLICATE KEY UPDATE lmp_date = VALUES(lmp_date), active = VALUES(active)'
        )->execute([':p' => $pid, ':l' => $lmp, ':a' => $active]);

        $this->logAudit('pregnancy_store', 'Actualizó el seguimiento de embarazo.', $pid);
        Response::success(['lmp_date' => $lmp, 'active' => (bool) $active]);
    }
}
