<?php
/**
 * Mis Medicamentos — endpoints del Portal del Paciente (trait de PortalController).
 * Datos en medical_call_center (portal_medications + portal_med_intakes).
 * Acotado por patient_id del JWT.
 *
 * Rutas:
 *   GET    /portal/me/medications              medsGet     (lista + checklist de hoy)
 *   POST   /portal/me/medications              medStore
 *   PUT    /portal/me/medications/{id}         medUpdate
 *   DELETE /portal/me/medications/{id}         medDelete
 *   POST   /portal/me/medications/intake       medIntake   (marcar/desmarcar toma)
 */
trait MedsTrait
{
    public function medsGet(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $today = date('Y-m-d');

        $st = $this->db->prepare('SELECT id, name, dose, times, note, active FROM portal_medications
                                    WHERE patient_id = ? ORDER BY active DESC, name ASC');
        $st->execute([$pid]);
        $meds = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $meds[] = [
                'id'     => (int) $r['id'],
                'name'   => $r['name'],
                'dose'   => $r['dose'] ?: '',
                'times'  => $r['times'] ? (json_decode($r['times'], true) ?: []) : [],
                'note'   => $r['note'] ?: '',
                'active' => (bool) $r['active'],
            ];
        }

        // tomas ya marcadas hoy
        $st = $this->db->prepare('SELECT medication_id, intake_time FROM portal_med_intakes
                                    WHERE patient_id = ? AND intake_date = ?');
        $st->execute([$pid, $today]);
        $taken = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $taken[$r['medication_id'] . '|' . $r['intake_time']] = true;
        }

        // checklist de hoy: cada med activo × cada horario
        $todaySchedule = [];
        foreach ($meds as $m) {
            if (!$m['active']) continue;
            foreach ($m['times'] as $t) {
                $todaySchedule[] = [
                    'medication_id' => $m['id'], 'name' => $m['name'], 'dose' => $m['dose'],
                    'time' => $t, 'taken' => isset($taken[$m['id'] . '|' . $t]),
                ];
            }
        }
        usort($todaySchedule, fn($a, $b) => strcmp($a['time'], $b['time']));

        Response::success(['medications' => $meds, 'today' => $todaySchedule, 'date' => $today]);
    }

    public function medStore(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        [$name, $dose, $times, $note] = $this->medValidate($in);
        if ($name === null) Response::error('El nombre del medicamento es obligatorio.', 422);

        $st = $this->db->prepare('INSERT INTO portal_medications (patient_id, name, dose, times, note, active)
                                    VALUES (?,?,?,?,?,1)');
        $st->execute([$pid, $name, $dose, $times ? json_encode($times) : null, $note]);
        $this->logAudit('med_store', 'Agregó un medicamento.', $pid);
        Response::success(['id' => (int) $this->db->lastInsertId()]);
    }

    public function medUpdate(string $id): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();

        // pertenencia
        $own = $this->db->prepare('SELECT id FROM portal_medications WHERE id = ? AND patient_id = ?');
        $own->execute([(int) $id, $pid]);
        if (!$own->fetchColumn()) Response::notFound('Medicamento no encontrado.');

        // toggle de activo
        if (array_key_exists('active', $in) && count($in) === 1) {
            $this->db->prepare('UPDATE portal_medications SET active = ? WHERE id = ? AND patient_id = ?')
                     ->execute([!empty($in['active']) ? 1 : 0, (int) $id, $pid]);
            Response::success(['updated' => true]);
        }

        [$name, $dose, $times, $note] = $this->medValidate($in);
        if ($name === null) Response::error('El nombre del medicamento es obligatorio.', 422);
        $active = !empty($in['active']) ? 1 : (array_key_exists('active', $in) ? 0 : 1);

        $this->db->prepare('UPDATE portal_medications SET name = ?, dose = ?, times = ?, note = ?, active = ?
                              WHERE id = ? AND patient_id = ?')
                 ->execute([$name, $dose, $times ? json_encode($times) : null, $note, $active, (int) $id, $pid]);
        Response::success(['updated' => true]);
    }

    public function medDelete(string $id): void
    {
        $pid = (int) $this->patient['patient_id'];
        $this->db->prepare('DELETE FROM portal_medications WHERE id = ? AND patient_id = ?')->execute([(int) $id, $pid]);
        $this->db->prepare('DELETE FROM portal_med_intakes WHERE medication_id = ? AND patient_id = ?')->execute([(int) $id, $pid]);
        Response::success(['deleted' => true]);
    }

    public function medIntake(): void
    {
        $pid = (int) $this->patient['patient_id'];
        $in  = $this->body();
        $mid  = (int) ($in['medication_id'] ?? 0);
        $date = (string) ($in['date'] ?? date('Y-m-d'));
        $time = (string) ($in['time'] ?? '');
        $taken = !empty($in['taken']);
        if (!$mid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            Response::error('Datos incompletos.', 422);
        }
        // pertenencia
        $own = $this->db->prepare('SELECT id FROM portal_medications WHERE id = ? AND patient_id = ?');
        $own->execute([$mid, $pid]);
        if (!$own->fetchColumn()) Response::notFound('Medicamento no encontrado.');

        if ($taken) {
            $this->db->prepare('INSERT IGNORE INTO portal_med_intakes (patient_id, medication_id, intake_date, intake_time)
                                  VALUES (?,?,?,?)')->execute([$pid, $mid, $date, $time]);
        } else {
            $this->db->prepare('DELETE FROM portal_med_intakes WHERE patient_id = ? AND medication_id = ? AND intake_date = ? AND intake_time = ?')
                     ->execute([$pid, $mid, $date, $time]);
        }
        Response::success(['taken' => $taken]);
    }

    /** @return array{0:?string,1:?string,2:array,3:?string} [name,dose,times,note] */
    private function medValidate(array $in): array
    {
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 120);
        if ($name === '') return [null, null, [], null];
        $dose = isset($in['dose']) ? mb_substr(trim((string) $in['dose']), 0, 60) : null;
        $note = isset($in['note']) ? mb_substr(trim((string) $in['note']), 0, 255) : null;
        $times = [];
        foreach ((array) ($in['times'] ?? []) as $t) {
            if (is_string($t) && preg_match('/^\d{2}:\d{2}$/', $t)) $times[] = $t;
            if (count($times) >= 12) break;
        }
        $times = array_values(array_unique($times));
        sort($times);
        return [$name, $dose ?: null, $times, $note ?: null];
    }
}
