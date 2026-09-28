<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/helpers.php';
Helpers::only(['GET']);
$cfg = require __DIR__ . '/../includes/runtime-config.php';

// Required: the app cannot serve requests without these.
// Recommended: the app runs without them (fallbacks exist), but they should be enabled in cPanel.
$required    = ['pdo', 'pdo_mysql', 'json'];
$recommended = ['mbstring', 'fileinfo', 'curl'];
$missingRequired    = array_values(array_filter($required, fn($e) => !extension_loaded($e)));
$missingRecommended = array_values(array_filter($recommended, fn($e) => !extension_loaded($e)));

Helpers::respond([
    'status'     => $missingRequired ? 'degraded' : 'healthy',
    'service'    => 'Oneiros',
    'version'    => '2.1.0',
    'timestamp'  => date('c'),
    'php'        => PHP_VERSION,
    'extensions' => [
        'missing_required'    => $missingRequired,
        'missing_recommended' => $missingRecommended,
    ],
], $missingRequired ? 503 : 200);
