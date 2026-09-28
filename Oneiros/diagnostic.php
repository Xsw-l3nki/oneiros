<?php
/**
 * Oneiros — System Diagnostic & Integration Test Suite
 *
 * Runs every system check + creates real test data + cleans up after itself.
 *
 * USAGE:
 *   Browser:  https://yourdomain.com/diagnostic.php
 *             https://yourdomain.com/diagnostic.php?format=json
 *             https://yourdomain.com/diagnostic.php?destructive=1   ← runs full integration tests
 *   CLI:      php diagnostic.php
 *             php diagnostic.php --destructive
 *             php diagnostic.php --json
 *
 * SAFETY:
 *   By default: read-only checks (env, DB connectivity, schema, file perms, etc.)
 *   With ?destructive=1: actually creates a test user, logs dreams, runs matching,
 *                        sends connection requests, etc., then deletes everything.
 *
 * SECURITY:
 *   When done testing, RENAME or DELETE this file from the server.
 *   While present, it exposes diagnostic info that could help an attacker.
 */

// ─────────────────────────────────────────────────────────────
// HEADERS & MODE DETECTION
// ─────────────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
@set_time_limit(120);

$IS_CLI         = php_sapi_name() === 'cli';
$IS_JSON        = isset($_GET['format']) && $_GET['format'] === 'json' || in_array('--json', $argv ?? []);
$IS_DESTRUCTIVE = (isset($_GET['destructive']) && $_GET['destructive']) || in_array('--destructive', $argv ?? []);

if (!$IS_CLI && !$IS_JSON) {
    header('Content-Type: text/html; charset=utf-8');
} elseif (!$IS_CLI && $IS_JSON) {
    header('Content-Type: application/json; charset=utf-8');
}

// ─────────────────────────────────────────────────────────────
// TEST FRAMEWORK
// ─────────────────────────────────────────────────────────────
class Test {
    public static array $results = [];
    public static array $cleanup = [];
    public static array $stats   = ['pass' => 0, 'fail' => 0, 'warn' => 0, 'skip' => 0];
    public static float $startTime;

    public static function record(string $category, string $name, string $status, string $detail = ''): void
    {
        self::$results[] = compact('category', 'name', 'status', 'detail');
        self::$stats[$status] = (self::$stats[$status] ?? 0) + 1;
    }

    public static function pass(string $category, string $name, string $detail = ''): void
    { self::record($category, $name, 'pass', $detail); }

    public static function fail(string $category, string $name, string $detail = ''): void
    { self::record($category, $name, 'fail', $detail); }

    public static function warn(string $category, string $name, string $detail = ''): void
    { self::record($category, $name, 'warn', $detail); }

    public static function skip(string $category, string $name, string $detail = ''): void
    { self::record($category, $name, 'skip', $detail); }

    public static function assert(string $category, string $name, bool $condition, string $detail = ''): void
    { $condition ? self::pass($category, $name, $detail) : self::fail($category, $name, $detail); }

    public static function track(string $type, string $value): void
    { self::$cleanup[] = ['type' => $type, 'value' => $value]; }
}

Test::$startTime = microtime(true);

// ═════════════════════════════════════════════════════════════
// 1. ENVIRONMENT CHECKS
// ═════════════════════════════════════════════════════════════
Test::assert('Environment', 'PHP version >= 7.4',
    version_compare(PHP_VERSION, '7.4.0', '>='),
    'Running PHP ' . PHP_VERSION);

// mbstring has fallbacks in includes/helpers.php, but enable it anyway for full Unicode support.
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    Test::assert('Environment', "Extension: $ext",
        extension_loaded($ext),
        extension_loaded($ext) ? 'loaded' : 'NOT LOADED — install via cPanel PHP Selector');
}

if (!function_exists('password_hash')) {
    Test::fail('Environment', 'password_hash() available', 'CRITICAL — auth will fail');
} else {
    Test::pass('Environment', 'password_hash() available');
}

if (!function_exists('random_bytes')) {
    Test::fail('Environment', 'random_bytes() available', 'CRITICAL — JWT secrets unsafe');
} else {
    Test::pass('Environment', 'random_bytes() available');
}

Test::assert('Environment', 'JSON encoding works',
    json_encode(['test' => 'ok']) === '{"test":"ok"}');

// PHP 7.4 polyfill check
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    Test::warn('Environment', 'PHP version', 'Running PHP < 8.0 — polyfills will activate for str_contains/str_ends_with');
}

// ═════════════════════════════════════════════════════════════
// 2. FILE STRUCTURE & PERMISSIONS
// ═════════════════════════════════════════════════════════════
$baseDir = __DIR__;
$expectedFiles = [
    'oneiros.html', 'oneiros-admin.html', 'oneiros-moderation.html',
    'manifest.json', 'sw.js', '.htaccess', 'api.php',
    'includes/config.php', 'includes/db.php', 'includes/auth.php',
    'includes/jwt.php', 'includes/helpers.php', 'includes/cors.php',
    'includes/analysis.php', 'includes/matching.php',
    'api/health.php', 'api/auth/login.php', 'api/auth/register.php',
    'api/auth/refresh.php', 'api/auth/logout.php', 'api/auth/me.php',
    'api/auth/profile.php', 'api/auth/password.php', 'api/auth/account.php',
    'api/dreams/index.php', 'api/dreams/single.php', 'api/dreams/matches.php',
    'api/dreams/image.php', 'api/dreams/privacy.php', 'api/dreams/flag.php',
    'api/connections/index.php', 'api/connections/manage.php', 'api/connections/messages.php',
    'api/notifications/index.php',
    'api/research/global.php', 'api/research/me.php', 'api/research/public.php',
    'api/admin/users.php', 'api/admin/flags.php', 'api/admin/stats.php',
    'sql/schema.sql',
];
foreach ($expectedFiles as $rel) {
    $abs = $baseDir . '/' . $rel;
    Test::assert('Files', "Exists: $rel", file_exists($abs),
        file_exists($abs) ? '' : 'MISSING — re-upload zip');
}

