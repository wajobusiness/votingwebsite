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
('registration_fee', '0'),
('registration_open', '1'),
('voting_open', '1'),
('competition_end_time', '2026-12-31T23:59'),
('current_stage', 'Stage One'),
('support_email', 'hello@theusersportal.cloud'),
('support_phone', '08139188570')
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
  `registration_status` ENUM('pending', 'paid', 'exempt') NOT NULL DEFAULT 'paid',
  `registration_paid_at` DATETIME DEFAULT NULL,
  `registration_payment_ref` VARCHAR(100) DEFAULT NULL,
  `registration_fee_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `competition_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_username` (`username`),
  UNIQUE KEY `idx_user_email` (`email`),
  KEY `idx_user_votes` (`vote_count` DESC),
  KEY `idx_user_admin` (`is_admin`),
  KEY `idx_user_reg_status` (`registration_status`),
  KEY `idx_user_competition` (`competition_id`),
  CONSTRAINT `fk_users_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: payments (Financial Audit & Gateway Transactions)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'Contestant who received votes or registered',
  `transaction_id` VARCHAR(100) NOT NULL COMMENT 'Paystack transaction ID / Reference',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount paid in main currency (e.g. 500.00)',
  `currency` VARCHAR(10) DEFAULT 'NGN',
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT 'paystack',
  `payment_type` VARCHAR(50) NOT NULL DEFAULT 'vote' COMMENT 'vote, book, registration',
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
  KEY `idx_payment_type` (`payment_type`),
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
-- Table: contest_battles (Head-to-Head Contest Battles & Showdowns)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contest_battles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'Talent Battle',
  `description` TEXT DEFAULT NULL,
  `contestant_one_name` VARCHAR(255) NOT NULL,
  `contestant_one_image` VARCHAR(255) NOT NULL,
  `contestant_two_name` VARCHAR(255) NOT NULL,
  `contestant_two_image` VARCHAR(255) NOT NULL,
  `banner_image` VARCHAR(255) DEFAULT NULL,
  `battle_date` DATE NOT NULL,
  `battle_time` VARCHAR(50) NOT NULL,
  `venue_type` ENUM('online', 'physical') NOT NULL DEFAULT 'online',
  `platform` VARCHAR(100) DEFAULT 'Instagram Live',
  `live_url` VARCHAR(500) DEFAULT NULL,
  `venue_name` VARCHAR(255) DEFAULT NULL,
  `venue_address` VARCHAR(255) DEFAULT NULL,
  `venue_city` VARCHAR(100) DEFAULT NULL,
  `venue_state` VARCHAR(100) DEFAULT NULL,
  `maps_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('upcoming', 'live', 'ended', 'cancelled') NOT NULL DEFAULT 'upcoming',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_battles_published` (`is_published`),
  KEY `idx_battles_status` (`status`),
  KEY `idx_battles_order` (`display_order` ASC, `battle_date` ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
