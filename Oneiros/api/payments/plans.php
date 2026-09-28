<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/payments.php';

// Public: the pricing screen is shown before sign-up too.
Helpers::only(['GET']);
$cfg = Premium::cfg();

$describe = function (array $plan): array {
    $plan['price'] = Premium::money($plan['price_cents']);
    if ($plan['days'] > 0) $plan['per_night'] = Premium::money((int)round($plan['price_cents'] / $plan['days']));
    return $plan;
};
$plans = array_values(array_map($describe, Premium::plans()));
// Savings are measured against the featured (usually monthly) pass, and only shown for longer passes
$reference = null;
foreach ($plans as $plan) if ($plan['featured']) $reference = $plan;
$reference = $reference ?? ($plans[0] ?? null);
foreach ($plans as &$plan) {
    $plan['saving_percent'] = 0;
    if ($reference && $plan['days'] > $reference['days']) {
        $saving = 1 - ($plan['price_cents'] / $plan['days']) / ($reference['price_cents'] / $reference['days']);
        $plan['saving_percent'] = max(0, (int)round($saving * 100));
    }
}
unset($plan);

Helpers::respond([
    'currency'  => $cfg['currency'] ?? 'ZAR',
    'symbol'    => $cfg['currency_symbol'] ?? 'R',
    'plans'     => $plans,
    'support'   => array_values(array_map($describe, Premium::supportPlans())),
    'perks'     => Premium::PERKS,
    'providers' => Premium::providers(),
    'free'      => [
        'visible_matches'              => (int)($cfg['free_visible_matches'] ?? 5),
        'connection_requests_per_week' => (int)($cfg['free_connection_requests_per_week'] ?? 3),
        'ai_paintings_per_day'         => (int)($cfg['free_ai_paintings_per_day'] ?? 1),
    ],
    'referral_reward_days' => (int)($cfg['referral_reward_days'] ?? 0),
    'business'  => [
        'name'    => $cfg['business_name'] ?? 'Oneiros',
        'email'   => $cfg['business_email'] ?? '',
        'details' => $cfg['business_details'] ?? '',
    ],
]);
