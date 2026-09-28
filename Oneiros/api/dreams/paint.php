<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/security.php';
Helpers::only(['GET', 'POST']);
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $claim = JWT::decode((string)($_GET['token'] ?? ''), $cfg['jwt_secret']);
    if (!$claim || ($claim['purpose'] ?? '') !== 'paint') Helpers::respond(['error' => 'Preview expired'], 401);
    $account = Database::fetchOne('SELECT id FROM users WHERE id = ? AND is_active = 1', [$claim['viewerId']]);
    $path = $account ? Media::path($claim['path'] ?? '') : null;
    if (!$path) Helpers::respond(['error' => 'Preview not found'], 404);
    Media::serve($path);
}
$user = Auth::require();
if (empty($cfg['image_api_key']) || !function_exists('curl_init')) {
    Helpers::respond(['error' => 'AI painting is not configured. Use Dream Canvas or upload your own image.'], 503);
}
$input = Helpers::input();
Helpers::require_fields($input, ['prompt']);
$prompt = trim($input['prompt']);
if (mb_strlen($prompt) < 10 || mb_strlen($prompt) > 4000) Helpers::respond(['error' => 'Use a prompt between 10 and 4,000 characters.'], 400);
if (empty($user['isPremium'])) {
    $daily = (int)($cfg['free_ai_paintings_per_day'] ?? 1);
    if ($daily < 1 || !Security::rateLimit('paint_free_' . $user['userId'], $daily, 86400)) {
        Helpers::respond(['error' => 'You have used today’s AI painting. Your Dream Canvas is always free, and Lucid paints more.', 'upgrade' => 'paint'], 402);
    }
}
if (!Security::rateLimit('paint_' . $user['userId'], 5, 3600)) Helpers::respond(['error' => 'You can generate up to five AI paintings per hour.'], 429);
set_time_limit(150);
// Official API contract: https://developers.openai.com/api/docs/guides/image-generation
$request = curl_init('https://api.openai.com/v1/images/generations');
curl_setopt_array($request, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['image_api_key']],
    CURLOPT_POSTFIELDS => json_encode(['model' => $cfg['image_model'], 'prompt' => 'Create a poetic, cinematic dream illustration. No text. Dream: ' . $prompt,
        'n' => 1, 'size' => '1536x1024', 'quality' => 'medium', 'output_format' => 'png']),
]);
$response = curl_exec($request);
$status = curl_getinfo($request, CURLINFO_HTTP_CODE);
curl_close($request);
$result = $response ? json_decode($response, true) : null;
if ($status !== 200 || empty($result['data'][0]['b64_json'])) {
    error_log('Oneiros image provider returned HTTP ' . $status);
    Helpers::respond(['error' => 'The image provider could not complete this painting. Please try Dream Canvas or try again later.'], 502);
}
$bytes = base64_decode($result['data'][0]['b64_json'], true);
if (!$bytes || strlen($bytes) > 25 * 1024 * 1024) Helpers::respond(['error' => 'The image provider returned an invalid image.'], 502);
$relative = 'paintings/' . $user['userId'] . '/' . bin2hex(random_bytes(20)) . '.png';
$path = $cfg['upload_dir'] . '/' . $relative;
if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
if (file_put_contents($path, $bytes, LOCK_EX) === false) Helpers::respond(['error' => 'Could not save the painting.'], 500);
$token = JWT::encode(['purpose' => 'paint', 'viewerId' => $user['userId'], 'path' => $relative], $cfg['jwt_secret'], 3600);
Helpers::respond(['image_url' => Media::apiUrl('dreams/paint') . '&token=' . rawurlencode($token), 'paint_token' => $token], 201);
