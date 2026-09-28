<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/premium.php';

// Grant Lucid days (for EFT payments, competitions, apologies) or end a pass.
$admin = Auth::requireAdmin();
Helpers::only(['POST']);
$input = Helpers::input();
$target = !empty($input['user_id'])
    ? Database::fetchOne('SELECT id, email FROM users WHERE id = ?', [(string)$input['user_id']])
    : Database::fetchOne('SELECT id, email FROM users WHERE email = ?', [strtolower(trim((string)($input['email'] ?? '')))]);
if (!$target) Helpers::respond(['error' => 'No dreamer with that email.'], 404);

if (!empty($input['revoke'])) {
    Premium::reduce($target['id'], null);
} else {
    $days = (int)($input['days'] ?? 0);
    if ($days < 1 || $days > 3650) Helpers::respond(['error' => 'Choose between 1 and 3650 days.'], 400);
    Premium::extend($target['id'], $days);
    Notification::create($target['id'], 'premium_activated', [
        'title' => 'A gift of Lucid nights',
        'body'  => "{$days} Lucid nights were added to your account.",
    ]);
}
Helpers::respond(['email' => $target['email']] + Premium::status($target['id']));
