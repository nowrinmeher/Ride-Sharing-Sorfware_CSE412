-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 28, 2025 at 08:55 AM
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
-- Database: `ride_sharing_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver_locations`
--

CREATE TABLE `driver_locations` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` decimal(8,2) DEFAULT NULL,
  `speed` decimal(8,2) DEFAULT NULL,
  `heading` decimal(5,2) DEFAULT NULL,
  `altitude` decimal(8,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ride_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_profiles`
--

CREATE TABLE `driver_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `nid_number` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_model` varchar(100) DEFAULT NULL,
  `seating_capacity` int(11) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 5.00,
  `total_rides` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_profiles`
--

INSERT INTO `driver_profiles` (`id`, `user_id`, `license_number`, `nid_number`, `vehicle_type`, `vehicle_model`, `seating_capacity`, `vehicle_plate`, `rating`, `total_rides`, `is_available`, `current_latitude`, `current_longitude`, `created_at`) VALUES
(4, 9, '123456789016', '12345678901289021', 'Car', 'Toyota', 10, '1223215', 5.00, 0, 1, NULL, NULL, '2025-05-27 11:11:08');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `type` enum('airport','university','shopping_mall','hospital','station','other') DEFAULT 'other',
  `is_popular` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `address`, `latitude`, `longitude`, `type`, `is_popular`, `created_at`) VALUES
(1, 'Hazrat Shahjalal International Airport', 'Airport - Dakshinkhan Rd, Dhaka', NULL, NULL, 'airport', 1, '2025-05-18 06:32:00'),
(2, 'North South University', 'Plot # 15, Dhaka', NULL, NULL, 'university', 1, '2025-05-18 06:32:00'),
(3, 'East West University', 'Aftabnagar, Dhaka', NULL, NULL, 'university', 1, '2025-05-18 06:32:00'),
(4, 'Dhanmondi', 'Dhanmondi, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(5, 'Gulshan', 'Gulshan, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(6, 'Banani', 'Banani, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(7, 'Uttara', 'Uttara, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(8, 'Mirpur', 'Mirpur, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(9, 'Old Dhaka', 'Old Dhaka, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(10, 'New Market', 'New Market, Dhaka', NULL, NULL, 'shopping_mall', 1, '2025-05-18 06:32:00'),
(11, 'Bashundhara City', 'Bashundhara City, Dhaka', NULL, NULL, 'shopping_mall', 1, '2025-05-18 06:32:00'),
(12, 'Motijheel', 'Motijheel, Dhaka', NULL, NULL, 'other', 1, '2025-05-18 06:32:00'),
(13, 'Wari', 'Wari, Dhaka', NULL, NULL, 'other', 0, '2025-05-18 06:32:00'),
(14, 'Ramna', 'Ramna, Dhaka', NULL, NULL, 'other', 0, '2025-05-18 06:32:00'),
(15, 'Tejgaon', 'Tejgaon, Dhaka', NULL, NULL, 'other', 0, '2025-05-18 06:32:00'),
(16, 'Mohammadpur', 'Mohammadpur, Dhaka', NULL, NULL, 'other', 0, '2025-05-18 06:32:00'),
(17, 'Dhaka Medical College Hospital', 'Dhaka Medical College Hospital Road, Dhaka', NULL, NULL, 'hospital', 1, '2025-05-18 06:32:00'),
(18, 'Square Hospital', 'West Panthapath, Dhaka', NULL, NULL, 'hospital', 1, '2025-05-18 06:32:00'),
(19, 'United Hospital', 'Gulshan, Dhaka', NULL, NULL, 'hospital', 1, '2025-05-18 06:32:00'),
(20, 'Kamalapur Railway Station', 'Kamalapur, Dhaka', NULL, NULL, 'station', 1, '2025-05-18 06:32:00');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token`, `expires_at`, `used`, `updated_at`, `created_at`) VALUES
(1, 1, '184366', '2025-05-20 08:46:43', 1, NULL, '2025-05-20 06:31:43'),
(2, 1, '184366', '2025-05-20 08:46:43', 1, NULL, '2025-05-20 06:31:43'),
(3, 1, '184366', '2025-05-20 08:46:43', 1, NULL, '2025-05-20 06:31:43'),
(5, 3, '740763', '2025-05-20 08:17:43', 1, NULL, '2025-05-20 06:02:43'),
(12, 3, '740763', '2025-05-20 08:17:43', 1, NULL, '2025-05-20 06:02:43'),
(13, 1, '184366', '2025-05-20 08:46:43', 1, NULL, '2025-05-20 06:31:43'),
(14, 1, '184366', '2025-05-20 08:46:43', 1, NULL, '2025-05-20 06:31:43'),
(15, 1, '614591', '2025-05-25 12:13:24', 1, NULL, '2025-05-25 09:58:24'),
(16, 1, '256565', '2025-05-25 12:13:36', 1, NULL, '2025-05-25 09:58:36'),
(17, 1, '527714', '2025-05-25 12:14:42', 1, NULL, '2025-05-25 09:59:42'),
(18, 1, '268753', '2025-05-25 12:14:48', 1, NULL, '2025-05-25 09:59:48'),
(19, 1, '535287', '2025-05-25 12:15:01', 1, NULL, '2025-05-25 10:00:01'),
(20, 1, '129121', '2025-05-25 12:16:52', 1, NULL, '2025-05-25 10:01:52'),
(21, 1, '942909', '2025-05-25 12:17:07', 1, NULL, '2025-05-25 10:02:07'),
(23, 1, '945381', '2025-05-25 12:19:02', 1, NULL, '2025-05-25 10:04:02'),
(24, 1, '724064', '2025-05-25 12:22:57', 1, NULL, '2025-05-25 10:07:57'),
(25, 3, '363742', '2025-05-25 12:24:30', 1, NULL, '2025-05-25 10:09:30'),
(26, 3, '629447', '2025-05-25 12:32:13', 1, NULL, '2025-05-25 10:17:13'),
(27, 1, '463406', '2025-05-25 12:53:38', 1, '2025-05-25 16:28:02', '2025-05-25 10:23:38'),
(28, 3, '478900', '2025-05-25 12:58:42', 1, '2025-05-25 16:29:04', '2025-05-25 10:28:42'),
(29, 1, '331877', '2025-05-25 13:07:05', 1, NULL, '2025-05-25 10:37:05'),
(30, 1, '626608', '2025-05-25 13:11:15', 1, NULL, '2025-05-25 10:41:15'),
(31, 1, '805575', '2025-05-25 13:11:19', 1, NULL, '2025-05-25 10:41:19'),
(32, 1, '751884', '2025-05-25 13:12:21', 1, NULL, '2025-05-25 10:42:21'),
(33, 1, '866881', '2025-05-25 13:12:30', 1, NULL, '2025-05-25 10:42:30'),
(34, 3, '215645', '2025-05-25 13:13:10', 1, '2025-05-25 16:43:40', '2025-05-25 10:43:10'),
(35, 1, '578349', '2025-05-25 13:22:33', 1, NULL, '2025-05-25 10:52:33'),
(36, 1, '825794', '2025-05-25 13:24:42', 1, NULL, '2025-05-25 10:54:42'),
(37, 1, '623371', '2025-05-25 13:24:45', 1, NULL, '2025-05-25 10:54:45'),
(38, 1, '577396', '2025-05-25 13:24:53', 1, NULL, '2025-05-25 10:54:53'),
(39, 1, '834796', '2025-05-25 13:33:10', 1, NULL, '2025-05-25 11:03:10'),
(40, 1, '892947', '2025-05-25 13:34:13', 1, NULL, '2025-05-25 11:04:13'),
(41, 1, '744558', '2025-05-25 13:34:54', 1, NULL, '2025-05-25 11:04:54'),
(42, 1, '783193', '2025-05-25 13:45:57', 1, NULL, '2025-05-25 11:15:57'),
(43, 1, '523215', '2025-05-25 13:46:05', 1, NULL, '2025-05-25 11:16:05'),
(44, 1, '188708', '2025-05-25 13:49:31', 1, NULL, '2025-05-25 11:19:31'),
(45, 1, '502416', '2025-05-25 13:54:07', 1, NULL, '2025-05-25 11:24:07'),
(46, 3, '313705', '2025-05-25 13:54:32', 0, NULL, '2025-05-25 11:24:32'),
(48, 1, '475109', '2025-05-25 13:55:39', 1, NULL, '2025-05-25 11:25:39'),
(49, 5, '746034', '2025-05-25 13:57:06', 1, NULL, '2025-05-25 11:27:06'),
(50, 5, '866288', '2025-05-25 13:57:28', 1, NULL, '2025-05-25 11:27:28'),
(51, 5, '957743', '2025-05-25 14:00:48', 1, NULL, '2025-05-25 11:30:48'),
(52, 5, '407200', '2025-05-25 14:01:18', 1, '2025-05-25 17:31:49', '2025-05-25 11:31:18'),
(54, 1, '705286', '2025-05-25 14:03:57', 1, NULL, '2025-05-25 11:33:57'),
(68, 1, '824362', '2025-05-25 15:19:45', 1, '2025-05-25 18:50:46', '2025-05-25 12:49:45'),
(80, 1, '681926', '2025-05-25 17:09:41', 1, NULL, '2025-05-25 14:39:41'),
(81, 1, '253960', '2025-05-25 17:09:58', 1, NULL, '2025-05-25 14:39:58'),
(82, 1, '365535', '2025-05-25 17:10:26', 1, NULL, '2025-05-25 14:40:26'),
(83, 1, '909781', '2025-05-25 17:10:45', 1, NULL, '2025-05-25 14:40:45'),
(84, 1, '801198', '2025-05-25 17:10:58', 1, '2025-05-25 20:41:24', '2025-05-25 14:40:58');

