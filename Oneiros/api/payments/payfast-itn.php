<?php
/**
 * PayFast Instant Transaction Notification. Server-to-server, no dreamer session.
 * A payment is honoured only when the signature, merchant, amount and PayFast's own
 * server confirmation all agree. Every notification is recorded in payment_events.
 */
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['POST']);
header('Content-Type: text/plain; charset=utf-8');
$post = $_POST;
$orderId = (string)($post['m_payment_id'] ?? '');
$status = strtoupper((string)($post['payment_status'] ?? ''));
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$cfg = Premium::cfg();

$reject = function (string $why) use ($orderId): void {
    Premium::logEvent('payfast', $orderId !== '' ? $orderId : null, 'itn_rejected', false, $why);
    http_response_code(200);   // acknowledge, so PayFast stops retrying a notification that will never be accepted
    exit('REJECTED');
};

if (!PayFast::validSignature($post)) $reject('signature mismatch');
if ((string)($post['merchant_id'] ?? '') !== (string)$cfg['payfast_merchant_id']) $reject('merchant mismatch');
$order = $orderId !== '' ? Database::fetchOne('SELECT * FROM premium_orders WHERE id = ?', [$orderId]) : null;
if (!$order) $reject('unknown order');
$paidCents = (int)round(((float)($post['amount_gross'] ?? 0)) * 100);
if ($paidCents !== (int)$order['amount_cents']) $reject("amount $paidCents vs {$order['amount_cents']}");
if (!PayFast::fromPayFast($ip)) Premium::logEvent('payfast', $orderId, 'itn_unlisted_ip', false, $ip);
if (!PayFast::confirmWithServer(PayFast::itnParamString($post))) $reject('server confirmation failed');

Premium::logEvent('payfast', $orderId, 'itn_' . strtolower($status !== '' ? $status : 'unknown'), true, 'pf ' . ($post['pf_payment_id'] ?? ''));
try {
    if ($status === 'COMPLETE') {
        Premium::fulfill($orderId, (string)($post['pf_payment_id'] ?? ''), $paidCents);
    } elseif ($status === 'CANCELLED' || $status === 'FAILED') {
        Database::query(
            'UPDATE premium_orders SET status = ? WHERE id = ? AND status = "pending"',
            [$status === 'FAILED' ? 'failed' : 'cancelled', $orderId]
        );
    }
} catch (Throwable $e) {
    Premium::logEvent('payfast', $orderId, 'fulfil_error', true, $e->getMessage());
    http_response_code(500);   // let PayFast retry later
    exit('ERROR');
}
echo 'OK';
