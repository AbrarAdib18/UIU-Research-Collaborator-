-- =====================================================================
-- UIU ResearchCollab — Migration 006
-- Admin Portal: faculty verification, platform settings, and the two
-- additive moderation flags needed for community post/comment hide-
-- restore (neither column previously existed).
--
-- Deliberately minimal: every other admin moderation action (user
-- activate/deactivate/suspend/restore, opportunity close/reopen/archive,
-- team archive/restore, community suspend/restore, resource publish/hide,
-- connection block, advisor-assignment end) reuses a status ENUM value
-- that already exists on that table — see this migration's sibling
-- README section for the full mapping. Moderation *reasons* are stored
-- in the existing `activity_logs.description` column rather than adding
-- a `note` column to five different tables.
--
-- Run this AFTER database/schema.sql, migrations.sql, migrations_002,
-- migrations_003, migrations_004, and migrations_005_faculty_portal.sql.
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER, same pattern as the
-- earlier migration files.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- faculty_verifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_verifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','verified','rejected','needs_update') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_verification_user` (`faculty_user_id`),
  KEY `idx_faculty_verification_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- platform_settings — simple key/value store, only for settings that
-- have real, verified backend effect (see README for the exact list).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

ALTER TABLE `faculty_verifications` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- ---------------------------------------------------------------------
-- Foreign keys (separate so table creation above never fails if a
-- constraint with the same name already exists from a partial re-run)
-- ---------------------------------------------------------------------
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_verification_user');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_verifications` ADD CONSTRAINT `fk_faculty_verification_user` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_verification_verifier');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_verifications` ADD CONSTRAINT `fk_faculty_verification_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_platform_setting_updater');
SET @sql := IF(@fk = 0, 'ALTER TABLE `platform_settings` ADD CONSTRAINT `fk_platform_setting_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- community_posts.is_hidden / community_comments.is_hidden — required
-- for admin Hide/Restore moderation (neither column previously existed).
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts' AND COLUMN_NAME = 'is_hidden');
SET @sql := IF(@col = 0, 'ALTER TABLE `community_posts` ADD COLUMN `is_hidden` tinyint(1) NOT NULL DEFAULT 0 AFTER `content`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_comments' AND COLUMN_NAME = 'is_hidden');
SET @sql := IF(@col = 0, 'ALTER TABLE `community_comments` ADD COLUMN `is_hidden` tinyint(1) NOT NULL DEFAULT 0 AFTER `comment`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- users(role, status) — every admin user-list/dashboard-count query
-- filters on both columns together.
-- ---------------------------------------------------------------------
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_role_status');
SET @sql := IF(@idx = 0, 'ALTER TABLE `users` ADD KEY `idx_users_role_status` (`role`,`status`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
