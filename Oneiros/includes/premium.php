<?php
/**
 * Oneiros Lucid — once-off passes, gifts, codes and referral rewards.
 * Nothing here ever renews or charges again: each purchase adds days to the dreamer's pass.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/matching.php';   // Notification

class Premium
{
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** What Lucid unlocks, shown on the pricing screen. */
    public const PERKS = [
        ['icon' => '✧', 'title' => 'Every resonance', 'text' => 'See every dreamer who shared your dream, not just the closest five.'],
        ['icon' => '◎', 'title' => 'Unlimited connections', 'text' => 'Reach out to as many resonant dreamers as you like.'],
        ['icon' => '◈', 'title' => 'Deep insights', 'text' => 'Emotional timeline, dream signs, word constellations and monthly themes.'],
        ['icon' => '☾', 'title' => 'Full symbol reflections', 'text' => 'Gentle questions for every symbol that appears in your dreams.'],
        ['icon' => '≋', 'title' => 'Wind-down library', 'text' => 'Every soundscape, layered mixes and a fading sleep timer.'],
        ['icon' => '❖', 'title' => 'Your Dream Book', 'text' => 'A printable keepsake of your journal with its paintings, plus HD canvas downloads.'],
        ['icon' => '◐', 'title' => 'Lucid themes', 'text' => 'Aurora, Rosé and Eclipse colour worlds, and a Lucid mark beside your name.'],
        ['icon' => '◌', 'title' => 'Longer voice notes', 'text' => 'Record up to ten minutes the moment you wake.'],
    ];

    public static function cfg(): array
    {
        return require __DIR__ . '/runtime-config.php';
    }

    /** Lucid passes from configuration, keyed by id. */
    public static function plans(): array
    {
        $plans = [];
        foreach ((self::cfg()['plans'] ?? []) as $id => $plan) {
            if (!preg_match('/^[a-z0-9-]{2,40}$/', (string)$id) || (int)($plan['days'] ?? 0) < 1 || (int)($plan['price_cents'] ?? 0) < 500) continue;
            $plans[$id] = [
                'id' => $id, 'kind' => 'pass', 'name' => (string)($plan['name'] ?? 'Lucid'),
                'days' => (int)$plan['days'], 'price_cents' => (int)$plan['price_cents'],
                'featured' => !empty($plan['featured']),
            ];
        }
        return $plans;
    }

    /** One-off support amounts. */
    public static function supportPlans(): array
    {
        $plans = [];
        foreach ((self::cfg()['support_amounts'] ?? []) as $cents) {
            $cents = (int)$cents;
            if ($cents < 500) continue;
            $plans['support-' . $cents] = ['id' => 'support-' . $cents, 'kind' => 'support', 'name' => 'Support Oneiros', 'days' => 0, 'price_cents' => $cents, 'featured' => false];
        }
        return $plans;
    }

    public static function findPlan(string $id): ?array
    {
        return self::plans()[$id] ?? self::supportPlans()[$id] ?? null;
    }

    /** Gateways with credentials configured on this server. */
    public static function providers(): array
    {
        $cfg = self::cfg();
        $providers = [];
        if (!function_exists('curl_init') || empty($cfg['feature_payments'])) return $providers;
        if (!empty($cfg['payfast_merchant_id']) && !empty($cfg['payfast_merchant_key'])) $providers[] = 'payfast';
        if (!empty($cfg['paystack_secret_key'])) $providers[] = 'paystack';
        return $providers;
    }

    public static function money(int $cents): string
    {
        $cfg = self::cfg();
        return ($cfg['currency_symbol'] ?? 'R') . number_format($cents / 100, $cents % 100 ? 2 : 0, '.', ' ');
    }

    public static function iso(?string $datetime): ?string
    {
        return $datetime ? gmdate('Y-m-d\TH:i:s\Z', strtotime($datetime . ' UTC')) : null;
    }

    public static function isActiveUntil(?string $until): bool
    {
        return $until !== null && $until !== '' && strtotime($until . ' UTC') > time();
    }

    public static function isActive(string $userId): bool
    {
        $row = Database::fetchOne('SELECT premium_until FROM users WHERE id = ?', [$userId]);
        return self::isActiveUntil($row['premium_until'] ?? null);
    }

    /** Membership summary for the dreamer. */
    public static function status(string $userId): array
    {
        $row = Database::fetchOne('SELECT premium_until, is_patron, referral_code FROM users WHERE id = ?', [$userId]) ?? [];
        $until = $row['premium_until'] ?? null;
        $active = self::isActiveUntil($until);
        $cfg = self::cfg();
        $invited = Database::fetchOne('SELECT COUNT(*) AS c, SUM(referral_rewarded) AS r FROM users WHERE referred_by = ?', [$userId]);
        return [
            'is_premium'    => $active,
            'premium_until' => $active ? self::iso($until) : null,
            'expired_at'    => !$active && $until ? self::iso($until) : null,
            'days_left'     => $active ? (int)ceil((strtotime($until . ' UTC') - time()) / 86400) : 0,
            'is_patron'     => (bool)($row['is_patron'] ?? false),
            'referral'      => [
                'code'        => $row['referral_code'] ?? null,
                'invited'     => (int)($invited['c'] ?? 0),
                'rewarded'    => (int)($invited['r'] ?? 0),
                'reward_days' => (int)($cfg['referral_reward_days'] ?? 0),
            ],
        ];
    }

    /** Add days to a pass, starting from whichever is later: now, or the current end date. */
    public static function extend(string $userId, int $days): ?string
    {
        if ($days < 1) return null;
        Database::query(
            'UPDATE users SET premium_until = DATE_ADD(GREATEST(COALESCE(premium_until, UTC_TIMESTAMP()), UTC_TIMESTAMP()), INTERVAL ? DAY), is_premium = 1 WHERE id = ?',
            [$days, $userId]
        );
        return Database::fetchOne('SELECT premium_until FROM users WHERE id = ?', [$userId])['premium_until'] ?? null;
    }

    /** Remove days (refunds) or end a pass entirely when $days is null. */
    public static function reduce(string $userId, ?int $days = null): void
    {
        if ($days === null) {
            Database::query('UPDATE users SET premium_until = NULL, is_premium = 0 WHERE id = ?', [$userId]);
            return;
        }
        Database::query('UPDATE users SET premium_until = DATE_SUB(premium_until, INTERVAL ? DAY) WHERE id = ? AND premium_until IS NOT NULL', [$days, $userId]);
        Database::query('UPDATE users SET premium_until = NULL, is_premium = 0 WHERE id = ? AND premium_until <= UTC_TIMESTAMP()', [$userId]);
    }

    public static function randomCode(string $prefix, int $groups = 2): string
    {
        $parts = [];
        for ($g = 0; $g < $groups; $g++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) $part .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            $parts[] = $part;
        }
        return $prefix . '-' . implode('-', $parts);
    }

    public static function newReference(): string
    {
        do {
            $ref = 'ON' . substr(self::randomCode('X', 2), 2);
            $ref = str_replace('-', '', $ref);
        } while (Database::fetchOne('SELECT id FROM premium_orders WHERE reference = ?', [$ref]));
        return $ref;
    }

    public static function normaliseCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($code)));
    }

    /** Create a gift, promo (free days) or discount (percent off) code. */
    public static function createCode(string $kind, int $days, int $percentOff, int $maxUses, ?string $expiresAt, ?string $note, ?string $orderId = null, ?string $createdBy = null, ?string $code = null): array
    {
        if (!in_array($kind, ['gift', 'promo', 'discount'], true)) throw new InvalidArgumentException('Unknown code type');
        if ($kind === 'discount' && ($percentOff < 1 || $percentOff > 90)) throw new InvalidArgumentException('Discounts must be between 1% and 90%.');
        if ($kind !== 'discount' && ($days < 1 || $days > 3650)) throw new InvalidArgumentException('Codes must give between 1 and 3650 days.');
        $code = $code ? self::normaliseCode($code) : self::randomCode($kind === 'gift' ? 'GIFT' : 'LUCID');
        if (!preg_match('/^[A-Z0-9-]{4,40}$/', $code)) throw new InvalidArgumentException('Codes use 4–40 letters, numbers or dashes.');
        if (Database::fetchOne('SELECT id FROM premium_codes WHERE code = ?', [$code])) throw new InvalidArgumentException('That code already exists.');
        $row = [
            'id' => Helpers::uuid(), 'code' => $code, 'kind' => $kind,
            'days' => $kind === 'discount' ? 0 : $days, 'percent_off' => $kind === 'discount' ? $percentOff : 0,
            'max_uses' => max(1, min(100000, $maxUses)), 'expires_at' => $expiresAt, 'note' => $note ? mb_substr($note, 0, 160) : null,
            'order_id' => $orderId, 'created_by' => $createdBy,
        ];
        Database::insert('premium_codes', $row);
        return Database::fetchOne('SELECT * FROM premium_codes WHERE id = ?', [$row['id']]);
    }

    private static function usableCode(string $code, array $kinds): ?array
    {
        $row = Database::fetchOne('SELECT * FROM premium_codes WHERE code = ?', [self::normaliseCode($code)]);
        if (!$row || !in_array($row['kind'], $kinds, true) || !$row['is_active']) return null;
        if ($row['expires_at'] && strtotime($row['expires_at'] . ' UTC') < time()) return null;
        if ((int)$row['uses'] >= (int)$row['max_uses']) return null;
        return $row;
    }

    /** Price a plan, applying a discount code when one is given. */
    public static function quote(array $plan, ?string $code, string $userId): array
    {
        $price = (int)$plan['price_cents'];
        $result = ['plan' => $plan, 'amount_cents' => $price, 'discount_cents' => 0, 'code' => null, 'percent_off' => 0];
        if (!$code || $plan['kind'] === 'support') return $result;
        $row = self::usableCode($code, ['discount']);
        if (!$row) throw new InvalidArgumentException('That code is not valid, has expired, or has been fully used.');
        if (Database::fetchOne('SELECT id FROM premium_redemptions WHERE code_id = ? AND user_id = ?', [$row['id'], $userId])) {
            throw new InvalidArgumentException('You have already used this code.');
        }
        $discount = (int)round($price * (int)$row['percent_off'] / 100);
        $amount = max(500, $price - $discount);
        return ['plan' => $plan, 'amount_cents' => $amount, 'discount_cents' => $price - $amount, 'code' => $row['code'], 'percent_off' => (int)$row['percent_off']];
    }

    public static function createOrder(array $quote, array $user, string $provider, bool $isGift, ?string $recipientEmail): array
    {
        $plan = $quote['plan'];
        $kind = $plan['kind'] === 'support' ? 'support' : ($isGift ? 'gift' : 'pass');
        $order = [
            'id' => Helpers::uuid(), 'reference' => self::newReference(), 'user_id' => $user['id'], 'buyer_email' => $user['email'],
            'plan_id' => $plan['id'], 'plan_name' => $kind === 'gift' ? 'Gift · ' . $plan['name'] : $plan['name'], 'kind' => $kind,
            'days' => (int)$plan['days'], 'amount_cents' => (int)$quote['amount_cents'], 'discount_cents' => (int)$quote['discount_cents'],
            'discount_code' => $quote['code'], 'currency' => self::cfg()['currency'] ?? 'ZAR', 'provider' => $provider,
            'recipient_email' => $kind === 'gift' ? $recipientEmail : null,
        ];
        Database::insert('premium_orders', $order);
        return Database::fetchOne('SELECT * FROM premium_orders WHERE id = ?', [$order['id']]);
    }

    /**
     * Mark an order paid and deliver what was bought. Safe to call more than once:
     * a second notification for the same order changes nothing.
     */
    public static function fulfill(string $orderId, ?string $providerRef, ?int $paidCents): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $order = Database::fetchOne('SELECT * FROM premium_orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$order) throw new RuntimeException('Order not found');
            if ($order['status'] === 'paid') { $pdo->commit(); return $order; }
            if ($order['status'] === 'refunded') throw new RuntimeException('Order was refunded');
            if ($paidCents !== null && $paidCents !== (int)$order['amount_cents']) throw new RuntimeException('Paid amount does not match the order');

            $until = null; $giftCode = null;
            if ($order['kind'] === 'pass' && $order['user_id']) $until = self::extend($order['user_id'], (int)$order['days']);
            if ($order['kind'] === 'gift') {
                $giftCode = self::createCode('gift', (int)$order['days'], 0, 1, null, 'Gift from order ' . $order['reference'], $order['id'], $order['user_id'])['code'];
            }
            if ($order['kind'] === 'support' && $order['user_id']) Database::query('UPDATE users SET is_patron = 1 WHERE id = ?', [$order['user_id']]);
            if ($order['discount_code']) {
                $code = Database::fetchOne('SELECT id FROM premium_codes WHERE code = ?', [$order['discount_code']]);
                if ($code) {
                    Database::query('UPDATE premium_codes SET uses = uses + 1 WHERE id = ?', [$code['id']]);
                    if ($order['user_id']) Database::query('INSERT IGNORE INTO premium_redemptions (id, code_id, user_id, order_id) VALUES (?, ?, ?, ?)', [Helpers::uuid(), $code['id'], $order['user_id'], $order['id']]);
                }
            }
            Database::query(
                'UPDATE premium_orders SET status = "paid", paid_at = UTC_TIMESTAMP(), provider_ref = COALESCE(?, provider_ref), premium_until = ?, gift_code = ? WHERE id = ?',
                [$providerRef, $until, $giftCode, $order['id']]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $order = Database::fetchOne('SELECT * FROM premium_orders WHERE id = ?', [$orderId]);
        self::announce($order);
        return $order;
    }

    /** In-app notification and emailed receipt after a successful payment (never fatal). */
    private static function announce(array $order): void
    {
        try {
            if ($order['user_id']) {
                $messages = [
                    'pass'    => ['Your Lucid pass is open', "{$order['days']} nights of Lucid have been added. Thank you for dreaming with us."],
                    'gift'    => ['Your gift is ready', "Share the code {$order['gift_code']} with someone special. It unlocks {$order['days']} Lucid nights."],
                    'support' => ['Thank you for the light', 'Your support keeps Oneiros dreaming. A Patron mark now glows beside your name.'],
                ][$order['kind']];
                Notification::create($order['user_id'], 'premium_activated', ['title' => $messages[0], 'body' => $messages[1], 'data' => ['orderId' => $order['id']]]);
            }
            require_once __DIR__ . '/mailer.php';
            Mailer::queue($order['buyer_email'], '', 'receipt', ['order' => $order, 'amount' => self::money((int)$order['amount_cents'])]);
            if ($order['kind'] === 'gift' && $order['recipient_email']) {
                Mailer::queue($order['recipient_email'], '', 'gift', ['code' => $order['gift_code'], 'days' => $order['days']]);
            }
        } catch (Throwable $e) {
            error_log('Oneiros: order announcement failed: ' . $e->getMessage());
        }
    }

    /** Redeem a gift or promo code for Lucid days. */
    public static function redeem(string $userId, string $input): array
    {
        $code = self::normaliseCode($input);
        $row = Database::fetchOne('SELECT * FROM premium_codes WHERE code = ?', [$code]);
        if ($row && $row['kind'] === 'discount') throw new InvalidArgumentException('This is a discount code. Choose a pass and enter it at checkout.');
        $row = self::usableCode($code, ['gift', 'promo']);
        if (!$row) throw new InvalidArgumentException('That code is not valid, has expired, or has already been used.');
        if (Database::fetchOne('SELECT id FROM premium_redemptions WHERE code_id = ? AND user_id = ?', [$row['id'], $userId])) {
            throw new InvalidArgumentException('You have already redeemed this code.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $claimed = Database::query('UPDATE premium_codes SET uses = uses + 1 WHERE id = ? AND is_active = 1 AND uses < max_uses', [$row['id']])->rowCount();
            if (!$claimed) throw new InvalidArgumentException('That code has just been used up.');
            Database::insert('premium_redemptions', ['id' => Helpers::uuid(), 'code_id' => $row['id'], 'user_id' => $userId]);
            $until = self::extend($userId, (int)$row['days']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Notification::create($userId, 'premium_activated', [
            'title' => 'Lucid unlocked',
            'body'  => "{$row['days']} Lucid nights were added with your " . ($row['kind'] === 'gift' ? 'gift' : 'code') . '.',
        ]);
        return ['days' => (int)$row['days'], 'premium_until' => self::iso($until)];
    }

    /** Both dreamers receive Lucid days when an invited friend saves their first dream. */
    public static function rewardReferral(string $userId): void
    {
        try {
            $cfg = self::cfg();
            $days = (int)($cfg['referral_reward_days'] ?? 0);
            if ($days < 1) return;
            $user = Database::fetchOne('SELECT referred_by, referral_rewarded FROM users WHERE id = ?', [$userId]);
            if (!$user || !$user['referred_by'] || $user['referral_rewarded']) return;
            if (!Database::query('UPDATE users SET referral_rewarded = 1 WHERE id = ? AND referral_rewarded = 0', [$userId])->rowCount()) return;

            self::extend($userId, $days);
            Notification::create($userId, 'premium_activated', ['title' => 'A welcome gift', 'body' => "You joined through a friend, so {$days} Lucid nights are yours."]);

            $referrer = Database::fetchOne('SELECT id FROM users WHERE id = ? AND is_active = 1', [$user['referred_by']]);
            if (!$referrer) return;
            $thisYear = Database::fetchOne(
                'SELECT COUNT(*) AS c FROM users WHERE referred_by = ? AND referral_rewarded = 1 AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 365 DAY)',
                [$referrer['id']]
            );
            if ((int)$thisYear['c'] > (int)($cfg['referral_max_rewards_per_year'] ?? 12)) return;
            self::extend($referrer['id'], $days);
            Notification::create($referrer['id'], 'premium_activated', ['title' => 'Your invitation was accepted', 'body' => "A friend saved their first dream, so {$days} Lucid nights were added for you."]);
        } catch (Throwable $e) {
            error_log('Oneiros: referral reward failed: ' . $e->getMessage());
        }
    }

    /** One gentle reminder three days before a pass ends. */
    public static function remind(string $userId): void
    {
        try {
            $row = Database::fetchOne('SELECT premium_until, premium_reminded_until FROM users WHERE id = ?', [$userId]);
            $until = $row['premium_until'] ?? null;
            if (!self::isActiveUntil($until) || $row['premium_reminded_until'] === $until) return;
            if (strtotime($until . ' UTC') - time() > 3 * 86400) return;
            if (!Database::query('UPDATE users SET premium_reminded_until = premium_until WHERE id = ? AND (premium_reminded_until IS NULL OR premium_reminded_until <> premium_until)', [$userId])->rowCount()) return;
            Notification::create($userId, 'premium_expiring', [
                'title' => 'Your Lucid pass is almost complete',
                'body'  => 'It ends on ' . gmdate('j F', strtotime($until . ' UTC')) . '. Nothing renews automatically; add more nights whenever you like.',
            ]);
        } catch (Throwable $e) {}
    }

    /** Order details that are safe to show the buyer. */
    public static function publicOrder(array $order): array
    {
        return [
            'id' => $order['id'], 'reference' => $order['reference'], 'plan_id' => $order['plan_id'], 'plan_name' => $order['plan_name'],
            'kind' => $order['kind'], 'days' => (int)$order['days'], 'amount_cents' => (int)$order['amount_cents'],
            'discount_cents' => (int)$order['discount_cents'], 'discount_code' => $order['discount_code'],
            'amount' => self::money((int)$order['amount_cents']), 'currency' => $order['currency'], 'provider' => $order['provider'],
            'status' => $order['status'], 'gift_code' => $order['status'] === 'paid' ? $order['gift_code'] : null,
            'recipient_email' => $order['recipient_email'], 'premium_until' => self::iso($order['premium_until']),
            'created_at' => self::iso($order['created_at']), 'paid_at' => self::iso($order['paid_at']),
        ];
    }

    public static function logEvent(string $provider, ?string $orderId, string $event, bool $verified, string $detail = ''): void
    {
        try {
            Database::insert('payment_events', [
                'id' => Helpers::uuid(), 'provider' => $provider, 'order_id' => $orderId,
                'event' => mb_substr($event, 0, 60), 'verified' => (int)$verified, 'detail' => mb_substr($detail, 0, 500),
            ]);
        } catch (Throwable $e) {
            error_log("Oneiros payment event ($provider/$event): $detail");
        }
    }
}
