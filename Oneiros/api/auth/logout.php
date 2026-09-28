<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jwt.php';

Helpers::only(['POST']);
$user = Auth::require();
$input = Helpers::input();

if (!empty($input['refresh_token'])) {
    Database::query('DELETE FROM refresh_tokens WHERE token_hash = ? AND user_id = ?', [JWT::hash($input['refresh_token']), $user['userId']]);
} else {
    Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$user['userId']]);
}

Helpers::respond(['message' => 'Logged out successfully']);
