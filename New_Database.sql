-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 20, 2026 at 09:44 AM
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
-- Database: `padak_course_internships`
--

-- --------------------------------------------------------

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
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `type`, `priority`, `batch_id`, `coordinator_id`, `target_all`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to Padak Internship Program!', 'We are thrilled to have you join. Please complete your profile and review your assigned tasks to get started. Your journey to a free internship certificate begins here!', 'general', 'important', NULL, 1, 1, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32'),
(2, 'First Task Released', 'Your first internship task has been assigned. Please check the Tasks section and submit before the deadline. Early submissions get bonus points!', 'task', 'urgent', NULL, 2, 1, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32'),
(3, 'Certificate Policy Update', 'Students who earn 1200+ points and complete all mandatory tasks will receive a FREE internship completion certificate. Top 3 earners get Outstanding grade certificates.', 'certificate', 'important', NULL, 1, 1, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32'),
(4, 'Team Formation Open', 'You can now create or join teams for group tasks. Navigate to the Messenger section to collaborate with your teammates.', 'general', 'normal', NULL, 2, 1, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32');

-- --------------------------------------------------------

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
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `announcement_id`, `student_id`, `read_at`) VALUES
(1, 1, 1, '2024-02-20 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `room_id`, `sender_id`, `message`, `is_deleted`, `created_at`) VALUES
(1, 1, 1, 'Hi.. Testing Messages', 0, '2026-02-20 08:17:15'),
(2, 1, 2, 'Hi Padak..!', 0, '2026-02-20 08:25:19');

-- --------------------------------------------------------

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
-- Dumping data for table `chat_rooms`
--

INSERT INTO `chat_rooms` (`id`, `room_name`, `room_type`, `created_by`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'direct', 1, 1, '2026-02-20 07:56:27', '2026-02-20 07:56:27');

-- --------------------------------------------------------

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
-- Dumping data for table `chat_room_members`
--

INSERT INTO `chat_room_members` (`id`, `room_id`, `student_id`, `last_read_at`, `joined_at`) VALUES
(1, 1, 1, '2026-02-20 14:14:48', '2026-02-20 07:56:27'),
(2, 1, 2, '2026-02-20 13:55:30', '2026-02-20 07:56:27');

-- --------------------------------------------------------

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
-- Dumping data for table `coordinators`
--

INSERT INTO `coordinators` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `department`, `profile_photo`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@padak.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration', NULL, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32'),
(2, 'John Coordinator', 'coordinator@padak.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coordinator', 'Web Development', NULL, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32'),
(3, 'Sarah Mentor', 'mentor@padak.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mentor', 'Data Science', NULL, 1, '2026-02-20 12:18:32', '2026-02-20 12:18:32');

-- --------------------------------------------------------

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
-- Dumping data for table `direct_message_pairs`
--

INSERT INTO `direct_message_pairs` (`id`, `room_id`, `student1_id`, `student2_id`, `created_at`) VALUES
(1, 1, 1, 2, '2026-02-20 07:56:27');

-- --------------------------------------------------------

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
-- Dumping data for table `game_scores`
--

INSERT INTO `game_scores` (`id`, `student_id`, `game_type`, `score`, `level_reached`, `played_at`) VALUES
(1, 1, 'memory_match', 0, 1, '2026-02-20 06:42:58'),
(2, 1, 'reflex_runner', 0, 1, '2026-02-20 06:43:04');

-- --------------------------------------------------------

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
-- Dumping data for table `internship_batches`
--

INSERT INTO `internship_batches` (`id`, `batch_name`, `domain`, `start_date`, `end_date`, `max_students`, `description`, `is_active`, `created_at`) VALUES
(1, 'Web Dev Batch 2025-A', 'Web Development', '2025-06-01', '2025-08-31', 50, 'Full Stack Web Development Internship', 1, '2026-02-20 12:10:38'),
(2, 'Data Science Batch 2025-A', 'Data Science', '2025-06-01', '2025-08-31', 50, 'Data Science & ML Internship', 1, '2026-02-20 12:10:38'),
(3, 'UI/UX Batch 2025-A', 'UI/UX Design', '2025-07-01', '2025-09-30', 50, 'User Interface Design Internship', 1, '2026-02-20 12:10:38');

-- --------------------------------------------------------

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
-- Dumping data for table `internship_certificates`
--

INSERT INTO `internship_certificates` (`id`, `student_id`, `batch_id`, `certificate_number`, `issued_date`, `completion_grade`, `total_points_earned`, `is_issued`, `certificate_url`, `certificate_file`, `created_at`) VALUES
(1, 1, 1, 'CERT-2024-001', '2024-01-15', 'Outstanding', 95, 1, 'https://example.com/certificates/cert-001.pdf', '/certificates/cert-001.pdf', '2024-01-15 10:30:00'),
(2, 2, 1, 'CERT-2024-002', '2024-01-16', 'Excellent', 88, 1, 'https://example.com/certificates/cert-002.pdf', '/certificates/cert-002.pdf', '2024-01-16 11:00:00');

-- --------------------------------------------------------

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `internship_students`
--

INSERT INTO `internship_students` (`id`, `full_name`, `email`, `phone`, `password`, `college_name`, `degree`, `year_of_study`, `domain_interest`, `profile_photo`, `bio`, `linkedin_url`, `github_url`, `remember_token`, `token_expires_at`, `is_active`, `is_online`, `last_seen`, `email_verified`, `total_points`, `internship_status`, `created_at`, `updated_at`) VALUES
(1, 'Padak', 'padak.service@gmail.com', '+91774433757', '$2y$10$D1DfloUO.t9q4hv6b.GssOXSsAasw8Bd6dc4kxik7ae3w2ugzIwTC', 'Sri Lanka', 'CSE', '2nd Year', 'Digital Marketing', 'https://png.pngtree.com/png-clipart/20231020/original/pngtree-avatar-of-a-brunette-man-png-image_13379741.png', '', '', '', NULL, NULL, 1, 1, '2026-02-20 14:14:48', 0, 179, 'active', '2026-02-20 12:11:39', '2026-02-20 14:14:48'),
(2, 'Admin', 'a@gmail.com', '+91774433757', '$2y$10$0JdgpqHpr7f4s5OcFm7ee.mmaK9HJiGsPQ6JnY2qSoKoXJy.JYFl2', 'Sri Lanka', 'CSE', '4th Year', 'Digital Marketing', 'https://png.pngtree.com/png-clipart/20241117/original/pngtree-business-women-avatar-png-image_17163554.png', '', '', '', NULL, NULL, 1, 1, '2026-02-20 13:55:30', 0, 100, 'active', '2026-02-20 12:50:11', '2026-02-20 13:55:30');

-- --------------------------------------------------------

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
-- Dumping data for table `internship_tasks`
--

INSERT INTO `internship_tasks` (`id`, `title`, `description`, `task_type`, `batch_id`, `team_id`, `assigned_to_student`, `due_date`, `max_points`, `priority`, `status`, `resources_url`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Test', 'Database error', 'individual', NULL, NULL, NULL, '2026-02-19 00:00:00', 100, 'medium', 'active', 'http://localhost/Internships/admin.php', 'Admin', '2026-02-20 12:46:09', '2026-02-20 12:46:09'),
(2, 'Total Value terst', 'Testing all data', 'team', NULL, NULL, 1, '2026-02-20 00:00:00', 100, 'urgent', 'active', '', 'Admin', '2026-02-20 13:46:34', '2026-02-20 13:46:34');

-- --------------------------------------------------------

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
-- Dumping data for table `student_attendance`
--

INSERT INTO `student_attendance` (`id`, `student_id`, `batch_id`, `date`, `status`, `enrolled_date`, `marked_by`) VALUES
(1, 1, NULL, '2026-02-20', 'present', '2026-02-20 07:23:28', NULL),
(2, 1, NULL, '2026-02-19', 'present', '2026-02-20 07:23:41', NULL),
(3, 2, NULL, '2026-02-20', 'present', '2026-02-20 07:23:28', NULL),
(4, 2, NULL, '2026-02-19', 'present', '2026-02-20 07:23:41', NULL);

-- --------------------------------------------------------

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
-- Dumping data for table `student_batch_enrollments`
--

INSERT INTO `student_batch_enrollments` (`id`, `student_id`, `batch_id`, `enrolled_at`) VALUES
(1, 1, 1, '2024-02-20 09:00:00');

-- --------------------------------------------------------

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
-- Dumping data for table `student_login_logs`
--

INSERT INTO `student_login_logs` (`id`, `student_id`, `email`, `status`, `ip_address`, `logged_at`) VALUES
(1, 1, 'padak.service@gmail.com', 'success', '::1', '2026-02-20 12:11:53'),
(2, 1, 'padak.service@gmail.com', 'success', '::1', '2026-02-20 12:40:43'),
(3, 2, 'a@gmail.com', 'success', '::1', '2026-02-20 12:50:25'),
(4, 1, 'padak.service@gmail.com', 'success', '::1', '2026-02-20 13:11:06'),
(5, 1, 'padak.service@gmail.com', 'success', '::1', '2026-02-20 13:19:32'),
(6, 2, 'a@gmail.com', 'success', '::1', '2026-02-20 13:55:01'),
(7, 1, 'padak.service@gmail.com', 'success', '::1', '2026-02-20 13:55:53');

-- --------------------------------------------------------

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
-- Dumping data for table `student_notifications`
--

INSERT INTO `student_notifications` (`id`, `student_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'Task Submitted', 'Your submission for \"Test\" has been received.', 'task', NULL, 1, '2026-02-20 12:47:02'),
(2, 1, 'Submission Reviewed', 'Your submission for \"Test\" has been approved! You earned 90 points.', 'task', NULL, 1, '2026-02-20 12:47:54'),
(3, 2, 'Task Submitted', 'Your submission for \"Test\" has been received.', 'task', NULL, 1, '2026-02-20 12:51:58'),
(4, 2, 'Submission Reviewed', 'Your submission for \"Test\" requires revision. Check feedback.', 'task', NULL, 1, '2026-02-20 12:54:12'),
(5, 2, 'Task Submitted', 'Your submission for \"Test\" has been received.', 'task', NULL, 1, '2026-02-20 12:54:39'),
(6, 2, 'Submission Reviewed', 'Your submission for \"Test\" has been approved! You earned 100 points.', 'task', NULL, 1, '2026-02-20 13:45:57'),
(7, 1, 'Task Submitted', 'Your submission for \"Total Value terst\" has been received.', 'task', NULL, 1, '2026-02-20 13:47:32'),
(8, 1, 'Submission Reviewed', 'Your submission for \"Total Value terst\" has been approved! You earned 89 points.', 'task', NULL, 1, '2026-02-20 13:48:06');

-- --------------------------------------------------------

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
-- Dumping data for table `student_points_log`
--

INSERT INTO `student_points_log` (`id`, `student_id`, `points`, `reason`, `task_id`, `awarded_at`) VALUES
(1, 1, 90, 'Earned from task: Test', 1, '2026-02-20 12:47:54'),
(2, 2, 100, 'Earned from task: Test', 1, '2026-02-20 13:45:57'),
(3, 1, 89, 'Earned from task: Total Value terst', 2, '2026-02-20 13:48:06');

-- --------------------------------------------------------

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
-- Dumping data for table `task_submissions`
--

INSERT INTO `task_submissions` (`id`, `task_id`, `student_id`, `submission_text`, `submission_url`, `github_link`, `file_path`, `file_name`, `status`, `points_earned`, `feedback`, `reviewed_by`, `reviewed_at`, `submitted_at`, `updated_at`) VALUES
(1, 1, 1, 'Resumbit test', 'http://localhost/Internships/submit.php?task_id=1', 'http://localhost/Internships/submit.php', 'uploads/submissions/sub_1_1_1771571822.jpg', 'vigneshg_profile.jpg', 'approved', 90, 'Goiod', 'Admin', '2026-02-20 12:47:54', '2026-02-20 12:47:02', '2026-02-20 12:47:54'),
(2, 1, 2, 'Test 2 profile', 'http://localhost/Internships/submit.php?task_id=1', 'http://localhost/Internships/submit.php', 'uploads/submissions/sub_2_1_1771572118.jpg', 'naruto-kakashi.jpg', 'approved', 100, 'Good', 'Admin', '2026-02-20 13:45:57', '2026-02-20 12:54:39', '2026-02-20 13:45:57'),
(3, 2, 1, 'Greart', '', '', 'uploads/submissions/sub_1_2_1771575452.jpg', 'rock-lee.jpg', 'approved', 89, '', 'Admin', '2026-02-20 13:48:06', '2026-02-20 13:47:32', '2026-02-20 13:48:06');

-- --------------------------------------------------------

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
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `team_name`, `batch_id`, `team_code`, `description`, `max_members`, `created_by`, `created_at`) VALUES
(1, 'Alpha Team', 1, 'TEAM-ALPHA-01', 'A high-performing team focused on web development projects', 5, 1, '2024-02-20 10:00:00');

-- --------------------------------------------------------

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
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `student_id`, `role`, `joined_at`) VALUES
(1, 1, 1, 'leader', '2024-02-20 10:30:00');

--
-- Indexes for dumped tables
--

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
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_created_at` (`created_at`);

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
  ADD KEY `idx_student_id` (`student_id`);

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
  ADD KEY `idx_online_students` (`is_online`,`last_seen`);

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
-- Indexes for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enroll` (`student_id`,`batch_id`),
  ADD KEY `batch_id` (`batch_id`);

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
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_points_log`
--
ALTER TABLE `student_points_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `task_id` (`task_id`);

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
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_submission_review` (`status`,`submitted_at`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT for table `student_attendance`
--
ALTER TABLE `student_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_points_log`
--
ALTER TABLE `student_points_log`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD CONSTRAINT `fk_attendance_batch` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_batch_enrollments`
--
ALTER TABLE `student_batch_enrollments`
  ADD CONSTRAINT `student_batch_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_batch_enrollments_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `internship_batches` (`id`) ON DELETE CASCADE;

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

-- MESSENGER POGE CODE ALTER
-- Add columns to existing tables for new features

-- Add attachment and reply columns to chat_messages table
ALTER TABLE `chat_messages` 
ADD COLUMN `reply_to_id` INT(11) NULL DEFAULT NULL AFTER `message`,
ADD COLUMN `attachment_path` VARCHAR(500) NULL DEFAULT NULL AFTER `reply_to_id`,
ADD COLUMN `attachment_type` ENUM('image','file') NULL DEFAULT NULL AFTER `attachment_path`,
ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_type`,
ADD INDEX `idx_reply_to` (`reply_to_id`),
ADD FOREIGN KEY (`reply_to_id`) REFERENCES `chat_messages`(`id`) ON DELETE SET NULL;

-- Create reactions table (uses existing internship_students table)
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `emoji` VARCHAR(10) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`, `student_id`, `emoji`),
  KEY `idx_message` (`message_id`),
  KEY `idx_student` (`student_id`),
  FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `internship_students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better query performance
ALTER TABLE `chat_messages` ADD INDEX `idx_room_created` (`room_id`, `created_at`);
ALTER TABLE `chat_messages` ADD INDEX `idx_sender` (`sender_id`);



-- =====================================================
-- SOCIAL FEED TABLE - Single table design
-- =====================================================
-- This single table handles posts, likes, and comments
-- item_type column determines the type of record
-- parent_id links comments and likes to their parent post
-- =====================================================

CREATE TABLE IF NOT EXISTS `social_feed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `item_type` enum('post','like','comment') NOT NULL DEFAULT 'post',
  `content` text DEFAULT NULL,
  `media_path` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_parent_type` (`parent_id`, `item_type`),
  CONSTRAINT `fk_social_feed_parent` FOREIGN KEY (`parent_id`) REFERENCES `social_feed` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_social_feed_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE STRUCTURE EXPLANATION:
-- =====================================================
-- 
-- POSTS (item_type='post'):
--   - parent_id: NULL (posts are top-level)
--   - content: Post text content
--   - media_path: Path to uploaded image/video
--   - media_type: 'image' or 'video'
--
-- LIKES (item_type='like'):
--   - parent_id: ID of the post being liked
--   - content: NULL
--   - media_path: NULL
--   - media_type: NULL
--
-- COMMENTS (item_type='comment'):
--   - parent_id: ID of the post being commented on
--   - content: Comment text
--   - media_path: NULL (comments don't support media)
--   - media_type: NULL
--
-- BENEFITS:
--   - Single table reduces complexity
--   - Easy to query related data
--   - Efficient indexing
--   - Scalable for future features (shares, reactions, etc.)
--   - Maintains referential integrity
--
-- =====================================================

-- Sample queries for reference:

-- Get all posts with like and comment counts:
/*
SELECT 
  sf.*,
  s.full_name,
  s.profile_photo,
  (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='like' AND is_deleted=0) as likes_count,
  (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='comment' AND is_deleted=0) as comments_count
FROM social_feed sf
JOIN internship_students s ON s.id = sf.student_id
WHERE sf.item_type='post' AND sf.is_deleted=0
ORDER BY sf.created_at DESC;
*/

-- Get all comments for a specific post:
/*
SELECT sf.*, s.full_name, s.profile_photo
FROM social_feed sf
JOIN internship_students s ON s.id = sf.student_id
WHERE sf.parent_id = ? AND sf.item_type='comment' AND sf.is_deleted=0
ORDER BY sf.created_at ASC;
*/

-- Check if user has liked a post:
/*
SELECT COUNT(*) as has_liked
FROM social_feed
WHERE parent_id = ? AND student_id = ? AND item_type='like' AND is_deleted=0;
*/