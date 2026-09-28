<?php
/** Paystack webhook: HMAC-SHA512 signed, then re-verified with Paystack before fulfilment. */
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['POST']);
$raw = file_get_contents('php://input') ?: '';
if (!Paystack::validSignature($raw, (string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''))) {
    Premium::logEvent('paystack', null, 'webhook_rejected', false, 'bad signature');
    Helpers::respond(['error' => 'Invalid signature'], 401);
}
$event = json_decode($raw, true) ?: [];
if (($event['event'] ?? '') !== 'charge.success') Helpers::respond(['received' => true]);

$reference = (string)($event['data']['reference'] ?? '');
$order = Database::fetchOne('SELECT * FROM premium_orders WHERE reference = ?', [$reference]);
if (!$order) {
    Premium::logEvent('paystack', null, 'webhook_unknown_order', true, $reference);
    Helpers::respond(['received' => true]);
}
try {
    Paystack::settle($order);
} catch (Throwable $e) {
    Premium::logEvent('paystack', $order['id'], 'fulfil_error', true, $e->getMessage());
    Helpers::respond(['error' => 'Retry later'], 500);
}
Helpers::respond(['received' => true]);
