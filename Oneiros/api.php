<?php
/**
 * Oneiros — API Front Controller
 *
 * Routes all /api/* requests to the appropriate PHP handler.
 * Designed to work even on cPanel hosting with limited mod_rewrite support.
 */

// Always return JSON, never HTML errors
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);  // E_STRICT is part of E_ALL since PHP 8 and deprecated in 8.4

set_exception_handler(function($e) {
    $cfg = @include __DIR__ . '/includes/runtime-config.php';
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'detail' => ($cfg['debug'] ?? false) ? $e->getMessage() : 'Check error log'
    ]);
    error_log('Oneiros uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        error_log('Oneiros fatal: ' . $err['message']);
        echo json_encode(['error' => 'Server error. Please try again.']);
    }
});

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/helpers.php';

// Get the route — try multiple methods for cPanel compatibility
$route = $_GET['_route'] ?? '';

// If no rewrite happened, try parsing the URL ourselves
if (!$route) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/') !== false) {
        $route = preg_replace('#^.*/api/#', '', strtok($uri, '?'));
    }
}

$route = trim($route, '/');
if (!$route) {
    Helpers::respond(['error' => 'No route specified'], 400);
}

// Strip and store query string params back into $_GET
$queryStart = strpos($route, '?');
if ($queryStart !== false) {
    $queryStr = substr($route, $queryStart + 1);
    parse_str($queryStr, $extra);
    $_GET = array_merge($_GET, $extra);
    $route = substr($route, 0, $queryStart);
}

unset($_GET['_route']);

$segments = explode('/', $route);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ════════════════════════════════════════════════════════════
// ROUTING TABLE
// ════════════════════════════════════════════════════════════

$file = null;

