<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/premium.php';
Helpers::only(['GET']);
$cfg = require __DIR__ . '/../includes/runtime-config.php';
// Feature switches come from the Console (includes/settings.php).
Helpers::respond([
    'ai_paint' => !empty($cfg['feature_ai_paint']) && !empty($cfg['image_api_key']) && function_exists('curl_init'),
    'image_upload' => (bool)$cfg['feature_image_upload'],
    'audio_upload' => (bool)$cfg['feature_audio_upload'],
    'connections' => (bool)$cfg['feature_connections'],
    'registrations' => (bool)$cfg['feature_registrations'],
    'codes' => (bool)$cfg['feature_codes'],
    'public_research' => (bool)$cfg['feature_public_research'],
    'push_notifications' => false,
    'payments' => (bool)Premium::providers(),
    'max_file_size' => (int)$cfg['max_file_size'],
    'version' => $cfg['app_version'],
]);
