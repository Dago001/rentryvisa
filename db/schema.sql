-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 12, 2026 at 04:51 PM
-- Server version: 8.0.43-cll-lve
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nisappsc`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'Odeh', 'CREATE', 'Created new visa application for Usman, Umar (Visa: R2323)', NULL, NULL, '2025-11-21 07:15:22'),
(2, 'Odeh', 'CREATE', 'Created new visa application for Kim, Sun Jimlong (Visa: R2908765)', NULL, NULL, '2025-11-21 08:14:07'),
(3, 'Odeh', 'CREATE', 'Created new visa application for John, Emmanuel (Visa: R203040)', NULL, NULL, '2025-11-21 09:10:45'),
(4, 'Odeh', 'CREATE', 'Created new visa application for Eze, Adamma (Visa: R12120)', NULL, NULL, '2025-11-21 10:03:47'),
(5, 'Odeh', 'CREATE', 'Created new visa application for Dagogo, Gift (Visa: R900308333)', NULL, NULL, '2025-11-21 11:05:06'),
(6, 'Odeh', 'CREATE', 'Created new visa application for Fred, Gift (Visa: R0093735)', NULL, NULL, '2025-11-21 11:19:46'),
(7, 'Odeh', 'CREATE', 'Created new visa application for Herny, Herny (Visa: T97979QEEE)', NULL, NULL, '2025-11-21 20:45:15'),
(8, 'Odeh', 'CREATE', 'Created new visa application for DAGOGO, GIFT WILFRED (Visa: B8544477444)', NULL, NULL, '2025-12-12 08:34:36'),
(9, 'Odeh', 'CREATE', 'Created new visa application for DAGOGO, ABIYE DARINE (Visa: KK2233)', NULL, NULL, '2025-12-12 08:39:44'),
(10, 'Odeh', 'CREATE', 'Created new visa application for KAKAKI, MERCY (Visa: W22232223)', NULL, NULL, '2025-12-20 09:06:53'),
(11, 'Odeh', 'CREATE', 'Created new visa application for Gdddgdgd, Gdgdgdg (Visa: B123242424)', NULL, NULL, '2025-12-21 11:19:02'),
(12, 'Odeh', 'CREATE', 'Created new visa application for Wewe, Wewe (Visa: 127748)', NULL, NULL, '2025-12-21 11:25:32'),
(13, 'Odeh', 'CREATE', 'Created new visa application for Wewe, Wewe (Visa: TWTWTWTWTW)', NULL, NULL, '2025-12-21 11:33:42'),
(14, 'Odeh', 'CREATE', 'Created new visa application for DKNOAKNDA, ADADADA (Visa: B6666)', NULL, NULL, '2025-12-21 12:00:27'),
(15, 'Odeh', 'CREATE', 'Created new visa application for SFSFSF, SFSF (Visa: ERERER)', NULL, NULL, '2025-12-21 12:10:16'),
(16, 'Odeh', 'CREATE', 'Created new visa application for DFSFSF, SFSFS (Visa: 1211212)', NULL, NULL, '2025-12-21 12:22:49'),
(17, 'Odeh', 'CREATE', 'Created new visa application for SFSFSF, SFSSFSF (Visa: WURGWU)', NULL, NULL, '2025-12-21 12:51:08'),
(18, 'Odeh', 'CREATE', 'Created new visa application for Sdsds, Sdsd (Visa: DDSSDS)', NULL, NULL, '2025-12-21 19:12:05'),
(19, 'Odeh', 'CREATE', 'Created new visa application for Oooo, Oooo (Visa: OOOO)', NULL, NULL, '2025-12-21 19:13:10'),
(20, 'Guest Guest', 'CREATE', 'Created new visa application for Dgdgd, Dgdgd (Visa: 22325224)', NULL, NULL, '2025-12-21 21:42:13'),
(21, 'Adamma Eze', 'CREATE', 'Created new visa application for John, Paul', NULL, NULL, '2025-12-22 10:39:03'),
(22, 'Adamma Eze', 'CREATE', 'Created new visa application for Abas, Abas', NULL, NULL, '2025-12-22 11:03:54'),
(23, 'Odeh', 'CREATE', 'Created new visa application for John, Paul (Visa: 222BB1)', NULL, NULL, '2025-12-22 11:59:06'),
(24, 'Adamma Eze', 'CREATE', 'Created new visa application for Usman, Paul (Visa: R203011)', NULL, NULL, '2025-12-23 04:08:40'),
(25, '35562', 'CREATE', 'Created new visa application for Jsjdusu, Jsjsjs (Visa: 777DJJDJ)', NULL, NULL, '2026-01-11 20:21:48'),
(26, '35562', 'CREATE', 'Created new visa application for MBA, RODRIGUE FRANCIS (Visa: R1268290)', NULL, NULL, '2026-01-12 14:50:20'),
(27, '35562', 'CREATE', 'Created new visa application for Daohjohfe, Eetete (Visa: Q366254QQQQQQ)', NULL, NULL, '2026-08-06 04:34:18'),
(28, '35562', 'SEARCH', 'Performed global database search for query: \'QQQIIRUIEIU\'', '102.91.102.243', NULL, '2026-08-06 04:34:34'),
(29, '35562', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: Q366254QQQQQQ via Global Search', '102.91.102.243', NULL, '2026-08-06 04:34:41'),
(30, '35562', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: Q366254QQQQQQ via Global Search', '102.91.102.243', NULL, '2026-08-06 04:34:49'),
(31, '35562', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 39031', '197.159.65.194', NULL, '2026-08-10 08:40:35'),
(32, '39031', 'CREATE', 'Created new visa application for Usman, Emmanuel (Visa: 222B222)', NULL, NULL, '2026-08-10 08:44:26'),
(33, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: 222B222', '197.159.65.195', NULL, '2026-08-10 08:44:50'),
(34, '39031', 'EDIT', 'Edited visa application record for Visa No: 222B222', '197.159.65.195', NULL, '2026-08-10 08:45:12'),
(35, '39031', 'UPDATE', 'Updated user profile details', '197.159.65.195', NULL, '2026-08-10 08:55:05'),
(36, '39031', 'CREATE', 'Created new visa application for Abubarkar, Umar (Visa: R1232117)', NULL, NULL, '2026-08-10 12:44:51'),
(37, '35562', 'UPDATE', 'Updated user profile details', '105.112.120.136', NULL, '2026-08-12 03:39:26'),
(38, '35562', 'CREATE', 'Created new visa application for DDDDDDDDDDDDDDDDDDD, DDDDDDDDDDDDDDDD (Visa: 11111111111111111111)', NULL, NULL, '2026-08-12 03:43:09'),
(39, '35562', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: 11111111111111111111', '105.112.120.136', NULL, '2026-08-12 03:43:41'),
(40, '39031', 'UPDATE', 'Updated user profile details', '197.159.65.195', NULL, '2026-08-12 08:28:31'),
(41, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: 222BB1', '197.159.65.195', NULL, '2026-08-12 08:50:11'),
(42, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: 222BB1', '197.159.65.195', NULL, '2026-08-12 08:50:19'),
(43, '39031', 'CREATE', 'Created new visa application for USMA, UMAR (Visa: RV233)', NULL, NULL, '2026-08-12 08:58:52'),
(44, '39031', 'CREATE', 'Created new visa application for USMA, UMAR (Visa: R2323)', NULL, NULL, '2026-08-12 09:02:11'),
(45, '39031', 'EDIT', 'Edited visa application record for Visa No: R2323', '197.159.65.195', NULL, '2026-08-12 09:04:05'),
(46, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R2323', '197.159.65.195', NULL, '2026-08-12 09:12:42'),
(47, '39031', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 36921', '197.159.65.195', NULL, '2026-08-12 10:18:10'),
(48, '39031', 'CREATE', 'Created new visa application for EZE, ADA (Visa: RV1234)', NULL, NULL, '2026-08-12 13:17:39'),
(49, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: RV1234', '102.91.4.143', NULL, '2026-08-12 13:18:03'),
(50, '39031', 'EDIT', 'Edited visa application record for Visa No: RV1234', '102.91.4.143', NULL, '2026-08-12 13:19:03'),
(51, '35562', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 35375', '102.91.4.143', NULL, '2026-08-12 13:42:15'),
(52, '39031', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 35579', '197.159.65.194', NULL, '2026-08-12 13:54:21'),
(53, '35375', 'CREATE', 'Created new visa application for DAVID, VINCINE (Visa: R1295171)', NULL, NULL, '2026-08-12 13:55:34'),
(54, '35375', 'EDIT', 'Edited visa application record for Visa No: R1295171', '197.159.65.194', NULL, '2026-08-12 13:56:40'),
(55, '35375', 'EDIT', 'Edited visa application record for Visa No: R1295171', '197.159.65.194', NULL, '2026-08-12 13:56:44'),
(56, '37084', 'CREATE', 'Created new visa application for FONBAUSTIER, GALA  FRANCOISE  TINA (Visa: R1271930)', NULL, NULL, '2026-08-12 14:05:05'),
(57, '37084', 'EDIT', 'Edited visa application record for Visa No: R1271930', '197.159.65.195', NULL, '2026-08-12 14:05:47'),
(58, '37084', 'EDIT', 'Edited visa application record for Visa No: R1271930', '197.159.65.195', NULL, '2026-08-12 14:05:50'),
(59, '37084', 'EDIT', 'Edited visa application record for Visa No: R1271930', '197.159.65.195', NULL, '2026-08-12 14:06:10'),
(60, '35562', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user:                                             36022', '197.159.65.194', NULL, '2026-08-12 14:06:27'),
(61, '34079', 'CREATE', 'Created new visa application for DAVID, VINCIANE (Visa: RV 1295171)', NULL, NULL, '2026-08-12 14:34:46'),
(62, '34079', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: RV 1295171', '197.159.65.195', NULL, '2026-08-12 14:35:53');

-- --------------------------------------------------------

--
-- Table structure for table `old_visa_applications`
--

CREATE TABLE IF NOT EXISTS `old_visa_applications` (
  `passport_number` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `surname` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `given_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nationality` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `place_of_issue` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `authority` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `valid_until` date NOT NULL,
  `journey` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `visa_type` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `fees` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `application_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `old_visa_applications`
