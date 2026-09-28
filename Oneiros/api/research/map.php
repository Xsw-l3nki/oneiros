<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Helpers::respond(['error' => 'Method not allowed'], 405);
Helpers::requireFeature('public_research', 'Public research pages are switched off.');

try {
    // Anonymous region clusters — no PII
    $clusters = Database::fetchAll(
        'SELECT
            u.region_code,
            u.region,
            COUNT(d.id)                                                              AS dream_count,
            COUNT(CASE WHEN d.dreamed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)  THEN 1 END) AS active_7d,
            COUNT(CASE WHEN d.dreamed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) AS active_24h
         FROM users u
         JOIN dreams d ON d.user_id = u.id
            AND d.privacy IN ("public","research_only")
            AND d.is_removed = 0
         WHERE u.region_code IS NOT NULL AND u.region_code != ""
         GROUP BY u.region_code, u.region
         HAVING dream_count > 0
         ORDER BY dream_count DESC
         LIMIT 60',
        []
    );

    // Top theme per region
    $themesByRegion = [];
    if ($clusters) {
        $codes = array_column($clusters, 'region_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $rows = Database::fetchAll(
            "SELECT u.region_code,
                    JSON_UNQUOTE(JSON_EXTRACT(d.themes, '\$[0]')) AS top_theme,
                    COUNT(*) AS cnt
               FROM dreams d
               JOIN users u ON d.user_id = u.id
              WHERE u.region_code IN ($placeholders)
                AND d.privacy IN ('public','research_only')
                AND d.is_removed = 0
                AND d.themes IS NOT NULL AND d.themes != '[]'
              GROUP BY u.region_code, top_theme
              ORDER BY cnt DESC",
            $codes
        );
        foreach ($rows as $r) {
            if ($r['top_theme'] && !isset($themesByRegion[$r['region_code']])) {
                $themesByRegion[$r['region_code']] = $r['top_theme'];
            }
        }
    }

    // Online count: dreamers active in the last 15 minutes
    $onlineCount = 0;
    try {
        $row = Database::fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE is_active = 1 AND last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)', []);
        $onlineCount = (int)($row['cnt'] ?? 0);
    } catch (Exception $e) { /* column might not exist yet */ }

    // Total dreams & active countries
    $globalRow = Database::fetchOne(
        'SELECT COUNT(*) AS total_dreams, COUNT(DISTINCT u.region_code) AS countries
           FROM dreams d JOIN users u ON d.user_id = u.id
          WHERE d.privacy IN ("public","research_only") AND d.is_removed = 0 AND u.region_code IS NOT NULL',
        []
    );

    $points = [];
    foreach ($clusters as $c) {
        $points[] = [
            'region_code' => $c['region_code'],
            'region'      => $c['region'] ?: $c['region_code'],
            'dream_count' => (int)$c['dream_count'],
            'active_7d'   => (int)$c['active_7d'],
            'active_24h'  => (int)$c['active_24h'],
            'top_theme'   => $themesByRegion[$c['region_code']] ?? 'dreams',
        ];
    }

    Helpers::respond([
        'points'        => $points,
        'online_now'    => $onlineCount,
        'total_dreams'  => (int)($globalRow['total_dreams']  ?? 0),
        'active_countries' => (int)($globalRow['countries'] ?? 0),
    ]);

} catch (Exception $e) {
    error_log('Oneiros map error: ' . $e->getMessage());
    Helpers::respond(['error' => 'Map data unavailable'], 500);
}
