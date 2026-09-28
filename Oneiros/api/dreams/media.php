<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/media.php';
Helpers::only(['GET']);
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$claim = JWT::decode((string)($_GET['token'] ?? ''), $cfg['jwt_secret']);
if (!$claim || ($claim['purpose'] ?? '') !== 'media' || ($claim['dreamId'] ?? '') !== ($_GET['id'] ?? '')) {
    Helpers::respond(['error' => 'Media link has expired. Reopen the dream.'], 401);
}
$account = Database::fetchOne('SELECT id FROM users WHERE id = ? AND is_active = 1', [$claim['viewerId']]);
$dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ? AND is_removed = 0', [$claim['dreamId']]);
if (!$account || !$dream || ($dream['privacy'] !== 'public' && $dream['user_id'] !== $account['id'])) {
    Helpers::respond(['error' => 'Media not found'], 404);
}
$kind = ($claim['kind'] ?? '') === 'audio' ? 'audio' : 'image';
$path = Media::path($dream[$kind . '_url'] ?? '');
if (!$path) Helpers::respond(['error' => 'Media not found'], 404);
Media::serve($path);
