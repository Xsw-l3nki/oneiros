<?php
/**
 * GET /api/console/audit?q=&action=&page=
 * Admins see every entry (audit.view_all); moderators see only their own actions (audit.view_own).
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';

Helpers::only(['GET']);
$staff = Staff::require('audit.view_own');
$where = [];
$params = [];
if (!Staff::can($staff, 'audit.view_all')) { $where[] = 'actor_id = ?'; $params[] = $staff['userId']; }
$action = trim((string)($_GET['action'] ?? ''));
if ($action !== '') { $where[] = 'action LIKE ?'; $params[] = addcslashes($action, '%_\\') . '%'; }
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(summary LIKE ? OR actor_email LIKE ? OR target_id = ?)';
    array_push($params, $like, $like, $q);
}
$sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)Database::fetchOne("SELECT COUNT(*) c FROM audit_log$sql", $params)['c'];
$rows = Database::fetchAll("SELECT id, actor_email, actor_role, action, target_type, target_id, summary, details, ip, created_at
    FROM audit_log$sql ORDER BY id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
foreach ($rows as &$row) {
    $row['details'] = $row['details'] ? json_decode($row['details'], true) : null;
    $row['created_at'] = gmdate('c', strtotime($row['created_at'] . ' UTC'));
}
unset($row);
Helpers::respond(['entries' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $perPage))]);
