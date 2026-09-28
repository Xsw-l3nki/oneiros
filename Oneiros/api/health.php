<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/helpers.php';
Helpers::only(['GET']);
$cfg = require __DIR__ . '/../includes/runtime-config.php';

Helpers::respond([
    'status'    => 'healthy',
    'service'   => 'Oneiros',
    'version'   => '2.1.0',
    'timestamp' => date('c'),
]);
