<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/premium.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/media.php';

$currentAdmin = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

if ($method === 'GET' && !$id) {
    $users = Database::fetchAll(
        'SELECT id, email, display_name, region, region_code, is_active, premium_until, is_patron, is_moderator, is_admin, last_active_at, created_at
           FROM users ORDER BY created_at DESC'
    );
    foreach ($users as $idx => $u) {
        $users[$idx]['is_active']    = (bool)$u['is_active'];
        $users[$idx]['is_premium']   = Premium::isActiveUntil($u['premium_until']);
        $users[$idx]['premium_until']= Premium::iso($u['premium_until']);
        $users[$idx]['is_patron']    = (bool)$u['is_patron'];
        $users[$idx]['is_moderator'] = (bool)$u['is_moderator'];
        $users[$idx]['is_admin']     = (bool)$u['is_admin'];
    }
    Helpers::respond(['users' => $users]);
}

if (($method === 'PATCH' || $method === 'POST') && $id) {
    if (!Database::fetchOne('SELECT id FROM users WHERE id = ?', [$id])) Helpers::respond(['error' => 'User not found'], 404);
    $input = Helpers::input();
    $updates = [];
    if (isset($input['is_active']))    $updates['is_active']    = (int)(bool)$input['is_active'];
    if (isset($input['is_moderator'])) $updates['is_moderator'] = (int)(bool)$input['is_moderator'];
    // Premium is a dated pass: switching it on grants 30 days, switching it off ends the pass
    if (isset($input['is_premium'])) {
        if ($input['is_premium'] && !Premium::isActive($id)) Premium::extend($id, 30);
        if (!$input['is_premium']) Premium::reduce($id, null);
    }
    if ($id === $currentAdmin['userId'] && isset($updates['is_active']) && !$updates['is_active']) Helpers::respond(['error' => 'You cannot suspend your own admin account.'], 400);

    if (count($updates) > 0) {
        Database::update('users', $updates, 'id', $id);
    }
    $changed = array_intersect_key($input, array_flip(['is_active', 'is_moderator', 'is_premium']));
    if ($changed) {
        $target = Database::fetchOne('SELECT email FROM users WHERE id = ?', [$id]);
        Staff::audit($currentAdmin, 'user.update', 'Observatory: updated ' . ($target['email'] ?? $id) . ' (' . implode(', ', array_map(fn($k, $v) => $k . '=' . ($v ? 'on' : 'off'), array_keys($changed), $changed)) . ')', 'user', $id, $changed);
    }

    $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    Helpers::respond(['user' => Auth::safeUser($user)]);
}

if ($method === 'DELETE' && $id) {
    if ($id === $currentAdmin['userId']) Helpers::respond(['error' => 'Use account settings to delete your own account.'], 400);
    $target = Database::fetchOne('SELECT email FROM users WHERE id = ?', [$id]);
    Media::purgeUser($id);
    Database::query('DELETE FROM research_events WHERE created_by = ?', [$id]);
    Database::delete('users', 'id', $id);
    Staff::audit($currentAdmin, 'user.delete', 'Observatory: deleted the account ' . ($target['email'] ?? $id) . ' and its files', 'user', $id);
    Helpers::respond(['message' => 'User deleted']);
}

Helpers::respond(['error' => 'Invalid request'], 400);
