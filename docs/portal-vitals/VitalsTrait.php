<?php
/**
 * Mis Signos Vitales — endpoints del Portal del Paciente (trait de PortalController).
 *
 * Combina los vitales registrados por el HOSPITAL (`vital_signs`, solo lectura)
 * con las mediciones CASERAS del paciente (`portal_vitals`). Todo acotado por
 * patient_id del JWT. Escribe SOLO en medical_call_center; nunca toca SGC.
 *
 * Rutas:
 *   GET    /portal/me/vitals        vitalsGet     (series por tipo + último valor)
 *   POST   /portal/me/vitals        vitalsStore   (registrar medición casera)
 *   DELETE /portal/me/vitals/{id}   vitalsDelete  (borrar una medición propia)
 */
trait VitalsTrait
{
    public function vitalsGet(): void
    {
        $pid = (int) $this->patient['patient_id'];

        $series = ['bp' => [], 'weight' => [], 'heart_rate' => [], 'temperature' => [], 'spo2' => [], 'glucose' => []];
        $height = null;

        // ── Hospital (vital_signs) ──
        $st = $this->db->prepare('SELECT recorded_at, systolic, diastolic, heart_rate, temperature, weight_kg, height_cm, spo2
                                    FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at ASC');
        $st->execute([$pid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            self::vitalsPush($series, $height, $r, 'hospital', null);
        }

        // ── Paciente (portal_vitals) ──
        $st = $this->db->prepare('SELECT id, recorded_at, systolic, diastolic, heart_rate, temperature, weight_kg, height_cm, spo2, glucose
                                    FROM portal_vitals WHERE patient_id = ? ORDER BY recorded_at ASC');
        $st->execute([$pid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            self::vitalsPush($series, $height, $r, 'self', (int) $r['id']);
        }

        // ordenar cada serie por fecha y quedarnos con un histórico razonable
        foreach ($series as $k => &$pts) {
            usort($pts, fn($a, $b) => strcmp($a['t'], $b['t']));
            if (count($pts) > 60) $pts = array_slice($pts, -60);
        }
        unset($pts);

        $latest = [];
        foreach ($series as $k => $pts) {
            $latest[$k] = $pts ? $pts[count($pts) - 1] : null;
        }

        Response::success(['series' => $series, 'latest' => $latest, 'height_cm' => $height]);
    }

    private static function vitalsPush(array &$series, &$height, array $r, string $src, ?int $id): void
    {
        $t = substr((string) $r['recorded_at'], 0, 16);
        $base = ['t' => $t, 'src' => $src];
        if ($id !== null) $base['id'] = $id;

        if (!empty($r['height_cm'])) $height = (float) $r['height_cm'];

        if (!empty($r['systolic']) && !empty($r['diastolic'])) {
            $series['bp'][] = $base + ['sys' => (int) $r['systolic'], 'dia' => (int) $r['diastolic']];
        }
        if (!empty($r['weight_kg']))   $series['weight'][]      = $base + ['v' => (float) $r['weight_kg']];
        if (!empty($r['heart_rate']))  $series['heart_rate'][]  = $base + ['v' => (int) $r['heart_rate']];
        if (!empty($r['temperature'])) $series['temperature'][] = $base + ['v' => (float) $r['temperature']];
        if (!empty($r['spo2']))        $series['spo2'][]        = $base + ['v' => (int) $r['spo2']];
        if (!empty($r['glucose'] ?? null)) $series['glucose'][] = $base + ['v' => (int) $r['glucose']];
    }

    public function vitalsStore(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();

        // fecha/hora del registro (default ahora). Acepta 'YYYY-MM-DD HH:MM' o 'YYYY-MM-DD'.
        $when = trim((string) ($in['recorded_at'] ?? ''));
        if ($when !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $when)) {
            $recordedAt = strlen($when) === 10 ? $when . ' ' . date('H:i') : $when;
        } else {
            $recordedAt = date('Y-m-d H:i');
        }
        if (strtotime($recordedAt) > time() + 60) Response::error('La fecha no puede ser futura.', 422);

        $sys  = self::vInt($in['systolic']    ?? null, 60, 260);
        $dia  = self::vInt($in['diastolic']   ?? null, 30, 200);
        $hr   = self::vInt($in['heart_rate']  ?? null, 30, 240);
        $spo2 = self::vInt($in['spo2']        ?? null, 50, 100);
        $glu  = self::vInt($in['glucose']     ?? null, 20, 600);
        $wt   = self::vFloat($in['weight_kg'] ?? null, 2, 400);
        $ht   = self::vFloat($in['height_cm'] ?? null, 30, 260);
        $temp = self::vFloat($in['temperature'] ?? null, 30, 45);
        $note = isset($in['note']) ? mb_substr(trim((string) $in['note']), 0, 255) : null;

        // presión: ambos o ninguno
        if (($sys === null) !== ($dia === null)) Response::error('La presión necesita los dos valores (sistólica y diastólica).', 422);

        if ($sys === null && $hr === null && $spo2 === null && $glu === null && $wt === null && $ht === null && $temp === null) {
            Response::error('Ingresa al menos una medición.', 422);
        }

        $st = $this->db->prepare(
            'INSERT INTO portal_vitals (patient_id, recorded_at, systolic, diastolic, heart_rate, temperature, weight_kg, height_cm, spo2, glucose, note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$pid, $recordedAt, $sys, $dia, $hr, $temp, $wt, $ht, $spo2, $glu, $note]);

        $this->logAudit('vitals_store', 'Registró una medición de signos vitales.', $pid);
        Response::success(['id' => (int) $this->db->lastInsertId()]);
    }

    public function vitalsDelete(string $id): void
    {
        $pid = (int) $this->patient['patient_id'];
        $st = $this->db->prepare('DELETE FROM portal_vitals WHERE id = ? AND patient_id = ?');
        $st->execute([(int) $id, $pid]);
        Response::success(['deleted' => $st->rowCount() > 0]);
    }

    private static function vInt($v, int $lo, int $hi): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        $v = (int) $v;
        return ($v >= $lo && $v <= $hi) ? $v : null;
    }

    private static function vFloat($v, float $lo, float $hi): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        $v = round((float) $v, 2);
        return ($v >= $lo && $v <= $hi) ? $v : null;
    }
}
