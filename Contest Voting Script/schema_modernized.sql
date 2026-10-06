-- =========================================================
-- Modernized Database Schema for Online Voting Platform
-- Standardized on InnoDB engine & UTF8mb4 charset with full transactional support
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: settings (Key-Value System Configuration)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `value` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_settings_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default settings
INSERT INTO `settings` (`name`, `value`) VALUES
('site_title', 'Crown Night Star'),
('site_tagline', 'Most Anticipated Online Contest'),
('currency_symbol', '₦'),
('currency_code', 'NGN'),
('vote_price', '50'),
('registration_open', '1'),
('voting_open', '1'),
('competition_end_time', '2026-12-31T23:59'),
('current_stage', 'Stage One'),
('support_email', 'hello@theusersportal.cloud'),
('support_phone', '09067619370')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- --------------------------------------------------------
-- Table: banner (Hero / Promotional Banners)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banner` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: competitions (Contests Showcase / Categories)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `status` ENUM('upcoming', 'active', 'ended') DEFAULT 'active',
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_competition_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users (Administrators and Contestants)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(25) NOT NULL,
  `photo` VARCHAR(255) DEFAULT 'default_avatar.png',
  `bio` TEXT DEFAULT NULL,
  `video_url` VARCHAR(500) DEFAULT NULL,
  `vote_count` INT(11) DEFAULT 0,
  `is_admin` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `competition_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_username` (`username`),
  UNIQUE KEY `idx_user_email` (`email`),
  KEY `idx_user_votes` (`vote_count` DESC),
  KEY `idx_user_admin` (`is_admin`),
  KEY `idx_user_competition` (`competition_id`),
  CONSTRAINT `fk_users_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: payments (Financial Audit & Gateway Transactions)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'Contestant who received votes',
  `transaction_id` VARCHAR(100) NOT NULL COMMENT 'Paystack transaction ID / Reference',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount paid in main currency (e.g. 500.00)',
  `currency` VARCHAR(10) DEFAULT 'NGN',
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT 'paystack',
  `channel` VARCHAR(50) DEFAULT NULL COMMENT 'card, bank, ussd, etc.',
  `payer_email` VARCHAR(100) DEFAULT NULL,
  `payer_name` VARCHAR(100) DEFAULT NULL,
  `payer_phone` VARCHAR(30) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `raw_response` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_payment_reference` (`transaction_id`),
  KEY `idx_payment_user_id` (`user_id`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_created` (`created_at`),
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: votes (Immutable Vote Ledger Receipts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `votes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Contestant who received votes',
  `payment_id` INT(11) DEFAULT NULL COMMENT 'Associated payment record',
  `vote_count` INT(11) NOT NULL DEFAULT 1 COMMENT 'Number of votes in this transaction',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Total amount paid for these votes',
  `voter_email` VARCHAR(100) DEFAULT NULL,
  `voter_name` VARCHAR(100) DEFAULT NULL,
  `voter_phone` VARCHAR(30) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `stage_name` VARCHAR(100) DEFAULT 'Stage One',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_votes_user_id` (`user_id`),
  KEY `idx_votes_payment_id` (`payment_id`),
  KEY `idx_votes_created` (`created_at`),
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: current_stage (Maintained for backward compatibility)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `current_stage` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `stage_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `current_stage` (`id`, `stage_name`) VALUES (1, 'Stage One')
ON DUPLICATE KEY UPDATE `stage_name`=VALUES(`stage_name`);

-- --------------------------------------------------------
-- Table: contests (Maintained for backward compatibility)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
