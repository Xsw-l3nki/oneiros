<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['POST']);
$user = Auth::require();
$input = Helpers::input();

$dreamId = $_GET['id'] ?? $input['dream_id'] ?? '';
if (!$dreamId) Helpers::respond(['error' => 'Missing dream id'], 400);
Helpers::require_fields($input, ['reason']);
$dream = Database::fetchOne('SELECT id FROM dreams WHERE id = ? AND privacy = "public" AND is_removed = 0', [$dreamId]);
if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);
if (Database::fetchOne('SELECT id FROM moderation_flags WHERE dream_id = ? AND reporter_id = ? AND status = "pending"', [$dreamId, $user['userId']])) {
    Helpers::respond(['error' => 'You have already reported this dream.'], 409);
}

if (!in_array($input['reason'], ['inappropriate','harmful','spam','personal_info','other'])) {
    Helpers::respond(['error' => 'Invalid reason'], 400);
}

Database::insert('moderation_flags', [
    'id'          => Helpers::uuid(),
    'dream_id'    => $dreamId,
    'reporter_id' => $user['userId'],
    'reason'      => $input['reason'],
    'notes'       => substr(trim($input['notes'] ?? ''), 0, 500) ?: null,
]);

// Increment flag count on dream
Database::query('UPDATE dreams SET flag_count = flag_count + 1, is_flagged = 1 WHERE id = ?', [$dreamId]);

Helpers::respond(['message' => 'Dream reported. Our moderators will review it.']);
