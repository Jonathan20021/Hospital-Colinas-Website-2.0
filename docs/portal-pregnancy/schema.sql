-- Embarazo semana a semana — Portal del Paciente. medical_call_center. Idempotente.
-- Solo guarda la FUM del embarazo; el cálculo de semanas y el contenido viven en
-- el frontend. Una fila por paciente.

CREATE TABLE IF NOT EXISTS `portal_pregnancy` (
    `patient_id` INT       NOT NULL,
    `lmp_date`   DATE      NULL,        -- fecha de última menstruación (FUM)
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