switch ($segments[0] ?? '') {

    // ─── /api/health ───
    case 'health':
        $file = __DIR__ . '/api/health.php';
        break;

    case 'capabilities':
        $file = __DIR__ . '/api/capabilities.php';
        break;

    // ─── /api/auth/* ───
    case 'auth':
        $sub = $segments[1] ?? '';
        // Rate-limit auth endpoints: 20 requests per 10 minutes per IP
        if (in_array($sub, ['login','register'])) {
            require_once __DIR__ . '/includes/security.php';
            $ip = Security::ipKey();
            if (!Security::rateLimit("auth_{$sub}_{$ip}", 20, 600)) {
                Helpers::respond(['error' => 'Too many requests. Please wait before trying again.'], 429);
            }
        }
        $valid = ['register','login','refresh','logout','me','profile','password','account'];
        if (in_array($sub, $valid)) {
            $file = __DIR__ . "/api/auth/$sub.php";
        }
        break;

    // ─── /api/dreams ───
    // /api/dreams                           → list/create
    // /api/dreams/:id                       → get/update/delete
    // /api/dreams/:id/matches               → matches for dream
    // /api/dreams/:id/image                 → upload image
    // /api/dreams/:id/privacy               → update privacy
    // /api/dreams/:id/flag                  → flag content
    case 'dreams':
        if (($segments[1] ?? '') === 'paint') {
            $file = __DIR__ . '/api/dreams/paint.php';
        } elseif (!isset($segments[1])) {
            $file = __DIR__ . '/api/dreams/index.php';
        } else {
            $dreamId = $segments[1];
            $action = $segments[2] ?? null;

            if ($action === 'media') {
                $_GET['id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/media.php';
            } elseif ($action === 'matches') {
                $_GET['dream_id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/matches.php';
            } elseif ($action === 'image') {
                $_GET['dream_id'] = $dreamId;
                $_POST['dream_id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/image.php';
            } elseif ($action === 'privacy') {
                $_GET['id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/privacy.php';
            } elseif ($action === 'flag') {
                $_GET['id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/flag.php';
            } else {
                $_GET['id'] = $dreamId;
                $file = __DIR__ . '/api/dreams/single.php';
            }
        }
        break;

    // ─── /api/matches ───
    case 'matches':
        $file = __DIR__ . '/api/dreams/matches.php';
        break;

    // ─── /api/connections ───
    case 'connections':
        if (!isset($segments[1])) {
            $file = __DIR__ . '/api/connections/index.php';
        } elseif ($segments[1] === 'request') {
            $file = __DIR__ . '/api/connections/index.php';
            // POST to /api/connections/request → POST to /api/connections (same handler)
        } elseif ($segments[1] === 'block') {
            $_GET['action'] = 'block';
            $file = __DIR__ . '/api/connections/manage.php';
        } else {
            $connId = $segments[1];
            $action = $segments[2] ?? null;

            if ($action === 'accept') {
                $_GET['id'] = $connId;
                $_GET['action'] = 'accept';
                $file = __DIR__ . '/api/connections/manage.php';
            } elseif ($action === 'reject') {
                $_GET['id'] = $connId;
                $_GET['action'] = 'reject';
                $file = __DIR__ . '/api/connections/manage.php';
            } elseif ($action === 'messages') {
                $_GET['connection_id'] = $connId;
                $file = __DIR__ . '/api/connections/messages.php';
            }
        }
        break;

    // ─── /api/notifications ───
    case 'notifications':
        $file = __DIR__ . '/api/notifications/index.php';
        break;

    // ─── /api/research/* ───
    case 'research':
        $action = $segments[1] ?? '';
        if ($action === 'global' || $action === 'me' || $action === 'public' || $action === 'map') {
            $file = __DIR__ . "/api/research/$action.php";
        }
        break;

    // ─── /api/admin/* ───
    case 'admin':
        $sub = $segments[1] ?? '';
        if ($sub === 'users') {
            if (isset($segments[2])) $_GET['id'] = $segments[2];
            $file = __DIR__ . '/api/admin/users.php';
        } elseif ($sub === 'flags') {
            if (isset($segments[2])) $_GET['id'] = $segments[2];
            $file = __DIR__ . '/api/admin/flags.php';
        } elseif ($sub === 'stats') {
            $file = __DIR__ . '/api/admin/stats.php';
        } elseif (in_array($sub, ['revenue', 'codes', 'premium'], true)) {
            $file = __DIR__ . "/api/admin/$sub.php";
        } elseif ($sub === 'email') {
            $_GET['action'] = $segments[2] ?? '';
            $file = __DIR__ . '/api/admin/email.php';
        } elseif ($sub === 'dreams' && isset($segments[2]) && ($segments[3] ?? '') === 'privacy') {
            $_GET['id'] = $segments[2];
            $file = __DIR__ . '/api/dreams/privacy.php';
        }
        break;

    // ─── /api/console/* — staff Console (see includes/staff.php for permissions) ───
    case 'console':
        $sub = $segments[1] ?? '';
        if (in_array($sub, ['me', 'overview', 'health', 'settings', 'users', 'staff', 'audit', 'email'], true)) {
            if (isset($segments[2]) && $sub === 'users') $_GET['id'] = $segments[2];
            $file = __DIR__ . "/api/console/$sub.php";
        }
        break;

    // ─── /api/streaks/* ───
    // GET /streaks/me, /streaks/badges/me, /streaks/leaderboard
    case 'streaks':
        $sub = $segments[1] ?? 'me';
        $_GET['sub'] = $sub;
        if ($sub === 'badges' && isset($segments[2])) {
            $_GET['sub2'] = $segments[2];
        }
        $file = __DIR__ . '/api/streaks/index.php';
        break;

    // ─── /api/audio/* ───
    case 'audio':
        if (($segments[1] ?? '') === 'upload') {
            $file = __DIR__ . '/api/audio/upload.php';
        }
        break;

    // ─── /api/payments/* — once-off Lucid passes ───
    case 'payments':
        $sub = $segments[1] ?? '';
        $payments = ['plans', 'status', 'quote', 'checkout', 'order', 'cancel', 'redeem', 'payfast-itn', 'paystack-webhook'];
        if (in_array($sub, $payments, true)) $file = __DIR__ . "/api/payments/$sub.php";
        break;

    // ─── /api/push/* (placeholder) ───
    case 'push':
        $sub = $segments[1] ?? '';
        if ($sub === 'vapid-key') {
            Helpers::respond(['key' => null]);
        } else {
            Helpers::respond(['message' => 'Push notifications not configured']);
        }
}

// ════════════════════════════════════════════════════════════
// EXECUTE OR 404
// ════════════════════════════════════════════════════════════

if ($file && file_exists($file)) {
    require $file;
    exit;
}

Helpers::respond([
    'error' => 'Route not found',
    'route' => $route,
    'method' => $method,
], 404);
