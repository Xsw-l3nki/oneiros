<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['PATCH', 'POST']);
$user = Auth::require();
$input = Helpers::input();
Helpers::require_fields($input, ['password']);

if (strlen($input['password']) < 8) {
    Helpers::respond(['error' => 'Password must be at least 8 characters'], 400);
}

$hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $user['userId']]);

// Invalidate all existing sessions
Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$user['userId']]);

Helpers::respond(['message' => 'Password updated successfully']);