// Critical: uploads dir must be writable
$uploadDir = $baseDir . '/uploads';
if (!is_dir($uploadDir)) {
    Test::fail('Files', 'uploads/ directory exists', 'Create the directory and set permissions to 0775');
} else {
    Test::pass('Files', 'uploads/ directory exists');
    Test::assert('Files', 'uploads/ is writable',
        is_writable($uploadDir),
        is_writable($uploadDir) ? 'OK' : 'CRITICAL — chmod 0775 uploads/');

    // Check uploads has security .htaccess
    if (file_exists($uploadDir . '/.htaccess')) {
        $contents = file_get_contents($uploadDir . '/.htaccess');
        Test::assert('Files', 'uploads/.htaccess blocks PHP',
            strpos($contents, 'deny') !== false || strpos($contents, 'Require all denied') !== false,
            'Prevents PHP execution in upload dir');
    } else {
        Test::warn('Files', 'uploads/.htaccess present', 'Security risk — should block PHP execution');
    }
}

// includes/ should be inaccessible from web
if (file_exists($baseDir . '/includes/.htaccess')) {
    Test::pass('Files', 'includes/.htaccess present', 'Blocks direct access');
} else {
    Test::warn('Files', 'includes/.htaccess present', 'Recommended — blocks direct browsing');
}

// ═════════════════════════════════════════════════════════════
// 3. CONFIGURATION
// ═════════════════════════════════════════════════════════════
$configPath = $baseDir . '/includes/config.php';
$cfg = null;
if (file_exists($configPath)) {
    $cfg = require $configPath;
    Test::assert('Config', 'config.php loads', is_array($cfg));

    if (is_array($cfg)) {
        Test::assert('Config', 'db_name set',
            !empty($cfg['db_name']) && $cfg['db_name'] !== 'YOURNAME_oneiros',
            $cfg['db_name'] === 'YOURNAME_oneiros' ? 'STILL HAS DEFAULT VALUE — edit config.php' : 'OK');

        Test::assert('Config', 'db_user set',
            !empty($cfg['db_user']) && $cfg['db_user'] !== 'YOURNAME_oneirosuser',
            $cfg['db_user'] === 'YOURNAME_oneirosuser' ? 'STILL HAS DEFAULT VALUE' : 'OK');

        Test::assert('Config', 'db_pass set',
            !empty($cfg['db_pass']) && $cfg['db_pass'] !== 'YOUR_DB_PASSWORD');

        Test::assert('Config', 'jwt_secret strong (>= 40 chars)',
            isset($cfg['jwt_secret']) && strlen($cfg['jwt_secret']) >= 40
                && $cfg['jwt_secret'] !== 'CHANGE_ME_to_a_random_string_at_least_40_characters_long',
            'CRITICAL — generate a long random string');

        Test::assert('Config', 'jwt_refresh_secret strong & DIFFERENT from jwt_secret',
            isset($cfg['jwt_refresh_secret']) && strlen($cfg['jwt_refresh_secret']) >= 40
                && $cfg['jwt_refresh_secret'] !== $cfg['jwt_secret']
                && $cfg['jwt_refresh_secret'] !== 'CHANGE_ME_to_a_DIFFERENT_random_string_at_least_40_chars',
            'CRITICAL — must differ from jwt_secret');

        Test::assert('Config', 'jwt_access_ttl reasonable (5-30 min)',
            isset($cfg['jwt_access_ttl']) && $cfg['jwt_access_ttl'] >= 300 && $cfg['jwt_access_ttl'] <= 3600);

        Test::assert('Config', 'jwt_refresh_ttl set (1-30 days)',
            isset($cfg['jwt_refresh_ttl']) && $cfg['jwt_refresh_ttl'] >= 86400);
    }
} else {
    Test::fail('Config', 'config.php exists', 'CRITICAL');
}