-- --------------------------------------------------------

--
-- Table structure for table `rider_profiles`
--

CREATE TABLE `rider_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_latitude` decimal(10,7) DEFAULT NULL,
  `current_longitude` decimal(10,7) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rides`
--

CREATE TABLE `rides` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `pickup_location` varchar(255) NOT NULL,
  `dropoff_location` varchar(255) NOT NULL,
  `pickup_latitude` decimal(10,8) DEFAULT NULL,
  `pickup_longitude` decimal(11,8) DEFAULT NULL,
  `dropoff_latitude` decimal(10,8) DEFAULT NULL,
  `dropoff_longitude` decimal(11,8) DEFAULT NULL,
  `ride_type` enum('UberX','UberPremium','UberXL','UberPool') DEFAULT 'UberX',
  `status` enum('requested','confirmed','in_progress','completed','cancelled') DEFAULT 'requested',
  `estimated_price` decimal(10,2) DEFAULT NULL,
  `actual_price` decimal(10,2) DEFAULT NULL,
  `scheduled_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rides`
--

INSERT INTO `rides` (`id`, `user_id`, `driver_id`, `pickup_location`, `dropoff_location`, `pickup_latitude`, `pickup_longitude`, `dropoff_latitude`, `dropoff_longitude`, `ride_type`, `status`, `estimated_price`, `actual_price`, `scheduled_time`, `created_at`, `updated_at`) VALUES
(37, 3, NULL, 'Aftabnagar', 'Chittagong', NULL, NULL, NULL, NULL, '', 'cancelled', 1200.00, NULL, NULL, '2025-05-27 16:56:38', '2025-05-28 06:54:46'),
(38, 3, NULL, 'Aftabnagar', 'Chittagong', NULL, NULL, NULL, NULL, '', 'completed', 1500.00, 1500.00, NULL, '2025-05-27 18:46:48', '2025-05-28 06:54:46'),
(39, 3, NULL, 'Gazipur', 'Dhaka', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'completed', 65.75, 65.75, NULL, '2025-05-27 20:19:40', '2025-05-28 06:54:46'),
(40, 3, NULL, 'South Badda, Dhaka-1212', 'East West University, Aftabnagar, Dhaka-1212', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'completed', 289.62, 289.62, NULL, '2025-05-28 01:51:42', '2025-05-28 06:54:46'),
(41, 3, NULL, 'South Badda, Dhaka-1212', 'East West University, Aftabnagar, Dhaka-1212', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 289.62, NULL, NULL, '2025-05-28 01:52:47', '2025-05-28 01:54:46'),
(42, 3, NULL, 'South Badda, Dhaka-1212', 'East West University, Aftabnagar, Dhaka-1212', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 289.62, NULL, NULL, '2025-05-28 01:54:11', '2025-05-28 01:54:49'),
(43, 3, NULL, 'South Badda, Dhaka-1212', 'East West University, Aftabnagar, Dhaka-1212', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 289.62, NULL, NULL, '2025-05-28 01:54:15', '2025-05-28 01:54:52'),
(44, 3, NULL, 'aftabnagar', 'Badda', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'completed', 285.47, 285.47, NULL, '2025-05-28 02:04:02', '2025-05-28 06:54:46'),
(45, 3, NULL, 'aftabnagar', 'Badda', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'completed', 285.47, 285.47, NULL, '2025-05-28 02:04:15', '2025-05-28 06:54:46'),
(46, 3, NULL, 'aftabnagar', 'Badda', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 285.47, NULL, NULL, '2025-05-28 02:07:07', '2025-05-28 02:25:57'),
(47, 3, NULL, 'aftabnagar', 'Badda', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 285.47, NULL, NULL, '2025-05-28 02:23:27', '2025-05-28 02:25:59'),
(48, 3, NULL, 'aftabnagar', 'Badda', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 285.47, NULL, NULL, '2025-05-28 02:25:50', '2025-05-28 02:26:03'),
(49, 3, NULL, 'South Badda', 'Banasree', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'completed', 191.65, 191.65, NULL, '2025-05-28 02:26:20', '2025-05-28 06:54:46'),
(50, 3, NULL, 'South Badda', 'Banasree', 0.00000000, 0.00000000, 0.00000000, 0.00000000, '', 'cancelled', 191.65, NULL, NULL, '2025-05-28 02:26:38', '2025-05-28 02:26:42');

-- --------------------------------------------------------

--
-- Table structure for table `ride_reviews`
--

CREATE TABLE `ride_reviews` (
  `id` int(11) NOT NULL,
  `ride_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `search_query` varchar(255) NOT NULL,
  `search_type` enum('location','general') DEFAULT 'location',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `user_type` enum('rider','driver','admin') DEFAULT 'rider',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `profile_image`, `user_type`, `status`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'Meher Nowrin', 'nowrinmeher222@gmail.com', '$2y$10$DUeE9vHeIkghQyUia46KK.rxAmZWwUdu1p4dBGbnU.Vn6eURZS6rm', '01841994048', NULL, 'admin', 'active', 0, '2025-05-18 18:01:24', '2025-05-25 14:41:24'),
