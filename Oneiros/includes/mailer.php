<?php
/**
 * Oneiros — Email System
 * Uses PHP mail() which works on cPanel shared hosting.
 * Templates are self-contained HTML to avoid dependency on template files.
 */

class Mailer
{
    /**
     * Send an email using a named template.
     * Returns true on success.
     */
    public static function send(string $to, string $toName, string $template, array $vars): bool
    {
        [$from, $fromName, $appUrl, $appName, $enabled] = self::sender();
        if (!$enabled) return false;  // Console → Settings → Email → Send emails

        $vars['app_url']   = $appUrl;
        $vars['app_name']  = $appName;
        $vars['year']      = date('Y');
        $vars['to_name']   = $toName ?: 'Dreamer';

        [$subject, $html] = self::render($template, $vars);

        $toHeader = $toName ? mb_encode_mimeheader($toName, 'UTF-8') . " <{$to}>" : $to;

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "X-Mailer: Oneiros\r\n";

        $result = @mail($toHeader, $subject, $html, $headers);

        if (!$result) {
            error_log("Oneiros Mailer: failed to send '{$template}' to {$to}");
        }

        return (bool)$result;
    }

    /**
     * Queue email for async sending (stored in DB, sent by cron or next request).
     */
    public static function queue(string $to, string $toName, string $template, array $vars): void
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        $vars += [
            'app_url'  => rtrim($cfg['frontend_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? '')), '/'),
            'app_name' => $cfg['app_name'],
            'year'     => date('Y'),
            'to_name'  => $toName ?: 'Dreamer',
        ];
        [$subject, $html] = self::render($template, $vars);

        try {
            require_once __DIR__ . '/db.php';
            require_once __DIR__ . '/helpers.php';
            Database::insert('email_queue', [
                'id'        => Helpers::uuid(),
                'to_email'  => $to,
                'to_name'   => $toName,
                'subject'   => $subject,
                'body_html' => $html,
                'status'    => 'pending',
            ]);
        } catch (Exception $e) {
            // If queue fails, try direct send
            self::send($to, $toName, $template, $vars);
        }
    }

    /**
     * Process pending emails from the queue (call from a cron or admin endpoint).
     * Returns count of emails sent.
     */
    public static function processQueue(int $limit = 20): int
    {
        $rows = Database::fetchAll(
            "SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 LIMIT ?",
            [$limit]
        );

        [$from, $fromName, , , $enabled] = self::sender();
        if (!$enabled) return 0;
        $sent = 0;
        foreach ($rows as $row) {

            $toHeader = $row['to_name']
                ? mb_encode_mimeheader($row['to_name'], 'UTF-8') . " <{$row['to_email']}>"
                : $row['to_email'];

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <{$from}>\r\n";
            $headers .= "Reply-To: {$from}\r\n";

            $ok = @mail($toHeader, $row['subject'], $row['body_html'], $headers);

            Database::query(
                "UPDATE email_queue SET attempts = attempts + 1, status = ?, sent_at = ? WHERE id = ?",
                [$ok ? 'sent' : 'pending', $ok ? date('Y-m-d H:i:s') : null, $row['id']]
            );

            if ($ok) {
                $sent++;
            } elseif ((int)$row['attempts'] >= 2) {
                Database::query("UPDATE email_queue SET status = 'failed' WHERE id = ?", [$row['id']]);
            }
        }

        return $sent;
    }

    /** Sender details from Console settings: [from, fromName, appUrl, appName, enabled]. */
    private static function sender(): array
    {
        $cfg = require __DIR__ . '/runtime-config.php';
        $host = $_SERVER['HTTP_HOST'] ?? (parse_url($cfg['frontend_url'], PHP_URL_HOST) ?: 'localhost');
        $from = filter_var($cfg['mail_from'] ?? '', FILTER_VALIDATE_EMAIL) && !str_ends_with($cfg['mail_from'], '@example.com')
            ? $cfg['mail_from'] : 'noreply@' . preg_replace('/^www\./', '', $host);
        return [$from, $cfg['mail_from_name'] ?: $cfg['app_name'], $cfg['frontend_url'] ?: 'https://' . $host, $cfg['app_name'], (bool)$cfg['mail_enabled']];
    }

    // ─── Template renderers ───────────────────────────────────

    private static function render(string $template, array $vars): array
    {
        return match($template) {
            'welcome'            => self::templateWelcome($vars),
            'match_found'        => self::templateMatchFound($vars),
            'connection_request' => self::templateConnectionRequest($vars),
            'weekly_digest'      => self::templateWeeklyDigest($vars),
            're_engagement'      => self::templateReEngagement($vars),
            'badge_earned'       => self::templateBadgeEarned($vars),
            'receipt'            => self::templateReceipt($vars),
            'gift'               => self::templateGift($vars),
            default              => ['Oneiros Notification', self::baseLayout('A notification from Oneiros.', $vars)],
        };
    }

    private static function baseLayout(string $body, array $vars): string
    {
        $appUrl  = htmlspecialchars($vars['app_url']  ?? '');
        $year    = htmlspecialchars($vars['year']     ?? date('Y'));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Oneiros</title>
<style>
  body { margin:0; padding:0; background:#0f0a1e; font-family:'Helvetica Neue',Arial,sans-serif; color:#e8e4f0; }
  .wrapper { max-width:600px; margin:0 auto; padding:40px 20px; }
  .header { text-align:center; padding:32px 0 24px; }
  .logo { font-size:28px; font-weight:300; letter-spacing:0.06em; color:#c8b8e8; }
  .logo span { color:#a78bca; font-style:italic; }
  .card { background:rgba(45,27,78,0.6); border-radius:16px; padding:32px; margin:16px 0; border:1px solid rgba(167,139,202,0.2); }
  .btn { display:inline-block; background:#5b21b6; color:#fff; text-decoration:none; border-radius:100px; padding:14px 36px; font-size:14px; font-weight:600; letter-spacing:0.06em; margin:16px 0; }
  .btn:hover { background:#7c3aed; }
  h1 { font-size:24px; font-weight:300; color:#e8e4f0; margin:0 0 12px; line-height:1.3; }
  p { font-size:15px; line-height:1.7; color:#b8b0d0; margin:0 0 16px; }
  .footer { text-align:center; padding:24px 0; font-size:12px; color:#6b6090; }
  .footer a { color:#7c5cbf; text-decoration:none; }
  .divider { border:none; border-top:1px solid rgba(167,139,202,0.15); margin:24px 0; }
  .stat-row { display:flex; gap:16px; margin:16px 0; }
  .stat { flex:1; background:rgba(91,33,182,0.2); border-radius:12px; padding:16px; text-align:center; }
  .stat-num { font-size:28px; font-weight:300; color:#c8b8e8; }
  .stat-label { font-size:11px; color:#8b7ab0; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="logo">◌ one<span>iros</span> ✦</div>
  </div>
  {$body}
  <div class="footer">
    <p>You're receiving this because you're a member of Oneiros.<br>
    <a href="{$appUrl}">Open Oneiros</a> · <a href="{$appUrl}/#unsubscribe">Unsubscribe</a></p>
    <p>© {$year} Oneiros. Dream Research Platform.</p>
  </div>
</div>
</body>
</html>
HTML;
    }

    private static function templateWelcome(array $vars): array
    {
        $name   = htmlspecialchars($vars['to_name'] ?? 'Dreamer');
        $appUrl = htmlspecialchars($vars['app_url'] ?? '');

        $body = <<<HTML
<div class="card">
  <h1>Welcome to Oneiros, {$name} 🌙</h1>
  <p>You've joined a global community of dreamers mapping the unconscious mind together. Every dream you log becomes part of something extraordinary.</p>
  <p>Here's what awaits you:</p>
  <div class="stat-row">
    <div class="stat">
      <div class="stat-num">🌍</div>
      <div class="stat-label">Global Matching</div>
    </div>
    <div class="stat">
      <div class="stat-num">✨</div>
      <div class="stat-label">AI Dream Art</div>
    </div>
    <div class="stat">
      <div class="stat-num">🎙️</div>
      <div class="stat-label">Voice Logging</div>
    </div>
  </div>
  <p>Your first step: log tonight's dream the moment you wake up. The fresher the memory, the richer the match.</p>
  <a href="{$appUrl}" class="btn">Open My Dream Journal →</a>
  <hr class="divider">
  <p style="font-size:13px;color:#6b6090">Tip: Set a phone reminder for 7am. Dreams fade within 20 minutes of waking.</p>
</div>
HTML;

        return ['Welcome to Oneiros 🌙', self::baseLayout($body, $vars)];
    }

    private static function templateMatchFound(array $vars): array
    {
        $name    = htmlspecialchars($vars['to_name'] ?? 'Dreamer');
        $count   = (int)($vars['match_count'] ?? 1);
        $appUrl  = htmlspecialchars($vars['app_url'] ?? '');
        $region  = htmlspecialchars($vars['region']  ?? 'another country');
        $plural  = $count === 1 ? 'dreamer' : 'dreamers';

        $body = <<<HTML
<div class="card">
  <h1>✨ {$count} {$plural} shared your vision last night</h1>
  <p>Hi {$name}, someone in {$region} logged a dream that resonates with yours. The matching engine found a significant overlap in themes, emotions, and visual symbols.</p>
  <div class="stat-row">
    <div class="stat">
      <div class="stat-num">{$count}</div>
      <div class="stat-label">New Matches</div>
    </div>
    <div class="stat">
      <div class="stat-num">🌍</div>
      <div class="stat-label">{$region}</div>
    </div>
  </div>
  <p>Open Oneiros to see the resonance score and what you shared — and to connect with this dreamer.</p>
  <a href="{$appUrl}" class="btn">See My Matches →</a>
</div>
HTML;

        return ["✨ You have {$count} new dream {$plural}", self::baseLayout($body, $vars)];
    }

    private static function templateConnectionRequest(array $vars): array
    {
        $name        = htmlspecialchars($vars['to_name']        ?? 'Dreamer');
        $requester   = htmlspecialchars($vars['requester_name'] ?? 'A dreamer');
        $matchScore  = (int)($vars['match_score'] ?? 0);
        $appUrl      = htmlspecialchars($vars['app_url'] ?? '');

        $scoreHtml = $matchScore > 0
            ? "<p>Your dreams resonated at <strong style='color:#c8b8e8'>{$matchScore}%</strong> — a significant shared vision.</p>"
            : '';

        $body = <<<HTML
<div class="card">
  <h1>🌙 {$requester} wants to connect</h1>
  <p>Hi {$name}, a dreamer who matched with you has sent a connection request.</p>
  {$scoreHtml}
  <p>Once connected, you can message each other and explore your shared dream themes in depth.</p>
  <a href="{$appUrl}" class="btn">Accept Connection →</a>
</div>
HTML;

        return ["{$requester} sent you a connection request", self::baseLayout($body, $vars)];
    }

    private static function templateWeeklyDigest(array $vars): array
    {
        $name         = htmlspecialchars($vars['to_name']       ?? 'Dreamer');
        $dreamsCount  = (int)($vars['dreams_this_week']         ?? 0);
        $matchesCount = (int)($vars['matches_this_week']        ?? 0);
        $streak       = (int)($vars['current_streak']           ?? 0);
        $topTheme     = htmlspecialchars($vars['top_theme']     ?? 'N/A');
        $appUrl       = htmlspecialchars($vars['app_url']       ?? '');

        $streakHtml = $streak > 0
            ? "<p>🔥 You're on a <strong style='color:#c8b8e8'>{$streak}-day streak</strong>. Keep going — the patterns emerge with consistency.</p>"
            : '<p>💡 Tip: logging every morning — even a single sentence — builds your streak and unlocks badges.</p>';

        $body = <<<HTML
<div class="card">
  <h1>Your dream week in review, {$name}</h1>
  <div class="stat-row">
    <div class="stat">
      <div class="stat-num">{$dreamsCount}</div>
      <div class="stat-label">Dreams Logged</div>
    </div>
    <div class="stat">
      <div class="stat-num">{$matchesCount}</div>
      <div class="stat-label">New Matches</div>
    </div>
    <div class="stat">
      <div class="stat-num">{$streak}</div>
      <div class="stat-label">Day Streak</div>
    </div>
  </div>
  <p>Your most common theme this week: <strong style="color:#c8b8e8">{$topTheme}</strong></p>
  {$streakHtml}
  <a href="{$appUrl}" class="btn">Open My Journal →</a>
</div>
HTML;

        return ['Your weekly dream summary 🌙', self::baseLayout($body, $vars)];
    }

    private static function templateReEngagement(array $vars): array
    {
        $name   = htmlspecialchars($vars['to_name'] ?? 'Dreamer');
        $days   = (int)($vars['days_absent']        ?? 7);
        $appUrl = htmlspecialchars($vars['app_url'] ?? '');

        $body = <<<HTML
<div class="card">
  <h1>🌙 Your dreams are waiting, {$name}</h1>
  <p>It's been {$days} nights since you last logged a dream. The matching engine is still running — and dreamers around the world may be having visions that resonate with yours right now.</p>
  <p>Even a single sentence is enough. Your subconscious is working every night whether you log it or not.</p>
  <a href="{$appUrl}" class="btn">Log Last Night's Dream →</a>
  <hr class="divider">
  <p style="font-size:13px;color:#6b6090">Tip: Sleeping with a notepad nearby helps capture fragments before they fade.</p>
</div>
HTML;

        return ["We miss your dreams, {$name} 🌙", self::baseLayout($body, $vars)];
    }

    private static function templateBadgeEarned(array $vars): array
    {
        $name    = htmlspecialchars($vars['to_name']   ?? 'Dreamer');
        $badge   = htmlspecialchars($vars['badge_name'] ?? 'Unknown Badge');
        $desc    = htmlspecialchars($vars['badge_desc'] ?? '');
        $icon    = htmlspecialchars($vars['badge_icon'] ?? '🏅');
        $appUrl  = htmlspecialchars($vars['app_url']   ?? '');

        $body = <<<HTML
<div class="card" style="text-align:center">
  <div style="font-size:64px;margin-bottom:16px">{$icon}</div>
  <h1>Badge Unlocked: {$badge}</h1>
  <p>{$desc}</p>
  <p>View all your badges and progress on your Oneiros profile.</p>
  <a href="{$appUrl}" class="btn">See My Badges →</a>
</div>
HTML;

        return ["🏅 You earned the {$badge} badge!", self::baseLayout($body, $vars)];
    }

    private static function templateReceipt(array $vars): array
    {
        $order  = $vars['order'] ?? [];
        $appUrl = htmlspecialchars($vars['app_url'] ?? '');
        $ref    = htmlspecialchars($order['reference'] ?? '');
        $item   = htmlspecialchars($order['plan_name'] ?? 'Oneiros Lucid');
        $amount = htmlspecialchars($vars['amount'] ?? '');
        $date   = gmdate('j F Y', strtotime(($order['paid_at'] ?? 'now') . ' UTC'));
        $nights = (int)($order['days'] ?? 0);
        $until  = !empty($order['premium_until']) ? gmdate('j F Y', strtotime($order['premium_until'] . ' UTC')) : '';
        $gift   = htmlspecialchars($order['gift_code'] ?? '');
        $detail = match ($order['kind'] ?? 'pass') {
            'gift'    => "<p>Your gift code is <strong style=\"letter-spacing:.1em\">{$gift}</strong>. Share it with someone special: it unlocks {$nights} Lucid nights and never expires until it is used.</p>",
            'support' => '<p>Thank you for supporting Oneiros. A Patron mark now glows beside your name.</p>',
            default   => "<p>{$nights} Lucid nights have been added. Your pass is open until <strong>{$until}</strong>.</p>",
        };
        $body = <<<HTML
<div class="card">
  <h1>Thank you ✦</h1>
  {$detail}
  <hr class="divider">
  <p><strong>Receipt {$ref}</strong><br>{$item}<br>{$date}<br>Amount paid: <strong>{$amount}</strong></p>
  <p style="font-size:13px">This was a once-off payment. It does not renew and you will not be charged again.</p>
  <a href="{$appUrl}/oneiros.php" class="btn">Open Oneiros</a>
</div>
HTML;
        return ["Your Oneiros receipt · {$ref}", self::baseLayout($body, $vars)];
    }

    private static function templateGift(array $vars): array
    {
        $appUrl = htmlspecialchars($vars['app_url'] ?? '');
        $code   = htmlspecialchars($vars['code'] ?? '');
        $nights = (int)($vars['days'] ?? 30);
        $link   = $appUrl . '/oneiros.php?redeem=' . rawurlencode($vars['code'] ?? '');
        $body = <<<HTML
<div class="card">
  <h1>Someone gave you a gift of dreams 🌙</h1>
  <p>You have received <strong>{$nights} nights of Oneiros Lucid</strong>: a quiet place to remember your dreams, discover their patterns, and meet the people around the world who dream what you dream.</p>
  <p style="text-align:center;font-size:22px;letter-spacing:.14em;color:#f6ddb0"><strong>{$code}</strong></p>
  <a href="{$link}" class="btn">Redeem my gift</a>
  <p style="font-size:13px">Create a free journal (or sign in), and the code is applied for you. It never expires until it is used.</p>
</div>
HTML;
        return ['A gift of Lucid dreams awaits you', self::baseLayout($body, $vars)];
    }
}
