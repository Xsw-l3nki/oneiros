<?php
/**
 * Oneiros — Streak & Badge Engine
 * Called after every dream log to update streaks and award badges.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

// ─────────────────────────────────────────────────────────────────
// STREAK ENGINE
// ─────────────────────────────────────────────────────────────────

class Streak
{
    /**
     * Update streak after a dream is logged.
     * Returns new streak data ['current', 'longest', 'streak_broken', 'first_today'].
     */
    public static function update(string $userId): array
    {
        $user = Database::fetchOne(
            'SELECT current_streak, longest_streak, last_dream_date FROM users WHERE id = ?',
            [$userId]
        );

        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastDate  = $user['last_dream_date'] ?? null;

        // Already logged today — no change
        if ($lastDate === $today) {
            return [
                'current'       => (int)$user['current_streak'],
                'longest'       => (int)$user['longest_streak'],
                'streak_broken' => false,
                'first_today'   => false,
            ];
        }

        // Determine new streak
        if ($lastDate === $yesterday) {
            $newStreak = (int)$user['current_streak'] + 1;
        } else {
            $newStreak = 1;   // Gap — reset
        }

        $wasBroken  = ($lastDate !== null && $lastDate !== $yesterday);
        $newLongest = max($newStreak, (int)$user['longest_streak']);

        Database::query(
            'UPDATE users SET current_streak = ?, longest_streak = ?, last_dream_date = ? WHERE id = ?',
            [$newStreak, $newLongest, $today, $userId]
        );

        return [
            'current'       => $newStreak,
            'longest'       => $newLongest,
            'streak_broken' => $wasBroken,
            'first_today'   => true,
        ];
    }

    /**
     * Fetch current streak data for a user.
     */
    public static function get(string $userId): array
    {
        $user = Database::fetchOne(
            'SELECT current_streak, longest_streak, last_dream_date FROM users WHERE id = ?',
            [$userId]
        );

        $today = date('Y-m-d');
        $isActiveToday = ($user['last_dream_date'] === $today);

        return [
            'current_streak'   => !empty($user['last_dream_date']) && $user['last_dream_date'] >= date('Y-m-d', strtotime('-1 day')) ? (int)$user['current_streak'] : 0,
            'longest_streak'   => (int)$user['longest_streak'],
            'last_dream_date'  => $user['last_dream_date'],
            'is_active_today'  => $isActiveToday,
        ];
    }

    /**
     * Top 10 dreamers by current streak (anonymous — no names, just region + streak).
     */
    public static function leaderboard(): array
    {
        $rows = Database::fetchAll(
            'SELECT current_streak, longest_streak, region_code
               FROM users
              WHERE is_active = 1 AND current_streak > 0 AND last_dream_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              ORDER BY current_streak DESC, longest_streak DESC
              LIMIT 10'
        );

        return array_map(function ($r, $i) {
            return [
                'rank'           => $i + 1,
                'current_streak' => (int)$r['current_streak'],
                'longest_streak' => (int)$r['longest_streak'],
                'region_code'    => $r['region_code'] ?? null,
            ];
        }, $rows, array_keys($rows));
    }
}

// ─────────────────────────────────────────────────────────────────
// BADGE ENGINE
// ─────────────────────────────────────────────────────────────────

