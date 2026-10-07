-- ==============================================
-- PERSONAL STORAGE — Complete MySQL Schema
-- ==============================================
-- Run this ONCE to create all tables.
--
-- Usage (Windows/XAMPP):
--   cd C:\xampp\mysql\bin
--   .\mysql.exe -u root personal_storage < C:\xampp\htdocs\personal-storage\database\schema.sql
--
-- Requirements:
--   MySQL 5.7+ or MariaDB 10.3+
--   Database must already exist:
--     CREATE DATABASE personal_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ==============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ──────────────────────────────────────────────
-- 1. USERS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `full_name`         VARCHAR(255)    NOT NULL,
    `email`             VARCHAR(255)    NOT NULL,
    `password_hash`     VARCHAR(255)    NOT NULL,
    `email_verified_at` DATETIME        NULL DEFAULT NULL,
    `status`            ENUM('pending', 'active', 'disabled') NOT NULL DEFAULT 'pending',
    `last_login_at`     DATETIME        NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_status` (`status`),
    KEY `idx_users_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 2. EMAIL VERIFICATIONS (OTP)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `otp_hash`      VARCHAR(255)    NOT NULL COMMENT 'Hashed OTP, never plaintext',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`    DATETIME        NOT NULL,
    `used`          TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ev_user` (`user_id`),
    KEY `idx_ev_expires` (`expires_at`),
    CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 3. PASSWORD RESETS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `token_hash`    VARCHAR(255)    NOT NULL COMMENT 'Hashed reset token',
    `otp_hash`      VARCHAR(255)    NULL DEFAULT NULL COMMENT 'Hashed OTP for reset verification',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`    DATETIME        NOT NULL,
    `used`          TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_user` (`user_id`),
    KEY `idx_pr_expires` (`expires_at`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 4. FILES
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `files` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`               INT UNSIGNED    NOT NULL,
    `original_name`         VARCHAR(255)    NOT NULL COMMENT 'User-provided filename (sanitized)',
    `stored_name`           VARCHAR(255)    NOT NULL COMMENT 'Randomized physical filename',
    `storage_path`          VARCHAR(500)    NOT NULL COMMENT 'Relative path inside storage/private/uploads/',
    `mime_type`             VARCHAR(100)    NOT NULL,
    `extension`             VARCHAR(20)     NOT NULL,
    `category`              ENUM('document', 'image', 'video', 'other') NOT NULL DEFAULT 'other',
    `size_bytes`            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `metadata`              JSON            NULL DEFAULT NULL COMMENT 'Optional: dimensions, duration, etc.',
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            DATETIME        NULL DEFAULT NULL COMMENT 'Soft-delete timestamp (recycle bin)',
    `deletion_expiry_at`    DATETIME        NULL DEFAULT NULL COMMENT 'Auto-permanent-delete after 7 days',
    PRIMARY KEY (`id`),
    KEY `idx_files_user` (`user_id`),
    KEY `idx_files_category` (`category`),
    KEY `idx_files_deleted` (`deleted_at`),
    KEY `idx_files_expiry` (`deletion_expiry_at`),
    KEY `idx_files_user_active` (`user_id`, `deleted_at`),
    CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 5. NOTES
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notes` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`               INT UNSIGNED    NOT NULL,
    `title`                 VARCHAR(255)    NULL DEFAULT NULL,
    `content`               TEXT            NOT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            DATETIME        NULL DEFAULT NULL,
    `deletion_expiry_at`    DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notes_user` (`user_id`),
    KEY `idx_notes_deleted` (`deleted_at`),
    KEY `idx_notes_expiry` (`deletion_expiry_at`),
    KEY `idx_notes_user_active` (`user_id`, `deleted_at`),
    CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 6. STORAGE SETTINGS (Global Defaults)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `storage_settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)    NOT NULL,
    `setting_value` TEXT            NOT NULL,
    `setting_type`  ENUM('int', 'string', 'json', 'bool') NOT NULL DEFAULT 'string',
    `description`   VARCHAR(255)    NULL DEFAULT NULL,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ss_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 7. USER STORAGE SETTINGS (Per-User Overrides)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_storage_settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `setting_key`   VARCHAR(100)    NOT NULL,
    `setting_value` TEXT            NOT NULL,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uss_user_key` (`user_id`, `setting_key`),
    CONSTRAINT `fk_uss_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 8. ADMIN USERS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(100)    NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `full_name`     VARCHAR(255)    NOT NULL,
    `status`        ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME        NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_username` (`username`),
    UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 9. ACTIVITY LOGS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NULL DEFAULT NULL COMMENT 'NULL if admin-only action',
    `admin_id`      INT UNSIGNED    NULL DEFAULT NULL COMMENT 'NULL if user-only action',
    `action`        VARCHAR(100)    NOT NULL COMMENT 'e.g. login, upload, delete, restore',
    `entity_type`   VARCHAR(50)     NULL DEFAULT NULL COMMENT 'file, note, user, setting',
    `entity_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `details`       JSON            NULL DEFAULT NULL COMMENT 'Additional context (no secrets)',
    `ip_address`    VARCHAR(45)     NOT NULL,
    `user_agent`    VARCHAR(500)    NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_user` (`user_id`),
    KEY `idx_al_admin` (`admin_id`),
    KEY `idx_al_action` (`action`),
    KEY `idx_al_entity` (`entity_type`, `entity_id`),
    KEY `idx_al_created` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_al_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- 10. RATE LIMITS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rate_key`      VARCHAR(255)    NOT NULL COMMENT 'e.g. login:user@email.com',
    `attempted_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rl_key_time` (`rate_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ══════════════════════════════════════════════
-- SEED: DEFAULT GLOBAL STORAGE SETTINGS
-- ══════════════════════════════════════════════

INSERT INTO `storage_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
    ('default_storage_quota',       '10737418240',    'int',    'Default user storage quota in bytes (10 GB)'),
    ('max_document_size',           '20971520',       'int',    'Max document file size in bytes (20 MB)'),
    ('max_image_size',              '10485760',       'int',    'Max image file size in bytes (10 MB)'),
    ('max_video_size',              '31457280',       'int',    'Max video file size in bytes (30 MB)'),
    ('max_other_size',              '10485760',       'int',    'Max other file size in bytes (10 MB)'),
    ('max_document_count',          '1000',           'int',    'Max number of document files per user'),
    ('max_image_count',             '2000',           'int',    'Max number of image files per user'),
    ('max_video_count',             '100',            'int',    'Max number of video files per user'),
    ('max_other_count',             '500',            'int',    'Max number of other files per user'),
    ('max_note_count',              '500',            'int',    'Max number of notes per user'),
    ('notes_count_toward_storage',  '0',              'bool',   'Whether note content counts toward storage quota'),
    ('recycle_bin_days',            '7',              'int',    'Days before recycle bin items are permanently deleted'),
    ('allowed_document_extensions', '["pdf","doc","docx","xls","xlsx","ppt","pptx","txt","csv","zip"]', 'json', 'Allowed document extensions'),
    ('allowed_image_extensions',    '["jpg","jpeg","png","gif","webp","svg"]', 'json', 'Allowed image extensions'),
    ('allowed_video_extensions',    '["mp4","webm","ogg","avi","mov","mkv"]', 'json', 'Allowed video extensions'),
    ('allowed_other_extensions',    '["json","xml","html","css","js","md","log"]', 'json', 'Allowed other file extensions'),
    ('allowed_document_mimes',      '["application/pdf","application/msword","application/vnd.openxmlformats-officedocument.wordprocessingml.document","application/vnd.ms-excel","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application/vnd.ms-powerpoint","application/vnd.openxmlformats-officedocument.presentationml.presentation","text/plain","text/csv","application/zip"]', 'json', 'Allowed document MIME types'),
    ('allowed_image_mimes',         '["image/jpeg","image/png","image/gif","image/webp","image/svg+xml"]', 'json', 'Allowed image MIME types'),
    ('allowed_video_mimes',         '["video/mp4","video/webm","video/ogg","video/x-msvideo","video/quicktime","video/x-matroska"]', 'json', 'Allowed video MIME types'),
    ('allowed_other_mimes',         '["application/json","application/xml","text/html","text/css","application/javascript","text/markdown","text/plain"]', 'json', 'Allowed other MIME types');


SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================
-- Schema import complete.
-- Tables created: 10
-- Default settings seeded: 20
-- ==============================================