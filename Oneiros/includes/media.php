<?php
require_once __DIR__ . '/jwt.php';

/** Private uploads are delivered only through short-lived, signed API URLs. */
class Media
{
    public static function url(array $dream, string $kind, string $viewerId): ?string
    {
        if (empty($dream[$kind . '_url'])) return null;
        $cfg = require __DIR__ . '/runtime-config.php';
        $token = JWT::encode(['purpose' => 'media', 'dreamId' => $dream['id'],
            'viewerId' => $viewerId, 'kind' => $kind], $cfg['jwt_secret'], 3600);
        return self::apiUrl('dreams/' . $dream['id'] . '/media') . '&token=' . rawurlencode($token);
    }

    public static function apiUrl(string $route): string
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        $base = rtrim(parse_url($cfg['frontend_url'], PHP_URL_PATH) ?: '', '/');
        return $base . '/api.php?_route=' . rawurlencode($route);
    }

    public static function path(string $stored): ?string
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        if (str_contains($stored, '://') || str_starts_with($stored, '/')) {
            $urlPath = parse_url($stored, PHP_URL_PATH) ?: '';
            $marker = strpos($urlPath, '/uploads/');
            if ($marker === false) return null;
            $stored = substr($urlPath, $marker + 9);
        }
        $root = realpath($cfg['upload_dir']);
        $path = realpath($cfg['upload_dir'] . '/' . $stored);
        if (!$root || !$path || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root) . '/') || !is_file($path)) return null;
        return $path;
    }

    /** Remove a dream's stored image and recording from disk; the caller deletes the rows. */
    public static function purge(array $dream): void
    {
        foreach (['image_url', 'audio_url'] as $column) {
            $path = !empty($dream[$column]) ? self::path($dream[$column]) : null;
            if ($path) @unlink($path);
        }
    }

    /** Remove every file an account owns, including unused AI paintings. */
    public static function purgeUser(string $userId): void
    {
        foreach (Database::fetchAll('SELECT image_url, audio_url FROM dreams WHERE user_id = ?', [$userId]) as $dream) {
            self::purge($dream);
        }
        $cfg = require __DIR__ . '/runtime-config.php';
        foreach (glob($cfg['upload_dir'] . '/paintings/' . basename($userId) . '/*') ?: [] as $file) {
            if (is_file($file)) @unlink($file);
        }
    }

    public static function attachPaint(string $token, string $dreamId, string $userId): ?string
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        $claim = JWT::decode($token, $cfg['jwt_secret']);
        if (!$claim || ($claim['purpose'] ?? '') !== 'paint' || ($claim['viewerId'] ?? '') !== $userId) return null;
        $source = self::path($claim['path'] ?? '');
        if (!$source) return null;
        $relative = 'dreams/' . $userId . '/' . $dreamId . '-' . bin2hex(random_bytes(6)) . '.png';
        $target = $cfg['upload_dir'] . '/' . $relative;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        return rename($source, $target) ? $relative : null;
    }

    public static function serve(string $path): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif','audio/mpeg','audio/wav','audio/x-wav','audio/ogg','application/ogg','audio/webm','video/webm','audio/mp4','video/mp4'], true)) {
            Helpers::respond(['error' => 'Unsupported media'], 415);
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
