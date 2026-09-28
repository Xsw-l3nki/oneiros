<?php
/**
 * /api/console/email
 *   GET                  queue numbers and the newest failures (email.process)
 *   POST ?action=process send up to 50 queued emails now (email.process)
 *   POST ?action=retry   put failed emails back in the queue (email.process)
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/mailer.php';

Helpers::only(['GET', 'POST']);
$staff = Staff::require('email.process');
$cfg = require __DIR__ . '/../../includes/runtime-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    if ($action === 'process') {
        if (!$cfg['mail_enabled']) Helpers::respond(['error' => 'Sending is switched off in Settings → Email'], 400);
        $sent = Mailer::processQueue(50);
        Staff::audit($staff, 'email.process', "Sent $sent queued email(s)", 'system', 'email_queue');
        Helpers::respond(['sent' => $sent]);
    }
    if ($action === 'retry') {
        $count = Database::query("UPDATE email_queue SET status = 'pending', attempts = 0 WHERE status = 'failed'")->rowCount();
        Staff::audit($staff, 'email.retry', "Re-queued $count failed email(s)", 'system', 'email_queue');
        Helpers::respond(['requeued' => $count]);
    }
    Helpers::respond(['error' => 'Unknown action'], 400);
}

$counts = Database::fetchOne("SELECT SUM(status = 'pending') pending, SUM(status = 'sent') sent, SUM(status = 'failed') failed FROM email_queue");
Helpers::respond([
    'enabled' => (bool)$cfg['mail_enabled'],
    'from'    => $cfg['mail_from'],
    'pending' => (int)$counts['pending'], 'sent' => (int)$counts['sent'], 'failed' => (int)$counts['failed'],
    'recent_failures' => Database::fetchAll("SELECT to_email, subject, attempts, created_at FROM email_queue WHERE status = 'failed' ORDER BY created_at DESC LIMIT 20"),
]);
