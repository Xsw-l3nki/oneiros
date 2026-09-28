<?php
/**
 * Oneiros — Security: headers, CSRF, rate limiting
 */
class Security
{
    public static function headers(): void
    {
        if (headers_sent()) return;
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(self)');
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob: https://image.pollinations.ai https:",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            // The Lucid checkout form posts to PayFast's hosted payment page
            "form-action 'self' https://www.payfast.co.za https://sandbox.payfast.co.za",
        ]);
        header("Content-Security-Policy: $csp");
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'cookie_secure'   => !empty($_SERVER['HTTPS']),
            ]);
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** File-based rate limiting — no Redis needed on cPanel shared hosting */
    public static function rateLimit(string $key, int $maxHits, int $windowSecs): bool
    {
        $dir = rtrim(sys_get_temp_dir(), '/\\') . '/oneiros_rl_' . substr(hash('sha256', __DIR__), 0, 12) . '/';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $file = $dir . md5($key) . '.json';
        $now  = time();
        $handle = @fopen($file, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) return false;
        $data = json_decode(stream_get_contents($handle), true) ?: [];
        $data = array_values(array_filter($data, static fn($t) => $t > $now - $windowSecs));
        if (count($data) >= $maxHits) { flock($handle, LOCK_UN); fclose($handle); return false; }
        $data[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return true;
    }

    public static function ipKey(): string
    {
        // Forwarded headers are untrusted unless a server-level trusted proxy is configured.
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public static function h(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
