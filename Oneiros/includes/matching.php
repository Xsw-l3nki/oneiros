<?php
/**
 * Oneiros — Matching Engine
 * Runs after a dream is logged. Scores it against all public dreams.
 */

require_once __DIR__ . '/helpers.php';  // polyfills first
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analysis.php';

class Matching
{
    private const MIN_SCORE = 30;

    /** Remove obsolete relationships before an edit or privacy change and repair counts. */
    public static function clear(string $dreamId): void
    {
        $matches = Database::fetchAll('SELECT dream_a_id, dream_b_id FROM dream_matches WHERE dream_a_id = ? OR dream_b_id = ?', [$dreamId, $dreamId]);
        Database::query('DELETE FROM dream_matches WHERE dream_a_id = ? OR dream_b_id = ?', [$dreamId, $dreamId]);
        $ids = [$dreamId];
        foreach ($matches as $match) { $ids[] = $match['dream_a_id']; $ids[] = $match['dream_b_id']; }
        foreach (array_unique($ids) as $id) {
            $count = Database::fetchOne('SELECT COUNT(*) AS c FROM dream_matches WHERE dream_a_id = ? OR dream_b_id = ?', [$id, $id]);
            Database::query('UPDATE dreams SET match_count = ? WHERE id = ?', [(int)$count['c'], $id]);
        }
    }

    /**
     * Run matching for a newly created dream.
     * Stores matches and triggers notifications.
     */
    public static function process(string $dreamId, string $userId): int
    {
        $source = Database::fetchOne('SELECT * FROM dreams WHERE id = ?', [$dreamId]);
        if (!$source || $source['user_id'] !== $userId || $source['is_removed']) return 0;

        $source = Helpers::formatDream($source);
        self::detectRecurring($dreamId, $userId, $source);
        if ($source['privacy'] !== 'public') return 0;

        // Fetch candidate dreams (public/research_only, not from same user)
        $candidates = Database::fetchAll(
            'SELECT d.*, u.region, u.region_code
               FROM dreams d
               JOIN users u ON d.user_id = u.id
              WHERE d.privacy = "public" AND u.is_active = 1
                AND d.is_removed = 0
                AND d.user_id != ?
                AND NOT EXISTS (SELECT 1 FROM connections c WHERE c.status = "blocked"
                    AND ((c.requester_id = ? AND c.receiver_id = d.user_id) OR (c.receiver_id = ? AND c.requester_id = d.user_id)))
              ORDER BY d.dreamed_at DESC
              LIMIT 500',
            [$userId, $userId, $userId]
        );

        $matchCount = 0;
        $highResonance = 0;
        $matchedPartners = [];  // Track partners for reciprocal notifications

        foreach ($candidates as $cand) {
            $cand = Helpers::formatDream($cand);
            $scores = Analysis::score($source, $cand);
            if ($scores['score'] < self::MIN_SCORE) continue;

            // Insert match (ignore if already exists)
            try {
                $sourceFirst = strcmp($dreamId, $cand['id']) < 0;
                Database::insert('dream_matches', [
                    'id'              => Helpers::uuid(),
                    'dream_a_id'      => $sourceFirst ? $dreamId : $cand['id'],
                    'dream_b_id'      => $sourceFirst ? $cand['id'] : $dreamId,
                    'user_a_id'       => $sourceFirst ? $userId : $cand['user_id'],
                    'user_b_id'       => $sourceFirst ? $cand['user_id'] : $userId,
                    'score'           => $scores['score'],
                    'theme_score'     => $scores['theme_score'],
                    'emotion_score'   => $scores['emotion_score'],
                    'symbol_score'    => $scores['symbol_score'],
                    'narrative_score' => $scores['narrative_score'],
                    'recency_weight'  => $scores['recency_weight'],
                ]);
                $matchCount++;
                if ($scores['score'] >= 80) $highResonance++;

                // Increment match_count on the matched dream too
                Database::query('UPDATE dreams SET match_count = match_count + 1 WHERE id = ?', [$cand['id']]);

                // Track partners for reciprocal notification
                $partnerId = $cand['user_id'];
                if (!isset($matchedPartners[$partnerId])) {
                    $matchedPartners[$partnerId] = ['count' => 0, 'high' => 0];
                }
                $matchedPartners[$partnerId]['count']++;
                if ($scores['score'] >= 80) $matchedPartners[$partnerId]['high']++;
            } catch (PDOException $e) {
                // Likely a duplicate — skip silently
            }
        }

        // Update source dream's match count
        Database::query('UPDATE dreams SET match_count = match_count + ? WHERE id = ?', [$matchCount, $dreamId]);

        // Notify each partner (reciprocal — only one notification per partner)
        foreach ($matchedPartners as $partnerId => $info) {
            Notification::create($partnerId, 'new_matches', [
                'title' => $info['high'] > 0 ? 'A high-resonance dream match' : 'A new dreamer resonates with you',
                'body'  => $info['high'] > 0
                    ? "Someone just logged a dream with over 80% resonance to one of yours."
                    : "Someone just logged a dream that resonates with one of yours.",
                'data'  => ['type' => 'reciprocal_match'],
            ]);
        }

        // Notify user
        if ($matchCount > 0) {
            Notification::create($userId, 'new_matches', [
                'title' => "$matchCount dreamers shared your vision",
                'body'  => $highResonance > 0
                    ? "Your dream resonated with $matchCount other dreamers, including $highResonance with over 80% resonance."
                    : "Your dream resonated with $matchCount other dreamers.",
                'data'  => ['dreamId' => $dreamId, 'matchCount' => $matchCount, 'highResonanceCount' => $highResonance],
            ]);
        }

        // Recurring dream detection

        return $matchCount;
    }

