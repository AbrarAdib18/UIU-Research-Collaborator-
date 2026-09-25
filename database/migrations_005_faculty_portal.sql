-- =====================================================================
-- UIU ResearchCollab — Migration 005
-- Faculty Portal + Advisor/Mentorship system.
--
-- Adds:
--   - Normalized faculty profile tables (mirrors the student_profiles
--     pattern: domains/skills/education/publications/projects/
--     availability/preferences/visibility).
--   - Advisor/mentorship workflow: advisor_requests, advisor_assignments,
--     advisor_feedback.
--   - Faculty<->student research connections: research_connections.
--   - Direct (1:1) chat: direct_conversations, direct_messages.
--   - Two additive column changes (faculty_profiles extra fields,
--     opportunity_applications.status gains 'Shortlisted').
--
-- Run this AFTER database/schema.sql, database/migrations.sql,
-- database/migrations_002_landing.sql, database/migrations_003_profile_extended.sql,
-- and database/migrations_004_communities.sql.
-- Safe to re-run: uses IF NOT EXISTS / guarded ALTER, same pattern as the
-- earlier migration files. No existing table is dropped or destructively
-- altered.
--
-- Note on "prevent duplicate pending request / active assignment":
-- MariaDB 10.4 (the target server per config/database.php) does not
-- support partial/filtered unique indexes, so a DB-level unique index
-- keyed only on status='pending' rows isn't expressible here. That
-- de-duplication is instead enforced at the application layer (a SELECT
-- check inside the same transaction as the INSERT) — see
-- faculty/mentorship-request-details.php and student/advisor-requests.php.
-- Ordinary (non-unique) indexes are still added below for the inbox/
-- dashboard queries that filter on these columns.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- faculty_research_domains — mirrors profile_research_domains
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_research_domains` (
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `domain_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`faculty_profile_id`,`domain_id`),
  KEY `idx_frd_domain` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_skills — mirrors profile_skills (no level; faculty list areas
-- of expertise, not proficiency levels)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_skills` (
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `skill_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`faculty_profile_id`,`skill_id`),
  KEY `idx_faculty_skill_skill` (`skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_education — mirrors `education`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_education` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `institution` varchar(200) NOT NULL,
  `degree` varchar(150) DEFAULT NULL,
  `field_of_study` varchar(150) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faculty_education_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_publications — mirrors `publications`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_publications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(300) NOT NULL,
  `authors` text DEFAULT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `publication_type` varchar(100) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `doi` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `status` enum('Published','Accepted','Under Review','In Preparation') DEFAULT 'Published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faculty_publication_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_projects — mirrors `projects`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_projects` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `funding_source` varchar(200) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Ongoing','Completed','Planned') DEFAULT 'Ongoing',
  `repository_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faculty_project_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_availability — mirrors profile_availability
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_availability` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_availability_day` (`faculty_profile_id`,`day_of_week`),
  KEY `idx_faculty_availability_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_preferences — mentorship/advisory capacity & preferences
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_preferences` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `accepting_mentees` tinyint(1) NOT NULL DEFAULT 1,
  `accepting_team_advisory` tinyint(1) NOT NULL DEFAULT 1,
  `accepting_paper_advisory` tinyint(1) NOT NULL DEFAULT 1,
  `max_active_mentees` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `preferred_project_types` varchar(150) DEFAULT NULL,
  `preferred_domains` varchar(255) DEFAULT NULL,
  `meeting_preference` enum('Online','In Person','Online + In Person') DEFAULT NULL,
  `availability_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_preferences_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- faculty_visibility — mirrors profile_visibility
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty_visibility` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_profile_id` int(10) UNSIGNED NOT NULL,
  `profile_visibility` enum('Public','Students Only','Private') DEFAULT 'Public',
  `contact_visibility` tinyint(1) DEFAULT 1,
  `research_visibility` tinyint(1) DEFAULT 1,
  `project_visibility` tinyint(1) DEFAULT 1,
  `publication_visibility` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_visibility_profile` (`faculty_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- advisor_requests
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advisor_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_type` enum('student','team') NOT NULL,
  `requested_by_user_id` int(10) UNSIGNED NOT NULL,
  `faculty_user_id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `opportunity_id` int(10) UNSIGNED DEFAULT NULL,
  `request_type` enum('research_advisor','paper_advisor','project_mentor','fydp_supervisor','research_mentor','technical_mentor') NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `research_summary` text DEFAULT NULL,
  `expected_commitment` varchar(150) DEFAULT NULL,
  `preferred_meeting_frequency` varchar(100) DEFAULT NULL,
  `status` enum('pending','accepted','declined','clarification_requested','cancelled','completed') NOT NULL DEFAULT 'pending',
  `faculty_response` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advisor_request_faculty_status` (`faculty_user_id`,`status`),
  KEY `idx_advisor_request_requester` (`requested_by_user_id`,`status`),
  KEY `idx_advisor_request_team` (`team_id`),
  KEY `idx_advisor_request_project` (`project_id`),
  KEY `idx_advisor_request_opportunity` (`opportunity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- advisor_assignments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advisor_assignments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `advisor_request_id` int(10) UNSIGNED DEFAULT NULL,
  `faculty_user_id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(10) UNSIGNED DEFAULT NULL,
  `team_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `opportunity_id` int(10) UNSIGNED DEFAULT NULL,
  `assignment_type` enum('research_advisor','paper_advisor','project_mentor','fydp_supervisor','research_mentor','technical_mentor') NOT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advisor_assignment_faculty_status` (`faculty_user_id`,`status`),
  KEY `idx_advisor_assignment_student` (`student_user_id`),
  KEY `idx_advisor_assignment_team` (`team_id`),
  KEY `idx_advisor_assignment_request` (`advisor_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- advisor_feedback
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advisor_feedback` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `faculty_user_id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(10) UNSIGNED DEFAULT NULL,
  `team_id` int(10) UNSIGNED DEFAULT NULL,
  `task_id` int(10) UNSIGNED DEFAULT NULL,
  `milestone_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `visibility` enum('student','team','private') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advisor_feedback_assignment` (`assignment_id`),
  KEY `idx_advisor_feedback_student` (`student_user_id`),
  KEY `idx_advisor_feedback_team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- research_connections — lightweight professional connection (student<->
-- faculty, or faculty<->faculty). pair_key gives us a fast, always-
-- computed "unordered pair" lookup; true duplicate-prevention on
-- pending/accepted rows is enforced in PHP inside a transaction (see
-- note at top of file).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `research_connections` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `request_message` text DEFAULT NULL,
  `status` enum('pending','accepted','declined','cancelled','blocked') NOT NULL DEFAULT 'pending',
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pair_key` varchar(41) GENERATED ALWAYS AS (CONCAT(LEAST(`requester_id`,`recipient_id`),'-',GREATEST(`requester_id`,`recipient_id`))) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_connection_pair` (`pair_key`),
  KEY `idx_connection_requester` (`requester_id`,`status`),
  KEY `idx_connection_recipient` (`recipient_id`,`status`),
  CONSTRAINT `chk_connection_not_self` CHECK (`requester_id` <> `recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- direct_conversations — always stored with user_one_id < user_two_id
