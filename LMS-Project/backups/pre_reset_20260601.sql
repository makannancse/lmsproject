-- LearnWise LMS full backup
-- Generated: 2026-06-01 06:58:50 UTC

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `class_attendance`;
CREATE TABLE `class_attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` enum('teacher','student') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `joined_at` datetime DEFAULT NULL,
  `left_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_class_attendance_user` (`class_id`,`user_id`,`role`),
  KEY `fk_class_attendance_user` (`user_id`),
  CONSTRAINT `fk_class_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (16, 36, 3, 'teacher', '2026-05-30 05:36:38', '2026-05-30 05:37:55', '2026-05-30 11:07:04', '2026-05-30 11:26:25');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (17, 36, 6, 'student', '2026-05-30 05:37:04', NULL, '2026-05-30 11:07:04', '2026-05-30 11:07:04');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (19, 37, 12, 'teacher', '2026-05-30 06:01:21', '2026-05-30 06:03:27', '2026-05-30 12:02:41', '2026-05-30 12:02:41');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (20, 38, 12, 'teacher', '2026-05-30 06:38:43', '2026-05-30 06:40:51', '2026-05-30 12:08:55', '2026-05-30 12:11:04');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (21, 38, 6, 'student', '2026-05-30 06:38:56', NULL, '2026-05-30 12:08:56', '2026-05-30 12:08:56');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (24, 40, 13, 'student', '2026-05-30 07:46:46', NULL, '2026-05-30 13:16:46', '2026-05-30 13:16:46');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (25, 41, 12, 'teacher', '2026-05-30 09:20:28', '2026-05-30 09:21:38', '2026-05-30 14:50:43', '2026-05-30 14:51:40');
INSERT INTO `class_attendance` (`id`, `class_id`, `user_id`, `role`, `joined_at`, `left_at`, `created_at`, `updated_at`) VALUES (26, 41, 6, 'student', '2026-05-30 09:20:43', NULL, '2026-05-30 14:50:43', '2026-05-30 14:50:43');

DROP TABLE IF EXISTS `class_master`;
CREATE TABLE `class_master` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_master` (`id`, `class_name`, `description`, `status`, `created_at`, `updated_at`) VALUES (1, 'Dance Class', NULL, 'active', '2026-04-11 12:34:55', '2026-04-11 12:34:55');
INSERT INTO `class_master` (`id`, `class_name`, `description`, `status`, `created_at`, `updated_at`) VALUES (2, 'Music Class', NULL, 'active', '2026-04-11 12:35:05', '2026-04-11 12:35:05');
INSERT INTO `class_master` (`id`, `class_name`, `description`, `status`, `created_at`, `updated_at`) VALUES (3, 'Maths', NULL, 'active', '2026-04-11 12:35:17', '2026-04-11 12:35:17');
INSERT INTO `class_master` (`id`, `class_name`, `description`, `status`, `created_at`, `updated_at`) VALUES (4, 'English Class', 'Test', 'active', '2026-04-19 13:20:34', '2026-04-19 13:20:34');

