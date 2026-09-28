<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['GET']);
Auth::require();

// Total dreams
$totalDreams = Database::fetchOne(
    'SELECT COUNT(*) as c FROM dreams WHERE privacy IN ("public","research_only") AND is_removed = 0'
);

// Active countries
$regions = Database::fetchAll(
    'SELECT DISTINCT u.region, u.region_code, COUNT(d.id) as count
       FROM dreams d JOIN users u ON d.user_id = u.id
      WHERE d.privacy IN ("public","research_only") AND d.is_removed = 0 AND u.region_code IS NOT NULL
      GROUP BY u.region, u.region_code
      ORDER BY count DESC'
);

// Active now (last 30 min)
$activeNow = Database::fetchOne(
    'SELECT COUNT(*) as c FROM users WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
);

// Top themes (last 7 days)
$themeData = Database::fetchAll(
    'SELECT themes FROM dreams
      WHERE privacy IN ("public","research_only") AND is_removed = 0
        AND dreamed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
);
$themeCount = [];
foreach ($themeData as $row) {
    foreach (Helpers::decodeArray($row['themes']) as $t) {
        $themeCount[$t] = ($themeCount[$t] ?? 0) + 1;
    }
}
arsort($themeCount);
$topThemes = [];
foreach (array_slice($themeCount, 0, 10, true) as $theme => $count) {
    $topThemes[] = ['theme' => $theme, 'count' => $count];
}

// Top emotions (last 24h)
$emotionData = Database::fetchAll(
    'SELECT emotions FROM dreams
      WHERE privacy IN ("public","research_only") AND is_removed = 0
        AND dreamed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
);
$emotionCount = [];
foreach ($emotionData as $row) {
    foreach (Helpers::decodeArray($row['emotions']) as $e) {
        $emotionCount[$e] = ($emotionCount[$e] ?? 0) + 1;
    }
}
arsort($emotionCount);
$topEmotions = [];
foreach (array_slice($emotionCount, 0, 8, true) as $emotion => $count) {
    $topEmotions[] = ['emotion' => $emotion, 'count' => $count];
}

// Compute top theme per region
$regionalActivity = [];
foreach ($regions as $r) {
    $regionThemes = Database::fetchAll(
        'SELECT d.themes FROM dreams d
           JOIN users u ON d.user_id = u.id
          WHERE d.privacy IN ("public","research_only") AND d.is_removed = 0
            AND u.region_code = ?',
        [$r['region_code']]
    );
    $rThemeCount = [];
    foreach ($regionThemes as $rt) {
        foreach (Helpers::decodeArray($rt['themes']) as $t) {
            $rThemeCount[$t] = ($rThemeCount[$t] ?? 0) + 1;
        }
    }
    arsort($rThemeCount);
    $topThemeForRegion = key($rThemeCount) ?: ($topThemes[0]['theme'] ?? 'unknown');

    $regionalActivity[] = [
        'region_code' => $r['region_code'],
        'region'      => $r['region'],
        'count'       => (int)$r['count'],
        'top_theme'   => $topThemeForRegion,
    ];
}

Helpers::respond([
    'total_dreams'        => (int)($totalDreams['c'] ?? 0),
    'active_countries'    => count($regions),
    'dreamers_active_now' => (int)($activeNow['c'] ?? 0),
    'top_themes'          => $topThemes,
    'top_emotions'        => $topEmotions,
    'regional_activity'   => $regionalActivity,
]);
