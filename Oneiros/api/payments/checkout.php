<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['POST']);
$user = Auth::require();
if (!Security::rateLimit('checkout_' . $user['userId'], 20, 3600)) {
    Helpers::respond(['error' => 'Too many checkout attempts. Please wait a little.'], 429);
}
$providers = Premium::providers();
if (!$providers) Helpers::respond(['error' => 'Online payments are not open yet. You can still redeem a gift or promo code.'], 503);
if (!filter_var(Premium::cfg()['frontend_url'] ?? '', FILTER_VALIDATE_URL)) {
    Helpers::respond(['error' => 'Payments need frontend_url configured.'], 503);
}

$input = Helpers::input();
$provider = in_array($input['provider'] ?? '', $providers, true) ? $input['provider'] : $providers[0];
$plan = Premium::findPlan((string)($input['plan_id'] ?? ''));
if (!$plan) Helpers::respond(['error' => 'Choose a pass first.'], 400);

$isGift = !empty($input['gift']) && $plan['kind'] === 'pass';
$recipient = trim((string)($input['recipient_email'] ?? ''));
if ($isGift && $recipient !== '' && !Helpers::isEmail($recipient)) {
    Helpers::respond(['error' => 'Enter a valid email for the gift, or leave it empty to share the code yourself.'], 400);
}

try {
    $quote = Premium::quote($plan, (string)($input['code'] ?? ''), $user['userId']);
} catch (InvalidArgumentException $e) {
    Helpers::respond(['error' => $e->getMessage()], 400);
}

$account = Database::fetchOne('SELECT id, email FROM users WHERE id = ?', [$user['userId']]);
$order = Premium::createOrder($quote, $account, $provider, $isGift, $recipient ?: null);
try {
    $redirect = $provider === 'payfast'
        ? PayFast::checkout($order, oneiros_base_url())
        : Paystack::checkout($order, oneiros_base_url());
} catch (Throwable $e) {
    Database::query('UPDATE premium_orders SET status = "failed" WHERE id = ?', [$order['id']]);
    Premium::logEvent($provider, $order['id'], 'checkout_failed', false, $e->getMessage());
    Helpers::respond(['error' => 'The payment page could not be opened. Please try again in a moment.'], 502);
}
Premium::logEvent($provider, $order['id'], 'checkout_started', true, $order['plan_id'] . ' ' . $order['amount_cents']);
Helpers::respond(['order' => Premium::publicOrder($order), 'redirect' => $redirect], 201);
