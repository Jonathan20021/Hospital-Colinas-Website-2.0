-- Recordatorios de Prevención — Portal del Paciente. medical_call_center. Idempotente.
-- Solo guarda cuándo el paciente se hizo cada tamizaje; el catálogo y el cálculo
-- de "qué toca" viven en el frontend (según edad y sexo del expediente).

CREATE TABLE IF NOT EXISTS `portal_screenings` (
    `id`            INT         NOT NULL AUTO_INCREMENT,
    `patient_id`    INT         NOT NULL,
    `screening_key` VARCHAR(40) NOT NULL,
    `done_date`     DATE        NULL,
    `created_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patient_screening` (`patient_id`, `screening_key`),
    KEY `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
