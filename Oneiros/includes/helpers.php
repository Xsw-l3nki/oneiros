<?php
/**
 * Oneiros — Helper functions
 */

// PHP 7.4 polyfills (Afrihost cPanel may run PHP 7.4)
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || (strlen($haystack) >= strlen($needle)
            && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}

// mbstring fallbacks — some cPanel PHP builds ship without mbstring, and a missing
// mb_* function is a fatal error (this is what broke sign-up in v2.x). UTF-8 only.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $encoding = null): int {
        return function_exists('iconv_strlen') ? (int)iconv_strlen($s, 'UTF-8')
            : (int)preg_match_all('/./us', $s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $encoding = null): string {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) $chars = str_split($s); // invalid UTF-8: fall back to bytes
        return implode('', array_slice($chars, $start, $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s, ?string $encoding = null): string {
        return strtolower($s);
    }
}
if (!function_exists('mb_encode_mimeheader')) {
    function mb_encode_mimeheader(string $s, ?string $charset = null, ?string $transfer = null, string $newline = "\r\n", int $indent = 0): string {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}

class Helpers
{
    /** Stop with 403 when a Console feature switch is off. */
    public static function requireFeature(string $feature, string $message): void
    {
        require_once __DIR__ . '/settings.php';
        if (!Settings::feature($feature)) self::respond(['error' => $message, 'feature_off' => $feature], 403);
    }

    /**
     * Respond with JSON and exit.
     */
    public static function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Get JSON body from request.
     */
    public static function input(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw || !empty($_POST)) return $_POST ?? [];
        $data = json_decode($raw, true);
        if (!is_array($data)) self::respond(['error' => 'Request body must be valid JSON'], 400);
        return $data;
    }

    /**
     * Generate a UUID v4.
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Validate email format.
     */
    public static function isEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Calculate age from date of birth.
     */
    public static function age(string $dob): int
    {
        try {
            $birth = DateTime::createFromFormat('!Y-m-d', $dob);
            $today = new DateTime('today');
            if (!$birth || $birth->format('Y-m-d') !== $dob || $birth > $today) return 0;
            return $birth->diff($today)->y;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Encode array as JSON for storage in TEXT column.
     */
    public static function encodeArray(?array $arr): ?string
    {
        if ($arr === null || count($arr) === 0) return null;
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode JSON column to array.
     */
    public static function decodeArray($value): array
    {
        if (!$value) return [];
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decode JSON column to associative array.
     */
    public static function decodeObject($value): array
    {
        if (!$value) return [];
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Format dream record for JSON response.
     */
    public static function formatDream(array $dream): array
    {
        $dream['emotions']      = self::decodeArray($dream['emotions'] ?? null);
        $dream['themes']        = self::decodeArray($dream['themes'] ?? null);
        $dream['symbols']       = self::decodeArray($dream['symbols'] ?? null);
        $dream['emotion_score'] = self::decodeObject($dream['emotion_score'] ?? null);
        $dream['ai_generated_image'] = (bool)($dream['ai_generated_image'] ?? false);
        $dream['is_recurring']  = (bool)($dream['is_recurring'] ?? false);
        $dream['is_flagged']    = (bool)($dream['is_flagged'] ?? false);
        $dream['is_removed']    = (bool)($dream['is_removed'] ?? false);
        if (!empty($dream['image_url']) || !empty($dream['audio_url'])) {
            require_once __DIR__ . '/media.php';
            require_once __DIR__ . '/auth.php';
            $viewer = Auth::user();
            $dream['image_url'] = $viewer ? Media::url($dream, 'image', $viewer['userId']) : null;
            $dream['audio_url'] = $viewer ? Media::url($dream, 'audio', $viewer['userId']) : null;
        }
        return $dream;
    }

    /**
     * Update last active timestamp for a user.
     */
    public static function touchUser(string $userId): void
    {
        try {
            Database::query('UPDATE users SET last_active_at = NOW() WHERE id = ?', [$userId]);
        } catch (Exception $e) {
            // Silent fail - non-critical
        }
    }

    /**
     * Validate and require fields from input.
     */
    public static function require_fields(array $input, array $fields): void
    {
        $missing = [];
        foreach ($fields as $f) {
            if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === null || !is_scalar($input[$f])) $missing[] = $f;
        }
        if (count($missing) > 0) {
            self::respond(['error' => 'Missing required fields', 'fields' => $missing], 400);
        }
    }

    /**
     * Allow only specific HTTP methods.
     */
    public static function only(array $methods): void
    {
        $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($current, $methods)) {
            header('Allow: ' . implode(', ', $methods));
            self::respond(['error' => 'Method not allowed'], 405);
        }
    }
}
