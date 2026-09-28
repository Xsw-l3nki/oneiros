<?php
/**
 * Oneiros — Settings: the single source of runtime configuration.
 *
 * Layers, lowest to highest priority:
 *   1. includes/config.example.php   shipped defaults (every key has one)
 *   2. includes/config.php           server file (bootstrap: database + signing secrets)
 *   3. includes/config.local.php     local development only
 *   4. app_settings table            values saved from the Console
 *   5. ONEIROS_* environment vars     locks a value; the Console shows it as locked
 *
 * Only keys listed in Settings::definitions() can be saved from the Console. Database
 * credentials and signing secrets are bootstrap values: they must exist before the
 * database can be reached, so they stay in config.php.
 * Secret values are encrypted at rest (see Settings::encrypt) and never sent to a browser.
 */
require_once __DIR__ . '/db.php';

class Settings
{
    public const TIERS = ['operations', 'core', 'financial'];
    private const ENV_KEYS = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'jwt_secret',
        'jwt_refresh_secret', 'frontend_url', 'mail_from', 'mail_enabled', 'image_api_key', 'image_model', 'debug',
        'payfast_merchant_id', 'payfast_merchant_key', 'payfast_passphrase', 'payfast_sandbox', 'paystack_secret_key',
        'environment', 'maintenance_mode'];
    private const ENV_BOOLS = ['debug', 'mail_enabled', 'payfast_sandbox', 'maintenance_mode'];

    private static ?array $base = null;
    private static ?array $config = null;
    private static ?array $stored = null;
    private static ?string $loadError = null;

    /**
     * Registry of every Console-editable setting. tier decides who may change it:
     * operations = moderators and admins (running the site, incl. non-financial feature switches),
     * core = admins, financial = admins only and hidden from moderators. See RESUME-HERE/RULES.md R6.
     */
    public static function definitions(): array
    {
        return [
            // ─── Operations: moderators may change these ───
            'maintenance_mode' => ['group' => 'Operations', 'tier' => 'operations', 'type' => 'bool', 'label' => 'Maintenance mode',
                'help' => 'Dreamers see the message below and cannot use the app. Staff can still sign in and use the Console.'],
            'maintenance_message' => ['group' => 'Operations', 'tier' => 'operations', 'type' => 'text', 'max' => 500, 'label' => 'Maintenance message'],
            'feature_registrations' => ['group' => 'Operations', 'tier' => 'operations', 'type' => 'bool', 'label' => 'New sign-ups open',
                'help' => 'Turn off to pause new accounts (for example during a spam wave). Existing dreamers are unaffected.'],
            'registrations_closed_message' => ['group' => 'Operations', 'tier' => 'operations', 'type' => 'text', 'max' => 300, 'label' => 'Message when sign-ups are closed'],

            // ─── Core ───
            'app_name' => ['group' => 'Site', 'tier' => 'core', 'type' => 'string', 'max' => 60, 'label' => 'Site name'],
            'frontend_url' => ['group' => 'Site', 'tier' => 'core', 'type' => 'url', 'label' => 'Site address',
                'help' => 'Full address including any subfolder, no final slash. Used for links in emails, payments and CORS. A wrong value breaks sign-in from the app.'],
            'environment' => ['group' => 'Site', 'tier' => 'core', 'type' => 'select', 'options' => ['production', 'staging', 'development'], 'label' => 'Environment'],
            'debug' => ['group' => 'Site', 'tier' => 'core', 'type' => 'bool', 'label' => 'Debug details in errors',
                'help' => 'Shows internal error details in API responses. Keep off on the live site.'],

            'feature_connections' => ['group' => 'Features', 'tier' => 'operations', 'type' => 'bool', 'label' => 'Connections and messages',
                'help' => 'Off: dreamers cannot send new connection requests or messages. Existing conversations stay readable.'],
            'feature_image_upload' => ['group' => 'Features', 'tier' => 'operations', 'type' => 'bool', 'label' => 'Dream image uploads'],
            'feature_audio_upload' => ['group' => 'Features', 'tier' => 'operations', 'type' => 'bool', 'label' => 'Voice notes'],
            'feature_public_research' => ['group' => 'Features', 'tier' => 'operations', 'type' => 'bool', 'label' => 'Public research pages and map'],

            'min_age' => ['group' => 'Accounts and security', 'tier' => 'core', 'type' => 'int', 'min' => 18, 'max' => 99, 'label' => 'Minimum age to join',
                'help' => 'Oneiros is an adult service; this cannot be set below 18.'],
            'max_file_size' => ['group' => 'Accounts and security', 'tier' => 'core', 'type' => 'megabytes', 'min' => 1048576, 'max' => 52428800, 'label' => 'Largest upload (MB)'],
            'jwt_access_ttl' => ['group' => 'Accounts and security', 'tier' => 'core', 'type' => 'int', 'min' => 300, 'max' => 86400, 'label' => 'Access token lifetime (seconds)'],
            'jwt_refresh_ttl' => ['group' => 'Accounts and security', 'tier' => 'core', 'type' => 'int', 'min' => 86400, 'max' => 31536000, 'label' => 'Stay-signed-in lifetime (seconds)'],

            'mail_enabled' => ['group' => 'Email', 'tier' => 'core', 'type' => 'bool', 'label' => 'Send emails',
                'help' => 'Off: emails are queued but not sent. Verify your mailbox and delivery before switching on.'],
            'mail_from' => ['group' => 'Email', 'tier' => 'core', 'type' => 'email', 'label' => 'Sender address',
                'help' => 'Must be a mailbox on your own domain, or emails land in spam.'],
            'mail_from_name' => ['group' => 'Email', 'tier' => 'core', 'type' => 'string', 'max' => 60, 'label' => 'Sender name'],

            'business_name' => ['group' => 'Business details', 'tier' => 'core', 'type' => 'string', 'max' => 120, 'label' => 'Business name (receipts)'],
            'business_email' => ['group' => 'Business details', 'tier' => 'core', 'type' => 'email', 'label' => 'Business email (receipts)'],
            'business_details' => ['group' => 'Business details', 'tier' => 'core', 'type' => 'text', 'max' => 300, 'label' => 'Registration details (receipts)'],

            // ─── Financial: admins only ───
            'feature_payments' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'bool', 'label' => 'Sell Lucid passes',
                'help' => 'Off hides checkout even when gateway keys are set. Codes and gifts already issued keep working.'],
            'currency' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'select', 'options' => ['ZAR'], 'label' => 'Currency',
                'help' => 'PayFast settles in rand only.'],
            'currency_symbol' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'string', 'max' => 4, 'label' => 'Currency symbol'],
            'payfast_merchant_id' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'string', 'max' => 40, 'label' => 'PayFast merchant ID'],
            'payfast_merchant_key' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'secret', 'label' => 'PayFast merchant key'],
            'payfast_passphrase' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'secret', 'pattern' => '/^[A-Za-z0-9_.-]*$/', 'label' => 'PayFast passphrase',
                'help' => 'Letters, numbers, dots, dashes and underscores only. Must match PayFast → Settings → Security.'],
            'payfast_sandbox' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'bool', 'label' => 'PayFast sandbox (test payments)'],
            'paystack_secret_key' => ['group' => 'Payments', 'tier' => 'financial', 'type' => 'secret', 'pattern' => '/^(sk_(live|test)_[A-Za-z0-9]+)?$/', 'label' => 'Paystack secret key'],

            'plans' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'plans', 'label' => 'Lucid passes',
                'help' => 'Prices in cents (R69 = 6900, minimum 500). Removing a pass hides it; past orders are unaffected.'],
            'support_amounts' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'cents_list', 'label' => 'Support amounts (cents)'],
            'feature_codes' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'bool', 'label' => 'Promo, discount and gift codes'],
            'free_visible_matches' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'int', 'min' => 0, 'max' => 100, 'label' => 'Free: resonances shown'],
            'free_connection_requests_per_week' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'int', 'min' => 0, 'max' => 100, 'label' => 'Free: connection requests per week'],
            'referral_reward_days' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'int', 'min' => 0, 'max' => 90, 'label' => 'Invite reward (Lucid days, 0 = off)'],
            'referral_max_rewards_per_year' => ['group' => 'Lucid plans and free tier', 'tier' => 'financial', 'type' => 'int', 'min' => 0, 'max' => 365, 'label' => 'Invite rewards per dreamer per year'],

            'feature_ai_paint' => ['group' => 'AI painting', 'tier' => 'financial', 'type' => 'bool', 'label' => 'AI painting',
                'help' => 'Needs an image API key. Each painting is billed by the provider. Off: dreamers get the free in-browser Dream Canvas.'],
            'image_api_key' => ['group' => 'AI painting', 'tier' => 'financial', 'type' => 'secret', 'label' => 'Image API key'],
            'image_model' => ['group' => 'AI painting', 'tier' => 'financial', 'type' => 'string', 'max' => 60, 'label' => 'Image model'],
            'free_ai_paintings_per_day' => ['group' => 'AI painting', 'tier' => 'financial', 'type' => 'int', 'min' => 0, 'max' => 50, 'label' => 'Free: AI paintings per day'],
        ];
    }

    // ─── Loading ─────────────────────────────────────────────

    public static function defaults(): array
    {
        return require __DIR__ . '/config.example.php';
    }

    /** Files + environment only. Used to reach the database, so it never touches it. */
    public static function base(): array
    {
        if (self::$base !== null) return self::$base;
        $config = self::defaults();
        foreach (['config.php', 'config.local.php'] as $configFile) {
            if (is_file(__DIR__ . '/' . $configFile)) {
                $overrides = require __DIR__ . '/' . $configFile;
                if (is_array($overrides)) $config = array_replace($config, $overrides);
            }
        }
        $config = self::applyEnvironment($config);
        // The version belongs to the code, not to a server's copy of the example config.
        $config['app_version'] = self::defaults()['app_version'];
        return self::$base = self::normalise($config);
    }

    /** The full runtime configuration: files, then Console values, then environment locks. */
    public static function config(): array
    {
        if (self::$config !== null) return self::$config;
        $config = self::base();
        $definitions = self::definitions();
        foreach (self::stored() as $key => $row) {
            if (!isset($definitions[$key])) continue;
            $value = self::decodeStored($row);
            if ($value !== null) $config[$key] = $value;
        }
        return self::$config = self::normalise(self::applyEnvironment($config));
    }

    /** True when a feature switch is on. Unknown switches default to on. */
    public static function feature(string $name): bool
    {
        return (bool)(self::config()['feature_' . $name] ?? true);
    }

    /** Rows saved from the Console, keyed by setting. Empty when the table is missing or the database is down. */
    public static function stored(): array
    {
        if (self::$stored !== null) return self::$stored;
        self::$stored = [];
        $pdo = Database::tryPdo();
        if (!$pdo) { self::$loadError = 'database unavailable'; return self::$stored; }
        try {
            foreach ($pdo->query('SELECT setting_key, setting_value, is_encrypted, updated_by, updated_at FROM app_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                self::$stored[$row['setting_key']] = $row;
            }
        } catch (PDOException $e) {
            self::$loadError = 'app_settings table missing (run migrations)';
        }
        return self::$stored;
    }

    public static function loadError(): ?string
    {
        self::stored();
        return self::$loadError;
    }

    public static function reset(): void
    {
        self::$config = self::$stored = null;
        self::$loadError = null;
    }

    private static function applyEnvironment(array $config): array
    {
        foreach (self::ENV_KEYS as $key) {
            $value = getenv('ONEIROS_' . strtoupper($key));
            if ($value !== false) {
                $config[$key] = in_array($key, self::ENV_BOOLS, true) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value;
            }
        }
        return $config;
    }

    private static function normalise(array $config): array
    {
        $config['db_port'] = (int)$config['db_port'];
        $config['frontend_url'] = rtrim((string)$config['frontend_url'], '/');
        date_default_timezone_set('UTC');
        return $config;
    }

    private static function lockedByEnvironment(string $key): bool
    {
        return in_array($key, self::ENV_KEYS, true) && getenv('ONEIROS_' . strtoupper($key)) !== false;
    }

    // ─── Console view and saving ──────────────────────────────

    /** Which tiers a staff role may change. */
    public static function editableTiers(bool $isAdmin): array
    {
        return $isAdmin ? self::TIERS : ['operations'];
    }

    /** Settings as the Console shows them. Secrets are never included, only whether they are set. */
    public static function describe(bool $isAdmin): array
    {
        $config = self::config();
        $stored = self::stored();
        $defaults = self::defaults();
        $fileConfig = self::base();
        $editable = self::editableTiers($isAdmin);
        $out = [];
        foreach (self::definitions() as $key => $def) {
            // Moderators do not see financial settings at all.
            if (!$isAdmin && $def['tier'] === 'financial') continue;
            $locked = self::lockedByEnvironment($key);
            $source = $locked ? 'environment' : (isset($stored[$key]) ? 'console'
                : (($fileConfig[$key] ?? null) !== ($defaults[$key] ?? null) ? 'config file' : 'default'));
            $item = $def + [
                'key' => $key,
                'source' => $source,
                'editable' => !$locked && in_array($def['tier'], $editable, true),
                'updated_at' => $stored[$key]['updated_at'] ?? null,
            ];
            unset($item['pattern']);
            if ($def['type'] === 'secret') {
                $value = (string)($config[$key] ?? '');
                $item['is_set'] = $value !== '';
                $item['hint'] = strlen($value) >= 12 ? '…' . substr($value, -4) : ($value !== '' ? 'set' : '');
                $item['decrypt_failed'] = isset($stored[$key]) && self::decodeStored($stored[$key]) === null;
            } else {
                $item['value'] = $config[$key] ?? null;
                $item['default'] = $defaults[$key] ?? null;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Validate and save Console changes. $changes maps key => value; a null value clears the
     * Console value so the config file/default applies again. Returns audit entries.
     */
    public static function save(array $changes, array $actor): array
    {
        $definitions = self::definitions();
        $editable = self::editableTiers($actor['isAdmin']);
        $current = self::config();
        $rows = [];
        $audit = [];
        foreach ($changes as $key => $value) {
            $def = $definitions[$key] ?? null;
            if (!$def) throw new InvalidArgumentException("Unknown setting: $key");
            if (!in_array($def['tier'], $editable, true)) throw new DomainException("Your role cannot change “{$def['label']}”.");
            if (self::lockedByEnvironment($key)) throw new DomainException("“{$def['label']}” is locked by an environment variable on the server.");
            $clean = $value === null ? null : self::validate($key, $def, $value);
            $rows[$key] = $clean;
            $isSecret = $def['type'] === 'secret';
            $audit[] = [
                'key' => $key,
                'label' => $def['label'],
                'tier' => $def['tier'],
                'from' => $isSecret ? (($current[$key] ?? '') !== '' ? '(set)' : '(empty)') : ($current[$key] ?? null),
                'to' => $clean === null ? '(reset to file/default)' : ($isSecret ? ($clean !== '' ? '(new value)' : '(empty)') : $clean),
            ];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $key => $clean) {
                if ($clean === null) {
                    Database::query('DELETE FROM app_settings WHERE setting_key = ?', [$key]);
                    continue;
                }
                $encrypt = $definitions[$key]['type'] === 'secret' && $clean !== '';
                $stored = $encrypt ? self::encrypt(json_encode($clean)) : json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                Database::query(
                    'INSERT INTO app_settings (setting_key, setting_value, is_encrypted, updated_by, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
                    [$key, $stored, (int)$encrypt, $actor['userId']]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::reset();
        return $audit;
    }

    private static function validate(string $key, array $def, $value)
    {
        $label = $def['label'];
        switch ($def['type']) {
            case 'bool':
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) throw new InvalidArgumentException("$label must be on or off.");
                return $bool;
            case 'int':
            case 'megabytes':
                if (!is_numeric($value) || (int)$value != $value) throw new InvalidArgumentException("$label must be a whole number.");
                $int = (int)$value;
                if (isset($def['min']) && $int < $def['min']) throw new InvalidArgumentException("$label must be at least {$def['min']}.");
                if (isset($def['max']) && $int > $def['max']) throw new InvalidArgumentException("$label must be at most {$def['max']}.");
                return $int;
            case 'select':
                if (!in_array($value, $def['options'], true)) throw new InvalidArgumentException("$label must be one of: " . implode(', ', $def['options']));
                return $value;
            case 'email':
                $value = trim((string)$value);
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException("$label must be an email address.");
                return $value;
            case 'url':
                $value = rtrim(trim((string)$value), '/');
                if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value)) throw new InvalidArgumentException("$label must be a full web address starting with https://");
                return $value;
            case 'string':
            case 'text':
            case 'secret':
                if (!is_string($value)) throw new InvalidArgumentException("$label must be text.");
                $value = trim($value);
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) throw new InvalidArgumentException("$label contains control characters.");
                if ($def['type'] !== 'text' && str_contains($value, "\n")) throw new InvalidArgumentException("$label must be a single line.");
                $max = $def['max'] ?? 500;
                if (mb_strlen($value) > $max) throw new InvalidArgumentException("$label must be at most $max characters.");
                if (isset($def['pattern']) && !preg_match($def['pattern'], $value)) throw new InvalidArgumentException("$label has an invalid format.");
                return $value;
            case 'cents_list':
                if (!is_array($value) || count($value) > 10) throw new InvalidArgumentException("$label must be a list of up to 10 amounts.");
                $list = [];
                foreach ($value as $cents) {
                    if (!is_numeric($cents) || (int)$cents < 500 || (int)$cents > 10000000) throw new InvalidArgumentException("$label: each amount must be between 500 and 10000000 cents.");
                    $list[] = (int)$cents;
                }
                return array_values(array_unique($list));
            case 'plans':
                if (!is_array($value) || count($value) > 12) throw new InvalidArgumentException("$label must be a list of up to 12 passes.");
                $plans = [];
                foreach ($value as $id => $plan) {
                    if (!preg_match('/^[a-z0-9-]{2,40}$/', (string)$id)) throw new InvalidArgumentException("Pass id “{$id}” must be 2–40 lowercase letters, numbers or dashes.");
                    $name = trim((string)($plan['name'] ?? ''));
                    $days = (int)($plan['days'] ?? 0);
                    $price = (int)($plan['price_cents'] ?? 0);
                    if ($name === '' || mb_strlen($name) > 40) throw new InvalidArgumentException("Pass “{$id}” needs a name of up to 40 characters.");
                    if ($days < 1 || $days > 3650) throw new InvalidArgumentException("Pass “{$id}”: days must be 1–3650.");
                    if ($price < 500 || $price > 10000000) throw new InvalidArgumentException("Pass “{$id}”: price must be 500–10000000 cents.");
                    $plans[$id] = ['name' => $name, 'days' => $days, 'price_cents' => $price] + (!empty($plan['featured']) ? ['featured' => true] : []);
                }
                return $plans;
        }
        throw new InvalidArgumentException("$label cannot be saved.");
    }

    // ─── Encryption of secret values at rest ─────────────────

    private static function decodeStored(array $row)
    {
        $raw = $row['setting_value'];
        if ($raw === null) return null;
        if ($row['is_encrypted']) {
            $raw = self::decrypt($raw);
            if ($raw === null) return null;
        }
        return json_decode($raw, true);
    }

    /** 32-byte key: config 'settings_key' (64 hex chars) or includes/.settings-key, created on first use. */
    private static function key(bool $create): ?string
    {
        $configured = (string)(self::base()['settings_key'] ?? '');
        if (preg_match('/^[0-9a-f]{64}$/i', $configured)) return hex2bin($configured);
        $file = __DIR__ . '/.settings-key';
        if (is_file($file)) {
            $hex = trim((string)file_get_contents($file));
            if (preg_match('/^[0-9a-f]{64}$/i', $hex)) return hex2bin($hex);
        }
        if (!$create) return null;
        $hex = bin2hex(random_bytes(32));
        if (@file_put_contents($file, $hex . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Cannot create includes/.settings-key to encrypt secrets. Make includes/ writable once, or set settings_key in config.php.');
        }
        @chmod($file, 0600);
        return hex2bin($hex);
    }

    public static function keyStatus(): string
    {
        $configured = (string)(self::base()['settings_key'] ?? '');
        if (preg_match('/^[0-9a-f]{64}$/i', $configured)) return 'config.php';
        return self::key(false) ? 'key file' : 'not created yet';
    }

    private static function encrypt(string $plain): string
    {
        $key = self::key(true);
        $nonce = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) throw new RuntimeException('Encryption failed: the PHP openssl extension is required.');
        return 'v1:' . base64_encode($nonce . $tag . $cipher);
    }

    private static function decrypt(string $stored): ?string
    {
        $key = self::key(false);
        if (!$key || !str_starts_with($stored, 'v1:')) return null;
        $bin = base64_decode(substr($stored, 3), true);
        if ($bin === false || strlen($bin) < 29) return null;
        $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
        return $plain === false ? null : $plain;
    }
}
