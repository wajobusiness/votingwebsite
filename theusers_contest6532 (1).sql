-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 23, 2024 at 07:23 PM
-- Server version: 10.11.8-MariaDB
-- PHP Version: 8.1.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `theusers_contest6532`
--

-- --------------------------------------------------------

--
-- Table structure for table `banner`
--

CREATE TABLE `banner` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `banner`
--

INSERT INTO `banner` (`id`, `image_path`) VALUES
(4, 'uploads/bant.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `competitions`
--

CREATE TABLE `competitions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `status` enum('active','ended') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `competitions`
--

INSERT INTO `competitions` (`id`, `title`, `description`, `image_path`, `status`, `created_at`) VALUES
(2, 'The Skitmaster Contest - June 2024', 'The Skitmaster Contest - June 2024', 'uploads/4876572Skit (1).jpeg', 'ended', '2024-08-23 08:35:44'),
(3, 'The Skitmaster Contest - June 2024', 'The Skitmaster Contest - June 2024', 'uploads/27267958skit.jpeg', 'ended', '2024-08-23 08:36:15'),
(4, 'The Skitmaster Contest - June 2024yy', 'The Skitmaster Contest - June 2024999', 'uploads/bant.jpg', 'active', '2024-08-23 08:37:33');

-- --------------------------------------------------------

--
-- Table structure for table `contests`
--

CREATE TABLE `contests` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `current_stage`
--

CREATE TABLE `current_stage` (
  `id` int(11) NOT NULL,
  `stage_name` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `current_stage`
--

INSERT INTO `current_stage` (`id`, `stage_name`) VALUES
(1, 'Stage Three');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `registration_open` tinyint(1) DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `name`, `value`, `registration_open`) VALUES
(2, 'competition_end_time', '2024-09-25T07:48', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `vote_count` int(11) DEFAULT 0,
  `full_name` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `photo`, `vote_count`, `full_name`, `phone_number`, `is_admin`) VALUES
(1, 'admin', 'admin@admin.com', '$2y$10$PTupi4SQdU71SnFX2BdoAOUyuz6ahNd9YjCePDys2PeHq/mnZoXnG', 'IMG_9498.JPG', 0, 'Administrator', '', 1),
(6, 'emma', 'emadagwu@gmail.com', '$2y$10$BIpFtMuZvuhdq4/MAW//9.l8Qqki0uAbGl5Kzw/R6SWSDtjuGRe6K', 'b5a54449-9ca5-42fc-a50f-deb822abbec2.JPG', 969, 'Emma Nuel', '2529178071', 0),
(7, 'gword', 'dghl@jhsd.com', '$2y$10$H2DJRVCo3h7TtfZvj/dfneMUcMJ5lToojces1ixANGMoSCMMmDURq', 'IMG_5458.JPG', 36, 'Godsword Vic', '123456789', 0),
(10, 'prince', 'djsa@jhbds.com', '$2y$10$dRjdztwJf.mTFTAb5.O7x.JvCIDkPnqvDag926F.YVN08EG2mPBB.', 'IMG_4539.JPG', 603, 'Prince Junior', '0902367832', 0),
(11, 'James', 'jhds@kjsd.com', '$2y$10$XBrE.inUWlTs2NaRiB5yBu4wdXijURoal3XprV6ZP4nS6TsKUuqRy', 'IMG_7512.jpg', 327, 'Evan', '09023436718', 0),
(14, 'Walex', 'femiwale38@gmail.com', '$2y$10$tRbHouw4xEJKvavbu3ak4Oy/ReF.IvVx2h7mcsu/F3J5ipeKNHYey', 'FF73A904-CC1D-4B57-80E8-FA4CC705852E.png', 700000, 'Femi', '0810578995', 0),
(15, 'jeffben', 'berryab070@gmail.com', '$2y$10$AjYVgQVeRuk6IMESwAQtO.NGJTYKg6lUQ/T1ArRnBe1WbF6eQHDkq', 'IMG_9441.jpg', 0, 'benson jeff', '090789908272', 0),
(19, 'bobi124', 'vgodsword@gmail.comqq', '$2y$10$C73JxCEpvWmvJ9P790vbdeMKFwGjPBqHXqC7CoF/r/MvTeLqzLugO', 'IMG_9440.jpg', 0, 'tobi123', '09078991', 0),
(20, 'iuytrtyu', 'vgodsword@gmail.comuh', '$2y$10$F0Huhmz.b.j0r.UBfTf1NOJl6kC9UNuK9uFpUJ7t/gyge/P15bSeC', 'banner2.jpg', 0, 'benson jeff', '987676545', 0),
(21, 'hhhbbbv', 'berryab070@gmail.com999', '$2y$10$q4tmim5JUaTdXGfATjUnh.CTPpEHK.RU44W0ImrORqZLqt8dky5om', 'banner.jpg', 0, 'tobi123i8', '000999', 0);

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `voter_email` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banner`
--
ALTER TABLE `banner`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `competitions`
--
ALTER TABLE `competitions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contests`
--
ALTER TABLE `contests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `current_stage`
--
ALTER TABLE `current_stage`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banner`
--
ALTER TABLE `banner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `competitions`
--
ALTER TABLE `competitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contests`
--
ALTER TABLE `contests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `current_stage`
--
ALTER TABLE `current_stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
