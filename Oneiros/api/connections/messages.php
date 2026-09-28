<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/matching.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$connectionId = $_GET['connection_id'] ?? '';
if (!$connectionId) Helpers::respond(['error' => 'Missing connection_id'], 400);

// Verify access to connection
$conn = Database::fetchOne(
    'SELECT * FROM connections
      WHERE id = ? AND status = "connected"
        AND (requester_id = ? OR receiver_id = ?)',
    [$connectionId, $user['userId'], $user['userId']]
);
if (!$conn) Helpers::respond(['error' => 'Access denied'], 403);

if ($method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $messages = Database::fetchAll(
        'SELECT * FROM messages WHERE connection_id = ? AND is_flagged = 0
          ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
        [$connectionId]
    );

    $total = Database::fetchOne('SELECT COUNT(*) as c FROM messages WHERE connection_id = ? AND is_flagged = 0', [$connectionId]);

    // Mark as read
    Database::query('UPDATE messages SET is_read = 1 WHERE connection_id = ? AND sender_id != ?',
        [$connectionId, $user['userId']]);

    foreach ($messages as $idx => $m) {
        $messages[$idx]['is_read'] = (bool)$m['is_read'];
        $messages[$idx]['is_flagged'] = (bool)$m['is_flagged'];
    }

    Helpers::respond([
        'messages' => array_reverse($messages),
        'total'    => (int)($total['c'] ?? 0),
    ]);
}

if ($method === 'POST') {
    $input = Helpers::input();
    Helpers::require_fields($input, ['content']);
    $content = trim($input['content']);
    if (mb_strlen($content) < 1 || mb_strlen($content) > 2000) {
        Helpers::respond(['error' => 'Message must be 1-2000 characters'], 400);
    }

    $messageId = Helpers::uuid();
    Database::insert('messages', [
        'id'            => $messageId,
        'connection_id' => $connectionId,
        'sender_id'     => $user['userId'],
        'content'       => $content,
    ]);

    $recipientId = $conn['requester_id'] === $user['userId'] ? $conn['receiver_id'] : $conn['requester_id'];
    Notification::create($recipientId, 'new_message', [
        'title' => 'New message',
        'body'  => 'You have a new message from a connected dreamer.',
        'data'  => ['connectionId' => $connectionId, 'messageId' => $messageId],
    ]);

    Helpers::respond(Database::fetchOne('SELECT * FROM messages WHERE id = ?', [$messageId]), 201);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
