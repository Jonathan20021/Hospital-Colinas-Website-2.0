-- =============================================================================
--  Autorización de estudios (Imágenes y Laboratorio) — esquema
--  Base de datos: medical_call_center  (NUNCA SGC/SIGMA — eso es solo lectura)
--  Motor: MySQL/MariaDB · charset utf8mb4
--
--  Captura solicitudes de estudios (típicamente de pacientes externos, p. ej.
--  referidos del HOMS) para que un agente del call center / seguros gestione la
--  autorización con la ARS y registre el copago/restante a pagar.
--
--  Despliegue: ejecutar en JENOFONTE contra medical_call_center.
--    mysql -u <user> -p medical_call_center < 01-schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Solicitud de autorización (cabecera)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_auth_requests` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_code`       VARCHAR(20)  NOT NULL,                 -- código legible (EST-2026-000123)
  `patient_id`        BIGINT UNSIGNED NULL,                  -- paciente del portal, si existe
  `source`            ENUM('portal','guest') NOT NULL DEFAULT 'guest',
  `full_name`         VARCHAR(160) NOT NULL,
  `cedula`            VARCHAR(20)  NOT NULL,
  `phone`             VARCHAR(40)  NOT NULL,
  `email`             VARCHAR(160) NULL,
  `referring_center`  VARCHAR(160) NULL,                     -- p. ej. "HOMS"
  `referring_doctor`  VARCHAR(160) NULL,
  `insurer`           VARCHAR(120) NULL,                     -- ARS
  `insurer_member_id` VARCHAR(80)  NULL,                     -- nº afiliado / contrato
  `insurer_plan`      VARCHAR(120) NULL,
  `study_type`        ENUM('imaging','lab','both') NOT NULL,
  `urgency`           ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  `preferred_date`    DATE NULL,
  `notes`             TEXT NULL,
  `status`            ENUM(
                        'received',     -- recibida (recién enviada por el paciente)
                        'reviewing',    -- el agente la está revisando
                        'authorizing',  -- en gestión de autorización con la ARS
                        'authorized',   -- ARS autorizó
                        'need_info',    -- falta información / documento del paciente
                        'rejected',     -- la ARS no cubre / rechazada
                        'quoted',       -- copago/restante calculado y enviado
                        'scheduled',    -- coordinada la visita
                        'closed',       -- atendida / cerrada
                        'cancelled'     -- cancelada por el paciente
                      ) NOT NULL DEFAULT 'received',
  `assigned_agent_id` BIGINT UNSIGNED NULL,
  `consent_contact`   TINYINT(1) NOT NULL DEFAULT 0,         -- acepta que lo contacten
  `created_ip`        VARBINARY(16) NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_code` (`public_code`),
  KEY `ix_cedula`    (`cedula`),
  KEY `ix_status`    (`status`),
  KEY `ix_patient`   (`patient_id`),
  KEY `ix_assigned`  (`assigned_agent_id`),
  KEY `ix_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Estudios solicitados (detalle 1..N)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_auth_request_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `category`   ENUM('imaging','lab') NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `code`       VARCHAR(40) NULL,                             -- código interno / CPT si aplica
  PRIMARY KEY (`id`),
  KEY `ix_req` (`request_id`),
  CONSTRAINT `fk_item_req` FOREIGN KEY (`request_id`)
    REFERENCES `study_auth_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Documentos adjuntos (orden médica, carnet del seguro, cédula)
-- El binario se guarda CIFRADO en disco fuera del webroot; aquí va la metadata.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_auth_documents` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`    BIGINT UNSIGNED NOT NULL,
  `doc_type`      ENUM('order','insurance_front','insurance_back','cedula','other') NOT NULL,
  `storage_path`  VARCHAR(255) NOT NULL,                     -- ruta del blob cifrado
  `mime`          VARCHAR(80)  NOT NULL,
  `size_bytes`    INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(200) NULL,
  `uploaded_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`request_id`),
  CONSTRAINT `fk_doc_req` FOREIGN KEY (`request_id`)
    REFERENCES `study_auth_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Cotización / autorización (1:1 con la solicitud) — la llena el AGENTE
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_auth_quote` (
  `request_id`           BIGINT UNSIGNED NOT NULL,
  `authorization_number` VARCHAR(80) NULL,
  `currency`             CHAR(3) NOT NULL DEFAULT 'DOP',
  `total_amount`         DECIMAL(12,2) NULL,                 -- precio del/los estudio(s)
  `covered_amount`       DECIMAL(12,2) NULL,                 -- lo que cubre la ARS
  `copay_amount`         DECIMAL(12,2) NULL,                 -- copago fijo (si aplica)
  `patient_balance`      DECIMAL(12,2) NULL,                 -- RESTANTE A PAGAR por el paciente
  `valid_until`          DATE NULL,                          -- vigencia de la autorización
  `agent_note`           TEXT NULL,                          -- instrucciones (qué traer, preparación)
  `quoted_by`            BIGINT UNSIGNED NULL,
  `quoted_at`            TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `fk_quote_req` FOREIGN KEY (`request_id`)
    REFERENCES `study_auth_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Bitácora / auditoría de cada acción sobre la solicitud
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_auth_events` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `actor_type` ENUM('patient','agent','system') NOT NULL,
  `actor_id`   BIGINT UNSIGNED NULL,
  `action`     VARCHAR(60) NOT NULL,                         -- created, status_changed, doc_added, quoted...
  `detail`     TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_req` (`request_id`),
  CONSTRAINT `fk_evt_req` FOREIGN KEY (`request_id`)
    REFERENCES `study_auth_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Nota de retención: programar purga de `study_auth_documents` (blobs cifrados)
-- a los N días de `status IN ('closed','rejected','cancelled')` según la política
-- de retención del hospital. La metadata puede conservarse para estadística.
