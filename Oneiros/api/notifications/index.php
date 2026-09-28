<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $notifications = Database::fetchAll(
        'SELECT * FROM notifications WHERE user_id = ?
          ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
        [$user['userId']]
    );

    $unread = Database::fetchOne(
        'SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0',
        [$user['userId']]
    );

    foreach ($notifications as $idx => $n) {
        $notifications[$idx]['is_read'] = (bool)$n['is_read'];
        $notifications[$idx]['data'] = Helpers::decodeObject($n['data']);
    }

    Helpers::respond([
        'notifications' => $notifications,
        'unread_count'  => (int)($unread['c'] ?? 0),
    ]);
}

if ($method === 'PATCH' || $method === 'POST') {
    $input = Helpers::input();
    if (!empty($input['ids']) && is_array($input['ids'])) {
        $placeholders = implode(',', array_fill(0, count($input['ids']), '?'));
        Database::query(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)",
            array_merge([$user['userId']], $input['ids'])
        );
    } else {
        Database::query('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$user['userId']]);
    }
    Helpers::respond(['message' => 'Notifications marked as read']);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
