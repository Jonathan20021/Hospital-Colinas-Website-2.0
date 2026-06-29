-- Mis Signos Vitales — auto-registro del paciente (mediciones caseras).
-- medical_call_center. Idempotente. Los vitales del hospital viven en
-- `vital_signs` (registrados por el portal médico); esta tabla es SOLO lo que
-- el paciente mide en casa. La vista combina ambas fuentes (source hospital/self).

CREATE TABLE IF NOT EXISTS `portal_vitals` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `patient_id`  INT          NOT NULL,
    `recorded_at` DATETIME     NOT NULL,
    `systolic`    SMALLINT UNSIGNED NULL,   -- presión sistólica (mmHg)
    `diastolic`   SMALLINT UNSIGNED NULL,   -- presión diastólica (mmHg)
    `heart_rate`  SMALLINT UNSIGNED NULL,   -- pulso (lpm)
    `temperature` DECIMAL(4,1)  NULL,       -- °C
    `weight_kg`   DECIMAL(5,2)  NULL,       -- kg
    `height_cm`   DECIMAL(5,1)  NULL,       -- cm
    `spo2`        SMALLINT UNSIGNED NULL,   -- saturación O2 (%)
    `glucose`     SMALLINT UNSIGNED NULL,   -- glucemia (mg/dL)
    `note`        VARCHAR(255)  NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_patient` (`patient_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
