-- =====================================================================
-- UIU ResearchCollab — Migration 004
-- Hardens the Communities module now that student-facing community
-- creation exists (student/community-create.php). Adds a case-insensitive
-- duplicate-name guard and a couple of read-path indexes; the create/join/
-- post/comment logic itself needed no schema changes — `communities` and
-- `community_members` already had every column and the unique
-- (community_id, user_id) membership constraint required.
--
-- Run this AFTER database/schema.sql, database/migrations.sql,
-- database/migrations_002_landing.sql, and
-- database/migrations_003_profile_extended.sql.
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER, same pattern as the
-- earlier migration files.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Case-insensitive duplicate community names. `communities.name` already
-- uses the utf8mb4_general_ci collation, so a plain UNIQUE key on it is
-- naturally case-insensitive ("AI Club" and "ai club" collide).
-- ---------------------------------------------------------------------
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communities' AND INDEX_NAME = 'uq_community_name');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `communities` ADD UNIQUE KEY `uq_community_name` (`name`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Read-path indexes: communities.php filters every listing query on
-- (status, privacy), and community-details.php filters single lookups the
-- same way.
-- ---------------------------------------------------------------------
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communities' AND INDEX_NAME = 'idx_community_status');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `communities` ADD KEY `idx_community_status` (`status`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communities' AND INDEX_NAME = 'idx_community_privacy');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `communities` ADD KEY `idx_community_privacy` (`privacy`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