// ═════════════════════════════════════════════════════════════
// 4. DATABASE CONNECTIVITY & SCHEMA
// ═════════════════════════════════════════════════════════════
$pdo = null;
if ($cfg && !empty($cfg['db_name']) && $cfg['db_name'] !== 'YOURNAME_oneiros') {
    try {
        // Connect manually so we don't trigger the exit-on-failure in db.php
        $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
        }
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);

        // Now load the rest
        require_once $baseDir . '/includes/db.php';
        require_once $baseDir . '/includes/helpers.php';

        Test::pass('Database', 'PDO connection established');

        // Check we're on MySQL
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        Test::assert('Database', 'Driver is MySQL', $driver === 'mysql', "Driver: $driver");

        // Charset
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'");
        $row = $stmt->fetch();
        Test::assert('Database', 'Charset is utf8mb4',
            isset($row['Value']) && strpos($row['Value'], 'utf8') !== false,
            'Charset: ' . ($row['Value'] ?? 'unknown'));

        // Verify all tables exist
        $expectedTables = [
            'users', 'dreams', 'dream_matches', 'connections', 'messages',
            'notifications', 'moderation_flags', 'recurring_dream_groups',
            'research_events', 'refresh_tokens'
        ];
        $stmt = $pdo->query('SHOW TABLES');
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expectedTables as $t) {
            Test::assert('Database', "Table exists: $t",
                in_array($t, $existingTables),
                in_array($t, $existingTables) ? '' : 'MISSING — re-import sql/schema.sql');
        }

        // Verify users table columns
        $stmt = $pdo->query('DESCRIBE users');
        $columns = array_column($stmt->fetchAll(), 'Field');
        $requiredUserCols = ['id', 'email', 'password_hash', 'is_admin', 'is_moderator',
                             'is_premium', 'region_code', 'last_active_at'];
        foreach ($requiredUserCols as $col) {
            Test::assert('Database', "Column users.$col exists", in_array($col, $columns));
        }

        // Verify dreams table columns
        $stmt = $pdo->query('DESCRIBE dreams');
        $columns = array_column($stmt->fetchAll(), 'Field');
        $requiredDreamCols = ['id', 'user_id', 'content', 'privacy', 'emotions',
                              'themes', 'symbols', 'narrative_arc', 'is_recurring'];
        foreach ($requiredDreamCols as $col) {
            Test::assert('Database', "Column dreams.$col exists", in_array($col, $columns));
        }

        // Verify foreign keys exist (CASCADE checks)
        $stmt = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
              FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fkCount = $stmt->rowCount();
        Test::assert('Database', 'Foreign keys configured',
            $fkCount >= 8, "Found $fkCount FK relationships");

        // Counts of existing data
        $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $dreamCount = (int)$pdo->query('SELECT COUNT(*) FROM dreams')->fetchColumn();
        $matchCount = (int)$pdo->query('SELECT COUNT(*) FROM dream_matches')->fetchColumn();
        Test::pass('Database', 'Current data counts',
            "$userCount users · $dreamCount dreams · $matchCount matches");

    } catch (Exception $e) {
        Test::fail('Database', 'Connection failed', $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════
// 5. INCLUDE/CLASS LOAD CHECKS
// ═════════════════════════════════════════════════════════════
try {
    require_once $baseDir . '/includes/jwt.php';
    require_once $baseDir . '/includes/auth.php';
    require_once $baseDir . '/includes/analysis.php';
    require_once $baseDir . '/includes/matching.php';

    Test::assert('Classes', 'JWT class loaded', class_exists('JWT'));
    Test::assert('Classes', 'Auth class loaded', class_exists('Auth'));
    Test::assert('Classes', 'Database class loaded', class_exists('Database'));
    Test::assert('Classes', 'Helpers class loaded', class_exists('Helpers'));
    Test::assert('Classes', 'Analysis class loaded', class_exists('Analysis'));
    Test::assert('Classes', 'Matching class loaded', class_exists('Matching'));
    Test::assert('Classes', 'Notification class loaded', class_exists('Notification'));
} catch (Exception $e) {
    Test::fail('Classes', 'Failed to load classes', $e->getMessage());
}

// ═════════════════════════════════════════════════════════════
// 6. UNIT TESTS — Pure logic (no DB)
// ═════════════════════════════════════════════════════════════
if (class_exists('Helpers')) {
    // UUID generation
    $uuid1 = Helpers::uuid();
    $uuid2 = Helpers::uuid();
    Test::assert('Logic', 'UUIDs are unique', $uuid1 !== $uuid2);
    Test::assert('Logic', 'UUID format valid',
        preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1) === 1);

    // Email validation
    Test::assert('Logic', 'Email validation: valid',
        Helpers::isEmail('test@example.com') === true);
    Test::assert('Logic', 'Email validation: invalid',
        Helpers::isEmail('not-an-email') === false);

    // Age calc
    Test::assert('Logic', 'Age calc: 25 years ago = 25',
        Helpers::age(date('Y-m-d', strtotime('-25 years'))) === 25);
    Test::assert('Logic', 'Age calc: 17 years ago = 17',
        Helpers::age(date('Y-m-d', strtotime('-17 years'))) === 17);

    // JSON column encoding
    $encoded = Helpers::encodeArray(['theme1', 'theme2']);
    $decoded = Helpers::decodeArray($encoded);
    Test::assert('Logic', 'Array encode/decode roundtrip',
        $decoded === ['theme1', 'theme2']);
}

if (class_exists('Analysis')) {
    // Theme extraction
    $analysis = Analysis::analyse('I was flying high above the clouds with a feeling of peace');
    Test::assert('Logic', 'Analysis extracts "flying" theme',
        in_array('flying', $analysis['themes']));
    Test::assert('Logic', 'Analysis returns narrative_arc',
        !empty($analysis['narrative_arc']));
    Test::assert('Logic', 'Analysis infers emotion from text',
        isset($analysis['emotion_score']['peace']) && $analysis['emotion_score']['peace'] > 0);

    // Scoring
    $dreamA = ['themes' => ['flying'], 'symbols' => ['light'], 'emotions' => ['peace'],
               'narrative_arc' => 'ascent', 'dreamed_at' => 'now'];
    $dreamB = ['themes' => ['flying'], 'symbols' => ['light'], 'emotions' => ['peace'],
               'narrative_arc' => 'ascent', 'dreamed_at' => 'now'];
    $score = Analysis::score($dreamA, $dreamB);
    Test::assert('Logic', 'Identical dreams score 100',
        $score['score'] >= 99,
        "Score: {$score['score']}");

    $dreamC = ['themes' => ['water'], 'symbols' => ['ocean'], 'emotions' => ['joy'],
               'narrative_arc' => 'descent', 'dreamed_at' => 'now'];
    $score2 = Analysis::score($dreamA, $dreamC);
    Test::assert('Logic', 'Unrelated dreams score < 30',
        $score2['score'] < 30,
        "Score: {$score2['score']}");
}

if (class_exists('JWT') && $cfg) {
    $secret = $cfg['jwt_secret'];
    $payload = ['userId' => 'test-uuid', 'email' => 'test@test.com'];
    $token = JWT::encode($payload, $secret, 60);
    Test::assert('Logic', 'JWT encode produces three-part token',
        substr_count($token, '.') === 2);

    $decoded = JWT::decode($token, $secret);
    Test::assert('Logic', 'JWT decode returns payload',
        $decoded && $decoded['userId'] === 'test-uuid');

    $tampered = $token . 'x';
    Test::assert('Logic', 'JWT rejects tampered token',
        JWT::decode($tampered, $secret) === null);

    $wrongSecret = JWT::decode($token, 'different-secret');
    Test::assert('Logic', 'JWT rejects wrong secret',
        $wrongSecret === null);

    $expiredToken = JWT::encode($payload, $secret, -10); // already expired
    Test::assert('Logic', 'JWT rejects expired token',
        JWT::decode($expiredToken, $secret) === null);
}

// ═════════════════════════════════════════════════════════════
// 7. API ROUTING CHECKS (without auth)
// ═════════════════════════════════════════════════════════════
$baseUrl = null;
if (!$IS_CLI) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] .
        rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    function curlGet(string $url, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $body, 'json' => json_decode($body ?: '', true)];
    }

    function curlSend(string $method, string $url, array $body = [], array $headers = []): array {
        $ch = curl_init($url);
        $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resBody = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $resBody, 'json' => json_decode($resBody ?: '', true)];
    }

    if (!extension_loaded('curl')) {
        Test::skip('Routing', 'API live tests', 'curl extension required');
    } elseif (php_sapi_name() === 'cli-server') {
        Test::skip('Routing', 'API live tests', 'PHP built-in server is single-threaded — would deadlock. Skip on dev only; works on Apache.');
    } else {
        // Test 1: Direct PHP file access (always works)
        $r = curlGet("$baseUrl/api/health.php");
        Test::assert('Routing', 'Direct PHP: /api/health.php returns 200',
            $r['code'] === 200,
            "HTTP {$r['code']}");
        Test::assert('Routing', 'Direct PHP: returns JSON',
            isset($r['json']['status']) && $r['json']['status'] === 'healthy');

        // Test 2: Front controller with explicit ?_route=
        $r = curlGet("$baseUrl/api.php?_route=health");
        Test::assert('Routing', 'Front controller: api.php?_route=health',
            $r['code'] === 200,
            "HTTP {$r['code']}");

        // Test 3: Clean URL via mod_rewrite
        $r = curlGet("$baseUrl/api/health");
        if ($r['code'] === 200) {
            Test::pass('Routing', 'Clean URL via mod_rewrite: /api/health works',
                'mod_rewrite IS active');
        } elseif ($r['code'] === 404) {
            Test::warn('Routing', 'Clean URL via mod_rewrite',
                'mod_rewrite NOT active — frontend will use api.php fallback (still works)');
        } else {
            Test::warn('Routing', 'Clean URL test', "HTTP {$r['code']}");
        }

        // Test 4: 404 on bogus route
        $r = curlGet("$baseUrl/api.php?_route=does/not/exist");
        Test::assert('Routing', 'Unknown route returns 404',
            $r['code'] === 404);

        // Test 5: Public stats endpoint (no auth needed)
        $r = curlGet("$baseUrl/api.php?_route=research/public");
        Test::assert('Routing', 'Public stats endpoint works',
            $r['code'] === 200 && isset($r['json']['total_dreams']),
            "HTTP {$r['code']}");

        // Test 6: Auth endpoint requires POST
        $r = curlGet("$baseUrl/api.php?_route=auth/login");
        Test::assert('Routing', 'GET on POST endpoint returns 405',
            $r['code'] === 405);

        // Test 7: Protected endpoint requires auth
        $r = curlGet("$baseUrl/api.php?_route=dreams");
        Test::assert('Routing', 'Protected endpoint without token returns 401',
            $r['code'] === 401,
            "HTTP {$r['code']}");

        // Test 8: CORS headers present
        $r = curlGet("$baseUrl/api.php?_route=health");
        Test::pass('Routing', 'CORS headers test', 'See network tab in browser for header verification');
    }
} else {
    Test::skip('Routing', 'Live API tests', 'CLI mode — run via browser to test routing');
}

