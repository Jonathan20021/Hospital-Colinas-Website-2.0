<?php
/**
 * PDF "Diario de Síntomas — Resumen para la consulta" (Dompdf, marca HGLC).
 * Mismo stack que CycleSummaryPdf/PrescriptionPdf. Va en api/helpers/.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class SymptomsPdf
{
    private const MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    public const LABELS = [
        'headache' => 'Dolor de cabeza', 'fever' => 'Fiebre', 'cough' => 'Tos', 'sore_throat' => 'Dolor de garganta',
        'congestion' => 'Congestión nasal', 'fatigue' => 'Fatiga', 'nausea' => 'Náuseas', 'vomiting' => 'Vómito',
        'diarrhea' => 'Diarrea', 'abdominal_pain' => 'Dolor abdominal', 'muscle_pain' => 'Dolor muscular',
        'joint_pain' => 'Dolor articular', 'back_pain' => 'Dolor de espalda', 'chest_pain' => 'Dolor de pecho',
        'shortness_breath' => 'Falta de aire', 'dizziness' => 'Mareo', 'rash' => 'Erupción en la piel',
        'itching' => 'Picazón', 'insomnia' => 'Insomnio', 'anxiety' => 'Ansiedad', 'palpitations' => 'Palpitaciones',
        'loss_appetite' => 'Falta de apetito',
    ];
    private const SEV = [1 => 'Leve', 2 => 'Moderado', 3 => 'Fuerte'];
    private const FEEL = ['good' => 'Bien', 'regular' => 'Regular', 'bad' => 'Mal'];

    public static function render(PDO $db, int $patientId): array
    {
        $st = $db->prepare('SELECT name FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $name = mb_convert_case(mb_strtolower(trim((string) ($st->fetchColumn() ?: 'Paciente')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        $st = $db->prepare('SELECT recorded_at, symptoms, severity, feeling, note
                              FROM portal_symptom_entries WHERE patient_id = ?
                              ORDER BY recorded_at DESC LIMIT 60');
        $st->execute([$patientId]);
        $entries = $st->fetchAll(PDO::FETCH_ASSOC);

        $clinic = self::clinicSettings($db);
        $html   = self::buildHtml($name, $entries, $clinic);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();

        return ['pdf' => $dompdf->output(), 'filename' => 'diario_sintomas_' . date('Ymd') . '.pdf', 'title' => 'Diario de Síntomas'];
    }

    private static function clinicSettings(PDO $db): array
    {
        $settings = [];
        try { foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $r) $settings[$r['setting_key']] = $r['setting_value']; }
        catch (\Throwable $e) {}
        return array_merge(['clinic_name' => 'Hospital General Las Colinas',
            'clinic_address' => 'Santiago de los Caballeros, República Dominicana', 'clinic_phone' => '(809) 806-0444'], $settings);
    }

    private static function buildHtml(string $name, array $entries, array $clinic): string
    {
        $esc = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $logoData = @file_get_contents(__DIR__ . '/hglc_logo.png');
        $logoTag  = $logoData ? '<img class="logo" src="data:image/png;base64,' . base64_encode($logoData) . '" alt="">' : '';
        $contact  = trim($esc($clinic['clinic_address']) . ' · Tel: ' . $esc($clinic['clinic_phone']), ' ·');
        $gen      = (int) date('j') . ' de ' . self::MESES[(int) date('n')] . ' de ' . date('Y');

        // frecuencia de síntomas
        $freq = [];
        foreach ($entries as $e) {
            foreach (($e['symptoms'] ? json_decode($e['symptoms'], true) : []) ?: [] as $s) $freq[$s] = ($freq[$s] ?? 0) + 1;
        }
        arsort($freq);
        $freqHtml = '';
        foreach (array_slice($freq, 0, 8, true) as $id => $n) {
            $freqHtml .= '<span class="chip">' . $esc(self::LABELS[$id] ?? $id) . ' (' . $n . ')</span>';
        }

        // filas (cronológico)
        $rowsHtml = '';
        if (!$entries) {
            $rowsHtml = '<tr><td colspan="3" class="empty">Sin registros en el diario.</td></tr>';
        } else {
            foreach ($entries as $e) {
                $ts = strtotime($e['recorded_at']);
                $fecha = (int) date('j', $ts) . ' ' . substr(self::MESES[(int) date('n', $ts)], 0, 3) . ' ' . date('Y', $ts) . ' · ' . date('H:i', $ts);
                $syms = ($e['symptoms'] ? json_decode($e['symptoms'], true) : []) ?: [];
                $symTxt = $syms ? implode(', ', array_map(fn($s) => self::LABELS[$s] ?? $s, $syms)) : '—';
                $sev = self::SEV[(int) $e['severity']] ?? '';
                $feel = $e['feeling'] ? (self::FEEL[$e['feeling']] ?? '') : '';
                $note = trim((string) $e['note']);
                $rowsHtml .= '<tr>'
                    . '<td class="d">' . $esc($fecha) . '</td>'
                    . '<td class="s"><b>' . $esc($symTxt) . '</b>'
                    . ($note !== '' ? '<div class="nt">“' . $esc($note) . '”</div>' : '') . '</td>'
                    . '<td class="sev sev' . (int) $e['severity'] . '">' . $esc($sev) . ($feel ? '<div class="fl">' . $esc($feel) . '</div>' : '') . '</td>'
                    . '</tr>';
            }
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
            @page { margin: 0; }
            * { font-family: "DejaVu Sans", sans-serif; }
            body { margin: 0; color: #2a2f45; font-size: 10.5px; }
            .wrap { padding: 34px 44px; }
            .header { border-bottom: 3px solid #262161; padding-bottom: 14px; }
            .logo { height: 40px; }
            .clinic { color: #262161; font-size: 15px; font-weight: bold; margin-top: 6px; }
            .contact { color: #6b7390; font-size: 9.5px; margin-top: 2px; }
            .doc-band { background: #262161; color: #fff; padding: 11px 16px; border-radius: 7px; margin: 16px 0 6px; }
            .doc-band .t { font-size: 15px; font-weight: bold; }
            .doc-band .s { font-size: 10px; color: #c9c6ec; }
            .meta { width: 100%; margin: 10px 0 4px; font-size: 10px; color: #4a5170; }
            .meta .r { text-align: right; color: #6b7390; }
            .section-title { color: #262161; font-size: 11px; font-weight: bold; text-transform: uppercase;
                letter-spacing: .4px; margin: 16px 0 7px; border-left: 3px solid #5da334; padding-left: 8px; }
            .chip { display: inline-block; background: #eef7e9; color: #2f7d18; border: 1px solid #d9ecc9;
                border-radius: 20px; padding: 3px 11px; margin: 0 4px 4px 0; font-size: 9.5px; }
            table.log { width: 100%; border-collapse: collapse; }
            table.log td { padding: 8px 8px; border-bottom: 1px solid #eef0f5; vertical-align: top; }
            td.d { color: #6b7390; width: 24%; font-size: 9.5px; }
            td.s { width: 56%; }
            td.s .nt { color: #6b7390; font-style: italic; font-weight: normal; margin-top: 3px; }
            td.sev { width: 20%; text-align: right; font-weight: bold; }
            td.sev1 { color: #2f7d18; } td.sev2 { color: #b06d0a; } td.sev3 { color: #c0264a; }
            td.sev .fl { color: #6b7390; font-weight: normal; font-size: 9px; margin-top: 2px; }
            td.empty { text-align: center; color: #9aa0b8; padding: 18px; }
            .note { margin-top: 18px; padding: 11px 13px; background: #f7f8fb; border: 1px solid #e7eaf2;
                border-radius: 7px; color: #6b7390; font-size: 9px; }
            .foot { margin-top: 14px; text-align: center; color: #9aa0b8; font-size: 8.5px; }
        </style></head><body><div class="wrap">
            <div class="header">' . $logoTag . '<div class="clinic">' . $esc($clinic['clinic_name']) . '</div><div class="contact">' . $contact . '</div></div>
            <div class="doc-band"><div class="t">Diario de Síntomas — Resumen para la consulta</div><div class="s">Documento generado por la paciente desde el Portal</div></div>
            <table class="meta"><tr><td><b>Paciente:</b> ' . $esc($name) . '</td><td class="r">Generado: ' . $gen . ' · ' . count($entries) . ' registro(s)</td></tr></table>'
            . ($freqHtml ? '<div class="section-title">Síntomas más frecuentes</div><div>' . $freqHtml . '</div>' : '') . '
            <div class="section-title">Registros</div>
            <table class="log">' . $rowsHtml . '</table>
            <div class="note">Información autorreportada por la paciente con fines informativos. No constituye un diagnóstico ni sustituye la evaluación médica.</div>
            <div class="foot">' . $esc($clinic['clinic_name']) . ' · Portal de Pacientes</div>
        </div></body></html>';
    }
}
