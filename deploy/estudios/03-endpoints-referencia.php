<?php
/**
 * REFERENCIA para JENOFONTE — Autorización de estudios.
 *
 * NO es código del sitio público. Es la lógica de servidor a portar a la
 * estructura del API interno (traits portal_*, PDO, solo-OpenSSL). Adáptalo a
 * tu router/middleware. Escrito en PHP plano y comentado para que sea claro.
 *
 * Asunciones:
 *  - $pdo: PDO conectado a medical_call_center (utf8mb4).
 *  - El binario de documentos se guarda CIFRADO fuera del webroot:
 *      STUDY_DOCS_DIR  = '/var/secure/study_docs'   (no servible por Apache)
 *      STUDY_DOCS_KEY  = clave binaria de 32 bytes en config (NO en repo)
 *  - $patient = paciente autenticado (del JWT) en los endpoints /portal/me/*.
 *  - find_or_create_light_patient(): reusa la MISMA lógica del agendamiento de
 *    invitado (crea cuenta ligera, contraseña inicial = cédula, devuelve token).
 */

const STUDY_ALLOWED_MIME = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
const STUDY_MAX_BYTES    = 5 * 1024 * 1024;

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

function study_public_code(PDO $pdo, int $id): string {
    return sprintf('EST-%s-%06d', date('Y'), $id);
}

function study_clean_cedula(string $c): string {
    return preg_replace('/\D+/', '', $c);
}

/** Cifra y guarda el blob; devuelve la ruta relativa almacenada en BD. */
function study_store_encrypted(string $bin, string $mime): string {
    $dir = rtrim(STUDY_DOCS_DIR, '/') . '/' . date('Y/m');
    if (!is_dir($dir)) { mkdir($dir, 0700, true); }
    $name = bin2hex(random_bytes(16)) . '.enc';
    $rel  = date('Y/m') . '/' . $name;

    $iv  = random_bytes(12);                       // GCM nonce
    $tag = '';
    $ct  = openssl_encrypt($bin, 'aes-256-gcm', STUDY_DOCS_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) { throw new RuntimeException('cipher_failed'); }

    // formato: [12 IV][16 TAG][ciphertext]
    file_put_contents($dir . '/' . $name, $iv . $tag . $ct, LOCK_EX);
    @chmod($dir . '/' . $name, 0600);
    return $rel;
}