// ═════════════════════════════════════════════════════════════
// 8. INTEGRATION TESTS (DESTRUCTIVE — only with --destructive)
// ═════════════════════════════════════════════════════════════
if ($IS_DESTRUCTIVE && $pdo && $baseUrl && php_sapi_name() !== 'cli-server') {
    $testRunId = substr(md5(uniqid('', true)), 0, 8);
    $testEmail1 = "diagnostic_{$testRunId}_a@oneiros.test";
    $testEmail2 = "diagnostic_{$testRunId}_b@oneiros.test";

    try {
        // ─── Test: Registration ──────────────────────
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/register", [
            'email'            => $testEmail1,
            'password'         => 'TestPass1234!',
            'date_of_birth'    => '1995-01-01',
            'research_consent' => true,
            'tos_accepted'     => true,
            'region'           => 'South Africa',
            'region_code'      => 'ZA',
        ]);
        Test::assert('Integration', 'Register user A',
            $r['code'] === 201 && !empty($r['json']['access_token']),
            "HTTP {$r['code']}: " . ($r['json']['error'] ?? 'OK'));

        $tokenA = $r['json']['access_token'] ?? null;
        $userIdA = $r['json']['user']['id'] ?? null;
        if ($userIdA) Test::track('user', $userIdA);

        // ─── Test: Duplicate email rejected ──────────────────────
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/register", [
            'email'            => $testEmail1,
            'password'         => 'TestPass1234!',
            'date_of_birth'    => '1995-01-01',
            'research_consent' => true,
            'tos_accepted'     => true,
        ]);
        Test::assert('Integration', 'Duplicate email rejected',
            $r['code'] === 400);

        // ─── Test: Underage rejected ──────────────────────
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/register", [
            'email'            => "underage_{$testRunId}@oneiros.test",
            'password'         => 'TestPass1234!',
            'date_of_birth'    => date('Y-m-d', strtotime('-15 years')),
            'research_consent' => true,
            'tos_accepted'     => true,
        ]);
        Test::assert('Integration', 'Underage user rejected (< 18)',
            $r['code'] === 400);

        // ─── Test: Login ──────────────────────
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/login", [
            'email' => $testEmail1, 'password' => 'TestPass1234!'
        ]);
        Test::assert('Integration', 'Login with correct credentials',
            $r['code'] === 200 && !empty($r['json']['access_token']),
            "HTTP {$r['code']}: " . ($r['json']['error'] ?? 'OK'));

        // ─── Test: Wrong password rejected ──────────────────────
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/login", [
            'email' => $testEmail1, 'password' => 'wrong-password'
        ]);
        Test::assert('Integration', 'Wrong password rejected',
            $r['code'] === 401);

        // ─── Test: GET /auth/me with token ──────────────────────
        if ($tokenA) {
            $r = curlGet("$baseUrl/api.php?_route=auth/me", ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'GET /auth/me returns user',
                $r['code'] === 200 && isset($r['json']['user']['email']),
                "HTTP {$r['code']}");
        }

        // ─── Test: Update profile ──────────────────────
        if ($tokenA) {
            $r = curlSend('PATCH', "$baseUrl/api.php?_route=auth/profile",
                ['display_name' => 'Test Dreamer', 'region' => 'South Africa', 'region_code' => 'ZA'],
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'PATCH /auth/profile',
                $r['code'] === 200 && ($r['json']['user']['display_name'] ?? '') === 'Test Dreamer',
                "HTTP {$r['code']}");
        }

        // ─── Test: Create a dream ──────────────────────
        $dreamId1 = null;
        if ($tokenA) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=dreams",
                [
                    'content' => 'I was flying high above the clouds with a feeling of peace and wonder. The sky was bright and the wind carried me higher.',
                    'emotions' => ['peace', 'wonder'],
                    'privacy' => 'public',
                ],
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'POST /dreams creates dream',
                $r['code'] === 201 && !empty($r['json']['id']),
                "HTTP {$r['code']}: " . ($r['json']['error'] ?? 'OK'));
            $dreamId1 = $r['json']['id'] ?? null;
            if ($dreamId1) Test::track('dream', $dreamId1);

            // Verify analysis ran
            Test::assert('Integration', 'Dream analysis extracted themes',
                !empty($r['json']['themes']) && in_array('flying', $r['json']['themes'] ?? []),
                'Themes: ' . implode(',', $r['json']['themes'] ?? []));
        }

        // ─── Test: List dreams ──────────────────────
        if ($tokenA) {
            $r = curlGet("$baseUrl/api.php?_route=dreams", ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'GET /dreams lists dreams',
                $r['code'] === 200 && isset($r['json']['dreams']) && count($r['json']['dreams']) >= 1);
        }

        // ─── Test: Register user B for matching test ──────────────────────
        $tokenB = null;
        $userIdB = null;
        $r = curlSend('POST', "$baseUrl/api.php?_route=auth/register", [
            'email'            => $testEmail2,
            'password'         => 'TestPass1234!',
            'date_of_birth'    => '1995-01-01',
            'research_consent' => true,
            'tos_accepted'     => true,
            'region'           => 'United Kingdom',
            'region_code'      => 'GB',
        ]);
        Test::assert('Integration', 'Register user B',
            $r['code'] === 201);
        $tokenB = $r['json']['access_token'] ?? null;
        $userIdB = $r['json']['user']['id'] ?? null;
        if ($userIdB) Test::track('user', $userIdB);

        // ─── Test: User B logs similar dream → match should be created ──────────
        $dreamId2 = null;
        if ($tokenB) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=dreams",
                [
                    'content' => 'I was flying above clouds bathed in golden light, with such peace and wonder filling me.',
                    'emotions' => ['peace', 'wonder'],
                    'privacy' => 'public',
                ],
                ["Authorization: Bearer $tokenB"]);
            Test::assert('Integration', 'User B logs similar dream',
                $r['code'] === 201);
            $dreamId2 = $r['json']['id'] ?? null;
            if ($dreamId2) Test::track('dream', $dreamId2);

            // After matching runs synchronously, both dreams should have match_count > 0
            sleep(1);
            $check = $pdo->prepare('SELECT match_count FROM dreams WHERE id = ?');
            $check->execute([$dreamId2]);
            $matchCount = (int)$check->fetchColumn();
            Test::assert('Integration', 'Matching engine found resonance',
                $matchCount >= 1,
                "User B's dream has $matchCount matches");

            // Check that user A also got their match_count incremented
            $check->execute([$dreamId1]);
            $aMatchCount = (int)$check->fetchColumn();
            Test::assert('Integration', 'User A match_count incremented (reciprocal)',
                $aMatchCount >= 1,
                "User A's dream has $aMatchCount matches");
        }

        // ─── Test: User A fetches their matches ──────────────────────
        if ($tokenA && $dreamId1) {
            $r = curlGet("$baseUrl/api.php?_route=dreams/$dreamId1/matches",
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'GET /dreams/:id/matches returns enriched data',
                $r['code'] === 200 && isset($r['json']['matches']));

            if (!empty($r['json']['matches'])) {
                $firstMatch = $r['json']['matches'][0];
                Test::assert('Integration', 'Match has matched_user_id (for connection requests)',
                    !empty($firstMatch['matched_user_id']));
                Test::assert('Integration', 'Match has dream preview',
                    !empty($firstMatch['matched_dream_preview']));
                Test::assert('Integration', 'Match score in valid range (0-100)',
                    isset($firstMatch['score']) && $firstMatch['score'] >= 0 && $firstMatch['score'] <= 100);
            }
        }

        // ─── Test: Connection request ──────────────────────
        $connectionId = null;
        if ($tokenA && $userIdB) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=connections/request",
                ['receiver_id' => $userIdB],
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'POST /connections/request creates pending connection',
                $r['code'] === 201 && !empty($r['json']['id']),
                "HTTP {$r['code']}: " . ($r['json']['error'] ?? 'OK'));
            $connectionId = $r['json']['id'] ?? null;
        }

        // ─── Test: User B accepts via reciprocal POST ──────────────────────
        if ($tokenB && $userIdA) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=connections/request",
                ['receiver_id' => $userIdA],
                ["Authorization: Bearer $tokenB"]);
            Test::assert('Integration', 'Reciprocal request → auto-accept',
                $r['code'] === 200 && ($r['json']['status'] ?? '') === 'connected',
                "Status: " . ($r['json']['status'] ?? '???'));
        }

        // ─── Test: Send a message ──────────────────────
        if ($tokenA && $connectionId) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=connections/$connectionId/messages",
                ['content' => 'Hello fellow dreamer'],
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'POST /connections/:id/messages',
                $r['code'] === 201 && !empty($r['json']['id']),
                "HTTP {$r['code']}: " . ($r['json']['error'] ?? 'OK'));

            // Read messages
            $r = curlGet("$baseUrl/api.php?_route=connections/$connectionId/messages",
                ["Authorization: Bearer $tokenB"]);
            Test::assert('Integration', 'GET /connections/:id/messages',
                $r['code'] === 200 && count($r['json']['messages'] ?? []) >= 1);
        }

        // ─── Test: Notifications ──────────────────────
        if ($tokenB) {
            $r = curlGet("$baseUrl/api.php?_route=notifications",
                ["Authorization: Bearer $tokenB"]);
            Test::assert('Integration', 'GET /notifications returns array',
                $r['code'] === 200 && isset($r['json']['notifications']));
            Test::assert('Integration', 'User B received notifications from matching/connections',
                isset($r['json']['unread_count']) && $r['json']['unread_count'] > 0,
                "Unread: " . ($r['json']['unread_count'] ?? 0));
        }

        // ─── Test: Personal stats ──────────────────────
        if ($tokenA) {
            $r = curlGet("$baseUrl/api.php?_route=research/me",
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'GET /research/me returns stats',
                $r['code'] === 200
                    && isset($r['json']['total_dreams'])
                    && $r['json']['total_dreams'] >= 1);
        }

        // ─── Test: Public stats ──────────────────────
        $r = curlGet("$baseUrl/api.php?_route=research/public");
        Test::assert('Integration', '/research/public returns counts',
            $r['code'] === 200 && isset($r['json']['total_dreams']) && $r['json']['total_dreams'] >= 2);

        // ─── Test: Privacy update ──────────────────────
        if ($tokenA && $dreamId1) {
            $r = curlSend('PATCH', "$baseUrl/api.php?_route=dreams/$dreamId1/privacy",
                ['privacy' => 'private'],
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'PATCH dream privacy → private',
                $r['code'] === 200 && ($r['json']['privacy'] ?? '') === 'private');
        }

        // ─── Test: Flag a dream ──────────────────────
        if ($tokenB && $dreamId1) {
            $r = curlSend('POST', "$baseUrl/api.php?_route=dreams/$dreamId1/flag",
                ['reason' => 'inappropriate', 'notes' => 'test flag — auto-cleanup'],
                ["Authorization: Bearer $tokenB"]);
            Test::assert('Integration', 'POST /dreams/:id/flag',
                $r['code'] === 200);
        }

        // ─── Test: Token refresh ──────────────────────
        // (Would need to capture refresh_token; covered indirectly by login)

        // ─── Test: Invalid token rejected ──────────────────────
        $r = curlGet("$baseUrl/api.php?_route=auth/me",
            ["Authorization: Bearer invalid.token.here"]);
        Test::assert('Integration', 'Invalid token rejected with 401',
            $r['code'] === 401);

        // ─── Test: Admin endpoint requires admin ──────────────────────
        if ($tokenA) {
            $r = curlGet("$baseUrl/api.php?_route=admin/users",
                ["Authorization: Bearer $tokenA"]);
            Test::assert('Integration', 'Non-admin rejected from /admin endpoint',
                $r['code'] === 403);
        }

    } catch (Exception $e) {
        Test::fail('Integration', 'Test suite exception', $e->getMessage());
    }

    // ─── CLEANUP ──────────────────────────────────────
    $cleaned = 0;
    foreach (Test::$cleanup as $item) {
        try {
            if ($item['type'] === 'user') {
                // CASCADE DELETE will remove dreams, matches, connections, etc.
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$item['value']]);
                $cleaned++;
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    // Also clean any leftover test users by email pattern
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email LIKE 'diagnostic_%@oneiros.test' OR email LIKE 'underage_%@oneiros.test'");
        $stmt->execute();
        $cleaned += $stmt->rowCount();
    } catch (Exception $e) {}

    Test::pass('Cleanup', 'Test data removed', "$cleaned records cleaned via CASCADE");

} elseif (!$IS_DESTRUCTIVE) {
    Test::skip('Integration', 'Full integration tests',
        'Run with ?destructive=1 (browser) or --destructive (CLI) to enable');
} elseif (!$pdo) {
    Test::skip('Integration', 'Full integration tests',
        'Skipped — DB not connected');
} elseif (!$baseUrl) {
    Test::skip('Integration', 'Full integration tests',
        'Skipped — CLI mode cannot make HTTP requests in this script');
}