    /**
     * Detect if this dream is part of a recurring pattern.
     */
    private static function detectRecurring(string $dreamId, string $userId, array $dream): void
    {
        if (count($dream['themes']) === 0) return;

        $previous = Database::fetchAll(
            'SELECT id, themes, symbols, dreamed_at, recurring_group_id
               FROM dreams
              WHERE user_id = ? AND id != ? AND is_removed = 0',
            [$userId, $dreamId]
        );

        // Broad themes (light, nature, time…) overlap in most dreams, so require a strong
        // overall resemblance plus a shared symbol or a larger shared set of themes.
        $themes = array_unique($dream['themes']);
        $symbols = array_unique($dream['symbols'] ?? []);
        $similar = [];
        foreach ($previous as $prev) {
            $prevThemes = array_unique(Helpers::decodeArray($prev['themes']));
            $overlap = count(array_intersect($themes, $prevThemes));
            $union = count(array_unique(array_merge($themes, $prevThemes)));
            $sharedSymbols = count(array_intersect($symbols, Helpers::decodeArray($prev['symbols'])));
            if ($overlap >= 2 && $overlap / max(1, $union) >= 0.5 && ($sharedSymbols >= 1 || $overlap >= 3)) {
                $similar[] = $prev;
            }
        }

        if (count($similar) >= 2) {
            $existingGroupId = null;
            foreach ($similar as $s) {
                if (!empty($s['recurring_group_id'])) { $existingGroupId = $s['recurring_group_id']; break; }
            }

            if ($existingGroupId) {
                Database::query('UPDATE dreams SET is_recurring = 1, recurring_group_id = ? WHERE id = ?',
                    [$existingGroupId, $dreamId]);
                Database::query('UPDATE recurring_dream_groups SET last_seen_at = ?, occurrence_count = ? WHERE id = ?',
                    [$dream['dreamed_at'], count($similar) + 1, $existingGroupId]);
            } else {
                $groupId = Helpers::uuid();
                Database::insert('recurring_dream_groups', [
                    'id'              => $groupId,
                    'user_id'         => $userId,
                    'core_themes'     => Helpers::encodeArray(array_slice($dream['themes'], 0, 5)),
                    'first_seen_at'   => $similar[count($similar)-1]['dreamed_at'] ?? $dream['dreamed_at'],
                    'last_seen_at'    => $dream['dreamed_at'],
                    'occurrence_count' => count($similar) + 1,
                ]);
                $allIds = array_merge([$dreamId], array_column($similar, 'id'));
                $placeholders = implode(',', array_fill(0, count($allIds), '?'));
                Database::query(
                    "UPDATE dreams SET is_recurring = 1, recurring_group_id = ? WHERE id IN ($placeholders)",
                    array_merge([$groupId], $allIds)
                );
            }

            $occurrences = count($similar) + 1;
            Notification::create($userId, 'recurring_dream', [
                'title' => 'Recurring dream detected',
                'body'  => "We've noticed this dream has appeared $occurrences times. It has been marked as a recurring pattern in your journal.",
                'data'  => ['dreamId' => $dreamId, 'occurrences' => $occurrences],
            ]);
        }
    }
}

class Notification
{
    public static function create(string $userId, string $type, array $data): void
    {
        try {
            Database::insert('notifications', [
                'id'      => Helpers::uuid(),
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $data['title'] ?? 'Oneiros',
                'body'    => $data['body']  ?? '',
                'data'    => json_encode($data['data'] ?? []),
            ]);
        } catch (Exception $e) {
            error_log('Oneiros notification error: ' . $e->getMessage());
        }
    }
}
