<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['GET']);
$user = Auth::require();
Premium::remind($user['userId']);

// Paid and refunded orders, plus checkouts started within the last hour
$orders = Database::fetchAll(
    'SELECT * FROM premium_orders
      WHERE user_id = ? AND (status IN ("paid", "refunded") OR (status = "pending" AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)))
      ORDER BY created_at DESC LIMIT 30',
    [$user['userId']]
);
Helpers::respond(Premium::status($user['userId']) + [
    'orders' => array_map([Premium::class, 'publicOrder'], $orders),
]);
