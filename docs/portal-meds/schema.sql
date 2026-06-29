-- Mis Medicamentos — Portal del Paciente. medical_call_center. Idempotente.
-- Lista de medicamentos del paciente + registro de tomas (checklist diario).

CREATE TABLE IF NOT EXISTS `portal_medications` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `patient_id` INT          NOT NULL,
    `name`       VARCHAR(120) NOT NULL,
    `dose`       VARCHAR(60)  NULL,     -- ej. "500 mg", "1 tableta"
    `times`      JSON         NULL,     -- ["08:00","20:00"]
    `note`       VARCHAR(255) NULL,     -- ej. "con comida"
    `active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_patient` (`patient_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_med_intakes` (
    `id`            INT       NOT NULL AUTO_INCREMENT,
    `patient_id`    INT       NOT NULL,
    `medication_id` INT       NOT NULL,
    `intake_date`   DATE      NOT NULL,
    `intake_time`   VARCHAR(5) NOT NULL,   -- "08:00"
    `taken_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_intake` (`medication_id`, `intake_date`, `intake_time`),
    KEY `idx_patient_date` (`patient_id`, `intake_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
