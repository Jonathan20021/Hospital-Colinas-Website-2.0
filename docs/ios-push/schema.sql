-- App de iOS "Mi Hospital" — tokens de APNs de los pacientes.
-- medical_call_center. Idempotente.
--
-- Un token identifica un DISPOSITIVO, no un paciente: si otro paciente inicia
-- sesión en el mismo iPhone, el token se le reasigna (UNIQUE en `token`).
-- La app lo da de baja al cerrar sesión; APNs avisa (410) si se desinstaló y
-- ApnsSender lo borra.

CREATE TABLE IF NOT EXISTS `portal_push_apns` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `patient_id`   INT          NOT NULL,
    `token`        VARCHAR(200) CHARACTER SET ascii NOT NULL,   -- hexadecimal
    `environment`  ENUM('sandbox','production') NOT NULL DEFAULT 'production',
    `bundle_id`    VARCHAR(120) NOT NULL,
    `app_version`  VARCHAR(20)  NULL,
    `os_version`   VARCHAR(20)  NULL,
    `device`       VARCHAR(40)  NULL,                            -- "iPhone" / "iPad"
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_sent_at` DATETIME     NULL,
    `last_error`   VARCHAR(60)  NULL,                            -- último motivo de rechazo de APNs
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_patient` (`patient_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interruptor general de los avisos por APNs. Empieza APAGADO ('0'): respeta la
-- política de no enviar nada a pacientes reales sin visto bueno. Ponerlo en '1'
-- cuando la app esté probada en TestFlight.
INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('patient_push_apns', '0')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
