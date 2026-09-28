<?php
/** Deployment and maintenance commands. Run from cPanel Terminal, never the web. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$command = $argv[1] ?? 'help';
if ($command === 'help') {
    echo <<<TXT
Oneiros maintenance

  php tools/console.php check                         verify PHP, database, tables and uploads
  php tools/console.php install                       create tables in an empty database
  php tools/console.php migrate                       update an existing database (keeps all data)
  php tools/console.php admin you@example.com         give an account administrator rights
  php tools/console.php mail                          send queued emails (for a cron job)

Lucid (once-off passes)
  php tools/console.php payments                      show gateway setup and the URLs to give PayFast/Paystack
  php tools/console.php grant you@example.com 30      add 30 Lucid days to an account (e.g. EFT payments)
  php tools/console.php revoke you@example.com        end an account's Lucid pass
  php tools/console.php codes promo 30 10             create 10 single-use codes worth 30 Lucid days each
  php tools/console.php codes promo 7 1 500           create 1 code worth 7 days that 500 people can use
  php tools/console.php codes discount 20 1 100       create 1 code for 20% off, usable 100 times

Back up your database before install/migrate. Install only accepts an empty database.

TXT;
    exit;
}
try {
    $cfg = require $root . '/includes/runtime-config.php';
    foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'] as $ext) {
        if (!extension_loaded($ext)) throw new RuntimeException("Missing PHP extension: $ext");
    }
    if (version_compare(PHP_VERSION, '8.2.0', '<')) throw new RuntimeException('PHP 8.2 or later is required.');
    if (in_array($command, ['check', 'install', 'migrate'], true)) {
        foreach (['jwt_secret', 'jwt_refresh_secret'] as $key) {
            if (strlen($cfg[$key]) < 48 || str_contains($cfg[$key], 'CHANGE_ME')) throw new RuntimeException("Configure a unique random $key of at least 48 characters.");
        }
        if ($cfg['jwt_secret'] === $cfg['jwt_refresh_secret']) throw new RuntimeException('Use different access and refresh secrets.');
        if (!filter_var($cfg['frontend_url'], FILTER_VALIDATE_URL)) throw new RuntimeException('Configure frontend_url as your complete site URL.');
    }
    $pdo = new PDO("mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($command === 'install') {
        if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Database is not empty. Use migrate for an existing Oneiros database.');
        $pdo->exec(file_get_contents($root . '/sql/schema.sql'));
        echo "Complete schema installed.\n";
    } elseif ($command === 'migrate') {
        // Query metadata rather than using MariaDB-only ADD COLUMN IF NOT EXISTS.
        $columns = [
            'users' => [
                'current_streak' => 'INT NOT NULL DEFAULT 0', 'longest_streak' => 'INT NOT NULL DEFAULT 0',
                'last_dream_date' => 'DATE DEFAULT NULL', 'referral_code' => 'VARCHAR(20) DEFAULT NULL',
                'referred_by' => 'CHAR(36) DEFAULT NULL', 'last_seen_at' => 'TIMESTAMP NULL DEFAULT NULL',
                'premium_until' => 'DATETIME DEFAULT NULL', 'premium_reminded_until' => 'DATETIME DEFAULT NULL',
                'is_patron' => 'TINYINT(1) NOT NULL DEFAULT 0', 'referral_rewarded' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'dreams' => ['word_count' => 'SMALLINT UNSIGNED DEFAULT 0'],
        ];
        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $name => $definition) {
                $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
                $query->execute([$table, $name]);
                if (!$query->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
            }
        }
        foreach (['uk_referral_code' => 'UNIQUE KEY `uk_referral_code` (`referral_code`)', 'idx_last_seen' => 'INDEX `idx_last_seen` (`last_seen_at`)'] as $name => $definition) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
            $query->execute(['users', $name]);
            if (!$query->fetchColumn()) $pdo->exec("ALTER TABLE users ADD $definition");
        }
        $pdo->exec(file_get_contents($root . '/sql/schema.sql'));
        $pdo->exec("UPDATE users SET referral_code = UPPER(SUBSTRING(MD5(id), 1, 8)) WHERE referral_code IS NULL");
        // Accounts flagged premium before 2.1 keep access as a 30-day Lucid pass
        $pdo->exec("UPDATE users SET premium_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE is_premium = 1 AND premium_until IS NULL");
        $pdo->exec("UPDATE dreams SET word_count = LEAST(65535, LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1) WHERE word_count = 0 AND content IS NOT NULL");
        echo "Schema updated. Existing accounts, dreams and messages were retained.\n";
    } elseif ($command === 'admin') {
        $email = $argv[2] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Usage: php tools/console.php admin you@example.com');
        $q = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $q->execute([$email]);
        $id = $q->fetchColumn();
        if (!$id) throw new RuntimeException('Register this account on the website first.');
        $q = $pdo->prepare('UPDATE users SET is_admin = 1, is_moderator = 1 WHERE id = ?'); $q->execute([$id]);
        echo "Administrator access granted. Sign in again to refresh your role.\n";
    } elseif (in_array($command, ['grant', 'revoke', 'codes', 'payments'], true)) {
        require_once $root . '/includes/payments.php';
        if ($command === 'payments') {
            $base = rtrim($cfg['frontend_url'], '/');
            $providers = Premium::providers();
            echo 'Gateways ready: ' . ($providers ? implode(', ', $providers) : 'none (codes, gifts and referrals still work)') . "\n";
            if (!function_exists('curl_init')) echo "  ! The PHP curl extension is required for payments.\n";
            if (!empty($cfg['payfast_merchant_id'])) {
                echo 'PayFast: ' . (!empty($cfg['payfast_sandbox']) ? 'SANDBOX (test payments only)' : 'LIVE') . "\n";
                echo "  notify URL (sent automatically with each payment): $base/api.php?_route=payments/payfast-itn\n";
                if (empty($cfg['payfast_passphrase'])) echo "  ! Set a passphrase in PayFast and in config.php: it protects payment notifications.\n";
                elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $cfg['payfast_passphrase'])) echo "  ! Use only letters, numbers, dots, dashes or underscores in the passphrase (PayFast encodes other symbols inconsistently).\n";
            }
            if (!empty($cfg['paystack_secret_key'])) {
                echo 'Paystack: ' . (str_starts_with($cfg['paystack_secret_key'], 'sk_test') ? 'TEST mode' : 'LIVE') . "\n";
                echo "  paste this Webhook URL in Paystack > Settings > API Keys & Webhooks:\n  $base/api.php?_route=payments/paystack-webhook\n";
            }
            echo "Passes:\n";
            foreach (Premium::plans() as $plan) {
                echo sprintf("  %-12s %-14s %4d days  %s\n", $plan['id'], $plan['name'], $plan['days'], Premium::money($plan['price_cents']));
            }
        } elseif ($command === 'codes') {
            [$kind, $value, $count, $uses] = [$argv[2] ?? '', (int)($argv[3] ?? 0), (int)($argv[4] ?? 1), (int)($argv[5] ?? 1)];
            if (!in_array($kind, ['promo', 'discount'], true) || $value < 1 || $count < 1 || $count > 500) {
                throw new RuntimeException('Usage: php tools/console.php codes promo DAYS COUNT [USES]  or  codes discount PERCENT COUNT [USES]');
            }
            for ($i = 0; $i < $count; $i++) {
                $code = Premium::createCode($kind, $kind === 'promo' ? $value : 0, $kind === 'discount' ? $value : 0, $uses, null, 'Created from the console');
                echo $code['code'] . "\n";
            }
        } else {
            $email = strtolower(trim($argv[2] ?? ''));
            $account = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if (!$account) throw new RuntimeException('No account with that email. Register it on the website first.');
            if ($command === 'revoke') {
                Premium::reduce($account['id'], null);
                echo "Lucid pass ended for $email.\n";
            } else {
                $days = (int)($argv[3] ?? 0);
                if ($days < 1 || $days > 3650) throw new RuntimeException('Usage: php tools/console.php grant you@example.com DAYS');
                Premium::extend($account['id'], $days);
                $status = Premium::status($account['id']);
                echo "$email now has Lucid until {$status['premium_until']} ({$status['days_left']} days).\n";
            }
        }
    } elseif ($command === 'mail') {
        if (!$cfg['mail_enabled']) throw new RuntimeException('Mail is disabled. Verify your sender mailbox and delivery before enabling it.');
        require_once $root . '/includes/mailer.php';
        echo 'Processed ' . Mailer::processQueue(20) . " queued emails.\n";
    } elseif ($command !== 'check') {
        throw new RuntimeException('Unknown command. Run php tools/console.php help');
    }
    if (in_array($command, ['check', 'install', 'migrate'], true)) {
        $required = ['users','dreams','dream_matches','connections','messages','notifications','moderation_flags','recurring_dream_groups','research_events','refresh_tokens','user_badges','email_queue','dream_audio','referrals','premium_orders','premium_codes','premium_redemptions','payment_events'];
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($required, $existing);
        if ($missing) throw new RuntimeException('Missing tables: ' . implode(', ', $missing));
        foreach (['dreams', 'audio'] as $folder) {
            $path = rtrim($cfg['upload_dir'], '/\\') . '/' . $folder;
            if (!is_dir($path) && !mkdir($path, 0755, true)) throw new RuntimeException("Cannot create uploads/$folder");
            if (!is_writable($path)) throw new RuntimeException("uploads/$folder must be writable by PHP");
        }
        if (!is_file($root . '/.htaccess')) throw new RuntimeException('The release .htaccess is missing.');
        echo 'OK: PHP ' . PHP_VERSION . ', required extensions, database connection, ' . count($required) . " tables, upload permissions, and configuration.\n";
    }
} catch (Throwable $e) {
    // Keep credentials and raw connection diagnostics out of CLI logs.
    fwrite(STDERR, $e instanceof PDOException ? "Database operation failed. Check credentials, database privileges and the schema.\n" : $e->getMessage() . "\n");
    exit(1);
}
