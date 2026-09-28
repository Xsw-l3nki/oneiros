<?php
/**
 * Oneiros — CORS Handler
 * Sets up cross-origin headers and handles preflight.
 */

// Inline polyfill for PHP 7.4
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || (strlen($haystack) >= strlen($needle)
            && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}

$cfg = require __DIR__ . '/runtime-config.php';

$parts = parse_url($cfg['frontend_url']);
$allowedOrigin = isset($parts['scheme'], $parts['host'])
    ? $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') : '';
$origin        = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && $origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Disable caching of API responses
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
