<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['GET']);
$user = Auth::require();

$totalDreams = Database::fetchOne(
    'SELECT COUNT(*) as c FROM dreams WHERE user_id = ? AND is_removed = 0', [$user['userId']]
);

$totalMatches = Database::fetchOne(
    'SELECT COUNT(*) as c FROM dream_matches WHERE user_a_id = ? OR user_b_id = ?',
    [$user['userId'], $user['userId']]
);

$recurring = Database::fetchOne(
    'SELECT COUNT(*) as c FROM dreams WHERE user_id = ? AND is_recurring = 1', [$user['userId']]
);

$connections = Database::fetchOne(
    'SELECT COUNT(*) as c FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = "connected"',
    [$user['userId'], $user['userId']]
);

// Top emotions
$emotionData = Database::fetchAll(
    'SELECT emotions FROM dreams WHERE user_id = ? AND is_removed = 0', [$user['userId']]
);
$emotionCount = [];
foreach ($emotionData as $row) {
    foreach (Helpers::decodeArray($row['emotions']) as $e) {
        $emotionCount[$e] = ($emotionCount[$e] ?? 0) + 1;
    }
}
arsort($emotionCount);
$topEmotions = [];
foreach (array_slice($emotionCount, 0, 5, true) as $emotion => $count) {
    $topEmotions[] = ['emotion' => $emotion, 'count' => $count];
}

Helpers::respond([
    'total_dreams'    => (int)($totalDreams['c'] ?? 0),
    'total_matches'   => (int)($totalMatches['c'] ?? 0),
    'recurring_dreams' => (int)($recurring['c'] ?? 0),
    'connections'     => (int)($connections['c'] ?? 0),
    'top_emotions'    => $topEmotions,
]);
