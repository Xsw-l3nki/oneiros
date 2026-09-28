<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/premium.php';

Helpers::only(['GET']);
$user = Auth::require();

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$dreamId = $_GET['dream_id'] ?? null;
$filter = $_GET['filter'] ?? null;

$where = '(m.user_a_id = ? OR m.user_b_id = ?) AND da.privacy = "public" AND db.privacy = "public"
    AND da.is_removed = 0 AND db.is_removed = 0 AND ua.is_active = 1 AND ub.is_active = 1
    AND NOT EXISTS (SELECT 1 FROM connections c WHERE c.status = "blocked" AND
        ((c.requester_id = m.user_a_id AND c.receiver_id = m.user_b_id) OR
         (c.requester_id = m.user_b_id AND c.receiver_id = m.user_a_id)))';
$params = [$user['userId'], $user['userId']];
if ($dreamId) {
    $dream = Database::fetchOne('SELECT id FROM dreams WHERE id = ? AND user_id = ? AND is_removed = 0', [$dreamId, $user['userId']]);
    if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);
    $where .= ' AND (m.dream_a_id = ? OR m.dream_b_id = ?)';
    $params[] = $dreamId; $params[] = $dreamId;
}
$filters = ['theme' => 'm.theme_score >= 50', 'emotion' => 'm.emotion_score >= 50',
    'visual' => 'm.symbol_score >= 50', 'narrative' => 'm.narrative_score >= 80'];
if (isset($filters[$filter ?? ''])) $where .= ' AND ' . $filters[$filter];
$joins = ' FROM dream_matches m JOIN dreams da ON da.id = m.dream_a_id JOIN dreams db ON db.id = m.dream_b_id
    JOIN users ua ON ua.id = m.user_a_id JOIN users ub ON ub.id = m.user_b_id';
// Free dreamers see their closest resonances; Lucid reveals every one
$isPremium = !empty($user['isPremium']);
$freeVisible = max(1, (int)(Premium::cfg()['free_visible_matches'] ?? 5));
if (!$isPremium) {
    $limit = min($limit, $freeVisible);
    $offset = 0;
}
$matches = (!$isPremium && $page > 1) ? [] : Database::fetchAll("SELECT m.* $joins WHERE $where ORDER BY m.score DESC LIMIT $limit OFFSET $offset", $params);
$total = Database::fetchOne("SELECT COUNT(*) AS c $joins WHERE $where", $params);
$totalCount = (int)$total['c'];
// Enrich with anonymized dream + region data
$enriched = [];
foreach ($matches as $m) {
    // Determine which side is the current user — use user_id, not dream_id
    $isUserA = ($m['user_a_id'] === $user['userId']);
    $matchedDreamId = $isUserA ? $m['dream_b_id'] : $m['dream_a_id'];
    $matchedUserId  = $isUserA ? $m['user_b_id'] : $m['user_a_id'];

    $matched = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$matchedDreamId]);
    $matchedUser = Database::fetchOne('SELECT region, region_code FROM users WHERE id = ?', [$matchedUserId]);

    $connection = Database::fetchOne(
        'SELECT status FROM connections
          WHERE (requester_id = ? AND receiver_id = ?)
             OR (requester_id = ? AND receiver_id = ?)',
        [$user['userId'], $matchedUserId, $matchedUserId, $user['userId']]
    );

    $enriched[] = [
        'match_id'        => $m['id'],
        'dream_id'        => $matchedDreamId,
        'score'           => (float)$m['score'],
        'theme_score'     => (float)$m['theme_score'],
        'emotion_score'   => (float)$m['emotion_score'],
        'symbol_score'    => (float)$m['symbol_score'],
        'narrative_score' => (float)$m['narrative_score'],
        'matched_dream_preview' => $matched ? [
            'id'              => $matched['id'],
            'title'           => $matched['title'],
            'content_preview' => mb_substr($matched['content'], 0, 200),
            'emotions'        => Helpers::decodeArray($matched['emotions']),
            'themes'          => Helpers::decodeArray($matched['themes']),
            'symbols'         => Helpers::decodeArray($matched['symbols']),
            'narrative_arc'   => $matched['narrative_arc'],
            'dreamed_at'      => $matched['dreamed_at'],
        ] : null,
        'region'            => $matchedUser['region'] ?? null,
        'region_code'       => $matchedUser['region_code'] ?? null,
        'connection_status' => $connection['status'] ?? null,
        'matched_user_id'   => $matchedUserId,
        'created_at'        => $m['created_at'],
    ];
}

Helpers::respond([
    'matches'      => $enriched,
    'total'        => $totalCount,
    'is_premium'   => $isPremium,
    'locked_count' => $isPremium ? 0 : max(0, $totalCount - $freeVisible),
]);