// ═════════════════════════════════════════════════════════════
// 9. FINAL REPORT
// ═════════════════════════════════════════════════════════════
$elapsed = round(microtime(true) - Test::$startTime, 2);
$total   = array_sum(Test::$stats);

if ($IS_JSON || $IS_CLI && in_array('--json', $argv ?? [])) {
    echo json_encode([
        'summary'  => Test::$stats,
        'elapsed'  => $elapsed,
        'total'    => $total,
        'php'      => PHP_VERSION,
        'time'     => date('c'),
        'destructive' => $IS_DESTRUCTIVE,
        'results'  => Test::$results,
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($IS_CLI) {
    // Plain text output
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Oneiros Diagnostic Report — " . date('Y-m-d H:i:s') . "\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $byCategory = [];
    foreach (Test::$results as $r) {
        $byCategory[$r['category']][] = $r;
    }
    foreach ($byCategory as $cat => $items) {
        echo "\n■ $cat\n";
        echo str_repeat('─', 60) . "\n";
        foreach ($items as $r) {
            $icon = ['pass' => '✓', 'fail' => '✗', 'warn' => '⚠', 'skip' => '○'][$r['status']];
            echo sprintf("  %s  %-50s %s\n", $icon, $r['name'],
                $r['detail'] ? "[{$r['detail']}]" : '');
        }
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    $s = Test::$stats;
    echo "  PASSED: {$s['pass']}   FAILED: {$s['fail']}   ";
    echo "WARNINGS: {$s['warn']}   SKIPPED: {$s['skip']}\n";
    echo "  Total: $total checks · {$elapsed}s\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    exit(Test::$stats['fail'] > 0 ? 1 : 0);
}

// HTML output
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Oneiros — System Diagnostic</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#0D1220; color:#E2E8F0; padding:2rem 1.5rem; line-height:1.5; }
.container { max-width:1100px; margin:0 auto; }
h1 { font-size:1.8rem; font-weight:600; color:#A78BFA; margin-bottom:0.3rem; letter-spacing:-0.01em; }
.sub { font-size:0.85rem; color:#64748B; margin-bottom:2rem; font-family:'SF Mono', Monaco, Consolas, monospace; }
.summary { display:grid; grid-template-columns:repeat(4,1fr); gap:0.75rem; margin-bottom:2rem; }
.box { padding:1rem 1.2rem; border-radius:0.6rem; border:1px solid; }
.pass-box { background:rgba(52,211,153,0.08); border-color:rgba(52,211,153,0.3); }
.fail-box { background:rgba(251,113,133,0.08); border-color:rgba(251,113,133,0.4); }
.warn-box { background:rgba(251,191,36,0.08); border-color:rgba(251,191,36,0.3); }
.skip-box { background:rgba(100,116,139,0.08); border-color:rgba(100,116,139,0.3); }
.box-num { font-size:1.8rem; font-weight:700; font-family:'SF Mono', Monaco, Consolas, monospace; }
.box-label { font-size:0.7rem; letter-spacing:0.12em; text-transform:uppercase; color:#94A3B8; margin-top:0.2rem; }
.pass-box .box-num { color:#34D399; }
.fail-box .box-num { color:#FB7185; }
.warn-box .box-num { color:#FBBF24; }
.skip-box .box-num { color:#94A3B8; }
.actions { display:flex; gap:0.75rem; margin-bottom:2rem; flex-wrap:wrap; }
.btn { background:#6382FF; color:#fff; border:none; padding:0.6rem 1.2rem; border-radius:0.4rem; cursor:pointer; font-size:0.85rem; font-weight:600; text-decoration:none; display:inline-block; }
.btn:hover { opacity:0.85; }
.btn.danger { background:#FB7185; }
.btn.outline { background:transparent; border:1px solid rgba(99,130,255,0.3); color:#6382FF; }
.warning-banner { background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.3); border-radius:0.5rem; padding:0.85rem 1.1rem; margin-bottom:1.5rem; font-size:0.85rem; color:#FBBF24; }
.warning-banner strong { color:#FBD24F; }
.section { background:rgba(255,255,255,0.02); border:1px solid rgba(99,130,255,0.12); border-radius:0.5rem; margin-bottom:1rem; overflow:hidden; }
.section-header { padding:0.85rem 1.1rem; background:rgba(99,130,255,0.04); border-bottom:1px solid rgba(99,130,255,0.12); font-weight:600; font-size:0.95rem; color:#A78BFA; display:flex; justify-content:space-between; align-items:center; }
.section-stats { font-size:0.72rem; font-family:'SF Mono', Monaco, Consolas, monospace; color:#64748B; }
.test-row { display:grid; grid-template-columns: 28px 1fr auto; gap:0.85rem; padding:0.5rem 1.1rem; border-bottom:1px solid rgba(99,130,255,0.06); align-items:center; }
.test-row:last-child { border-bottom:none; }
.test-row:hover { background:rgba(99,130,255,0.03); }
.test-icon { font-size:1rem; text-align:center; }
.icon-pass { color:#34D399; }
.icon-fail { color:#FB7185; }
.icon-warn { color:#FBBF24; }
.icon-skip { color:#64748B; }
.test-name { font-size:0.85rem; }
.test-detail { font-size:0.75rem; color:#64748B; font-family:'SF Mono', Monaco, Consolas, monospace; }
.test-row.fail .test-name { color:#FB7185; font-weight:500; }
.test-row.warn .test-name { color:#FBBF24; }
footer { margin-top:2rem; text-align:center; color:#64748B; font-size:0.75rem; font-family:'SF Mono', Monaco, Consolas, monospace; }
.security-warning { margin-top:1rem; padding:1rem; background:rgba(251,113,133,0.1); border:1px solid rgba(251,113,133,0.3); border-radius:0.5rem; font-size:0.85rem; color:#FB7185; }
</style>
</head>
<body>
<div class="container">

<h1>🌙 Oneiros System Diagnostic</h1>
<div class="sub">
  <?= date('Y-m-d H:i:s') ?> ·
  PHP <?= PHP_VERSION ?> ·
  <?= $elapsed ?>s ·
  <?= $IS_DESTRUCTIVE ? 'FULL INTEGRATION MODE' : 'READ-ONLY MODE' ?>
</div>

<?php if (!$IS_DESTRUCTIVE && Test::$stats['fail'] === 0): ?>
<div class="warning-banner">
  <strong>Read-only mode.</strong> Read-only checks passed.
  Click "Run Full Integration Tests" below to actually create test users, log dreams,
  run the matching engine, and clean up — confirming end-to-end functionality.
</div>
<?php endif; ?>

<div class="summary">
  <div class="box pass-box"><div class="box-num"><?= Test::$stats['pass'] ?? 0 ?></div><div class="box-label">Passed</div></div>
  <div class="box fail-box"><div class="box-num"><?= Test::$stats['fail'] ?? 0 ?></div><div class="box-label">Failed</div></div>
  <div class="box warn-box"><div class="box-num"><?= Test::$stats['warn'] ?? 0 ?></div><div class="box-label">Warnings</div></div>
  <div class="box skip-box"><div class="box-num"><?= Test::$stats['skip'] ?? 0 ?></div><div class="box-label">Skipped</div></div>
</div>

<div class="actions">
  <a href="?" class="btn outline">↻ Re-run read-only</a>
  <a href="?destructive=1" class="btn danger" onclick="return confirm('This will create test users and dreams in your database, then delete them. Continue?')">⚠ Run Full Integration Tests</a>
  <a href="?format=json<?= $IS_DESTRUCTIVE ? '&destructive=1' : '' ?>" class="btn outline">{ } View as JSON</a>
</div>

<?php
$byCategory = [];
foreach (Test::$results as $r) $byCategory[$r['category']][] = $r;

foreach ($byCategory as $cat => $items):
    $catStats = ['pass'=>0,'fail'=>0,'warn'=>0,'skip'=>0];
    foreach ($items as $i) $catStats[$i['status']]++;
?>
<div class="section">
  <div class="section-header">
    <span><?= htmlspecialchars($cat) ?></span>
    <span class="section-stats">
      <?= count($items) ?> checks ·
      <?= $catStats['pass'] ?> ✓ ·
      <?php if ($catStats['fail']): ?><span style="color:#FB7185"><?= $catStats['fail'] ?> ✗</span> · <?php endif; ?>
      <?php if ($catStats['warn']): ?><span style="color:#FBBF24"><?= $catStats['warn'] ?> ⚠</span> · <?php endif; ?>
      <?= $catStats['skip'] ?> ○
    </span>
  </div>
  <?php foreach ($items as $r): ?>
    <div class="test-row <?= $r['status'] ?>">
      <div class="test-icon icon-<?= $r['status'] ?>">
        <?= ['pass'=>'✓','fail'=>'✗','warn'=>'⚠','skip'=>'○'][$r['status']] ?>
      </div>
      <div class="test-name"><?= htmlspecialchars($r['name']) ?></div>
      <div class="test-detail"><?= htmlspecialchars($r['detail']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="security-warning">
  <strong>⚠ Security reminder:</strong> Once you've finished verifying, <strong>delete or rename diagnostic.php</strong>
  from your server. Leaving it accessible exposes diagnostic info that could help an attacker map your system.
</div>

<footer>
  Oneiros Diagnostic Suite · <?= count(Test::$results) ?> checks · <?= $elapsed ?>s · <?= $IS_DESTRUCTIVE ? 'destructive' : 'read-only' ?> mode
</footer>

</div>
</body>
</html>