class Badge
{
    // All badge definitions
    public static function definitions(): array
    {
        return [
            // --- Dreaming ---
            'first_dream' => [
                'id'    => 'first_dream',
                'name'  => 'First Dream',
                'desc'  => 'You logged your first dream. The journey begins.',
                'icon'  => '🌙',
                'tier'  => 'bronze',
            ],
            'dream_3' => [
                'id'    => 'dream_3',
                'name'  => 'Three Visions',
                'desc'  => 'You\'ve logged 3 dreams.',
                'icon'  => '✨',
                'tier'  => 'bronze',
            ],
            'dream_10' => [
                'id'    => 'dream_10',
                'name'  => 'Dream Keeper',
                'desc'  => 'Your journal holds 10 dreams.',
                'icon'  => '📖',
                'tier'  => 'silver',
            ],
            'dream_50' => [
                'id'    => 'dream_50',
                'name'  => 'Deep Sleeper',
                'desc'  => 'You\'ve logged 50 dreams.',
                'icon'  => '🌊',
                'tier'  => 'gold',
            ],
            'dream_100' => [
                'id'    => 'dream_100',
                'name'  => 'Century of Dreams',
                'desc'  => '100 dreams recorded. You are a true chronicler.',
                'icon'  => '💫',
                'tier'  => 'legend',
            ],
            // --- Streaks ---
            'streak_3' => [
                'id'    => 'streak_3',
                'name'  => 'Three Nights Running',
                'desc'  => '3-day dream streak. Your subconscious remembers.',
                'icon'  => '🔥',
                'tier'  => 'bronze',
            ],
            'streak_7' => [
                'id'    => 'streak_7',
                'name'  => 'Week of Dreams',
                'desc'  => '7 consecutive nights of dreaming.',
                'icon'  => '🌈',
                'tier'  => 'silver',
            ],
            'streak_30' => [
                'id'    => 'streak_30',
                'name'  => 'Moon Cycle',
                'desc'  => 'A full lunar cycle of unbroken dreams.',
                'icon'  => '🌕',
                'tier'  => 'gold',
            ],
            'streak_100' => [
                'id'    => 'streak_100',
                'name'  => 'Dream Master',
                'desc'  => '100-day streak. You have mastered the dream state.',
                'icon'  => '⚡',
                'tier'  => 'legend',
            ],
            // --- Matching ---
            'first_match' => [
                'id'    => 'first_match',
                'name'  => 'First Resonance',
                'desc'  => 'Your dream matched with someone across the world.',
                'icon'  => '🤝',
                'tier'  => 'bronze',
            ],
            'matches_10' => [
                'id'    => 'matches_10',
                'name'  => 'Connected Dreamer',
                'desc'  => '10 dream matches found.',
                'icon'  => '🌍',
                'tier'  => 'silver',
            ],
            'matches_50' => [
                'id'    => 'matches_50',
                'name'  => 'Global Resonance',
                'desc'  => '50 dream matches — you dream with the world.',
                'icon'  => '🌐',
                'tier'  => 'gold',
            ],
            'high_resonance' => [
                'id'    => 'high_resonance',
                'name'  => 'High Resonance',
                'desc'  => 'A dream matched at 90% or higher.',
                'icon'  => '⚡',
                'tier'  => 'silver',
            ],
            // --- Social ---
            'first_connection' => [
                'id'    => 'first_connection',
                'name'  => 'Dream Companion',
                'desc'  => 'You made your first connection.',
                'icon'  => '💫',
                'tier'  => 'bronze',
            ],
            'social_dreamer' => [
                'id'    => 'social_dreamer',
                'name'  => 'Social Dreamer',
                'desc'  => '5 or more connections established.',
                'icon'  => '❤️',
                'tier'  => 'silver',
            ],
            // --- Features ---
            'voice_dreamer' => [
                'id'    => 'voice_dreamer',
                'name'  => 'Voice of Dreams',
                'desc'  => 'You used your voice to record a dream.',
                'icon'  => '🎙️',
                'tier'  => 'bronze',
            ],
            'dream_painter' => [
                'id'    => 'dream_painter',
                'name'  => 'Dream Painter',
                'desc'  => 'You painted a dream with AI.',
                'icon'  => '🎨',
                'tier'  => 'bronze',
            ],
            // --- Research ---
            'researcher' => [
                'id'    => 'researcher',
                'name'  => 'Dream Researcher',
                'desc'  => 'Your public dream data has contributed to research.',
                'icon'  => '🔬',
                'tier'  => 'silver',
            ],
            'recurring_detector' => [
                'id'    => 'recurring_detector',
                'name'  => 'Pattern Seeker',
                'desc'  => 'A recurring dream pattern has been detected in your journal.',
                'icon'  => '🌀',
                'tier'  => 'silver',
            ],
        ];
    }

