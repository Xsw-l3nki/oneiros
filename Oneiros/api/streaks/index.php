<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/streaks.php';

$user   = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$sub    = $_GET['sub']  ?? 'me';
$sub2   = $_GET['sub2'] ?? '';

if ($method !== 'GET') Helpers::respond(['error' => 'Method not allowed'], 405);

// GET /streaks/me
if ($sub === 'me' || $sub === '') {
    Helpers::respond(Streak::get($user['userId']));
}

// GET /streaks/badges/me  (sub=badges, sub2=me)
if ($sub === 'badges') {
    $badges = Badge::getUserBadges($user['userId']);
    Helpers::respond(['badges' => $badges]);
}

// GET /streaks/leaderboard
if ($sub === 'leaderboard') {
    Helpers::respond(['leaderboard' => Streak::leaderboard()]);
}

Helpers::respond(['error' => 'Not found'], 404);
