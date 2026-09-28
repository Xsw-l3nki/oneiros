<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['POST']);
$user = Auth::require();
$input = Helpers::input();
$plan = Premium::findPlan((string)($input['plan_id'] ?? ''));
if (!$plan) Helpers::respond(['error' => 'Choose a pass first.'], 400);

try {
    $quote = Premium::quote($plan, (string)($input['code'] ?? ''), $user['userId']);
} catch (InvalidArgumentException $e) {
    Helpers::respond(['error' => $e->getMessage()], 400);
}
Helpers::respond([
    'plan_id'        => $plan['id'],
    'amount_cents'   => $quote['amount_cents'],
    'amount'         => Premium::money($quote['amount_cents']),
    'discount_cents' => $quote['discount_cents'],
    'discount'       => Premium::money($quote['discount_cents']),
    'percent_off'    => $quote['percent_off'],
    'code'           => $quote['code'],
]);