    /**
     * Check and award any newly earned badges for a user.
     * Returns array of newly awarded badge IDs.
     */
    public static function check(string $userId, array $context = []): array
    {
        $defs    = self::definitions();
        $earned  = self::getUserBadgeIds($userId);
        $newBadges = [];

        // Fetch fresh user stats
        $user = Database::fetchOne(
            'SELECT current_streak, longest_streak, last_dream_date FROM users WHERE id = ?',
            [$userId]
        );

        $totalDreams = (int)(Database::fetchOne(
            'SELECT COUNT(*) as c FROM dreams WHERE user_id = ? AND is_removed = 0',
            [$userId]
        )['c'] ?? 0);

        $totalMatches = (int)(Database::fetchOne(
            'SELECT COUNT(*) as c FROM dream_matches WHERE user_a_id = ? OR user_b_id = ?',
            [$userId, $userId]
        )['c'] ?? 0);

        $connections = (int)(Database::fetchOne(
            "SELECT COUNT(*) as c FROM connections
              WHERE (requester_id = ? OR receiver_id = ?) AND status = 'connected'",
            [$userId, $userId]
        )['c'] ?? 0);

        $hasRecurring = (int)(Database::fetchOne(
            'SELECT COUNT(*) as c FROM dreams WHERE user_id = ? AND is_recurring = 1',
            [$userId]
        )['c'] ?? 0) > 0;

        $highResMatch = (bool)(Database::fetchOne(
            'SELECT id FROM dream_matches WHERE (user_a_id = ? OR user_b_id = ?) AND score >= 90 LIMIT 1',
            [$userId, $userId]
        ));

        $currentStreak = (int)($user['current_streak'] ?? 0);

        $checkMap = [
            'first_dream'        => $totalDreams >= 1,
            'dream_3'            => $totalDreams >= 3,
            'dream_10'           => $totalDreams >= 10,
            'dream_50'           => $totalDreams >= 50,
            'dream_100'          => $totalDreams >= 100,
            'streak_3'           => $currentStreak >= 3,
            'streak_7'           => $currentStreak >= 7,
            'streak_30'          => $currentStreak >= 30,
            'streak_100'         => $currentStreak >= 100,
            'first_match'        => $totalMatches >= 1,
            'matches_10'         => $totalMatches >= 10,
            'matches_50'         => $totalMatches >= 50,
            'high_resonance'     => $highResMatch,
            'first_connection'   => $connections >= 1,
            'social_dreamer'     => $connections >= 5,
            'researcher'         => (bool)Database::fetchOne('SELECT id FROM dreams WHERE user_id = ? AND privacy IN ("public", "research_only") AND is_removed = 0 LIMIT 1', [$userId]),
            'recurring_detector' => $hasRecurring,
            // voice_dreamer / dream_painter awarded via context from the API
            'voice_dreamer'      => ($context['used_voice'] ?? false),
            'dream_painter'      => ($context['used_ai_paint'] ?? false),
        ];

        foreach ($checkMap as $badgeId => $condition) {
            if ($condition && !in_array($badgeId, $earned)) {
                try {
                    Database::insert('user_badges', [
                        'id'      => Helpers::uuid(),
                        'user_id' => $userId,
                        'badge_id' => $badgeId,
                    ]);
                    $newBadges[] = $badgeId;
                } catch (PDOException $e) {
                    // Duplicate — already exists
                }
            }
        }

        return $newBadges;
    }

    /**
     * Get all badge IDs the user has earned.
     */
    public static function getUserBadgeIds(string $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT badge_id FROM user_badges WHERE user_id = ?',
            [$userId]
        );
        return array_column($rows, 'badge_id');
    }

    /**
     * Get full badge data for a user (earned + all definitions with earned status).
     */
    public static function getUserBadges(string $userId): array
    {
        $earned = Database::fetchAll(
            'SELECT badge_id, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC',
            [$userId]
        );

        $earnedMap = [];
        foreach ($earned as $e) {
            $earnedMap[$e['badge_id']] = $e['earned_at'];
        }

        $defs   = self::definitions();
        $result = [];

        foreach ($defs as $id => $def) {
            $result[] = array_merge($def, [
                'earned'    => isset($earnedMap[$id]),
                'earned_at' => $earnedMap[$id] ?? null,
            ]);
        }

        return $result;
    }
}
