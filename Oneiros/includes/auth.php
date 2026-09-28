<?php
/**
 * Oneiros — Authentication Helpers
 */

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/db.php';

class Auth
{
    /**
     * Get the authenticated user from the request, or null.
     */
    public static function user(): ?array
    {
        $token = self::bearerToken();
        if (!$token) return null;

        $cfg = require __DIR__ . '/runtime-config.php';
        $payload = JWT::decode($token, $cfg['jwt_secret']);
        if (!$payload || empty($payload['userId'])) return null;
        // Check the live account so suspensions, deletions, and role changes apply immediately.
        $account = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$payload['userId']]);
        if (!$account || !$account['is_active']) return null;
        $premiumUntil = $account['premium_until'] ?? null;

        return [
            'userId'      => $account['id'],
            'email'       => $account['email'],
            'isAdmin'     => (bool)$account['is_admin'],
            'isModerator' => (bool)$account['is_moderator'],
            'isPremium'   => $premiumUntil !== null && strtotime($premiumUntil . ' UTC') > time(),
        ];
    }

    /**
     * Require authentication or 401.
     */
    public static function require(): array
    {
        $user = self::user();
        if (!$user) Helpers::respond(['error' => 'Unauthorized'], 401);
        // Keep "dreaming now" honest: at most one write every two minutes per dreamer
        try {
            Database::query('UPDATE users SET last_active_at = NOW() WHERE id = ? AND (last_active_at IS NULL OR last_active_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))', [$user['userId']]);
        } catch (Throwable $e) {}
        return $user;
    }

    /**
     * Require admin role or 403.
     */
    public static function requireAdmin(): array
    {
        $user = self::require();
        if (!$user['isAdmin']) Helpers::respond(['error' => 'Admin access required'], 403);
        return $user;
    }

    /**
     * Require moderator role or 403.
     */
    public static function requireModerator(): array
    {
        $user = self::require();
        if (!$user['isModerator'] && !$user['isAdmin']) {
            Helpers::respond(['error' => 'Moderator access required'], 403);
        }
        return $user;
    }

    /**
     * Generate a new access token + refresh token pair.
     */
    public static function generateTokens(array $user): array
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        if (strlen($cfg['jwt_secret']) < 32 || strlen($cfg['jwt_refresh_secret']) < 32
            || str_contains($cfg['jwt_secret'], 'CHANGE_ME') || str_contains($cfg['jwt_refresh_secret'], 'CHANGE_ME')) {
            Helpers::respond(['error' => 'Setup incomplete: configure both signing secrets before creating accounts.'], 503);
        }
        $payload = [
            'userId'      => $user['id'],
            'email'       => $user['email'],
            'isAdmin'     => (bool)($user['is_admin'] ?? false),
            'isModerator' => (bool)($user['is_moderator'] ?? false),
        ];

        $accessToken  = JWT::encode($payload, $cfg['jwt_secret'], $cfg['jwt_access_ttl']);
        $refreshToken = JWT::encode($payload, $cfg['jwt_refresh_secret'], $cfg['jwt_refresh_ttl']);

        // Store refresh token hash in DB
        Database::insert('refresh_tokens', [
            'id'         => Helpers::uuid(),
            'user_id'    => $user['id'],
            'token_hash' => JWT::hash($refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + $cfg['jwt_refresh_ttl']),
        ]);

        return ['access_token' => $accessToken, 'refresh_token' => $refreshToken];
    }

    /**
     * Strip sensitive fields from a user record.
     */
    public static function safeUser(array $user): array
    {
        return [
            'id'           => $user['id'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'] ?? null,
            'region'       => $user['region'] ?? null,
            'region_code'  => $user['region_code'] ?? null,
            'is_premium'   => !empty($user['premium_until']) && strtotime($user['premium_until'] . ' UTC') > time(),
            'premium_until'=> !empty($user['premium_until']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($user['premium_until'] . ' UTC')) : null,
            'is_patron'    => (bool)($user['is_patron'] ?? false),
            'referral_code'=> $user['referral_code'] ?? null,
            'is_moderator' => (bool)($user['is_moderator'] ?? false),
            'is_admin'     => (bool)($user['is_admin'] ?? false),
            'created_at'   => $user['created_at'] ?? null,
        ];
    }

    private static function bearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