/** Lee y descifra el blob (uso del PANEL DEL AGENTE). */
function study_read_encrypted(string $rel): string {
    $path = rtrim(STUDY_DOCS_DIR, '/') . '/' . $rel;
    $raw  = file_get_contents($path);
    if ($raw === false || strlen($raw) < 28) { throw new RuntimeException('blob_missing'); }
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    $pt  = openssl_decrypt($ct, 'aes-256-gcm', STUDY_DOCS_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    if ($pt === false) { throw new RuntimeException('decipher_failed'); }
    return $pt;
}

function study_log(PDO $pdo, int $reqId, string $actorType, ?int $actorId, string $action, ?string $detail = null): void {
    $st = $pdo->prepare(
        'INSERT INTO study_auth_events (request_id, actor_type, actor_id, action, detail)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$reqId, $actorType, $actorId, $action, $detail]);
}

/* ---------------------------------------------------------------------------
 * Crear solicitud (núcleo compartido por invitado y autenticado)
 * ------------------------------------------------------------------------- */

function study_create_request(PDO $pdo, array $in, array $ctx): array {
    // $ctx: ['source'=>'portal'|'guest', 'patient_id'=>?int, 'ip'=>?string]
    $type = $in['study_type'] ?? '';
    if (!in_array($type, ['imaging','lab','both'], true)) {
        throw new InvalidArgumentException('study_type inválido');
    }
    $items = is_array($in['items'] ?? null) ? $in['items'] : [];
    $items = array_values(array_filter($items, fn($it) =>
        is_array($it) && in_array(($it['category'] ?? ''), ['imaging','lab'], true) && trim((string)($it['name'] ?? '')) !== ''
    ));
    if (!$items) { throw new InvalidArgumentException('Debes indicar al menos un estudio.'); }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO study_auth_requests
              (public_code, patient_id, source, full_name, cedula, phone, email,
               referring_center, referring_doctor, insurer, insurer_member_id, insurer_plan,
               study_type, urgency, preferred_date, notes, consent_contact, created_ip)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            'PENDING', $ctx['patient_id'] ?? null, $ctx['source'],
            trim((string)$in['full_name']), study_clean_cedula((string)$in['cedula']),
            trim((string)$in['phone']), ($in['email'] ?? null) ?: null,
            ($in['referring_center'] ?? null) ?: null, ($in['referring_doctor'] ?? null) ?: null,
            ($in['insurer'] ?? null) ?: null, ($in['insurer_member_id'] ?? null) ?: null,
            ($in['insurer_plan'] ?? null) ?: null,
            $type, in_array(($in['urgency'] ?? 'normal'), ['normal','urgent'], true) ? $in['urgency'] : 'normal',
            ($in['preferred_date'] ?? null) ?: null, ($in['notes'] ?? null) ?: null,
            !empty($in['consent_contact']) ? 1 : 0,
            isset($ctx['ip']) ? @inet_pton($ctx['ip']) : null,
        ]);
        $id   = (int)$pdo->lastInsertId();
        $code = study_public_code($pdo, $id);
        $pdo->prepare('UPDATE study_auth_requests SET public_code=? WHERE id=?')->execute([$code, $id]);

        $ins = $pdo->prepare('INSERT INTO study_auth_request_items (request_id, category, name, code) VALUES (?,?,?,?)');
        foreach ($items as $it) {
            $ins->execute([$id, $it['category'], mb_substr(trim((string)$it['name']), 0, 200), ($it['code'] ?? null) ?: null]);
        }
        study_log($pdo, $id, $ctx['source'] === 'portal' ? 'patient' : 'patient', $ctx['patient_id'] ?? null, 'created', 'Solicitud creada por el paciente.');
        $pdo->commit();
        return ['request_id' => $id, 'public_code' => $code, 'status' => 'received'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ---------------------------------------------------------------------------
 * Endpoint: POST /portal/guest/study-requests
 * ------------------------------------------------------------------------- */
function ep_guest_create(PDO $pdo, array $in, string $ip): array {
    // validar captcha + rate-limit aquí (reusar lo del agendamiento de invitado)
    $cedula = study_clean_cedula((string)($in['cedula'] ?? ''));
    if (strlen($cedula) < 8 || trim((string)($in['full_name'] ?? '')) === '' || trim((string)($in['phone'] ?? '')) === '') {
        return ['success' => false, 'message' => 'Faltan datos obligatorios.'];
    }

    // ¿la cédula ya tiene cuenta de portal?
    [$patientId, $hasAccount, $login] = find_or_create_light_patient($pdo, [
        'full_name' => $in['full_name'], 'cedula' => $cedula,
        'phone' => $in['phone'], 'email' => $in['email'] ?? null,
    ]); // reusar lógica existente del guest-appointment

    $res = study_create_request($pdo, $in, ['source' => 'guest', 'patient_id' => $patientId, 'ip' => $ip]);

    $data = $res + ['account_created' => (bool)($login !== null && !$hasAccount), 'has_account' => (bool)$hasAccount];
    if ($login !== null && !$hasAccount) {
        // autologin: devolver token igual que /portal/auth/login
        $data += [
            'token' => $login['token'], 'expires_in' => $login['expires_in'] ?? 3600,
            'patient' => $login['patient'] ?? null, 'email_verified' => !empty($login['email_verified']),
        ];
    }
    return ['success' => true, 'data' => $data];
}

/* ---------------------------------------------------------------------------
 * Endpoint: POST /portal/me/study-requests  (autenticado)
 * ------------------------------------------------------------------------- */
function ep_me_create(PDO $pdo, array $patient, array $in): array {
    // completar identidad desde el expediente del paciente autenticado
    $in['full_name'] = $in['full_name'] ?? $patient['name'];
    $in['cedula']    = $in['cedula']    ?? $patient['cedula'];
    $in['phone']     = $in['phone']     ?? ($patient['phone'] ?? '');
    $in['email']     = $in['email']     ?? ($patient['email'] ?? null);
    $in['insurer']   = $in['insurer']   ?? ($patient['insurance_provider'] ?? null);
    $res = study_create_request($pdo, $in, ['source' => 'portal', 'patient_id' => (int)$patient['id']]);
    return ['success' => true, 'data' => $res];
}

/* ---------------------------------------------------------------------------
 * Endpoint: GET /portal/me/study-requests  (autenticado)
 * ------------------------------------------------------------------------- */
function ep_me_list(PDO $pdo, array $patient): array {
    $st = $pdo->prepare(
        'SELECT r.*, q.authorization_number, q.currency, q.total_amount, q.covered_amount,
                q.copay_amount, q.patient_balance, q.valid_until, q.agent_note,
                (SELECT COUNT(*) FROM study_auth_documents d WHERE d.request_id=r.id) AS documents_count
         FROM study_auth_requests r
         LEFT JOIN study_auth_quote q ON q.request_id = r.id
         WHERE r.patient_id = ?
         ORDER BY r.created_at DESC'
    );
    $st->execute([(int)$patient['id']]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $itemsByReq = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $iq  = $pdo->prepare("SELECT request_id, category, name FROM study_auth_request_items WHERE request_id IN ($ph)");
        $iq->execute($ids);
        foreach ($iq->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $itemsByReq[$it['request_id']][] = ['category' => $it['category'], 'name' => $it['name']];
        }
    }

    $out = array_map(function (array $r) use ($itemsByReq) {
        $hasQuote = $r['patient_balance'] !== null || $r['authorization_number'] !== null;
        return [
            'id' => (int)$r['id'], 'public_code' => $r['public_code'],
            'study_type' => $r['study_type'], 'status' => $r['status'],
            'insurer' => $r['insurer'], 'created_at' => $r['created_at'],
            'items' => $itemsByReq[$r['id']] ?? [],
            'documents_count' => (int)$r['documents_count'],
            'quote' => $hasQuote ? [
                'authorization_number' => $r['authorization_number'], 'currency' => $r['currency'] ?: 'DOP',
                'total_amount' => $r['total_amount'], 'covered_amount' => $r['covered_amount'],
                'copay_amount' => $r['copay_amount'], 'patient_balance' => $r['patient_balance'],
                'valid_until' => $r['valid_until'], 'agent_note' => $r['agent_note'],
            ] : null,
        ];
    }, $rows);

    return ['success' => true, 'data' => ['requests' => $out]];
}

/* ---------------------------------------------------------------------------
 * Endpoint: POST /portal/me/study-requests/{id}/documents  (autenticado)
 * ------------------------------------------------------------------------- */
function ep_me_add_document(PDO $pdo, array $patient, int $reqId, array $in): array {
    // anti-IDOR: la solicitud debe ser del paciente
    $own = $pdo->prepare('SELECT id FROM study_auth_requests WHERE id=? AND patient_id=?');
    $own->execute([$reqId, (int)$patient['id']]);
    if (!$own->fetchColumn()) { return ['success' => false, 'message' => 'No encontrada.']; }

    $type = in_array(($in['doc_type'] ?? ''), ['order','insurance_front','insurance_back','cedula','other'], true)
        ? $in['doc_type'] : 'other';
    $mime = (string)($in['mime'] ?? '');
    if (!in_array($mime, STUDY_ALLOWED_MIME, true)) { return ['success' => false, 'message' => 'Tipo de archivo no permitido.']; }

    $bin = base64_decode((string)($in['data'] ?? ''), true);
    if ($bin === false || $bin === '') { return ['success' => false, 'message' => 'Archivo inválido.']; }
    if (strlen($bin) > STUDY_MAX_BYTES) { return ['success' => false, 'message' => 'El archivo supera 5 MB.']; }

    $rel = study_store_encrypted($bin, $mime);
    $st  = $pdo->prepare(
        'INSERT INTO study_auth_documents (request_id, doc_type, storage_path, mime, size_bytes, original_name)
         VALUES (?,?,?,?,?,?)'
    );
    $st->execute([$reqId, $type, $rel, $mime, strlen($bin), mb_substr((string)($in['filename'] ?? ''), 0, 200)]);
    $docId = (int)$pdo->lastInsertId();
    study_log($pdo, $reqId, 'patient', (int)$patient['id'], 'doc_added', $type);
    return ['success' => true, 'data' => ['document_id' => $docId]];
}

/* ---------------------------------------------------------------------------
 * Endpoint (PANEL AGENTE): POST /staff/study-requests/{id}/quote
 * Aquí el AGENTE teclea el restante a pagar tras autorizar con la ARS.
 * ------------------------------------------------------------------------- */
function ep_staff_quote(PDO $pdo, array $agent, int $reqId, array $in): array {
    $num = function ($v) { return ($v === '' || $v === null) ? null : round((float)$v, 2); };
    $st = $pdo->prepare(
        'INSERT INTO study_auth_quote
            (request_id, authorization_number, currency, total_amount, covered_amount,
             copay_amount, patient_balance, valid_until, agent_note, quoted_by, quoted_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE
            authorization_number=VALUES(authorization_number), currency=VALUES(currency),
            total_amount=VALUES(total_amount), covered_amount=VALUES(covered_amount),
            copay_amount=VALUES(copay_amount), patient_balance=VALUES(patient_balance),
            valid_until=VALUES(valid_until), agent_note=VALUES(agent_note),
            quoted_by=VALUES(quoted_by), quoted_at=NOW()'
    );
    $st->execute([
        $reqId, ($in['authorization_number'] ?? null) ?: null, ($in['currency'] ?? 'DOP') ?: 'DOP',
        $num($in['total_amount'] ?? null), $num($in['covered_amount'] ?? null),
        $num($in['copay_amount'] ?? null), $num($in['patient_balance'] ?? null),
        ($in['valid_until'] ?? null) ?: null, ($in['agent_note'] ?? null) ?: null,
        (int)$agent['id'],
    ]);
    $pdo->prepare("UPDATE study_auth_requests SET status='quoted' WHERE id=?")->execute([$reqId]);
    study_log($pdo, $reqId, 'agent', (int)$agent['id'], 'quoted',
        'Restante a pagar: ' . ($in['patient_balance'] ?? '—'));
    return ['success' => true, 'data' => ['status' => 'quoted']];
}

/* ---------------------------------------------------------------------------
 * Endpoint (PANEL AGENTE): POST /staff/study-requests/{id}/status
 * ------------------------------------------------------------------------- */
function ep_staff_status(PDO $pdo, array $agent, int $reqId, array $in): array {
    $valid = ['received','reviewing','authorizing','authorized','need_info','rejected','quoted','scheduled','closed','cancelled'];
    $status = $in['status'] ?? '';
    if (!in_array($status, $valid, true)) { return ['success' => false, 'message' => 'Estado inválido.']; }
    $pdo->prepare('UPDATE study_auth_requests SET status=?, assigned_agent_id=COALESCE(assigned_agent_id,?) WHERE id=?')
        ->execute([$status, (int)$agent['id'], $reqId]);
    study_log($pdo, $reqId, 'agent', (int)$agent['id'], 'status_changed', $status . (isset($in['note']) ? ' — ' . $in['note'] : ''));
    return ['success' => true, 'data' => ['status' => $status]];
}
