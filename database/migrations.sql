-- =====================================================================
-- UIU ResearchCollab — Migration 001
-- Adds Research Repository support (research_resources, saved_resources)
-- and a CV/resume column on student_profiles.
--
-- Run this AFTER importing database/schema.sql (uiu_researchcollab.sql).
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- research_resources
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `research_resources` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(250) NOT NULL,
  `description` text DEFAULT NULL,
  `resource_type` enum('Research Paper','Journal Article','Conference Paper','Dataset','Book','Thesis','Tutorial','Documentation','Other') NOT NULL DEFAULT 'Other',
  `author` varchar(200) DEFAULT NULL,
  `publication_year` year(4) DEFAULT NULL,
  `domain_id` int(10) UNSIGNED DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `visibility` enum('Public','Students Only','Private') NOT NULL DEFAULT 'Public',
  `status` enum('Published','Draft') NOT NULL DEFAULT 'Published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resource_status` (`status`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `fk_resource_domain` (`domain_id`),
  KEY `fk_resource_uploader` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- saved_resources
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_resources` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`resource_id`),
  KEY `fk_saved_resource_resource` (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

-- ---------------------------------------------------------------------
-- Foreign keys (separate so table creation above never fails if a
-- constraint with the same name already exists from a partial re-run)
-- ---------------------------------------------------------------------
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_resource_uploader');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `research_resources` ADD CONSTRAINT `fk_resource_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_resource_domain');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `research_resources` ADD CONSTRAINT `fk_resource_domain` FOREIGN KEY (`domain_id`) REFERENCES `research_domains` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_saved_resource_user');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `saved_resources` ADD CONSTRAINT `fk_saved_resource_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_saved_resource_resource');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `saved_resources` ADD CONSTRAINT `fk_saved_resource_resource` FOREIGN KEY (`resource_id`) REFERENCES `research_resources` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- student_profiles.cv_path (CV / résumé upload)
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'cv_path');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `cv_path` varchar(255) DEFAULT NULL AFTER `cover_photo`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