(3, 'Meher Nowrin', 'nowrinmeher6@gmail.com', '$2y$10$qvcvptMNv1HYQtwlyhIIHuFo/GWnDWmdMQXVzZ2L1Hr.lbegsJn0m', '01724727754', NULL, 'rider', 'active', 0, '2025-05-18 20:23:44', '2025-05-25 10:43:40'),
(5, 'Angry Bird', 'angrybird2025fav@gmail.com', '$2y$10$RuHOMHPGtDEaJvU5fk4xmu01jovuOXu/R6FfVz5R.vqlgrGGwt6/a', '01718510845', NULL, 'rider', 'active', 0, '2025-05-25 07:26:54', '2025-05-25 11:31:49'),
(9, 'meher nowrin', 'nowrinmeher13@gmail.com', '$2y$10$mpyXDPzzSIDd.sByN51FWOHJ8e8O5/20/61mcNltjoKDyo3JVIWEe', '01718510836', NULL, 'driver', 'active', 0, '2025-05-27 11:11:08', '2025-05-27 11:11:08');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_ride_history`
-- (See below for the actual view)
--
CREATE TABLE `user_ride_history` (
`id` int(11)
,`pickup_location` varchar(255)
,`dropoff_location` varchar(255)
,`ride_type` enum('UberX','UberPremium','UberXL','UberPool')
,`status` enum('requested','confirmed','in_progress','completed','cancelled')
,`actual_price` decimal(10,2)
,`created_at` timestamp
,`full_name` varchar(100)
,`email` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `user_ride_history`
--
DROP TABLE IF EXISTS `user_ride_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_ride_history`  AS SELECT `r`.`id` AS `id`, `r`.`pickup_location` AS `pickup_location`, `r`.`dropoff_location` AS `dropoff_location`, `r`.`ride_type` AS `ride_type`, `r`.`status` AS `status`, `r`.`actual_price` AS `actual_price`, `r`.`created_at` AS `created_at`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email` FROM (`rides` `r` join `users` `u` on(`r`.`user_id` = `u`.`id`)) ORDER BY `r`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_updated` (`driver_id`,`updated_at`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_driver_locations_driver_time` (`driver_id`,`updated_at`);

--
-- Indexes for table `driver_profiles`
--
ALTER TABLE `driver_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_driver_profiles_available` (`is_available`);

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_locations_name` (`name`),
  ADD KEY `idx_locations_popular` (`is_popular`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rider_profiles`
--
ALTER TABLE `rider_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rides_user_id` (`user_id`);

--
-- Indexes for table `ride_reviews`
--
ALTER TABLE `ride_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ride_id` (`ride_id`),
  ADD KEY `rider_id` (`rider_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_search_history_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `driver_locations`
--
ALTER TABLE `driver_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_profiles`
--
ALTER TABLE `driver_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `rider_profiles`
--
ALTER TABLE `rider_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `ride_reviews`
--
ALTER TABLE `ride_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD CONSTRAINT `driver_locations_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_profiles`
--
ALTER TABLE `driver_profiles`
  ADD CONSTRAINT `driver_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_profiles`
--
ALTER TABLE `rider_profiles`
  ADD CONSTRAINT `rider_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `rides`
--
ALTER TABLE `rides`
  ADD CONSTRAINT `rides_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ride_reviews`
--
ALTER TABLE `ride_reviews`
  ADD CONSTRAINT `ride_reviews_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_reviews_ibfk_2` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_reviews_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `search_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