--

INSERT INTO `old_visa_applications` (`passport_number`, `surname`, `given_name`, `nationality`, `place_of_issue`, `date_of_issue`, `authority`, `valid_until`, `journey`, `visa_type`, `fees`, `application_date`, `status`) VALUES
('B123456', 'Dagogo', 'Gift', '', 'ABUJA', '2025-02-12', 'embassy', '0000-00-00', 'multiple', '', '10000.00', '2025-11-18 11:54:21', 'pending'),
('B123456', 'Jaja', 'ADA', '', 'ABUJA', '2023-02-02', 'HC', '0000-00-00', 'single', '', '0.91', '2025-11-18 11:46:58', 'pending'),
('a53242356', 'JANSEN', 'KUMAR', '', 'ABUJA', '2025-10-07', 'INGO', '2026-01-04', 'multiple', 'business', '200.00', '2025-10-07 11:18:45', 'pending'),
('a53242356', 'JANSEN', 'KUMAR', '', 'ABUJA', '2025-10-07', 'embassy', '2026-02-04', 'single', 'business', '200.00', '2025-10-07 12:16:03', 'pending'),
('A5324235645', 'SURESH', 'KUMAR', '', 'ABUJA', '2025-10-07', 'INTORG', '2026-02-04', 'single', 'tourist', '200.00', '2025-11-11 15:45:47', 'pending'),
('BK00790987', 'SUN', 'KIM LEE', '', 'BEIJING', '2024-03-04', 'embassy', '0000-00-00', 'multiple', '', '120.00', '2025-11-19 11:35:05', 'pending'),
('A3456778', 'DET', 'CALEB JAMES', '', 'VIENNA', '2023-08-09', 'embassy', '0000-00-00', 'multiple', '', '0.00', '2025-11-19 14:51:54', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit`
--

CREATE TABLE IF NOT EXISTS `tbl_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `serviceNo` int NOT NULL,
  `Activity` varchar(500) NOT NULL,
  `entryDate` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE IF NOT EXISTS `tbl_roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE IF NOT EXISTS `tbl_users` (
  `user_id` varchar(7) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `user_role` varchar(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `photo` varchar(50) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`user_id`, `email_address`, `password`, `user_role`, `firstname`, `lastname`, `photo`) VALUES
('2000', 'ige@gmail.com', '123', 'Admin', 'Moses', 'Ige', ''),
('2001', 'jt@gmail.com', '123', 'Admin', 'Vihishima', 'JT', ''),
('2002', 'obed@gmail.com', '123', 'Admin', 'Obed', 'Obed', ''),
('2003', 'lasi@gmail.com', '123', 'Admin', 'Lasisi', 'Lasisi', ''),
('2004', 'jaja@gmail.com', '123', 'Admin', 'Jaja', 'Jaja', ''),
('2005', 'adanma@gmail.com', '123', 'Admin', 'Eze', 'Adanma', '2005.jpg'),
('27266', 'podeh@immigration.gov.ng', 'immigration123', 'Admin', 'Patrick', 'Odeh', ''),
('35562', 'dago26go@gmail.com', 'ba77d4fa0bb858d14129a4b7146bb831', 'Admin', 'Dagogo', 'Gift', '35562.png');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Admin','Supervisor','DataEntry','Report Viewer','CGI') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DataEntry',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `google_auth_secret` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_no` (`service_no`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `service_no`, `fname`, `lname`, `email`, `password`, `role`, `photo`, `created_at`, `updated_at`, `status`, `google_auth_secret`) VALUES
(1, '35562', 'gift', 'dagogo', 'dag@gmail.com', '$2y$10$YDXsRFRt1zIOa8TAXmo0ruQqbnwykNn5wkxRiGCXMwEd0jIIt9JJy', 'Admin', 'uploads/6a7beaeec7f01.png', '2025-12-21 20:43:54', '2026-08-12 03:39:26', 'active', 'HPBS3ZMFGQKBBN6E'),
(5, '39031', 'Adamma', 'Eze', 'ezeab.nis@gmail.com', '$2y$10$3P5/2vxI11ooeIrkHacrrOUSM7pbleOWZBmb.h3IHCwff3jJp7jj2', 'Admin', 'uploads/6a7c2eaf81efd.png', '2025-12-22 06:17:39', '2026-08-12 08:28:31', 'active', 'AQ74MNZIBGAMV7JC'),
(6, '27266', 'PATRICK', 'ODEH', '27266@nis.com', '$2y$10$r1gK.WKyqaJrJh.iegENjeNdHki9.SCx2fzzIhfjFvOVvTsy2YTvW', 'Admin', '', '2025-12-22 09:41:41', '2025-12-23 04:04:52', 'active', NULL),
(7, '11284', 'A G', 'Shaba', 'shaba@gmail.com', '$2y$10$2bDPG5uz.BqOhm1XD3QXd.jTANFKnqlb3TtWF7eFGd7DGyxnZC0rS', 'Supervisor', '', '2025-12-22 13:56:09', '2025-12-22 13:56:09', 'active', NULL),
(8, '35579', 'A I ', 'Mohammed ', 'mohammed@gmail.com', '$2y$10$uXnT/yrH3UGYYswloEB7QeQBrlqx3XclcU05pfsxONcW5zehqujVG', 'DataEntry', '', '2025-12-22 14:04:47', '2026-08-12 13:57:01', 'active', 'MS4VSQLLNWZUKQUP'),
(9, '36921', 'Akpan', 'N S', 'akpan@gmail.com', '$2y$10$4iZc3mdNZFLe.jo2mP.b0e1QuM8Fw8k2xtg.vCFL3yii3XUIxkZpG', 'DataEntry', '', '2025-12-22 14:06:59', '2026-08-12 10:21:09', 'active', 'SV6YYPFBIWLUHXEJ'),
(10, '35375', 'Sani ', 'Y', 'sani@gmail.com', '$2y$10$xHZcdlWA9K2NTKRcOttir.CGwxd9Cyuv.EYjqhGoUxXiID2FCBLt2', 'DataEntry', '', '2025-12-22 14:07:54', '2026-08-12 13:45:07', 'active', 'MZHS7WPWNSKWHTJ3'),
(11, '37084', 'NKIRUKA', 'CHRISTIAN', 'cnkiruka15@gmail.com', '$2y$10$/bn3P5fEHJfawzWoOT2iaOY4rcscgkKIyVy1K7FYIFMr0k6hoiJAq', 'DataEntry', '', '2026-08-12 13:44:36', '2026-08-12 13:50:24', 'active', 'E4PSRQBOL3XH237C'),
(12, '                                            36022', 'MOHAMMED', 'BALA', 'basla@gmail.com', '$2y$10$0gnQ.5uFuvhvB.nME9arrOdamA6vH11rLTw8ZXP49pRaRfxxEiH3i', 'DataEntry', '', '2026-08-12 14:06:19', '2026-08-12 14:06:27', 'active', NULL),
(13, '34079', 'IK', 'ANYANWU', 'ikuru2000@gmail.com', '$2y$10$EDvi9FJFaIT6PU326tL1IO4FZ.1I077gyZpDKZWRZIYLc6FEofaZS', 'DataEntry', '', '2026-08-12 14:20:17', '2026-08-12 14:21:19', 'active', 'LPWMT467XPLPRJBH'),
(14, '27015', 'DAUDA', 'LAWAL', 'lawaldabbas@gmail.com', '$2y$10$GGQ1KFfSCybHMj0bUK3rUOx925rVr.Xm2W/k8ut3ddbxUH7OaBLi6', 'Supervisor', '', '2026-08-12 14:44:00', '2026-08-12 14:50:11', 'active', '6UXMZHYKSSM2EWFB');

-- --------------------------------------------------------

--
-- Table structure for table `visa_applications`
--

CREATE TABLE IF NOT EXISTS `visa_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visa_number` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `passport_number` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `surname` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `given_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nationality` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `place_of_issue` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `embassy` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `authority` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `within` date NOT NULL,
  `journey` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `fees` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `application_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT 'pending',
  `created_by` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `category` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'NONE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `visa_applications`
--

INSERT INTO `visa_applications` (`id`, `visa_number`, `passport_number`, `surname`, `given_name`, `nationality`, `place_of_issue`, `date_of_issue`, `embassy`, `authority`, `within`, `journey`, `fees`, `application_date`, `status`, `created_by`, `created_at`, `updated_at`, `category`) VALUES
(29, '222BB1', 'B123440', 'Stephen', 'Paul', 'Belgium', 'Belgium', '2024-02-20', 'Belgium', 'NG', '2025-02-02', 'Single', 'Gratis', '2025-12-22 11:59:06', 'active', 'Odeh', NULL, '2026-01-13 08:43:23', 'NONE'),
(30, 'R203011', 'B22323', 'Usman', 'Paul', 'Benin', 'Benin', '2024-03-02', 'Benin', 'NG', '2025-02-02', 'multiple', 'Gratis', '2025-12-23 04:08:40', 'active', 'Adamma Eze', NULL, '2025-12-23 04:08:40', 'NONE'),
(31, '777DJJDJ', 'SNDNDDN66666', 'Jsjdusu', 'Jsjsjs', 'Andorra', 'Jsjdhsh', '2026-01-01', 'Hdhdhd', 'Jdjdjd', '2026-04-17', 'single', 'Gratis', '2026-01-11 20:21:48', 'active', '35562', NULL, '2026-01-11 20:21:48', 'NONE'),
(32, 'R1268290', 'D00343034', 'MBA', 'RODRIGUE FRANCIS', 'Gabon', 'HQ', '2026-01-12', 'GABON', 'ADGAGGHAG', '2027-11-12', 'multiple', 'Gratis', '2026-01-12 14:50:20', 'active', '35562', NULL, '2026-01-12 14:50:20', 'NONE'),
(33, 'Q366254QQQQQQ', 'QQQIIRUIEIU', 'Daohjohfe', 'Eetete', 'Azerbaijan', 'Tetetete', '2026-06-12', 'Fdggdg', 'Dgtete', '2026-08-21', 'multiple', 'Gratis', '2026-08-06 04:34:18', 'active', '35562', NULL, '2026-08-06 04:34:18', 'NONE'),
(34, '222B222', 'B25566767', 'Usman', 'Emmanuel', 'Belgium', 'Algeria', '2026-01-13', 'United Kingdom', 'Embassy', '2027-02-10', 'MULTIPLE', 'Gratis', '2026-08-10 08:44:26', 'active', '39031', NULL, '2026-08-10 08:45:12', 'NONE'),
(35, 'R1232117', 'B233488677', 'Abubarkar', 'Umar', 'Angola', 'ABUJA HQTRSS', '2026-08-01', 'UNICEF', 'ABUJA', '2026-08-27', 'multiple', 'Gratis', '2026-08-10 12:44:51', 'active', '39031', NULL, '2026-08-10 12:44:51', 'NONE'),
(37, 'RV233', 'B12344', 'USMA', 'UMAR', 'NAMIBIA', 'ABUJA HQTRS', '2026-08-12', 'UGANDA', 'ABJ/HQ/DIP/12344', '2026-08-29', 'MULTIPLE', 'GRATIS', '2026-08-12 08:58:52', 'active', '39031', NULL, '2026-08-12 08:58:52', 'DIPLOMAT'),
(38, 'R2323', 'B12344', 'JOHN', 'ABAS', 'GAMBIA', 'ABUJA HQTRS', '2026-08-12', 'UGANDA', 'ABJ/HQ/DIP/22', '2026-08-30', 'SINGLE', 'GRATIS', '2026-08-12 09:02:11', 'active', '39031', NULL, '2026-08-12 09:04:05', 'DIPLOMAT'),
(39, 'RV1234', 'B23455767', 'EZE', 'ADAH', 'BELARUS', 'ABUJA HQTRS', '2026-08-12', 'USA WASHINTON DC', 'ABJ/HQ/DIP/12334', '2026-08-29', 'MULTIPLE', 'GRATIS', '2026-08-12 13:17:39', 'active', '39031', NULL, '2026-08-12 13:19:03', 'DIPLOMAT'),
(40, 'R1295171', '23CD074451', 'DAVID', 'VINCINE', 'FRANCE', 'ABUJA HQTRS', '2026-08-12', 'EMBASSY OF FRANCE', 'ABJ/HQ/DIP/EM/03/402', '2028-08-27', 'MULTIPLE', 'GRATIS', '2026-08-12 13:55:34', 'active', '35375', NULL, '2026-08-12 13:56:44', 'DIPLOMAT'),
(41, 'R1271930', '23CE20891', 'FONBAUSTIER', 'GALA  FRANCOISE  TINA', 'NORTH MACEDONIA', 'ABUJA HQTRS', '2007-08-17', 'EMBASSY  OF FRANCE', 'ABJ/HQ/DIP/EM/03/370', '2028-09-02', 'MULTIPLE', 'GRATIS', '2026-08-12 14:05:05', 'active', '37084', NULL, '2026-08-12 14:06:10', 'DIPLOMAT'),
(42, 'RV 1295171', '23CD07451', 'DAVID', 'VINCIANE', 'FRANCE', 'ABUJA HQTRS', '2024-08-23', 'EMBASSY  OF FRANCE', 'ABJ/HQ/DIP/04/300', '2026-08-13', 'MULTIPLE', 'GRATIS', '2026-08-12 14:34:46', 'active', '34079', NULL, '2026-08-12 14:34:46', 'DIPLOMAT');

COMMIT;
