<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/matching.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$connectionId = $_GET['id'] ?? '';

if ($action === 'accept' && $method === 'PATCH') {
    $conn = Database::fetchOne(
        'SELECT * FROM connections WHERE id = ? AND receiver_id = ? AND status = "pending"',
        [$connectionId, $user['userId']]
    );
    if (!$conn) Helpers::respond(['error' => 'Connection request not found'], 404);

    Database::query('UPDATE connections SET status = "connected", connected_at = NOW() WHERE id = ?', [$connectionId]);

    Notification::create($conn['requester_id'], 'connection_accepted', [
        'title' => 'Connection accepted',
        'body'  => 'Your connection request was accepted. You can now chat.',
        'data'  => ['connectionId' => $connectionId],
    ]);

    Helpers::respond(Database::fetchOne('SELECT * FROM connections WHERE id = ?', [$connectionId]));
}

if ($action === 'reject' && $method === 'DELETE') {
    Database::query('DELETE FROM connections WHERE id = ? AND receiver_id = ? AND status = "pending"',
        [$connectionId, $user['userId']]);
    Helpers::respond(['message' => 'Connection request declined']);
}

if ($action === 'block' && ($method === 'POST' || $method === 'PATCH')) {
    $input = Helpers::input();
    Helpers::require_fields($input, ['target_id']);
    $targetId = $input['target_id'];
    if ($targetId === $user['userId'] || !Database::fetchOne('SELECT id FROM users WHERE id = ?', [$targetId])) Helpers::respond(['error' => 'Dreamer not found'], 404);

    Database::query(
        'DELETE FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)',
        [$user['userId'], $targetId, $targetId, $user['userId']]
    );
    Database::insert('connections', [
        'id'           => Helpers::uuid(),
        'requester_id' => $user['userId'],
        'receiver_id'  => $targetId,
        'status'       => 'blocked',
    ]);
    $pairs = Database::fetchAll('SELECT dream_a_id, dream_b_id FROM dream_matches WHERE (user_a_id = ? AND user_b_id = ?) OR (user_a_id = ? AND user_b_id = ?)', [$user['userId'], $targetId, $targetId, $user['userId']]);
    Database::query('DELETE FROM dream_matches WHERE (user_a_id = ? AND user_b_id = ?) OR (user_a_id = ? AND user_b_id = ?)', [$user['userId'], $targetId, $targetId, $user['userId']]);
    foreach ($pairs as $pair) foreach ([$pair['dream_a_id'], $pair['dream_b_id']] as $dreamId) {
        $count = Database::fetchOne('SELECT COUNT(*) AS c FROM dream_matches WHERE dream_a_id = ? OR dream_b_id = ?', [$dreamId, $dreamId]);
        Database::query('UPDATE dreams SET match_count = ? WHERE id = ?', [(int)$count['c'], $dreamId]);
    }
    Helpers::respond(['message' => 'User blocked']);
}

Helpers::respond(['error' => 'Invalid action'], 400);
