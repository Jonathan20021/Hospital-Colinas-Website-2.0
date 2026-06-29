-- Mi Ciclo — anti-duplicado de recordatorios push + toggle global.
-- medical_call_center. Idempotente.

CREATE TABLE IF NOT EXISTS `portal_cycle_reminders_sent` (
    `id`         INT         NOT NULL AUTO_INCREMENT,
    `patient_id` INT         NOT NULL,
    `kind`       VARCHAR(20) NOT NULL,   -- period_soon | period_today | fertile_start | ovulation
    `for_date`   DATE        NOT NULL,   -- fecha del próximo periodo de referencia
    `sent_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reminder` (`patient_id`, `kind`, `for_date`),
    KEY `idx_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master switch (ausencia = habilitado). Lo dejamos explícito en '1'.
INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('patient_push_cycle_reminder', '1')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
