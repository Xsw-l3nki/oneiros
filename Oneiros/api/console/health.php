<?php
/**
 * /api/console/health
 *   GET                 every health check, grouped (health.view)
 *   GET  ?logs=1        newest lines of the PHP error logs (health.logs)
 *   POST ?action=migrate  bring the database schema up to date (health.migrate)
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/migrator.php';
require_once __DIR__ . '/../../includes/premium.php';

Helpers::only(['GET', 'POST']);
$root = dirname(__DIR__, 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_GET['action'] ?? '') !== 'migrate') Helpers::respond(['error' => 'Unknown action'], 400);
    $staff = Staff::require('health.migrate');
    try {
        $steps = Migrator::run(Database::pdo(), $root . '/sql/schema.sql');
    } catch (Throwable $e) {
        error_log('Oneiros migrate failed: ' . $e->getMessage());
        Helpers::respond(['error' => 'Migration failed. The error log has the details.'], 500);
    }
    Staff::audit($staff, 'health.migrate', $steps ? 'Ran database migrations: ' . implode(', ', $steps) : 'Ran database migrations (already up to date)', 'system', 'database', ['steps' => $steps]);
    Helpers::respond(['steps' => $steps]);
}

if (!empty($_GET['logs'])) {
    Staff::require('health.logs');
    $files = array_merge(glob($root . '/error_log') ?: [], glob($root . '/api/*/error_log') ?: [], glob($root . '/api/error_log') ?: []);
    $logs = [];
    foreach ($files as $file) {
        $lines = tailLines($file, 60);
        // Hide the server's home path and anything that looks like a credential.
        $lines = array_map(fn($l) => preg_replace(['#/home[0-9]?/[^/\s]+/#', "/(password|passwd|secret|key)=\\S+/i"], ['~/', '$1=***'], $l), $lines);
        $logs[] = ['file' => substr($file, strlen($root) + 1), 'size' => filesize($file), 'modified' => gmdate('c', filemtime($file)), 'lines' => $lines];
    }
    Helpers::respond(['logs' => $logs]);
}

Staff::require('health.view');
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$checks = [];
$add = function (string $group, string $label, string $status, string $detail) use (&$checks) {
    $checks[] = compact('group', 'label', 'status', 'detail');
};

// ─── Server ───
$add('Server', 'PHP version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail', 'PHP ' . PHP_VERSION . ' (8.2 or later required)');
foreach (['pdo_mysql' => 'fail', 'json' => 'fail', 'openssl' => 'fail', 'curl' => 'warn', 'mbstring' => 'warn', 'fileinfo' => 'warn'] as $ext => $severity) {
    $add('Server', "Extension: $ext", extension_loaded($ext) ? 'ok' : $severity,
        extension_loaded($ext) ? 'loaded' : 'not loaded — enable it in cPanel → Select PHP Version → Extensions');
}
$uploadDir = rtrim($cfg['upload_dir'], '/\\');
foreach (['dreams', 'audio', 'paintings'] as $folder) {
    $path = "$uploadDir/$folder";
    $ok = is_dir($path) ? is_writable($path) : is_writable($uploadDir);
    $add('Server', "Uploads: $folder", $ok ? 'ok' : 'fail', $ok ? 'writable' : "uploads/$folder is not writable by PHP");
}
$free = @disk_free_space($uploadDir);
if ($free !== false) {
    $mb = (int)($free / 1048576);
    $add('Server', 'Free disk space', $mb < 100 ? 'fail' : ($mb < 500 ? 'warn' : 'ok'), number_format($mb) . ' MB free');
}
$add('Server', 'Web protection files', is_file("$root/.htaccess") && is_file("$root/includes/.htaccess") ? 'ok' : 'fail',
    'the root and includes/ .htaccess files block access to configuration and logs');

// ─── Database ───
$started = microtime(true);
$pdo = Database::tryPdo();
if ($pdo) {
    $add('Database', 'Connection', 'ok', 'connected in ' . round((microtime(true) - $started) * 1000) . ' ms');
    $pending = Migrator::pending($pdo);
    $add('Database', 'Schema', $pending ? 'fail' : 'ok', $pending ? 'needs migrating: ' . implode(', ', $pending) : 'all ' . count(Migrator::TABLES) . ' tables and columns present');
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $add('Database', 'Server version', 'ok', (string)$version);
} else {
    $add('Database', 'Connection', 'fail', 'cannot connect — check the database details in includes/config.php');
}
$loadError = Settings::loadError();
$add('Database', 'Console settings', $loadError ? 'warn' : 'ok', $loadError ? "not loaded: $loadError" : count(Settings::stored()) . ' values saved from the Console');

// ─── Security ───
$jwtOk = strlen($cfg['jwt_secret']) >= 48 && strlen($cfg['jwt_refresh_secret']) >= 48
    && !str_contains($cfg['jwt_secret'] . $cfg['jwt_refresh_secret'], 'CHANGE_ME') && $cfg['jwt_secret'] !== $cfg['jwt_refresh_secret'];
$add('Security', 'Signing secrets', $jwtOk ? 'ok' : 'fail', $jwtOk ? 'set, strong and different from each other' : 'set two different random secrets of 48+ characters in includes/config.php');
$add('Security', 'Server config file', is_file("$root/includes/config.php") || getenv('ONEIROS_DB_NAME') !== false ? 'ok' : 'warn',
    is_file("$root/includes/config.php") ? 'includes/config.php present' : 'includes/config.php missing (using environment or defaults)');
