<?php
/**
 * Recordatorios de Prevención — endpoints del Portal del Paciente (trait de PortalController).
 * Datos en medical_call_center (portal_screenings). Acotado por patient_id del JWT.
 * El catálogo y el cálculo de vencimientos viven en el frontend.
 *
 * Rutas:
 *   GET    /portal/me/screenings          screeningsGet   ({ key: done_date })
 *   POST   /portal/me/screenings          screeningsStore ({ key, date })
 *   DELETE /portal/me/screenings/{key}    screeningsDelete
 */
trait ScreeningsTrait
{
    public function screeningsGet(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('SELECT screening_key, done_date FROM portal_screenings WHERE patient_id = ?');
        $st->execute([$pid]);
        $records = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $records[$r['screening_key']] = $r['done_date'];
        }
        Response::success(['records' => $records]);
    }

    public function screeningsStore(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $key  = trim((string) ($in['key'] ?? ''));
        $date = trim((string) ($in['date'] ?? ''));
        if (!preg_match('/^[a-z_]{2,40}$/', $key)) Response::error('Tamizaje inválido.', 422);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$this->screeningValidDate($date)) Response::error('Fecha inválida.', 422);
        if ($date > date('Y-m-d')) Response::error('La fecha no puede ser futura.', 422);

        $this->db->prepare(
            'INSERT INTO portal_screenings (patient_id, screening_key, done_date)
             VALUES (:p, :k, :d)
             ON DUPLICATE KEY UPDATE done_date = VALUES(done_date)'
        )->execute([':p' => $pid, ':k' => $key, ':d' => $date]);

        $this->logAudit('screening_store', 'Marcó un tamizaje como realizado: ' . $key, $pid);
        Response::success(['key' => $key, 'date' => $date]);
    }

    public function screeningsDelete(string $key): void
    {
        $pid = (int) $this->patient['patient_id'];
        if (!preg_match('/^[a-z_]{2,40}$/', $key)) Response::error('Tamizaje inválido.', 422);
        $st = $this->db->prepare('DELETE FROM portal_screenings WHERE patient_id = ? AND screening_key = ?');
        $st->execute([$pid, $key]);
        Response::success(['deleted' => $st->rowCount() > 0]);
    }

    private function screeningValidDate(string $s): bool
    {
        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $y);
    }
}
