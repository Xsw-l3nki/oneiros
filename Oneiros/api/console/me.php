<?php
/** GET /api/console/me — who is signed in to the Console and what their role may do. */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';

Helpers::only(['GET']);
$staff = Staff::require('overview.view');
$cfg = require __DIR__ . '/../../includes/runtime-config.php';
Helpers::respond([
    'user'        => ['id' => $staff['userId'], 'email' => $staff['email'], 'role' => $staff['role']],
    'permissions' => Staff::permissionsFor($staff),
    'app_name'    => $cfg['app_name'],
    'version'     => $cfg['app_version'],
    'environment' => $cfg['environment'],
]);
