<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/media.php';

Helpers::only(['DELETE', 'POST']);
$user = Auth::require();

Media::purgeUser($user['userId']);
Database::query('DELETE FROM research_events WHERE created_by = ?', [$user['userId']]);
Database::delete('users', 'id', $user['userId']);
Helpers::respond(['message' => 'Account deleted']);
