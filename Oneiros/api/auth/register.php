<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

Helpers::only(['POST']);
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$input = Helpers::input();
Helpers::require_fields($input, ['email', 'password', 'date_of_birth', 'research_consent', 'tos_accepted']);

$email = strtolower(trim($input['email']));
if (!Helpers::isEmail($email)) Helpers::respond(['error' => 'Invalid email'], 400);
if (strlen($input['password']) < 8) Helpers::respond(['error' => 'Password must be at least 8 characters'], 400);
if (strlen($input['password']) > 72) Helpers::respond(['error' => 'Password must be no longer than 72 bytes'], 400);

if (Helpers::age($input['date_of_birth']) < $cfg['min_age']) {
    Helpers::respond(['error' => 'You must be 18 or older to join Oneiros'], 400);
}

if (!filter_var($input['research_consent'], FILTER_VALIDATE_BOOLEAN) || !filter_var($input['tos_accepted'], FILTER_VALIDATE_BOOLEAN)) {
    Helpers::respond(['error' => 'You must accept the Terms of Service and research consent to join'], 400);
}

// Check if email already exists
$existing = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
if ($existing) Helpers::respond(['error' => 'An account with this email already exists'], 400);

$userId        = Helpers::uuid();
$referralCode  = strtoupper(substr(md5($userId . time()), 0, 8));
// Invited by a friend? Both receive Lucid days once this dreamer saves a first dream.
$referrer = null;
$inviteCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($input['referral_code'] ?? '')));
if ($inviteCode !== '') {
    $referrer = Database::fetchOne('SELECT id FROM users WHERE referral_code = ? AND is_active = 1', [$inviteCode]);
}

Database::insert('users', [
    'id'               => $userId,
    'email'            => $email,
    'display_name'     => mb_substr(trim((string)($input['display_name'] ?? '')), 0, 60) ?: null,
    'password_hash'    => password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]),
    'date_of_birth'    => $input['date_of_birth'],
    'is_18_plus'       => 1,
    'research_consent' => 1,
    'tos_accepted'     => 1,
    'region'           => mb_substr(trim((string)($input['region'] ?? '')), 0, 100) ?: null,
    'region_code'      => strtoupper(substr(trim((string)($input['region_code'] ?? '')), 0, 2)) ?: null,
    'last_active_at'   => date('Y-m-d H:i:s'),
    'referred_by'      => $referrer['id'] ?? null,
]);

// Try to set referral code (column may not exist on v1.0 DBs)
try {
    Database::query(
        'UPDATE users SET referral_code = ? WHERE id = ?',
        [$referralCode, $userId]
    );
} catch (Exception $e) { /* column not yet migrated — non-fatal */ }

$user   = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
$tokens = Auth::generateTokens($user);

// Send welcome email asynchronously (queued)
try {
    Mailer::queue($email, '', 'welcome', []);
} catch (Exception $e) {
    error_log('Oneiros: welcome email queue failed: ' . $e->getMessage());
}

Helpers::respond([
    'user'          => Auth::safeUser($user),
    'access_token'  => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
], 201);
