<?php
/**
 * /api/console/staff (staff.manage — admins only)
 *   GET                         everyone with a staff role
 *   POST {email, role}          give an existing account the moderator or admin role
 * Removing or changing a role uses POST /api/console/users?id=&action=role.
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';

Helpers::only(['GET', 'POST']);
$staff = Staff::require('staff.manage');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = Database::fetchAll('SELECT id, email, display_name, is_admin, is_active, last_active_at, created_at FROM users
        WHERE is_admin = 1 OR is_moderator = 1 ORDER BY is_admin DESC, email');
    Helpers::respond(['staff' => array_map(fn($r) => [
        'id' => $r['id'], 'email' => $r['email'], 'display_name' => $r['display_name'],
        'role' => $r['is_admin'] ? 'admin' : 'moderator', 'is_active' => (bool)$r['is_active'],
        'last_active_at' => $r['last_active_at'] ? gmdate('c', strtotime($r['last_active_at'] . ' UTC')) : null,
    ], $rows), 'permissions' => Staff::PERMISSIONS]);
}

$input = Helpers::input();
$email = strtolower(trim((string)($input['email'] ?? '')));
$role = (string)($input['role'] ?? 'moderator');
if (!in_array($role, ['moderator', 'admin'], true)) Helpers::respond(['error' => 'Role must be moderator or admin'], 422);
$user = Database::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
if (!$user) Helpers::respond(['error' => 'No account uses that email. Ask them to sign up in the app first.'], 404);
if (!$user['is_active']) Helpers::respond(['error' => 'That account is suspended. Reactivate it first.'], 400);
if ($user['id'] === $staff['userId']) Helpers::respond(['error' => 'You cannot change your own role'], 400);
Database::update('users', ['is_admin' => (int)($role === 'admin'), 'is_moderator' => 1], 'id', $user['id']);
Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$user['id']]);
$from = $user['is_admin'] ? 'admin' : ($user['is_moderator'] ? 'moderator' : 'dreamer');
Staff::audit($staff, 'staff.role', "Made {$user['email']} $role (was $from)", 'user', $user['id'], ['from' => $from, 'to' => $role]);
Helpers::respond(['message' => "{$user['email']} is now $role. They sign in at the Console with their usual password."]);
