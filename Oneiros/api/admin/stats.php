<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['GET']);
Auth::requireAdmin();

$totalUsers   = Database::fetchOne('SELECT COUNT(*) as c FROM users');
$totalDreams  = Database::fetchOne('SELECT COUNT(*) as c FROM dreams');
$totalMatches = Database::fetchOne('SELECT COUNT(*) as c FROM dream_matches');
$pendingFlags = Database::fetchOne('SELECT COUNT(*) as c FROM moderation_flags WHERE status = "pending"');
$premiumUsers = Database::fetchOne('SELECT COUNT(*) as c FROM users WHERE premium_until > UTC_TIMESTAMP()');
$moderators   = Database::fetchOne('SELECT COUNT(*) as c FROM users WHERE is_moderator = 1');
$arcs = Database::fetchAll('SELECT narrative_arc AS arc, COUNT(*) AS count FROM dreams WHERE is_removed = 0 AND narrative_arc IS NOT NULL GROUP BY narrative_arc ORDER BY count DESC');
$daily = Database::fetchAll('SELECT DATE(created_at) AS date, COUNT(*) AS count FROM dreams WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND is_removed = 0 GROUP BY DATE(created_at) ORDER BY date');
$recurring = Database::fetchAll('SELECT occurrence_count, core_themes, first_seen_at, last_seen_at FROM recurring_dream_groups ORDER BY last_seen_at DESC LIMIT 100');
foreach ($arcs as &$row) $row['count'] = (int)$row['count'];
unset($row);
foreach ($daily as &$row) $row['count'] = (int)$row['count'];
unset($row);
foreach ($recurring as &$row) { $row['occurrence_count'] = (int)$row['occurrence_count']; $row['core_themes'] = Helpers::decodeArray($row['core_themes']); }
unset($row);

Helpers::respond([
    'total_users'   => (int)($totalUsers['c'] ?? 0),
    'total_dreams'  => (int)($totalDreams['c'] ?? 0),
    'total_matches' => (int)($totalMatches['c'] ?? 0),
    'pending_flags' => (int)($pendingFlags['c'] ?? 0),
    'premium_users' => (int)($premiumUsers['c'] ?? 0),
    'moderators'    => (int)($moderators['c'] ?? 0),
    'narrative_arcs' => $arcs,
    'daily_volume' => $daily,
    'recurring_groups' => $recurring,
]);