$undecryptable = array_filter(Settings::describe(true), fn($s) => !empty($s['decrypt_failed']));
$add('Security', 'Secret storage key', $undecryptable ? 'fail' : 'ok', $undecryptable
    ? 'cannot decrypt: ' . implode(', ', array_column($undecryptable, 'label')) . '. The key was lost or changed; enter these again.'
    : 'key source: ' . Settings::keyStatus());
$add('Security', 'Site address', !filter_var($cfg['frontend_url'], FILTER_VALIDATE_URL) ? 'fail' : (str_starts_with($cfg['frontend_url'], 'https://') ? 'ok' : 'warn'),
    $cfg['frontend_url'] . (str_starts_with($cfg['frontend_url'], 'https://') ? '' : ' — use https:// on the live site'));
$add('Security', 'Debug mode', !empty($cfg['debug']) && $cfg['environment'] === 'production' ? 'warn' : 'ok',
    !empty($cfg['debug']) ? 'on: error details are shown to visitors' : 'off');

// ─── Operations ───
$add('Operations', 'Maintenance mode', $cfg['maintenance_mode'] ? 'warn' : 'ok', $cfg['maintenance_mode'] ? 'ON: dreamers cannot use the app' : 'off');
$add('Operations', 'New sign-ups', $cfg['feature_registrations'] ? 'ok' : 'warn', $cfg['feature_registrations'] ? 'open' : 'closed');
if ($pdo) {
    $flags = (int)Database::fetchOne("SELECT COUNT(*) c FROM moderation_flags WHERE status = 'pending'")['c'];
    $add('Operations', 'Moderation queue', $flags > 0 ? 'warn' : 'ok', $flags . ' report' . ($flags === 1 ? '' : 's') . ' waiting');
}

// ─── Email ───
$add('Email', 'Sending', $cfg['mail_enabled'] ? 'ok' : 'warn', $cfg['mail_enabled'] ? 'on, from ' . $cfg['mail_from'] : 'off: emails are queued but not sent');
if ($pdo) {
    $queue = Database::fetchOne("SELECT SUM(status = 'pending') pending, SUM(status = 'failed') failed,
        MIN(CASE WHEN status = 'pending' THEN created_at END) oldest FROM email_queue");
    $oldestHours = $queue['oldest'] ? (time() - strtotime($queue['oldest'] . ' UTC')) / 3600 : 0;
    $add('Email', 'Queue', $cfg['mail_enabled'] && $oldestHours > 1 ? 'warn' : 'ok', (int)$queue['pending'] . ' waiting'
        . ($oldestHours > 1 ? ', oldest ' . round($oldestHours) . ' h — is the mail cron job running?' : ''));
    $add('Email', 'Failed deliveries', (int)$queue['failed'] > 0 ? 'warn' : 'ok', (int)$queue['failed'] . ' failed after 3 attempts');
}

// ─── Payments and AI ───
$providers = Premium::providers();
$add('Payments', 'Gateways', !$cfg['feature_payments'] ? 'warn' : ($providers ? 'ok' : 'warn'),
    !$cfg['feature_payments'] ? 'sales switched off in the Console' : ($providers ? 'ready: ' . implode(', ', $providers) : 'no gateway keys set: passes cannot be bought'));
if (!empty($cfg['payfast_merchant_id'])) {
    $add('Payments', 'PayFast mode', $cfg['payfast_sandbox'] && $cfg['environment'] === 'production' ? 'warn' : 'ok', $cfg['payfast_sandbox'] ? 'SANDBOX: test payments only' : 'live');
    $add('Payments', 'PayFast passphrase', $cfg['payfast_passphrase'] !== '' ? 'ok' : 'warn', $cfg['payfast_passphrase'] !== '' ? 'set' : 'not set: payment notifications are less protected');
}
if (!empty($cfg['paystack_secret_key'])) {
    $add('Payments', 'Paystack mode', str_starts_with($cfg['paystack_secret_key'], 'sk_test') && $cfg['environment'] === 'production' ? 'warn' : 'ok',
        str_starts_with($cfg['paystack_secret_key'], 'sk_test') ? 'TEST key' : 'live key');
}
$add('AI painting', 'Provider', 'ok',
    !$cfg['feature_ai_paint'] ? 'switched off: dreamers get the in-browser Dream Canvas' : ($cfg['image_api_key'] !== '' ? 'on, model ' . $cfg['image_model'] : 'no API key: dreamers get the in-browser Dream Canvas'));

// ─── Logs ───
$logFile = "$root/error_log";
if (is_file($logFile)) {
    $recent = array_filter(tailLines($logFile, 200), fn($l) => preg_match('/PHP (Fatal|Parse)|Oneiros (fatal|uncaught|DB error)/', $l)
        && ($t = strtotime(substr($l, 1, 20))) && $t > time() - 86400);
    $add('Logs', 'Errors in the last 24 h', $recent ? 'warn' : 'ok', $recent ? count($recent) . ' serious errors — open the log viewer' : 'none');
}

$summary = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) $summary[$c['status']]++;
Helpers::respond([
    'overall' => $summary['fail'] ? 'fail' : ($summary['warn'] ? 'warn' : 'ok'),
    'summary' => $summary,
    'checks'  => $checks,
    'checked_at' => gmdate('c'),
]);

function tailLines(string $file, int $count): array
{
    $size = filesize($file);
    $handle = @fopen($file, 'rb');
    if (!$handle) return [];
    fseek($handle, max(0, $size - 65536));
    $lines = preg_split('/\r?\n/', trim((string)stream_get_contents($handle)));
    fclose($handle);
    return array_slice($lines, -$count);
}
