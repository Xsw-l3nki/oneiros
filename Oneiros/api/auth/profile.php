<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Helpers::only(['PATCH', 'POST']);
$user = Auth::require();
$input = Helpers::input();

$updates = [];
if (isset($input['display_name'])) $updates['display_name'] = substr(trim($input['display_name']), 0, 60);
if (isset($input['region']))       $updates['region']       = substr(trim($input['region']), 0, 100);
if (isset($input['region_code']))  $updates['region_code']  = strtoupper(substr(trim($input['region_code']), 0, 2));

if (count($updates) === 0) Helpers::respond(['error' => 'No updates provided'], 400);

Database::update('users', $updates, 'id', $user['userId']);
$updated = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$user['userId']]);

Helpers::respond(['user' => Auth::safeUser($updated)]);
