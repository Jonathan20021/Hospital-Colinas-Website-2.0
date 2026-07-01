<?php
/**
 * PDF de un documento clínico redactado en el editor del Portal Médico (Dompdf).
 * Mismo stack que SymptomsPdf/CycleSummaryPdf/PrescriptionPdf. Va en api/helpers/.
 *
 * Compone el MEMBRETE en el momento de renderizar (no se guarda en el registro):
 *   · Identidad institucional HGLC (logo o wordmark) a la izquierda.
 *   · Logo/membrete propio del médico (doctors.letterhead_logo) a la derecha, si existe.
 *   · Nombre, especialidad, exequátur y colegiatura del médico.
 *   · Línea del paciente + fecha.
 *   · Cuerpo = body_html YA saneado por DoctorDocumentsTrait::sanitizeHtml.
 *   · Pie con la firma del médico (doctors.signature_data) o una línea para firmar.
 *
 * Marca HGLC: navy #262161 + verde #5da334.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class DoctorDocumentPdf
{
    private const NAVY  = '#262161';
    private const GREEN = '#5da334';

    private const MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    public static function render(PDO $db, int $doctorId, int $documentId): array
    {
        // Documento (acotado por doctor_id → anti-IDOR)
        $st = $db->prepare('SELECT id, patient_id, appointment_id, title, body_html, created_at, updated_at
                              FROM doctor_documents
                             WHERE id = ? AND doctor_id = ? AND status = "active"');
        $st->execute([$documentId, $doctorId]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) throw new \RuntimeException('Documento no encontrado.');

        $doctor  = self::doctorInfo($db, $doctorId);
        $patient = self::patientInfo($db, (int) $doc['patient_id']);
        $clinic  = self::clinicSettings($db);

        $html = self::buildHtml($doc, $doctor, $patient, $clinic);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);   // habilita data: URIs (logos/firma embebidos)
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();

        $slug = self::slug($doc['title'] ?: 'documento');
        return [
            'pdf'      => $dompdf->output(),
            'filename' => $slug . '_' . date('Ymd') . '.pdf',
            'title'    => (string) $doc['title'],
        ];
    }

    // ── Datos ───────────────────────────────────────────────────────────────

    private static function doctorInfo(PDO $db, int $id): array
    {
        // Mismo JOIN que CertificatePdfGenerator: nombre = users.name,
        // especialidad = specialties.name; identidad legal en doctors.
        $st = $db->prepare(
            'SELECT u.name AS doctor_name, s.name AS specialty, d.subspecialty,
                    d.exequatur, d.medical_license_number, d.signature_data, d.letterhead_logo
               FROM doctors d
               JOIN users u       ON d.user_id = u.id
               LEFT JOIN specialties s ON d.specialty_id = s.id
              WHERE d.id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $d = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'name'         => trim((string) ($d['doctor_name'] ?? 'Médico')),
            'specialty'    => trim((string) ($d['specialty'] ?? '')),
            'subspecialty' => trim((string) ($d['subspecialty'] ?? '')),
            'exequatur'    => trim((string) ($d['exequatur'] ?? '')),
            'colegiatura'  => trim((string) ($d['medical_license_number'] ?? '')),
            'signature'    => self::dataUri((string) ($d['signature_data'] ?? '')),
            'logo'         => self::dataUri((string) ($d['letterhead_logo'] ?? '')),
        ];
    }

    private static function patientInfo(PDO $db, int $id): array
    {
        $st = $db->prepare('SELECT name, cedula, dob, gender FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $name = mb_convert_case(mb_strtolower(trim((string) ($p['name'] ?? 'Paciente')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        $age = '';
        if (!empty($p['dob'])) {
            try { $age = (string) (new DateTime())->diff(new DateTime($p['dob']))->y; } catch (\Throwable $e) {}
        }
        return ['name' => $name, 'cedula' => trim((string) ($p['cedula'] ?? '')), 'age' => $age];
    }

    private static function clinicSettings(PDO $db): array
    {
        $settings = [];
        try {
            foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Throwable $e) {}

        $logo = '';
        // 1) Logo institucional embebido (mismo que los demás PDF del portal).
        //    2) Data-URI en settings (clinic_logo_data). 3) wordmark de texto.
        $logoFile = __DIR__ . '/hglc_logo.png';
        if (is_readable($logoFile)) {
            $bytes = @file_get_contents($logoFile);
            if ($bytes !== false) $logo = 'data:image/png;base64,' . base64_encode($bytes);
        }
        if ($logo === '' && !empty($settings['clinic_logo_data'])) {
            $logo = self::dataUri((string) $settings['clinic_logo_data']);
        }

        return array_merge([
            'clinic_name'    => 'Hospital General Las Colinas',
            'clinic_address' => 'Santiago de los Caballeros, República Dominicana',
            'clinic_phone'   => '',
            'clinic_logo'    => $logo,
        ], array_intersect_key($settings, array_flip(['clinic_name', 'clinic_address', 'clinic_phone']))) + ['clinic_logo' => $logo];
    }

    // ── HTML ──────────────────────────────────────────────────────────────

    private static function buildHtml(array $doc, array $doc2, array $patient, array $clinic): string
    {
        $navy = self::NAVY; $green = self::GREEN;

        // Membrete institucional (izquierda): logo o wordmark
        if (!empty($clinic['clinic_logo'])) {
            $instBlock = '<img src="' . self::e($clinic['clinic_logo']) . '" style="max-height:58px;max-width:230px;">';
        } else {
            $instBlock = '<div class="wm"><span class="wm1">HOSPITAL GENERAL</span><br><span class="wm2">LAS COLINAS</span></div>';
        }

        // Logo propio del médico (derecha), si lo cargó
        $docLogo = !empty($doc2['logo'])
            ? '<img src="' . self::e($doc2['logo']) . '" style="max-height:58px;max-width:220px;">'
            : '';

        // Identidad del médico
        $spec = $doc2['specialty'];
        if ($doc2['subspecialty'] !== '') $spec = trim($spec . ' · ' . $doc2['subspecialty']);
        $cred = [];
        if ($doc2['exequatur']   !== '') $cred[] = 'Exequátur ' . self::e($doc2['exequatur']);
        if ($doc2['colegiatura'] !== '') $cred[] = 'CMD ' . self::e($doc2['colegiatura']);
        $credLine = $cred ? '<div class="lh-cred">' . implode(' &nbsp;·&nbsp; ', $cred) . '</div>' : '';

        // Línea del paciente
        $pmeta = ['Paciente: <b>' . self::e($patient['name']) . '</b>'];
        if ($patient['cedula'] !== '') $pmeta[] = 'Cédula: ' . self::e($patient['cedula']);
        if ($patient['age'] !== '')    $pmeta[] = 'Edad: ' . self::e($patient['age']) . ' años';

        $today = (int) date('j') . ' de ' . self::MESES[(int) date('n')] . ' de ' . date('Y');
        $place = 'Santiago de los Caballeros, R.D.';

        // Firma
        if (!empty($doc2['signature'])) {
            $sig = '<img src="' . self::e($doc2['signature']) . '" style="max-height:70px;max-width:240px;"><div class="sig-line"></div>';
        } else {
            $sig = '<div class="sig-space"></div><div class="sig-line"></div>';
        }
        $sigName = '<div class="sig-name">' . self::e($doc2['name']) . '</div>';
        $sigSub  = $spec !== '' ? '<div class="sig-sub">' . self::e($spec) . '</div>' : '';
        if ($cred) $sigSub .= '<div class="sig-sub">' . implode(' · ', $cred) . '</div>';

        $body = $doc['body_html'] !== '' ? $doc['body_html'] : '<p style="color:#94a3b8">(Documento sin contenido)</p>';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
        @page { margin: 46px 54px 176px 54px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #1f2430; font-size: 11.5pt; line-height: 1.5; }

        /* Membrete en el flujo (solo primera página, sin encimarse) */
        .lh { margin: 0 0 16px; }
        .lh-logos { width: 100%; border-collapse: collapse; }
        .lh-logos td { vertical-align: middle; }
        .lh-left { text-align: left; }
        .lh-right { text-align: right; }
        .wm { line-height: 1.05; }
        .wm1 { color: ' . $navy . '; font-size: 15pt; font-weight: bold; letter-spacing: .5px; }
        .wm2 { color: ' . $green . '; font-size: 15pt; font-weight: bold; letter-spacing: .5px; }
        .lh-id { text-align: center; margin-top: 8px; }
        .lh-name { color: ' . $navy . '; font-size: 13pt; font-weight: bold; }
        .lh-spec { color: #475569; font-size: 9.5pt; margin-top: 1px; }
        .lh-cred { color: #64748b; font-size: 8.5pt; margin-top: 2px; }
        .lh-rule { height: 3px; margin-top: 8px;
                   background: ' . $navy . '; border-bottom: 2px solid ' . $green . '; }

        /* Pie fijo (todas las páginas) */
        .ft { position: fixed; bottom: 0; left: 0; right: 0;
              border-top: 1px solid #e2e8f0; padding-top: 6px;
              color: #94a3b8; font-size: 8pt; text-align: center; }

        /* Firma anclada SIEMPRE al fondo, sobre el pie (reservado por el margen) */
        .sign { position: fixed; left: 0; right: 0; bottom: 44px; text-align: center; }

        .meta { width: 100%; border-collapse: collapse; margin: 0 0 14px; font-size: 9.5pt; color: #334155; }
        .meta td { vertical-align: top; }
        .meta .r { text-align: right; color: #64748b; }

        h1.doc-title { color: ' . $navy . '; font-size: 15pt; margin: 2px 0 12px; }
        .doc-body { text-align: left; }
        .doc-body p { margin: 0 0 9px; }
        .doc-body h1 { font-size: 14pt; color: ' . $navy . '; margin: 14px 0 7px; }
        .doc-body h2 { font-size: 12.5pt; color: ' . $navy . '; margin: 12px 0 6px; }
        .doc-body h3 { font-size: 11.5pt; color: #334155; margin: 10px 0 5px; }
        .doc-body ul, .doc-body ol { margin: 0 0 9px 20px; padding: 0; }
        .doc-body li { margin: 0 0 3px; }
        .doc-body table { border-collapse: collapse; width: 100%; margin: 8px 0; }
        .doc-body th, .doc-body td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 10pt; text-align: left; }
        .doc-body th { background: #f1f5f9; color: ' . $navy . '; }
        .doc-body blockquote { margin: 8px 0; padding: 4px 12px; border-left: 3px solid ' . $green . '; color: #475569; }
        .doc-body hr { border: 0; border-top: 1px solid #e2e8f0; margin: 12px 0; }
        .doc-body img { max-width: 100%; }
        .doc-align-center { text-align: center; }
        .doc-align-right { text-align: right; }
        .doc-align-justify { text-align: justify; }
        .page-break { page-break-before: always; }

        .sign-inner { width: 270px; margin-left: auto; margin-right: auto; text-align: center; }
        .sig-space { height: 60px; }
        .sig-line { border-top: 1px solid #334155; margin-top: 4px; }
        .sig-name { color: ' . $navy . '; font-weight: bold; font-size: 10.5pt; margin-top: 5px; }
        .sig-sub { color: #64748b; font-size: 8.5pt; }
        </style></head><body>

        <div class="lh">
            <table class="lh-logos"><tr>
                <td class="lh-left">' . $instBlock . '</td>
                <td class="lh-right">' . $docLogo . '</td>
            </tr></table>
            <div class="lh-id">
                <div class="lh-name">' . self::e($doc2['name']) . '</div>'
                . ($spec !== '' ? '<div class="lh-spec">' . self::e($spec) . '</div>' : '')
                . $credLine . '
            </div>
            <div class="lh-rule"></div>
        </div>

        <div class="ft">' . self::e($clinic['clinic_name']) . ' &nbsp;·&nbsp; ' . self::e($clinic['clinic_address'])
            . ($clinic['clinic_phone'] ? ' &nbsp;·&nbsp; ' . self::e($clinic['clinic_phone']) : '') . '</div>

        <table class="meta"><tr>
            <td>' . implode(' &nbsp;·&nbsp; ', $pmeta) . '</td>
            <td class="r">' . self::e($place) . '<br>' . self::e($today) . '</td>
        </tr></table>

        <h1 class="doc-title">' . self::e($doc['title']) . '</h1>

        <div class="doc-body">' . $body . '</div>

        <div class="sign"><div class="sign-inner">'
            . $sig . $sigName . $sigSub .
        '</div></div>

        </body></html>';
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private static function dataUri(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        return preg_match('#^data:image/#i', $v) ? $v : '';
    }

    private static function slug(string $s): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $s));
        $s = trim($s, '_');
        return $s !== '' ? substr($s, 0, 48) : 'documento';
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
