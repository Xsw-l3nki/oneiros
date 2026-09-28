<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jwt.php';

Helpers::only(['POST']);
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$input = Helpers::input();
Helpers::require_fields($input, ['refresh_token']);

$refreshToken = $input['refresh_token'];
$payload = JWT::decode($refreshToken, $cfg['jwt_refresh_secret']);
if (!$payload || empty($payload['userId'])) Helpers::respond(['error' => 'Invalid or expired refresh token'], 401);

$tokenHash = JWT::hash($refreshToken);
$stored = Database::fetchOne(
    'SELECT * FROM refresh_tokens WHERE token_hash = ? AND user_id = ? AND expires_at > NOW()',
    [$tokenHash, $payload['userId']]
);

if (!$stored) {
    // Token reuse detected — invalidate all tokens for this user
    Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$payload['userId']]);
    Helpers::respond(['error' => 'Token reuse detected. Please log in again.'], 401);
}

// Delete used token (rotation)
Database::query('DELETE FROM refresh_tokens WHERE token_hash = ?', [$tokenHash]);

$user = Database::fetchOne('SELECT * FROM users WHERE id = ? AND is_active = 1', [$payload['userId']]);
if (!$user) Helpers::respond(['error' => 'Account unavailable'], 401);

$tokens = Auth::generateTokens($user);

Helpers::respond([
    'user' => Auth::safeUser($user),
    'access_token'  => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
]);
