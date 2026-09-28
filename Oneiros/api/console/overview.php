<?php
/** GET /api/console/overview — live status numbers. Money figures only for roles with finance.view. */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';

Helpers::only(['GET']);
$staff = Staff::require('overview.view');
$cfg = require __DIR__ . '/../../includes/runtime-config.php';

$count = fn(string $sql) => (int)(Database::fetchOne($sql)['c'] ?? 0);
$out = [
    'status' => [
        'maintenance_mode'  => (bool)$cfg['maintenance_mode'],
        'registrations_open'=> (bool)$cfg['feature_registrations'],
        'mail_enabled'      => (bool)$cfg['mail_enabled'],
    ],
    'users' => [
        'total'      => $count('SELECT COUNT(*) c FROM users'),
        'new_24h'    => $count('SELECT COUNT(*) c FROM users WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY'),
        'new_7d'     => $count('SELECT COUNT(*) c FROM users WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
        'active_24h' => $count('SELECT COUNT(*) c FROM users WHERE last_active_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY'),
        'online_now' => $count('SELECT COUNT(*) c FROM users WHERE last_active_at >= UTC_TIMESTAMP() - INTERVAL 5 MINUTE'),
        'suspended'  => $count('SELECT COUNT(*) c FROM users WHERE is_active = 0'),
        'lucid'      => $count('SELECT COUNT(*) c FROM users WHERE premium_until > UTC_TIMESTAMP()'),
        'staff'      => $count('SELECT COUNT(*) c FROM users WHERE is_admin = 1 OR is_moderator = 1'),
    ],
    'content' => [
        'dreams_total'  => $count('SELECT COUNT(*) c FROM dreams WHERE is_removed = 0'),
        'dreams_24h'    => $count('SELECT COUNT(*) c FROM dreams WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY'),
        'pending_flags' => $count("SELECT COUNT(*) c FROM moderation_flags WHERE status = 'pending'"),
        'messages_24h'  => $count('SELECT COUNT(*) c FROM messages WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY'),
    ],
    'email' => [
        'pending' => $count("SELECT COUNT(*) c FROM email_queue WHERE status = 'pending'"),
        'failed'  => $count("SELECT COUNT(*) c FROM email_queue WHERE status = 'failed'"),
    ],
    'signups_14d' => array_map(fn($r) => ['date' => $r['d'], 'count' => (int)$r['c']], Database::fetchAll(
        'SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= UTC_DATE() - INTERVAL 13 DAY GROUP BY DATE(created_at) ORDER BY d')),
];
if (Staff::can($staff, 'finance.view')) {
    $paid = Database::fetchOne("SELECT COUNT(*) c, COALESCE(SUM(amount_cents), 0) s FROM premium_orders WHERE status = 'paid' AND paid_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY");
    $out['finance'] = [
        'orders_30d'       => (int)($paid['c'] ?? 0),
        'revenue_30d_cents'=> (int)($paid['s'] ?? 0),
        'currency_symbol'  => $cfg['currency_symbol'],
    ];
}
Helpers::respond($out);
