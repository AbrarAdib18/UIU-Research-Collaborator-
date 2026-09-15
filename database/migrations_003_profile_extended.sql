-- =====================================================================
-- UIU ResearchCollab — Migration 003
-- Removes every "Coming Soon" placeholder from the Student Profile /
-- Edit Profile area and backs each formerly-inert field with a real
-- database column or table.
--
-- Run this AFTER database/schema.sql, database/migrations.sql, and
-- database/migrations_002_landing.sql.
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER, same pattern as the
-- earlier migration files.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- student_profiles — new nullable columns for previously "Coming Soon"
-- Personal / Academic / Research fields.
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'date_of_birth');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `date_of_birth` date DEFAULT NULL AFTER `location`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'gender');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `gender` varchar(30) DEFAULT NULL AFTER `date_of_birth`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'preferred_contact');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `preferred_contact` varchar(30) DEFAULT NULL AFTER `gender`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'academic_status');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `academic_status` varchar(30) DEFAULT NULL AFTER `preferred_contact`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'expected_graduation_date');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `expected_graduation_date` date DEFAULT NULL AFTER `academic_status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'research_methodologies');
SET @sql := IF(@col = 0,
  'ALTER TABLE `student_profiles` ADD COLUMN `research_methodologies` text DEFAULT NULL AFTER `research_statement`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- extracurricular_activities — normalized CRUD table (mirrors the
-- existing education / work_experience pattern).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `extracurricular_activities` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `organization` varchar(200) DEFAULT NULL,
  `role` varchar(150) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_extracurricular_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- profile_availability — one row per available weekday (real day/time
-- slots, not a fake grid). A unique constraint on (profile_id,
-- day_of_week) keeps the UI's "one slot per day" checkbox+time model
-- free of duplicates.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profile_availability` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_availability_profile_day` (`profile_id`,`day_of_week`),
  KEY `idx_availability_profile` (`profile_id`),
  KEY `idx_availability_day` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

-- ---------------------------------------------------------------------
-- Foreign keys (separate so table creation above never fails if a
-- constraint with the same name already exists from a partial re-run)
-- ---------------------------------------------------------------------
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_extracurricular_profile');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `extracurricular_activities` ADD CONSTRAINT `fk_extracurricular_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_availability_profile');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `profile_availability` ADD CONSTRAINT `fk_availability_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
