<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/matching.php';
require_once __DIR__ . '/../../includes/premium.php';

$user = Auth::require();
$method = $_SERVER['REQUEST_METHOD'];

// GET /connections?status=pending - incoming requests, still anonymous (no name or email)
if ($method === 'GET' && ($_GET['status'] ?? '') === 'pending') {
    $requests = Database::fetchAll(
        'SELECT c.id, c.created_at, m.score, u.region, u.region_code
           FROM connections c
           JOIN users u ON u.id = c.requester_id AND u.is_active = 1
           LEFT JOIN dream_matches m ON m.id = c.match_id
          WHERE c.receiver_id = ? AND c.status = "pending"
          ORDER BY c.created_at DESC LIMIT 50',
        [$user['userId']]
    );
    Helpers::respond(array_map(fn($r) => [
        'id'          => $r['id'],
        'created_at'  => $r['created_at'],
        'match_score' => $r['score'] !== null ? round((float)$r['score']) : null,
        'region'      => $r['region'],
        'region_code' => $r['region_code'],
    ], $requests));
}

// GET /connections - list
if ($method === 'GET') {
    $connections = Database::fetchAll(
        'SELECT * FROM connections
          WHERE (requester_id = ? OR receiver_id = ?) AND status = "connected"
          ORDER BY connected_at DESC',
        [$user['userId'], $user['userId']]
    );

    $enriched = [];
    foreach ($connections as $c) {
        $partnerId = $c['requester_id'] === $user['userId'] ? $c['receiver_id'] : $c['requester_id'];
        // Connected dreamers see each other's chosen display name and country, never the account email
        $partner = Database::fetchOne(
            'SELECT id, display_name, region, region_code, premium_until, is_patron FROM users WHERE id = ?', [$partnerId]
        );
        if ($partner) {
            $partner['is_lucid'] = Premium::isActiveUntil($partner['premium_until']);
            $partner['is_patron'] = (bool)$partner['is_patron'];
            unset($partner['premium_until']);
        }

        $unread = Database::fetchOne(
            'SELECT COUNT(*) as c FROM messages WHERE connection_id = ? AND sender_id != ? AND is_read = 0',
            [$c['id'], $user['userId']]
        );

        $enriched[] = [
            'connection'   => $c,
            'partner'      => $partner,
            'unread_count' => (int)($unread['c'] ?? 0),
        ];
    }

    Helpers::respond($enriched);
}

// POST /connections - request
if ($method === 'POST') {
    Helpers::requireFeature('connections', 'New connections are paused for now.');
    $input = Helpers::input();
    Helpers::require_fields($input, ['receiver_id']);

    $receiverId = $input['receiver_id'];
    if ($receiverId === $user['userId']) Helpers::respond(['error' => 'Cannot connect with yourself'], 400);
    if (!Database::fetchOne('SELECT id FROM users WHERE id = ? AND is_active = 1', [$receiverId])) Helpers::respond(['error' => 'Dreamer not found'], 404);
    if (!empty($input['match_id']) && !Database::fetchOne('SELECT id FROM dream_matches WHERE id = ? AND ((user_a_id = ? AND user_b_id = ?) OR (user_b_id = ? AND user_a_id = ?))', [$input['match_id'], $user['userId'], $receiverId, $user['userId'], $receiverId])) {
        Helpers::respond(['error' => 'Match not found'], 404);
    }

    // Check if already exists
    $existing = Database::fetchOne(
        'SELECT * FROM connections
          WHERE (requester_id = ? AND receiver_id = ?)
             OR (requester_id = ? AND receiver_id = ?)',
        [$user['userId'], $receiverId, $receiverId, $user['userId']]
    );

    if ($existing) {
        if ($existing['status'] === 'blocked')   Helpers::respond(['error' => 'Cannot connect with this user'], 400);
        if ($existing['status'] === 'connected') Helpers::respond(['error' => 'Already connected'], 400);
        // If the other user already requested, accept it
        if ($existing['status'] === 'pending' && $existing['requester_id'] === $receiverId) {
            Database::query('UPDATE connections SET status = "connected", connected_at = NOW() WHERE id = ?', [$existing['id']]);
            Notification::create($receiverId, 'connection_accepted', [
                'title' => 'Connection accepted',
                'body'  => 'Your connection request was accepted. You can now chat.',
                'data'  => ['connectionId' => $existing['id']],
            ]);
            Helpers::respond(Database::fetchOne('SELECT * FROM connections WHERE id = ?', [$existing['id']]));
        }
        Helpers::respond(['error' => 'Connection request already sent'], 400);
    }

    if (empty($user['isPremium'])) {
        $weekly = (int)(Premium::cfg()['free_connection_requests_per_week'] ?? 3);
        $sent = Database::fetchOne(
            'SELECT COUNT(*) AS c FROM connections WHERE requester_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)',
            [$user['userId']]
        );
        if ((int)$sent['c'] >= $weekly) {
            Helpers::respond([
                'error'   => "You have sent $weekly connection requests this week. Lucid removes the limit, or try again in a few days.",
                'upgrade' => 'connections',
            ], 402);
        }
    }

    $connId = Helpers::uuid();
    Database::insert('connections', [
        'id'           => $connId,
        'requester_id' => $user['userId'],
        'receiver_id'  => $receiverId,
        'match_id'     => $input['match_id'] ?? null,
        'status'       => 'pending',
    ]);

    // Get match score for notification
    $matchScore = null;
    if (!empty($input['match_id'])) {
        $match = Database::fetchOne('SELECT score FROM dream_matches WHERE id = ?', [$input['match_id']]);
        $matchScore = $match ? round((float)$match['score']) : null;
    }

    Notification::create($receiverId, 'connection_request', [
        'title' => 'A dreamer wants to connect',
        'body'  => $matchScore
            ? "A dreamer with a {$matchScore}% dream resonance has sent you a connection request."
            : 'A matched dreamer has sent you a connection request.',
        'data'  => ['connectionId' => $connId, 'matchId' => $input['match_id'] ?? null],
    ]);

    Helpers::respond(Database::fetchOne('SELECT * FROM connections WHERE id = ?', [$connId]), 201);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
