<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/premium.php';

Helpers::only(['GET']);
$auth = Auth::require();
$user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$auth['userId']]);
Helpers::touchUser($auth['userId']);
Premium::remind($auth['userId']);
if (!$user) Helpers::respond(['error' => 'User not found'], 404);

// Update last_seen_at for online tracking (non-fatal)
try {
    Database::query('UPDATE users SET last_seen_at = NOW() WHERE id = ?', [$auth['userId']]);
} catch (Exception $e) {}

Helpers::respond(['user' => Auth::safeUser($user)]);
