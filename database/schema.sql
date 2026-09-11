-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2026 at 08:28 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uiu_researchcollab`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `organization` varchar(200) DEFAULT NULL,
  `achievement_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `activity_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `issuing_organization` varchar(200) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `credential_id` varchar(150) DEFAULT NULL,
  `credential_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communities`
--

CREATE TABLE `communities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `domain_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `privacy` enum('Public','Private') DEFAULT 'Public',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_comments`
--

CREATE TABLE `community_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_members`
--

CREATE TABLE `community_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `community_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` enum('Admin','Moderator','Member') DEFAULT 'Member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_posts`
--

CREATE TABLE `community_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `community_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(250) DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `education`
--

CREATE TABLE `education` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `institution` varchar(200) NOT NULL,
  `degree` varchar(150) DEFAULT NULL,
  `field_of_study` varchar(150) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_profiles`
--

CREATE TABLE `faculty_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` varchar(50) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `office_location` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `google_scholar_url` varchar(255) DEFAULT NULL,
  `researchgate_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `name`, `created_at`) VALUES
(1, 'Bengali', '2026-09-10 18:21:46'),
(2, 'English', '2026-09-10 18:21:46'),
(3, 'Hindi', '2026-09-10 18:21:46'),
(4, 'Arabic', '2026-09-10 18:21:46'),
(5, 'French', '2026-09-10 18:21:46'),
(6, 'German', '2026-09-10 18:21:46'),
(7, 'Spanish', '2026-09-10 18:21:46'),
(8, 'Japanese', '2026-09-10 18:21:46'),
(9, 'Chinese', '2026-09-10 18:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_applications`
--

CREATE TABLE `opportunity_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected','Withdrawn') DEFAULT 'Pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_domains`
--

CREATE TABLE `opportunity_domains` (
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `domain_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_languages`
--

CREATE TABLE `profile_languages` (
  `profile_id` int(10) UNSIGNED NOT NULL,
  `language_id` int(10) UNSIGNED NOT NULL,
  `proficiency` enum('Basic','Conversational','Intermediate','Advanced','Fluent','Native') DEFAULT 'Intermediate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_research_domains`
--

CREATE TABLE `profile_research_domains` (
  `profile_id` int(10) UNSIGNED NOT NULL,
  `domain_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_skills`
--

CREATE TABLE `profile_skills` (
  `profile_id` int(10) UNSIGNED NOT NULL,
  `skill_id` int(10) UNSIGNED NOT NULL,
  `level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_visibility`
--

CREATE TABLE `profile_visibility` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `profile_visibility` enum('Public','Students Only','Private') DEFAULT 'Students Only',
  `contact_visibility` tinyint(1) DEFAULT 1,
  `research_visibility` tinyint(1) DEFAULT 1,
  `project_visibility` tinyint(1) DEFAULT 1,
  `publication_visibility` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `repository_url` varchar(255) DEFAULT NULL,
  `demo_url` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `publications`
--

CREATE TABLE `publications` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(300) NOT NULL,
  `authors` text DEFAULT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `publication_type` varchar(100) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `doi` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `status` enum('Published','Accepted','Under Review','In Preparation') DEFAULT 'In Preparation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_domains`
--

CREATE TABLE `research_domains` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_domains`
--

INSERT INTO `research_domains` (`id`, `name`, `description`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Artificial Intelligence', 'Research in artificial intelligence, machine learning, deep learning, and intelligent systems.', 'bi bi-cpu', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(2, 'Big Data Analytics', 'Research involving large-scale data processing, data mining, analytics, and distributed data systems.', 'bi bi-database-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(3, 'Bioinformatics', 'Application of computational methods, data analysis, and algorithms to biological and biomedical data.', 'bi bi-bezier2', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(4, 'Blockchain', 'Research in blockchain technology, distributed ledgers, smart contracts, and decentralized applications.', 'bi bi-link-45deg', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(5, 'Computer Vision', 'Research in image processing, image analysis, object detection, recognition, and visual understanding.', 'bi bi-eye-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(6, 'Cloud Computing', 'Research in cloud infrastructure, distributed computing, cloud platforms, virtualization, and cloud services.', 'bi bi-cloud-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(7, 'Cyber Security', 'Research in information security, network security, privacy, threat detection, and cyber defense.', 'bi bi-shield-lock-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(8, 'Data Science', 'Research in data analysis, statistics, machine learning, predictive analytics, and data-driven decision making.', 'bi bi-bar-chart-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(9, 'Healthcare AI', 'Application of artificial intelligence and machine learning techniques to healthcare and biomedical problems.', 'bi bi-heart-pulse-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(10, 'Human Computer Interaction', 'Research on interaction between humans and computer systems, usability, user experience, and interface design.', 'bi bi-mouse2', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(11, 'Internet of Things', 'Research in connected devices, sensors, smart systems, IoT architectures, and Internet-connected technologies.', 'bi bi-wifi', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(12, 'Mobile Computing', 'Research in mobile applications, mobile devices, wireless technologies, and mobile computing systems.', 'bi bi-phone-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(13, 'Natural Language Processing', 'Research in natural language understanding, text processing, speech, language models, and computational linguistics.', 'bi bi-chat-square-text-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(14, 'Networking', 'Research in computer networks, network architecture, communication protocols, wireless networks, and network performance.', 'bi bi-diagram-3-fill', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(15, 'Robotics', 'Research in robots, autonomous systems, robotic perception, control, and intelligent machines.', 'bi bi-robot', '2026-09-10 18:17:23', '2026-09-10 18:17:23'),
(16, 'Software Engineering', 'Research in software development, software architecture, testing, maintenance, quality, and engineering practices.', 'bi bi-code-slash', '2026-09-10 18:17:23', '2026-09-10 18:17:23');

-- --------------------------------------------------------

--
-- Table structure for table `research_opportunities`
--

CREATE TABLE `research_opportunities` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(250) NOT NULL,
  `description` text DEFAULT NULL,
  `problem_statement` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `team_size_min` int(10) UNSIGNED DEFAULT 2,
  `team_size_max` int(10) UNSIGNED DEFAULT 6,
  `deadline` date DEFAULT NULL,
  `status` enum('Open','Closed','Draft','Completed') DEFAULT 'Open',
  `visibility` enum('Public','Community','Private') DEFAULT 'Public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_preferences`
--

CREATE TABLE `research_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `looking_for_team` tinyint(1) DEFAULT 1,
  `preferred_team_size_min` int(10) UNSIGNED DEFAULT 2,
  `preferred_team_size_max` int(10) UNSIGNED DEFAULT 4,
  `availability_hours_per_week` int(10) UNSIGNED DEFAULT NULL,
  `collaboration_preference` enum('Online','In Person','Online + In Person') DEFAULT NULL,
  `project_type_preference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_teams`
--

CREATE TABLE `research_teams` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED DEFAULT NULL,
  `research_domain_id` int(10) UNSIGNED DEFAULT NULL,
  `team_size_limit` int(10) UNSIGNED DEFAULT 6,
  `status` enum('Forming','Active','Completed','Archived') DEFAULT 'Forming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_opportunities`
--

CREATE TABLE `saved_opportunities` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `cgpa` decimal(3,2) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `portfolio_url` varchar(255) DEFAULT NULL,
  `research_statement` text DEFAULT NULL,
  `profile_completion` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_files`
--

CREATE TABLE `team_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_invitations`
--

CREATE TABLE `team_invitations` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `invited_by` int(10) UNSIGNED NOT NULL,
  `invited_user_id` int(10) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` enum('Leader','Member') DEFAULT 'Member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Left','Removed') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_messages`
--

CREATE TABLE `team_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_milestones`
--

CREATE TABLE `team_milestones` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Delayed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_requests`
--

CREATE TABLE `team_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_tasks`
--

CREATE TABLE `team_tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `team_id` int(10) UNSIGNED NOT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('To Do','In Progress','Completed','Blocked') DEFAULT 'To Do',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','faculty','admin') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_experience`
--

CREATE TABLE `work_experience` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `organization` varchar(200) NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Internship','Research Assistant','Volunteer','Freelance') DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_achievement_profile` (`profile_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`,`created_at`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_certification_profile` (`profile_id`);

--
-- Indexes for table `communities`
--
ALTER TABLE `communities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_community_domain` (`domain_id`),
  ADD KEY `fk_community_creator` (`created_by`);

--
-- Indexes for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comment_post` (`post_id`),
  ADD KEY `fk_comment_user` (`user_id`);

--
-- Indexes for table `community_members`
--
ALTER TABLE `community_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_community_member` (`community_id`,`user_id`),
  ADD KEY `fk_community_member_user` (`user_id`);

--
-- Indexes for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_community_post_community` (`community_id`),
  ADD KEY `fk_community_post_user` (`user_id`);

--
-- Indexes for table `education`
--
ALTER TABLE `education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_education_profile` (`profile_id`);

--
-- Indexes for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_user` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`opportunity_id`,`user_id`),
  ADD KEY `fk_application_user` (`user_id`);

--
-- Indexes for table `opportunity_domains`
--
ALTER TABLE `opportunity_domains`
  ADD PRIMARY KEY (`opportunity_id`,`domain_id`),
  ADD KEY `fk_opportunity_domain_domain` (`domain_id`);

--
-- Indexes for table `profile_languages`
--
ALTER TABLE `profile_languages`
  ADD PRIMARY KEY (`profile_id`,`language_id`),
  ADD KEY `fk_profile_language_language` (`language_id`);

--
-- Indexes for table `profile_research_domains`
--
ALTER TABLE `profile_research_domains`
  ADD PRIMARY KEY (`profile_id`,`domain_id`),
  ADD KEY `fk_prd_domain` (`domain_id`);

--
-- Indexes for table `profile_skills`
--
ALTER TABLE `profile_skills`
  ADD PRIMARY KEY (`profile_id`,`skill_id`),
  ADD KEY `fk_profile_skill_skill` (`skill_id`);

--
-- Indexes for table `profile_visibility`
--
ALTER TABLE `profile_visibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_project_profile` (`profile_id`);

--
-- Indexes for table `publications`
--
ALTER TABLE `publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_publication_profile` (`profile_id`);

--
-- Indexes for table `research_domains`
--
ALTER TABLE `research_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `research_opportunities`
--
ALTER TABLE `research_opportunities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_opportunity_status` (`status`),
  ADD KEY `idx_opportunity_deadline` (`deadline`),
  ADD KEY `fk_opportunity_creator` (`created_by`);

--
-- Indexes for table `research_preferences`
--
ALTER TABLE `research_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`);

--
-- Indexes for table `research_teams`
--
ALTER TABLE `research_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_team_creator` (`created_by`),
  ADD KEY `fk_team_opportunity` (`opportunity_id`),
  ADD KEY `fk_team_domain` (`research_domain_id`);

--
-- Indexes for table `saved_opportunities`
--
ALTER TABLE `saved_opportunities`
  ADD PRIMARY KEY (`user_id`,`opportunity_id`),
  ADD KEY `fk_saved_opportunity_opportunity` (`opportunity_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `team_files`
--
ALTER TABLE `team_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_team_file_team` (`team_id`),
  ADD KEY `fk_team_file_user` (`uploaded_by`);

--
-- Indexes for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_invitation_team` (`team_id`),
  ADD KEY `fk_invitation_sender` (`invited_by`),
  ADD KEY `fk_invitation_user` (`invited_user_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_member` (`team_id`,`user_id`),
  ADD KEY `fk_team_member_user` (`user_id`);

--
-- Indexes for table `team_messages`
--
ALTER TABLE `team_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_team_message` (`team_id`,`created_at`),
  ADD KEY `fk_team_message_sender` (`sender_id`);

--
-- Indexes for table `team_milestones`
--
ALTER TABLE `team_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_milestone_team` (`team_id`);

--
-- Indexes for table `team_requests`
--
ALTER TABLE `team_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_request` (`team_id`,`user_id`),
  ADD KEY `fk_team_request_user` (`user_id`);

--
-- Indexes for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_task_team` (`team_id`),
  ADD KEY `fk_task_assignee` (`assigned_to`),
  ADD KEY `fk_task_creator` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_work_profile` (`profile_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communities`
--
ALTER TABLE `communities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_comments`
--
ALTER TABLE `community_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_members`
--
ALTER TABLE `community_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_posts`
--
ALTER TABLE `community_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `education`
--
ALTER TABLE `education`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profile_visibility`
--
ALTER TABLE `profile_visibility`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `publications`
--
ALTER TABLE `publications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_domains`
--
ALTER TABLE `research_domains`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `research_opportunities`
--
ALTER TABLE `research_opportunities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_preferences`
--
ALTER TABLE `research_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_teams`
--
ALTER TABLE `research_teams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_files`
--
ALTER TABLE `team_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_invitations`
--
ALTER TABLE `team_invitations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_messages`
--
ALTER TABLE `team_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_milestones`
--
ALTER TABLE `team_milestones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_requests`
--
ALTER TABLE `team_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_tasks`
--
ALTER TABLE `team_tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_experience`
--
ALTER TABLE `work_experience`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `achievements`
--
ALTER TABLE `achievements`
  ADD CONSTRAINT `fk_achievement_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `fk_certification_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communities`
--
ALTER TABLE `communities`
  ADD CONSTRAINT `fk_community_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_community_domain` FOREIGN KEY (`domain_id`) REFERENCES `research_domains` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_members`
--
ALTER TABLE `community_members`
  ADD CONSTRAINT `fk_community_member_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_community_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD CONSTRAINT `fk_community_post_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_community_post_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `education`
--
ALTER TABLE `education`
  ADD CONSTRAINT `fk_education_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  ADD CONSTRAINT `fk_faculty_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  ADD CONSTRAINT `fk_application_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opportunity_domains`
--
ALTER TABLE `opportunity_domains`
  ADD CONSTRAINT `fk_opportunity_domain_domain` FOREIGN KEY (`domain_id`) REFERENCES `research_domains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_opportunity_domain_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profile_languages`
--
ALTER TABLE `profile_languages`
  ADD CONSTRAINT `fk_profile_language_language` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_profile_language_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profile_research_domains`
--
ALTER TABLE `profile_research_domains`
  ADD CONSTRAINT `fk_prd_domain` FOREIGN KEY (`domain_id`) REFERENCES `research_domains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prd_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profile_skills`
--
ALTER TABLE `profile_skills`
  ADD CONSTRAINT `fk_profile_skill_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_profile_skill_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profile_visibility`
--
ALTER TABLE `profile_visibility`
  ADD CONSTRAINT `fk_visibility_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_project_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `publications`
--
ALTER TABLE `publications`
  ADD CONSTRAINT `fk_publication_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_opportunities`
--
ALTER TABLE `research_opportunities`
  ADD CONSTRAINT `fk_opportunity_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_preferences`
--
ALTER TABLE `research_preferences`
  ADD CONSTRAINT `fk_preference_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_teams`
--
ALTER TABLE `research_teams`
  ADD CONSTRAINT `fk_team_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_domain` FOREIGN KEY (`research_domain_id`) REFERENCES `research_domains` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_team_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `saved_opportunities`
--
ALTER TABLE `saved_opportunities`
  ADD CONSTRAINT `fk_saved_opportunity_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `research_opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_opportunity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `fk_student_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_files`
--
ALTER TABLE `team_files`
  ADD CONSTRAINT `fk_team_file_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_file_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD CONSTRAINT `fk_invitation_sender` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invitation_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invitation_user` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `fk_team_member_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_messages`
--
ALTER TABLE `team_messages`
  ADD CONSTRAINT `fk_team_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_message_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_milestones`
--
ALTER TABLE `team_milestones`
  ADD CONSTRAINT `fk_milestone_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_requests`
--
ALTER TABLE `team_requests`
  ADD CONSTRAINT `fk_team_request_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD CONSTRAINT `fk_task_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_task_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_task_team` FOREIGN KEY (`team_id`) REFERENCES `research_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD CONSTRAINT `fk_work_profile` FOREIGN KEY (`profile_id`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
