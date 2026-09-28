<?php
/**
 * Admin: Email Campaign + Queue Management
 * POST /admin/email/campaign — send a campaign to all active users
 * POST /admin/email/process  — flush the email queue
 * GET  /admin/email/stats    — queue statistics
 */

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/mailer.php';

$admin = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ─── GET /admin/email/stats ───
if ($method === 'GET' && $action === 'stats') {
    $pending = Database::fetchOne("SELECT COUNT(*) as c FROM email_queue WHERE status = 'pending'")['c'] ?? 0;
    $sent    = Database::fetchOne("SELECT COUNT(*) as c FROM email_queue WHERE status = 'sent'")['c'] ?? 0;
    $failed  = Database::fetchOne("SELECT COUNT(*) as c FROM email_queue WHERE status = 'failed'")['c'] ?? 0;
    Helpers::respond([
        'pending' => (int)$pending,
        'sent'    => (int)$sent,
        'failed'  => (int)$failed,
    ]);
}

// ─── POST /admin/email/process — flush queue ───
if ($method === 'POST' && $action === 'process') {
    $sent = Mailer::processQueue(50);
    Staff::audit($admin, 'email.process', "Observatory: sent $sent queued email(s)", 'system', 'email_queue');
    Helpers::respond(['sent' => $sent, 'message' => "Processed {$sent} emails from queue"]);
}

// ─── POST /admin/email/campaign — send template to all/segment ───
if ($method === 'POST' && $action === 'campaign') {
    $input    = Helpers::input();
    $template = $input['template'] ?? '';
    $segment  = $input['segment']  ?? 'all';  // all | inactive_7d | inactive_30d | no_dreams

    $allowed = ['welcome','re_engagement','weekly_digest'];
    if (!in_array($template, $allowed)) {
        Helpers::respond(['error' => 'Invalid template. Allowed: ' . implode(', ', $allowed)], 400);
    }

    // Build user query based on segment
    $where = 'is_active = 1';
    $params = [];

    if ($segment === 'inactive_7d') {
        $where .= ' AND (last_active_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_active_at IS NULL)';
    } elseif ($segment === 'inactive_30d') {
        $where .= ' AND (last_active_at < DATE_SUB(NOW(), INTERVAL 30 DAY) OR last_active_at IS NULL)';
    } elseif ($segment === 'no_dreams') {
        $where .= ' AND id NOT IN (SELECT DISTINCT user_id FROM dreams WHERE is_removed = 0)';
    }

    $users = Database::fetchAll("SELECT email, display_name FROM users WHERE {$where} LIMIT 1000");
    $queued = 0;

    foreach ($users as $u) {
        // Weekly digest needs per-user stats
        $vars = [];
        if ($template === 'weekly_digest') {
            $stats = Database::fetchOne(
                "SELECT COUNT(*) as dreams FROM dreams
                  WHERE user_id = (SELECT id FROM users WHERE email = ? LIMIT 1)
                    AND dreamed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_removed = 0",
                [$u['email']]
            );
            $vars['dreams_this_week'] = (int)($stats['dreams'] ?? 0);
            $vars['matches_this_week'] = 0;
            $vars['current_streak'] = 0;
            $vars['top_theme'] = 'unknown';
        }

        try {
            Mailer::queue($u['email'], $u['display_name'] ?? '', $template, $vars);
            $queued++;
        } catch (Exception $e) {
            error_log('Oneiros campaign queue error: ' . $e->getMessage());
        }
    }

    Staff::audit($admin, 'email.campaign', "Observatory: queued $queued '{$template}' campaign email(s)", 'system', 'email_queue');
    Helpers::respond([
        'queued'  => $queued,
        'message' => "Queued {$queued} emails for '{$template}' campaign",
    ]);
}

Helpers::respond(['error' => 'Unknown action'], 400);
