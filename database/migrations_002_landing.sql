-- =====================================================================
-- UIU ResearchCollab — Migration 002
-- Adds support for the public landing-page "Forgot Password" flow and
-- the Contact Us form.
--
-- Run this AFTER database/schema.sql and database/migrations.sql.
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER, same pattern as
-- migrations.sql.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- password_reset_tokens
-- Backs the public "Forgot Password?" link on login.php. Tokens are
-- single-use, expire after 1 hour, and are stored hashed (never the
-- raw token) so a leaked database row can't be used to reset a password.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token_hash` (`token_hash`),
  KEY `idx_reset_user` (`user_id`),
  KEY `idx_reset_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- contact_messages
-- Backs the public Contact Us form (contact.php). No outbound email is
-- configured in this environment, so messages are simply stored here
-- for later follow-up.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

-- ---------------------------------------------------------------------
-- Foreign keys (separate so table creation above never fails if a
-- constraint with the same name already exists from a partial re-run)
-- ---------------------------------------------------------------------
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_reset_token_user');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `password_reset_tokens` ADD CONSTRAINT `fk_reset_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
