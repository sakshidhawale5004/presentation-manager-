-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 11:46 AM
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
-- Database: `presenter_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Business', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38'),
(2, 'Professional', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38'),
(3, 'Entrepreneur', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38'),
(4, 'Other', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chapters`
--

INSERT INTO `chapters` (`id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Mumbai Central', 'MUM-C', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38'),
(2, 'Navi Mumbai', 'NAV-M', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38'),
(3, 'Pune', 'PUN', 1, '2026-10-26 07:29:38', '2026-10-26 07:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED DEFAULT NULL,
  `presentation_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_email` varchar(190) NOT NULL,
  `email_type` enum('monthly_reminder','thirty_day_reminder','followup','overdue_admin','test','manual_resend') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `dedupe_key` varchar(190) DEFAULT NULL,
  `sent_by` int(10) UNSIGNED DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `member_id`, `presentation_id`, `recipient_email`, `email_type`, `subject`, `body`, `status`, `error_message`, `attempts`, `dedupe_key`, `sent_by`, `sent_at`, `created_at`) VALUES
(1, NULL, NULL, 'hasina94shaikh@gmail.com', 'test', 'Presenter Manager: test email', '<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;padding:0;background:#f4f6fb;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f4f6fb;padding:24px 0;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\"><tr><td style=\"background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;\">Presenter Manager</td></tr><tr><td style=\"padding:24px;font-size:15px;line-height:1.6;\"><h2 style=\"margin:0 0 16px;font-size:20px;color:#1e293b;\">Test email</h2><p style=\"margin:0 0 12px;\">If you can read this, email sending works.</p><table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" style=\"margin:12px 0 16px;\"><tr><td style=\"padding:6px 12px 6px 0;color:#64748b;white-space:nowrap;\">Sent from</td><td style=\"padding:6px 0;font-weight:bold;\">360sociale@gmail.com</td></tr><tr><td style=\"padding:6px 12px 6px 0;color:#64748b;white-space:nowrap;\">Server</td><td style=\"padding:6px 0;font-weight:bold;\">smtp.gmail.com:587</td></tr><tr><td style=\"padding:6px 12px 6px 0;color:#64748b;white-space:nowrap;\">Time</td><td style=\"padding:6px 0;font-weight:bold;\">27 Oct 2026, 01:55 PM</td></tr></table></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;\">This message was sent by Presenter Manager.</td></tr></table></td></tr></table></body></html>', 'sent', NULL, 1, NULL, 1, '2026-10-27 13:55:14', '2026-10-27 08:25:10'),
(2, 3, 20, 'rahul.sample@example.com', 'monthly_reminder', 'Upcoming Presentation Reminder', '<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;padding:0;background:#f4f6fb;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f4f6fb;padding:24px 0;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\"><tr><td style=\"background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;\">Presenter Manager</td></tr><tr><td style=\"padding:24px;font-size:15px;line-height:1.6;\"><h2 style=\"margin:0 0 16px;font-size:20px;color:#1e293b;\">Presentation reminder</h2><p style=\"margin:0 0 12px;\">Namaskar Rahul Deshmukh,</p><p style=\"margin:0 0 12px;\">This email is regarding your upcoming presentation reminder.</p><p style=\"margin:0 0 12px;\">As your last presentation was conducted on <strong>08 Jul 2026</strong> at <strong>Hotel Sea Breeze, Dadar</strong>, we request you to kindly confirm your upcoming presentation date, venue, and chapter.</p><p style=\"margin:0 0 12px;\">Your presentation was due on <strong>06 Oct 2026</strong> (21 days overdue).</p><p style=\"margin:0 0 12px;\">Thank you for your cooperation.</p><p style=\"margin:16px 0 0;\">Regards,<br>Presentation Coordination Team</p></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;\">This message was sent by Presenter Manager.</td></tr></table></td></tr></table></body></html>', 'sent', NULL, 1, 'monthly:20:2026-10', 1, '2026-10-27 13:58:11', '2026-10-27 08:28:08'),
(3, 4, 21, 'priya.sample@example.com', 'monthly_reminder', 'Upcoming Presentation Reminder', '<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;padding:0;background:#f4f6fb;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f4f6fb;padding:24px 0;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\"><tr><td style=\"background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;\">Presenter Manager</td></tr><tr><td style=\"padding:24px;font-size:15px;line-height:1.6;\"><h2 style=\"margin:0 0 16px;font-size:20px;color:#1e293b;\">Presentation reminder</h2><p style=\"margin:0 0 12px;\">Namaskar Priya Kulkarni,</p><p style=\"margin:0 0 12px;\">This email is regarding your upcoming presentation reminder.</p><p style=\"margin:0 0 12px;\">As your last presentation was conducted on <strong>23 Jul 2026</strong> at <strong>Club House, Parel</strong>, we request you to kindly confirm your upcoming presentation date, venue, and chapter.</p><p style=\"margin:0 0 12px;\">Your presentation was due on <strong>21 Oct 2026</strong> (6 days overdue).</p><p style=\"margin:0 0 12px;\">Thank you for your cooperation.</p><p style=\"margin:16px 0 0;\">Regards,<br>Presentation Coordination Team</p></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;\">This message was sent by Presenter Manager.</td></tr></table></td></tr></table></body></html>', 'sent', NULL, 1, 'monthly:21:2026-10', 1, '2026-10-27 13:58:12', '2026-10-27 08:28:11'),
(4, 5, 22, 'amit.sample@example.com', 'monthly_reminder', 'Upcoming Presentation Reminder', '<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;padding:0;background:#f4f6fb;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f4f6fb;padding:24px 0;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\"><tr><td style=\"background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;\">Presenter Manager</td></tr><tr><td style=\"padding:24px;font-size:15px;line-height:1.6;\"><h2 style=\"margin:0 0 16px;font-size:20px;color:#1e293b;\">Presentation reminder</h2><p style=\"margin:0 0 12px;\">Namaskar Amit Patil,</p><p style=\"margin:0 0 12px;\">This email is regarding your upcoming presentation reminder.</p><p style=\"margin:0 0 12px;\">As your last presentation was conducted on <strong>28 Jul 2026</strong> at <strong>Vashi Community Hall</strong>, we request you to kindly confirm your upcoming presentation date, venue, and chapter.</p><p style=\"margin:0 0 12px;\">Your presentation was due on <strong>26 Oct 2026</strong> (1 day overdue).</p><p style=\"margin:0 0 12px;\">Thank you for your cooperation.</p><p style=\"margin:16px 0 0;\">Regards,<br>Presentation Coordination Team</p></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;\">This message was sent by Presenter Manager.</td></tr></table></td></tr></table></body></html>', 'sent', NULL, 1, 'monthly:22:2026-10', 1, '2026-10-27 13:58:14', '2026-10-27 08:28:12'),
(5, 6, 23, 'sneha.sample@example.com', 'monthly_reminder', 'Upcoming Presentation Reminder', '<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"margin:0;padding:0;background:#f4f6fb;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#f4f6fb;padding:24px 0;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\"><tr><td style=\"background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;\">Presenter Manager</td></tr><tr><td style=\"padding:24px;font-size:15px;line-height:1.6;\"><h2 style=\"margin:0 0 16px;font-size:20px;color:#1e293b;\">Presentation reminder</h2><p style=\"margin:0 0 12px;\">Namaskar Sneha Joshi,</p><p style=\"margin:0 0 12px;\">This email is regarding your upcoming presentation reminder.</p><p style=\"margin:0 0 12px;\">As your last presentation was conducted on <strong>01 Aug 2026</strong> at <strong>Nerul Business Centre</strong>, we request you to kindly confirm your upcoming presentation date, venue, and chapter.</p><p style=\"margin:0 0 12px;\">Your next presentation is due on <strong>30 Oct 2026</strong> (in 3 days).</p><p style=\"margin:0 0 12px;\">Thank you for your cooperation.</p><p style=\"margin:16px 0 0;\">Regards,<br>Presentation Coordination Team</p></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;\">This message was sent by Presenter Manager.</td></tr></table></td></tr></table></body></html>', 'sent', NULL, 1, 'monthly:23:2026-10', 1, '2026-10-27 13:58:15', '2026-10-27 08:28:14');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(10) UNSIGNED NOT NULL,
  `member_code` varchar(30) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(190) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `chapter_id` int(10) UNSIGNED NOT NULL,
  `last_presentation_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `venue` varchar(190) DEFAULT NULL,
  `last_presentation_chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `coordinator_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_code`, `full_name`, `mobile`, `email`, `category_id`, `chapter_id`, `last_presentation_date`, `next_due_date`, `venue`, `last_presentation_chapter_id`, `status`, `remarks`, `coordinator_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'MEM-SMOKE', 'Smoke Test Member', '9876543210', 'smoke.member@example.com', 1, 1, '2026-10-16', '2027-01-14', 'Main Hall', NULL, 'active', 'Module 2 smoke test', 1, 1, '2026-10-26 08:16:04', '2026-10-26 08:16:04'),
