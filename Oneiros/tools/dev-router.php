<?php
/** Local PHP development router. Production uses .htaccess. */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#^/(?:includes|uploads|sql|tools|tests|docs|RESUME-HERE|\.git|\.firecrawl|dist|artifacts)(?:/|$)#i', $path)
    || preg_match('#(?:^/diagnostic\.php$|error_log$|\.(?:zip|sql|log|bak|old|env)$)#i', $path)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
if (preg_match('#^/api/(.+)$#', $path, $match)) {
    $_GET['_route'] = $match[1] === 'health.php' ? 'health' : $match[1];
    require $root . '/api.php'; return true;
}
if (preg_match('#^/(oneiros(?:-admin|-moderation)?)\.html$#', $path, $match)) {
    header('Location: /' . $match[1] . '.php', true, 302); return true;
}
if ($path === '/') { require $root . '/index.php'; return true; }
$target = realpath($root . $path);
if ($target && str_starts_with($target, $root . DIRECTORY_SEPARATOR) && is_file($target)) return false;
http_response_code(404); echo 'Not found'; return true;
