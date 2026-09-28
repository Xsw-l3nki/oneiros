<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analysis.php';
require_once __DIR__ . '/../../includes/matching.php';
require_once __DIR__ . '/../../includes/streaks.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/premium.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $rows = Database::fetchAll(
        'SELECT * FROM dreams WHERE user_id = ? AND is_removed = 0
          ORDER BY dreamed_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
        [$user['userId']]
    );

    $total = Database::fetchOne(
        'SELECT COUNT(*) as c FROM dreams WHERE user_id = ? AND is_removed = 0',
        [$user['userId']]
    );

    Helpers::respond([
        'dreams' => array_map([Helpers::class, 'formatDream'], $rows),
        'total'  => (int)($total['c'] ?? 0),
    ]);
}

if ($method === 'POST') {
    $input = Helpers::input();
    Helpers::require_fields($input, ['content']);

    $content = trim($input['content']);
    if (mb_strlen($content) < 10 || mb_strlen($content) > 10000) {
        Helpers::respond(['error' => 'Dream must be between 10 and 10,000 characters'], 400);
    }

    $emotions = array_slice(array_values(array_filter(is_array($input['emotions'] ?? null) ? $input['emotions'] : [], 'is_string')), 0, 12);
    $privacy  = in_array($input['privacy'] ?? '', ['public','private','research_only']) ? $input['privacy'] : 'private';
    if (!empty($input['dreamed_at']) && strtotime($input['dreamed_at']) === false) Helpers::respond(['error' => 'Invalid dream date'], 400);
    $dreamedAt = !empty($input['dreamed_at']) ? date('Y-m-d H:i:s', strtotime($input['dreamed_at'])) : date('Y-m-d H:i:s');

    // Run analysis
    $analysis = Analysis::analyse($content, $emotions);

    $dreamId = Helpers::uuid();
    Database::insert('dreams', [
        'id'            => $dreamId,
        'user_id'       => $user['userId'],
        'title'         => isset($input['title']) ? mb_substr(trim((string)$input['title']), 0, 200) : null,
        'content'       => $content,
        'privacy'       => $privacy,
        'emotions'      => Helpers::encodeArray($emotions),
        'themes'        => Helpers::encodeArray($analysis['themes']),
        'symbols'       => Helpers::encodeArray($analysis['symbols']),
        'narrative_arc' => $analysis['narrative_arc'],
        'emotion_score' => json_encode($analysis['emotion_score']),
        'dreamed_at'    => $dreamedAt,
        'word_count'    => count(preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY)),
    ]);

    // Run matching synchronously (non-blocking on cPanel is hard, so we just do it)
    if (!empty($input['paint_token'])) {
        $imagePath = Media::attachPaint((string)$input['paint_token'], $dreamId, $user['userId']);
        if ($imagePath) Database::query('UPDATE dreams SET image_url = ?, ai_generated_image = 1 WHERE id = ?', [$imagePath, $dreamId]);
    }
    {
        try {
            Matching::process($dreamId, $user['userId']);
        } catch (Exception $e) {
            error_log('Oneiros matching error: ' . $e->getMessage());
        }
    }

    $dreamCount = Database::fetchOne('SELECT COUNT(*) AS c FROM dreams WHERE user_id = ?', [$user['userId']]);
    if ((int)$dreamCount['c'] === 1) Premium::rewardReferral($user['userId']);

    $dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$dreamId]);
    $result = Helpers::formatDream($dream);
    $result['streak'] = Streak::update($user['userId']);
    $result['new_badges'] = Badge::check($user['userId'], ['used_voice' => !empty($input['used_voice']), 'used_ai_paint' => !empty($dream['ai_generated_image'])]);
    Helpers::respond($result, 201);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
