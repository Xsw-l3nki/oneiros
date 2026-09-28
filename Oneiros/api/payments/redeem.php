<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/payments.php';

Helpers::only(['POST']);
$user = Auth::require();
if (!Security::rateLimit('redeem_' . $user['userId'], 10, 3600)) {
    Helpers::respond(['error' => 'Too many attempts. Please try again later.'], 429);
}
$input = Helpers::input();
if (trim((string)($input['code'] ?? '')) === '') Helpers::respond(['error' => 'Enter your code.'], 400);

try {
    $result = Premium::redeem($user['userId'], (string)$input['code']);
} catch (InvalidArgumentException $e) {
    Helpers::respond(['error' => $e->getMessage()], 400);
}
Helpers::respond($result + ['membership' => Premium::status($user['userId'])]);
