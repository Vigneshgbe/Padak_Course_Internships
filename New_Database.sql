-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 11:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('general','task','deadline','certificate','urgent','event') DEFAULT 'general',
  `priority` enum('urgent','important','normal') DEFAULT 'normal',
  `batch_id` int(11) DEFAULT NULL,
  `coordinator_id` int(11) DEFAULT NULL,
  `target_all` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT '?' COMMENT 'Single emoji character',
  `tier` enum('bronze','silver','gold','platinum','diamond') DEFAULT 'bronze',
  `category` varchar(100) DEFAULT 'general',
  `points_bonus` int(11) DEFAULT 0,
  `awarded_for` text DEFAULT NULL COMMENT 'Criteria description shown to students',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `attachment_type` enum('image','file') DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `room_type` enum('direct','group') DEFAULT 'group',
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `chat_room_members`
--

CREATE TABLE `chat_room_members` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','coordinator','mentor') DEFAULT 'coordinator',
  `department` varchar(150) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `direct_message_pairs`
--

CREATE TABLE `direct_message_pairs` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student1_id` int(11) NOT NULL,
  `student2_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `game_scores`
--

CREATE TABLE `game_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `game_type` varchar(50) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `level_reached` int(11) DEFAULT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `internship_batches`
--

CREATE TABLE `internship_batches` (
  `id` int(11) NOT NULL,
  `batch_name` varchar(150) NOT NULL,
  `domain` varchar(150) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `max_students` int(11) DEFAULT 50,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `internship_certificates`
--

CREATE TABLE `internship_certificates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `completion_grade` enum('Outstanding','Excellent','Good','Satisfactory') DEFAULT 'Good',
  `total_points_earned` int(11) DEFAULT 0,
  `is_issued` tinyint(1) DEFAULT 0,
  `certificate_url` varchar(500) DEFAULT NULL,
  `certificate_file` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `internship_students`
--

CREATE TABLE `internship_students` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `degree` varchar(150) DEFAULT NULL,
  `year_of_study` enum('1st Year','2nd Year','3rd Year','4th Year','Graduate') DEFAULT '1st Year',
  `domain_interest` varchar(150) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `total_points` int(11) DEFAULT 0,
  `internship_status` enum('pending','active','completed','withdrawn') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_code` varchar(255) DEFAULT NULL,
  `reset_code_expires` datetime DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `internship_tasks`
--

CREATE TABLE `internship_tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `task_type` enum('individual','team','batch') DEFAULT 'individual',
  `batch_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `assigned_to_student` int(11) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `max_points` int(11) DEFAULT 100,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('active','draft','closed') DEFAULT 'active',
  `resources_url` varchar(500) DEFAULT NULL,
  `created_by` varchar(150) DEFAULT 'Coordinator',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `social_feed`
--

CREATE TABLE `social_feed` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `item_type` enum('post','like','comment') NOT NULL DEFAULT 'post',
  `content` text DEFAULT NULL,
  `media_path` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_viewed` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_attendance`
--

CREATE TABLE `student_attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('active','inactive','completed','dropped','present','absent','late') DEFAULT 'active',
  `enrolled_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `marked_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_badges`
--

CREATE TABLE `student_badges` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `award_note` text DEFAULT NULL COMMENT 'Admin note / reason for awarding',
  `awarded_by` varchar(150) DEFAULT 'Admin',
  `awarded_at` datetime DEFAULT current_timestamp(),
  `viewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_batch_enrollments`
--

CREATE TABLE `student_batch_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `enrolled_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_feed_views`
--

CREATE TABLE `student_feed_views` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `last_viewed_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_login_logs`
--

CREATE TABLE `student_login_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_notifications`
--

CREATE TABLE `student_notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('task','message','grade','certificate','announcement','system') DEFAULT 'system',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_points_log`
--

CREATE TABLE `student_points_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `awarded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `student_rewards`
--

CREATE TABLE `student_rewards` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reward_type` enum('mentorship','software','resource','perk','bonus') NOT NULL DEFAULT 'bonus',
  `title` varchar(150) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL COMMENT 'Short description',
  `icon` varchar(50) DEFAULT '?' COMMENT 'Emoji or icon class',
  `color` varchar(20) DEFAULT 'orange' COMMENT 'Theme color: orange, blue, green, purple, pink',
  `value` varchar(80) DEFAULT NULL COMMENT 'e.g., "60 min", "7 days", "Lifetime"',
  `instructions` text DEFAULT NULL COMMENT 'Brief redemption steps',
  `code` varchar(50) DEFAULT NULL COMMENT 'Redemption code (auto-generated)',
  `awarded_for` varchar(255) DEFAULT NULL COMMENT 'Achievement reason',
  `status` enum('locked','unlocked','activate_requested','activated','claimed') NOT NULL DEFAULT 'locked',
  `awarded_at` datetime DEFAULT current_timestamp(),
  `unlocked_at` datetime DEFAULT NULL,
  `activation_requested_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `activated_by` varchar(100) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `awarded_by` varchar(100) DEFAULT 'Admin',
  `priority` tinyint(1) DEFAULT 0 COMMENT '0=normal, 1=featured/urgent',
  `position` int(11) DEFAULT 0 COMMENT 'Box order/position',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `student_sessions`
--

CREATE TABLE `student_sessions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_submissions`
--