-- (enforced in PHP at insert time) so the unique key below prevents
-- duplicate conversations for the same pair regardless of who started it.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `direct_conversations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_one_id` int(10) UNSIGNED NOT NULL,
  `user_two_id` int(10) UNSIGNED NOT NULL,
  `connection_id` int(10) UNSIGNED DEFAULT NULL,
  `advisor_assignment_id` int(10) UNSIGNED DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversation_pair` (`user_one_id`,`user_two_id`),
  KEY `idx_conversation_user_two` (`user_two_id`),
  KEY `idx_conversation_connection` (`connection_id`),
  KEY `idx_conversation_assignment` (`advisor_assignment_id`),
  CONSTRAINT `chk_conversation_not_self` CHECK (`user_one_id` <> `user_two_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- direct_messages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `direct_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_direct_message_conv` (`conversation_id`,`created_at`),
  KEY `idx_direct_message_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

-- ---------------------------------------------------------------------
-- AUTO_INCREMENT (guarded: only set starting point, never resets rows)
-- ---------------------------------------------------------------------
ALTER TABLE `faculty_education` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `faculty_publications` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `faculty_projects` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `faculty_availability` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `faculty_preferences` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `faculty_visibility` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `advisor_requests` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `advisor_assignments` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `advisor_feedback` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `research_connections` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `direct_conversations` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `direct_messages` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- ---------------------------------------------------------------------
-- Foreign keys (separate so table creation above never fails if a
-- constraint with the same name already exists from a partial re-run)
-- ---------------------------------------------------------------------

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_frd_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_research_domains` ADD CONSTRAINT `fk_frd_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_frd_domain');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_research_domains` ADD CONSTRAINT `fk_frd_domain` FOREIGN KEY (`domain_id`) REFERENCES `research_domains` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_skill_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_skills` ADD CONSTRAINT `fk_faculty_skill_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_skill_skill');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_skills` ADD CONSTRAINT `fk_faculty_skill_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_education_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_education` ADD CONSTRAINT `fk_faculty_education_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_publication_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_publications` ADD CONSTRAINT `fk_faculty_publication_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_project_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_projects` ADD CONSTRAINT `fk_faculty_project_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_availability_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_availability` ADD CONSTRAINT `fk_faculty_availability_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_preferences_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_preferences` ADD CONSTRAINT `fk_faculty_preferences_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_faculty_visibility_profile');
SET @sql := IF(@fk = 0, 'ALTER TABLE `faculty_visibility` ADD CONSTRAINT `fk_faculty_visibility_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_request_requester');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_requests` ADD CONSTRAINT `fk_advisor_request_requester` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_request_faculty');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_requests` ADD CONSTRAINT `fk_advisor_request_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_request_team');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_requests` ADD CONSTRAINT `fk_advisor_request_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_request_project');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_requests` ADD CONSTRAINT `fk_advisor_request_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_request_opportunity');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_requests` ADD CONSTRAINT `fk_advisor_request_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_request');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_request` FOREIGN KEY (`advisor_request_id`) REFERENCES `advisor_requests` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_faculty');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_student');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_team');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_project');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_assignment_opportunity');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_assignments` ADD CONSTRAINT `fk_advisor_assignment_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_assignment');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `advisor_assignments` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_faculty');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_student');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_team');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_task');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_task` FOREIGN KEY (`task_id`) REFERENCES `team_tasks` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advisor_feedback_milestone');
SET @sql := IF(@fk = 0, 'ALTER TABLE `advisor_feedback` ADD CONSTRAINT `fk_advisor_feedback_milestone` FOREIGN KEY (`milestone_id`) REFERENCES `team_milestones` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_connection_requester');
SET @sql := IF(@fk = 0, 'ALTER TABLE `research_connections` ADD CONSTRAINT `fk_connection_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_connection_recipient');
SET @sql := IF(@fk = 0, 'ALTER TABLE `research_connections` ADD CONSTRAINT `fk_connection_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_conversation_user_one');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_conversations` ADD CONSTRAINT `fk_conversation_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_conversation_user_two');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_conversations` ADD CONSTRAINT `fk_conversation_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_conversation_connection');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_conversations` ADD CONSTRAINT `fk_conversation_connection` FOREIGN KEY (`connection_id`) REFERENCES `research_connections` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_conversation_assignment');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_conversations` ADD CONSTRAINT `fk_conversation_assignment` FOREIGN KEY (`advisor_assignment_id`) REFERENCES `advisor_assignments` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_direct_message_conversation');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_messages` ADD CONSTRAINT `fk_direct_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `direct_conversations` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_direct_message_sender');
SET @sql := IF(@fk = 0, 'ALTER TABLE `direct_messages` ADD CONSTRAINT `fk_direct_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- faculty_profiles — additive columns
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculty_profiles' AND COLUMN_NAME = 'research_statement');
SET @sql := IF(@col = 0, 'ALTER TABLE `faculty_profiles` ADD COLUMN `research_statement` text DEFAULT NULL AFTER `bio`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculty_profiles' AND COLUMN_NAME = 'portfolio_url');
SET @sql := IF(@col = 0, 'ALTER TABLE `faculty_profiles` ADD COLUMN `portfolio_url` varchar(255) DEFAULT NULL AFTER `researchgate_url`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculty_profiles' AND COLUMN_NAME = 'cover_photo');
SET @sql := IF(@col = 0, 'ALTER TABLE `faculty_profiles` ADD COLUMN `cover_photo` varchar(255) DEFAULT NULL AFTER `profile_photo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- opportunity_applications.status — widen ENUM to add 'Shortlisted'
-- (existing values Pending/Accepted/Rejected/Withdrawn are untouched, so
-- every existing comparison/switch in the student portal keeps working).
-- ---------------------------------------------------------------------
SET @has_shortlisted := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunity_applications'
    AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%Shortlisted%'
);
SET @sql := IF(@has_shortlisted = 0,
  "ALTER TABLE `opportunity_applications` MODIFY `status` enum('Pending','Shortlisted','Accepted','Rejected','Withdrawn') DEFAULT 'Pending'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- opportunity_applications.review_notes — internal notes visible only to
-- the faculty/admin reviewing the application, never shown to the student.
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunity_applications' AND COLUMN_NAME = 'review_notes');
SET @sql := IF(@col = 0, 'ALTER TABLE `opportunity_applications` ADD COLUMN `review_notes` text DEFAULT NULL AFTER `message`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
