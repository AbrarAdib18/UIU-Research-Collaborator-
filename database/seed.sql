-- =====================================================================
-- UIU ResearchCollab — Demo / Seed Data
-- =====================================================================
-- What this is:
--   Realistic demo data for local development and grading demos of the
--   UIU ResearchCollab student portal. No Lorem Ipsum — names, projects,
--   opportunities, teams, and communities are written to look like real
--   UIU CSE activity so every screen has something meaningful to show.
--
-- Run order:
--   1. database/schema.sql        (base schema + research_domains/languages seed)
--   2. database/migrations.sql    (research_resources, saved_resources, cv_path)
--   3. database/seed.sql          (this file)
--
-- Assumption:
--   This file uses EXPLICIT primary-key IDs everywhere (not just for
--   users/student_profiles/research_domains) so every foreign key below
--   is unambiguous and easy to verify by eye. It still assumes a FRESH
--   import (empty tables) — it does not attempt to coexist with any
--   pre-existing rows in these tables. FOREIGN_KEY_CHECKS is disabled
--   for the duration of the import so statement order does not have to
--   be FK-perfect, but tables are still grouped in a sensible dependency
--   order (users -> profiles -> links -> opportunities/teams/communities
--   -> their children).
--
-- Official demo credentials (password is the SAME for every seeded
-- account, including the 8 extra students and 1 extra faculty below):
--   Student : student@example.com / Password123!   (user id 1, student_profiles id 1)
--   Faculty : faculty@example.com / Password123!   (user id 2, faculty_profiles id 1)
--   Admin   : admin@example.com   / Password123!   (user id 3)
--
-- THIS FILE IS FOR LOCAL DEVELOPMENT ONLY. Do not import into any
-- shared/production database — passwords, emails and content are fake
-- but the bcrypt hash below is public/well-known once you know it maps
-- to "Password123!".
-- =====================================================================

SET FOREIGN_KEY_CHECKS=0;
START TRANSACTION;

-- --------------------------------------------------------
--
-- Seed data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `category`) VALUES
(1, 'Python', 'Programming Language'),
(2, 'Java', 'Programming Language'),
(3, 'C++', 'Programming Language'),
(4, 'JavaScript', 'Programming Language'),
(5, 'PHP', 'Programming Language'),
(6, 'React', 'Framework'),
(7, 'Node.js', 'Framework'),
(8, 'Django', 'Framework'),
(9, 'Laravel', 'Framework'),
(10, 'Flutter', 'Framework'),
(11, 'TensorFlow', 'Data Science'),
(12, 'PyTorch', 'Data Science'),
(13, 'Scikit-learn', 'Data Science'),
(14, 'Pandas', 'Data Science'),
(15, 'NumPy', 'Data Science'),
(16, 'OpenCV', 'Data Science'),
(17, 'MySQL', 'Tools'),
(18, 'MongoDB', 'Tools'),
(19, 'Docker', 'Tools'),
(20, 'Git', 'Tools'),
(21, 'AWS', 'Tools'),
(22, 'Figma', 'Tools'),
(23, 'R', 'Programming Language'),
(24, 'Linux', 'Tools'),
(25, 'Arduino/Embedded Systems', 'Tools');

