<?php
/**
 * Editor de documentos clínicos del Portal Médico — endpoints (trait del
 * controlador del portal del doctor, mismo patrón que los demás me/* del médico).
 *
 * Datos SOLO en medical_call_center (doctor_documents, doctor_document_templates,
 * doctors.letterhead_logo). NUNCA toca SGC. Todo acotado por doctor_id del JWT
 * (aud=doctor) → anti-IDOR. El HTML del cuerpo se guarda YA saneado (lista blanca).
 *
 * Rutas a registrar en index.php ($doctorRoutes) — ver DEPLOY.md:
 *   GET    /portal-doctor/me/documents                 docDocumentsIndex   (?patient_id=)
 *   POST   /portal-doctor/me/documents                 docDocumentCreate
 *   GET    /portal-doctor/me/documents/{id}            docDocumentGet
 *   PUT    /portal-doctor/me/documents/{id}            docDocumentUpdate
 *   DELETE /portal-doctor/me/documents/{id}            docDocumentDelete
 *   GET    /portal-doctor/me/documents/{id}/pdf        docDocumentPdf      (binario)
 *   GET    /portal-doctor/me/document-templates        docTemplatesIndex
 *   POST   /portal-doctor/me/document-templates        docTemplateCreate
 *   DELETE /portal-doctor/me/document-templates/{id}   docTemplateDelete
 *   GET    /portal-doctor/me/letterhead                docLetterheadGet
 *   POST   /portal-doctor/me/letterhead                docLetterheadSet
 *   DELETE /portal-doctor/me/letterhead                docLetterheadDelete
 */
trait DoctorDocumentsTrait
{
    // ── Documentos ─────────────────────────────────────────────────────────