(2, 'MEM-0001', 'aamin shaikh', '09689439943', 'hasina94shaikh@gmail.com', 4, 1, '2026-10-01', '2026-12-30', 'satara', NULL, 'active', 'test', 1, 1, '2026-10-26 08:49:52', '2026-10-26 08:49:52'),
(3, 'SMP-001', 'Rahul Deshmukh', '9800000001', 'rahul.sample@example.com', 1, 1, '2026-07-08', '2026-10-06', 'Hotel Sea Breeze, Dadar', NULL, 'active', 'Overdue sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(4, 'SMP-002', 'Priya Kulkarni', '9800000002', 'priya.sample@example.com', 2, 1, '2026-07-23', '2026-10-21', 'Club House, Parel', NULL, 'active', 'Overdue sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(5, 'SMP-003', 'Amit Patil', '9800000003', 'amit.sample@example.com', 3, 2, '2026-07-28', '2026-10-26', 'Vashi Community Hall', NULL, 'active', 'Due today sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(6, 'SMP-004', 'Sneha Joshi', '9800000004', 'sneha.sample@example.com', 1, 2, '2026-08-01', '2026-10-30', 'Nerul Business Centre', NULL, 'active', 'Due soon sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(7, 'SMP-005', 'Vikram Shinde', '9800000005', 'vikram.sample@example.com', 2, 3, '2026-08-09', '2026-11-07', 'Koregaon Park Hall', NULL, 'active', 'Approaching sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(8, 'SMP-006', 'Neha Bhosale', '9800000006', 'neha.sample@example.com', 4, 3, '2026-08-22', '2026-11-20', 'Baner Conference Room', NULL, 'active', 'Approaching sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(9, 'SMP-007', 'Sanjay More', '9800000007', 'sanjay.sample@example.com', 1, 1, '2026-09-11', '2026-12-10', 'Lower Parel Office', NULL, 'active', 'Upcoming sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(10, 'SMP-008', 'Kavita Pawar', '9800000008', 'kavita.sample@example.com', 3, 2, '2026-10-16', '2027-01-14', 'Belapur Club', NULL, 'active', 'Upcoming sample', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(11, 'SMP-009', 'Rohan Gaikwad', '9800000009', 'rohan.sample@example.com', 2, 3, '2026-09-16', '2026-12-15', 'Shivajinagar Hall', NULL, 'active', 'Scheduled sample (confirmed)', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(12, 'SMP-010', 'Meera Naik', '9800000010', 'meera.sample@example.com', 1, 1, '2026-08-17', '2026-11-15', 'Worli Banquet', NULL, 'active', 'Scheduled sample (not confirmed)', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(13, 'SMP-011', 'Deepak Salunkhe', '9800000011', 'deepak.sample@example.com', 4, 3, '2026-08-07', '2026-11-05', 'Kothrud Hall', NULL, 'inactive', 'Inactive sample - must NOT appear in open lists', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(18, 'SMP-012', 'Anita Chavan', '9800000012', 'anita.sample@example.com', 1, 2, NULL, NULL, NULL, NULL, 'active', 'No presentation recorded yet', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `member_registrations`
--

CREATE TABLE `member_registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `member_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(190) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `chapter_id` int(10) UNSIGNED NOT NULL,
  `last_presentation_date` date DEFAULT NULL,
  `venue` varchar(190) DEFAULT NULL,
  `last_presentation_chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `member_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `member_id` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_registrations`
--

INSERT INTO `member_registrations` (`id`, `member_code`, `full_name`, `mobile`, `email`, `category_id`, `chapter_id`, `last_presentation_date`, `venue`, `last_presentation_chapter_id`, `member_status`, `remarks`, `status`, `rejection_reason`, `member_id`, `reviewed_by`, `reviewed_at`, `ip_address`, `created_at`) VALUES
(1, 'MEM-0002', 'aamin shaikh', '09689439943', 'shaikhhassi377@gmail.com', 1, 1, NULL, 'satara', 2, 'active', 'rsfsdf', 'pending', NULL, NULL, NULL, NULL, '::1', '2026-10-26 11:20:11');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title` varchar(150) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `presentations`
--

CREATE TABLE `presentations` (
  `id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `calculated_due_date` date DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `venue` varchar(190) DEFAULT NULL,
  `completed_chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','scheduled','completed','cancelled') NOT NULL DEFAULT 'pending',
  `details_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `open_member_id` int(10) UNSIGNED GENERATED ALWAYS AS (case when `status` in ('pending','scheduled') then `member_id` else NULL end) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `presentations`
--

INSERT INTO `presentations` (`id`, `member_id`, `calculated_due_date`, `scheduled_date`, `completed_date`, `venue`, `completed_chapter_id`, `status`, `details_confirmed`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, '2026-10-16', 'Main Hall', NULL, 'completed', 1, '[26 Oct 2026 14:14 · System] Imported from the member profile.', NULL, NULL, '2026-10-26 08:44:55', '2026-10-26 08:44:55'),
(2, 1, '2027-01-14', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 08:44:55', '2026-10-26 08:44:55'),
(3, 2, NULL, NULL, '2026-10-01', 'satara', NULL, 'completed', 1, '[26 Oct 2026 14:19 · hasina shaikh] Recorded from the member profile.', 1, 1, '2026-10-26 08:49:52', '2026-10-26 08:49:52'),
(4, 2, '2026-12-30', NULL, NULL, NULL, NULL, 'pending', 0, NULL, 1, 1, '2026-10-26 08:49:52', '2026-10-26 08:49:52'),
(5, 3, NULL, NULL, '2026-07-08', 'Hotel Sea Breeze, Dadar', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(6, 4, NULL, NULL, '2026-07-23', 'Club House, Parel', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(7, 5, NULL, NULL, '2026-07-28', 'Vashi Community Hall', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(8, 6, NULL, NULL, '2026-08-01', 'Nerul Business Centre', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(9, 7, NULL, NULL, '2026-08-09', 'Koregaon Park Hall', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(10, 8, NULL, NULL, '2026-08-22', 'Baner Conference Room', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(11, 9, NULL, NULL, '2026-09-11', 'Lower Parel Office', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(12, 10, NULL, NULL, '2026-10-16', 'Belapur Club', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(13, 11, NULL, NULL, '2026-09-16', 'Shivajinagar Hall', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(14, 12, NULL, NULL, '2026-08-17', 'Worli Banquet', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(15, 13, NULL, NULL, '2026-08-07', 'Kothrud Hall', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Sample data.', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(20, 3, '2026-10-06', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(21, 4, '2026-10-21', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(22, 5, '2026-10-26', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(23, 6, '2026-10-30', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(24, 7, '2026-11-07', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(25, 8, '2026-11-20', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(26, 9, '2026-12-10', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(27, 10, '2027-01-14', NULL, NULL, NULL, NULL, 'pending', 0, NULL, NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(28, 11, '2026-12-15', '2026-11-05', NULL, 'Shivajinagar Hall', NULL, 'scheduled', 1, '[26 Oct 2026 14:30 · System] Scheduled (sample, confirmed).', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(29, 12, '2026-11-15', '2026-11-10', NULL, NULL, NULL, 'scheduled', 0, '[26 Oct 2026 14:30 · System] Scheduled (sample, not confirmed).', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59'),
(35, 3, '2026-07-03', NULL, '2026-04-04', 'Hotel Sea Breeze, Dadar', NULL, 'completed', 1, '[26 Oct 2026 14:30 · System] Older cycle (sample).', NULL, NULL, '2026-10-26 09:00:59', '2026-10-26 09:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('admin_alert_email', '', 'Email address that receives overdue alerts', '2026-10-26 07:29:38'),
('approaching_days', '30', 'Status becomes \"Approaching\" this many days before due date', '2026-10-26 07:29:38'),
('email_from_address', '', 'Sender email address', '2026-10-26 07:29:38'),
('email_from_name', 'Presenter Manager', 'Sender name for emails', '2026-10-26 07:29:38'),
('enable_30day_reminders', '0', 'Send optional 30-day reminder emails', '2026-10-26 07:29:38'),
('enable_followup_emails', '1', 'Send follow-up for unconfirmed details', '2026-10-26 07:29:38'),
('enable_monthly_reminders', '1', 'Send monthly reminder emails', '2026-10-26 07:29:38'),
('followup_after_days', '7', 'Days to wait before a follow-up email', '2026-10-26 07:29:38'),
('presentation_interval_days', '90', 'Days between presentations', '2026-10-26 07:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','chapter_coordinator') NOT NULL DEFAULT 'chapter_coordinator',
  `chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `chapter_id`, `is_active`, `failed_attempts`, `locked_until`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'hasina shaikh', 'hasina94shaikh@gmail.com', '$2y$10$TLjEOaCUEVlyeUUWT.V33.j8mTS8CPPPr83W2mNDSvW1zbiW1J3Ri', 'super_admin', NULL, 1, 0, NULL, '2026-10-27 15:02:57', '2026-10-26 07:57:10', '2026-10-27 09:32:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`);

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_chapters_name` (`name`),
  ADD UNIQUE KEY `uq_chapters_code` (`code`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_dedupe` (`dedupe_key`),
  ADD KEY `idx_email_status` (`status`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_email_member` (`member_id`),
  ADD KEY `idx_email_created` (`created_at`),
  ADD KEY `fk_email_pres` (`presentation_id`),
  ADD KEY `fk_email_sent_by` (`sent_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_members_code` (`member_code`),
  ADD KEY `idx_members_name` (`full_name`),
  ADD KEY `idx_members_email` (`email`),
  ADD KEY `idx_members_chapter` (`chapter_id`),
  ADD KEY `idx_members_category` (`category_id`),
  ADD KEY `idx_members_status` (`status`),
  ADD KEY `idx_members_next_due` (`next_due_date`),
  ADD KEY `fk_members_created_by` (`created_by`),
  ADD KEY `idx_members_coordinator` (`coordinator_id`),
  ADD KEY `idx_members_mobile` (`mobile`),
  ADD KEY `idx_members_last_chapter` (`last_presentation_chapter_id`);

--
-- Indexes for table `member_registrations`
--
ALTER TABLE `member_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reg_status` (`status`),
  ADD KEY `idx_reg_email` (`email`),
  ADD KEY `idx_reg_chapter` (`chapter_id`),
  ADD KEY `idx_reg_ip_created` (`ip_address`,`created_at`),
  ADD KEY `fk_reg_category` (`category_id`),
  ADD KEY `fk_reg_member` (`member_id`),
  ADD KEY `fk_reg_reviewer` (`reviewed_by`),
  ADD KEY `fk_reg_last_chapter` (`last_presentation_chapter_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `presentations`
--
ALTER TABLE `presentations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_one_open_per_member` (`open_member_id`),
  ADD KEY `idx_pres_member` (`member_id`),
  ADD KEY `idx_pres_status` (`status`),
  ADD KEY `idx_pres_due` (`calculated_due_date`),
  ADD KEY `idx_pres_scheduled` (`scheduled_date`),
  ADD KEY `idx_pres_completed` (`completed_date`),
  ADD KEY `fk_pres_created_by` (`created_by`),
  ADD KEY `fk_pres_updated_by` (`updated_by`),
  ADD KEY `fk_pres_completed_chapter` (`completed_chapter_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_chapter` (`chapter_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `member_registrations`
--
ALTER TABLE `member_registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presentations`
--
ALTER TABLE `presentations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_pres` FOREIGN KEY (`presentation_id`) REFERENCES `presentations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_members_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_members_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_members_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_members_last_chapter` FOREIGN KEY (`last_presentation_chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `member_registrations`
--
ALTER TABLE `member_registrations`
  ADD CONSTRAINT `fk_reg_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reg_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reg_last_chapter` FOREIGN KEY (`last_presentation_chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reg_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reg_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `presentations`
--
ALTER TABLE `presentations`
  ADD CONSTRAINT `fk_pres_completed_chapter` FOREIGN KEY (`completed_chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pres_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pres_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pres_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
