<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/matching.php';
require_once __DIR__ . '/../../includes/staff.php';

$currentUser = Auth::requireModerator();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

if ($method === 'GET' && !$id) {
    $flags = Database::fetchAll(
        'SELECT * FROM moderation_flags ORDER BY created_at DESC LIMIT 100'
    );

    // Enrich with dream content preview, reporter email, reviewer email
    $enriched = [];
    foreach ($flags as $f) {
        $preview = null;
        $emotions = [];
        $themes = [];
        if ($f['dream_id']) {
            $d = Database::fetchOne('SELECT title, content, emotions, themes FROM dreams WHERE id = ?', [$f['dream_id']]);
            if ($d) {
                $preview = ($d['title'] ? $d['title'] . ' — ' : '') . mb_substr($d['content'], 0, 250);
                $emotions = Helpers::decodeArray($d['emotions']);
                $themes = Helpers::decodeArray($d['themes']);
            }
        }
        $reporter = Database::fetchOne('SELECT email FROM users WHERE id = ?', [$f['reporter_id']]);
        $reviewer = $f['reviewed_by'] ? Database::fetchOne('SELECT email FROM users WHERE id = ?', [$f['reviewed_by']]) : null;

        // Flag count for the dream
        $flagCount = 1;
        if ($f['dream_id']) {
            $fc = Database::fetchOne('SELECT flag_count FROM dreams WHERE id = ?', [$f['dream_id']]);
            $flagCount = (int)($fc['flag_count'] ?? 1);
        }

        $enriched[] = array_merge($f, [
            'dream_content_preview' => $preview,
            'emotions'              => $emotions,
            'themes'                => $themes,
            'flag_count'            => $flagCount,
            'reporter'              => $reporter ? ['email' => $reporter['email']] : null,
            'reviewer'              => $reviewer ? ['email' => $reviewer['email']] : null,
        ]);
    }

    Helpers::respond(['flags' => $enriched]);
}

if (($method === 'PATCH' || $method === 'POST') && $id) {
    $input = Helpers::input();
    $status = $input['status'] ?? '';
    $action = mb_substr((string)($input['action_taken'] ?? ''), 0, 255) ?: null;
    $existingFlag = Database::fetchOne('SELECT * FROM moderation_flags WHERE id = ?', [$id]);
    if (!$existingFlag) Helpers::respond(['error' => 'Report not found'], 404);

    if (!in_array($status, ['dismissed','warned','hidden','actioned','escalated','reviewed'])) {
        Helpers::respond(['error' => 'Invalid status'], 400);
    }

    Database::query(
        'UPDATE moderation_flags
            SET status = ?, action_taken = ?, reviewed_by = ?, reviewed_at = NOW()
          WHERE id = ?',
        [$status, $action, $currentUser['userId'], $id]
    );

    // Apply action to the dream
    $flag = Database::fetchOne('SELECT dream_id FROM moderation_flags WHERE id = ?', [$id]);
    if ($flag && $flag['dream_id']) {
        if ($status === 'actioned' || $status === 'escalated') {
            Database::query('UPDATE dreams SET is_removed = 1 WHERE id = ?', [$flag['dream_id']]);
        }
        if ($status === 'hidden') {
            Database::query('UPDATE dreams SET privacy = "private" WHERE id = ?', [$flag['dream_id']]);
        }
        if (in_array($status, ['hidden', 'actioned', 'escalated'], true)) Matching::clear($flag['dream_id']);
        if (in_array($status, ['warned', 'hidden', 'actioned', 'escalated'], true)) {
            $owner = Database::fetchOne('SELECT user_id FROM dreams WHERE id = ?', [$flag['dream_id']]);
            if ($owner) Notification::create($owner['user_id'], 'moderation', [
                'title' => $status === 'warned' ? 'A note from the moderation team' : 'A dream has been moderated',
                'body' => $action ?: 'Please review the community guidelines before sharing further dreams.',
                'data' => ['dreamId' => $flag['dream_id'], 'status' => $status],
            ]);
        }
    }

    Staff::audit($currentUser, 'moderation.' . $status, "Marked a report on dream {$existingFlag['dream_id']} as $status" . ($action ? " — $action" : ''), 'flag', $id,
        ['dream_id' => $existingFlag['dream_id'], 'reason' => $existingFlag['reason'] ?? null]);
    Helpers::respond(['message' => 'Flag updated']);
}

Helpers::respond(['error' => 'Invalid request'], 400);
