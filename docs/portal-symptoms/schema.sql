-- Diario de Síntomas — Portal del Paciente. medical_call_center. Idempotente.
-- Cronología de cómo se siente el paciente; la lleva/comparte con su médico.

CREATE TABLE IF NOT EXISTS `portal_symptom_entries` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `patient_id`  INT          NOT NULL,
    `recorded_at` DATETIME     NOT NULL,
    `symptoms`    JSON         NULL,    -- ["headache","fever",...]
    `severity`    TINYINT      NOT NULL DEFAULT 1,  -- 1 leve, 2 moderado, 3 fuerte
    `feeling`     VARCHAR(12)  NULL,    -- good | regular | bad
    `note`        VARCHAR(500) NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_patient` (`patient_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
