<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/media.php';

Helpers::only(['POST']);
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$user = Auth::require();

$dreamId = $_POST['dream_id'] ?? $_GET['dream_id'] ?? '';
if (!$dreamId) Helpers::respond(['error' => 'Missing dream_id'], 400);

// Verify ownership
$dream = Database::fetchOne('SELECT id FROM dreams WHERE id = ? AND user_id = ?', [$dreamId, $user['userId']]);
if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    Helpers::respond(['error' => 'No image uploaded'], 400);
}

$file = $_FILES['image'];

if ($file['size'] > $cfg['max_file_size']) {
    Helpers::respond(['error' => 'Image must be under 10MB'], 400);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMimes)) {
    Helpers::respond(['error' => 'Only JPG, PNG, WEBP, or GIF images allowed'], 400);
}

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$ext = $extMap[$mime] ?? 'jpg';

$userDir = $cfg['upload_dir'] . '/dreams/' . $user['userId'];
if (!is_dir($userDir)) mkdir($userDir, 0775, true);

$filename = $dreamId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest = $userDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Helpers::respond(['error' => 'Failed to save uploaded file'], 500);
}

// Build URL
$imageUrl = 'dreams/' . $user['userId'] . '/' . $filename;

Database::query('UPDATE dreams SET image_url = ?, ai_generated_image = 0 WHERE id = ?', [$imageUrl, $dreamId]);

$dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$dreamId]);
Helpers::respond(['image_url' => Media::url($dream, 'image', $user['userId'])]);
