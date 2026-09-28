<?php
/**
 * Oneiros — database migrations, shared by tools/console.php and the Console health page.
 * Safe to run repeatedly: it only adds what is missing and never drops data.
 */
class Migrator
{
    public const TABLES = ['users', 'dreams', 'dream_matches', 'connections', 'messages', 'notifications',
        'moderation_flags', 'recurring_dream_groups', 'research_events', 'refresh_tokens', 'user_badges',
        'email_queue', 'dream_audio', 'referrals', 'premium_orders', 'premium_codes', 'premium_redemptions',
        'payment_events', 'app_settings', 'audit_log'];

    // Columns added after v1.0. Queried from metadata rather than MariaDB-only ADD COLUMN IF NOT EXISTS.
    private const COLUMNS = [
        'users' => [
            'current_streak' => 'INT NOT NULL DEFAULT 0', 'longest_streak' => 'INT NOT NULL DEFAULT 0',
            'last_dream_date' => 'DATE DEFAULT NULL', 'referral_code' => 'VARCHAR(20) DEFAULT NULL',
            'referred_by' => 'CHAR(36) DEFAULT NULL', 'last_seen_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'premium_until' => 'DATETIME DEFAULT NULL', 'premium_reminded_until' => 'DATETIME DEFAULT NULL',
            'is_patron' => 'TINYINT(1) NOT NULL DEFAULT 0', 'referral_rewarded' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'dreams' => ['word_count' => 'SMALLINT UNSIGNED DEFAULT 0'],
    ];
    private const INDEXES = [
        'uk_referral_code' => 'UNIQUE KEY `uk_referral_code` (`referral_code`)',
        'idx_last_seen' => 'INDEX `idx_last_seen` (`last_seen_at`)',
    ];

    /** What a migration would change: missing tables and columns. Empty means up to date. */
    public static function pending(PDO $pdo): array
    {
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $pending = [];
        foreach (array_diff(self::TABLES, $existing) as $table) $pending[] = "table $table";
        foreach (self::COLUMNS as $table => $definitions) {
            if (!in_array($table, $existing, true)) continue;
            foreach (array_keys($definitions) as $name) {
                if (!self::columnExists($pdo, $table, $name)) $pending[] = "column $table.$name";
            }
        }
        return $pending;
    }

    /** Bring an existing database up to date. Returns human-readable steps taken. */
    public static function run(PDO $pdo, string $schemaFile): array
    {
        $steps = [];
        foreach (self::COLUMNS as $table => $definitions) {
            if (!self::tableExists($pdo, $table)) continue;  // schema.sql creates it complete
            foreach ($definitions as $name => $definition) {
                if (!self::columnExists($pdo, $table, $name)) {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
                    $steps[] = "added column $table.$name";
                }
            }
        }
        if (self::tableExists($pdo, 'users')) {
            foreach (self::INDEXES as $name => $definition) {
                $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
                $query->execute(['users', $name]);
                if (!$query->fetchColumn()) { $pdo->exec("ALTER TABLE users ADD $definition"); $steps[] = "added index users.$name"; }
            }
        }
        $before = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec(file_get_contents($schemaFile));  // CREATE TABLE IF NOT EXISTS for every table
        foreach (array_diff($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), $before) as $table) $steps[] = "created table $table";
        $pdo->exec("UPDATE users SET referral_code = UPPER(SUBSTRING(MD5(id), 1, 8)) WHERE referral_code IS NULL");
        // Accounts flagged premium before 2.1 keep access as a 30-day Lucid pass
        $pdo->exec("UPDATE users SET premium_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE is_premium = 1 AND premium_until IS NULL");
        $pdo->exec("UPDATE dreams SET word_count = LEAST(65535, LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1) WHERE word_count = 0 AND content IS NOT NULL");
        return $steps;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $query->execute([$table]);
        return (bool)$query->fetchColumn();
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $query->execute([$table, $column]);
        return (bool)$query->fetchColumn();
    }
}
