<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analysis.php';
require_once __DIR__ . '/../../includes/matching.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

if (!$id) Helpers::respond(['error' => 'Missing dream id'], 400);

$dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ? AND is_removed = 0', [$id]);
if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);

if ($dream['privacy'] !== 'public' && $dream['user_id'] !== $user['userId']) {
    Helpers::respond(['error' => 'Dream not found'], 404);
}

if ($method === 'GET') {
    Helpers::respond(Helpers::formatDream($dream));
}

if ($method === 'PATCH' || $method === 'POST') {
    if ($dream['user_id'] !== $user['userId']) Helpers::respond(['error' => 'Access denied'], 403);
    $input = Helpers::input();

    $updates = [];
    if (isset($input['title']))    $updates['title']    = mb_substr(trim((string)$input['title']), 0, 200);
    if (isset($input['content'])) {
        if (!is_string($input['content']) || mb_strlen(trim($input['content'])) < 10 || mb_strlen($input['content']) > 10000) Helpers::respond(['error' => 'Dream must be between 10 and 10,000 characters'], 400);
        $updates['content'] = trim($input['content']);
        $updates['word_count'] = count(preg_split('/\s+/u', $updates['content'], -1, PREG_SPLIT_NO_EMPTY));
    }
    if (isset($input['emotions']) && is_array($input['emotions'])) $updates['emotions'] = Helpers::encodeArray(array_slice(array_values(array_filter($input['emotions'], 'is_string')), 0, 12));
    if (isset($input['privacy']) && in_array($input['privacy'], ['public','private','research_only'])) {
        $updates['privacy'] = $input['privacy'];
    }
    if (count($updates) === 0) Helpers::respond(['error' => 'No updates'], 400);
    if (isset($updates['content']) || array_key_exists('emotions', $updates)) {
        $analysis = Analysis::analyse($updates['content'] ?? $dream['content'], Helpers::decodeArray($updates['emotions'] ?? $dream['emotions']));
        $updates['themes'] = Helpers::encodeArray($analysis['themes']);
        $updates['symbols'] = Helpers::encodeArray($analysis['symbols']);
        $updates['narrative_arc'] = $analysis['narrative_arc'];
        $updates['emotion_score'] = json_encode($analysis['emotion_score']);
    }

    Database::update('dreams', $updates, 'id', $id);
    Matching::clear($id);
    Matching::process($id, $user['userId']);
    $updated = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$id]);
    Helpers::respond(Helpers::formatDream($updated));
}

if ($method === 'DELETE') {
    if ($dream['user_id'] !== $user['userId']) Helpers::respond(['error' => 'Access denied'], 403);
    require_once __DIR__ . '/../../includes/media.php';
    Matching::clear($id);
    Media::purge($dream);
    Database::delete('dreams', 'id', $id);
    Helpers::respond(['message' => 'Dream deleted']);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
