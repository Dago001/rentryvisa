-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 25, 2026 at 10:45 AM
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
-- Database: `nisappsc_visavignette`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int NOT NULL,
  `user_id` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
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
(62, '34079', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: RV 1295171', '197.159.65.195', NULL, '2026-08-12 14:35:53'),
(63, '39031', 'CREATE', 'Created new visa application for WRWR, WRWR (Visa: QEQEQEQ)', NULL, NULL, '2026-08-13 11:51:30'),
(64, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: QEQEQEQ', '102.91.92.245', NULL, '2026-08-13 12:43:20'),
(65, '39031', 'CREATE', 'Created new visa application for EZE, ADA (Visa: RV123411)', NULL, NULL, '2026-08-13 12:48:25'),
(66, '39031', 'CREATE', 'Created new visa application for EZE, ADA (Visa: RV23223)', NULL, NULL, '2026-08-13 12:50:03'),
(67, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: RV23223', '102.91.92.245', NULL, '2026-08-13 12:50:18'),
(68, '39031', 'EDIT', 'Edited visa application record for Visa No: RV23223', '102.91.92.245', NULL, '2026-08-13 12:50:36'),
(69, '39031', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 36921', '102.91.92.245', NULL, '2026-08-13 13:02:24'),
(70, '39031', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 45866', '102.91.92.245', NULL, '2026-08-13 13:13:32'),
(71, '39031', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 36921', '102.91.92.245', NULL, '2026-08-13 13:17:29'),
(72, '45866', 'CREATE', 'Created new visa application for BHIM, SINGH (Visa: R1266988)', NULL, NULL, '2026-08-13 13:25:03'),
(73, '45866', 'EDIT', 'Edited visa application record for Visa No: R1266988', '102.91.92.245', NULL, '2026-08-13 13:26:57'),
(74, '45866', 'EDIT', 'Edited visa application record for Visa No: R1266988', '102.91.92.245', NULL, '2026-08-13 13:27:05'),
(75, '45866', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1266988', '102.91.92.245', NULL, '2026-08-13 13:27:10'),
(76, '33477', 'CREATE', 'Created new visa application for KANYA, WILLY (Visa: R 12951715)', NULL, NULL, '2026-08-13 13:44:39'),
(77, '40315', 'CREATE', 'Created new visa application for ALEX, YOUNG (Visa: RV12952)', NULL, NULL, '2026-08-13 13:55:22'),
(78, '19650', 'CREATE', 'Created new visa application for ROSTER, TREWS (Visa: 123455)', NULL, NULL, '2026-08-13 14:20:27'),
(79, '27015', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1266988', '102.91.93.121', NULL, '2026-08-13 14:32:12'),
(80, '27015', 'EDIT', 'Edited visa application record for Visa No: R1266988', '102.91.93.121', NULL, '2026-08-13 14:32:55'),
(81, '27015', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1266988', '102.91.93.121', NULL, '2026-08-13 14:33:44'),
(82, '27015', 'CREATE', 'Created new visa application for LAWAL, DAUDA (Visa: 12337UP)', NULL, NULL, '2026-08-13 14:41:56'),
(83, '27015', 'CREATE', 'Created new visa application for LAWAL, DAUDA (Visa: 12337UP33)', NULL, NULL, '2026-08-13 14:44:29'),
(84, '33477', 'CREATE', 'Created new visa application for JIDDAH, HENRIATTA (Visa: R7654344323)', NULL, NULL, '2026-08-14 12:10:16'),
(85, '39031', 'CREATE', 'Created new visa application for AUDU, PRECIOUS (Visa: 23431)', NULL, NULL, '2026-08-17 12:28:26'),
(86, '39031', 'CREATE', 'Created new visa application for AUDU, PRECIOUS (Visa: RV3322)', NULL, NULL, '2026-08-17 12:29:55'),
(87, '27015', 'CREATE', 'Created new visa application for AUDU, PRECIOUS (Visa: RV123444)', NULL, NULL, '2026-08-17 14:16:25'),
(88, '35562', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 36921', '102.91.77.69', NULL, '2026-08-20 09:56:13'),
(89, 'AKPAN001', 'CREATE', 'Created new visa application for SSENTEZA, SIMON PETER (Visa: R1272549)', NULL, NULL, '2026-08-20 10:06:21'),
(90, 'AKPAN001', 'UPDATE', 'Updated user profile details', '102.91.77.69', NULL, '2026-08-20 10:09:38'),
(91, 'AKPAN001', 'CREATE', 'Created new visa application for OGUME, LAKI CHRISTOPHER (Visa: R1272548)', NULL, NULL, '2026-08-20 10:18:58'),
(92, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, SAMUEL OPPONG (Visa: R1272551)', NULL, NULL, '2026-08-20 10:29:16'),
(93, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, SUSSANA  NYARKOAH (Visa: R1272552)', NULL, NULL, '2026-08-20 10:38:03'),
(94, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, CHRISTABEL NYARKOAH (Visa: R1272553)', NULL, NULL, '2026-08-20 10:43:23'),
(95, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, ERIKA OPPONG (Visa: R1272556)', NULL, NULL, '2026-08-20 12:34:02'),
(96, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, JUANITA ANSAA BOATEMEAA (Visa: R1272555)', NULL, NULL, '2026-08-20 12:35:42'),
(97, 'AKPAN001', 'CREATE', 'Created new visa application for BOAKYE, ESTHER KORANG (Visa: R1272554)', NULL, NULL, '2026-08-20 12:36:56'),
(98, 'AKPAN001', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272554', '102.91.77.69', NULL, '2026-08-20 12:49:02'),
(99, 'AKPAN001', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272556', '102.91.77.69', NULL, '2026-08-20 12:50:40'),
(100, 'AKPAN001', 'CREATE', 'Created new visa application for MACHUHI, WILBERFORCE MWANGI (Visa: R1272557)', NULL, NULL, '2026-08-20 14:09:43'),
(101, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, CATHERINE WAIRIMU (Visa: R1272558)', NULL, NULL, '2026-08-20 14:35:04'),
(102, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272557', '102.91.77.69', NULL, '2026-08-20 14:35:42'),
(103, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272557', '102.91.77.69', NULL, '2026-08-20 14:35:45'),
(104, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, TREVOR KARANJA (Visa: R1272559)', NULL, NULL, '2026-08-20 14:39:26'),
(105, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, HADASA WANJIRU (Visa: R1272560)', NULL, NULL, '2026-08-20 14:43:38'),
(106, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, TRAVIS GITAU (Visa: R1272561)', NULL, NULL, '2026-08-20 14:46:14'),
(107, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, JOSEPH MACHUHI (Visa: R1272562)', NULL, NULL, '2026-08-20 14:47:41'),
(108, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, PETER NJIIRI (Visa: R1272563)', NULL, NULL, '2026-08-20 14:49:23'),
(109, 'AKPAN001', 'CREATE', 'Created new visa application for MWANGI, NDUNG\\\'U (Visa: R1272564)', NULL, NULL, '2026-08-20 14:50:59'),
(110, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272564', '102.91.77.69', NULL, '2026-08-20 14:51:22'),
(111, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, ZOHEIR (Visa: R1272565)', NULL, NULL, '2026-08-21 10:06:50'),
(112, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, NABILA (Visa: R1272566)', NULL, NULL, '2026-08-21 10:08:50'),
(113, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, LINA HANAA (Visa: R1272567)', NULL, NULL, '2026-08-21 10:11:01'),
(114, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, RAZANE (Visa: R1272568)', NULL, NULL, '2026-08-21 10:12:17'),
(115, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, NOHA (Visa: R1272569)', NULL, NULL, '2026-08-21 10:13:47'),
(116, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, SOFIA NOUR (Visa: R1272570)', NULL, NULL, '2026-08-21 10:15:09'),
(117, 'AKPAN001', 'CREATE', 'Created new visa application for BENSLIMANE, MOHAMED RAYAN (Visa: R1272571)', NULL, NULL, '2026-08-21 10:16:22'),
(118, '37084', 'UPDATE', 'Updated user profile details', '102.91.104.101', NULL, '2026-08-21 10:29:13'),
(119, '37084', 'CREATE', 'Created new visa application for TAGATA, MEGUMI (Visa: R1272572)', NULL, NULL, '2026-08-21 10:36:57'),
(120, '37084', 'CREATE', 'Created new visa application for IBA, ASAMI (Visa: R1272573)', NULL, NULL, '2026-08-21 10:48:49'),
(121, '37084', 'CREATE', 'Created new visa application for PUNTSO, PAIGE THORNER (Visa: R1272574)', NULL, NULL, '2026-08-21 10:58:42'),
(122, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272573', '102.91.104.101', NULL, '2026-08-21 10:59:28'),
(123, '37084', 'CREATE', 'Created new visa application for PUNTSO, PETER HEINRICH (Visa: R1272575)', NULL, NULL, '2026-08-21 11:11:30'),
(124, '37084', 'CREATE', 'Created new visa application for TOLLIVER, DERRICK LAMOND (Visa: R1272576)', NULL, NULL, '2026-08-21 11:18:34'),
(125, '37084', 'CREATE', 'Created new visa application for TOLLIVER, TSYROS ELIJAH NATHAN (Visa: R1272577)', NULL, NULL, '2026-08-21 11:23:12'),
(126, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272572', '102.91.104.101', NULL, '2026-08-21 11:25:30'),
(127, '37084', 'CREATE', 'Created new visa application for TOLLIVER, RAMONA GUILLEMETTE (Visa: R1272578)', NULL, NULL, '2026-08-21 11:32:22'),
(128, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272577', '102.91.104.101', NULL, '2026-08-21 11:35:26'),
(129, '37084', 'CREATE', 'Created new visa application for TOLLIVER, LUKA AARON (Visa: R1272579)', NULL, NULL, '2026-08-21 11:41:47'),
(130, '37084', 'CREATE', 'Created new visa application for LE, VIET HOANG (Visa: R1272580)', NULL, NULL, '2026-08-21 11:49:57'),
(131, '37084', 'CREATE', 'Created new visa application for NGUYEN, THU HANG (Visa: R1272581)', NULL, NULL, '2026-08-21 11:53:31'),
(132, '37084', 'CREATE', 'Created new visa application for MAMOUN, KHALIFA MOHAMEDALI OBIED (Visa: R1272582)', NULL, NULL, '2026-08-21 12:05:19'),
(133, '37084', 'CREATE', 'Created new visa application for SARA, SHAMSELDEEN YOUSIF AMEENELDEEN (Visa: R1272583)', NULL, NULL, '2026-08-21 12:09:30'),
(134, '37084', 'CREATE', 'Created new visa application for TEBIAN, MAMOUN KHALIFA MOHAMEDALI (Visa: R1272584)', NULL, NULL, '2026-08-21 12:18:18'),
(135, '37084', 'CREATE', 'Created new visa application for AHMED, MAMOUN KHALIFA MOHAMEDALI (Visa: R1272585)', NULL, NULL, '2026-08-21 12:22:02'),
(136, '37084', 'CREATE', 'Created new visa application for AYMAN, MAMOUN KHALIFA MOHAMEDALI (Visa: R1272586)', NULL, NULL, '2026-08-21 12:25:23'),
(137, 'AKPAN001', 'CREATE', 'Created new visa application for LEBRETON, CAROLE GLADYS FLEUR (Visa: R1272590)', NULL, NULL, '2026-08-21 12:30:59'),
(138, 'AKPAN001', 'CREATE', 'Created new visa application for MULINGANYA, NTANGAMYAMPI (Visa: R1272591)', NULL, NULL, '2026-08-21 12:41:45'),
(139, 'AKPAN001', 'CREATE', 'Created new visa application for GOMEZ, SORIA JAVIER (Visa: R1272592)', NULL, NULL, '2026-08-21 12:51:05'),
(140, 'AKPAN001', 'CREATE', 'Created new visa application for GRECO, TANIA SABINA (Visa: R1272593)', NULL, NULL, '2026-08-21 12:56:14'),
(141, '37084', 'CREATE', 'Created new visa application for MARSTRANDER, HELGE KLOUMAN (Visa: R1272587)', NULL, NULL, '2026-08-21 13:00:46'),
(142, '37084', 'CREATE', 'Created new visa application for MARSTRANDER, TORUNN (Visa: R1272588)', NULL, NULL, '2026-08-21 13:07:41'),
(143, 'AKPAN001', 'CREATE', 'Created new visa application for SADEK, ANDREJ (Visa: R1272594)', NULL, NULL, '2026-08-21 13:11:49'),
(144, 'AKPAN001', 'CREATE', 'Created new visa application for GRACA DA SILVA, RUTE LILANA (Visa: R1272595)', NULL, NULL, '2026-08-21 13:15:38'),
(145, '37084', 'CREATE', 'Created new visa application for KU, KWIHYANG (Visa: R1272589)', NULL, NULL, '2026-08-21 13:17:56'),
(146, 'AKPAN001', 'CREATE', 'Created new visa application for EREMICHEV, KIRILL (Visa: R1272596)', NULL, NULL, '2026-08-21 13:21:36'),
(147, 'AKPAN001', 'CREATE', 'Created new visa application for MANCINI, MARY BETH (Visa: R1272597)', NULL, NULL, '2026-08-21 13:24:42'),
(148, '37084', 'CREATE', 'Created new visa application for JONATHAN, LIM KAI EN (Visa: R1272601)', NULL, NULL, '2026-08-21 13:26:40'),
(149, 'AKPAN001', 'CREATE', 'Created new visa application for MANCINI, MICHAEL JOHN (Visa: R1272598)', NULL, NULL, '2026-08-21 13:27:45'),
(150, 'AKPAN001', 'CREATE', 'Created new visa application for CHAPMAN, CAROLINE JANE (Visa: R1272599)', NULL, NULL, '2026-08-21 13:32:34'),
(151, '37084', 'CREATE', 'Created new visa application for HACHEM, RANA CHAWKI (Visa: R1272602)', NULL, NULL, '2026-08-21 13:34:27'),
(152, 'AKPAN001', 'CREATE', 'Created new visa application for CHAPMAN, FREYA LILY (Visa: R1272600)', NULL, NULL, '2026-08-21 13:34:52'),
(153, '37084', 'CREATE', 'Created new visa application for SCHATTOVICH, STEFAN (Visa: R1272603)', NULL, NULL, '2026-08-21 13:43:50'),
(154, '37084', 'CREATE', 'Created new visa application for SALES, SCHATTOVICH (Visa: R1272604)', NULL, NULL, '2026-08-21 13:48:17'),
(155, 'AKPAN001', 'CREATE', 'Created new visa application for CHOI, HYUNG KWON (Visa: R1272614)', NULL, NULL, '2026-08-21 13:51:27'),
(156, 'AKPAN001', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272614', '102.91.104.101', NULL, '2026-08-21 13:55:06'),
(157, 'AKPAN001', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272614', '102.91.104.101', NULL, '2026-08-21 13:55:23'),
(158, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272614', '102.91.104.101', NULL, '2026-08-21 13:56:33'),
(159, 'AKPAN001', 'CREATE', 'Created new visa application for CHOI, HYUNG KWON (Visa: R1272614)', NULL, NULL, '2026-08-21 13:59:35'),
(160, 'AKPAN001', 'CREATE', 'Created new visa application for HAN, EUNJI (Visa: R1272615)', NULL, NULL, '2026-08-21 14:01:10'),
(161, 'AKPAN001', 'CREATE', 'Created new visa application for CHOI, SI U (Visa: R1272616)', NULL, NULL, '2026-08-21 14:03:07'),
(162, 'AKPAN001', 'CREATE', 'Created new visa application for CHOI, JI WOO (Visa: R1272617)', NULL, NULL, '2026-08-21 14:04:35'),
(163, '37084', 'CREATE', 'Created new visa application for MACKEN, HANNAH (Visa: R1272605)', NULL, NULL, '2026-08-21 14:05:41'),
(164, '37084', 'CREATE', 'Created new visa application for PELL, JOHANNES (Visa: R1272606)', NULL, NULL, '2026-08-21 14:10:47'),
(165, 'AKPAN001', 'CREATE', 'Created new visa application for WILSON, LAUREN ALEXANDRA (Visa: R1272618)', NULL, NULL, '2026-08-21 14:12:32'),
(166, 'AKPAN001', 'CREATE', 'Created new visa application for SHAW, SIMON CHRISTOPHER (Visa: R1272619)', NULL, NULL, '2026-08-21 14:15:55'),
(167, '37084', 'CREATE', 'Created new visa application for MASHAYO, EMMANUELA FRANK BETHUEL (Visa: R1272607)', NULL, NULL, '2026-08-21 14:18:39'),
(168, 'AKPAN001', 'CREATE', 'Created new visa application for WITTWER, STEFAN RALPH (Visa: R1272620)', NULL, NULL, '2026-08-21 14:19:47'),
(169, 'AKPAN001', 'CREATE', 'Created new visa application for YEMANE, MESELE MINDACHEW (Visa: R1272621)', NULL, NULL, '2026-08-21 14:23:17'),
(170, '37084', 'CREATE', 'Created new visa application for OFORIWAA, AMMA (Visa: R1272608)', NULL, NULL, '2026-08-21 14:24:54'),
(171, '37084', 'CREATE', 'Created new visa application for ASANTE, MAAME AMMA AKOMA (Visa: R1272609)', NULL, NULL, '2026-08-21 14:28:41'),
(172, 'AKPAN001', 'CREATE', 'Created new visa application for MOHAMMED, AZIZI BIN NGADIRAN (Visa: R1272622)', NULL, NULL, '2026-08-21 14:30:24'),
(173, 'AKPAN001', 'CREATE', 'Created new visa application for AADELA, MELATI BINTI AHMAD TERMIZI (Visa: R1272623)', NULL, NULL, '2026-08-21 14:32:26'),
(174, '37084', 'CREATE', 'Created new visa application for ASANTE, MAXWELL OWUSU (Visa: R1272610)', NULL, NULL, '2026-08-21 14:32:55'),
(175, 'AKPAN001', 'CREATE', 'Created new visa application for MUHAMMAD, QAISER SULAIMAN (Visa: R1272624)', NULL, NULL, '2026-08-21 14:33:41'),
(176, 'AKPAN001', 'CREATE', 'Created new visa application for AAISYA, ROSE BINTI MOHAMMAD AZIZI (Visa: R1272625)', NULL, NULL, '2026-08-21 14:35:15'),
(177, 'AKPAN001', 'CREATE', 'Created new visa application for AALIYA, ROSE BINTI MOHAMMAD AZIZI (Visa: R1272626)', NULL, NULL, '2026-08-21 14:36:42'),
(178, '37084', 'CREATE', 'Created new visa application for AMELE, ASRAT ASFAW (Visa: R1272550)', NULL, NULL, '2026-08-21 14:39:53'),
(179, '37084', 'CREATE', 'Created new visa application for THOMAS, CLARE SALLY (Visa: R1272611)', NULL, NULL, '2026-08-21 14:48:02'),
(180, '37084', 'CREATE', 'Created new visa application for MARSHALL, CHRISTOPHER JOHN DE CARLE (Visa: R1272612)', NULL, NULL, '2026-08-21 14:51:25'),
(181, '37084', 'CREATE', 'Created new visa application for MARSHALL, THOMAS IDA JEANNE (Visa: R1272613)', NULL, NULL, '2026-08-21 14:54:16'),
(182, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272591', '102.91.104.101', NULL, '2026-08-21 14:59:00'),
(183, '37084', 'CREATE', 'Created new visa application for ISHIGAME, HANAKO (Visa: R1272627)', NULL, NULL, '2026-08-21 15:02:43'),
(184, '37084', 'CREATE', 'Created new visa application for ISHIGAME, KOHSUKE (Visa: R1272628)', NULL, NULL, '2026-08-21 15:05:18'),
(185, '37084', 'CREATE', 'Created new visa application for ISHIGAME, TAKAYUKI (Visa: R1272629)', NULL, NULL, '2026-08-21 15:07:13'),
(186, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272604', '102.91.104.101', NULL, '2026-08-21 15:21:26'),
(187, '37084', 'CREATE', 'Created new visa application for SALES, SCHATTOVICH CAMILLE (Visa: R1272604)', NULL, NULL, '2026-08-21 15:25:06'),
(188, '35562', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272604', '102.91.105.20', NULL, '2026-08-22 17:47:40'),
(189, '39031', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272626', '105.117.12.248', NULL, '2026-08-23 21:57:28'),
(190, '35562', 'CREATE', 'Created new visa application for WRWRW, WRWR (Visa: WRWRW)', NULL, NULL, '2026-08-24 12:17:53'),
(191, '27015', 'UPDATE', 'Updated user profile details', '102.91.104.14', NULL, '2026-08-24 12:24:48'),
(192, '27015', 'RESET_PASSWORD', 'Reset password to default \'password@123\' for user: 27091', '102.91.104.14', NULL, '2026-08-24 12:30:28'),
(193, '27091', 'UPDATE', 'Updated user profile details', '102.91.104.14', NULL, '2026-08-24 12:40:08'),
(194, '27091', 'VIEW/PRINT', 'Viewed/Printed visa application details for Visa No: R1272604', '102.91.104.14', NULL, '2026-08-24 12:47:23'),
(195, '37084', 'CREATE', 'Created new visa application for NDINGA, RAY IONEL (Visa: R1272630)', NULL, NULL, '2026-08-24 13:12:57'),
(196, '37084', 'CREATE', 'Created new visa application for ALLOSSE EP NDINGA, JACINDA ORIANNE (Visa: R1272631)', NULL, NULL, '2026-08-24 13:17:52'),
(197, '37084', 'CREATE', 'Created new visa application for RAYANNE NDINGA, GABRIELLE EVA (Visa: R1272632)', NULL, NULL, '2026-08-24 13:21:08'),
(198, '37084', 'CREATE', 'Created new visa application for CHIQUEIO, JOAQUIM JOAO (Visa: R1272633)', NULL, NULL, '2026-08-24 13:30:56'),
(199, '37084', 'CREATE', 'Created new visa application for NKUBITO KAREKEZI, PATRICK (Visa: R1272634)', NULL, NULL, '2026-08-24 13:38:37'),
(200, '37084', 'CREATE', 'Created new visa application for POPLAWSKI RIBEIRO, MARCOS (Visa: R1272635)', NULL, NULL, '2026-08-24 13:43:43'),
(201, '37084', 'CREATE', 'Created new visa application for SICHULA, MARK (Visa: R1272639)', NULL, NULL, '2026-08-24 15:17:16'),
(202, 'AKPAN001', 'CREATE', 'Created new visa application for CEESAY, ANSUMANA (Visa: R1272640)', NULL, NULL, '2026-08-24 15:22:33'),
(203, 'AKPAN001', 'CREATE', 'Created new visa application for JAITEH, AMIE (Visa: R1272641)', NULL, NULL, '2026-08-24 15:23:42'),
(204, '37084', 'CREATE', 'Created new visa application for GOFI DOMTA, ALAIN (Visa: R1272636)', NULL, NULL, '2026-08-24 15:24:06'),
(205, 'AKPAN001', 'CREATE', 'Created new visa application for CEESAY, FATIMA (Visa: R1272642)', NULL, NULL, '2026-08-24 15:24:39'),
(206, 'AKPAN001', 'CREATE', 'Created new visa application for CEESAY, AISHA (Visa: R1272643)', NULL, NULL, '2026-08-24 15:25:35'),
(207, 'AKPAN001', 'CREATE', 'Created new visa application for CEESAY, KHADIJATOU (Visa: R1272644)', NULL, NULL, '2026-08-24 15:26:43'),
(208, '37084', 'CREATE', 'Created new visa application for SINEH EPSE GOFI, EDEL QUEEN (Visa: R1272637)', NULL, NULL, '2026-08-24 15:27:09'),
(209, 'AKPAN001', 'CREATE', 'Created new visa application for CEESAY, SARAHTA (Visa: R1272645)', NULL, NULL, '2026-08-24 15:27:45'),
(210, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272645', '102.91.104.14', NULL, '2026-08-24 15:29:20'),
(211, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272644', '102.91.104.14', NULL, '2026-08-24 15:29:44'),
(212, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272644', '102.91.104.14', NULL, '2026-08-24 15:30:49'),
(213, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272643', '102.91.104.14', NULL, '2026-08-24 15:30:56'),
(214, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272643', '102.91.104.14', NULL, '2026-08-24 15:30:59'),
(215, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272642', '102.91.104.14', NULL, '2026-08-24 15:31:09'),
(216, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272641', '102.91.104.14', NULL, '2026-08-24 15:31:23'),
(217, 'AKPAN001', 'EDIT', 'Edited visa application record for Visa No: R1272640', '102.91.104.14', NULL, '2026-08-24 15:31:34'),
(218, '37084', 'CREATE', 'Created new visa application for BELLO, ABDOUL HAKIM (Visa: R1272638)', NULL, NULL, '2026-08-24 15:35:39'),
(219, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272638', '102.91.104.14', NULL, '2026-08-24 15:36:30'),
(220, '37084', 'EDIT', 'Edited visa application record for Visa No: R1272638', '102.91.104.14', NULL, '2026-08-24 15:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `old_visa_applications`
--

CREATE TABLE `old_visa_applications` (
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

CREATE TABLE `tbl_audit` (
  `id` int NOT NULL,
  `serviceNo` int NOT NULL,
  `Activity` varchar(500) NOT NULL,
  `entryDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `role_id` int NOT NULL,
  `role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` varchar(7) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `user_role` varchar(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `photo` varchar(50) NOT NULL
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

CREATE TABLE `users` (
  `id` int NOT NULL,
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
  `google_auth_secret` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL
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
(9, '36921', 'Akpan', 'N S', 'akpan@gmail.com', '$2y$10$RoNUILia8l3IaKDxRV4mdOr.ze6doM.vv/cWiRo3fMmqo2llLQRkW', 'DataEntry', '', '2025-12-22 14:06:59', '2026-08-20 09:56:13', 'active', 'SV6YYPFBIWLUHXEJ'),
(10, '35375', 'Sani ', 'Y', 'sani@gmail.com', '$2y$10$xHZcdlWA9K2NTKRcOttir.CGwxd9Cyuv.EYjqhGoUxXiID2FCBLt2', 'DataEntry', '', '2025-12-22 14:07:54', '2026-08-12 13:45:07', 'active', 'MZHS7WPWNSKWHTJ3'),
(11, '37084', 'NKIRUKA', 'CHRISTIAN', 'cnkiruka15@gmail.com', '$2y$10$IKsBFtteiQJTU4Nql0cecO/i7AbuNlfxE/RnVEf47IroBYTIDAgWa', 'DataEntry', '', '2026-08-12 13:44:36', '2026-08-21 10:29:13', 'active', 'E4PSRQBOL3XH237C'),
(12, '                                            36022', 'MOHAMMED', 'BALA', 'basla@gmail.com', '$2y$10$0gnQ.5uFuvhvB.nME9arrOdamA6vH11rLTw8ZXP49pRaRfxxEiH3i', 'DataEntry', '', '2026-08-12 14:06:19', '2026-08-12 14:06:27', 'active', NULL),
(13, '34079', 'IK', 'ANYANWU', 'ikuru2000@gmail.com', '$2y$10$EDvi9FJFaIT6PU326tL1IO4FZ.1I077gyZpDKZWRZIYLc6FEofaZS', 'DataEntry', '', '2026-08-12 14:20:17', '2026-08-12 14:21:19', 'active', 'LPWMT467XPLPRJBH'),
(14, '27015', 'DAUDA', 'LAWAL', 'lawaldabbas@gmail.com', '$2y$10$o2df3idYx3yU0.hqjf1XgOxy0RtLPHJ2YnCjxii/GUf33P.yZodru', 'Supervisor', '', '2026-08-12 14:44:00', '2026-08-24 12:24:48', 'active', '6UXMZHYKSSM2EWFB'),
(16, '45866', 'MA', 'SULE', 'muhammedsule596@gmail.com', '$2y$10$j/lRw/WezCU/oVEw10/mG.KBtEirAINSkLg9B6rZWqU4RmqPmiGz2', 'DataEntry', '', '2026-08-13 13:17:18', '2026-08-13 13:19:00', 'active', 'ZHU3S6JEUSF7DOJ4'),
(17, '33477', 'HN', 'JIDDAH', 'jiddah@gmail.com', '$2y$10$ZRAobibVPdhZkPufrZWu0OJStQeX6r2w7.vMaye3P6QLUY4G1hneu', 'DataEntry', '', '2026-08-13 13:35:33', '2026-08-13 13:37:16', 'active', 'YXUCYH6JXLDXOA36'),
(18, '40315', 'AA', 'SALIHI', 'salih@gmail.com', '$2y$10$UbYpvc02AqwuyO./TQdJn.0POPswXamGgqaNh6.eIZHcILKtJcBR.', 'DataEntry', '', '2026-08-13 13:48:50', '2026-08-13 13:50:09', 'active', '2WFIZBDVZBLOTSED'),
(19, '19650', 'RI', 'ADEMOLA', 'ademola@gmail.com', '$2y$10$CaLOqe9SjKhHPQoeEAvYte/nNYX42sj85hn/x6wW8l/lkM7pFzj3W', 'DataEntry', '', '2026-08-13 14:00:27', '2026-08-13 14:09:13', 'active', '5QF7P4SWYQLZE7CW'),
(20, '25079', 'SALIHU', 'DAUDA', 'SALIHU@GMAIL.COM', '$2y$10$.xQgVEnFjsRvlhofwCyEO.OrCAq6JZZur1W3F1BtKNOd6B7h9OhCa', 'DataEntry', '', '2026-08-17 14:29:48', '2026-08-17 14:41:30', 'active', 'CSA3RI5B3SYLIOME'),
(21, '333456', 'ABBAS', 'LAWAL', 'abbas@gmail.com', '$2y$10$VJU/p3w2E5dQG3Hz6e0/a.EfhDIGbo94tFpCcIii.E47hLqcM8vBC', '', '', '2026-08-17 14:45:37', '2026-08-17 14:46:25', 'active', 'MAFSEOFYDGVY7TNH'),
(22, 'AKPAN001', 'UWEM', 'AKPAN', 'nkereuwemlink@gmail.com', '$2y$10$t.sJ6aertBhUK/6RFe4.Yue9GQJKmL4XmOeVHwbIiI6Wx9/GJYxjC', 'DataEntry', '', '2026-08-20 09:59:52', '2026-08-20 10:09:38', 'active', 'ZB7P5XHLHCMEC6VH'),
(23, '27091', 'ESTHER', 'OKORIE', 'estryq@gmail.com', '$2y$10$SZQzSm9C6jRfSLsB.SS.s.txcI2M1/3jri0Ke/O4GSS.r7MNuTcyS', 'Supervisor', '', '2026-08-24 12:30:07', '2026-08-24 12:40:08', 'active', 'NECPT3GTJOEQJACS');

-- --------------------------------------------------------

--
-- Table structure for table `visa_applications`
--

CREATE TABLE `visa_applications` (
  `id` int NOT NULL,
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
  `application_type` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'fresh',
  `number_of_dependencies` int NOT NULL DEFAULT '0',
  `visa_type` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `mfa_letter` varchar(3) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `covering_letter` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `entry_stamp` varchar(3) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `entry_stamp_date` date DEFAULT NULL,
  `contract_duration` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `old_visa_number` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `passport_date_of_issue` date DEFAULT NULL,
  `passport_date_of_expiry` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `visa_applications`
--

INSERT INTO `visa_applications` (`id`, `visa_number`, `passport_number`, `surname`, `given_name`, `nationality`, `place_of_issue`, `date_of_issue`, `embassy`, `authority`, `within`, `journey`, `fees`, `application_date`, `status`, `created_by`, `created_at`, `updated_at`, `category`, `application_type`, `number_of_dependencies`, `visa_type`, `gender`, `mfa_letter`, `covering_letter`, `entry_stamp`, `entry_stamp_date`, `contract_duration`, `old_visa_number`, `passport_date_of_issue`, `passport_date_of_expiry`) VALUES
(56, 'R1272549', 'B00771278', 'SSENTEZA', 'SIMON PETER', 'UGANDA', 'ABUJA HQTRS', '2026-08-20', 'UGANDA HC', 'ABJ/HQ/DIP/HC/27', '2027-08-09', 'MULTIPLE', 'GRATIS', '2026-08-20 10:06:21', 'active', 'AKPAN001', NULL, '2026-08-20 10:06:21', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-20', '2 YEARS', NULL, '2025-12-30', '2035-12-29'),
(57, 'R1272548', 'D00002857', 'OGUME', 'LAKI CHRISTOPHER', 'UGANDA', 'ABUJA HQTRS', '2026-08-20', 'UGANDA HC', 'ABJ/HQ/DIP/HC/29', '2027-07-17', 'MULTIPLE', 'GRATIS', '2026-08-20 10:18:58', 'active', 'AKPAN001', NULL, '2026-08-20 10:18:58', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-20', '2 YEARS', NULL, '2024-04-18', '2034-04-17'),
(58, 'R1272551', '15LP00267', 'BOAKYE', 'SAMUEL OPPONG', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 10:29:16', 'active', 'AKPAN001', NULL, '2026-08-20 10:29:16', 'INT/ORG', 'fresh', 5, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-11-13', '2029-11-07'),
(59, 'R1272552', '15LP00268', 'BOAKYE', 'SUSSANA  NYARKOAH', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061/', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 10:38:03', 'active', 'AKPAN001', NULL, '2026-08-20 10:38:03', 'INT/ORG', 'fresh', 5, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-11-13', '2029-11-12'),
(60, 'R1272553', '15LP01420', 'BOAKYE', 'CHRISTABEL NYARKOAH', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061//', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 10:43:23', 'active', 'AKPAN001', NULL, '2026-08-20 10:43:23', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-11-13', '2029-11-12'),
(61, 'R1272556', '15LP01418', 'BOAKYE', 'ERIKA OPPONG', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 12:34:02', 'active', 'AKPAN001', NULL, '2026-08-20 12:34:02', 'NONE', 'renewal', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', 'R1272556', '2024-11-13', '2029-11-12'),
(62, 'R1272555', '15LP01419', 'BOAKYE', 'JUANITA ANSAA BOATEMEAA', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 12:35:42', 'active', 'AKPAN001', NULL, '2026-08-20 12:35:42', 'INT/ORG', 'renewal', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', 'R1272555', '2024-11-13', '2029-11-12'),
(63, 'R1272554', '15LP01421', 'BOAKYE', 'ESTHER KORANG', 'GHANA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/286061', '2028-09-24', 'MULTIPLE', 'GRATIS', '2026-08-20 12:36:56', 'active', 'AKPAN001', NULL, '2026-08-20 12:36:56', 'INT/ORG', 'renewal', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', 'R1272554', '2024-11-13', '2029-11-12'),
(64, 'R1272557', '15LP00280', 'MACHUHI', 'WILBERFORCE MWANGI', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:09:43', 'active', 'AKPAN001', NULL, '2026-08-20 14:35:45', 'INT/ORG', 'fresh', 6, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', '', '2026-08-20', '2 YEARS', '', '2024-03-11', '2029-03-11'),
(65, 'R1272558', '15LP00221', 'MWANGI', 'CATHERINE WAIRIMU', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740/', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:35:04', 'active', 'AKPAN001', NULL, '2026-08-20 14:35:04', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-02-26', '2029-02-25'),
(66, 'R1272559', '15LP01500', 'MWANGI', 'TREVOR KARANJA', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740//', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:39:26', 'active', 'AKPAN001', NULL, '2026-08-20 14:39:26', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-02-26', '2029-02-25'),
(67, 'R1272560', '15LP01056', 'MWANGI', 'HADASA WANJIRU', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:43:38', 'active', 'AKPAN001', NULL, '2026-08-20 14:43:38', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-02-26', '2029-02-25'),
(68, 'R1272561', '15LP01058', 'MWANGI', 'TRAVIS GITAU', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:46:14', 'active', 'AKPAN001', NULL, '2026-08-20 14:46:14', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-04-16', '2029-04-13'),
(69, 'R1272562', '15LP01057', 'MWANGI', 'JOSEPH MACHUHI', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:47:41', 'active', 'AKPAN001', NULL, '2026-08-20 14:47:41', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-02-26', '2029-02-25'),
(70, 'R1272563', '15LP01059', 'MWANGI', 'PETER NJIIRI', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:49:23', 'active', 'AKPAN001', NULL, '2026-08-20 14:49:23', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-20', '2 YEARS', NULL, '2024-04-16', '2029-04-13'),
(71, 'R1272564', '15LP01497', 'MWANGI', 'NDUNG\'U', 'KENYA', 'ABUJA HQTRS', '2026-08-20', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2740', '2028-09-08', 'MULTIPLE', 'GRATIS', '2026-08-20 14:50:59', 'active', 'AKPAN001', NULL, '2026-08-20 14:51:22', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', '', '2026-08-20', '2 YEARS', '', '2024-04-16', '2029-04-13'),
(72, 'R1272565', '300516780', 'BENSLIMANE', 'ZOHEIR', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:06:50', 'active', 'AKPAN001', NULL, '2026-08-21 10:06:50', 'DIPLOMAT', 'fresh', 6, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(73, 'R1272566', '300516776', 'BENSLIMANE', 'NABILA', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:08:50', 'active', 'AKPAN001', NULL, '2026-08-21 10:08:50', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(74, 'R1272567', '300516796', 'BENSLIMANE', 'LINA HANAA', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:11:01', 'active', 'AKPAN001', NULL, '2026-08-21 10:11:01', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(75, 'R1272568', '300516777', 'BENSLIMANE', 'RAZANE', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:12:17', 'active', 'AKPAN001', NULL, '2026-08-21 10:12:17', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(76, 'R1272569', '300516775', 'BENSLIMANE', 'NOHA', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:13:47', 'active', 'AKPAN001', NULL, '2026-08-21 10:13:47', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(77, 'R1272570', '300516797', 'BENSLIMANE', 'SOFIA NOUR', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:15:09', 'active', 'AKPAN001', NULL, '2026-08-21 10:15:09', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(78, 'R1272571', '300516778', 'BENSLIMANE', 'MOHAMED RAYAN', 'ALGERIA', 'ABUJA HQTRS', '2026-08-21', 'ALGERIA', 'ABJ/HQ/DIP/EM/84/169', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 10:16:22', 'active', 'AKPAN001', NULL, '2026-08-21 10:16:22', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2026-04-02', '2031-04-01'),
(79, 'R1272572', 'HA0006089', 'TAGATA', 'MEGUMI', 'JAPAN', 'ABUJA HQTRS', '2026-08-21', 'JAPAN', 'ABJ/HQ/DIP/EM/225', '2028-07-28', 'MULTIPLE', 'GRATIS', '2026-08-21 10:36:57', 'active', '37084', NULL, '2026-08-21 11:25:30', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', '', '2026-07-29', '2 YEARS', '', '2026-06-25', '2031-06-25'),
(80, 'R1272573', 'HA0005939', 'IBA', 'ASAMI', 'JAPAN', 'ABUJA HQTRS', '2026-08-21', 'JAPAN', 'ABJ/HQ/DIP/EM/226', '2028-07-28', 'MULTIPLE', 'GRATIS', '2026-08-21 10:48:49', 'active', '37084', NULL, '2026-08-21 10:59:28', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', '', '2026-07-29', '2 YEARS', '', '2026-06-17', '2031-06-17'),
(81, 'R1272574', 'X00098716', 'PUNTSO', 'PAIGE THORNER', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/903', '2029-01-09', 'MULTIPLE', 'GRATIS', '2026-08-21 10:58:42', 'active', '37084', NULL, '2026-08-21 10:58:42', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-21', '3 YEARS', NULL, '2024-01-10', '2029-01-09'),
(82, 'R1272575', 'X00098711', 'PUNTSO', 'PETER HEINRICH', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/903', '2029-01-09', 'MULTIPLE', 'GRATIS', '2026-08-21 11:11:30', 'active', '37084', NULL, '2026-08-21 11:11:30', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-10', '3 YEARS', NULL, '2024-01-10', '2029-01-09'),
(83, 'R1272576', 'X00180399', 'TOLLIVER', 'DERRICK LAMOND', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/900', '2029-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 11:18:34', 'active', '37084', NULL, '2026-08-21 11:18:34', 'DIPLOMAT', 'fresh', 3, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '3 YEARS', NULL, '2026-07-28', '2031-07-27'),
(84, 'R1272577', 'X00076334', 'TOLLIVER', 'TSYROS ELIJAH NATHAN', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/900', '2028-05-09', 'MULTIPLE', 'GRATIS', '2026-08-21 11:23:12', 'active', '37084', NULL, '2026-08-21 11:35:26', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', '', '2026-08-01', '3 YEARS', '', '2023-05-10', '2028-05-09'),
(85, 'R1272578', 'X00105198', 'TOLLIVER', 'RAMONA GUILLEMETTE', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/900', '2029-03-13', 'MULTIPLE', 'GRATIS', '2026-08-21 11:32:22', 'active', '37084', NULL, '2026-08-21 11:32:22', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '3 YEARS', NULL, '2024-03-14', '2029-03-13'),
(86, 'R1272579', 'X00077651', 'TOLLIVER', 'LUKA AARON', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'USA', 'ABJ/HQ/DIP/EM/77/100/900', '2028-05-21', 'MULTIPLE', 'GRATIS', '2026-08-21 11:41:47', 'active', '37084', NULL, '2026-08-21 11:41:47', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '3 YEARS', NULL, '2023-05-22', '2028-05-21'),
(87, 'R1272580', 'H00000006', 'LE', 'VIET HOANG', 'VIETNAM', 'ABUJA HQTRS', '2026-08-21', 'VIETNAM', 'ABJ/HQ/DIP/EM/06/10', '2028-08-11', 'MULTIPLE', 'GRATIS', '2026-08-21 11:49:57', 'active', '37084', NULL, '2026-08-21 11:49:57', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-12', '2 YEARS', NULL, '2026-07-09', '2031-07-09'),
(88, 'R1272581', 'H00000008', 'NGUYEN', 'THU HANG', 'VIETNAM', 'ABUJA HQTRS', '2026-08-21', 'VIETNAM', 'ABJ/HQ/DIP/EM/06/10', '2028-08-11', 'MULTIPLE', 'GRATIS', '2026-08-21 11:53:31', 'active', '37084', NULL, '2026-08-21 11:53:31', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-21', '2 YEARS', NULL, '2026-07-09', '2031-07-09'),
(89, 'R1272582', 'P14962829', 'MAMOUN', 'KHALIFA MOHAMEDALI OBIED', 'SUDAN', 'ABUJA HQTRS', '2026-08-21', 'EMB SUDAN', 'ABJ/HQ/DIP/EM/62/32', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 12:05:19', 'active', '37084', NULL, '2026-08-21 12:05:19', 'DIPLOMAT', 'fresh', 4, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2022-08-02', '2 YEARS', NULL, '2026-02-10', '2036-02-09'),
(90, 'R1272583', 'P14946965', 'SARA', 'SHAMSELDEEN YOUSIF AMEENELDEEN', 'SUDAN', 'ABUJA HQTRS', '2026-08-21', 'EMB SUDAN', 'ABJ/HQ/DIP/EM/62/32', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 12:09:30', 'active', '37084', NULL, '2026-08-21 12:09:30', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2022-08-02', '2 YEARS', NULL, '2026-02-05', '2036-02-04'),
(91, 'R1272584', 'P14946984', 'TEBIAN', 'MAMOUN KHALIFA MOHAMEDALI', 'SUDAN', 'ABUJA HQTRS', '2026-08-21', 'EMB SUDAN', 'ABJ/HQ/DIP/EM/62/32', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 12:18:18', 'active', '37084', NULL, '2026-08-21 12:18:18', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '0022-08-02', '2 YEARS', NULL, '2026-02-05', '2031-02-04'),
(92, 'R1272585', 'P14946983', 'AHMED', 'MAMOUN KHALIFA MOHAMEDALI', 'SUDAN', 'ABUJA HQTRS', '2026-08-21', 'EMB SUDAN', 'ABJ/HQ/DIP/EM/62/32', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 12:22:02', 'active', '37084', NULL, '2026-08-21 12:22:02', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2022-08-02', '2 YEARS', NULL, '2026-02-05', '2031-02-04'),
(93, 'R1272586', 'P14946985', 'AYMAN', 'MAMOUN KHALIFA MOHAMEDALI', 'SUDAN', 'ABUJA HQTRS', '2026-08-21', 'EMB SUDAN', 'ABJ/HQ/DIP/EM/62/32', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 12:25:23', 'active', '37084', NULL, '2026-08-21 12:25:23', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2022-08-02', '2 YEARS', NULL, '2026-02-05', '2031-02-04'),
(94, 'R1272590', '24CD00260', 'LEBRETON', 'CAROLE GLADYS FLEUR', 'FRANCE', 'ABUJA HQTRS', '2026-08-21', 'EMB FRANCE', 'ABJ/HQ/DIP/EM/03/417', '2028-02-29', 'MULTIPLE', 'GRATIS', '2026-08-21 12:30:59', 'active', 'AKPAN001', NULL, '2026-08-21 12:30:59', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-27', '2 YEARS', NULL, '2024-07-29', '2028-02-29'),
(95, 'R1272591', 'OP1537188', 'MULINGANYA', 'NTANGAMYAMPI FABRICE', 'CONGO', 'ABUJA HQTRS', '2026-08-21', 'IITA', 'ABJ/HQ/DIP/INT/ORG/31/141', '2026-12-14', 'MULTIPLE', 'GRATIS', '2026-08-21 12:41:45', 'active', 'AKPAN001', NULL, '2026-08-21 14:59:00', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', '', '2026-07-03', '6 MONTHS', '', '2023-11-22', '2028-11-21'),
(96, 'R1272592', 'XGB023003', 'GOMEZ', 'SORIA JAVIER', 'SPAIN', 'ABUJA HQTRS', '2026-08-21', 'EMB SPAIN', 'ABJ/HQ/DIP/EM/68/68182', '2028-07-28', 'MULTIPLE', 'GRATIS', '2026-08-21 12:51:05', 'active', 'AKPAN001', NULL, '2026-08-21 12:51:05', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-29', '2 YEARS', NULL, '2026-06-16', '2029-06-16'),
(97, 'R1272593', 'CK1000875', 'GRECO', 'TANIA SABINA', 'FINLAND', 'ABUJA HQTRS', '2026-08-21', 'EM FINLAND', 'ABJ/HQ/DIP/EM/04/52', '2028-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 12:56:14', 'active', 'AKPAN001', NULL, '2026-08-21 12:56:14', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '2 YEARS', NULL, '2024-12-08', '2029-12-08'),
(98, 'R1272587', 'FGG002384', 'MARSTRANDER', 'HELGE KLOUMAN', 'NORWAY', 'ABUJA HQTRS', '2026-08-21', 'NORWEGIAN', 'ABJ/HQ/DIP/EM/230', '2028-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 13:00:46', 'active', '37084', NULL, '2026-08-21 13:00:46', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '2 YEARS', NULL, '2026-04-22', '2031-04-22'),
(99, 'R1272588', 'FGG002385', 'MARSTRANDER', 'TORUNN', 'NORWAY', 'ABUJA HQTRS', '2026-08-21', 'NORWEGIAN', 'ABJ/HQ/DIP/EM/230', '2028-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 13:07:41', 'active', '37084', NULL, '2026-08-21 13:07:41', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '0206-08-01', '2 YEARS', NULL, '2026-04-22', '2031-04-22'),
(100, 'R1272594', 'A0S29T73', 'SADEK', 'ANDREJ', 'SWITZERLAND', 'ABUJA HQTRS', '2026-08-21', 'SWITZERLAN', 'ABJ/HQ/DIP/EM/65', '2028-08-02', 'MULTIPLE', 'GRATIS', '2026-08-21 13:11:49', 'active', 'AKPAN001', NULL, '2026-08-21 13:11:49', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-03', '2 YEARS', NULL, '2026-05-06', '2036-05-05'),
(101, 'R1272595', 'C4J6M4WNW', 'GRACA DA SILVA', 'RUTE LILANA', 'GERMANY', 'ABUJA HQTRS', '2026-08-21', 'EM GERMANY', 'ABJ/HQ/DIP/EM/20/263', '2028-08-12', 'MULTIPLE', 'GRATIS', '2026-08-21 13:15:38', 'active', 'AKPAN001', NULL, '2026-08-21 13:15:38', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-13', '2 YEARS', NULL, '2026-05-27', '2036-05-26'),
(102, 'R1272589', 'M340T1242', 'KU', 'KWIHYANG', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'ICRC', 'ABJ/HQ/DIP/INTORG/12/1069', '2027-08-02', 'MULTIPLE', 'GRATIS', '2026-08-21 13:17:56', 'active', '37084', NULL, '2026-08-21 13:17:56', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-03', '1 YEAR', NULL, '2024-04-26', '2034-04-26'),
(103, 'R1272596', '10N0178182', 'EREMICHEV', 'KIRILL', 'RUSSIA', 'ABUJA HQTRS', '2026-08-21', 'EMB RUSSIA', 'ABJ/HQ/DIP/EM/399', '2028-07-24', 'MULTIPLE', 'GRATIS', '2026-08-21 13:21:36', 'active', 'AKPAN001', NULL, '2026-08-21 13:21:36', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-25', '2 YEARS', NULL, '2026-04-29', '2031-04-29'),
(104, 'R1272597', 'X00130486', 'MANCINI', 'MARY BETH', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'EMB USA', 'ABJ/HQ/DIP/EM/77/100/901', '2029-07-23', 'MULTIPLE', 'GRATIS', '2026-08-21 13:24:42', 'active', 'AKPAN001', NULL, '2026-08-21 13:24:42', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-24', '3 YEARS', NULL, '2024-12-05', '2029-12-04'),
(105, 'R1272601', 'K5141386N', 'JONATHAN', 'LIM KAI EN', 'SINGAPORE', 'ABUJA HQTRS', '2026-08-21', 'UNODC', 'ABJ/HQ/DIP/INTORG/69/55', '2027-01-22', 'MULTIPLE', 'GRATIS', '2026-08-21 13:26:40', 'active', '37084', NULL, '2026-08-21 13:26:40', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-07-22', '6 MONTHS', NULL, '2024-07-25', '2034-07-25'),
(106, 'R1272598', 'X00130487', 'MANCINI', 'MICHAEL JOHN', 'UNITED STATES', 'ABUJA HQTRS', '2026-08-21', 'EMB USA', 'ABJ/HQ/DIP/EM/77/100/902', '2029-07-23', 'MULTIPLE', 'GRATIS', '2026-08-21 13:27:45', 'active', 'AKPAN001', NULL, '2026-08-21 13:27:45', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-24', '3 YEARS', NULL, '2024-12-05', '2029-12-04'),
(107, 'R1272599', '562299892', 'CHAPMAN', 'CAROLINE JANE', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH HC', 'ABJ/HQ/DIP/HC/03/354', '2028-04-27', 'MULTIPLE', 'GRATIS', '2026-08-21 13:32:34', 'active', 'AKPAN001', NULL, '2026-08-21 13:32:34', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-02', '2 YEARS', NULL, '2019-11-06', '2029-11-06'),
(108, 'R1272602', 'LR4274882', 'HACHEM', 'RANA CHAWKI', 'LEBANON', 'ABUJA HQTRS', '2026-08-21', 'ICRC', 'ABJ/HQ/DIP/INTORG/12/1069', '2027-02-02', 'MULTIPLE', 'GRATIS', '2026-08-21 13:34:27', 'active', '37084', NULL, '2026-08-21 13:34:27', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-08-03', '6 MONTHS', NULL, '2026-01-19', '2036-01-18'),
(109, 'R1272600', '157698656', 'CHAPMAN', 'FREYA LILY', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH HC', 'ABJ/HQ/DIP/HC/03/354', '2028-04-27', 'MULTIPLE', 'GRATIS', '2026-08-21 13:34:52', 'active', 'AKPAN001', NULL, '2026-08-21 13:34:52', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-02', '2 YEARS', NULL, '2025-12-12', '2030-12-12'),
(110, 'R1272603', 'AD0002591', 'SCHATTOVICH', 'STEFAN', 'AUSTRIA', 'ABUJA HQTRS', '2026-08-21', 'EM AUSTRIA', 'ABJ/HQ/DIP/EM/34', '2028-07-30', 'MULTIPLE', 'GRATIS', '2026-08-21 13:43:50', 'active', '37084', NULL, '2026-08-21 13:43:50', 'DIPLOMAT', 'fresh', 1, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-31', '2 YEARS', NULL, '2026-04-22', '2029-04-21'),
(111, 'R125626254545', 'R1272604', 'SALES', 'SCHATTOVICH', 'AUSTRIA', 'ABUJA HQTRS', '2026-08-21', 'EM AUSTRIA', 'ABJ/HQ/DIP/EM/34', '2028-07-30', 'MULTIPLE', 'GRATIS', '2026-08-21 13:48:17', 'active', '37084', NULL, '2026-08-21 15:21:26', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', '', '2026-07-31', '2 YEARS', '', '2023-02-10', '2033-02-09'),
(112, 'R12141241', 'D560Y3226', 'CHOI', 'HYUNG KWON', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'EMB KOREA', 'ABJ/HQ/DIP/EM/41/143', '2027-01-06', 'MULTIPLE', 'GRATIS', '2026-08-21 13:51:27', 'active', 'AKPAN001', NULL, '2026-08-21 13:56:33', 'DIPLOMAT', 'fresh', 3, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', '', '2023-11-24', '1 YEAR', '', '2023-07-06', '2027-01-06'),
(113, 'R1272614', 'D818D3420', 'CHOI', 'HYUNG KWON', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'EMB KOREA', 'ABJ/HQ/DIP/EM/41/143', '2027-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 13:59:35', 'active', 'AKPAN001', NULL, '2026-08-21 13:59:35', 'DIPLOMAT', 'fresh', 3, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2023-11-24', '1 YEAR', NULL, '2026-07-22', '2027-07-31'),
(114, 'R1272615', 'D971F8574', 'HAN', 'EUNJI', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'EMB KOREA', 'ABJ/HQ/DIP/EM/41/143', '2027-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:01:10', 'active', 'AKPAN001', NULL, '2026-08-21 14:01:10', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2023-11-24', '1 YEAR', NULL, '2026-07-22', '2027-07-31'),
(115, 'R1272616', 'D922F5012', 'CHOI', 'SI U', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'EMB KOREA', 'ABJ/HQ/DIP/EM/41/143', '2027-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:03:07', 'active', 'AKPAN001', NULL, '2026-08-21 14:03:07', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2023-11-24', '1 YEAR', NULL, '2026-07-22', '2027-07-31'),
(116, 'R1272617', 'D029F4388', 'CHOI', 'JI WOO', 'KOREA, SOUTH', 'ABUJA HQTRS', '2026-08-21', 'EMB KOREA', 'ABJ/HQ/DIP/EM/41/143', '2027-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:04:35', 'active', 'AKPAN001', NULL, '2026-08-21 14:04:35', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2023-11-24', '1 YEAR', NULL, '2026-07-22', '2027-07-31'),
(117, 'R1272605', 'DT9372409', 'MACKEN', 'HANNAH', 'IRELAND', 'ABUJA HQTRS', '2026-08-21', 'EM IRELAND', 'ABJ/HQ/DIP/EM/45', '2028-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:05:41', 'active', '37084', NULL, '2026-08-21 14:05:41', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-01', '2 YEARS', NULL, '2026-01-30', '2031-01-29'),
(118, 'R1272606', 'AD0003203', 'PELL', 'JOHANNES', 'AUSTRIA', 'ABUJA HQTRS', '2026-08-21', 'EM AUSRIA', 'ABJ/HQ/DIP/EM/35', '2028-07-30', 'MULTIPLE', 'GRATIS', '2026-08-21 14:10:47', 'active', '37084', NULL, '2026-08-21 14:10:47', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-31', '2 YEARS', NULL, '2026-05-20', '2031-05-19'),
(119, 'R1272618', '310785244', 'WILSON', 'LAUREN ALEXANDRA', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH HC', 'ABJ/HQ/DIP/HC/03/380', '2028-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:12:32', 'active', 'AKPAN001', NULL, '2026-08-21 14:12:32', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-01', '2 YEARS', NULL, '2026-04-19', '2032-04-19'),
(120, 'R1272619', '147111818', 'SHAW', 'SIMON CHRISTOPHER', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH HC', 'ABJ/HQ/DIP/HC/03/378', '2028-07-24', 'MULTIPLE', 'GRATIS', '2026-08-21 14:15:55', 'active', 'AKPAN001', NULL, '2026-08-21 14:15:55', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-25', '2 YEARS', NULL, '2024-03-22', '2034-03-22'),
(121, 'R1272607', 'AUNM32580', 'MASHAYO', 'EMMANUELA FRANK BETHUEL', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'UNWFP', 'ABJ/HQ/DIP/INTORG/06/196', '2028-07-05', 'MULTIPLE', 'GRATIS', '2026-08-21 14:18:39', 'active', '37084', NULL, '2026-08-21 14:18:39', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2026-07-06', '2 YEARS', NULL, '2023-11-20', '2028-11-20'),
(122, 'R1272620', 'C4J6LK6GT', 'WITTWER', 'STEFAN RALPH', 'GERMANY', 'ABUJA HQTRS', '2026-08-21', 'EM GERMANY', 'ABJ/HQ/DIP/EM/20/265', '2028-07-27', 'MULTIPLE', 'GRATIS', '2026-08-21 14:19:47', 'active', 'AKPAN001', NULL, '2026-08-21 14:19:47', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-28', '2 YEARS', NULL, '2026-01-28', '2036-01-27'),
(123, 'R1272621', 'EP6962282', 'YEMANE', 'MESELE MINDACHEW', 'ETHIOPIA', 'ABUJA HQTRS', '2026-08-21', 'UNICEF', 'ABJ/HQ/DIP/INT/ORG/30/371', '2027-01-24', 'MULTIPLE', 'GRATIS', '2026-08-21 14:23:17', 'active', 'AKPAN001', NULL, '2026-08-21 14:23:17', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-01-25', '1 YEAR', NULL, '2022-03-18', '2027-03-17'),
(124, 'R1272608', 'C4J61LF92', 'OFORIWAA', 'AMMA', 'GERMANY', 'ABUJA HQTRS', '2026-08-21', 'EM GERMANY', 'ABJ/HQ/DIP/EM/20/264', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 14:24:54', 'active', '37084', NULL, '2026-08-21 14:24:54', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-03-17', '2 YEARS', NULL, '2020-09-28', '2030-09-27'),
(125, 'R1272609', 'C4J68N74H', 'ASANTE', 'MAAME AMMA AKOMA', 'GERMANY', 'ABUJA HQTRS', '2026-08-21', 'EM GERMANY', 'ABJ/HQ/DIP/EM/20/264', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 14:28:41', 'active', '37084', NULL, '2026-08-21 14:28:41', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-08-04', '2 YEARS', NULL, '2026-03-05', '2032-03-04'),
(126, 'R1272622', 'D00080124', 'MOHAMMED', 'AZIZI BIN NGADIRAN', 'MALAYSIA', 'ABUJA HQTRS', '2026-08-21', 'MALAYSIA', 'ABJ/HQ/DIP/HC/198', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 14:30:24', 'active', 'AKPAN001', NULL, '2026-08-21 14:30:24', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-27', '2 YEARS', NULL, '2026-06-25', '2031-06-25'),
(127, 'R1272623', 'D00080123', 'AADELA', 'MELATI BINTI AHMAD TERMIZI', 'MALAYSIA', 'ABUJA HQTRS', '2026-08-21', 'MALAYSIA', 'ABJ/HQ/DIP/HC/198', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 14:32:26', 'active', 'AKPAN001', NULL, '2026-08-21 14:32:26', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-27', '2 YEARS', NULL, '2026-06-25', '2031-06-25'),
(128, 'R1272610', 'C4J6PVYLN', 'ASANTE', 'MAXWELL OWUSU', 'GERMANY', 'ABUJA HQTRS', '2026-08-21', 'EM GERMANY', 'ABJ/HQ/DIP/EM/20/264', '2028-08-03', 'MULTIPLE', 'GRATIS', '2026-08-21 14:32:55', 'active', '37084', NULL, '2026-08-21 14:32:55', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-08-04', '2 YEARS', NULL, '2020-09-28', '2030-09-27'),
(129, 'R1272624', 'D00080125', 'MUHAMMAD', 'QAISER SULAIMAN', 'MALAYSIA', 'ABUJA HQTRS', '2026-08-21', 'MALAYSIA', 'ABJ/HQ/DIP/HC/198', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 14:33:41', 'active', 'AKPAN001', NULL, '2026-08-21 14:33:41', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-27', '2 YEARS', NULL, '2026-06-25', '2031-06-25'),
(130, 'R1272625', 'D00080133', 'AAISYA', 'ROSE BINTI MOHAMMAD AZIZI', 'MALAYSIA', 'ABUJA HQTRS', '2026-08-21', 'MALAYSIA', 'ABJ/HQ/DIP/HC/198', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 14:35:15', 'active', 'AKPAN001', NULL, '2026-08-21 14:35:15', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-27', '2 YEARS', NULL, '2026-06-25', '2031-06-25'),
(131, 'R1272626', 'D00080126', 'AALIYA', 'ROSE BINTI MOHAMMAD AZIZI', 'MALAYSIA', 'ABUJA HQTRS', '2026-08-21', 'MALAYSIA', 'ABJ/HQ/DIP/HC/198', '2028-07-26', 'MULTIPLE', 'GRATIS', '2026-08-21 14:36:42', 'active', 'AKPAN001', NULL, '2026-08-21 14:36:42', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-07-27', '2 YEARS', NULL, '2026-06-25', '2031-06-25'),
(132, 'R1272550', 'EQ1324623', 'AMELE', 'ASRAT ASFAW', 'ETHIOPIA', 'ABUJA HQTRS', '2026-08-21', 'IITA', 'ABJ/HQ/DIP/INTORG/31/62', '2027-07-31', 'MULTIPLE', 'GRATIS', '2026-08-21 14:39:53', 'active', '37084', NULL, '2026-08-21 14:39:53', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2021-08-22', '2 YEARS', NULL, '2025-11-25', '2030-11-24'),
(133, 'R1272611', '310771037', 'THOMAS', 'CLARE SALLY', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH', 'ABJ/HQ/DIP/HC/03/379', '2028-08-01', 'MULTIPLE', 'GRATIS', '2026-08-21 14:48:02', 'active', '37084', NULL, '2026-08-21 14:48:02', 'DIPLOMAT', 'fresh', 2, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-02', '2 YEARS', NULL, '2026-04-23', '2036-04-23'),
(134, 'R1272612', '310401917', 'MARSHALL', 'CHRISTOPHER JOHN DE CARLE', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH', 'ABJ/HQ/DIP/HC/03/379', '2028-08-01', 'MULTIPLE', 'GRATIS', '2026-08-21 14:51:25', 'active', '37084', NULL, '2026-08-21 14:51:25', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-02', '2 YEARS', NULL, '2026-04-23', '2036-04-23'),
(135, 'R1272613', '310181338', 'MARSHALL', 'THOMAS IDA JEANNE', 'UNITED KINGDOM', 'ABUJA HQTRS', '2026-08-21', 'BRITISH', 'ABJ/HQ/DIP/HC/03/379', '2028-08-01', 'MULTIPLE', 'GRATIS', '2026-08-21 14:54:16', 'active', '37084', NULL, '2026-08-21 14:54:16', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-02', '2 YEARS', NULL, '2026-04-23', '2031-04-23'),
(136, 'R1272627', 'HB0018990', 'ISHIGAME', 'HANAKO', 'JAPAN', 'ABUJA HQTRS', '2026-08-21', 'EMB JAPAN', 'ABJ/HQ/DIP/EM/218', '2027-09-13', 'MULTIPLE', 'GRATIS', '2026-08-21 15:02:43', 'active', '37084', NULL, '2026-08-21 15:02:43', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-30', '2 YEARS', NULL, '2026-05-27', '2031-05-27'),
(137, 'R1272628', 'HB0018992', 'ISHIGAME', 'KOHSUKE', 'JAPAN', 'ABUJA HQTRS', '2026-08-21', 'EMB JAPAN', 'ABJ/HQ/DIP/EM/218', '2027-09-13', 'MULTIPLE', 'GRATIS', '2026-08-21 15:05:18', 'active', '37084', NULL, '2026-08-21 15:05:18', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-30', '2 YEARS', NULL, '2026-05-27', '2031-05-27'),
(138, 'R1272629', 'HB0018991', 'ISHIGAME', 'TAKAYUKI', 'JAPAN', 'ABUJA HQTRS', '2026-08-21', 'EMB JAPAN', 'ABJ/HQ/DIP/EM/218', '2027-09-13', 'MULTIPLE', 'GRATIS', '2026-08-21 15:07:13', 'active', '37084', NULL, '2026-08-21 15:07:13', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-07-30', '2 YEARS', NULL, '2026-05-27', '2031-05-27'),
(139, 'R1272604', 'GG160143', 'SALES', 'SCHATTOVICH CAMILLE', 'AUSTRIA', 'ABUJA HQTRS', '2026-08-21', 'EM AUSTRIA', 'ABJ/HQ/DIP/EM/34', '2028-07-30', 'MULTIPLE', 'GRATIS', '2026-08-21 15:25:06', 'active', '37084', NULL, '2026-08-21 15:25:06', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'EMBASSY', NULL, '2026-07-31', '2 YEARS', NULL, '2023-02-10', '2033-02-09'),
(140, 'WRWRW', 'WRRWRWR', 'WRWRW', 'WRWR', 'AUSTRIA', 'ABUJA HQTRS', '2026-08-24', 'WRWRW', 'ABJ/HQ/DIP/EM/3RRRRR', '2028-07-30', 'SINGLE', 'GRATIS', '2026-08-24 12:17:53', 'active', '35562', NULL, '2026-08-24 12:17:53', 'DIPLOMAT', 'fresh', 0, 'RWWRWW', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2026-08-28', 'WWRRWR', NULL, '2026-08-13', '2026-08-30'),
(141, 'R1272630', 'OA0777272', 'NDINGA', 'RAY IONEL', 'CONGO', 'ABUJA HQTRS', '2026-08-24', 'IFC', 'ABJ/HQ/DIP/INT/ORG/83/26', '2028-04-05', 'MULTIPLE', 'GRATIS', '2026-08-24 13:12:57', 'active', '37084', NULL, '2026-08-24 13:12:57', 'INT/ORG', 'fresh', 2, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2024-09-06', '2 YEARS', NULL, '2025-09-02', '2030-09-01'),
(142, 'R1272631', '24PP38989', 'ALLOSSE EP NDINGA', 'JACINDA ORIANNE', 'GABON', 'ABUJA HQTRS', '2026-08-24', 'IFC', 'ABJ/HQ/DIP/INT/ORG/83/26', '2028-04-05', 'MULTIPLE', 'GRATIS', '2026-08-24 13:17:52', 'active', '37084', NULL, '2026-08-24 13:17:52', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2024-09-06', '2 YEARS', NULL, '2024-10-11', '2029-10-10'),
(143, 'R1272632', '24PP11964', 'RAYANNE NDINGA', 'GABRIELLE EVA', 'GABON', 'ABUJA HQTRS', '2026-08-24', 'IFC', 'ABJ/HQ/DIP/INT/ORG/83/26', '2028-04-05', 'MULTIPLE', 'GRATIS', '2026-08-24 13:21:08', 'active', '37084', NULL, '2026-08-24 13:21:08', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'NIT/ORG', NULL, '2024-09-06', '2 YEARS', NULL, '2024-09-09', '2029-09-08'),
(144, 'R1272633', 'N3760852', 'CHIQUEIO', 'JOAQUIM JOAO', 'ANGOLA', 'ABUJA HQTRS', '2026-08-24', 'ANGOLA', 'ABJ/HQ/DIP/EM/85/56', '2028-02-04', 'MULTIPLE', 'GRATIS', '2026-08-24 13:30:56', 'active', '37084', NULL, '2026-08-24 13:30:56', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'EMBASSY', NULL, '2026-02-04', '2 YEARS', NULL, '2026-02-07', '2041-02-07'),
(145, 'R1272634', '15LP00626', 'NKUBITO KAREKEZI', 'PATRICK', 'RWANDA', 'ABUJA HQTRS', '2026-08-24', 'ARC', 'ABJ/HQ/DIP/INT/ORG/2930', '2028-08-05', 'MULTIPLE', 'GRATIS', '2026-08-24 13:38:37', 'active', '37084', NULL, '2026-08-24 13:38:37', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2023-12-20', '2 YEARS', NULL, '2026-07-28', '2031-07-28'),
(146, 'R1272635', 'AUNB49432', 'POPLAWSKI RIBEIRO', 'MARCOS', 'BRAZIL', 'ABUJA HQTRS', '2026-08-24', 'IMF', 'ABJ/HQ/DIP/INT/ORG/312220', '2028-03-14', 'MULTIPLE', 'GRATIS', '2026-08-24 13:43:43', 'active', '37084', NULL, '2026-08-24 13:43:43', 'INT/ORG', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'NIT/ORG', NULL, '2026-08-22', '2 YEARS', NULL, '2023-03-14', '2028-03-14'),
(147, 'R1272639', 'R1272639', 'SICHULA', 'MARK', 'ZAMBIA', 'ABUJA HQTRS', '2026-08-24', 'ZAMBIA HC', 'ABJ/HQ/DIP/HC/07/289', '2027-11-15', 'MULTIPLE', 'GRATIS', '2026-08-24 15:17:16', 'active', '37084', NULL, '2026-08-24 15:17:16', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2023-11-18', '1 YEAR', NULL, '2023-11-13', '2033-11-12'),
(148, 'R1272640', 'D0002298', 'CEESAY', 'ANSUMANA', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:22:33', 'active', 'AKPAN001', NULL, '2026-08-24 15:31:34', 'DIPLOMAT', 'fresh', 5, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2022-09-01', '2027-09-01'),
(149, 'R1272641', 'D0002294', 'JAITEH', 'AMIE', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:23:42', 'active', 'AKPAN001', NULL, '2026-08-24 15:31:23', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2022-09-01', '2027-09-01'),
(150, 'R1272636', 'SA029151', 'GOFI DOMTA', 'ALAIN', 'CAMEROON', 'ABUJA HQTRS', '2026-08-24', 'CAMEROON', 'ABJ/HQ/DIP/HC/31', '2028-07-25', 'MULTIPLE', 'GRATIS', '2026-08-24 15:24:06', 'active', '37084', NULL, '2026-08-24 15:24:06', 'DIPLOMAT', 'fresh', 2, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', NULL, '2023-07-27', '2 YEARS', NULL, '2026-07-10', '2031-07-10'),
(151, 'R1272642', 'D0002293', 'CEESAY', 'FATIMA', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:24:39', 'active', 'AKPAN001', NULL, '2026-08-24 15:31:09', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2022-09-01', '2027-09-01'),
(152, 'R1272643', 'D0002291', 'CEESAY', 'AISHA', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:25:35', 'active', 'AKPAN001', NULL, '2026-08-24 15:30:59', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2022-09-01', '2027-09-01'),
(153, 'R1272644', 'D0002292', 'CEESAY', 'KHADIJATOU', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:26:43', 'active', 'AKPAN001', NULL, '2026-08-24 15:30:49', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2022-09-01', '2027-09-01'),
(154, 'R1272637', 'SA009351', 'SINEH EPSE GOFI', 'EDEL QUEEN', 'CAMEROON', 'ABUJA HQTRS', '2026-08-24', 'CAMEROON', 'ABJ/HQ/DIP/HC/31', '2028-07-03', 'MULTIPLE', 'GRATIS', '2026-08-24 15:27:09', 'active', '37084', NULL, '2026-08-24 15:27:09', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', NULL, '2023-07-27', '2 YEARS', NULL, '2023-07-03', '2028-07-03'),
(155, 'R1272645', 'D0005168', 'CEESAY', 'SARAHTA', 'GAMBIA', 'ABUJA HQTRS', '2026-08-24', 'GAMBIA HC', 'ABJ/HQ/DIP/HC/23505/VOL2', '2027-09-01', 'MULTIPLE', 'GRATIS', '2026-08-24 15:27:45', 'active', 'AKPAN001', NULL, '2026-08-24 15:29:20', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'FEMALE', 'YES', 'HIGH COMMISSION', '', '2024-04-27', '2 YEARS', '', '2025-05-19', '2030-05-19'),
(156, 'R1272638', 'SA012526', 'BELLO', 'ABDOUL HAKIM', 'CAMEROON', 'ABUJA HQTRS', '2026-08-24', 'CAMEROON', 'ABJ/HQ/DIP/HC/31', '2028-07-25', 'MULTIPLE', 'GRATIS', '2026-08-24 15:35:39', 'active', '37084', NULL, '2026-08-24 15:37:12', 'DIPLOMAT', 'fresh', 0, 'DIPLOMATIC', 'MALE', 'YES', 'HIGH COMMISSION', '', '2023-07-27', '2 YEARS', '', '2024-02-29', '2029-03-01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_audit`
--
ALTER TABLE `tbl_audit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_no` (`service_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `visa_applications`
--
ALTER TABLE `visa_applications`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT for table `tbl_audit`
--
ALTER TABLE `tbl_audit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `visa_applications`
--
ALTER TABLE `visa_applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
