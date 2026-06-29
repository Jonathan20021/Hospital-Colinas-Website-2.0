<?php
/**
 * Generador del PDF "Mi Ciclo — Resumen para la consulta".
 * Mismo stack que el resto de documentos del portal: Dompdf (vendor/) + marca HGLC.
 * Recalcula las predicciones server-side desde portal_cycle_* (medical_call_center).
 * Se ubica en /opt/lampp/htdocs/api/helpers/ (logo en hglc_logo.png, igual que PrescriptionPdf).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class CycleSummaryPdf
{
    private const MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    private const SYMPTOMS = [
        'cramps' => 'Cólicos', 'headache' => 'Dolor de cabeza', 'bloating' => 'Hinchazón',
        'tender_breasts' => 'Senos sensibles', 'acne' => 'Acné', 'fatigue' => 'Fatiga',
        'cravings' => 'Antojos', 'nausea' => 'Náuseas', 'backache' => 'Dolor de espalda',
        'insomnia' => 'Insomnio', 'discharge' => 'Flujo vaginal', 'dizziness' => 'Mareos',
    ];

    private const GOALS = ['track' => 'Seguir el ciclo', 'conceive' => 'Buscar embarazo', 'pregnant' => 'Embarazo'];

    public static function render(PDO $db, int $patientId): array
    {
        // ── datos ──────────────────────────────────────────────────────────
        $st = $db->prepare('SELECT name FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $name = trim((string) ($st->fetchColumn() ?: 'Paciente'));
        $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        $st = $db->prepare('SELECT avg_cycle_length, avg_period_length, goal FROM portal_cycle_settings WHERE patient_id = ?');
        $st->execute([$patientId]);
        $settings = $st->fetch(PDO::FETCH_ASSOC) ?: ['avg_cycle_length' => 28, 'avg_period_length' => 5, 'goal' => 'track'];

        $st = $db->prepare('SELECT start_date, end_date FROM portal_cycle_periods WHERE patient_id = ? ORDER BY start_date ASC');
        $st->execute([$patientId]);
        $periods = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $db->prepare('SELECT symptoms FROM portal_cycle_logs WHERE patient_id = ?');
        $st->execute([$patientId]);
        $logSyms = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'symptoms');

        $pred = self::predict($settings, $periods);
        $sym  = self::topSymptoms($logSyms);

        $clinic = self::clinicSettings($db);
        $html   = self::buildHtml($name, $settings, $periods, $pred, $sym, $clinic);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();

        return [
            'pdf'      => $dompdf->output(),
            'filename' => 'mi_ciclo_resumen_' . date('Ymd') . '.pdf',
            'title'    => 'Mi Ciclo — Resumen',
        ];
    }

    /** Predicción server-side (espejo de la lógica del frontend). */
    private static function predict(array $settings, array $periods): array
    {
        $setCycle  = self::clampInt($settings['avg_cycle_length'] ?? 28, 21, 40, 28);
        $setPeriod = self::clampInt($settings['avg_period_length'] ?? 5, 2, 10, 5);

        $lengths = [];
        for ($i = 1; $i < count($periods); $i++) {
            $dd = (int) round((strtotime($periods[$i]['start_date']) - strtotime($periods[$i - 1]['start_date'])) / 86400);
            if ($dd >= 18 && $dd <= 60) $lengths[] = $dd;
        }
        $recent   = array_slice($lengths, -6);
        $avgCycle = $recent ? (int) round(array_sum($recent) / count($recent)) : $setCycle;

        $durs = [];
        foreach ($periods as $p) {
            if (!empty($p['end_date'])) {
                $d = (int) round((strtotime($p['end_date']) - strtotime($p['start_date'])) / 86400) + 1;
                if ($d >= 1 && $d <= 12) $durs[] = $d;
            }
        }
        $durs      = array_slice($durs, -6);
        $avgPeriod = $durs ? (int) round(array_sum($durs) / count($durs)) : $setPeriod;

        $regularity = null; $spread = 0;
        if (count($recent) >= 2) {
            $spread = max($recent) - min($recent);
            $regularity = $spread <= 3 ? 'Regular' : ($spread <= 7 ? 'Algo irregular' : 'Irregular');
        }

        $lastStart = $periods ? $periods[count($periods) - 1]['start_date'] : null;
        $nextStart = null; $pregWeeks = null; $pregDays = null; $dueDate = null;
        if ($lastStart) {
            $today = strtotime(date('Y-m-d'));
            $ts = strtotime($lastStart);
            while ($ts < $today) { $ts += $avgCycle * 86400; }
            // si retrocedimos de más, vuelve un ciclo (próximo >= hoy)
            $nextStart = date('Y-m-d', $ts);

            if (($settings['goal'] ?? '') === 'pregnant') {
                $gd = (int) round(($today - strtotime($lastStart)) / 86400);
                $pregWeeks = intdiv($gd, 7);
                $pregDays  = $gd % 7;
                $dueDate   = date('Y-m-d', strtotime($lastStart) + 280 * 86400);
            }
        }

        return compact('avgCycle', 'avgPeriod', 'recent', 'regularity', 'spread', 'lastStart', 'nextStart', 'pregWeeks', 'pregDays', 'dueDate');
    }

    private static function topSymptoms(array $logSyms): array
    {
        $counts = [];
        foreach ($logSyms as $j) {
            $arr = $j ? json_decode($j, true) : [];
            if (!is_array($arr)) continue;
            foreach ($arr as $s) { $counts[$s] = ($counts[$s] ?? 0) + 1; }
        }
        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, 6, true) as $id => $n) {
            $out[] = (self::SYMPTOMS[$id] ?? $id) . ' (' . $n . ')';
        }
        return $out;
    }

    private static function clinicSettings(PDO $db): array
    {
        $settings = [];
        try {
            foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) { /* defaults */ }
        return array_merge([
            'clinic_name'    => 'Hospital General Las Colinas',
            'clinic_address' => 'Santiago de los Caballeros, República Dominicana',
            'clinic_phone'   => '(809) 806-0444',
        ], $settings);
    }

    private static function longDate(?string $ymd): string
    {
        if (!$ymd) return '—';
        $ts = strtotime($ymd);
        return (int) date('j', $ts) . ' de ' . self::MESES[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
    }

    private static function clampInt($v, int $lo, int $hi, int $def): int
    {
        $v = (int) $v;
        if ($v === 0) return $def;
        return max($lo, min($hi, $v));
    }

    private static function buildHtml(string $name, array $settings, array $periods, array $pred, array $sym, array $clinic): string
    {
        $esc = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $logoData = @file_get_contents(__DIR__ . '/hglc_logo.png');
        $logoTag  = $logoData ? '<img class="logo" src="data:image/png;base64,' . base64_encode($logoData) . '" alt="">' : '';

        $goal = self::GOALS[$settings['goal'] ?? 'track'] ?? 'Seguir el ciclo';
        $gen  = self::longDate(date('Y-m-d'));

        // filas del resumen
        $rows = [
            ['Última menstruación (FUM)', self::longDate($pred['lastStart'])],
            ['Ciclo promedio', $pred['avgCycle'] . ' días'],
            ['Duración del periodo', $pred['avgPeriod'] . ' días'],
            ['Regularidad', $pred['regularity'] ?? 'Datos insuficientes'],
            ['Próximo periodo estimado', self::longDate($pred['nextStart'])],
            ['Ciclos registrados', (string) count($periods)],
            ['Objetivo', $esc($goal)],
        ];
        $rowsHtml = '';
        foreach ($rows as [$k, $v]) {
            $rowsHtml .= '<tr><td class="k">' . $esc($k) . '</td><td class="v">' . $esc($v) . '</td></tr>';
        }

        // embarazo
        $pregHtml = '';
        if (($settings['goal'] ?? '') === 'pregnant' && $pred['pregWeeks'] !== null) {
            $pregHtml = '<div class="section-title">Embarazo</div><table class="kv">'
                . '<tr><td class="k">Edad gestacional</td><td class="v">' . (int) $pred['pregWeeks'] . ' semanas ' . (int) $pred['pregDays'] . ' días</td></tr>'
                . '<tr><td class="k">Fecha probable de parto</td><td class="v">' . self::longDate($pred['dueDate']) . '</td></tr></table>';
        }

        // historial de ciclos
        $histHtml = '';
        if (!empty($pred['recent'])) {
            $cells = '';
            foreach ($pred['recent'] as $i => $l) {
                $cells .= '<td class="bar-cell"><div class="barwrap"><div class="bar" style="height:' . max(8, (int) round($l / max($pred['recent']) * 46)) . 'px"></div></div><div class="barval">' . (int) $l . '</div><div class="barlbl">#' . ($i + 1) . '</div></td>';
            }
            $histHtml = '<div class="section-title">Longitud de los últimos ciclos (días)</div>'
                . '<table class="bars"><tr>' . $cells . '</tr></table>';
        }

        // síntomas
        $symHtml = '';
        if ($sym) {
            $chips = '';
            foreach ($sym as $s) { $chips .= '<span class="chip">' . $esc($s) . '</span>'; }
            $symHtml = '<div class="section-title">Síntomas más frecuentes</div><div class="chips">' . $chips . '</div>';
        }

        $contact = trim($esc($clinic['clinic_address']) . ' · Tel: ' . $esc($clinic['clinic_phone']), ' ·');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
            @page { margin: 0; }
            * { font-family: "DejaVu Sans", sans-serif; }
            body { margin: 0; color: #2a2f45; font-size: 11px; }
            .wrap { padding: 34px 44px; }
            .header { border-bottom: 3px solid #262161; padding-bottom: 14px; margin-bottom: 4px; }
            .logo { height: 40px; }
            .clinic { color: #262161; font-size: 15px; font-weight: bold; margin-top: 6px; }
            .contact { color: #6b7390; font-size: 9.5px; margin-top: 2px; }
            .doc-band { background: #262161; color: #fff; padding: 11px 16px; border-radius: 7px; margin: 16px 0 6px; }
            .doc-band .t { font-size: 15px; font-weight: bold; }
            .doc-band .s { font-size: 10px; color: #c9c6ec; }
            .meta { width: 100%; margin: 10px 0 4px; font-size: 10px; color: #4a5170; }
            .meta td { padding: 2px 0; }
            .meta .r { text-align: right; color: #6b7390; }
            .section-title { color: #262161; font-size: 11px; font-weight: bold; text-transform: uppercase;
                letter-spacing: .4px; margin: 18px 0 7px; border-left: 3px solid #5da334; padding-left: 8px; }
            table.kv { width: 100%; border-collapse: collapse; }
            table.kv td { padding: 7px 10px; border-bottom: 1px solid #eef0f5; font-size: 11px; }
            table.kv td.k { color: #6b7390; width: 55%; }
            table.kv td.v { color: #1d2138; font-weight: bold; text-align: right; }
            table.bars { width: 100%; border-collapse: collapse; margin-top: 6px; }
            .bar-cell { text-align: center; vertical-align: bottom; }
            .barwrap { height: 50px; vertical-align: bottom; }
            .bar { width: 22px; margin: 0 auto; background: #262161; border-radius: 4px 4px 0 0; }
            .barval { font-weight: bold; font-size: 10px; margin-top: 3px; }
            .barlbl { color: #9aa0b8; font-size: 8.5px; }
            .chips { line-height: 1.9; }
            .chip { display: inline-block; background: #eef7e9; color: #2f7d18; border: 1px solid #d9ecc9;
                border-radius: 20px; padding: 3px 11px; margin: 0 4px 4px 0; font-size: 10px; }
            .note { margin-top: 22px; padding: 11px 13px; background: #f7f8fb; border: 1px solid #e7eaf2;
                border-radius: 7px; color: #6b7390; font-size: 9px; }
            .foot { margin-top: 16px; text-align: center; color: #9aa0b8; font-size: 8.5px; }
        </style></head><body><div class="wrap">
            <div class="header">' . $logoTag . '<div class="clinic">' . $esc($clinic['clinic_name']) . '</div>'
                . '<div class="contact">' . $contact . '</div></div>
            <div class="doc-band"><div class="t">Mi Ciclo — Resumen para la consulta</div>'
                . '<div class="s">Documento generado por la paciente desde el Portal</div></div>
            <table class="meta"><tr><td><b>Paciente:</b> ' . $esc($name) . '</td>'
                . '<td class="r">Generado: ' . $gen . '</td></tr></table>
            <div class="section-title">Resumen del ciclo</div>
            <table class="kv">' . $rowsHtml . '</table>'
            . $pregHtml . $histHtml . $symHtml . '
            <div class="note">Información autorreportada por la paciente con fines informativos. Las fechas son
                estimaciones basadas en su historial y no constituyen un diagnóstico ni sustituyen la evaluación médica.</div>
            <div class="foot">' . $esc($clinic['clinic_name']) . ' · Portal de Pacientes</div>
        </div></body></html>';
    }
}
