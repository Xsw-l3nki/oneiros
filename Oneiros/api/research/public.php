<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

Helpers::only(['GET']);

try {
    $totalDreams = Database::fetchOne('SELECT COUNT(*) as c FROM dreams WHERE privacy IN ("public","research_only") AND is_removed = 0');
    $totalMatches = Database::fetchOne('SELECT COUNT(*) as c FROM dream_matches');
    $countries = Database::fetchOne('SELECT COUNT(DISTINCT u.region_code) as c FROM dreams d JOIN users u ON d.user_id = u.id WHERE d.privacy IN ("public","research_only") AND d.is_removed = 0 AND u.region_code IS NOT NULL');
    $activeNow = Database::fetchOne('SELECT COUNT(*) as c FROM users WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)');

    Helpers::respond([
        'total_dreams'        => (int)($totalDreams['c'] ?? 0),
        'total_matches'       => (int)($totalMatches['c'] ?? 0),
        'active_countries'    => (int)($countries['c'] ?? 0),
        'dreamers_active_now' => (int)($activeNow['c'] ?? 0),
    ]);
} catch (Exception $e) {
    error_log('Oneiros public stats error: ' . $e->getMessage());
    Helpers::respond([
        'total_dreams'        => 0,
        'total_matches'       => 0,
        'active_countries'    => 0,
        'dreamers_active_now' => 0,
    ]);
}
