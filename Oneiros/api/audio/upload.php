<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/streaks.php';
Helpers::only(['POST']);
$user = Auth::require();
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
$dreamId = (string)($_POST['dream_id'] ?? '');
$dream = Database::fetchOne('SELECT * FROM dreams WHERE id = ? AND user_id = ? AND is_removed = 0', [$dreamId, $user['userId']]);
if (!$dream) Helpers::respond(['error' => 'Dream not found'], 404);
$file = $_FILES['audio'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) Helpers::respond(['error' => 'No recording uploaded'], 400);
if ($file['size'] > $cfg['max_file_size']) Helpers::respond(['error' => 'Recording must be under 10 MB'], 400);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$types = ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
    'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/webm' => 'webm',
    'video/webm' => 'webm', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a'];
if (!isset($types[$mime])) Helpers::respond(['error' => 'Unsupported recording format'], 400);
$relative = 'audio/' . $user['userId'] . '/' . $dreamId . '-' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
$path = $cfg['upload_dir'] . '/' . $relative;
if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
if (!move_uploaded_file($file['tmp_name'], $path)) Helpers::respond(['error' => 'Could not save recording'], 500);
Database::query('UPDATE dreams SET audio_url = ? WHERE id = ?', [$relative, $dreamId]);
Database::insert('dream_audio', ['id' => Helpers::uuid(), 'dream_id' => $dreamId, 'user_id' => $user['userId'],
    'audio_url' => $relative, 'transcript' => mb_substr((string)($_POST['transcript'] ?? ''), 0, 10000) ?: null,
    'duration_seconds' => isset($_POST['duration_seconds']) ? max(0, min(3600, (int)$_POST['duration_seconds'])) : null]);
Badge::check($user['userId'], ['used_voice' => true]);
$dream['audio_url'] = $relative;
Helpers::respond(['audio_url' => Media::url($dream, 'audio', $user['userId'])], 201);
