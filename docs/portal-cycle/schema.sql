-- ============================================================================
--  Mi Ciclo — esquema de datos (Portal del Paciente · HGLC)
--  Base de datos: medical_call_center  (NUNCA en SGC/SIGMA — solo lectura ahí)
--  Motor: MySQL / MariaDB
--
--  Datos altamente sensibles (salud reproductiva). Acceso siempre acotado por
--  patient_id del paciente autenticado (JWT). El paciente solo ve lo suyo.
-- ============================================================================

-- Preferencias del ciclo (una fila por paciente) -----------------------------
CREATE TABLE IF NOT EXISTS `portal_cycle_settings` (
    `patient_id`        INT          NOT NULL,
    `avg_cycle_length`  TINYINT      NOT NULL DEFAULT 28,
    `avg_period_length` TINYINT      NOT NULL DEFAULT 5,
    `goal`              ENUM('track','conceive','pregnant') NOT NULL DEFAULT 'track',
    `onboarded`         TINYINT(1)   NOT NULL DEFAULT 0,
    `reminders`         JSON         NULL,           -- reservado (fase push)
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periodos registrados (un inicio = una fila) --------------------------------
CREATE TABLE IF NOT EXISTS `portal_cycle_periods` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `patient_id` INT          NOT NULL,
    `start_date` DATE         NOT NULL,
    `end_date`   DATE         NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patient_start` (`patient_id`, `start_date`),
    KEY `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro diario (flujo, síntomas, ánimo, etc.) -----------------------------
CREATE TABLE IF NOT EXISTS `portal_cycle_logs` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `patient_id` INT          NOT NULL,
    `log_date`   DATE         NOT NULL,
    `flow`       VARCHAR(10)  NULL,    -- none | light | medium | heavy
    `symptoms`   JSON         NULL,    -- ["cramps","headache",...]
    `moods`      JSON         NULL,    -- ["happy","tired",...]
    `intimacy`   VARCHAR(12)  NULL,    -- none | protected | unprotected
    `pain`       TINYINT      NOT NULL DEFAULT 0,   -- 0..3
    `temp`       DECIMAL(4,2) NULL,    -- temperatura basal °C
    `notes`      VARCHAR(500) NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patient_day` (`patient_id`, `log_date`),
    KEY `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