CREATE TABLE `task_submissions` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `submission_url` varchar(500) DEFAULT NULL,
  `github_link` varchar(500) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected','revision_requested') DEFAULT 'submitted',
  `points_earned` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `reviewed_by` varchar(150) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `team_name` varchar(150) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `team_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_members` int(11) DEFAULT 5,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `role` enum('leader','member') DEFAULT 'member',
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_active` (`is_active`),
  ADD KEY `idx_announcements_priority` (`priority`),
  ADD KEY `idx_announcements_batch` (`batch_id`),
  ADD KEY `fk_announcements_coordinator` (`coordinator_id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`announcement_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tier` (`tier`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reply_to` (`reply_to_id`),
  ADD KEY `idx_room_created` (`room_id`,`created_at`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_room_sender_created` (`room_id`,`sender_id`,`created_at`);

--
-- Indexes for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_type` (`room_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `fk_chat_rooms_creator` (`created_by`);

--
-- Indexes for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_member` (`room_id`,`student_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_student_room` (`student_id`,`room_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `direct_message_pairs`
--
ALTER TABLE `direct_message_pairs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dm_pair` (`student1_id`,`student2_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_student2` (`student2_id`);

--
-- Indexes for table `game_scores`
--
ALTER TABLE `game_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `internship_batches`
--
ALTER TABLE `internship_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `internship_certificates`
--
ALTER TABLE `internship_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `idx_cert_verification` (`certificate_number`,`is_issued`);

--
-- Indexes for table `internship_students`
--
ALTER TABLE `internship_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_student_points` (`total_points`),
  ADD KEY `idx_online_students` (`is_online`,`last_seen`),
  ADD KEY `idx_batch` (`batch_id`);

--
-- Indexes for table `internship_tasks`
--
ALTER TABLE `internship_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `assigned_to_student` (`assigned_to_student`),
  ADD KEY `idx_task_status_due` (`status`,`due_date`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`student_id`,`emoji`),
  ADD KEY `idx_message` (`message_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `social_feed`
--
ALTER TABLE `social_feed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_parent_type` (`parent_id`,`item_type`),
  ADD KEY `idx_student_viewed` (`student_id`,`is_viewed`,`created_at`),
  ADD KEY `idx_student_created_deleted` (`student_id`,`created_at`,`is_deleted`);

--
-- Indexes for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_date` (`student_id`,`date`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_badge` (`student_id`,`badge_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_badge_id` (`badge_id`),
  ADD KEY `idx_viewed` (`student_id`,`viewed_at`),
  ADD KEY `idx_student_viewed` (`student_id`,`viewed_at`);

--
-- Indexes for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enroll` (`student_id`,`batch_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `student_feed_views`
--
ALTER TABLE `student_feed_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student` (`student_id`);

--
-- Indexes for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_unread` (`student_id`,`is_read`,`created_at`);

--
-- Indexes for table `student_points_log`
--
ALTER TABLE `student_points_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `student_rewards`
--
ALTER TABLE `student_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student_position` (`student_id`,`position`);

--
-- Indexes for table `student_sessions`
--
ALTER TABLE `student_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `task_submissions`
--
ALTER TABLE `task_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`task_id`,`student_id`),
  ADD KEY `idx_submission_review` (`status`,`submitted_at`),
  ADD KEY `idx_student_task` (`student_id`,`task_id`,`status`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_code` (`team_code`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_member` (`team_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `direct_message_pairs`
--
ALTER TABLE `direct_message_pairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `game_scores`
--
ALTER TABLE `game_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `internship_batches`
--
ALTER TABLE `internship_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `internship_certificates`
--
ALTER TABLE `internship_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `internship_students`
--
ALTER TABLE `internship_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `internship_tasks`
--
ALTER TABLE `internship_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `social_feed`
--
ALTER TABLE `social_feed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_attendance`
--
ALTER TABLE `student_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_badges`
--
ALTER TABLE `student_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_feed_views`
--
ALTER TABLE `student_feed_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `student_points_log`
--
ALTER TABLE `student_points_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_rewards`
--
ALTER TABLE `student_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_sessions`
--
ALTER TABLE `student_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_submissions`
--
ALTER TABLE `task_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_announcements_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`reply_to_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chat_messages_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  ADD CONSTRAINT `fk_chat_room_members_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_room_members_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internship_certificates`
--
ALTER TABLE `internship_certificates`
  ADD CONSTRAINT `internship_certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internship_certificates_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `internship_tasks`
--
ALTER TABLE `internship_tasks`
  ADD CONSTRAINT `internship_tasks_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `internship_tasks_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `internship_tasks_ibfk_3` FOREIGN KEY (`assigned_to_student`) REFERENCES `internship_students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `social_feed`
--
ALTER TABLE `social_feed`
  ADD CONSTRAINT `fk_social_feed_parent` FOREIGN KEY (`parent_id`) REFERENCES `social_feed` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_social_feed_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD CONSTRAINT `fk_attendance_batch` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD CONSTRAINT `fk_sb_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sb_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  ADD CONSTRAINT `student_batch_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_batch_enrollments_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_feed_views`
--
ALTER TABLE `student_feed_views`
  ADD CONSTRAINT `student_feed_views_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  ADD CONSTRAINT `student_login_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD CONSTRAINT `student_notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_points_log`
--
ALTER TABLE `student_points_log`
  ADD CONSTRAINT `student_points_log_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_points_log_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `internship_tasks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_rewards`
--
ALTER TABLE `student_rewards`
  ADD CONSTRAINT `fk_rewards_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_sessions`
--
ALTER TABLE `student_sessions`
  ADD CONSTRAINT `student_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_submissions`
--
ALTER TABLE `task_submissions`
  ADD CONSTRAINT `task_submissions_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `internship_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teams_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `internship_students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