DROP TABLE IF EXISTS `class_recordings`;
CREATE TABLE `class_recordings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
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
  KEY `fk_class_recordings_teacher` (`teacher_id`),
  CONSTRAINT `fk_class_recordings_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_recordings_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_recordings` (`id`, `class_id`, `teacher_id`, `recording_url`, `recording_file_id`, `recording_title`, `recording_duration`, `visible_to_student`, `sync_status`, `source`, `created_at`, `updated_at`) VALUES (1, 37, 12, 'https://drive.google.com/file/d/10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2/view?usp=drivesdk', '10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2', 'Test Music Class - 2026/05/30 11:31 IST – Recording', 1, 'no', 'ready', 'google_drive', '2026-05-30 12:05:56', '2026-05-30 12:05:56');
INSERT INTO `class_recordings` (`id`, `class_id`, `teacher_id`, `recording_url`, `recording_file_id`, `recording_title`, `recording_duration`, `visible_to_student`, `sync_status`, `source`, `created_at`, `updated_at`) VALUES (2, 38, 12, 'https://drive.google.com/file/d/10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2/view?usp=drivesdk', '10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2', 'Test Music Class - 2026/05/30 11:31 IST – Recording', 1, 'yes', 'ready', 'google_drive', '2026-05-30 12:12:27', '2026-05-30 12:18:06');

DROP TABLE IF EXISTS `class_sessions`;
CREATE TABLE `class_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `class_master_id` int unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `recording_acknowledged_at` datetime DEFAULT NULL,
  `recording_acknowledged_by` int unsigned DEFAULT NULL,
  `student_joined_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_class_sessions_teacher` (`teacher_id`),
  KEY `fk_class_sessions_class_master` (`class_master_id`),
  CONSTRAINT `fk_class_sessions_class_master` FOREIGN KEY (`class_master_id`) REFERENCES `class_master` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_class_sessions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (36, 3, 4, 'English Class', 'Test', '2026-05-30 05:26:00', '2026-05-30 05:26:00', '2026-05-30 05:26:00', '2026-05-30 06:26:00', '2026-05-30 06:26:00', 'Asia/Kolkata', 'Asia/Kolkata', 'completed', 'https://meet.google.com/txg-mtfs-nmd', 'cd7a9ss5m29gcl6t78ap98f9ss', 'kannanandhu99@gmail.com', 'spaces/tkvaND-sPq8B', 'txg-mtfs-nmd', 'conferenceRecords/phE857oVpHolbeTkxVwDDxISOAIIigIgABgECA', 'ended', 2, '100.00', '500', '0', NULL, NULL, NULL, '2026-05-30 11:05:43', '2026-05-30 11:26:25', '2026-05-30 05:37:55', '2026-05-30 05:36:38', '2026-05-30 05:37:55', 1, 1, NULL, 0, 'pending', NULL, NULL, '2026-05-30 05:36:38', NULL, NULL, '2026-05-30 05:37:04');
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (37, 12, 2, 'Test Music Class', 'Test', '2026-05-30 06:00:00', '2026-05-30 06:00:00', '2026-05-30 06:00:00', '2026-05-30 07:00:00', '2026-05-30 07:00:00', 'Asia/Kolkata', 'Asia/Kolkata', 'completed', 'https://meet.google.com/etm-mxme-cmv', '9m6j959k4bkih687vgdp2j6cns', 'narmadha@edulearnwise.com', 'spaces/UJwTuLZSz3MB', 'etm-mxme-cmv', 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA', 'ended', 2, '100.00', '1000', '0', NULL, NULL, NULL, '2026-05-30 11:27:44', '2026-05-30 12:05:56', '2026-05-30 06:03:27', '2026-05-30 06:01:21', '2026-05-30 06:03:27', 2, 2, 'https://drive.google.com/file/d/10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2/view?usp=drivesdk', 1, 'ready', NULL, '2026-05-30 06:35:56', '2026-05-30 06:01:21', '2026-05-30 06:01:05', 12, '2026-05-30 06:01:39');
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (38, 12, 1, 'Dance Class Test', 'Test', '2026-05-30 06:40:00', '2026-05-30 06:40:00', '2026-05-30 06:40:00', '2026-05-30 07:40:00', '2026-05-30 07:40:00', 'Asia/Kolkata', 'Asia/Kolkata', 'completed', 'https://meet.google.com/zts-tjwd-eoa', 'bbrf9g07f5fdhm3bbejd8l20d8', 'narmadha@edulearnwise.com', 'spaces/d7IQwxyXUusB', 'zts-tjwd-eoa', 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA', 'ended', 2, '100.00', '1000', '0', NULL, NULL, NULL, '2026-05-30 12:07:24', '2026-05-30 12:12:27', '2026-05-30 06:40:51', '2026-05-30 06:38:43', '2026-05-30 06:40:51', 2, 2, 'https://drive.google.com/file/d/10KPJVO6is1vilI5rPdPCiwzibCzWCJJ2/view?usp=drivesdk', 1, 'ready', NULL, '2026-05-30 06:42:27', '2026-05-30 06:38:43', '2026-05-30 06:38:30', 12, '2026-05-30 06:38:56');
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (39, 3, 1, 'cmcx.,xsd', 'fdhxghfgvb', '2026-05-30 18:07:00', '2026-05-30 18:07:00', '2026-05-30 18:07:00', '2026-05-30 19:07:00', '2026-05-30 19:07:00', 'UTC', 'UTC', 'rescheduled', 'https://meet.google.com/soa-qcbz-fim', 'qu9ft883vu5jkf1kqju8ptore0', 'kannanandhu99@gmail.com', 'spaces/Kz1EbTH47skB', 'soa-qcbz-fim', NULL, 'pending', NULL, '99.97', '200', '0', NULL, NULL, NULL, '2026-05-30 12:52:45', '2026-05-30 13:07:45', NULL, NULL, NULL, NULL, NULL, NULL, 0, 'pending', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (40, 3, 1, 'test dance class', 'dfns,m,m,smf', '2026-05-30 13:13:00', '2026-05-30 13:13:00', '2026-05-30 13:13:00', '2026-05-30 15:13:00', '2026-05-30 15:13:00', 'UTC', 'UTC', 'completed', 'https://meet.google.com/jcy-ohzb-aaq', '1smqjpiajb8nt53ib6i7u2lr04', 'kannanandhu99@gmail.com', 'spaces/ES0e_Xpf1GUB', 'jcy-ohzb-aaq', 'conferenceRecords/IyHCAWQDyoEGUE8r68LPDxIXOAIIigIgABgECA', 'ended', 1, '100.00', '200', '0', NULL, NULL, NULL, '2026-05-30 13:14:16', '2026-05-30 13:18:10', '2026-05-30 07:46:24', '2026-05-30 07:46:09', '2026-05-30 07:46:24', 0, 0, NULL, 0, 'pending', NULL, NULL, '2026-05-30 07:46:09', NULL, NULL, '2026-05-30 07:46:46');
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (41, 12, 3, 'Test', 'Test', '2026-05-30 09:13:00', '2026-05-30 09:13:00', '2026-05-30 09:13:00', '2026-05-30 10:13:00', '2026-05-30 10:13:00', 'Asia/Kolkata', 'Asia/Kolkata', 'completed', 'https://meet.google.com/tjr-skos-scc', '44tvbb543dr9hrs3avjf71rchs', 'narmadha@edulearnwise.com', 'spaces/m3kVDIoq1iMB', 'tjr-skos-scc', 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA', 'ended', 2, '100.00', '500', '0', NULL, NULL, NULL, '2026-05-30 14:43:32', '2026-05-30 14:51:40', '2026-05-30 09:21:38', '2026-05-30 09:20:28', '2026-05-30 09:21:38', 1, 1, NULL, 1, 'processing', NULL, NULL, '2026-05-30 09:20:28', '2026-05-30 09:20:17', 12, '2026-05-30 09:20:43');
INSERT INTO `class_sessions` (`id`, `teacher_id`, `class_master_id`, `title`, `description`, `start_datetime`, `scheduled_time_utc`, `start_time_utc`, `end_datetime`, `end_time_utc`, `timezone`, `scheduled_timezone`, `status`, `meeting_link`, `google_event_id`, `teacher_google_email`, `google_meet_space_name`, `google_meeting_code`, `google_conference_id`, `meeting_live_status`, `meeting_participant_count`, `payout_amount`, `student_fee`, `teacher_payout`, `zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `created_at`, `updated_at`, `completed_at`, `actual_start_time`, `actual_end_time`, `actual_duration`, `actual_duration_minutes`, `recording_url`, `recording_enabled`, `recording_sync_status`, `recording_sync_error`, `recording_synced_at`, `teacher_joined_at`, `recording_acknowledged_at`, `recording_acknowledged_by`, `student_joined_at`) VALUES (42, 3, 1, 'Test', 'tesy', '2026-06-01 05:24:00', '2026-06-01 05:24:00', '2026-06-01 05:24:00', '2026-06-01 06:24:00', '2026-06-01 06:24:00', 'Asia/Kolkata', 'Asia/Kolkata', 'scheduled', 'https://meet.google.com/ssg-eikh-gyf', 'c9lk744rn9pg1tkvc5cnrb6aec', 'kannanandhu99@gmail.com', 'spaces/l-6JDDlm5DwB', 'ssg-eikh-gyf', NULL, 'pending', NULL, '100.00', '500', '0', NULL, NULL, NULL, '2026-06-01 10:55:22', '2026-06-01 10:55:22', NULL, NULL, NULL, NULL, NULL, NULL, 0, 'pending', NULL, NULL, NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `status` enum('active','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_enrollment` (`class_id`,`student_id`),
  KEY `fk_enrollments_student` (`student_id`),
  CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (17, 36, 6, 'active', '2026-05-30 11:05:43', '2026-05-30 11:05:43');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (18, 37, 6, 'active', '2026-05-30 11:27:44', '2026-05-30 11:27:44');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (19, 38, 6, 'active', '2026-05-30 12:07:24', '2026-05-30 12:07:24');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (20, 39, 13, 'active', '2026-05-30 12:52:45', '2026-05-30 12:52:45');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (21, 40, 13, 'active', '2026-05-30 13:14:16', '2026-05-30 13:14:16');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (22, 41, 6, 'active', '2026-05-30 14:43:32', '2026-05-30 14:43:32');
INSERT INTO `enrollments` (`id`, `class_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES (23, 42, 7, 'active', '2026-06-01 10:55:22', '2026-06-01 10:55:22');

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint unsigned NOT NULL DEFAULT '5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_feedback_pair` (`student_id`,`teacher_id`),
  KEY `fk_fb_teacher` (`teacher_id`),
  CONSTRAINT `fk_fb_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fb_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `homework_assigned_students`;
CREATE TABLE `homework_assigned_students` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `homework_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hw_student` (`homework_id`,`student_id`),
  KEY `fk_has_student` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `homework_assigned_students` (`id`, `homework_id`, `student_id`, `assigned_at`) VALUES (3, 9, 7, '2026-05-30 13:31:10');
INSERT INTO `homework_assigned_students` (`id`, `homework_id`, `student_id`, `assigned_at`) VALUES (4, 9, 13, '2026-05-30 13:31:10');

DROP TABLE IF EXISTS `homework_attachments`;
CREATE TABLE `homework_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `homework_id` int unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ha_hw` (`homework_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `homework_attachments` (`id`, `homework_id`, `file_name`, `file_path`, `uploaded_at`) VALUES (1, 9, 'report_1.pdf', 'uploads/homework/hw_6a1a99467bfe80.01033099_report_1.pdf', '2026-05-30 08:01:10');

DROP TABLE IF EXISTS `homework_submissions`;
CREATE TABLE `homework_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `homework_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_homework` (`homework_id`,`student_id`),
  KEY `fk_hs_student` (`student_id`),
  CONSTRAINT `fk_hs_hw` FOREIGN KEY (`homework_id`) REFERENCES `homeworks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `homework_submissions` (`id`, `homework_id`, `student_id`, `file_name`, `file_path`, `original_name`, `submitted_at`, `uploaded_at`) VALUES (2, 9, 13, 'G G KRISHNA CHAITHANYA_AADHAAR_CARD.jpg', 'uploads/homework_submissions/hw_6a1a999dc3d435.96652674_G_G_KRISHNA_CHAITHANYA_AADHAAR_CARD.jpg', NULL, '2026-05-30 08:02:37', '2026-05-30 13:32:37');

DROP TABLE IF EXISTS `homeworks`;
CREATE TABLE `homeworks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `due_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `status` enum('pending','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_homeworks_creator` (`created_by`),
  KEY `fk_homeworks_teacher` (`teacher_id`),
  CONSTRAINT `fk_homeworks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_homeworks_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `homeworks` (`id`, `teacher_id`, `title`, `description`, `due_date`, `due_timezone`, `status`, `completed_at`, `created_by`, `created_at`, `updated_at`) VALUES (9, 3, 'dfsd', 'frges', '2026-05-30 13:30:00', 'UTC', 'completed', '2026-05-30 08:03:35', 1, '2026-05-30 13:31:10', '2026-05-30 13:33:35');

DROP TABLE IF EXISTS `log`;
CREATE TABLE `log` (
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


DROP TABLE IF EXISTS `meeting_activity_logs`;
CREATE TABLE `meeting_activity_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
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
  KEY `idx_meeting_activity_class_role_join` (`class_id`,`role`,`joined_at`),
  CONSTRAINT `fk_meeting_activity_logs_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (1, 36, 3, 'conferenceRecords/phE857oVpHolbeTkxVwDDxISOAIIigIgABgECA/participants/111912129901590285760', 'conferenceRecords/phE857oVpHolbeTkxVwDDxISOAIIigIgABgECA/participants/111912129901590285760/participantSessions/218', 'teacher', '2026-05-30 05:36:38', '2026-05-30 05:37:55', 1, 'google_meet_api', '2026-05-30 11:07:04', '2026-05-30 11:26:25');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (2, 36, NULL, 'conferenceRecords/phE857oVpHolbeTkxVwDDxISOAIIigIgABgECA/participants/100578788178439393161', 'conferenceRecords/phE857oVpHolbeTkxVwDDxISOAIIigIgABgECA/participants/100578788178439393161/participantSessions/219', 'student', '2026-05-30 05:37:16', '2026-05-30 05:37:50', 0, 'google_meet_api', '2026-05-30 11:26:25', '2026-05-30 11:26:25');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (4, 37, NULL, 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/100578788178439393161', 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/100578788178439393161/participantSessions/483', 'student', '2026-05-30 06:01:39', '2026-05-30 06:01:57', 0, 'google_meet_api', '2026-05-30 12:02:41', '2026-05-30 12:02:41');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (5, 37, NULL, 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/100578788178439393161', 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/100578788178439393161/participantSessions/486', 'student', '2026-05-30 06:02:27', '2026-05-30 06:03:09', 0, 'google_meet_api', '2026-05-30 12:02:41', '2026-05-30 12:02:41');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (6, 37, 12, 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/112056070223696545859/participantSessions/482', 'teacher', '2026-05-30 06:01:21', '2026-05-30 06:03:27', 2, 'google_meet_api', '2026-05-30 12:02:41', '2026-05-30 12:02:41');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (7, 37, 12, 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/eKFTNHCmjbatZwCRr4s0DxISOAIIigIgABgECA/participants/112056070223696545859/participantSessions/491', 'teacher', '2026-05-30 06:03:05', '2026-05-30 06:03:27', 0, 'google_meet_api', '2026-05-30 12:02:41', '2026-05-30 12:02:41');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (8, 38, 12, 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/112056070223696545859/participantSessions/445', 'teacher', '2026-05-30 06:38:43', '2026-05-30 06:40:51', 2, 'google_meet_api', '2026-05-30 12:08:55', '2026-05-30 12:11:04');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (9, 38, NULL, 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/100578788178439393161', 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/100578788178439393161/participantSessions/446', 'student', '2026-05-30 06:39:00', NULL, NULL, 'google_meet_api', '2026-05-30 12:10:07', '2026-05-30 12:11:04');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (11, 38, 12, 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/9PovrXX_20EtCNNWTsY8DxIXOAIIigIgABgECA/participants/112056070223696545859/participantSessions/453', 'teacher', '2026-05-30 06:40:01', '2026-05-30 06:40:51', 0, 'google_meet_api', '2026-05-30 12:10:07', '2026-05-30 12:11:04');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (15, 41, 12, 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/112056070223696545859/participantSessions/571', 'teacher', '2026-05-30 09:20:28', '2026-05-30 09:21:38', 1, 'google_meet_api', '2026-05-30 14:50:43', '2026-05-30 14:51:40');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (16, 41, NULL, 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/100578788178439393161', 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/100578788178439393161/participantSessions/572', 'student', '2026-05-30 09:20:46', NULL, NULL, 'google_meet_api', '2026-05-30 14:51:15', '2026-05-30 14:51:40');
INSERT INTO `meeting_activity_logs` (`id`, `class_id`, `user_id`, `google_participant_name`, `google_participant_session_name`, `role`, `joined_at`, `left_at`, `duration_minutes`, `source`, `created_at`, `updated_at`) VALUES (18, 41, 12, 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/112056070223696545859', 'conferenceRecords/9dEmpfSjJTzmCGOYPS8gDxIXOAIIigIgABgECA/participants/112056070223696545859/participantSessions/577', 'teacher', '2026-05-30 09:21:11', '2026-05-30 09:21:38', 0, 'google_meet_api', '2026-05-30 14:51:15', '2026-05-30 14:51:40');

DROP TABLE IF EXISTS `reschedule_requests`;
CREATE TABLE `reschedule_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
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
  KEY `fk_rr_teacher` (`teacher_id`),
  CONSTRAINT `fk_rr_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reschedule_requests` (`id`, `class_id`, `student_id`, `teacher_id`, `requested_by`, `initiated_by`, `requested_date`, `requested_time`, `old_timezone`, `new_timezone`, `reason`, `status`, `teacher_comment`, `admin_comment`, `created_at`, `updated_at`) VALUES (5, 39, 13, 3, 'student', 'student', '2026-05-30', '15:00:00', 'UTC', 'UTC', 'cvdffdssssssssss', 'approved', NULL, NULL, '2026-05-30 12:56:18', '2026-05-30 13:06:37');
INSERT INTO `reschedule_requests` (`id`, `class_id`, `student_id`, `teacher_id`, `requested_by`, `initiated_by`, `requested_date`, `requested_time`, `old_timezone`, `new_timezone`, `reason`, `status`, `teacher_comment`, `admin_comment`, `created_at`, `updated_at`) VALUES (6, 39, 13, 3, 'teacher', 'teacher', '2026-05-30', '18:07:00', 'UTC', 'UTC', 'hbmnbmnm', 'approved', 'hbmnbmnm', NULL, '2026-05-30 13:07:44', '2026-05-30 13:07:44');

DROP TABLE IF EXISTS `student_payments`;
CREATE TABLE `student_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_payments_student` (`student_id`),
  KEY `idx_student_payments_class` (`class_id`),
  KEY `idx_student_payments_status` (`status`),
  CONSTRAINT `fk_student_payments_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_payments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (17, 6, 36, '500.00', 'INR', 'paid', '2026-05-30 07:01:03', '2026-05-30 11:05:43');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (18, 6, 37, '1000.00', 'INR', 'paid', '2026-05-30 07:01:02', '2026-05-30 11:27:44');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (19, 6, 38, '1000.00', 'INR', 'paid', '2026-05-30 07:01:00', '2026-05-30 12:07:24');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (20, 13, 39, '200.00', 'INR', 'pending', NULL, '2026-05-30 12:52:45');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (21, 13, 40, '199.97', 'INR', 'paid', '2026-05-30 07:49:08', '2026-05-30 13:14:16');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (22, 6, 41, '500.00', 'INR', 'pending', NULL, '2026-05-30 14:43:32');
INSERT INTO `student_payments` (`id`, `student_id`, `class_id`, `amount`, `currency`, `status`, `payment_date`, `created_at`) VALUES (23, 7, 42, '500.00', 'INR', 'pending', NULL, '2026-06-01 10:55:22');

DROP TABLE IF EXISTS `student_reports`;
CREATE TABLE `student_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
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
  KEY `idx_student_reports_teacher_date` (`teacher_id`,`report_date`),
  CONSTRAINT `fk_student_reports_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_reports_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `student_reports` (`id`, `student_id`, `teacher_id`, `performance_rating`, `understanding_level`, `strengths`, `improvements`, `comments`, `report_date`, `created_at`, `email`, `student_name`, `teacher_name`, `subject`, `overall_performance`, `concept_understanding`, `application_ability`, `homework_completion`, `attention_level`, `participation_level`, `behaviour`, `subjects_addressed`, `future_focus`, `recommended_focus`, `study_strategies`, `additional_support`, `overall_feedback`, `pdf_path`) VALUES (1, 13, 3, '', '', NULL, NULL, NULL, '2026-05-30', '2026-05-30 13:20:57', 'saranyab260404@gmail.com', 'Saranya B', 'Kannan', 'dance', 'Good', 'Basic understanding', 'Applies independently', 'Sometimes late', 'Highly attentive', 'Moderate', 'Excellent', 'dfsfsdfsfs', 'dsfsf', 'dfs', 'dfsd', 'dfsfsd', 'sdfsd', 'uploads/reports/report_1.pdf');

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_payment_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_students_user` (`user_id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES (1, 6, 'India', '', NULL, '0.00', NULL, '2026-03-15 21:08:55', '2026-03-15 21:08:55');
INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES (2, 7, 'India', '', NULL, '0.00', NULL, '2026-03-21 12:54:49', '2026-03-21 12:54:49');
INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES (3, 9, 'India', 'kannan@capminds.com', NULL, '0.00', NULL, '2026-04-19 11:59:55', '2026-04-19 11:59:55');
INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES (4, 10, 'India', 'narmadhasuresh7@gmail.com', NULL, '0.00', NULL, '2026-04-19 13:16:24', '2026-04-19 13:16:24');
INSERT INTO `students` (`id`, `user_id`, `country`, `parent_email`, `subject`, `default_payment_amount`, `notes`, `created_at`, `updated_at`) VALUES (5, 13, 'India', 'saranya.capminds@gmail.com', NULL, '0.00', NULL, '2026-05-30 12:38:20', '2026-05-30 12:38:20');

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('google_calendar_id', 'primary', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('google_client_id', '716021670756-arantp7s7kp6rlj10g09ac2on89qb9fe.apps.googleusercontent.com', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('google_client_secret', 'password', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('google_refresh_token', '1//052KelzSHCwkUCgYIARAAGAUSNwF-L9IrKkzTLvBZUJ9n5BKg9OnKwhuxwRFyr1Gv9zdq_rPRoOZVZPbS-Atl_UOf-nxzFW2djjE', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('mail_from_name', 'LMS', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('payout_rate_full_time', '30', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('payout_rate_part_time', '20', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('payout_rate_per_hour', '20', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('smtp_encryption', 'tls', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('smtp_password', 'password', '2026-04-11 13:41:28');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('smtp_port', '587', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('smtp_username', 'admin@example.com', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('static_meeting_link', 'https://zoom.us/j/7874901508', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('static_zoom_meeting_link', 'https://zoom.us/j/7874901508', '2026-05-01 13:47:55');
INSERT INTO `system_config` (`key`, `value`, `updated_at`) VALUES ('zoom_api_key', 'Test', '2026-03-15 21:19:19');

DROP TABLE IF EXISTS `teacher_availability`;
CREATE TABLE `teacher_availability` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `weekday` tinyint unsigned NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_teacher_availability_teacher` (`teacher_id`),
  CONSTRAINT `fk_teacher_availability_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `teacher_google_accounts`;
CREATE TABLE `teacher_google_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
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
  UNIQUE KEY `uniq_tga_teacher` (`teacher_id`),
  CONSTRAINT `fk_tga_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teacher_google_accounts` (`id`, `teacher_id`, `google_email`, `google_person_resource_name`, `google_person_id`, `google_user_id`, `account_type`, `recording_supported`, `access_token`, `refresh_token`, `token_expiry`, `connected_at`, `status`, `created_at`, `updated_at`) VALUES (1, 3, 'kannanandhu99@gmail.com', 'people/111912129901590285760', '111912129901590285760', '111912129901590285760', 'personal', 0, 'jW41ThTtbIVwd+BESPFnBbBW8k7i4ejS4O3HsOpGCHfWBw2wzhYV9zCLH/H1c5bbHXqJpYJpTw4o5ImoVU8vL2Div5pe8hMCDJKWjpTX2yrXyVSRcO2KHyjddcr+frHHXblkFedOQVTaVgSict/6j/k4B2JGE77Ut5r42g+WM1kkhWYn1cksZXrjPhecSHI82Aq8ec1IU2oRERYx9jhLqOgfUlFgffHm6l2ESwfbyETrvsO256xn0/ucqSizlYr/ao6wjsdx0BD7MxNQcS2VXCaGDCiYxPlEcBY84ht1wO4ONGtouybU9TdRGeM8E+/LRJ3RCSDKesSowY2/EJF5i/vVp4pvEmngGDCjf3eaXo8=', 'stYLkFaNN4H9YtZcGJy4NHLfr9jG2jCscVKNJ1wvWMnhOF3kBjXB+uKg03s+Nz+F9UKU9OCpPmHMVLsDjuvmcc7z1K5K9VDlchqlSbUi4ZRWAzuV1xsi46lQzDeT2Lv2qDwT+Sy+FCPwqyUz/o9EbjK+3zETe11Z1BDheH+ju2o=', '2026-06-01 06:25:17', '2026-05-10 09:40:48', 'active', '2026-05-10 09:40:48', '2026-06-01 10:55:18');
INSERT INTO `teacher_google_accounts` (`id`, `teacher_id`, `google_email`, `google_person_resource_name`, `google_person_id`, `google_user_id`, `account_type`, `recording_supported`, `access_token`, `refresh_token`, `token_expiry`, `connected_at`, `status`, `created_at`, `updated_at`) VALUES (2, 12, 'narmadha@edulearnwise.com', 'people/112056070223696545859', '112056070223696545859', '112056070223696545859', 'workspace', 1, '4YHdrotqAXKbggqCZx2T2SgOHANjlMjJr/vBWqYZyPEW/Cxg327fXjTKrq57lnIn2BCfKSzyeX8Nem1f74OSgBPtfnR0BminVnk3z/sVC8oDwUDDJS8f/P/OMj4BmDmrUji91erRYcUzjHbs45qg0l5dE1GT8wPwBIjDkW3bZeAgoXKQR/j13WlI9AjRA+JOglOS3pByyOrpzu1MSRXQBHxmJ8UcorAz72RCAAHaT8EJyV6vBUoczFpzGRbx7ESvT36Gvof4Mye3u8TFAg2a5noxWmpnZpibR77xDdDqMAJk3aE5Veg9dwQPoL6qXbmrABb6C1aC4XrtdRXOA+IS+B52AQKarVZNBShko1rnqcQ=', 'idxZIMibsZrp/WIvMclkY39MiuGzLMHrzjMvMLK/rsqMXFKGRCx60GpcocUDDvKK2wwAED1+PYOlHA46+i2S9neNBrb6c9nPkuCLVCdGLXgw/4Ug7777yvNlhDu3AcuK94pKgSpZsvD1hubwWkRbHvP3iMatXtoXwp9CoN8BlWE=', '2026-05-30 10:13:29', '2026-05-16 07:20:12', 'active', '2026-05-16 07:20:12', '2026-05-30 14:43:30');

DROP TABLE IF EXISTS `teacher_payment_logs`;
CREATE TABLE `teacher_payment_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_logs_teacher` (`teacher_id`),
  KEY `idx_payment_logs_class` (`class_id`),
  KEY `idx_payment_logs_status` (`status`),
  CONSTRAINT `fk_payment_logs_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_logs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES (12, 3, 36, '100.00', 'paid', '2026-05-30 05:56:35');
INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES (13, 12, 37, '100.00', 'paid', '2026-05-30 06:35:37');
INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES (14, 12, 38, '100.00', 'paid', '2026-05-30 06:41:06');
INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES (15, 3, 40, '100.00', 'pending', '2026-05-30 07:48:31');
INSERT INTO `teacher_payment_logs` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `created_at`) VALUES (16, 12, 41, '100.00', 'pending', '2026-05-30 09:21:40');

DROP TABLE IF EXISTS `teacher_payments`;
CREATE TABLE `teacher_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','partial','paid','advance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teacher_payments` (`id`, `teacher_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `payment_date`, `remarks`, `created_at`) VALUES (1, 3, '100.00', '100.00', '0.00', 'paid', '2026-05-30 06:56:19', '', '2026-05-30 06:56:19');
INSERT INTO `teacher_payments` (`id`, `teacher_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `payment_date`, `remarks`, `created_at`) VALUES (2, 12, '200.00', '150.00', '100.00', 'partial', '2026-05-30 06:56:37', '', '2026-05-30 06:56:37');
INSERT INTO `teacher_payments` (`id`, `teacher_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `payment_date`, `remarks`, `created_at`) VALUES (3, 12, '200.00', '100.00', '0.00', 'paid', '2026-05-30 06:56:49', 'Marked paid from dashboard', '2026-05-30 06:56:49');

DROP TABLE IF EXISTS `teacher_payments_bk`;
CREATE TABLE `teacher_payments_bk` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','partial','paid','advance') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_payments_teacher` (`teacher_id`),
  KEY `idx_teacher_payments_status` (`payment_status`),
  CONSTRAINT `fk_teacher_payments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `teacher_payouts`;
CREATE TABLE `teacher_payouts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `calculated_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payout_per_class` (`teacher_id`,`class_id`),
  KEY `fk_teacher_payouts_class` (`class_id`),
  CONSTRAINT `fk_teacher_payouts_class` FOREIGN KEY (`class_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_payouts_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teacher_payouts` (`id`, `teacher_id`, `class_id`, `amount`, `status`, `calculated_at`, `paid_at`, `created_at`, `updated_at`) VALUES (6, 12, 41, '100.00', 'pending', '2026-05-30 09:21:40', NULL, '2026-05-30 09:21:40', '2026-05-30 14:51:40');

DROP TABLE IF EXISTS `teacher_students`;
CREATE TABLE `teacher_students` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_teacher_student` (`teacher_id`,`student_id`),
  KEY `fk_ts_student` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (8, 3, 13, '2026-05-30 12:41:44');
INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (7, 3, 7, '2026-05-30 12:41:44');
INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (9, 12, 13, '2026-05-30 14:41:35');
INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (4, 11, 10, '2026-04-19 13:18:46');
INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (5, 11, 6, '2026-04-19 13:18:46');
INSERT INTO `teacher_students` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES (10, 12, 6, '2026-05-30 14:41:35');

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `employment_type` enum('full_time','part_time') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'part_time',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_refresh_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_connected_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_teachers_user` (`user_id`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES (1, 3, 'full_time', NULL, NULL, NULL, NULL, '2026-03-15 20:58:32', '2026-03-15 20:58:32');
INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES (2, 8, 'part_time', NULL, NULL, NULL, NULL, '2026-04-18 15:43:25', '2026-04-18 15:43:25');
INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES (3, 11, 'part_time', NULL, NULL, NULL, NULL, '2026-04-19 13:18:07', '2026-04-19 13:18:07');
INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES (4, 12, 'full_time', NULL, NULL, NULL, NULL, '2026-05-16 07:18:39', '2026-05-16 07:18:39');
INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `hourly_rate`, `notes`, `google_refresh_token`, `google_connected_at`, `created_at`, `updated_at`) VALUES (5, 14, 'full_time', NULL, NULL, NULL, NULL, '2026-05-30 12:40:12', '2026-05-30 12:40:12');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (1, 'Admin', 'admin@example.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'UTC', 'active', '2026-01-06 13:12:18', '2026-01-06 13:12:18');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (3, 'Kannan', 'kannanandhu99@gmail.com', NULL, '$2y$10$hIx4SmBjQSu8xo231r/1Ae4wEdbA4/S.jka0IucbrouUZbfHTxROG', 'teacher', 'UTC', 'active', '2026-03-15 20:58:32', '2026-03-15 20:58:32');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (6, 'Test Student', 'kannan1997cse@gmail.com', NULL, '$2y$10$KW9or8sDmQiyFHmCRaLWEeZwsbu/LTqpJnhXeC/Ul/KdhBUc24I5u', 'student', 'UTC', 'active', '2026-03-15 21:08:55', '2026-03-15 21:08:55');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (7, 'Nandhini', 'nandhinibalakrishnan2020@gmail.com', NULL, '$2y$10$RV4GeHRHUdiCp2GONX0rFOVtf9skFIu6uZHPCtw87vnQgMHsv/I7a', 'student', 'IST', 'active', '2026-03-21 12:54:49', '2026-03-21 12:54:49');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (8, 'Test Teacher', 'kannan1197cse@gmail.com', NULL, '$2y$10$hIx4SmBjQSu8xo231r/1Ae4wEdbA4/S.jka0IucbrouUZbfHTxROG', 'teacher', 'UTC', 'active', '2026-04-18 15:43:25', '2026-05-01 11:23:25');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (9, 'New student', 'kannan19cse@gmail.com', NULL, '$2y$10$5oMuoWh.ZEnDJt2fIJO4lOGD3hR6AvPDOB1tdORDsHTGUusS1kdH.', 'student', 'UTC', 'active', '2026-04-19 11:59:55', '2026-04-19 11:59:55');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (10, 'Suresh', '2k8.surya@gmail.com', NULL, '$2y$10$Bn9h1CPMK0nwVlAKiZY1.e5WVkLlpSnyFuHSHFDoX7S/MPdk0zLFi', 'student', 'UTC', 'active', '2026-04-19 13:16:24', '2026-04-19 13:16:24');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (11, 'Narmadha', '2k9.surya@gmail.com', NULL, '$2y$10$nvbbFDyl14PTmXRew.fnp.hy36ZNTtAS4A7YfbEgbq04d3VQWp3a6', 'teacher', 'UTC', 'active', '2026-04-19 13:18:07', '2026-04-19 13:18:07');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (12, 'LearnWise', 'narmadha@edulearnwise.com', NULL, '$2y$10$6nbphf.X1WbIiuoN1oBcNu3LHSgj7c9ycvrDYPXrB9tXQhWlSPEp2', 'teacher', 'UTC', 'active', '2026-05-16 07:18:39', '2026-05-16 07:18:39');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (13, 'Saranya B', 'saranyab260404@gmail.com', NULL, '$2y$10$OH9AUyJlvsmcmE8bxLYZA.1TySEkitoG5JSlFTN0IHp0O.9YTamUy', 'student', 'UTC', 'active', '2026-05-30 12:38:20', '2026-05-30 12:38:20');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `timezone`, `status`, `created_at`, `updated_at`) VALUES (14, 'TestTeacher', 'TestTeacher@gmail.com', NULL, '$2y$10$3unp2wurHOS2c8H2Dn704uMeIlMPEInmUjxv6kEHKtm4vR4.ylxpO', 'teacher', 'UTC', 'active', '2026-05-30 12:40:12', '2026-05-30 12:40:12');

SET FOREIGN_KEY_CHECKS = 1;
