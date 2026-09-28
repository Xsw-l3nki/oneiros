-- ============================================================
-- Oneiros v1.1.0 Migration
-- Run in phpMyAdmin → SQL tab, or via cPanel MySQL command
-- Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS checks)
-- ============================================================

SET NAMES utf8mb4;

-- ─── Streaks on users ───
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `current_streak`  INT NOT NULL DEFAULT 0  AFTER `is_premium`,
  ADD COLUMN IF NOT EXISTS `longest_streak`  INT NOT NULL DEFAULT 0  AFTER `current_streak`,
  ADD COLUMN IF NOT EXISTS `last_dream_date` DATE DEFAULT NULL        AFTER `longest_streak`,
  ADD COLUMN IF NOT EXISTS `referral_code`   VARCHAR(20) DEFAULT NULL AFTER `last_dream_date`,
  ADD COLUMN IF NOT EXISTS `referred_by`     CHAR(36) DEFAULT NULL   AFTER `referral_code`;

-- Referral code uniqueness (only if not already there)
ALTER TABLE `users`
  ADD UNIQUE KEY IF NOT EXISTS `uk_referral_code` (`referral_code`);

-- ─── Badges ───
CREATE TABLE IF NOT EXISTS `user_badges` (
  `id`        CHAR(36) NOT NULL,
  `user_id`   CHAR(36) NOT NULL,
  `badge_id`  VARCHAR(50) NOT NULL,
  `earned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_badge` (`user_id`, `badge_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_badges` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Email queue (fire and remember for retry) ───
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`        CHAR(36) NOT NULL,
  `to_email`  VARCHAR(255) NOT NULL,
  `to_name`   VARCHAR(100) DEFAULT NULL,
  `subject`   VARCHAR(300) NOT NULL,
  `body_html` LONGTEXT NOT NULL,
  `status`    ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`  INT NOT NULL DEFAULT 0,
  `sent_at`   DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email_status` (`status`),
  INDEX `idx_email_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Dream audio table ───
CREATE TABLE IF NOT EXISTS `dream_audio` (
  `id`               CHAR(36) NOT NULL,
  `dream_id`         CHAR(36) NOT NULL,
  `user_id`          CHAR(36) NOT NULL,
  `audio_url`        VARCHAR(500) NOT NULL,
  `transcript`       TEXT DEFAULT NULL,
  `duration_seconds` INT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`dream_id`) REFERENCES `dreams` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_dream_audio_dream` (`dream_id`),
  INDEX `idx_dream_audio_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Referrals ───
CREATE TABLE IF NOT EXISTS `referrals` (
  `id`            CHAR(36) NOT NULL,
  `referrer_id`   CHAR(36) NOT NULL,
  `referred_id`   CHAR(36) DEFAULT NULL,
  `referral_code` VARCHAR(20) NOT NULL,
  `email`         VARCHAR(255) DEFAULT NULL,
  `status`        ENUM('pending','registered','rewarded') NOT NULL DEFAULT 'pending',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ref_code` (`referral_code`),
  INDEX `idx_referrer` (`referrer_id`),
  FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Backfill referral codes for existing users ───
-- Each existing user gets a unique code based on their ID
UPDATE `users`
SET `referral_code` = UPPER(SUBSTRING(MD5(id), 1, 8))
WHERE `referral_code` IS NULL;
