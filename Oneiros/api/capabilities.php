<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/premium.php';
Helpers::only(['GET']);
$cfg = require __DIR__ . '/../includes/runtime-config.php';
Helpers::respond([
    'ai_paint' => !empty($cfg['image_api_key']) && function_exists('curl_init'),
    'image_upload' => true,
    'audio_upload' => true,
    'push_notifications' => false,
    'payments' => (bool)Premium::providers(),
    'max_file_size' => (int)$cfg['max_file_size'],
    'version' => '2.1.0',
]);
