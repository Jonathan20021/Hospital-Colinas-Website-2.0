<?php
/**
 * Diario de Síntomas — endpoints del Portal del Paciente (trait de PortalController).
 * Datos en medical_call_center (portal_symptom_entries). Acotado por patient_id del JWT.
 *
 * Rutas:
 *   GET    /portal/me/symptoms              symptomsGet    (cronología)
 *   POST   /portal/me/symptoms              symptomsStore  (nueva entrada)
 *   DELETE /portal/me/symptoms/{id}         symptomsDelete
 *   GET    /portal/me/symptoms/summary.pdf  symptomsPdf    (resumen para el médico)
 */
trait SymptomsTrait
{
    public function symptomsGet(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('SELECT id, recorded_at, symptoms, severity, feeling, note
                                    FROM portal_symptom_entries WHERE patient_id = ?
                                    ORDER BY recorded_at DESC LIMIT 200');
        $st->execute([$pid]);
        $entries = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entries[] = [
                'id'          => (int) $r['id'],
                'recorded_at' => $r['recorded_at'],
                'symptoms'    => $r['symptoms'] ? (json_decode($r['symptoms'], true) ?: []) : [],
                'severity'    => (int) $r['severity'],
                'feeling'     => $r['feeling'] ?: '',
                'note'        => $r['note'] ?: '',
            ];
        }
        Response::success(['entries' => $entries]);
    }

    public function symptomsStore(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();

        $when = trim((string) ($in['recorded_at'] ?? ''));
        if ($when !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $when)) {
            $recordedAt = strlen($when) === 10 ? $when . ' ' . date('H:i') : $when;
        } else {
            $recordedAt = date('Y-m-d H:i');
        }
        if (strtotime($recordedAt) > time() + 60) Response::error('La fecha no puede ser futura.', 422);

        $symptoms = $this->symCleanList($in['symptoms'] ?? [], 25);
        $severity = max(1, min(3, (int) ($in['severity'] ?? 1)));
        $feeling  = in_array($in['feeling'] ?? '', ['good', 'regular', 'bad'], true) ? $in['feeling'] : null;
        $note     = isset($in['note']) ? mb_substr(trim((string) $in['note']), 0, 500) : null;

        if (!$symptoms && !$note && !$feeling) Response::error('Registra al menos un síntoma o una nota.', 422);

        $st = $this->db->prepare('INSERT INTO portal_symptom_entries (patient_id, recorded_at, symptoms, severity, feeling, note)
                                    VALUES (?,?,?,?,?,?)');
        $st->execute([$pid, $recordedAt, $symptoms ? json_encode($symptoms) : null, $severity, $feeling, $note]);

        $this->logAudit('symptoms_store', 'Registró una entrada en el diario de síntomas.', $pid);
        Response::success(['id' => (int) $this->db->lastInsertId()]);
    }

    public function symptomsDelete(string $id): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('DELETE FROM portal_symptom_entries WHERE id = ? AND patient_id = ?');
        $st->execute([(int) $id, $pid]);
        Response::success(['deleted' => $st->rowCount() > 0]);
    }

    public function symptomsPdf(): void
    {
        $pid = (int) $this->patient['patient_id'];
        require_once __DIR__ . '/../../helpers/SymptomsPdf.php';
        try {
            $out = SymptomsPdf::render($this->db, $pid);
        } catch (\Throwable $e) {
            Response::error('No se pudo generar el documento.', 422);
        }
        $this->logAudit('symptoms_pdf', 'Diario de síntomas descargado por la paciente.', $pid);
        $inline = (($_GET['disposition'] ?? 'inline') === 'attachment') ? 'attachment' : 'inline';
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $inline . '; filename="' . $out['filename'] . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Length: ' . strlen($out['pdf']));
        echo $out['pdf'];
        exit;
    }

    private function symCleanList($v, int $max): array
    {
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $item) {
            if (is_string($item) && preg_match('/^[a-z_]{2,28}$/', $item)) $out[] = $item;
            if (count($out) >= $max) break;
        }
        return array_values(array_unique($out));
    }
}
