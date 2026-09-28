<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/premium.php';

$admin = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $codes = Database::fetchAll(
        'SELECT id, code, kind, days, percent_off, max_uses, uses, expires_at, is_active, note, created_at
           FROM premium_codes ORDER BY created_at DESC LIMIT 300'
    );
    foreach ($codes as &$code) {
        $code['is_active'] = (bool)$code['is_active'];
        $code['expires_at'] = Premium::iso($code['expires_at']);
        $code['created_at'] = Premium::iso($code['created_at']);
    }
    unset($code);
    Helpers::respond(['codes' => $codes]);
}

if ($method === 'POST') {
    $input = Helpers::input();
    $kind = (string)($input['kind'] ?? 'promo');
    $count = max(1, min(200, (int)($input['count'] ?? 1)));
    $custom = trim((string)($input['code'] ?? ''));
    if ($custom !== '' && $count > 1) Helpers::respond(['error' => 'A custom code can only be created once. Leave it empty to generate several.'], 400);
    $expires = trim((string)($input['expires_on'] ?? ''));
    if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) Helpers::respond(['error' => 'Use YYYY-MM-DD for the expiry date.'], 400);
    $created = [];
    try {
        for ($i = 0; $i < $count; $i++) {
            $created[] = Premium::createCode(
                $kind, (int)($input['days'] ?? 0), (int)($input['percent_off'] ?? 0), (int)($input['max_uses'] ?? 1),
                $expires !== '' ? $expires . ' 23:59:59' : null, (string)($input['note'] ?? ''), null, $admin['userId'], $custom ?: null
            );
        }
    } catch (InvalidArgumentException $e) {
        Helpers::respond(['error' => $e->getMessage(), 'created' => array_column($created, 'code')], 400);
    }
    Helpers::respond(['created' => array_column($created, 'code')], 201);
}

if ($method === 'PATCH') {
    $input = Helpers::input();
    Database::query('UPDATE premium_codes SET is_active = ? WHERE id = ?', [(int)!empty($input['is_active']), (string)($input['id'] ?? '')]);
    Helpers::respond(['message' => 'Code updated']);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
