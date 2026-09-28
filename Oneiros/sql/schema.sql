-- ============================================================
-- Oneiros MySQL Schema for cPanel
-- Import via phpMyAdmin → Import tab
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ─────────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `email`           VARCHAR(255) NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255) NOT NULL,
  `display_name`    VARCHAR(60) DEFAULT NULL,
  `date_of_birth`   DATE NOT NULL,
  `is_18_plus`      TINYINT(1) NOT NULL DEFAULT 0,
  `research_consent` TINYINT(1) NOT NULL DEFAULT 0,
  `tos_accepted`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `is_moderator`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_admin`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_premium`      TINYINT(1) NOT NULL DEFAULT 0,
  `premium_until`   DATETIME DEFAULT NULL,
  `premium_reminded_until` DATETIME DEFAULT NULL,
  `is_patron`       TINYINT(1) NOT NULL DEFAULT 0,
  `referral_rewarded` TINYINT(1) NOT NULL DEFAULT 0,
  `current_streak` INT NOT NULL DEFAULT 0,
  `longest_streak` INT NOT NULL DEFAULT 0,
  `last_dream_date` DATE DEFAULT NULL,
  `referral_code` VARCHAR(20) DEFAULT NULL UNIQUE,
  `referred_by` CHAR(36) DEFAULT NULL,
  `last_seen_at` DATETIME DEFAULT NULL,
  `region`          VARCHAR(100) DEFAULT NULL,
  `region_code`     CHAR(2) DEFAULT NULL,
  `last_active_at`  DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_region` (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- DREAMS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dreams` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `user_id`         CHAR(36) NOT NULL,
  `title`           VARCHAR(200) DEFAULT NULL,
  `content`         TEXT NOT NULL,
  `word_count` SMALLINT UNSIGNED DEFAULT 0,
  `audio_url`       VARCHAR(500) DEFAULT NULL,
  `image_url`       VARCHAR(500) DEFAULT NULL,
  `ai_generated_image` TINYINT(1) DEFAULT 0,
  `privacy`         ENUM('public','private','research_only') NOT NULL DEFAULT 'public',
  `emotions`        TEXT DEFAULT NULL,
  `themes`          TEXT DEFAULT NULL,
  `symbols`         TEXT DEFAULT NULL,
  `narrative_arc`   VARCHAR(50) DEFAULT NULL,
  `emotion_score`   TEXT DEFAULT NULL,
  `match_count`     INT NOT NULL DEFAULT 0,
  `is_recurring`    TINYINT(1) NOT NULL DEFAULT 0,
  `recurring_group_id` CHAR(36) DEFAULT NULL,
  `is_flagged`      TINYINT(1) NOT NULL DEFAULT 0,
  `flag_count`      INT NOT NULL DEFAULT 0,
  `is_removed`      TINYINT(1) NOT NULL DEFAULT 0,
  `dreamed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_privacy` (`privacy`),
  INDEX `idx_dreamed_at` (`dreamed_at`),
  INDEX `idx_recurring` (`is_recurring`),
  FULLTEXT INDEX `ft_content` (`content`, `title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- DREAM MATCHES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dream_matches` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `dream_a_id`      CHAR(36) NOT NULL,
  `dream_b_id`      CHAR(36) NOT NULL,
  `user_a_id`       CHAR(36) NOT NULL,
  `user_b_id`       CHAR(36) NOT NULL,
  `score`           DECIMAL(5,2) NOT NULL DEFAULT 0,
  `theme_score`    DECIMAL(5,2) DEFAULT 0,
  `emotion_score`  DECIMAL(5,2) DEFAULT 0,
  `symbol_score`   DECIMAL(5,2) DEFAULT 0,
  `narrative_score` DECIMAL(5,2) DEFAULT 0,
  `recency_weight` DECIMAL(3,2) DEFAULT 1.00,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pair` (`dream_a_id`, `dream_b_id`),
  FOREIGN KEY (`dream_a_id`) REFERENCES `dreams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dream_b_id`) REFERENCES `dreams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_a_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_b_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_score` (`score`),
  INDEX `idx_user_a` (`user_a_id`),
  INDEX `idx_user_b` (`user_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- CONNECTIONS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `connections` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `requester_id`    CHAR(36) NOT NULL,
  `receiver_id`     CHAR(36) NOT NULL,
  `status`          ENUM('pending','connected','blocked') NOT NULL DEFAULT 'pending',
  `match_id`        CHAR(36) DEFAULT NULL,
  `connected_at`    DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pair` (`requester_id`, `receiver_id`),
  FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- MESSAGES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `connection_id`   CHAR(36) NOT NULL,
  `sender_id`       CHAR(36) NOT NULL,
  `content`         TEXT NOT NULL,
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  `is_flagged`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`connection_id`) REFERENCES `connections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_conn_time` (`connection_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- NOTIFICATIONS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `user_id`         CHAR(36) NOT NULL,
  `type`            VARCHAR(30) NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `body`            TEXT NOT NULL,
  `data`            TEXT DEFAULT NULL,
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_time` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- MODERATION FLAGS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `moderation_flags` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `dream_id`        CHAR(36) DEFAULT NULL,
  `message_id`      CHAR(36) DEFAULT NULL,
  `reporter_id`     CHAR(36) NOT NULL,
  `reason`          ENUM('inappropriate','harmful','spam','personal_info','other') NOT NULL,
  `notes`           TEXT DEFAULT NULL,
  `status`          ENUM('pending','reviewed','actioned','dismissed','warned','hidden','escalated') NOT NULL DEFAULT 'pending',
  `reviewed_by`     CHAR(36) DEFAULT NULL,
  `reviewed_at`     DATETIME DEFAULT NULL,
  `action_taken`    VARCHAR(255) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_dream` (`dream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- RECURRING GROUPS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recurring_dream_groups` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `user_id`         CHAR(36) NOT NULL,
  `label`           VARCHAR(200) DEFAULT NULL,
  `core_themes`     TEXT DEFAULT NULL,
  `first_seen_at`   DATETIME NOT NULL,
  `last_seen_at`    DATETIME NOT NULL,
  `occurrence_count` INT NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- RESEARCH EVENTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `research_events` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `title`           VARCHAR(255) NOT NULL,
  `description`     TEXT DEFAULT NULL,
  `event_type`      VARCHAR(50) DEFAULT NULL,
  `regions_affected` TEXT DEFAULT NULL,
  `event_date`      DATETIME NOT NULL,
  `created_by`      CHAR(36) NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- REFRESH TOKENS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `user_id`         CHAR(36) NOT NULL,
  `token_hash`      CHAR(64) NOT NULL UNIQUE,
  `expires_at`      DATETIME NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ─────────────────────────────────────────────
-- LUCID: once-off passes, gifts, codes (never recurring)
-- Orders outlive deleted accounts so sales records stay complete.
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `premium_orders` (
  `id`              CHAR(36) NOT NULL PRIMARY KEY,
  `reference`       VARCHAR(20) NOT NULL UNIQUE,
  `user_id`         CHAR(36) DEFAULT NULL,
  `buyer_email`     VARCHAR(255) NOT NULL,
  `plan_id`         VARCHAR(40) NOT NULL,
  `plan_name`       VARCHAR(80) NOT NULL,
  `kind`            ENUM('pass','gift','support') NOT NULL DEFAULT 'pass',
  `days`            INT NOT NULL DEFAULT 0,
  `amount_cents`    INT NOT NULL,
  `discount_cents`  INT NOT NULL DEFAULT 0,
  `discount_code`   VARCHAR(40) DEFAULT NULL,
  `currency`        CHAR(3) NOT NULL DEFAULT 'ZAR',
  `provider`        VARCHAR(20) NOT NULL,
  `provider_ref`    VARCHAR(120) DEFAULT NULL,
  `status`          ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `recipient_email` VARCHAR(255) DEFAULT NULL,
  `gift_code`       VARCHAR(40) DEFAULT NULL,
  `premium_until`   DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at`         DATETIME DEFAULT NULL,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_orders_user` (`user_id`),
  INDEX `idx_orders_status` (`status`, `created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `premium_codes` (
  `id`           CHAR(36) NOT NULL PRIMARY KEY,
  `code`         VARCHAR(40) NOT NULL UNIQUE,
  `kind`         ENUM('gift','promo','discount') NOT NULL,
  `days`         INT NOT NULL DEFAULT 0,
  `percent_off`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_uses`     INT NOT NULL DEFAULT 1,
  `uses`         INT NOT NULL DEFAULT 0,
  `expires_at`   DATETIME DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `note`         VARCHAR(160) DEFAULT NULL,
  `order_id`     CHAR(36) DEFAULT NULL,
  `created_by`   CHAR(36) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_codes_kind` (`kind`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `premium_redemptions` (
  `id`          CHAR(36) NOT NULL PRIMARY KEY,
  `code_id`     CHAR(36) NOT NULL,
  `user_id`     CHAR(36) NOT NULL,
  `order_id`    CHAR(36) DEFAULT NULL,
  `redeemed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_code_user` (`code_id`, `user_id`),
  FOREIGN KEY (`code_id`) REFERENCES `premium_codes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every payment notification received, verified or not, for audits and support
CREATE TABLE IF NOT EXISTS `payment_events` (
  `id`          CHAR(36) NOT NULL PRIMARY KEY,
  `provider`    VARCHAR(20) NOT NULL,
  `order_id`    CHAR(36) DEFAULT NULL,
  `event`       VARCHAR(60) NOT NULL,
  `verified`    TINYINT(1) NOT NULL DEFAULT 0,
  `detail`      VARCHAR(500) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payment_events_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── v2.2 Console ───
-- Settings saved from the Console. Secret values are encrypted (is_encrypted = 1).
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key`   VARCHAR(80) NOT NULL PRIMARY KEY,
  `setting_value` MEDIUMTEXT DEFAULT NULL,
  `is_encrypted`  TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by`    CHAR(36) DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every staff action taken in the Console. Rows are never edited or deleted by the app.
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `actor_id`    CHAR(36) DEFAULT NULL,
  `actor_email` VARCHAR(255) DEFAULT NULL,
  `actor_role`  VARCHAR(20) NOT NULL,
  `action`      VARCHAR(60) NOT NULL,
  `target_type` VARCHAR(40) DEFAULT NULL,
  `target_id`   VARCHAR(80) DEFAULT NULL,
  `summary`     VARCHAR(500) NOT NULL,
  `details`     TEXT DEFAULT NULL,
  `ip`          VARCHAR(64) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_created` (`created_at`),
  INDEX `idx_audit_actor` (`actor_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
