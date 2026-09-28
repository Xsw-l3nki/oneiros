-- Oneiros v1.2.0 Migration
-- Run in phpMyAdmin AFTER v1.1.0 migration
-- Adds: last_seen_at for online tracking, online_count view

-- Add last_seen_at to users for online status
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_last_seen (last_seen_at);

-- Update last_seen when user fetches /auth/me
-- (done in PHP, this just creates the column)

-- Dream word count for better matching
ALTER TABLE dreams
    ADD COLUMN IF NOT EXISTS word_count SMALLINT UNSIGNED DEFAULT 0;

UPDATE dreams SET word_count = LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1
WHERE word_count = 0 AND content IS NOT NULL;