-- --------------------------------------------------------
--
-- Seed data for table `users`
--
-- DEMO CREDENTIALS (all seeded accounts share one password):
--   student@example.com / faculty@example.com / admin@example.com
--   Password: Password123!
--   Every `password` value below is the SAME bcrypt hash of "Password123!",
--   verified with Python's `bcrypt.checkpw()` against this exact hash
--   string before this file was finalized (PHP's password_verify() reads
--   $2a$/$2b$/$2y$ bcrypt hashes interchangeably, so this hash — generated
--   as $2b$ — verifies correctly against PHP's own $2y$-prefixed output).
--   Row 1 (student@example.com) is the primary demo student account used
--   throughout the Testing Checklist in README.md.
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Student Demo', 'student@example.com', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-06-01 09:00:00', '2026-09-10 08:00:00', '2026-06-01 09:00:00', '2026-06-01 09:00:00'),
(2, 'Faculty Demo', 'faculty@example.com', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'faculty', 'active', '2026-06-01 09:05:00', '2026-09-09 09:00:00', '2026-06-01 09:05:00', '2026-06-01 09:05:00'),
(3, 'Admin Demo', 'admin@example.com', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'admin', 'active', '2026-06-01 09:10:00', '2026-09-08 07:30:00', '2026-06-01 09:10:00', '2026-06-01 09:10:00'),
(4, 'Tanvir Ahmed', 'tanvir.ahmed@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-06-05 10:00:00', '2026-09-07 19:20:00', '2026-06-05 10:00:00', '2026-06-05 10:00:00'),
(5, 'Farhana Islam', 'farhana.islam@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-06-10 11:15:00', '2026-09-06 20:05:00', '2026-06-10 11:15:00', '2026-06-10 11:15:00'),
(6, 'Rakibul Hasan', 'rakibul.hasan@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-06-15 09:30:00', '2026-09-05 12:40:00', '2026-06-15 09:30:00', '2026-06-15 09:30:00'),
(7, 'Nusrat Jahan', 'nusrat.jahan@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-06-20 14:00:00', '2026-09-04 15:10:00', '2026-06-20 14:00:00', '2026-06-20 14:00:00'),
(8, 'Sadia Rahman', 'sadia.rahman@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-07-01 08:45:00', '2026-09-03 21:00:00', '2026-07-01 08:45:00', '2026-07-01 08:45:00'),
(9, 'Mehedi Hasan', 'mehedi.hasan@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-07-10 16:20:00', '2026-09-02 10:15:00', '2026-07-10 16:20:00', '2026-07-10 16:20:00'),
(10, 'Ayesha Siddiqua', 'ayesha.siddiqua@bscse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'student', 'active', '2026-07-20 12:00:00', '2026-09-01 18:50:00', '2026-07-20 12:00:00', '2026-07-20 12:00:00'),
(11, 'Dr. Kazi Shibli Zaman', 'kazi.zaman@cse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'faculty', 'active', '2026-08-01 10:00:00', '2026-09-09 13:00:00', '2026-08-01 10:00:00', '2026-08-01 10:00:00');

-- --------------------------------------------------------
--
-- Seed data for table `student_profiles`
-- profile 1 -> user 1 (Student Demo, the official demo account)
--

INSERT INTO `student_profiles` (`id`, `user_id`, `student_id`, `department`, `program`, `semester`, `cgpa`, `bio`, `profile_photo`, `cover_photo`, `cv_path`, `phone`, `location`, `linkedin_url`, `github_url`, `portfolio_url`, `research_statement`, `profile_completion`) VALUES
(1, 1, '011231001', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '7th Trimester', 3.75, 'CSE undergraduate at UIU passionate about NLP and applying AI to solve real-world Bengali language problems.', NULL, NULL, NULL, '+8801711223344', 'Dhaka, Bangladesh', 'https://linkedin.com/in/student-demo-uiu', 'https://github.com/student-demo', NULL, 'Interested in applying deep learning and NLP techniques to low-resource Bengali language problems.', 85),
(2, 4, '011201045', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '10th Trimester', 3.85, 'Aspiring cybersecurity researcher with hands-on experience in penetration testing and network defense.', NULL, NULL, NULL, '+8801812233445', 'Dhaka, Bangladesh', 'https://linkedin.com/in/tanvir-ahmed-cse', 'https://github.com/tanvir-ahmed', NULL, 'Focused on network security, intrusion detection, and building resilient distributed systems.', 78),
(3, 5, '011201078', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '8th Trimester', 3.68, 'Data-driven CSE student interested in machine learning, predictive analytics, and applied statistics.', NULL, NULL, NULL, '+8801912233445', 'Dhaka, Bangladesh', 'https://linkedin.com/in/farhana-islam-cse', 'https://github.com/farhana-islam', NULL, 'Exploring machine learning applications in predictive analytics and educational data mining.', 82),
(4, 6, '011201112', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '9th Trimester', 3.42, 'Robotics enthusiast building embedded systems and autonomous devices as part of coursework and personal projects.', NULL, NULL, NULL, '+8801611223344', 'Dhaka, Bangladesh', 'https://linkedin.com/in/rakibul-hasan-cse', 'https://github.com/rakibul-hasan', NULL, 'Building autonomous robotic systems and embedded IoT devices for real-world automation problems.', 70),
(5, 7, '012211034', 'Electrical and Electronic Engineering', 'Bachelor of Science in EEE', '6th Trimester', 3.55, 'EEE student exploring the intersection of hardware design, IoT, and wireless networking.', NULL, NULL, NULL, '+8801511223344', 'Dhaka, Bangladesh', 'https://linkedin.com/in/nusrat-jahan-eee', 'https://github.com/nusrat-jahan', NULL, 'Investigating low-power IoT hardware and wireless sensor networks for smart campus applications.', 60),
(6, 8, '011231056', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '5th Trimester', 3.91, 'CSE student building Bengali-language AI tools, from chatbots to sentiment analysis systems.', NULL, NULL, NULL, '+8801311223344', 'Dhaka, Bangladesh', 'https://linkedin.com/in/sadia-rahman-cse', 'https://github.com/sadia-rahman', NULL, 'Passionate about generative AI and conversational systems for the Bengali language.', 88),
(7, 9, '021241009', 'Business Administration', 'Bachelor of Business Administration', '4th Trimester', 3.30, 'BBA student with a growing interest in business analytics and data-driven decision making.', NULL, NULL, NULL, '+8801711998877', 'Dhaka, Bangladesh', 'https://linkedin.com/in/mehedi-hasan-bba', NULL, NULL, 'Interested in applying data analytics to business decision-making and financial forecasting.', 45),
(8, 10, '012251023', 'Computer Science and Engineering', 'Bachelor of Science in CSE', '3rd Trimester', 3.20, 'First-year-research CSE student exploring computer vision through coursework and personal projects.', NULL, NULL, NULL, '+8801611998877', 'Dhaka, Bangladesh', 'https://linkedin.com/in/ayesha-siddiqua-cse', 'https://github.com/ayesha-siddiqua', NULL, 'New to research, eager to explore computer vision applications in healthcare and safety systems.', 50);

-- --------------------------------------------------------
--
-- Seed data for table `faculty_profiles`
--

INSERT INTO `faculty_profiles` (`id`, `user_id`, `faculty_id`, `department`, `designation`, `specialization`, `office_location`, `phone`, `bio`, `linkedin_url`, `google_scholar_url`, `researchgate_url`) VALUES
(1, 2, 'FAC-1001', 'Computer Science and Engineering', 'Associate Professor', 'Artificial Intelligence, Natural Language Processing', 'Room 405, UIU CSE Building', '+8801711000001', 'Faculty Demo is an Associate Professor at UIU specializing in AI and NLP, currently advising several undergraduate research teams.', 'https://linkedin.com/in/faculty-demo-uiu', 'https://scholar.google.com/citations?user=facultydemo', 'https://researchgate.net/profile/Faculty-Demo'),
(2, 11, 'FAC-1002', 'Computer Science and Engineering', 'Assistant Professor', 'Blockchain, Cyber Security, Distributed Systems', 'Room 412, UIU CSE Building', '+8801711000002', 'Dr. Kazi Shibli Zaman researches blockchain security and distributed systems, and supervises the BlockSecure and CyberGuard research groups.', 'https://linkedin.com/in/kazi-shibli-zaman', 'https://scholar.google.com/citations?user=kszaman', 'https://researchgate.net/profile/Kazi-Shibli-Zaman');

-- --------------------------------------------------------
--
-- Seed data for table `profile_research_domains`
-- Deliberate overlap: AI(1) shared by profiles 1,3,6,8; Data Science(8) by 1,3,7;
-- Cyber Security(7)+Networking(14) shared by 2,5; IoT(11) shared by 4,5.
--

INSERT INTO `profile_research_domains` (`profile_id`, `domain_id`) VALUES
(1, 1), (1, 8), (1, 13),
(2, 7), (2, 14),
(3, 1), (3, 8),
(4, 11), (4, 15),
(5, 7), (5, 14), (5, 11),
(6, 13), (6, 1),
(7, 8), (7, 2),
(8, 5), (8, 1);

-- --------------------------------------------------------
--
-- Seed data for table `profile_skills`
-- Deliberate overlap on Python, Git, TensorFlow, Pandas for match scoring.
--

INSERT INTO `profile_skills` (`profile_id`, `skill_id`, `level`) VALUES
(1, 1, 'Advanced'), (1, 11, 'Intermediate'), (1, 12, 'Intermediate'), (1, 17, 'Advanced'), (1, 20, 'Expert'),
(2, 1, 'Intermediate'), (2, 24, 'Advanced'), (2, 17, 'Intermediate'), (2, 19, 'Beginner'), (2, 20, 'Advanced'),
(3, 1, 'Advanced'), (3, 14, 'Advanced'), (3, 15, 'Intermediate'), (3, 13, 'Intermediate'), (3, 20, 'Intermediate'),
(4, 3, 'Advanced'), (4, 25, 'Expert'), (4, 1, 'Intermediate'), (4, 20, 'Beginner'),
(5, 3, 'Intermediate'), (5, 25, 'Advanced'), (5, 24, 'Intermediate'), (5, 20, 'Beginner'),
(6, 1, 'Advanced'), (6, 11, 'Advanced'), (6, 12, 'Beginner'), (6, 14, 'Intermediate'), (6, 20, 'Intermediate'),
(7, 23, 'Intermediate'), (7, 14, 'Intermediate'), (7, 17, 'Beginner'), (7, 22, 'Intermediate'),
(8, 1, 'Intermediate'), (8, 16, 'Advanced'), (8, 11, 'Beginner'), (8, 20, 'Intermediate');

-- --------------------------------------------------------
--
-- Seed data for table `education`
--

INSERT INTO `education` (`id`, `profile_id`, `institution`, `degree`, `field_of_study`, `start_date`, `end_date`, `description`) VALUES
(1, 1, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2023-01-01', NULL, 'Currently pursuing BSc in CSE with focus on AI and NLP.'),
(2, 1, 'Notre Dame College, Dhaka', 'Higher Secondary Certificate', 'Science', '2019-07-01', '2021-06-30', 'Completed HSC with a focus on Physics, Chemistry and Higher Math.'),
(3, 2, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2021-01-01', NULL, 'Focusing on network security and penetration testing coursework.'),
(4, 3, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2022-01-01', NULL, 'Coursework emphasis on machine learning and applied statistics.'),
(5, 3, 'Viqarunnisa Noon College', 'Higher Secondary Certificate', 'Science', '2018-07-01', '2020-06-30', 'Completed HSC with focus on Science group subjects.'),
(6, 4, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2021-07-01', NULL, 'Specializing in embedded systems and robotics.'),
(7, 5, 'United International University', 'Bachelor of Science in Electrical and Electronic Engineering', 'Electrical and Electronic Engineering', '2023-07-01', NULL, 'Interested in IoT hardware design and wireless networking.'),
(8, 5, 'Dhaka Residential Model College', 'Higher Secondary Certificate', 'Science', '2019-01-01', '2021-01-01', 'Completed HSC with focus on Science group subjects.'),
(9, 6, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2024-01-01', NULL, 'Exploring NLP and generative AI as part of coursework projects.'),
(10, 7, 'United International University', 'Bachelor of Business Administration', 'Business Analytics', '2024-07-01', NULL, 'Combining business analytics coursework with data science electives.'),
(11, 7, 'Ideal College, Dhaka', 'Higher Secondary Certificate', 'Business Studies', '2020-07-01', '2022-06-30', 'Completed HSC in the Business Studies group.'),
(12, 8, 'United International University', 'Bachelor of Science in Computer Science and Engineering', 'Computer Science and Engineering', '2025-01-01', NULL, 'New to research, exploring computer vision through coursework projects.');

-- --------------------------------------------------------
--
-- Seed data for table `work_experience`
--

INSERT INTO `work_experience` (`id`, `profile_id`, `organization`, `position`, `employment_type`, `start_date`, `end_date`, `is_current`, `description`) VALUES
(1, 1, 'UIU AI & Robotics Lab', 'Undergraduate Research Assistant', 'Research Assistant', '2025-06-01', NULL, 1, 'Assisting faculty with NLP research on Bengali language models.'),
(2, 2, 'TechShield Bangladesh', 'Cyber Security Intern', 'Internship', '2025-12-01', '2026-03-01', 0, 'Performed vulnerability assessments and assisted in SOC monitoring.'),
(3, 4, 'Robo Innovators Ltd.', 'Embedded Systems Intern', 'Internship', '2026-01-15', '2026-05-15', 0, 'Developed firmware for IoT sensor nodes on a production line.'),
(4, 6, 'UIU Data Science Society', 'Research Volunteer', 'Volunteer', '2025-09-01', NULL, 1, 'Supporting peer research groups with data preprocessing and analysis.');

-- --------------------------------------------------------
--
-- Seed data for table `projects`
--

INSERT INTO `projects` (`id`, `profile_id`, `title`, `description`, `project_type`, `start_date`, `end_date`, `repository_url`, `demo_url`, `is_featured`) VALUES
(1, 1, 'Bengali Fake News Detector', 'A transformer-based classifier that flags likely fake news articles written in Bengali, trained on a crowd-sourced dataset.', 'Academic', '2025-09-01', '2026-01-15', 'https://github.com/student-demo/bengali-fake-news-detector', NULL, 1),
(2, 1, 'AI Research Paper Recommender', 'A lightweight recommender that suggests relevant NLP papers to students based on their reading history.', 'Personal', '2026-02-01', NULL, 'https://github.com/student-demo/paper-recommender', NULL, 0),
(3, 2, 'Campus Network Intrusion Simulator', 'A sandboxed environment that simulates common intrusion techniques to train students on detection and response.', 'Academic', '2025-10-01', '2026-02-01', 'https://github.com/tanvir-ahmed/network-intrusion-sim', NULL, 0),
(4, 2, 'Password Strength Analyzer CLI', 'A command-line tool that estimates password crack time using entropy and common-pattern heuristics.', 'Personal', '2025-08-01', '2025-09-10', 'https://github.com/tanvir-ahmed/pw-strength-cli', NULL, 0),
(5, 3, 'Student Performance Predictor', 'An ensemble ML model predicting at-risk students from LMS engagement and attendance data.', 'Academic', '2025-11-01', '2026-03-01', 'https://github.com/farhana-islam/student-performance-predictor', NULL, 1),
(6, 3, 'COVID Data Dashboard', 'An interactive dashboard visualizing regional COVID-19 trends using public datasets.', 'Personal', '2024-05-01', '2024-07-01', 'https://github.com/farhana-islam/covid-dashboard', NULL, 0),
(7, 4, 'Autonomous Line-Following Robot', 'A PID-controlled robot built for the UIU robotics showcase, using IR sensors and an Arduino Mega.', 'FYDP', '2025-09-01', NULL, 'https://github.com/rakibul-hasan/line-following-robot', NULL, 1),
(8, 4, 'Home Automation with Arduino', 'A WiFi-enabled home automation system controlling lights and appliances via a mobile app.', 'Personal', '2025-03-01', '2025-06-01', 'https://github.com/rakibul-hasan/home-automation', NULL, 0),
(9, 5, 'Smart Attendance IoT Device', 'An RFID-based attendance device that syncs check-ins to a central dashboard over WiFi.', 'Academic', '2026-01-01', NULL, NULL, NULL, 0),
(10, 5, 'Campus WiFi Coverage Mapper', 'A crowdsourced signal-strength mapping tool to identify dead zones across UIU campus.', 'Personal', '2025-05-01', '2025-07-01', NULL, NULL, 0),
(11, 6, 'Bengali Chatbot for Student Queries', 'A retrieval-augmented chatbot answering common academic queries in Bengali and English.', 'FYDP', '2026-02-01', NULL, 'https://github.com/sadia-rahman/bengali-chatbot', NULL, 1),
(12, 6, 'Sentiment Analysis on Product Reviews', 'A fine-tuned BERT model classifying e-commerce product reviews as positive, negative, or neutral.', 'Academic', '2025-10-01', '2026-01-01', 'https://github.com/sadia-rahman/review-sentiment', NULL, 0),
(13, 7, 'Retail Sales Forecasting Dashboard', 'A time-series forecasting tool for small retail businesses, built with Python and Power BI.', 'Academic', '2026-01-01', '2026-04-01', NULL, NULL, 0),
(14, 7, 'Personal Finance Tracker App', 'A budgeting and expense-tracking web app with monthly spending insights.', 'Personal', '2025-07-01', '2025-09-01', NULL, NULL, 0),
(15, 8, 'Traffic Sign Recognition System', 'A CNN-based classifier recognizing Bangladeshi road signs from dashcam footage.', 'Academic', '2026-03-01', NULL, 'https://github.com/ayesha-siddiqua/traffic-sign-recognition', NULL, 1),
(16, 8, 'Face Mask Detection using CNN', 'A real-time face mask detector built with OpenCV and a lightweight CNN for edge devices.', 'Personal', '2025-11-01', '2026-01-01', 'https://github.com/ayesha-siddiqua/mask-detection', NULL, 0);

-- --------------------------------------------------------
--
-- Seed data for table `publications`
--

INSERT INTO `publications` (`id`, `profile_id`, `title`, `authors`, `venue`, `publication_type`, `publication_date`, `doi`, `url`, `abstract`, `status`) VALUES
(1, 1, 'Improving Bengali Named Entity Recognition using Transformer Models', 'Student Demo, Faculty Demo', 'International Conference on Bangla Language Processing', 'Conference Paper', '2026-03-15', NULL, NULL, 'We propose a transformer-based fine-tuning approach that improves NER F1 score on Bengali news text by 6 points over prior baselines.', 'Under Review'),
(2, 3, 'A Comparative Analysis of Ensemble Methods for Student Performance Prediction', 'Farhana Islam, Faculty Demo', 'UIU Undergraduate Research Symposium', 'Conference Paper', '2025-12-10', '10.5281/zenodo.1234567', 'https://doi.org/10.5281/zenodo.1234567', 'This study compares Random Forest, XGBoost, and stacked ensembles for predicting at-risk students from LMS engagement data.', 'Published'),
(3, 6, 'Generative Approaches to Bengali Chatbot Dialogue', 'Sadia Rahman', NULL, 'Journal Article', NULL, NULL, NULL, 'A work-in-progress study on retrieval-augmented generation for open-domain Bengali conversational agents.', 'In Preparation');

-- --------------------------------------------------------
--
-- Seed data for table `certifications`
--

INSERT INTO `certifications` (`id`, `profile_id`, `name`, `issuing_organization`, `issue_date`, `expiry_date`, `credential_id`, `credential_url`) VALUES
(1, 2, 'Certified Ethical Hacker (CEH) Foundation', 'EC-Council', '2026-02-01', '2029-02-01', 'CEH-2026-0451', 'https://aspen.eccouncil.org/verify?id=CEH-2026-0451'),
(2, 4, 'Arduino Certified IoT Developer', 'Arduino', '2025-11-20', NULL, 'ACD-88213', NULL),
(3, 7, 'Google Data Analytics Professional Certificate', 'Google / Coursera', '2026-01-10', NULL, 'GDA-771029', 'https://coursera.org/verify/professional-cert/GDA771029');

-- --------------------------------------------------------
--
-- Seed data for table `achievements`
--

INSERT INTO `achievements` (`id`, `profile_id`, `title`, `description`, `organization`, `achievement_date`) VALUES
(1, 1, 'Best Paper Award - UIU CSE Research Fair 2026', 'Awarded for the Bengali NER research poster presented at the department research fair.', 'UIU CSE Department', '2026-04-20'),
(2, 6, '2nd Runner-up, National NLP Hackathon 2026', 'Placed 3rd overall among 40+ teams building Bengali NLP applications.', 'BASIS', '2026-05-05'),
(3, 8, 'Dean''s List, Spring 2026 Trimester', 'Recognized for academic excellence with a trimester GPA above 3.75.', 'United International University', '2026-06-01');

-- --------------------------------------------------------
--
-- Seed data for table `profile_languages`
--

INSERT INTO `profile_languages` (`profile_id`, `language_id`, `proficiency`) VALUES
(1, 1, 'Native'), (1, 2, 'Fluent'),
(2, 1, 'Native'), (2, 2, 'Advanced'),
(3, 1, 'Native'), (3, 2, 'Fluent'), (3, 3, 'Basic'),
(4, 1, 'Native'), (4, 2, 'Intermediate'),
(5, 1, 'Native'), (5, 2, 'Advanced'),
(6, 1, 'Native'), (6, 2, 'Fluent'), (6, 8, 'Basic'),
(7, 1, 'Native'), (7, 2, 'Intermediate'),
(8, 1, 'Native'), (8, 2, 'Advanced');

-- --------------------------------------------------------
--
-- Seed data for table `research_preferences`
--

INSERT INTO `research_preferences` (`id`, `profile_id`, `looking_for_team`, `preferred_team_size_min`, `preferred_team_size_max`, `availability_hours_per_week`, `collaboration_preference`, `project_type_preference`) VALUES
(1, 1, 1, 2, 4, 15, 'Online + In Person', 'Research'),
(2, 2, 1, 2, 3, 10, 'In Person', 'FYDP'),
(3, 3, 1, 2, 4, 12, 'Online', 'Research'),
(4, 4, 1, 3, 5, 18, 'In Person', 'FYDP'),
(5, 5, 1, 2, 4, 8, 'Online + In Person', 'Academic'),
(6, 6, 1, 2, 3, 20, 'Online', 'Research'),
(7, 7, 0, 2, 4, 5, 'Online', 'Academic'),
(8, 8, 1, 2, 4, 10, 'In Person', 'Academic');

-- --------------------------------------------------------
--
-- Seed data for table `profile_visibility`
-- profile 4 = Private (hide-everything test case); profile 6 = publications hidden.
--

INSERT INTO `profile_visibility` (`id`, `profile_id`, `profile_visibility`, `contact_visibility`, `research_visibility`, `project_visibility`, `publication_visibility`) VALUES
(1, 1, 'Students Only', 1, 1, 1, 1),
(2, 2, 'Students Only', 1, 1, 1, 1),
(3, 3, 'Students Only', 1, 1, 1, 1),
(4, 4, 'Private', 1, 1, 1, 1),
(5, 5, 'Students Only', 1, 1, 1, 1),
(6, 6, 'Students Only', 1, 1, 1, 0),
(7, 7, 'Students Only', 1, 1, 1, 1),
(8, 8, 'Public', 1, 1, 1, 1);

-- --------------------------------------------------------
--
-- Seed data for table `research_opportunities`
-- Opportunity 4 has a PAST deadline but is still 'Open' to exercise the
-- deadline-passed UI block. Opportunity 5 is 'Closed', opportunity 6 is
-- 'Completed', the rest are 'Open'.
--

INSERT INTO `research_opportunities` (`id`, `created_by`, `title`, `description`, `problem_statement`, `requirements`, `project_type`, `team_size_min`, `team_size_max`, `deadline`, `status`, `visibility`) VALUES
(1, 2, 'AI-Based Plant Disease Detection using Deep Learning', 'We are looking for motivated students to build a deep learning pipeline that detects plant diseases from leaf images captured in real farm conditions.', 'Bangladeshi farmers lack access to affordable, fast plant disease diagnosis tools, leading to avoidable crop losses.', 'Familiarity with Python, CNNs (TensorFlow/PyTorch), and basic image processing. Prior coursework in machine learning preferred.', 'Research', 2, 5, '2026-11-15', 'Open', 'Public'),
(2, 11, 'Blockchain-Based Secure Voting System', 'Design and implement a blockchain-backed voting platform for university elections to ensure transparency and tamper-resistance.', 'Current student election systems lack verifiable transparency and are vulnerable to manipulation.', 'Experience with Solidity or a similar smart contract language, basic web development, and cryptography fundamentals.', 'FYDP', 2, 4, '2026-12-01', 'Open', 'Public'),
(3, 2, 'NLP for Bengali Sentiment Analysis', 'Build and evaluate sentiment analysis models for Bengali social media text, including data collection, annotation, and model training.', 'Existing sentiment analysis tools perform poorly on Bengali due to limited labeled datasets and heavy code-mixing.', 'Python, basic NLP/ML background, and willingness to help with dataset annotation.', 'Research', 2, 4, '2026-10-20', 'Open', 'Public'),
(4, 11, 'IoT-Based Smart Attendance System', 'Develop an RFID/BLE-based smart attendance system integrated with a web dashboard for real-time tracking.', 'Manual attendance tracking in large lecture halls is slow and error-prone.', 'Basic embedded systems knowledge (Arduino/ESP32) and willingness to work with hardware prototypes.', 'Academic', 2, 3, '2026-09-01', 'Open', 'Public'),
(5, 2, 'Deep Learning for Medical Image Diagnosis', 'Apply convolutional neural networks to classify medical images (X-rays/MRIs) for early disease detection.', 'Limited access to radiologists in rural healthcare centers delays diagnosis and treatment.', 'Strong Python and deep learning background; familiarity with medical imaging is a plus.', 'Research', 2, 5, '2026-07-15', 'Closed', 'Public'),
(6, 11, 'Cybersecurity Threat Intelligence Platform', 'Build a platform that aggregates and analyzes open-source threat intelligence feeds to flag emerging attack patterns.', 'Small organizations lack affordable tools to stay updated on emerging cyber threats.', 'Familiarity with cybersecurity concepts, Python, and REST API integration.', 'Research', 2, 4, '2026-08-01', 'Completed', 'Public');

-- --------------------------------------------------------
--
-- Seed data for table `opportunity_domains`
--

INSERT INTO `opportunity_domains` (`opportunity_id`, `domain_id`) VALUES
(1, 1), (1, 9),
(2, 4),
(3, 13), (3, 1),
(4, 11),
(5, 1), (5, 9), (5, 5),
(6, 7);

-- --------------------------------------------------------
--
-- Seed data for table `opportunity_applications`
-- Mixed statuses (not all Pending) since there is no faculty review UI.
--

INSERT INTO `opportunity_applications` (`id`, `opportunity_id`, `user_id`, `message`, `status`, `reviewed_at`) VALUES
(1, 1, 1, 'I have experience with TensorFlow from a coursework project and would love to contribute to the CNN pipeline.', 'Pending', NULL),
(2, 1, 5, 'My final year interest is in applied ML for agriculture; I have already collected a small leaf-image dataset.', 'Accepted', '2026-08-20 10:00:00'),
(3, 1, 10, 'I am new to research but eager to learn image classification techniques.', 'Rejected', '2026-08-21 09:30:00'),
(4, 2, 4, 'I have written smart contracts in Solidity for a class project and want to apply it to a real system.', 'Accepted', '2026-08-18 14:00:00'),
(5, 2, 6, 'I can help with the front-end voting dashboard and basic contract testing.', 'Pending', NULL),
(6, 3, 1, 'Bengali NLP is exactly my research focus; I can help with both annotation and modeling.', 'Accepted', '2026-08-22 11:00:00'),
(7, 3, 8, 'I would like to help with dataset annotation and preprocessing.', 'Pending', NULL),
(8, 4, 6, 'I have built Arduino-based IoT devices before and can help with the hardware side.', 'Pending', NULL),
(9, 4, 7, 'As an EEE student I can contribute to the sensor and hardware integration work.', 'Accepted', '2026-08-15 09:00:00'),
(10, 5, 5, 'Interested in applying my ensemble modeling experience to medical imaging.', 'Rejected', '2026-07-10 16:00:00'),
(11, 6, 4, 'I would like to contribute my network security background to this platform.', 'Rejected', '2026-07-25 12:00:00');

-- --------------------------------------------------------
--
-- Seed data for table `saved_opportunities`
--

INSERT INTO `saved_opportunities` (`user_id`, `opportunity_id`) VALUES
(1, 3), (1, 5),
(8, 1),
(9, 2),
(10, 4);

-- --------------------------------------------------------
--
-- Seed data for table `research_teams`
-- Team 2 is full (4/4); the rest have open seats to exercise both cases.
--

INSERT INTO `research_teams` (`id`, `name`, `description`, `created_by`, `opportunity_id`, `research_domain_id`, `team_size_limit`, `status`) VALUES
(1, 'Team NeuroVision', 'Applying deep learning to medical image diagnosis, starting with chest X-ray classification.', 10, 5, 5, 5, 'Forming'),
(2, 'BlockSecure Research Group', 'Building a transparent, tamper-resistant blockchain voting system for UIU student elections.', 4, 2, 4, 4, 'Active'),
(3, 'Bengali NLP Lab', 'A student research group focused on Bengali sentiment analysis and language modeling.', 1, 3, 13, 4, 'Active'),
(4, 'Smart IoT Innovators', 'Designing an RFID/BLE smart attendance system with a live analytics dashboard.', 6, 4, 11, 5, 'Forming'),
(5, 'CyberGuard Squad', 'Developed a threat intelligence aggregation platform for the Cybersecurity Threat Intelligence Platform opportunity.', 7, 6, 7, 4, 'Completed'),
(6, 'Plant Health AI', 'Building a CNN-based plant disease detector to help smallholder farmers diagnose crop issues early.', 8, 1, 1, 5, 'Forming');

-- --------------------------------------------------------
--
-- Seed data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `user_id`, `role`, `status`) VALUES
(1, 1, 10, 'Leader', 'Active'),
(2, 1, 5, 'Member', 'Active'),
(3, 1, 1, 'Member', 'Active'),
(4, 2, 4, 'Leader', 'Active'),
(5, 2, 6, 'Member', 'Active'),
(6, 2, 8, 'Member', 'Active'),
(7, 2, 9, 'Member', 'Active'),
(8, 3, 1, 'Leader', 'Active'),
(9, 3, 8, 'Member', 'Active'),
(10, 3, 10, 'Member', 'Active'),
(11, 4, 6, 'Leader', 'Active'),
(12, 4, 7, 'Member', 'Active'),
(13, 4, 5, 'Member', 'Active'),
(14, 5, 7, 'Leader', 'Active'),
(15, 5, 4, 'Member', 'Active'),
(16, 6, 8, 'Leader', 'Active'),
(17, 6, 1, 'Member', 'Active'),
(18, 6, 9, 'Member', 'Active');

-- --------------------------------------------------------
--
-- Seed data for table `team_invitations`
-- Pending invitations point at students who are NOT current members of that team.
--

INSERT INTO `team_invitations` (`id`, `team_id`, `invited_by`, `invited_user_id`, `message`, `status`, `responded_at`) VALUES
(1, 1, 10, 9, 'We could use your analytics background on Team NeuroVision, want to join?', 'Pending', NULL),
(2, 4, 6, 9, 'Would you like to help with the IoT dashboard for Smart IoT Innovators?', 'Rejected', '2026-08-12 10:00:00'),
(3, 3, 1, 5, 'Your data analysis skills would be a great fit for the Bengali NLP Lab.', 'Pending', NULL),
(4, 6, 8, 4, 'We would love your networking background on Plant Health AI for the data pipeline security.', 'Accepted', '2026-08-14 17:30:00');

-- --------------------------------------------------------
--
-- Seed data for table `team_requests`
-- Pending requests come from students who are NOT current members of that team.
--

INSERT INTO `team_requests` (`id`, `team_id`, `user_id`, `message`, `status`, `responded_at`) VALUES
(1, 1, 6, 'I would like to join Team NeuroVision, I have image-processing experience from a robotics project.', 'Pending', NULL),
(2, 3, 9, 'I am interested in the business/analytics side of Bengali sentiment data, can I join?', 'Pending', NULL),
(3, 4, 10, 'I would like to contribute computer-vision-based attendance verification to this project.', 'Accepted', '2026-08-13 09:00:00'),
(4, 6, 10, 'I want to help with the plant disease image classifier.', 'Rejected', '2026-08-16 15:00:00');

-- --------------------------------------------------------
--
-- Seed data for table `team_tasks`
--

INSERT INTO `team_tasks` (`id`, `team_id`, `assigned_to`, `created_by`, `title`, `description`, `priority`, `status`, `due_date`) VALUES
(1, 2, 6, 4, 'Design blockchain voting smart contract', 'Draft the Solidity contract handling voter registration and ballot casting.', 'High', 'In Progress', '2026-09-25'),
(2, 2, 8, 4, 'Set up Ethereum test network', 'Configure a local Hardhat/Ganache test network for contract deployment testing.', 'Medium', 'To Do', '2026-09-30'),
(3, 2, 9, 4, 'Write security audit checklist', 'Compile a checklist covering re-entrancy, overflow, and access-control issues for the contract review.', 'Critical', 'Blocked', '2026-08-20'),
(4, 3, 8, 1, 'Collect Bengali sentiment dataset', 'Scrape and label a first batch of 2,000 Bengali social-media comments.', 'High', 'Completed', '2026-08-05'),
(5, 3, 10, 1, 'Preprocess and clean text corpus', 'Normalize Unicode, remove noise, and handle code-mixed text in the collected dataset.', 'Medium', 'In Progress', '2026-09-18'),
(6, 3, 1, 1, 'Train baseline sentiment classifier', 'Train and evaluate a baseline logistic regression / BERT model on the cleaned dataset.', 'Medium', 'To Do', '2026-10-05');

-- --------------------------------------------------------
--
-- Seed data for table `team_milestones`
--

INSERT INTO `team_milestones` (`id`, `team_id`, `title`, `description`, `due_date`, `status`) VALUES
(1, 1, 'Literature Review Complete', 'Summarize prior work on chest X-ray classification with CNNs.', '2026-08-25', 'Completed'),
(2, 1, 'Model Prototype v1', 'First working prototype trained on the public ChestX-ray14 dataset.', '2026-09-30', 'In Progress'),
(3, 4, 'Hardware Procurement', 'Order RFID readers, ESP32 boards, and enclosures for the prototype.', '2026-08-15', 'Completed'),
(4, 4, 'Firmware Integration', 'Integrate RFID scanning firmware with the WiFi upload pipeline.', '2026-09-28', 'Pending'),
(5, 4, 'Pilot Deployment', 'Deploy the prototype in one lecture hall for a two-week trial.', '2026-11-10', 'Pending');

-- --------------------------------------------------------
--
-- Seed data for table `team_files`
-- NOTE: file_path values are PLACEHOLDERS ONLY — no file actually exists
-- on disk at these paths. They exist purely to populate the list view;
-- the app would 404/fail to serve them until a real file is uploaded.
--

INSERT INTO `team_files` (`id`, `team_id`, `uploaded_by`, `file_name`, `file_path`, `file_type`, `file_size`) VALUES
(1, 2, 4, 'voting_system_proposal.pdf', 'team-files/2/voting_system_proposal.pdf', 'application/pdf', 245760),
(2, 2, 6, 'smart_contract_draft.sol', 'team-files/2/smart_contract_draft.sol', 'text/plain', 8192),
(3, 3, 1, 'bengali_sentiment_dataset.csv', 'team-files/3/bengali_sentiment_dataset.csv', 'text/csv', 512000),
(4, 3, 8, 'nlp_lab_meeting_notes.docx', 'team-files/3/nlp_lab_meeting_notes.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 32768);

-- --------------------------------------------------------
--
-- Seed data for table `team_messages`
-- Chronologically increasing per team.
--

INSERT INTO `team_messages` (`id`, `team_id`, `sender_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 4, 'Welcome to BlockSecure Research Group! Let''s kick off with the smart contract design.', 1, '2026-08-05 10:00:00'),
(2, 2, 6, 'I''ll start on the Ethereum test network setup this week.', 1, '2026-08-05 10:15:00'),
(3, 2, 8, 'Sounds good, I''ll review the security requirements doc.', 1, '2026-08-06 09:00:00'),
(4, 2, 9, 'Security audit checklist first draft is up in team files.', 0, '2026-08-07 14:30:00'),
(5, 3, 1, 'Team, I''ve shared the initial Bengali sentiment dataset in files.', 1, '2026-08-10 11:00:00'),
(6, 3, 8, 'Great, starting preprocessing today.', 1, '2026-08-10 11:20:00'),
(7, 3, 10, 'Found some noisy labels, cleaning them now.', 0, '2026-08-11 15:45:00'),
(8, 3, 1, 'Nice work team, let''s sync tomorrow on the baseline model.', 0, '2026-08-12 09:10:00');

-- --------------------------------------------------------
--
-- Seed data for table `communities`
--

INSERT INTO `communities` (`id`, `name`, `description`, `domain_id`, `created_by`, `privacy`, `status`) VALUES
(1, 'AI & Machine Learning Circle', 'A community for students and faculty exploring AI, machine learning, and deep learning research and projects.', 1, 2, 'Public', 'Active'),
(2, 'Cyber Security Enthusiasts', 'Discussions, CTF practice, and research sharing for students interested in cybersecurity and network defense.', 7, 4, 'Public', 'Active'),
(3, 'Web & App Dev Community', 'A space to discuss web and mobile app development frameworks, tools, and FYDP tech stacks.', 16, 1, 'Public', 'Active'),
(4, 'Data Science Hub', 'For students and faculty working on data analysis, statistics, and applied data science projects.', 8, 11, 'Public', 'Active'),
(5, 'Robotics & IoT Club', 'A community for robotics, embedded systems, and IoT project builders across UIU.', 15, 6, 'Private', 'Active');

-- --------------------------------------------------------
--
-- Seed data for table `community_members`
--

INSERT INTO `community_members` (`id`, `community_id`, `user_id`, `role`) VALUES
(1, 1, 2, 'Admin'),
(2, 1, 1, 'Member'),
(3, 1, 6, 'Member'),
(4, 1, 8, 'Member'),
(5, 1, 10, 'Member'),
(6, 2, 4, 'Admin'),
(7, 2, 7, 'Member'),
(8, 2, 5, 'Member'),
(9, 3, 1, 'Admin'),
(10, 3, 9, 'Member'),
(11, 3, 10, 'Member'),
(12, 3, 4, 'Member'),
(13, 4, 11, 'Admin'),
(14, 4, 7, 'Member'),
(15, 4, 9, 'Member'),
(16, 5, 6, 'Admin'),
(17, 5, 5, 'Member'),
(18, 5, 7, 'Member');

-- --------------------------------------------------------
--
-- Seed data for table `community_posts`
--

INSERT INTO `community_posts` (`id`, `community_id`, `user_id`, `title`, `content`) VALUES
(1, 1, 1, 'Best resources to start with Transformers?', 'I''m trying to get a solid grip on attention and transformer architectures before starting my NLP research. Any book or course recommendations?'),
(2, 1, 6, NULL, 'Anyone working on generative AI projects this trimester? Would love to compare notes on fine-tuning small models on a single GPU.'),
(3, 1, 8, 'Paper discussion: Attention is All You Need', 'Starting a weekly thread to go through foundational AI papers. First up: the original Transformer paper. Thoughts welcome!'),
(4, 2, 4, 'CTF practice session this weekend', 'Organizing an informal CTF practice session this Saturday at the CSE lab. We''ll cover web exploitation and basic reverse engineering.'),
(5, 2, 7, NULL, 'Sharing a great writeup on SQL injection prevention techniques I found while prepping for our security course project.'),
(6, 3, 1, 'Laravel vs Django for FYDP?', 'Deciding between Laravel and Django for my FYDP backend. Anyone have experience shipping a real project with either?'),
(7, 3, 9, NULL, 'Just deployed my first Flutter app to the Play Store beta! Happy to share what I learned about the release process.'),
(8, 4, 7, 'Kaggle competition team forming', 'Looking for 2-3 teammates to join an upcoming Kaggle tabular data competition. Comment if interested!'),
(9, 4, 9, NULL, 'Looking for feedback on my exploratory data analysis notebook for the retail sales forecasting project.'),
(10, 5, 6, 'Arduino sensor calibration issue', 'My IR sensor readings drift over time on the line-following robot. Anyone dealt with this before?'),
(11, 5, 5, NULL, 'Our IoT attendance prototype passed initial testing this week! RFID read range still needs tuning though.');

-- --------------------------------------------------------
--
-- Seed data for table `community_comments`
--

INSERT INTO `community_comments` (`id`, `post_id`, `user_id`, `comment`) VALUES
(1, 1, 6, 'Check out the "Illustrated Transformer" blog post, it really helped me build intuition before diving into the paper.'),
(2, 1, 8, 'Agreed, also recommend the Hugging Face NLP course, it pairs theory with hands-on notebooks.'),
(3, 3, 1, 'Great pick! The multi-head attention section is worth re-reading a few times.'),
(4, 4, 7, 'Count me in, I''ll bring some crypto challenges I''ve been working through.'),
(5, 6, 9, 'I''d go with Laravel if you want faster scaffolding, Django is great if your team already knows Python well.'),
(6, 8, 9, 'I''m interested, what''s the competition link? I can help with feature engineering.'),
(7, 10, 5, 'Try averaging multiple readings and adding a small delay between samples, that reduced drift for our IoT project.');

-- --------------------------------------------------------
--
-- Seed data for table `research_resources`
-- NOTE: rows with a file_path are PLACEHOLDERS ONLY — no file actually
-- exists on disk at that path; it exists purely to populate the list view.
--

INSERT INTO `research_resources` (`id`, `uploaded_by`, `title`, `description`, `resource_type`, `author`, `publication_year`, `domain_id`, `file_path`, `external_url`, `visibility`, `status`) VALUES
(1, 2, 'Deep Learning Approaches for Plant Disease Classification: A Survey', 'A survey of CNN and transfer-learning approaches for automated plant disease detection from leaf imagery.', 'Journal Article', 'M. Rahman et al.', 2025, 1, NULL, 'https://doi.org/10.1109/ACCESS.2025.3141592', 'Public', 'Published'),
(2, 11, 'Blockchain Consensus Mechanisms: A Comparative Study', 'Compares Proof-of-Work, Proof-of-Stake, and PBFT consensus mechanisms for permissioned voting applications.', 'Conference Paper', 'K. S. Zaman, F. Islam', 2024, 4, NULL, 'https://arxiv.org/abs/2403.11842', 'Public', 'Published'),
(3, 1, 'Bengali Sentiment Analysis Dataset (BanglaSent-2026)', 'A labeled dataset of 5,000 Bengali social media comments annotated for sentiment polarity.', 'Dataset', 'T. Ahmed', 2026, 13, 'resources/banglasent-2026.csv', NULL, 'Public', 'Published'),
(4, 4, 'A Survey on Network Intrusion Detection Systems', 'Reviews signature-based and anomaly-based NIDS approaches, including recent deep learning methods.', 'Research Paper', 'T. Ahmed, N. Jahan', 2025, 7, NULL, 'https://doi.org/10.1016/j.cose.2025.103201', 'Public', 'Published'),
(5, 2, 'Introduction to Convolutional Neural Networks', 'A beginner-friendly walkthrough of CNN architectures, pooling, and backpropagation with worked examples.', 'Tutorial', 'Faculty Demo', 2026, 1, NULL, 'https://cs231n.github.io/convolutional-networks/', 'Public', 'Published'),
(6, 6, 'IoT-Based Smart Attendance System: Undergraduate Thesis', 'Undergraduate thesis describing the design and evaluation of an RFID-based smart attendance system.', 'Thesis', 'R. Hasan', 2026, 11, 'resources/iot-attendance-thesis.pdf', NULL, 'Public', 'Published'),
(7, 11, 'Practical Guide to Smart Contract Security Auditing', 'A hands-on documentation resource covering common Solidity vulnerabilities and audit workflows.', 'Documentation', 'K. S. Zaman', 2025, 4, NULL, 'https://docs.openzeppelin.com/contracts/4.x/', 'Public', 'Published'),
(8, 8, 'Exploratory Data Analysis with Pandas: A Hands-on Guide', 'A tutorial covering data cleaning, aggregation, and visualization workflows using Pandas.', 'Tutorial', 'S. Rahman', 2026, 8, NULL, 'https://pandas.pydata.org/docs/user_guide/index.html', 'Public', 'Published'),
(9, 5, 'A Comparative Study of Machine Learning Algorithms for Predictive Analytics', 'Benchmarks common ML algorithms on educational and business predictive analytics tasks.', 'Research Paper', 'F. Islam', 2025, 8, NULL, 'https://doi.org/10.48550/arXiv.2501.09876', 'Public', 'Published'),
(10, 10, 'Object Detection Techniques in Computer Vision: A Review', 'Reviews two-stage and single-stage object detectors including Faster R-CNN, YOLO, and SSD.', 'Journal Article', 'A. Siddiqua', 2026, 5, NULL, 'https://doi.org/10.1109/TPAMI.2026.3178842', 'Public', 'Published'),
(11, 7, 'Wireless Sensor Networks for Smart Campus Monitoring', 'Explores low-power WSN deployment strategies for environmental and occupancy monitoring on campus.', 'Conference Paper', 'N. Jahan', 2025, 14, NULL, 'https://arxiv.org/abs/2502.04521', 'Public', 'Published'),
(12, 2, 'CSE Research Methodology Handbook', 'A department handbook covering literature review, experimental design, and academic writing basics.', 'Book', 'UIU CSE Department', 2024, NULL, 'resources/research-methodology-handbook.pdf', NULL, 'Public', 'Published');

-- --------------------------------------------------------
--
-- Seed data for table `saved_resources`
--

INSERT INTO `saved_resources` (`user_id`, `resource_id`) VALUES
(1, 1), (1, 5),
(8, 8), (8, 9),
(10, 10),
(4, 4),
(6, 6);

-- --------------------------------------------------------
--
-- Seed data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_type`, `related_id`, `is_read`, `created_at`, `read_at`) VALUES
(1, 1, 'application_status', 'Application Accepted', 'Your application to "NLP for Bengali Sentiment Analysis" has been accepted.', 'opportunity_application', 6, 1, '2026-08-22 11:05:00', '2026-08-23 08:00:00'),
(2, 5, 'application_status', 'Application Accepted', 'Your application to "AI-Based Plant Disease Detection using Deep Learning" has been accepted.', 'opportunity_application', 2, 0, '2026-08-20 10:05:00', NULL),
(3, 10, 'application_status', 'Application Update', 'Your application to "AI-Based Plant Disease Detection using Deep Learning" was not selected this time.', 'opportunity_application', 3, 0, '2026-08-21 09:35:00', NULL),
(4, 9, 'team_invitation', 'Team Invitation', 'You have been invited to join "Team NeuroVision".', 'team_invitation', 1, 0, '2026-08-24 10:00:00', NULL),
(5, 5, 'team_invitation', 'Team Invitation', 'You have been invited to join "Bengali NLP Lab".', 'team_invitation', 3, 0, '2026-08-24 10:10:00', NULL),
(6, 10, 'team_request', 'Team Join Request', 'Rakibul Hasan requested to join "Team NeuroVision".', 'team_request', 1, 1, '2026-08-23 09:00:00', '2026-08-23 12:00:00'),
(7, 1, 'team_request', 'Team Join Request', 'Mehedi Hasan requested to join "Bengali NLP Lab".', 'team_request', 2, 0, '2026-08-23 09:15:00', NULL),
(8, 4, 'team_message', 'New Team Message', 'New message in "BlockSecure Research Group".', 'team', 2, 0, '2026-08-07 14:30:00', NULL),
(9, 8, 'team_message', 'New Team Message', 'New message in "Bengali NLP Lab".', 'team', 3, 1, '2026-08-12 09:15:00', '2026-08-12 20:00:00'),
(10, 1, 'resource_shared', 'New Research Resource', 'A new resource "Introduction to Convolutional Neural Networks" was published in Artificial Intelligence.', 'research_resource', 5, 0, '2026-08-18 12:00:00', NULL),
(11, 6, 'opportunity_new', 'New Research Opportunity', 'A new opportunity "IoT-Based Smart Attendance System" matching your interests was posted.', 'research_opportunity', 4, 0, '2026-07-25 09:00:00', NULL),
(12, 7, 'opportunity_new', 'New Research Opportunity', 'A new opportunity "Cybersecurity Threat Intelligence Platform" matching your interests was posted.', 'research_opportunity', 6, 1, '2026-07-05 09:00:00', '2026-07-06 08:30:00');

-- --------------------------------------------------------
--
-- Seed data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `activity_type`, `description`, `related_type`, `related_id`, `created_at`) VALUES
(1, 1, 'signup', 'Account created', NULL, NULL, '2026-06-01 09:05:00'),
(2, 1, 'login', 'User logged in', NULL, NULL, '2026-08-20 08:30:00'),
(3, 1, 'profile_update', 'Updated profile bio and research statement', 'student_profile', 1, '2026-08-20 08:45:00'),
(4, 1, 'opportunity_apply', 'Applied to research opportunity: NLP for Bengali Sentiment Analysis', 'research_opportunity', 3, '2026-08-21 10:00:00'),
(5, 4, 'signup', 'Account created', NULL, NULL, '2026-06-05 10:05:00'),
(6, 4, 'team_create', 'Created research team: BlockSecure Research Group', 'research_team', 2, '2026-08-04 12:00:00'),
(7, 5, 'login', 'User logged in', NULL, NULL, '2026-08-15 14:20:00'),
(8, 5, 'opportunity_apply', 'Applied to research opportunity: AI-Based Plant Disease Detection using Deep Learning', 'research_opportunity', 1, '2026-08-16 09:10:00'),
(9, 6, 'team_join', 'Joined research team: Smart IoT Innovators', 'research_team', 4, '2026-08-09 11:00:00'),
(10, 7, 'community_post', 'Posted in community: Cyber Security Enthusiasts', 'community_post', 5, '2026-08-18 16:40:00'),
(11, 8, 'resource_upload', 'Uploaded research resource: Exploratory Data Analysis with Pandas: A Hands-on Guide', 'research_resource', 8, '2026-08-22 13:15:00'),
(12, 9, 'signup', 'Account created', NULL, NULL, '2026-07-10 16:25:00'),
(13, 10, 'team_request', 'Requested to join research team: Team NeuroVision', 'research_team', 1, '2026-08-25 17:00:00'),
(14, 2, 'login', 'Faculty logged in', NULL, NULL, '2026-08-30 09:00:00'),
(15, 2, 'opportunity_post', 'Posted new research opportunity: AI-Based Plant Disease Detection using Deep Learning', 'research_opportunity', 1, '2026-07-01 10:00:00');

-- ---------------------------------------------------------------------
-- Formerly "Coming Soon" Student Profile fields (requires
-- database/migrations_003_profile_extended.sql to have been imported
-- first). Populates a few demo profiles so the profile pages look
-- complete during a demonstration.
-- ---------------------------------------------------------------------
UPDATE `student_profiles` SET `date_of_birth`='2003-05-14', `gender`='Male', `preferred_contact`='University Email', `academic_status`='Currently Studying', `expected_graduation_date`='2027-06-01', `research_methodologies`='Machine Learning,Data Analysis,Experimental Research' WHERE `id`=1;
UPDATE `student_profiles` SET `date_of_birth`='2002-11-02', `gender`='Female', `preferred_contact`='Platform Messages', `academic_status`='Currently Studying', `expected_graduation_date`='2026-12-01', `research_methodologies`='System Development,Literature Review' WHERE `id`=2;
UPDATE `student_profiles` SET `date_of_birth`='2003-02-20', `gender`='Female', `preferred_contact`='Phone', `academic_status`='Currently Studying', `expected_graduation_date`='2027-12-01', `research_methodologies`='Survey Research,Data Analysis' WHERE `id`=3;

INSERT INTO `extracurricular_activities` (`profile_id`, `title`, `organization`, `role`, `start_date`, `end_date`, `is_current`, `description`) VALUES
(1, 'Robotics Club', 'UIU Robotics Club', 'Technical Lead', '2025-01-15', NULL, 1, 'Leading a team of 6 students building autonomous line-following robots for inter-university competitions.'),
(1, 'Hackathon Volunteer', 'UIU CSE Society', 'Volunteer', '2025-06-01', '2025-06-03', 0, 'Helped organize and run a 48-hour campus hackathon for 120+ participants.'),
(4, 'Debate Club', 'UIU Debating Society', 'Member', '2024-09-01', NULL, 1, 'Regular participant in inter-departmental debate competitions.'),
(5, 'Cultural Fest Committee', 'UIU Cultural Club', 'Coordinator', '2025-02-01', '2025-04-30', 0, 'Coordinated logistics for the annual spring cultural festival.');

INSERT INTO `profile_availability` (`profile_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 'Saturday', '09:00:00', '13:00:00'),
(1, 'Monday', '15:00:00', '18:00:00'),
(1, 'Wednesday', '15:00:00', '18:00:00'),
(4, 'Sunday', '10:00:00', '14:00:00'),
(4, 'Tuesday', '16:00:00', '19:00:00'),
(5, 'Saturday', '14:00:00', '17:00:00'),
(5, 'Thursday', '10:00:00', '13:00:00');

-- =====================================================================
-- Faculty Portal + Advisor System — demo data.
-- Requires database/migrations_005_faculty_portal.sql to have been
-- imported first (faculty_* tables, advisor_requests/assignments/
-- feedback, research_connections, direct_conversations/messages).
--
-- Adds 2 more faculty (4 total: 2 accepting mentees, 1 with limited
-- capacity, 1 not currently accepting — on sabbatical), their full
-- profiles, a faculty-created opportunity, a realistic mix of advisor
-- requests (pending / accepted / declined / clarification_requested,
-- both individual and team), 2 active advisor assignments (one
-- individual, one team), matching research_connections + a direct
-- message thread for each, one advisor_feedback entry, and the
-- notifications each of these actions would have produced.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 2 additional faculty users + profiles
-- ---------------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(15, 'Dr. Farzana Yasmin', 'farzana.yasmin@cse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'faculty', 'active', '2026-08-05 09:00:00', '2026-09-10 10:00:00', '2026-08-05 09:00:00', '2026-08-05 09:00:00'),
(16, 'Dr. Imran Chowdhury', 'imran.chowdhury@cse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'faculty', 'active', '2026-08-06 09:00:00', '2026-09-01 08:00:00', '2026-08-06 09:00:00', '2026-08-06 09:00:00');

INSERT INTO `faculty_profiles` (`id`, `user_id`, `faculty_id`, `department`, `designation`, `specialization`, `office_location`, `phone`, `bio`, `research_statement`, `linkedin_url`, `google_scholar_url`, `researchgate_url`, `portfolio_url`) VALUES
(3, 15, 'FAC-1003', 'Computer Science and Engineering', 'Professor', 'Human-Computer Interaction, Artificial Intelligence', 'Room 502, UIU CSE Building', '+8801711000003', 'Dr. Farzana Yasmin is a Professor researching accessible and inclusive AI interfaces, with a focus on assistive technology for visually impaired users.', 'My research explores how AI systems can be designed to be more inclusive and accessible, particularly for users with visual impairments. I welcome students interested in HCI, accessibility, or applied AI.', 'https://linkedin.com/in/farzana-yasmin-uiu', 'https://scholar.google.com/citations?user=farzanayasmin', 'https://researchgate.net/profile/Farzana-Yasmin', NULL),
(4, 16, 'FAC-1004', 'Computer Science and Engineering', 'Assistant Professor', 'Robotics, Internet of Things', 'Room 210, UIU CSE Building', '+8801711000004', 'Dr. Imran Chowdhury researches low-cost robotics and IoT systems for resource-constrained environments, and supervises embedded-systems FYDP teams.', 'I focus on affordable robotics and IoT solutions that work reliably in low-resource settings — smart attendance, campus automation, and autonomous delivery systems.', 'https://linkedin.com/in/imran-chowdhury-uiu', 'https://scholar.google.com/citations?user=imranchowdhury', NULL, NULL);

UPDATE `faculty_profiles` SET `research_statement` = 'I work on transformer-based NLP for low-resource languages, with a focus on Bengali. I welcome students interested in NLP, sentiment analysis, or applied deep learning.' WHERE `id` = 1;
UPDATE `faculty_profiles` SET `research_statement` = 'My research covers blockchain security, distributed consensus, and applied cryptography. I supervise students building secure, verifiable systems.' WHERE `id` = 2;

-- ---------------------------------------------------------------------
-- Faculty research domains / expertise
-- ---------------------------------------------------------------------
INSERT INTO `faculty_research_domains` (`faculty_profile_id`, `domain_id`) VALUES
(1, 1), (1, 13),
(2, 4), (2, 7),
(3, 1), (3, 10),
(4, 15), (4, 11);

INSERT INTO `faculty_skills` (`faculty_profile_id`, `skill_id`) VALUES
(1, 1), (1, 11), (1, 12),
(2, 1), (2, 19), (2, 21),
(3, 1), (3, 6), (3, 22),
(4, 25), (4, 1), (4, 16);

-- ---------------------------------------------------------------------
-- Faculty education
-- ---------------------------------------------------------------------
INSERT INTO `faculty_education` (`faculty_profile_id`, `institution`, `degree`, `field_of_study`, `start_date`, `end_date`, `description`) VALUES
(1, 'Bangladesh University of Engineering and Technology (BUET)', 'PhD', 'Computer Science (Natural Language Processing)', '2016-01-01', '2020-12-01', 'Dissertation on low-resource neural machine translation for South Asian languages.'),
(2, 'North South University', 'PhD', 'Computer Science and Engineering (Blockchain Security)', '2017-01-01', '2021-06-01', 'Dissertation on Byzantine fault-tolerant consensus for permissioned blockchains.'),
(3, 'University of Malaya', 'PhD', 'Human-Computer Interaction', '2015-09-01', '2019-08-01', 'Dissertation on accessible interface design for visually impaired users.'),
(4, 'Asian Institute of Technology (AIT)', 'PhD', 'Robotics Engineering', '2016-06-01', '2021-05-01', 'Dissertation on low-cost autonomous navigation for resource-constrained robotic platforms.');

-- ---------------------------------------------------------------------
-- Faculty publications
-- ---------------------------------------------------------------------
INSERT INTO `faculty_publications` (`faculty_profile_id`, `title`, `authors`, `venue`, `publication_type`, `publication_date`, `doi`, `url`, `abstract`, `status`) VALUES
(1, 'Transformer-Based Named Entity Recognition for Low-Resource Bengali Text', 'Faculty Demo, Student Demo', 'IEEE Access', 'Journal Article', '2025-11-10', '10.1109/ACCESS.2025.9988776', NULL, 'Proposes a fine-tuning approach improving Bengali NER F1 score by 6 points over prior baselines.', 'Published'),
(1, 'A Survey of Deep Learning Techniques for Bengali NLP', 'Faculty Demo', 'Journal of Language Technology', 'Journal Article', '2024-06-01', NULL, NULL, 'Surveys transformer and RNN-based approaches applied to Bengali language processing tasks.', 'Published'),
(2, 'Blockchain Consensus Mechanisms: A Comparative Study', 'K. S. Zaman, F. Islam', 'arXiv', 'Conference Paper', '2024-03-18', NULL, 'https://arxiv.org/abs/2403.11842', 'Compares Proof-of-Work, Proof-of-Stake, and PBFT for permissioned voting applications.', 'Published'),
(2, 'Smart Contract Vulnerability Detection using Static Analysis', 'K. S. Zaman', 'Journal of Systems Security', 'Journal Article', NULL, NULL, NULL, 'Proposes a static-analysis pipeline for detecting re-entrancy and access-control flaws in Solidity contracts.', 'Under Review'),
(3, 'Designing Inclusive AI Interfaces for Visually Impaired Users', 'F. Yasmin', 'ACM CHI', 'Conference Paper', '2025-04-22', '10.1145/3544548.3581234', NULL, 'Presents design guidelines for screen-reader-compatible AI assistant interfaces.', 'Published'),
(4, 'Low-Cost RFID Attendance Systems for Resource-Constrained Classrooms', 'I. Chowdhury', 'IEEE Region 10 Conference (TENCON)', 'Conference Paper', '2024-11-05', NULL, NULL, 'Evaluates a low-cost RFID attendance pipeline suitable for large lecture halls in resource-constrained institutions.', 'Published');

-- ---------------------------------------------------------------------
-- Faculty research projects
-- ---------------------------------------------------------------------
INSERT INTO `faculty_projects` (`faculty_profile_id`, `title`, `description`, `project_type`, `funding_source`, `start_date`, `end_date`, `status`, `repository_url`) VALUES
(1, 'Bengali NLP Toolkit', 'An open-source toolkit bundling tokenization, NER, and sentiment models for Bengali text.', 'Research', 'UIU Internal Research Grant', '2025-01-01', NULL, 'Ongoing', 'https://github.com/uiu-nlp/bengali-nlp-toolkit'),
(2, 'BlockSecure Voting Platform Research', 'Research underpinning a transparent, tamper-resistant blockchain voting system for university elections.', 'Research', NULL, '2025-06-01', NULL, 'Ongoing', NULL),
(3, 'Accessible Campus AI Assistant', 'A screen-reader-compatible AI assistant helping visually impaired students navigate campus resources.', 'Research', 'UIU Internal Research Grant', '2025-09-01', NULL, 'Ongoing', NULL),
(4, 'Autonomous Campus Delivery Robot', 'A low-cost autonomous ground robot for intra-campus document and parcel delivery.', 'Research', NULL, '2026-01-01', NULL, 'Planned', NULL);

-- ---------------------------------------------------------------------
-- Faculty availability
-- ---------------------------------------------------------------------
INSERT INTO `faculty_availability` (`faculty_profile_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 'Sunday', '10:00:00', '12:00:00'),
(1, 'Wednesday', '14:00:00', '16:00:00'),
(2, 'Monday', '11:00:00', '13:00:00'),
(3, 'Tuesday', '10:00:00', '12:00:00'),
(3, 'Thursday', '14:00:00', '16:00:00'),
(4, 'Saturday', '10:00:00', '12:00:00');

-- ---------------------------------------------------------------------
-- Faculty mentorship preferences (fp4 is NOT accepting mentees — on
-- sabbatical — to exercise the "not accepting" UI/logic path).
-- ---------------------------------------------------------------------
INSERT INTO `faculty_preferences` (`faculty_profile_id`, `accepting_mentees`, `accepting_team_advisory`, `accepting_paper_advisory`, `max_active_mentees`, `preferred_project_types`, `preferred_domains`, `meeting_preference`, `availability_note`) VALUES
(1, 1, 1, 1, 6, 'Research, FYDP', 'Artificial Intelligence, Natural Language Processing', 'Online + In Person', 'Prefers async updates by email between scheduled meetings.'),
(2, 1, 1, 1, 5, 'Research, FYDP', 'Blockchain, Cyber Security', 'Online', NULL),
(3, 1, 0, 1, 4, 'Research', 'Artificial Intelligence, Human Computer Interaction', 'In Person', 'Currently at full individual-mentee capacity for team advisory.'),
(4, 0, 0, 0, 2, 'FYDP', 'Robotics, Internet of Things', 'Online', 'On research sabbatical this trimester — not accepting new mentees or advisory requests until next term.');

-- ---------------------------------------------------------------------
-- Faculty profile visibility
-- ---------------------------------------------------------------------
INSERT INTO `faculty_visibility` (`faculty_profile_id`, `profile_visibility`, `contact_visibility`, `research_visibility`, `project_visibility`, `publication_visibility`) VALUES
(1, 'Public', 1, 1, 1, 1),
(2, 'Public', 1, 1, 1, 1),
(3, 'Public', 1, 1, 1, 1),
(4, 'Students Only', 0, 1, 1, 1);

-- ---------------------------------------------------------------------
-- A faculty-created opportunity from the newest faculty member
-- ---------------------------------------------------------------------
INSERT INTO `research_opportunities` (`id`, `created_by`, `title`, `description`, `problem_statement`, `requirements`, `project_type`, `team_size_min`, `team_size_max`, `deadline`, `status`, `visibility`) VALUES
(7, 15, 'Accessible AI Assistant for Visually Impaired Students', 'Build a screen-reader-friendly conversational assistant that helps visually impaired students navigate course materials and campus services.', 'Visually impaired students face significant friction using mainstream campus portals and LMS tools not designed with screen readers in mind.', 'Familiarity with accessibility standards (WCAG), basic NLP/conversational AI, and empathy-driven design. Frontend experience is a plus.', 'Research', 2, 4, '2026-12-15', 'Open', 'Public');

INSERT INTO `opportunity_domains` (`opportunity_id`, `domain_id`) VALUES
(7, 1), (7, 10);

-- ---------------------------------------------------------------------
-- Advisor / mentorship requests — a realistic mix of statuses.
-- ---------------------------------------------------------------------
INSERT INTO `advisor_requests` (`id`, `requester_type`, `requested_by_user_id`, `faculty_user_id`, `team_id`, `project_id`, `opportunity_id`, `request_type`, `title`, `message`, `research_summary`, `expected_commitment`, `preferred_meeting_frequency`, `status`, `faculty_response`, `responded_at`, `created_at`) VALUES
(1, 'student', 4, 2, NULL, 3, NULL, 'research_advisor', 'Requesting Research Advisor for Network Security Track', 'I have been working on network intrusion detection and would value your guidance as I move toward a publishable research paper.', 'Building an anomaly-based NIDS prototype evaluated against the CIC-IDS2017 dataset.', '5 hrs/week', 'Weekly', 'accepted', 'Happy to advise you on this — let''s set up a recurring weekly check-in.', '2026-08-10 10:00:00', '2026-08-08 09:00:00'),
(2, 'student', 8, 11, NULL, 11, NULL, 'technical_mentor', 'Technical Mentorship for Bengali Chatbot FYDP', 'I''m building a retrieval-augmented Bengali chatbot for my FYDP and would appreciate technical mentorship on the architecture.', 'Retrieval-augmented generation pipeline for open-domain Bengali academic Q&A.', '4 hrs/week', 'Biweekly', 'pending', NULL, NULL, '2026-09-15 11:20:00'),
(3, 'team', 1, 2, 3, NULL, 3, 'research_advisor', 'Faculty Advisor Request — Bengali NLP Lab', 'Our team would love to have you as our official faculty advisor for the Bengali sentiment analysis initiative.', 'Team is annotating a 5,000-comment Bengali sentiment dataset and training baseline classifiers.', '3 hrs/week', 'Weekly', 'accepted', 'Excited to guide this team — great initiative on the dataset work.', '2026-08-14 15:00:00', '2026-08-12 10:00:00'),
(4, 'student', 6, 15, NULL, 7, NULL, 'project_mentor', 'Project Mentorship for Autonomous Robot FYDP', 'Would you be willing to mentor my line-following robot project as it expands into a full FYDP?', 'Extending a PID-controlled line-following robot into a general-purpose autonomous navigation platform.', '4 hrs/week', 'Weekly', 'declined', 'My individual-mentee capacity is full this trimester — please reach out again next term.', '2026-09-05 09:30:00', '2026-09-02 14:00:00'),
(5, 'student', 9, 11, NULL, NULL, NULL, 'research_mentor', 'Research Mentorship — Business Analytics Direction', 'I''m interested in applying data analytics research methods to retail forecasting and would like your mentorship.', 'Exploring time-series forecasting methods for small-retail sales prediction.', NULL, NULL, 'clarification_requested', 'Could you share more detail on your research question and how many hours per week you can commit?', '2026-09-18 12:00:00', '2026-09-16 09:00:00'),
(6, 'team', 6, 16, 4, NULL, 4, 'fydp_supervisor', 'FYDP Supervisor Request — Smart IoT Innovators', 'Our team is building a smart attendance system for our FYDP and would like you as our supervisor.', 'RFID/BLE-based smart attendance system with a live analytics dashboard.', '3 hrs/week', 'Weekly', 'pending', NULL, NULL, '2026-07-20 10:00:00');

-- ---------------------------------------------------------------------
-- Active advisor assignments (created by requests 1 and 3 being accepted)
-- ---------------------------------------------------------------------
INSERT INTO `advisor_assignments` (`id`, `advisor_request_id`, `faculty_user_id`, `student_user_id`, `team_id`, `project_id`, `opportunity_id`, `assignment_type`, `status`, `assigned_at`) VALUES
(1, 1, 2, 4, NULL, 3, NULL, 'research_advisor', 'active', '2026-08-10 10:00:00'),
(2, 3, 2, NULL, 3, NULL, 3, 'research_advisor', 'active', '2026-08-14 15:00:00');

INSERT INTO `advisor_feedback` (`assignment_id`, `faculty_user_id`, `team_id`, `title`, `content`, `visibility`, `created_at`) VALUES
(2, 2, 3, 'Kickoff Guidance', 'Great start on the literature review — let''s prioritize expanding the labeled dataset to at least 5,000 comments before we train a baseline classifier.', 'team', '2026-08-16 09:00:00');

-- ---------------------------------------------------------------------
-- Research connections (accepted connections back the two conversations
-- below; one additional pending connection exercises that UI state).
-- ---------------------------------------------------------------------
INSERT INTO `research_connections` (`id`, `requester_id`, `recipient_id`, `request_message`, `status`, `responded_at`, `created_at`) VALUES
(1, 2, 4, 'Looking forward to advising you — let''s connect here first.', 'accepted', '2026-08-10 10:05:00', '2026-08-10 09:50:00'),
(2, 1, 11, 'Would love to connect and learn more about your blockchain security research.', 'pending', NULL, '2026-09-20 08:30:00'),
(3, 2, 1, 'Connecting so I can share guidance with your team here.', 'accepted', '2026-08-14 15:05:00', '2026-08-14 14:55:00');

-- ---------------------------------------------------------------------
-- Direct conversations + a short message thread for each accepted
-- connection above (canonical ordering: user_one_id < user_two_id).
-- ---------------------------------------------------------------------
INSERT INTO `direct_conversations` (`id`, `user_one_id`, `user_two_id`, `connection_id`, `advisor_assignment_id`, `last_message_at`, `created_at`) VALUES
(1, 2, 4, 1, 1, '2026-08-11 09:05:00', '2026-08-10 10:05:00'),
(2, 1, 2, 3, 2, '2026-08-15 11:20:00', '2026-08-14 15:05:00');

INSERT INTO `direct_messages` (`id`, `conversation_id`, `sender_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 2, 'Welcome aboard! Let''s set up a weekly check-in — does Sunday 10am work for you?', 1, '2026-08-10 10:10:00'),
(2, 1, 4, 'Sunday 10am works great. I''ll prepare a short progress summary beforehand.', 1, '2026-08-10 18:30:00'),
(3, 1, 2, 'Perfect, see you then. In the meantime, could you share your current dataset size?', 0, '2026-08-11 09:05:00'),
(4, 2, 2, 'Looking forward to advising Bengali NLP Lab. Can your team share the current dataset size?', 0, '2026-08-14 15:10:00'),
(5, 2, 1, 'We currently have about 2,000 labeled comments and are cleaning the corpus this week.', 1, '2026-08-15 11:20:00');

-- ---------------------------------------------------------------------
-- Notifications for the advisor / connection / messaging workflows above.
-- ---------------------------------------------------------------------
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_type`, `related_id`, `is_read`, `created_at`, `read_at`) VALUES
(13, 2, 'advisor_request', 'New Advisor Request', 'Tanvir Ahmed requested you as a Research Advisor.', 'advisor_request', 1, 1, '2026-08-08 09:00:00', '2026-08-08 12:00:00'),
(14, 4, 'advisor_request', 'Advisor Request Accepted', 'Faculty Demo accepted your advisor request for you.', 'advisor_request', 1, 1, '2026-08-10 10:00:00', '2026-08-10 12:00:00'),
(15, 2, 'advisor_request', 'New Team Advisor Request', 'Team "Bengali NLP Lab" requested you as a Research Advisor.', 'advisor_request', 3, 1, '2026-08-12 10:00:00', '2026-08-12 11:00:00'),
(16, 1, 'advisor_request', 'Advisor Request Accepted', 'Faculty Demo accepted the advisor request for your team "Bengali NLP Lab".', 'advisor_request', 3, 0, '2026-08-14 15:00:00', NULL),
(17, 8, 'advisor_assignment', 'Faculty Advisor Assigned', 'Faculty Demo is now advising your team "Bengali NLP Lab".', 'team', 3, 0, '2026-08-14 15:00:00', NULL),
(18, 10, 'advisor_assignment', 'Faculty Advisor Assigned', 'Faculty Demo is now advising your team "Bengali NLP Lab".', 'team', 3, 1, '2026-08-14 15:00:00', '2026-08-15 09:00:00'),
(19, 11, 'advisor_request', 'New Advisor Request', 'Sadia Rahman requested you as a Technical Mentor.', 'advisor_request', 2, 0, '2026-09-15 11:20:00', NULL),
(20, 6, 'advisor_request', 'Advisor Request Declined', 'Dr. Farzana Yasmin declined your advisor request. Reason: My individual-mentee capacity is full this trimester — please reach out again next term.', 'advisor_request', 4, 0, '2026-09-05 09:30:00', NULL),
(21, 11, 'advisor_request', 'New Advisor Request', 'Mehedi Hasan requested you as a Research Mentor.', 'advisor_request', 5, 1, '2026-09-16 09:00:00', '2026-09-16 10:00:00'),
(22, 9, 'advisor_request', 'Clarification Requested', 'Dr. Kazi Shibli Zaman asked a question about your advisor request.', 'advisor_request', 5, 0, '2026-09-18 12:00:00', NULL),
(23, 16, 'advisor_request', 'New Team Advisor Request', 'Team "Smart IoT Innovators" requested you as a FYDP Supervisor.', 'advisor_request', 6, 0, '2026-07-20 10:00:00', NULL),
(24, 4, 'connection_request', 'Connection Accepted', 'Faculty Demo connected with you.', 'connection_request', 2, 1, '2026-08-10 10:05:00', '2026-08-10 12:00:00'),
(25, 11, 'connection_request', 'New Connection Request', 'Student Demo wants to connect with you.', 'connection_request', 1, 0, '2026-09-20 08:30:00', NULL),
(26, 4, 'direct_message', 'New Message', 'Faculty Demo sent you a message.', 'direct_message', 2, 0, '2026-08-11 09:05:00', NULL),
(27, 1, 'direct_message', 'New Message', 'Faculty Demo sent you a message.', 'direct_message', 2, 0, '2026-08-14 15:10:00', NULL);

-- =====================================================================
-- Admin Portal — demo data.
-- Requires database/migrations_006_admin_portal.sql to have been
-- imported first (faculty_verifications, platform_settings, and the
-- community_posts/community_comments.is_hidden columns).
--
-- Adds a 2nd admin account (so the "cannot deactivate the last active
-- admin" rule has something real to demonstrate against the *original*
-- admin), a 5th faculty account seeded specifically in a PENDING
-- verification state (the other 4 faculty are marked verified so this
-- pass does not lock any of them out), two students set to
-- inactive/suspended so the admin user-management filters have real
-- data on first look, and the 7 platform_settings defaults matching
-- this codebase's existing hard-coded behavior exactly (so turning the
-- Admin Portal on changes nothing until an admin deliberately edits a
-- setting).
-- =====================================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(17, 'Second Admin', 'admin2@example.com', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'admin', 'active', '2026-06-01 09:10:00', '2026-09-01 09:00:00', '2026-06-01 09:10:00', '2026-06-01 09:10:00'),
(18, 'Dr. Nasrin Akter', 'nasrin.akter@cse.uiu.ac.bd', '$2b$10$skYHSQsOSXnLQ7XCsRjnOeqI2PCnINhBAlpdnL10B5FKPOPa10Usi', 'faculty', 'active', NULL, NULL, '2026-09-20 10:00:00', '2026-09-20 10:00:00');

INSERT INTO `faculty_profiles` (`id`, `user_id`, `faculty_id`, `department`, `designation`, `specialization`, `office_location`, `phone`, `bio`) VALUES
(5, 18, 'FAC-1005', 'Computer Science and Engineering', 'Assistant Professor', 'Software Engineering, Empirical Software Studies', 'Room 315, UIU CSE Building', '+8801711000005', 'Dr. Nasrin Akter recently joined UIU and researches software engineering practices in student-led development teams. Her faculty account is awaiting administrator verification.');

-- ---------------------------------------------------------------------
-- Faculty verification records — the 4 previously-seeded faculty are
-- already verified so this pass does not lock them out; the newest
-- faculty member (above) is left pending to demonstrate the review
-- queue.
-- ---------------------------------------------------------------------
INSERT INTO `faculty_verifications` (`faculty_user_id`, `status`, `admin_notes`, `verified_by`, `verified_at`, `created_at`) VALUES
(2, 'verified', 'Verified at initial platform rollout.', 3, '2026-06-02 09:00:00', '2026-06-01 09:05:00'),
(11, 'verified', 'Verified at initial platform rollout.', 3, '2026-08-02 09:00:00', '2026-08-01 10:00:00'),
(15, 'verified', 'Verified after department onboarding check.', 3, '2026-08-06 09:00:00', '2026-08-05 09:00:00'),
(16, 'verified', 'Verified after department onboarding check.', 3, '2026-08-07 09:00:00', '2026-08-06 09:00:00'),
(18, 'pending', NULL, NULL, NULL, '2026-09-20 10:05:00');

-- ---------------------------------------------------------------------
-- A couple of non-active user examples (chosen to be peripheral to the
-- advisor/team/chat seed narratives already in this file) so the admin
-- user-management filters have real inactive/suspended rows to show.
-- ---------------------------------------------------------------------
UPDATE `users` SET `status` = 'inactive' WHERE `id` = 5;
UPDATE `users` SET `status` = 'suspended' WHERE `id` = 7;

-- ---------------------------------------------------------------------
-- Platform settings — defaults exactly matching this codebase's
-- existing hard-coded behavior, so enabling the Admin Portal changes
-- nothing until an admin deliberately edits a setting.
-- ---------------------------------------------------------------------
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `updated_by`) VALUES
('public_registration_enabled', '1', 3),
('student_email_domain', 'bscse.uiu.ac.bd', 3),
('default_profile_visibility', 'Students Only', 3),
('default_team_size_limit', '6', 3),
('faculty_verification_required', '0', 3),
('community_creation_policy', 'open', 3),
('maintenance_notice', '', 3);

-- ---------------------------------------------------------------------
-- Activity logs + notifications narrating the admin actions above, so
-- the Admin Dashboard / Activity Logs pages have real recent history.
-- ---------------------------------------------------------------------
INSERT INTO `activity_logs` (`user_id`, `activity_type`, `description`, `related_type`, `related_id`, `created_at`) VALUES
(3, 'admin_faculty_verification', 'Verified faculty account for Dr. Farzana Yasmin', 'faculty_verification', 3, '2026-08-06 09:00:00'),
(3, 'admin_faculty_verification', 'Verified faculty account for Dr. Imran Chowdhury', 'faculty_verification', 4, '2026-08-07 09:00:00'),
(3, 'admin_user_status_change', 'Set Farhana Islam''s account status to inactive', 'user', 5, '2026-09-21 10:00:00'),
(3, 'admin_user_status_change', 'Set Nusrat Jahan''s account status to suspended', 'user', 7, '2026-09-21 10:05:00');

INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `related_type`, `related_id`, `is_read`, `created_at`) VALUES
(18, 'faculty_verification', 'Faculty Verification Pending', 'Your faculty account has been created and is awaiting administrator verification.', 'faculty_verification', 5, 0, '2026-09-20 10:05:00'),
(5, 'account_status', 'Account Status Updated', 'Your account status was changed to "Inactive" by an administrator.', 'user', 5, 0, '2026-09-21 10:00:00'),
(7, 'account_status', 'Account Status Updated', 'Your account status was changed to "Suspended" by an administrator.', 'user', 7, 0, '2026-09-21 10:05:00');

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
