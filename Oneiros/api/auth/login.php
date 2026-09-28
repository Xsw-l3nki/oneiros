<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['POST']);
$input = Helpers::input();
Helpers::require_fields($input, ['email', 'password']);

$email = strtolower(trim($input['email']));
$user = Database::fetchOne('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

if (!$user || !password_verify($input['password'], $user['password_hash'])) {
    Helpers::respond(['error' => 'Invalid email or password'], 401);
}

Helpers::touchUser($user['id']);
$tokens = Auth::generateTokens($user);

Helpers::respond([
    'user' => Auth::safeUser($user),
    'access_token'  => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
]);
