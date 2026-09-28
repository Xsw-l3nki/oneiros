<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/payments.php';

// Polled by the "returning from payment" screen until the gateway confirms.
Helpers::only(['GET']);
$user = Auth::require();
$order = Database::fetchOne('SELECT * FROM premium_orders WHERE id = ? AND user_id = ?', [(string)($_GET['id'] ?? ''), $user['userId']]);
if (!$order) Helpers::respond(['error' => 'Order not found'], 404);

if ($order['status'] !== 'paid' && $order['provider'] === 'paystack') {
    try {
        $order = Paystack::settle($order);
    } catch (Throwable $e) {
        Premium::logEvent('paystack', $order['id'], 'verify_failed', false, $e->getMessage());
    }
}
Helpers::respond(['order' => Premium::publicOrder($order), 'membership' => Premium::status($user['userId'])]);
