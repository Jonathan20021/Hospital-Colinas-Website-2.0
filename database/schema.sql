CREATE DATABASE IF NOT EXISTS hospital_colinas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hospital_colinas;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS specialties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    specialty_id INT UNSIGNED NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(160) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    title VARCHAR(40) NULL,
    exequatur VARCHAR(80) NULL,
    photo_path VARCHAR(255) NULL,
    biography TEXT NULL,
    education TEXT NULL,
    languages VARCHAR(255) NULL,
    services TEXT NULL,
    insurances TEXT NULL,
    associations TEXT NULL,
    schedule TEXT NULL,
    office VARCHAR(180) NULL,
    phone VARCHAR(80) NULL,
    email VARCHAR(190) NULL,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX doctors_status_idx (status),
    INDEX doctors_featured_idx (is_featured),
    CONSTRAINT doctors_specialty_fk
        FOREIGN KEY (specialty_id) REFERENCES specialties(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_users (name, email, password_hash, role, is_active)
SELECT 'Administrador Las Colinas', 'admin@colinashospital.com', '$2y$10$ewB3SDAYily4S4NMABH2aefPxVkmxAcatT4f9zVdwyN5WBPwju8TC', 'admin', 1
WHERE NOT EXISTS (
    SELECT 1 FROM admin_users WHERE email = 'admin@colinashospital.com'
);

CREATE TABLE IF NOT EXISTS ai_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    api_key TEXT NULL,
    model VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    max_tokens INT NOT NULL DEFAULT 700,
    system_prompt_extra TEXT NULL,
    welcome_message TEXT NULL,
    assistant_name VARCHAR(80) NOT NULL DEFAULT 'Colinas IA',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_settings (id, enabled, model)
SELECT 1, 0, 'gpt-4o-mini'
WHERE NOT EXISTS (SELECT 1 FROM ai_settings WHERE id = 1);

CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    tokens INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX ai_conv_session_idx (session_id),
    INDEX ai_conv_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
