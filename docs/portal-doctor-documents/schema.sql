-- ============================================================================
--  Editor de documentos clínicos del Portal Médico  ·  esquema
--  Base de datos: medical_call_center   (NUNCA SGC)
--  Motor: MariaDB 10.4+  (soporta ADD COLUMN IF NOT EXISTS y VALUES(col))
-- ============================================================================
--
--  Guarda documentos "tipo Word" que el médico redacta para un paciente
--  (cartas, informes, indicaciones, certificados libres, etc.). El cuerpo se
--  almacena como HTML saneado (lista blanca); el membrete y el pie se componen
--  en el momento de renderizar el PDF a partir del perfil del médico, de modo
--  que el dato guardado quede limpio y las actualizaciones de membrete apliquen
--  de forma retroactiva.
--
--  Todo va acotado por doctor_id (del JWT aud=doctor) → anti-IDOR.
-- ----------------------------------------------------------------------------

-- 1) Documentos redactados por el médico y guardados en el expediente ----------
CREATE TABLE IF NOT EXISTS doctor_documents (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    doctor_id      INT UNSIGNED    NOT NULL,
    patient_id     INT UNSIGNED    NOT NULL,
    appointment_id INT UNSIGNED    NULL,
    title          VARCHAR(200)    NOT NULL DEFAULT 'Documento clínico',
    body_html      MEDIUMTEXT      NOT NULL,
    status         ENUM('active','deleted') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_doc_patient (doctor_id, patient_id, status),
    KEY idx_patient     (patient_id, status),
    KEY idx_appt        (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Plantillas/formatos propios del médico (reutilizables, con variables) ------
CREATE TABLE IF NOT EXISTS doctor_document_templates (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    doctor_id  INT UNSIGNED  NOT NULL,
    name       VARCHAR(160)  NOT NULL,
    body_html  MEDIUMTEXT    NOT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_doctor (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Logo/membrete propio del médico -------------------------------------------
--    Igual que doctors.signature_data (PNG en data-URI base64). Opcional: si el
--    médico no sube nada, el PDF usa solo el membrete institucional de HGLC.
ALTER TABLE doctors
    ADD COLUMN IF NOT EXISTS letterhead_logo MEDIUMTEXT NULL AFTER signature_data;

-- ============================================================================
--  Notas
--  · body_html se guarda YA saneado por el backend (DoctorDocumentsTrait::
--    sanitizeHtml). Nunca confiar en el HTML del cliente.
--  · No se guarda el membrete en el registro: se compone al renderizar.
--  · Borrado lógico (status='deleted') para no perder trazabilidad del
--    expediente; el listado del paciente filtra status='active'.
-- ============================================================================
