<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/matching.php';

Helpers::only(['PATCH', 'POST']);
$user = Auth::require();
$input = Helpers::input();
$id = $_GET['id'] ?? $input['dream_id'] ?? '';

if (!$id) Helpers::respond(['error' => 'Missing dream id'], 400);
Helpers::require_fields($input, ['privacy']);

$privacy = $input['privacy'];
if (!in_array($privacy, ['public','private','research_only'])) {
    Helpers::respond(['error' => 'Invalid privacy setting'], 400);
}

$dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ? AND user_id = ?', [$id, $user['userId']]);
if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);

Database::query('UPDATE dreams SET privacy = ? WHERE id = ?', [$privacy, $id]);
Matching::clear($id);

if ($privacy === 'public') {
    try { Matching::process($id, $user['userId']); } catch (Exception $e) {}
}

$updated = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$id]);
Helpers::respond(Helpers::formatDream($updated));
