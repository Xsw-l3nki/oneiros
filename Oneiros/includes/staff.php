<?php
/**
 * Oneiros — Console staff roles, permissions and the audit log.
 *
 * Roles: admin (everything) and moderator (runs the site day to day; cannot change core
 * or financial settings, see money, or manage staff). The permission list below is the one
 * place that decides what each role may do; Console endpoints call Staff::require('perm').
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

class Staff
{
    public const PERMISSIONS = [
        'overview.view'      => ['admin', 'moderator'],
        'health.view'        => ['admin', 'moderator'],
        'health.migrate'     => ['admin'],
        'health.logs'        => ['admin'],
        'settings.view'      => ['admin', 'moderator'],
        'settings.edit'      => ['admin', 'moderator'],   // tier limits apply, see Settings::editableTiers
        'users.view'         => ['admin', 'moderator'],
        'users.suspend'      => ['admin', 'moderator'],   // not other staff accounts
        'users.signout'      => ['admin', 'moderator'],
        'users.edit'         => ['admin'],
        'users.delete'       => ['admin'],
        'users.lucid'        => ['admin'],                // financial
        'staff.manage'       => ['admin'],
        'moderation.manage'  => ['admin', 'moderator'],
        'email.process'      => ['admin', 'moderator'],
        'audit.view_all'     => ['admin'],
        'audit.view_own'     => ['admin', 'moderator'],
        'finance.view'       => ['admin'],
    ];

    public static function role(array $user): ?string
    {
        if (!empty($user['isAdmin'])) return 'admin';
        if (!empty($user['isModerator'])) return 'moderator';
        return null;
    }

    public static function can(array $user, string $permission): bool
    {
        $role = self::role($user);
        return $role !== null && in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    /** Authenticate a staff member and check one permission, or respond 401/403. */
    public static function require(string $permission): array
    {
        $user = Auth::require();
        if (!self::role($user)) Helpers::respond(['error' => 'Staff access required'], 403);
        if (!self::can($user, $permission)) Helpers::respond(['error' => 'Your role does not allow this action'], 403);
        $user['role'] = self::role($user);
        return $user;
    }

    /** Everything this role may do, for the Console to show or hide controls. */
    public static function permissionsFor(array $user): array
    {
        return array_values(array_filter(array_keys(self::PERMISSIONS), fn($p) => self::can($user, $p)));
    }

    /** Record a staff action. Never throws: an audit failure is logged, not shown. */
    public static function audit(array $actor, string $action, string $summary, ?string $targetType = null, ?string $targetId = null, array $details = []): void
    {
        try {
            Database::query(
                'INSERT INTO audit_log (actor_id, actor_email, actor_role, action, target_type, target_id, summary, details, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$actor['userId'] ?? null, $actor['email'] ?? null, self::role($actor) ?? 'system', $action, $targetType, $targetId,
                 mb_substr($summary, 0, 500), $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                 Security::ipKey()]
            );
        } catch (Throwable $e) {
            error_log('Oneiros audit write failed: ' . $e->getMessage());
        }
    }
}
