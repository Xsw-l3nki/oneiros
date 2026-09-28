<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// The dreamer came back through the gateway's cancel link. A later genuine payment
// notification for the same order is still honoured.
Helpers::only(['POST']);
$user = Auth::require();
$input = Helpers::input();
Database::query(
    'UPDATE premium_orders SET status = "cancelled" WHERE id = ? AND user_id = ? AND status = "pending"',
    [(string)($input['order_id'] ?? ''), $user['userId']]
);
Helpers::respond(['message' => 'No payment was taken.']);
