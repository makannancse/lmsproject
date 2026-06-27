-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 27, 2026 at 10:53 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `class_attendance`
--

DROP TABLE IF EXISTS `class_attendance`;
CREATE TABLE IF NOT EXISTS `class_attendance` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `role` enum('teacher','student') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `joined_at` datetime DEFAULT NULL,
  `left_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_class_attendance_user` (`class_id`,`user_id`,`role`),
  KEY `fk_class_attendance_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_attendance`
--

INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES
(3, 18, 3, 'teacher', '2026-06-20 15:18:15', '2026-06-20 15:18:21', '2026-06-20 15:18:17', '2026-06-20 15:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `class_master`
--

DROP TABLE IF EXISTS `class_master`;
CREATE TABLE IF NOT EXISTS `class_master` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_master`
--

INSERT INTO `class_master` (`id`, `class_name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Dance Class', NULL, 'active', '2026-04-11 07:04:55', '2026-04-11 07:04:55'),
(2, 'Music Class', NULL, 'active', '2026-04-11 07:05:05', '2026-04-11 07:05:05'),
(3, 'Maths', NULL, 'active', '2026-04-11 07:05:17', '2026-04-11 07:05:17'),
(4, 'English Class', 'Test', 'active', '2026-04-19 07:50:34', '2026-04-19 07:50:34');

-- --------------------------------------------------------

--
-- Table structure for table `class_recordings`
--

DROP TABLE IF EXISTS `class_recordings`;
CREATE TABLE IF NOT EXISTS `class_recordings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `recording_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recording_file_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recording_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recording_duration` int DEFAULT NULL,
  `visible_to_student` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `sync_status` enum('pending','processing','ready','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` enum('google_drive','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google_drive',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_class_recordings_class` (`class_id`),
  KEY `fk_class_recordings_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_sessions`
--

DROP TABLE IF EXISTS `class_sessions`;
CREATE TABLE IF NOT EXISTS `class_sessions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `class_master_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recurrence_parent_id` int UNSIGNED DEFAULT NULL,
  `recurrence_rule` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `recurring_series_id` int UNSIGNED DEFAULT NULL,
  `recurring_occurrence_id` int UNSIGNED DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `scheduled_time_utc` datetime DEFAULT NULL,
  `start_time_utc` datetime DEFAULT NULL,
  `end_datetime` datetime NOT NULL,
  `end_time_utc` datetime DEFAULT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `scheduled_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `status` enum('scheduled','ongoing','completed','cancelled','rescheduled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `meeting_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_event_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_google_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_meet_space_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_meeting_code` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_conference_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_live_status` enum('pending','active','ended','sync_error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `meeting_participant_count` int DEFAULT NULL,
  `payout_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Teacher payout when class completed',
  `student_fee` decimal(10,0) NOT NULL DEFAULT '0',
  `teacher_payout` decimal(10,0) NOT NULL,
  `zoom_meeting_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zoom_join_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `zoom_start_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `actual_duration` int DEFAULT NULL,
  `actual_duration_minutes` int DEFAULT NULL,
  `recording_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recording_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `recording_sync_status` enum('pending','processing','ready','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `recording_sync_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recording_synced_at` datetime DEFAULT NULL,
  `teacher_joined_at` datetime DEFAULT NULL,
  `teacher_join_delay_minutes` int DEFAULT NULL,
  `recording_acknowledged_at` datetime DEFAULT NULL,
  `recording_acknowledged_by` int UNSIGNED DEFAULT NULL,
  `student_joined_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_class_sessions_teacher` (`teacher_id`),
  KEY `fk_class_sessions_class_master` (`class_master_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_sessions`
--

INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `recurrence_parent_id`, `recurrence_rule`, `recurrence_end_date`, `recurring_series_id`, `recurring_occurrence_id`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `teacher_join_delay_minutes`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES
(18, 3, 1, 'Test Dance', 'Test', NULL, NULL, NULL, NULL, NULL, '2026-06-19 05:00:00', '2026-06-19 05:00:00', '2026-06-19 05:00:00', '2026-06-19 06:00:00', '2026-06-19 06:00:00', 'Asia/Kolkata', 'Asia/Kolkata', 'completed', 'https://meet.google.com/moq-oyjg-pyb', 'ukf6cf78d7h6rjtj4t9osdfpbo', 'kannanandhu99@gmail.com', 'spaces/CQuvcE2ZpKwB', 'moq-oyjg-pyb', 'conferenceRecords/lUTdzNEVgg7XQEpXPmCuDxIYOAIIigIgABgECA', 'ended', 1, 50.00, 1000, 0, NULL, NULL, NULL, '2026-06-18 08:30:41', '2026-06-20 15:18:31', '2026-06-20 15:18:21', '2026-06-20 15:18:15', '2026-06-20 15:18:21', 0, 0, NULL, 0, 'pending', NULL, NULL, '2026-06-20 15:18:15', 2058, '2026-06-20 15:15:23', 3, NULL),
(19, 3, 3, 'Maths Class', 'Test', NULL, NULL, NULL, NULL, NULL, '2026-06-20 14:54:00', '2026-06-20 14:54:00', '2026-06-20 14:54:00', '2026-06-20 15:54:00', '2026-06-20 15:54:00', 'Asia/Kolkata', 'Asia/Kolkata', 'scheduled', 'https://meet.google.com/fyu-igpv-pmz', '046j4t28mbnpjdjpvjuqah7od0', 'kannanandhu99@gmail.com', 'spaces/YvclKWCn2I0B', 'fyu-igpv-pmz', NULL, 'pending', NULL, 100.00, 1000, 0, NULL, NULL, NULL, '2026-06-20 14:54:43', '2026-06-20 14:54:43', NULL, NULL, NULL, NULL, NULL, NULL, 0, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `status` enum('active','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_enrollment` (`class_id`,`student_id`),
  KEY `fk_enrollments_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES
(18, 18, 6, 'active', '2026-06-18 08:30:41', '2026-06-18 08:30:41'),
(19, 19, 6, 'active', '2026-06-20 14:54:43', '2026-06-20 14:54:43');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_feedback_pair` (`student_id`,`teacher_id`),
  KEY `fk_fb_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homeworks`
--

DROP TABLE IF EXISTS `homeworks`;
CREATE TABLE IF NOT EXISTS `homeworks` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `due_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `status` enum('pending','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_homeworks_creator` (`created_by`),
  KEY `fk_homeworks_teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homework_assigned_students`
--

DROP TABLE IF EXISTS `homework_assigned_students`;
CREATE TABLE IF NOT EXISTS `homework_assigned_students` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `homework_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hw_student` (`homework_id`,`student_id`),
  KEY `fk_has_student` (`student_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homework_attachments`
--

DROP TABLE IF EXISTS `homework_attachments`;
CREATE TABLE IF NOT EXISTS `homework_attachments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `homework_id` int UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ha_hw` (`homework_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homework_submissions`
--

DROP TABLE IF EXISTS `homework_submissions`;
CREATE TABLE IF NOT EXISTS `homework_submissions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `homework_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_homework` (`homework_id`,`student_id`),
  KEY `fk_hs_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

DROP TABLE IF EXISTS `log`;
CREATE TABLE IF NOT EXISTS `log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `groupname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `user_notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `patient_id` bigint DEFAULT NULL,
  `success` tinyint(1) DEFAULT '1',
  `checksum` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `crt_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `log_from` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'open-emr',
  `menu_item_id` int DEFAULT NULL,
  `ccda_doc_id` int DEFAULT NULL COMMENT 'CCDA document id from ccda',
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `idx_log_event_user` (`event`,`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_activity_logs`
--

DROP TABLE IF EXISTS `meeting_activity_logs`;
CREATE TABLE IF NOT EXISTS `meeting_activity_logs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `google_participant_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_participant_session_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('teacher','student','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `joined_at` datetime NOT NULL,
  `left_at` datetime DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `source` enum('google_meet_api','workspace_events','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google_meet_api',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_meeting_activity_session` (`google_participant_session_name`),
  KEY `fk_meeting_activity_logs_user` (`user_id`),
  KEY `idx_meeting_activity_class_role_join` (`class_id`,`role`,`joined_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meeting_activity_logs`
--

INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES
(5, 18, 3, 'conferenceRecords/lUTdzNEVgg7XQEpXPmCuDxIYOAIIigIgABgECA/participants/111912129901590285760', 'conferenceRecords/lUTdzNEVgg7XQEpXPmCuDxIYOAIIigIgABgECA/participants/111912129901590285760/participantSessions/569', 'teacher', '2026-06-20 15:18:15', '2026-06-20 15:18:21', 0, 'google_meet_api', '2026-06-20 15:18:17', '2026-06-20 15:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_token` (`token_hash`),
  KEY `idx_password_reset_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 18, '5c8ab3dd6c441cfaa0761d2710139abff02ef2d924cae7f3b5f8952ef44b3ce1', '2026-06-20 16:55:05', '2026-06-20 15:55:51', '2026-06-20 21:25:05');

-- --------------------------------------------------------

--
-- Table structure for table `recurring_occurrences`
--

DROP TABLE IF EXISTS `recurring_occurrences`;
CREATE TABLE IF NOT EXISTS `recurring_occurrences` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `series_id` int UNSIGNED NOT NULL,
  `occurrence_date` date NOT NULL,
  `scheduled_start_utc` datetime NOT NULL,
  `scheduled_end_utc` datetime NOT NULL,
  `actual_start_utc` datetime DEFAULT NULL,
  `actual_end_utc` datetime DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled','missed','rescheduled') NOT NULL DEFAULT 'scheduled',
  `teacher_payment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `class_session_id` int UNSIGNED DEFAULT NULL,
  `google_conference_id` varchar(255) DEFAULT NULL,
  `teacher_joined_at` datetime DEFAULT NULL,
  `teacher_join_delay_minutes` int DEFAULT NULL,
  `student_joined_at` datetime DEFAULT NULL,
  `meeting_live_status` enum('pending','active','ended','sync_error') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_series_occurrence_date` (`series_id`,`occurrence_date`),
  KEY `idx_recurring_occurrences_series` (`series_id`),
  KEY `idx_recurring_occurrences_start` (`scheduled_start_utc`),
  KEY `idx_recurring_occurrences_status` (`status`),
  KEY `fk_recurring_occurrences_class` (`class_session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_series`
--

DROP TABLE IF EXISTS `recurring_series`;
CREATE TABLE IF NOT EXISTS `recurring_series` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `class_master_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `subject` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(512) DEFAULT NULL,
  `google_event_id` varchar(255) DEFAULT NULL,
  `google_meet_space_name` varchar(191) DEFAULT NULL,
  `google_meeting_code` varchar(128) DEFAULT NULL,
  `teacher_google_email` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `scheduled_timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `occurrence_count` int UNSIGNED DEFAULT NULL,
  `teacher_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `student_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','cancelled','completed') NOT NULL DEFAULT 'active',
  `recording_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recurring_series_teacher` (`teacher_id`),
  KEY `idx_recurring_series_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_series_students`
--

DROP TABLE IF EXISTS `recurring_series_students`;
CREATE TABLE IF NOT EXISTS `recurring_series_students` (
  `series_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`series_id`,`student_id`),
  KEY `fk_rss_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reschedule_requests`
--

DROP TABLE IF EXISTS `reschedule_requests`;
CREATE TABLE IF NOT EXISTS `reschedule_requests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `requested_by` enum('student','teacher','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `initiated_by` enum('student','teacher','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `requested_date` date NOT NULL,
  `requested_time` time NOT NULL,
  `old_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `new_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `teacher_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `admin_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rr_class` (`class_id`),
  KEY `fk_rr_student` (`student_id`),
  KEY `fk_rr_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_payment_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_students_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 'India', '', NULL, 0.00, NULL, '2026-03-15 15:38:55', '2026-03-15 15:38:55'),
(2, 7, 'India', '', NULL, 0.00, NULL, '2026-03-21 07:24:49', '2026-06-09 09:11:07'),
(4, 10, 'India', 'narmadhasuresh7@gmail.com', NULL, 0.00, NULL, '2026-04-19 07:46:24', '2026-04-19 07:46:24'),
(5, 13, 'India', 'saranya.capminds@gmail.com', NULL, 0.00, NULL, '2026-05-30 07:08:20', '2026-05-30 07:08:20'),
(8, 18, 'India', 'wkannan756@gmail.com', NULL, 0.00, NULL, '2026-06-20 15:52:55', '2026-06-20 15:52:55');

-- --------------------------------------------------------

--
-- Table structure for table `student_payments`
--

DROP TABLE IF EXISTS `student_payments`;
CREATE TABLE IF NOT EXISTS `student_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int UNSIGNED NOT NULL,
  `class_id` int UNSIGNED NOT NULL,
  `recurring_series_id` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_payments_student` (`student_id`),
  KEY `idx_student_payments_class` (`class_id`),
  KEY `idx_student_payments_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_payments`
--

INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `recurring_series_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES
(18, 6, 18, NULL, 1000.00, 'INR', 'pending', NULL, '2026-06-18 14:00:41'),
(19, 6, 19, NULL, 1000.00, 'INR', 'pending', NULL, '2026-06-20 20:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `student_reports`
--

DROP TABLE IF EXISTS `student_reports`;
CREATE TABLE IF NOT EXISTS `student_reports` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `performance_rating` varchar(50) NOT NULL,
  `understanding_level` varchar(100) NOT NULL,
  `strengths` text,
  `improvements` text,
  `comments` text,
  `report_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) NOT NULL DEFAULT '',
  `student_name` varchar(255) NOT NULL DEFAULT '',
  `teacher_name` varchar(255) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `overall_performance` varchar(100) NOT NULL DEFAULT '',
  `concept_understanding` varchar(100) NOT NULL DEFAULT '',
  `application_ability` varchar(100) NOT NULL DEFAULT '',
  `homework_completion` varchar(100) NOT NULL DEFAULT '',
  `attention_level` varchar(100) NOT NULL DEFAULT '',
  `participation_level` varchar(100) NOT NULL DEFAULT '',
  `behaviour` varchar(100) NOT NULL DEFAULT '',
  `subjects_addressed` text,
  `future_focus` text,
  `recommended_focus` text,
  `study_strategies` text,
  `additional_support` text,
  `overall_feedback` text,
  `pdf_path` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_reports_student_date` (`student_id`,`report_date`),
  KEY `idx_student_reports_teacher_date` (`teacher_id`,`report_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_reports`
--

INSERT INTO `student_reports` (`id`, `student_id`, `teacher_id`, `performance_rating`, `understanding_level`, `strengths`, `improvements`, `comments`, `report_date`, `created_at`, `email`, `student_name`, `teacher_name`, `subject`, `overall_performance`, `concept_understanding`, `application_ability`, `homework_completion`, `attention_level`, `participation_level`, `behaviour`, `subjects_addressed`, `future_focus`, `recommended_focus`, `study_strategies`, `additional_support`, `overall_feedback`, `pdf_path`) VALUES
(1, 6, 3, '', '', NULL, NULL, NULL, '2026-06-20', '2026-06-20 15:20:17', 'kannan1997cse@gmail.com', 'Test Student', 'Kannan M', 'Maths', 'Excellent', 'Strong understanding', 'Applies independently', 'Always on time', 'Easily distracted', 'Moderate', 'Excellent', 'Test', 'Test', 'Test', 'Test', 'Test', 'Test', 'uploads/reports/report_1.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE IF NOT EXISTS `system_config` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES
('admin_notification_email', '', '2026-06-18 06:33:25'),
('google_calendar_id', 'primary', '2026-05-01 08:17:55'),
('google_client_id', '716021670756-arantp7s7kp6rlj10g09ac2on89qb9fe.apps.googleusercontent.com', '2026-05-01 08:17:55'),
('google_client_secret', 'password', '2026-05-01 08:17:55'),
('google_refresh_token', '1//052KelzSHCwkUCgYIARAAGAUSNwF-L9IrKkzTLvBZUJ9n5BKg9OnKwhuxwRFyr1Gv9zdq_rPRoOZVZPbS-Atl_UOf-nxzFW2djjE', '2026-05-01 08:17:55'),
('mail_from_name', 'LMS', '2026-05-01 08:17:55'),
('notify_admin_class_scheduled', '1', '2026-06-18 06:33:25'),
('notify_admin_reschedule', '1', '2026-06-18 06:33:25'),
('notify_teacher_student_assigned', '1', '2026-06-18 06:33:25'),
('payout_rate_full_time', '30', '2026-05-01 08:17:55'),
('payout_rate_part_time', '20', '2026-05-01 08:17:55'),
('payout_rate_per_hour', '20', '2026-05-01 08:17:55'),
('smtp_encryption', 'tls', '2026-05-01 08:17:55'),
('smtp_password', 'password', '2026-04-11 08:11:28'),
('smtp_port', '587', '2026-05-01 08:17:55'),
('smtp_username', 'admin@example.com', '2026-05-01 08:17:55'),
('static_meeting_link', 'https://zoom.us/j/7874901508', '2026-05-01 08:17:55'),
('static_zoom_meeting_link', 'https://zoom.us/j/7874901508', '2026-05-01 08:17:55'),
('zoom_api_key', 'Test', '2026-03-15 15:49:19');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `employment_type` enum('full_time','part_time') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'part_time',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_refresh_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_connected_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_teachers_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'full_time', NULL, NULL, NULL, NULL, '2026-03-15 15:28:32', '2026-03-15 15:28:32'),
(2, 8, 'part_time', NULL, NULL, NULL, NULL, '2026-04-18 10:13:25', '2026-04-18 10:13:25'),
(3, 11, 'part_time', NULL, NULL, NULL, NULL, '2026-04-19 07:48:07', '2026-04-19 07:48:07'),
(4, 12, 'full_time', NULL, NULL, NULL, NULL, '2026-05-16 01:48:39', '2026-05-16 01:48:39'),
(5, 14, 'full_time', NULL, NULL, NULL, NULL, '2026-05-30 07:10:12', '2026-05-30 07:10:12'),
(6, 16, 'part_time', NULL, NULL, NULL, NULL, '2026-06-20 15:12:40', '2026-06-20 15:12:40');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_availability`
--

DROP TABLE IF EXISTS `teacher_availability`;
CREATE TABLE IF NOT EXISTS `teacher_availability` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `weekday` tinyint UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_teacher_availability_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_google_accounts`
--

DROP TABLE IF EXISTS `teacher_google_accounts`;
CREATE TABLE IF NOT EXISTS `teacher_google_accounts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `google_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_person_resource_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_person_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_user_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type` enum('workspace','personal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'workspace',
  `recording_supported` tinyint(1) NOT NULL DEFAULT '1',
  `access_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refresh_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `token_expiry` datetime DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `status` enum('active','disconnected','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tga_teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_google_accounts`
--

INSERT INTO `teacher_google_accounts` (`id`, `teacher_id`, `google_email`, `google_person_resource_name`, `google_person_id`, `google_user_id`, `account_type`, `recording_supported`, `access_token`, `refresh_token`, `token_expiry`, `connected_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'kannanandhu99@gmail.com', 'people/111912129901590285760', '111912129901590285760', '111912129901590285760', 'personal', 0, 'BN4Q8rynT9dRfdmuQ2JQUGRt40l9Raw9knKcGJgG+DfJSaDlvxNqa6KY/IOmlRzbyID5/kmRGw55dnQz8oHV5aXDHyhdHice7DdcOIZ67KLslzl3ojHIoQLAvsEMMcDJg5x8n8/Y8wvIqryl+uO89elXxBxsrJVCgVt1kGzQZ/nRvzJC5Z8vPcZt5OcOWqzJ87X7X38zpQ4gm9MhnEw0i7CEygo3sUgrxJYFzoiGtFs3JbhVluzG7jl9nTR7Hqp394VeHXybSeXVhLtGQsOMuDjFUXwUacHXrqyf7hT0Qq41htc/0t4O+ltv/z0yO/rd9gi2+xPV+nG2SRCN56RkW6D1POW2QoYfj0A/aJQbjro=', 'kP82aVEceqprQ/b91E3vYsXX29Whprzg4M+PmrZRblnfGjPStulCGbKvuX+M7aoNdepjHqR1W+jaNMSInp/NAwT2QvTMVT7ctLnaHv+K1eDjn6fv433YT1bm8Y7cUdKMBv0PDzDxq2ybdX5AH90xKDuG0tcnUzuakp80NgeA2Wo=', '2026-06-20 15:54:39', '2026-05-10 09:40:48', 'active', '2026-05-10 04:10:48', '2026-06-20 14:54:40'),
(2, 12, 'narmadha@edulearnwise.com', 'people/112056070223696545859', '112056070223696545859', '112056070223696545859', 'workspace', 1, 'LH8hN6WvP2aQcEghRsgIfoXVZ2xBAWqzkhKeJry+y6xg0I8EVZRsafp6vzqsrAg+I1u4Cn63fmIrCD9t9aA7dD9xzzIGsvTASB8sxTgPYOosRJe8Wp0kGk0ryZZ2YY6pBaquCzd68XAXN0RR/RsUWoKFST8kXlevprPgSSHWqKB79FX5Fel7LLqQXh3JzcD4Lonm0dbDDFfKliL4OheMIX+xHBLrhOb9vC/nsVRhXLGwTM6amSYlgWj1gJJh1y/VnR37roA0K+VNkBls9GLxeMYF/cMzLeFB39UFA96NVSGOhVwV1158YGMO+97or7hbqzZqe4Q6CQxSqQL9xyFlt1WqeputQ0XRakydfOnYAqY=', 'yBeKYNekuzSRStmYvkUmbzsUpWDHM5fKrg57g6hfTwZf/z+xteq/ZgAtIq1yWKXVlOL+AVP+D2AvfQHz7ltW6C+rGbR958OWHNhqtuEsTgv2sg9q4on/yUgaIwaKqiFQEf5tqx6RP8TO7jDgl/GRUfIyCNt+bvTV34WnEE2YLMQ=', '2026-06-09 10:41:41', '2026-05-16 07:20:12', 'active', '2026-05-16 01:50:12', '2026-06-09 09:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_payments`
--

DROP TABLE IF EXISTS `teacher_payments`;
CREATE TABLE IF NOT EXISTS `teacher_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','partial','paid','advance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_payments_bk`
--

DROP TABLE IF EXISTS `teacher_payments_bk`;
CREATE TABLE IF NOT EXISTS `teacher_payments_bk` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','partial','paid','advance') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_payments_teacher` (`teacher_id`),
  KEY `idx_teacher_payments_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_payment_logs`
--

DROP TABLE IF EXISTS `teacher_payment_logs`;
CREATE TABLE IF NOT EXISTS `teacher_payment_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `class_id` int UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_logs_teacher` (`teacher_id`),
  KEY `idx_payment_logs_class` (`class_id`),
  KEY `idx_payment_logs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `teacher_payment_logs`
--

INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES
(2, 3, 18, 50.00, 'pending', '2026-06-20 15:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_payouts`
--

DROP TABLE IF EXISTS `teacher_payouts`;
CREATE TABLE IF NOT EXISTS `teacher_payouts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `class_id` int UNSIGNED NOT NULL,
  `recurring_occurrence_id` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `calculated_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payout_per_class` (`teacher_id`,`class_id`),
  KEY `fk_teacher_payouts_class` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_payouts`
--

INSERT INTO `teacher_payouts` (`id`, `teacher_id`, `class_id`, `recurring_occurrence_id`, `amount`, `status`, `calculated_at`, `paid_at`, `created_at`, `updated_at`) VALUES
(2, 3, 18, NULL, 50.00, 'pending', '2026-06-20 15:18:31', NULL, '2026-06-20 09:48:31', '2026-06-20 15:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_students`
--

DROP TABLE IF EXISTS `teacher_students`;
CREATE TABLE IF NOT EXISTS `teacher_students` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_teacher_student` (`teacher_id`,`student_id`),
  KEY `fk_ts_student` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `teacher_students`
--

INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES
(16, 3, 7, '2026-06-20 15:13:29'),
(9, 12, 13, '2026-05-30 09:11:35'),
(4, 11, 10, '2026-04-19 07:48:46'),
(5, 11, 6, '2026-04-19 07:48:46'),
(10, 12, 6, '2026-05-30 09:11:35'),
(17, 3, 13, '2026-06-20 15:13:29'),
(18, 3, 6, '2026-06-20 15:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `password_hash` varchar(191) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@example.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'UTC', 'active', '2026-01-06 07:42:18', '2026-01-06 07:42:18'),
(3, 'Kannan M', 'kannanandhu99@gmail.com', NULL, '$2y$10$hIx4SmBjQSu8xo231r/1Ae4wEdbA4/S.jka0IucbrouUZbfHTxROG', 'teacher', 'UTC', 'active', '2026-03-15 15:28:32', '2026-06-09 09:11:27'),
(6, 'Test Student', 'kannan1997cse@gmail.com', NULL, '$2y$10$KW9or8sDmQiyFHmCRaLWEeZwsbu/LTqpJnhXeC/Ul/KdhBUc24I5u', 'student', 'UTC', 'active', '2026-03-15 15:38:55', '2026-03-15 15:38:55'),
(7, 'Nandhini K', 'nandhinibalakrishnan2020@gmail.com', NULL, '$2y$10$RV4GeHRHUdiCp2GONX0rFOVtf9skFIu6uZHPCtw87vnQgMHsv/I7a', 'student', 'Asia/Kolkata', 'active', '2026-03-21 07:24:49', '2026-06-09 09:11:07'),
(8, 'Test Teacher', 'kannan1197cse@gmail.com', NULL, '$2y$10$hIx4SmBjQSu8xo231r/1Ae4wEdbA4/S.jka0IucbrouUZbfHTxROG', 'teacher', 'UTC', 'active', '2026-04-18 10:13:25', '2026-05-01 05:53:25'),
(10, 'Suresh', '2k8.surya@gmail.com', NULL, '$2y$10$Bn9h1CPMK0nwVlAKiZY1.e5WVkLlpSnyFuHSHFDoX7S/MPdk0zLFi', 'student', 'UTC', 'inactive', '2026-04-19 07:46:24', '2026-06-20 15:57:37'),
(11, 'Narmadha', '2k9.surya@gmail.com', NULL, '$2y$10$nvbbFDyl14PTmXRew.fnp.hy36ZNTtAS4A7YfbEgbq04d3VQWp3a6', 'teacher', 'UTC', 'active', '2026-04-19 07:48:07', '2026-04-19 07:48:07'),
(12, 'LearnWise', 'narmadha@edulearnwise.com', NULL, '$2y$10$6nbphf.X1WbIiuoN1oBcNu3LHSgj7c9ycvrDYPXrB9tXQhWlSPEp2', 'teacher', 'UTC', 'active', '2026-05-16 01:48:39', '2026-05-16 01:48:39'),
(13, 'Saranya B', 'saranyab260404@gmail.com', NULL, '$2y$10$OH9AUyJlvsmcmE8bxLYZA.1TySEkitoG5JSlFTN0IHp0O.9YTamUy', 'student', 'UTC', 'active', '2026-05-30 07:08:20', '2026-05-30 07:08:20'),
(14, 'TestTeacher', 'TestTeacher@gmail.com', NULL, '$2y$10$3unp2wurHOS2c8H2Dn704uMeIlMPEInmUjxv6kEHKtm4vR4.ylxpO', 'teacher', 'UTC', 'inactive', '2026-05-30 07:10:12', '2026-06-09 09:11:19'),
(16, 'Maths Teacher', 'wkannan756@gmail.com', NULL, '$2y$10$A1urBQOjMv/fK3rfeJyF3O9wDLXMyEDlyJyQTRGPxR/vSeD9Fvw0.', 'teacher', 'Asia/Kolkata', 'active', '2026-06-20 15:12:40', '2026-06-20 15:12:40'),
(18, 'Karthi', 'learnwiseed629@gmail.com', NULL, '$2y$10$I0/3j7KSlULVCuZIjBFwyOUxJw8NDrCot3mGTN0vZcgxztJNZkHBK', 'student', 'Asia/Kolkata', 'active', '2026-06-20 15:52:55', '2026-06-20 15:55:51');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `class_attendance`
--
ALTER TABLE `class_attendance`
  ADD CONSTRAINT `fk_class_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_class_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_recordings`
--
ALTER TABLE `class_recordings`
  ADD CONSTRAINT `fk_class_recordings_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_class_recordings_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_sessions`
--
ALTER TABLE `class_sessions`
  ADD CONSTRAINT `fk_class_sessions_class_master` FOREIGN KEY (`class_master_id`) REFERENCES `class_master` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_class_sessions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fb_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `homeworks`
--
ALTER TABLE `homeworks`
  ADD CONSTRAINT `fk_homeworks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_homeworks_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `homework_submissions`
--
ALTER TABLE `homework_submissions`
  ADD CONSTRAINT `fk_hs_hw` FOREIGN KEY (`homework_id`) REFERENCES `homeworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_activity_logs`
--
ALTER TABLE `meeting_activity_logs`
  ADD CONSTRAINT `fk_meeting_activity_logs_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_meeting_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recurring_occurrences`
--
ALTER TABLE `recurring_occurrences`
  ADD CONSTRAINT `fk_recurring_occurrences_class` FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_recurring_occurrences_series` FOREIGN KEY (`series_id`) REFERENCES `recurring_series` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recurring_series`
--
ALTER TABLE `recurring_series`
  ADD CONSTRAINT `fk_recurring_series_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `recurring_series_students`
--
ALTER TABLE `recurring_series_students`
  ADD CONSTRAINT `fk_rss_series` FOREIGN KEY (`series_id`) REFERENCES `recurring_series` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rss_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reschedule_requests`
--
ALTER TABLE `reschedule_requests`
  ADD CONSTRAINT `fk_rr_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD CONSTRAINT `fk_student_payments_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_payments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_reports`
--
ALTER TABLE `student_reports`
  ADD CONSTRAINT `fk_student_reports_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_reports_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD CONSTRAINT `fk_teacher_availability_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_google_accounts`
--
ALTER TABLE `teacher_google_accounts`
  ADD CONSTRAINT `fk_tga_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_payments_bk`
--
ALTER TABLE `teacher_payments_bk`
  ADD CONSTRAINT `fk_teacher_payments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_payment_logs`
--
ALTER TABLE `teacher_payment_logs`
  ADD CONSTRAINT `fk_payment_logs_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_logs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_payouts`
--
ALTER TABLE `teacher_payouts`
  ADD CONSTRAINT `fk_teacher_payouts_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_payouts_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