    public function docDocumentsIndex(): void
    {
        $did = $this->docDoctorId();
        $pid = (int) ($_GET['patient_id'] ?? 0);

        if ($pid > 0) {
            $st = $this->db->prepare(
                'SELECT id, patient_id, appointment_id, title, created_at, updated_at
                   FROM doctor_documents
                  WHERE doctor_id = ? AND patient_id = ? AND status = "active"
                  ORDER BY updated_at DESC LIMIT 200'
            );
            $st->execute([$did, $pid]);
        } else {
            $st = $this->db->prepare(
                'SELECT id, patient_id, appointment_id, title, created_at, updated_at
                   FROM doctor_documents
                  WHERE doctor_id = ? AND status = "active"
                  ORDER BY updated_at DESC LIMIT 100'
            );
            $st->execute([$did]);
        }

        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id'             => (int) $r['id'],
                'patient_id'     => (int) $r['patient_id'],
                'appointment_id' => $r['appointment_id'] !== null ? (int) $r['appointment_id'] : null,
                'title'          => (string) $r['title'],
                'created_at'     => $r['created_at'],
                'updated_at'     => $r['updated_at'],
            ];
        }
        Response::success($rows);
    }

    public function docDocumentGet(string $id): void
    {
        $did = $this->docDoctorId();
        $st = $this->db->prepare(
            'SELECT id, patient_id, appointment_id, title, body_html, created_at, updated_at
               FROM doctor_documents WHERE id = ? AND doctor_id = ? AND status = "active"'
        );
        $st->execute([(int) $id, $did]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) Response::error('Documento no encontrado.', 404);

        Response::success([
            'id'             => (int) $r['id'],
            'patient_id'     => (int) $r['patient_id'],
            'appointment_id' => $r['appointment_id'] !== null ? (int) $r['appointment_id'] : null,
            'title'          => (string) $r['title'],
            'body_html'      => (string) $r['body_html'],
            'created_at'     => $r['created_at'],
            'updated_at'     => $r['updated_at'],
        ]);
    }

    public function docDocumentCreate(): void
    {
        $did = $this->docDoctorId();
        $in  = $this->body();

        $pid   = (int) ($in['patient_id'] ?? 0);
        $appt  = isset($in['appointment_id']) && $in['appointment_id'] !== '' ? (int) $in['appointment_id'] : null;
        $title = $this->docCleanTitle($in['title'] ?? '');
        $body  = $this->sanitizeHtml((string) ($in['body_html'] ?? ''));

        if ($pid <= 0) Response::error('Falta el paciente.', 422);
        if (!$this->patientHasAppointmentWithMe($pid)) Response::notFound('Paciente no encontrado en tu agenda.');
        if ($this->docIsEmptyHtml($body)) Response::error('El documento está vacío.', 422);
        if ($appt !== null && !$this->docAppointmentBelongsToDoctor($did, $appt, $pid)) $appt = null;

        $st = $this->db->prepare(
            'INSERT INTO doctor_documents (doctor_id, patient_id, appointment_id, title, body_html)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$did, $pid, $appt, $title, $body]);
        $newId = (int) $this->db->lastInsertId();

        $this->logAudit('doc_document_create', "Redactó un documento clínico (#$newId) para el paciente $pid.");
        Response::success(['id' => $newId, 'title' => $title]);
    }

    public function docDocumentUpdate(string $id): void
    {
        $did = $this->docDoctorId();
        $in  = $this->body();

        // Propiedad + existencia
        $st = $this->db->prepare('SELECT patient_id FROM doctor_documents WHERE id = ? AND doctor_id = ? AND status = "active"');
        $st->execute([(int) $id, $did]);
        $pid = (int) $st->fetchColumn();
        if ($pid <= 0) Response::error('Documento no encontrado.', 404);

        $title = $this->docCleanTitle($in['title'] ?? '');
        $body  = $this->sanitizeHtml((string) ($in['body_html'] ?? ''));
        if ($this->docIsEmptyHtml($body)) Response::error('El documento está vacío.', 422);

        $up = $this->db->prepare('UPDATE doctor_documents SET title = ?, body_html = ? WHERE id = ? AND doctor_id = ?');
        $up->execute([$title, $body, (int) $id, $did]);

        $this->logAudit('doc_document_update', "Actualizó el documento clínico #$id.");
        Response::success(['id' => (int) $id, 'title' => $title]);
    }

    public function docDocumentDelete(string $id): void
    {
        $did = $this->docDoctorId();
        $st = $this->db->prepare('UPDATE doctor_documents SET status = "deleted" WHERE id = ? AND doctor_id = ? AND status = "active"');
        $st->execute([(int) $id, $did]);
        Response::success(['deleted' => $st->rowCount() > 0]);
    }

    public function docDocumentPdf(string $id): void
    {
        $did = $this->docDoctorId();
        require_once __DIR__ . '/../../helpers/DoctorDocumentPdf.php';
        try {
            $out = DoctorDocumentPdf::render($this->db, $did, (int) $id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('No se pudo generar el documento.', 422);
        }

        $this->logAudit('doc_document_pdf', "Generó el PDF del documento clínico #$id.");
        $inline = (($_GET['disposition'] ?? 'inline') === 'attachment') ? 'attachment' : 'inline';
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $inline . '; filename="' . $out['filename'] . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Length: ' . strlen($out['pdf']));
        echo $out['pdf'];
        exit;
    }

    // ── Plantillas propias del médico ──────────────────────────────────────

    public function docTemplatesIndex(): void
    {
        Response::success($this->docTemplatesList($this->docDoctorId()));
    }

    public function docTemplateCreate(): void
    {
        $did = $this->docDoctorId();
        $in  = $this->body();
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 160);
        $body = $this->sanitizeHtml((string) ($in['body_html'] ?? ''));
        if ($name === '') Response::error('Ponle un nombre a la plantilla.', 422);
        if ($this->docIsEmptyHtml($body)) Response::error('La plantilla está vacía.', 422);

        // Límite defensivo: máx. 60 plantillas por médico
        $c = $this->db->prepare('SELECT COUNT(*) FROM doctor_document_templates WHERE doctor_id = ?');
        $c->execute([$did]);
        if ((int) $c->fetchColumn() >= 60) Response::error('Alcanzaste el máximo de plantillas.', 422);

        $st = $this->db->prepare('INSERT INTO doctor_document_templates (doctor_id, name, body_html) VALUES (?,?,?)');
        $st->execute([$did, $name, $body]);
        Response::success($this->docTemplatesList($did));
    }

    public function docTemplateDelete(string $id): void
    {
        $did = $this->docDoctorId();
        $st = $this->db->prepare('DELETE FROM doctor_document_templates WHERE id = ? AND doctor_id = ?');
        $st->execute([(int) $id, $did]);
        Response::success($this->docTemplatesList($did));
    }

    private function docTemplatesList(int $did): array
    {
        $st = $this->db->prepare('SELECT id, name, body_html FROM doctor_document_templates WHERE doctor_id = ? ORDER BY name ASC');
        $st->execute([$did]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'body_html' => (string) $r['body_html']];
        }
        return $out;
    }

    // ── Logo / membrete propio del médico ──────────────────────────────────

    public function docLetterheadGet(): void
    {
        $did = $this->docDoctorId();
        $st = $this->db->prepare('SELECT letterhead_logo FROM doctors WHERE id = ?');
        $st->execute([$did]);
        $logo = (string) ($st->fetchColumn() ?: '');
        Response::success(['has_logo' => $logo !== '', 'logo' => $logo !== '' ? $logo : null]);
    }

    public function docLetterheadSet(): void
    {
        $did = $this->docDoctorId();
        $in  = $this->body();
        $img = trim((string) ($in['image'] ?? ''));

        if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $img)) {
            Response::error('Imagen inválida. Sube un PNG o JPG.', 422);
        }
        // ~2.7MB en base64 (~2MB binario)
        if (strlen($img) > 2800000) Response::error('La imagen es demasiado grande (máx. ~2 MB).', 422);

        $st = $this->db->prepare('UPDATE doctors SET letterhead_logo = ? WHERE id = ?');
        $st->execute([$img, $did]);
        Response::success(['has_logo' => true]);
    }

    public function docLetterheadDelete(): void
    {
        $did = $this->docDoctorId();
        $st = $this->db->prepare('UPDATE doctors SET letterhead_logo = NULL WHERE id = ?');
        $st->execute([$did]);
        Response::success(['has_logo' => false]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** ID del médico autenticado (reusa el helper del controlador). */
    private function docDoctorId(): int
    {
        return $this->doctorId();
    }

    private function docAppointmentBelongsToDoctor(int $did, int $appt, int $pid): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM appointments WHERE id = ? AND doctor_id = ? AND patient_id = ? LIMIT 1');
        $st->execute([$appt, $did, $pid]);
        return (bool) $st->fetchColumn();
    }

    private function docCleanTitle($v): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $v));
        $t = mb_substr($t, 0, 200);
        return $t !== '' ? $t : 'Documento clínico';
    }

    /** ¿El HTML no tiene contenido visible (solo etiquetas/espacios)? */
    private function docIsEmptyHtml(string $html): bool
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\x{00A0}|\s+/u', '', $text);
        // Si no hay texto pero hay una imagen embebida, cuenta como contenido.
        if ($text === '' && stripos($html, '<img') === false) return true;
        return false;
    }

    // ── Saneador de HTML (lista blanca) ────────────────────────────────────
    //
    // El HTML que llega del editor NUNCA es de confianza. Se reconstruye a
    // partir de un árbol DOM permitiendo solo etiquetas/atributos seguros.
    // Se aplica tanto al guardar (defensa en profundidad) como el resultado se
    // reusa al renderizar el PDF y al mostrarlo en el navegador.

    private const DOC_ALLOWED = [
        'p' => [], 'br' => [], 'span' => ['style'], 'div' => ['style', 'class'],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'strike' => [], 'sub' => [], 'sup' => [], 'small' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => [], 'hr' => [], 'pre' => [],
        'table' => ['style'], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'style'], 'td' => ['colspan', 'rowspan', 'style'],
        'a' => ['href'], 'img' => ['src', 'style', 'width', 'height', 'alt'],
    ];
    private const DOC_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'link', 'meta', 'form', 'input', 'button', 'textarea', 'select', 'noscript', 'base'];
    private const DOC_STYLE_PROPS = ['text-align', 'font-weight', 'font-style', 'text-decoration', 'color', 'width', 'background-color'];
    private const DOC_CLASSES = ['page-break', 'doc-align-center', 'doc-align-right', 'doc-align-justify'];

    public function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        if (strlen($html) > 800000) $html = substr($html, 0, 800000);

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="__docroot">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        if (!$ok) return '';

        $root = null;
        foreach ($dom->childNodes as $n) {
            if ($n->nodeType === XML_ELEMENT_NODE && strtolower($n->nodeName) === 'div') { $root = $n; break; }
        }
        if (!$root) return '';

        $this->docSanitizeNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    private function docSanitizeNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) continue;
            if ($child->nodeType !== XML_ELEMENT_NODE) { $child->parentNode->removeChild($child); continue; }

            $tag = strtolower($child->nodeName);

            if (!isset(self::DOC_ALLOWED[$tag])) {
                if (in_array($tag, self::DOC_DROP, true)) {
                    $child->parentNode->removeChild($child);
                } else {
                    // Etiqueta desconocida pero inocua: conservar su contenido.
                    $this->docSanitizeNode($child);
                    while ($child->firstChild) {
                        $child->parentNode->insertBefore($child->firstChild, $child);
                    }
                    $child->parentNode->removeChild($child);
                }
                continue;
            }

            $keep = self::DOC_ALLOWED[$tag];
            $removeNode = false;
            foreach (iterator_to_array($child->attributes) as $attr) {
                $an = strtolower($attr->nodeName);
                if (!in_array($an, $keep, true)) { $child->removeAttribute($attr->nodeName); continue; }
                $av = trim((string) $attr->nodeValue);

                if ($an === 'href') {
                    if (!preg_match('#^(https?:|mailto:|tel:)#i', $av)) $child->removeAttribute('href');
                } elseif ($an === 'src') {
                    if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $av) || strlen($av) > 2800000) {
                        $removeNode = true; break;
                    }
                } elseif ($an === 'style') {
                    $clean = $this->docSanitizeStyle($av);
                    if ($clean === '') $child->removeAttribute('style'); else $child->setAttribute('style', $clean);
                } elseif ($an === 'class') {
                    $safe = array_values(array_filter(preg_split('/\s+/', $av), fn($c) => in_array($c, self::DOC_CLASSES, true)));
                    if ($safe) $child->setAttribute('class', implode(' ', $safe)); else $child->removeAttribute('class');
                } elseif (in_array($an, ['width', 'height', 'colspan', 'rowspan', 'start'], true)) {
                    if (!preg_match('/^\d{1,5}$/', $av)) $child->removeAttribute($attr->nodeName);
                }
            }
            if ($removeNode) { $child->parentNode->removeChild($child); continue; }

            $this->docSanitizeNode($child);
        }
    }

    private function docSanitizeStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (strpos($decl, ':') === false) continue;
            [$p, $v] = explode(':', $decl, 2);
            $p = strtolower(trim($p));
            $v = trim($v);
            if (!in_array($p, self::DOC_STYLE_PROPS, true)) continue;
            if (preg_match('/url\s*\(|expression|javascript:|@import|[<>{}]/i', $v)) continue;
            if (!preg_match('/^[#a-z0-9 ,.%()\-]+$/i', $v)) continue;
            $out[] = $p . ':' . $v;
        }
        return implode(';', $out);
    }
}
