<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/payments.php';

$admin = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sum = fn(string $where, array $params = []) => (int)(Database::fetchOne(
        "SELECT COALESCE(SUM(amount_cents), 0) AS s FROM premium_orders WHERE status = 'paid' $where", $params)['s'] ?? 0);
    $count = fn(string $sql, array $params = []) => (int)(Database::fetchOne($sql, $params)['c'] ?? 0);

    $daily = Database::fetchAll(
        "SELECT DATE(paid_at) AS day, SUM(amount_cents) AS cents, COUNT(*) AS orders
           FROM premium_orders WHERE status = 'paid' AND paid_at >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)
          GROUP BY DATE(paid_at) ORDER BY day"
    );
    $byPlan = Database::fetchAll(
        "SELECT plan_id, MAX(plan_name) AS plan_name, kind, COUNT(*) AS orders, SUM(amount_cents) AS cents
           FROM premium_orders WHERE status = 'paid' GROUP BY plan_id, kind ORDER BY cents DESC"
    );
    $orders = Database::fetchAll(
        'SELECT id, reference, buyer_email, plan_name, kind, days, amount_cents, discount_code, provider, provider_ref, status, gift_code, created_at, paid_at
           FROM premium_orders ORDER BY created_at DESC LIMIT 150'
    );
    foreach ($orders as &$order) {
        $order['amount'] = Premium::money((int)$order['amount_cents']);
        $order['created_at'] = Premium::iso($order['created_at']);
        $order['paid_at'] = Premium::iso($order['paid_at']);
    }
    unset($order);
    $paidOrders = $count("SELECT COUNT(*) AS c FROM premium_orders WHERE status = 'paid'");
    $allTime = $sum('');
    $cfg = Premium::cfg();

    Helpers::respond([
        'currency_symbol' => $cfg['currency_symbol'] ?? 'R',
        'totals' => [
            'today'        => $sum('AND paid_at >= UTC_DATE()'),
            'week'         => $sum('AND paid_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'),
            'month'        => $sum('AND paid_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'),
            'all_time'     => $allTime,
            'paid_orders'  => $paidOrders,
            'average'      => $paidOrders ? (int)round($allTime / $paidOrders) : 0,
            'refunded'     => (int)(Database::fetchOne("SELECT COALESCE(SUM(amount_cents), 0) AS s FROM premium_orders WHERE status = 'refunded'")['s'] ?? 0),
            'pending'      => $count("SELECT COUNT(*) AS c FROM premium_orders WHERE status = 'pending' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"),
            'active_passes'=> $count('SELECT COUNT(*) AS c FROM users WHERE premium_until > UTC_TIMESTAMP()'),
            'patrons'      => $count('SELECT COUNT(*) AS c FROM users WHERE is_patron = 1'),
            'buyers'       => $count("SELECT COUNT(DISTINCT buyer_email) AS c FROM premium_orders WHERE status = 'paid'"),
            'dreamers'     => $count('SELECT COUNT(*) AS c FROM users WHERE is_active = 1'),
        ],
        'daily'     => $daily,
        'by_plan'   => $byPlan,
        'orders'    => $orders,
        'providers' => Premium::providers(),
        'sandbox'   => !empty($cfg['payfast_sandbox']),
    ]);
}

if ($method === 'POST' || $method === 'PATCH') {
    $input = Helpers::input();
    $order = Database::fetchOne('SELECT * FROM premium_orders WHERE id = ?', [(string)($input['order_id'] ?? '')]);
    if (!$order) Helpers::respond(['error' => 'Order not found'], 404);
    $action = (string)($input['action'] ?? '');

    if ($action === 'mark_paid') {
        // For payments confirmed in the gateway dashboard whose notification never arrived
        if ($order['status'] === 'paid') Helpers::respond(['error' => 'This order is already paid.'], 400);
        $order = Premium::fulfill($order['id'], 'manual:' . $admin['email'], null);
        Premium::logEvent($order['provider'], $order['id'], 'marked_paid', true, 'by ' . $admin['email']);
        Staff::audit($admin, 'orders.mark_paid', "Observatory: marked order {$order['reference']} as paid", 'order', $order['id']);
        Helpers::respond(['order' => Premium::publicOrder($order)]);
    }
    if ($action === 'refund') {
        // Record a refund made in the gateway dashboard and take back what it bought
        if ($order['status'] !== 'paid') Helpers::respond(['error' => 'Only paid orders can be refunded.'], 400);
        Database::query('UPDATE premium_orders SET status = "refunded" WHERE id = ?', [$order['id']]);
        if ($order['kind'] === 'pass' && $order['user_id']) Premium::reduce($order['user_id'], (int)$order['days']);
        if ($order['kind'] === 'gift' && $order['gift_code']) Database::query('UPDATE premium_codes SET is_active = 0 WHERE code = ?', [$order['gift_code']]);
        Premium::logEvent($order['provider'], $order['id'], 'refunded', true, 'by ' . $admin['email']);
        Staff::audit($admin, 'orders.refund', "Observatory: recorded a refund for order {$order['reference']}", 'order', $order['id']);
        Helpers::respond(['order' => Premium::publicOrder(Database::fetchOne('SELECT * FROM premium_orders WHERE id = ?', [$order['id']]))]);
    }
    Helpers::respond(['error' => 'Unknown action'], 400);
}

Helpers::respond(['error' => 'Method not allowed'], 405);
