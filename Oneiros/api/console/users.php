<?php
/**
 * /api/console/users
 *   GET  ?q=&filter=&page=         search and list accounts (users.view)
 *   GET  ?id=                      one account with activity (users.view; orders need finance.view)
 *   POST ?id=&action=...           act on an account; the permission depends on the action:
 *        suspend | reactivate      users.suspend (moderators cannot act on staff accounts)
 *        signout                   users.signout — ends every saved session
 *        edit {display_name,email} users.edit
 *        role {role}               staff.manage — none | moderator | admin
 *        lucid_grant {days}        users.lucid
 *        lucid_end                 users.lucid
 *        delete {confirm_email}    users.delete — also removes the account's uploaded files
 * Every action is written to the audit log.
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/premium.php';
require_once __DIR__ . '/../../includes/media.php';

Helpers::only(['GET', 'POST']);
$id = isset($_GET['id']) ? (string)$_GET['id'] : null;

function consoleUser(array $u): array
{
    return [
        'id' => $u['id'], 'email' => $u['email'], 'display_name' => $u['display_name'],
        'region' => $u['region'], 'region_code' => $u['region_code'],
        'is_active' => (bool)$u['is_active'], 'is_admin' => (bool)$u['is_admin'], 'is_moderator' => (bool)$u['is_moderator'],
        'role' => $u['is_admin'] ? 'admin' : ($u['is_moderator'] ? 'moderator' : 'dreamer'),
        'is_lucid' => Premium::isActiveUntil($u['premium_until']), 'premium_until' => Premium::iso($u['premium_until']),
        'is_patron' => (bool)$u['is_patron'],
        'last_active_at' => $u['last_active_at'] ? gmdate('c', strtotime($u['last_active_at'] . ' UTC')) : null,
        'created_at' => gmdate('c', strtotime($u['created_at'] . ' UTC')),
    ];
}

// ─── List ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$id) {
    Staff::require('users.view');
    $where = [];
    $params = [];
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(email LIKE ? OR display_name LIKE ? OR id = ?)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $like, $like, $q);
    }
    $filters = [
        'active' => 'is_active = 1', 'suspended' => 'is_active = 0',
        'staff' => '(is_admin = 1 OR is_moderator = 1)', 'lucid' => 'premium_until > UTC_TIMESTAMP()',
        'new' => 'created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY',
    ];
    $filter = (string)($_GET['filter'] ?? 'all');
    if (isset($filters[$filter])) $where[] = $filters[$filter];
    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)Database::fetchOne("SELECT COUNT(*) c FROM users$sql", $params)['c'];
    $rows = Database::fetchAll("SELECT * FROM users$sql ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
    Helpers::respond(['users' => array_map('consoleUser', $rows), 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $perPage))]);
}

$user = $id ? Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]) : null;
if (!$user) Helpers::respond(['error' => 'Account not found'], 404);

// ─── Detail ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $staff = Staff::require('users.view');
    $one = fn(string $sql) => (int)(Database::fetchOne($sql, [$id])['c'] ?? 0);
    $detail = consoleUser($user) + [
        'activity' => [
            'dreams'         => $one('SELECT COUNT(*) c FROM dreams WHERE user_id = ?'),
            'dreams_removed' => $one('SELECT COUNT(*) c FROM dreams WHERE user_id = ? AND is_removed = 1'),
            'reports_against'=> $one('SELECT COUNT(*) c FROM moderation_flags f JOIN dreams d ON d.id = f.dream_id WHERE d.user_id = ?'),
            'connections'    => (int)(Database::fetchOne("SELECT COUNT(*) c FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'connected'", [$id, $id])['c'] ?? 0),
            'sessions'       => $one('SELECT COUNT(*) c FROM refresh_tokens WHERE user_id = ? AND expires_at > NOW()'),
            'current_streak' => (int)$user['current_streak'],
        ],
        'history' => Database::fetchAll('SELECT actor_email, action, summary, created_at FROM audit_log WHERE target_type = ? AND target_id = ? ORDER BY id DESC LIMIT 20', ['user', $id]),
    ];
    if (Staff::can($staff, 'finance.view')) {
        $detail['orders'] = Database::fetchAll('SELECT id, plan_name, amount_cents, status, provider, created_at, paid_at FROM premium_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [$id]);
    }
    Helpers::respond(['user' => $detail]);
}

// ─── Actions ───
$action = (string)($_GET['action'] ?? '');
$input = Helpers::input();
$permissionFor = [
    'suspend' => 'users.suspend', 'reactivate' => 'users.suspend', 'signout' => 'users.signout',
    'edit' => 'users.edit', 'role' => 'staff.manage', 'lucid_grant' => 'users.lucid', 'lucid_end' => 'users.lucid',
    'delete' => 'users.delete',
];
if (!isset($permissionFor[$action])) Helpers::respond(['error' => 'Unknown action'], 400);
$staff = Staff::require($permissionFor[$action]);
$isSelf = $id === $staff['userId'];
$targetIsStaff = $user['is_admin'] || $user['is_moderator'];
$label = $user['email'];

if ($targetIsStaff && $staff['role'] !== 'admin') Helpers::respond(['error' => 'Only an admin can act on staff accounts'], 403);

switch ($action) {
    case 'suspend':
    case 'reactivate':
        if ($isSelf) Helpers::respond(['error' => 'You cannot suspend your own account'], 400);
        $active = $action === 'reactivate';
        Database::update('users', ['is_active' => (int)$active], 'id', $id);
        if (!$active) Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$id]);
        $reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 300);
        Staff::audit($staff, "user.$action", ($active ? 'Reactivated ' : 'Suspended ') . $label . ($reason ? " — $reason" : ''), 'user', $id, ['reason' => $reason]);
        break;

    case 'signout':
        $ended = Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$id])->rowCount();
        Staff::audit($staff, 'user.signout', "Signed $label out of $ended session(s)", 'user', $id);
        break;

    case 'edit':
        $changes = [];
        if (array_key_exists('display_name', $input)) {
            $changes['display_name'] = mb_substr(trim((string)$input['display_name']), 0, 60) ?: null;
        }
        if (array_key_exists('email', $input)) {
            $email = strtolower(trim((string)$input['email']));
            if (!Helpers::isEmail($email)) Helpers::respond(['error' => 'Invalid email'], 422);
            if ($email !== $user['email'] && Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
                Helpers::respond(['error' => 'Another account already uses that email'], 409);
            }
            $changes['email'] = $email;
        }
        if (!$changes) Helpers::respond(['error' => 'Nothing to change'], 400);
        Database::update('users', $changes, 'id', $id);
        $before = array_intersect_key($user, $changes);
        Staff::audit($staff, 'user.edit', "Edited $label: " . implode(', ', array_keys($changes)), 'user', $id, ['from' => $before, 'to' => $changes]);
        break;

    case 'role':
        $role = (string)($input['role'] ?? '');
        if (!in_array($role, ['dreamer', 'moderator', 'admin'], true)) Helpers::respond(['error' => 'Role must be dreamer, moderator or admin'], 422);
        if ($isSelf) Helpers::respond(['error' => 'You cannot change your own role'], 400);
        if (!$user['is_active']) Helpers::respond(['error' => 'Reactivate the account before giving it a role'], 400);
        if ($user['is_admin'] && $role !== 'admin') {
            $admins = (int)Database::fetchOne('SELECT COUNT(*) c FROM users WHERE is_admin = 1 AND is_active = 1')['c'];
            if ($admins <= 1) Helpers::respond(['error' => 'Oneiros must keep at least one admin'], 400);
        }
        Database::update('users', ['is_admin' => (int)($role === 'admin'), 'is_moderator' => (int)($role !== 'dreamer')], 'id', $id);
        // Roles are read live on every request, but end sessions so the app reloads them cleanly.
        Database::query('DELETE FROM refresh_tokens WHERE user_id = ?', [$id]);
        $from = $user['is_admin'] ? 'admin' : ($user['is_moderator'] ? 'moderator' : 'dreamer');
        Staff::audit($staff, 'staff.role', "Changed $label from $from to $role", 'user', $id, ['from' => $from, 'to' => $role]);
        break;

    case 'lucid_grant':
        $days = (int)($input['days'] ?? 0);
        if ($days < 1 || $days > 3650) Helpers::respond(['error' => 'Days must be between 1 and 3650'], 422);
        Premium::extend($id, $days);
        $status = Premium::status($id);
        Staff::audit($staff, 'user.lucid_grant', "Granted $label $days Lucid days (until {$status['premium_until']})", 'user', $id, ['days' => $days]);
        break;

    case 'lucid_end':
        Premium::reduce($id, null);
        Staff::audit($staff, 'user.lucid_end', "Ended the Lucid pass of $label", 'user', $id);
        break;

    case 'delete':
        if ($isSelf) Helpers::respond(['error' => 'Use account settings in the app to delete your own account'], 400);
        if (strtolower(trim((string)($input['confirm_email'] ?? ''))) !== $user['email']) {
            Helpers::respond(['error' => 'Type the account email exactly to confirm deletion'], 422);
        }
        if ($user['is_admin']) Helpers::respond(['error' => 'Remove admin rights before deleting this account'], 400);
        Media::purgeUser($id);
        Database::query('DELETE FROM research_events WHERE created_by = ?', [$id]);
        Database::delete('users', 'id', $id);
        Staff::audit($staff, 'user.delete', "Deleted the account $label and its files", 'user', $id,
            ['reason' => mb_substr(trim((string)($input['reason'] ?? '')), 0, 300)]);
        Helpers::respond(['deleted' => true]);
}

$fresh = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
Helpers::respond(['user' => consoleUser($fresh)]);
