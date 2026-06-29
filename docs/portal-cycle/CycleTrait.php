<?php
/**
 * Mi Ciclo — endpoints del control menstrual del Portal del Paciente.
 *
 * Trait de PortalController (mismo patrón que ChatPatientTrait/StudyRequestsTrait).
 * Datos SOLO en medical_call_center (tablas portal_cycle_*). Nunca toca SGC.
 * Todo acotado por patient_id del JWT (aud=patient) → anti-IDOR.
 *
 * Rutas (en index.php $portalRoutes):
 *   GET    /portal/me/cycle              cycleGet
 *   PUT    /portal/me/cycle/settings     cyclePutSettings
 *   POST   /portal/me/cycle/period       cyclePostPeriod
 *   DELETE /portal/me/cycle/period/{id}  cycleDeletePeriod
 *   PUT    /portal/me/cycle/log          cyclePutLog
 */
trait CycleTrait
{
    public function cycleGet(): void
    {
        $pid = (int) $this->patient['patient_id'];

        $st = $this->db->prepare('SELECT avg_cycle_length, avg_period_length, goal, onboarded, reminders
                                    FROM portal_cycle_settings WHERE patient_id = ?');
        $st->execute([$pid]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        $settings = $s ? [
            'avg_cycle_length'  => (int) $s['avg_cycle_length'],
            'avg_period_length' => (int) $s['avg_period_length'],
            'goal'              => (string) $s['goal'],
            'onboarded'         => (bool) $s['onboarded'],
            'reminders'         => $s['reminders'] ? (json_decode($s['reminders'], true) ?: null) : null,
        ] : ['avg_cycle_length' => 28, 'avg_period_length' => 5, 'goal' => 'track', 'onboarded' => false, 'reminders' => null];

        $st = $this->db->prepare('SELECT id, start_date, end_date FROM portal_cycle_periods
                                    WHERE patient_id = ? ORDER BY start_date ASC');
        $st->execute([$pid]);
        $periods = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $periods[] = ['id' => (int) $r['id'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date']];
        }

        $st = $this->db->prepare('SELECT log_date, flow, symptoms, moods, intimacy, pain, temp, notes
                                    FROM portal_cycle_logs WHERE patient_id = ?');
        $st->execute([$pid]);
        $logs = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $logs[$r['log_date']] = [
                'flow'     => $r['flow'] ?: '',
                'symptoms' => $r['symptoms'] ? (json_decode($r['symptoms'], true) ?: []) : [],
                'moods'    => $r['moods'] ? (json_decode($r['moods'], true) ?: []) : [],
                'intimacy' => $r['intimacy'] ?: '',
                'pain'     => (int) $r['pain'],
                'temp'     => $r['temp'] !== null ? (float) $r['temp'] : '',
                'notes'    => $r['notes'] ?: '',
            ];
        }

        Response::success(['settings' => $settings, 'periods' => $periods, 'logs' => $logs]);
    }

    public function cyclePutSettings(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $cycle  = max(21, min(40, (int) ($in['avg_cycle_length']  ?? 28)));
        $period = max(2,  min(10, (int) ($in['avg_period_length'] ?? 5)));
        $goal   = in_array($in['goal'] ?? 'track', ['track', 'conceive', 'pregnant'], true) ? $in['goal'] : 'track';
        $onb    = !empty($in['onboarded']) ? 1 : 0;
        // reminders: solo se actualiza si viene en el request (COALESCE conserva lo previo)
        $reminders = (array_key_exists('reminders', $in) && is_array($in['reminders']))
            ? json_encode(['period' => !empty($in['reminders']['period'])])
            : null;

        // VALUES(col) en el UPDATE para no reusar placeholders nombrados
        // (la conexión tiene EMULATE_PREPARES=false). MariaDB 10.4 lo soporta.
        $this->db->prepare(
            'INSERT INTO portal_cycle_settings (patient_id, avg_cycle_length, avg_period_length, goal, onboarded, reminders)
             VALUES (:p, :c, :d, :g, :o, :r)
             ON DUPLICATE KEY UPDATE avg_cycle_length = VALUES(avg_cycle_length),
                                     avg_period_length = VALUES(avg_period_length),
                                     goal = VALUES(goal),
                                     onboarded = GREATEST(onboarded, VALUES(onboarded)),
                                     reminders = COALESCE(VALUES(reminders), reminders)'
        )->execute([':p' => $pid, ':c' => $cycle, ':d' => $period, ':g' => $goal, ':o' => $onb, ':r' => $reminders]);

        $this->logAudit('cycle_settings', 'Preferencias de Mi Ciclo actualizadas.');
        Response::success(['avg_cycle_length' => $cycle, 'avg_period_length' => $period, 'goal' => $goal]);
    }

    public function cyclePostPeriod(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $start = $this->cycleValidDate($in['start_date'] ?? null);
        $end   = (isset($in['end_date']) && $in['end_date']) ? $this->cycleValidDate($in['end_date']) : null;
        if (!$start) Response::error('Fecha inválida.', 422);
        if ($start > date('Y-m-d')) Response::error('La fecha no puede ser futura.', 422);
        if ($end && $end < $start) Response::error('El fin no puede ser anterior al inicio.', 422);

        $this->db->prepare(
            'INSERT INTO portal_cycle_periods (patient_id, start_date, end_date)
             VALUES (:p, :s, :e) ON DUPLICATE KEY UPDATE end_date = VALUES(end_date)'
        )->execute([':p' => $pid, ':s' => $start, ':e' => $end]);

        $id = (int) $this->db->lastInsertId();
        if (!$id) {
            $q = $this->db->prepare('SELECT id FROM portal_cycle_periods WHERE patient_id = ? AND start_date = ?');
            $q->execute([$pid, $start]);
            $id = (int) $q->fetchColumn();
        }
        Response::success(['id' => $id, 'start_date' => $start, 'end_date' => $end]);
    }

    public function cycleDeletePeriod(string $id): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('DELETE FROM portal_cycle_periods WHERE id = ? AND patient_id = ?');
        $st->execute([(int) $id, $pid]);
        Response::success(['deleted' => $st->rowCount() > 0]);
    }

    public function cyclePutLog(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $date = $this->cycleValidDate($in['date'] ?? null);
        if (!$date) Response::error('Fecha inválida.', 422);

        if (!empty($in['clear'])) {
            $this->db->prepare('DELETE FROM portal_cycle_logs WHERE patient_id = ? AND log_date = ?')
                     ->execute([$pid, $date]);
            Response::success(['cleared' => true]);
            return;
        }

        $flow  = in_array($in['flow'] ?? '', ['none', 'light', 'medium', 'heavy'], true) ? $in['flow'] : null;
        $sym   = $this->cycleCleanList($in['symptoms'] ?? [], 20);
        $moods = $this->cycleCleanList($in['moods'] ?? [], 12);
        $intim = in_array($in['intimacy'] ?? '', ['none', 'protected', 'unprotected'], true) ? $in['intimacy'] : null;
        $pain  = max(0, min(3, (int) ($in['pain'] ?? 0)));
        $temp  = (isset($in['temp']) && $in['temp'] !== '' && is_numeric($in['temp']))
                 ? max(34, min(42, (float) $in['temp'])) : null;
        $notes = isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 500) : null;

        $this->db->prepare(
            'INSERT INTO portal_cycle_logs (patient_id, log_date, flow, symptoms, moods, intimacy, pain, temp, notes)
             VALUES (:p, :d, :f, :s, :m, :i, :pa, :t, :n)
             ON DUPLICATE KEY UPDATE flow = VALUES(flow), symptoms = VALUES(symptoms), moods = VALUES(moods),
                                     intimacy = VALUES(intimacy), pain = VALUES(pain), temp = VALUES(temp), notes = VALUES(notes)'
        )->execute([
            ':p' => $pid, ':d' => $date, ':f' => $flow,
            ':s' => $sym ? json_encode($sym) : null,
            ':m' => $moods ? json_encode($moods) : null,
            ':i' => $intim, ':pa' => $pain, ':t' => $temp, ':n' => $notes,
        ]);
        Response::success(['saved' => true]);
    }

    /** PDF profesional del resumen del ciclo (Dompdf, marca HGLC) — para la consulta. */
    public function cycleSummaryPdf(): void
    {
        $pid = (int) $this->patient['patient_id'];
        require_once __DIR__ . '/../../helpers/CycleSummaryPdf.php';
        try {
            $out = CycleSummaryPdf::render($this->db, $pid);
        } catch (\Throwable $e) {
            Response::error('No se pudo generar el documento.', 422);
        }
        $this->logAudit('cycle_pdf', 'Resumen de Mi Ciclo descargado por la paciente.', $pid);
        $inline = (($_GET['disposition'] ?? 'inline') === 'attachment') ? 'attachment' : 'inline';
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $inline . '; filename="' . $out['filename'] . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Length: ' . strlen($out['pdf']));
        echo $out['pdf'];
        exit;
    }

    private function cycleValidDate(?string $s): ?string
    {
        if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $y) ? $s : null;
    }

    private function cycleCleanList($v, int $max): array
    {
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $item) {
            if (is_string($item) && preg_match('/^[a-z_]{2,24}$/', $item)) $out[] = $item;
            if (count($out) >= $max) break;
        }
        return array_values(array_unique($out));
    }
}
