<?php
/**
 * Oneiros — Dream Analysis Engine
 * Phase 1: weighted keyword matching + bigram overlap + related-theme credit.
 * Phase 2 hook: swap analyse() for OpenAI call.
 */
class Analysis
{
    private static array $THEME_KEYWORDS = [
        'flying'         => ['fly','flying','float','floating','soar','soaring','hover','levitat','airborne','above the ground','weightless'],
        'falling'        => ['fall','falling','drop','dropping','plunge','plunging','tumble','descent','descend','sinking'],
        'water'          => ['water','ocean','sea','river','lake','flood','rain','wave','waves','swim','drown','tide','submerge','underwater'],
        'pursuit'        => ['chase','chasing','run','running','escape','flee','fled','hunt','pursue','following me','being chased'],
        'architecture'   => ['house','building','room','door','window','stairs','corridor','hallway','tower','city','castle','structure','mansion','palace'],
        'nature'         => ['forest','tree','trees','mountain','field','garden','earth','ground','cloud','sun','moon','star','jungle','valley'],
        'darkness'       => ['dark','darkness','shadow','shadows','black','night','void','abyss','blind','invisible','pitch black'],
        'light'          => ['light','bright','glow','shine','luminous','radiant','illuminate','golden','white','blinding','blazing'],
        'figures'        => ['person','figure','stranger','man','woman','child','creature','being','entity','presence','someone','faceless'],
        'transformation' => ['change','transform','become','morph','shift','alter','metamorph','shape-shift','turning into'],
        'loss'           => ['lost','lose','missing','gone','disappeared','search','searching','forget','forgot','alone','left behind'],
        'time'           => ['time','past','future','memory','remember','forget','old','childhood','ancient','endless','repeating'],
        'surreal'        => ['strange','impossible','wrong','distorted','surreal','bizarre','absurd','weird','not right','didn\'t make sense'],
        'death'          => ['death','die','dead','dying','grave','ghost','spirit','afterlife','corpse','funeral'],
        'animals'        => ['animal','dog','cat','bird','wolf','snake','horse','lion','fish','spider','creature','bear','deer'],
    ];

    private static array $SYMBOL_KEYWORDS = [
        'door'      => ['door','gate','entrance','portal','threshold','opening','archway'],
        'water'     => ['ocean','sea','river','lake','water','flood','rain','tide'],
        'tower'     => ['tower','spire','building','skyscraper','pillar','column','obelisk'],
        'mirror'    => ['mirror','reflection','glass','reflect','doppelganger','double'],
        'key'       => ['key','lock','unlock','locked','open','sealed'],
        'staircase' => ['stair','stairs','staircase','steps','ladder','escalator'],
        'clock'     => ['clock','time','watch','hour','minute','countdown','ticking'],
        'fire'      => ['fire','flame','burning','smoke','ash','ember','blaze','inferno'],
        'darkness'  => ['dark','darkness','shadow','void','abyss','black','obscured'],
        'light'     => ['light','bright','glow','radiance','illuminate','beam','luminous'],
        'crowd'     => ['crowd','people','many','masses','surrounded','alone in a crowd'],
        'bridge'    => ['bridge','crossing','span','cross','overpass','viaduct'],
        'forest'    => ['forest','trees','woods','jungle','tree','grove','thicket'],
        'spiral'    => ['spiral','circle','loop','cycle','rotating','whirlpool','vortex'],
        'city'      => ['city','town','urban','streets','buildings','metropolis','skyline'],
        'child'     => ['child','children','baby','young','childhood','infant','toddler'],
        'vehicle'   => ['car','train','plane','bus','ship','boat','vehicle','flying car'],
        'weapon'    => ['sword','gun','knife','weapon','blade','fight','combat'],
    ];

    private static array $NARRATIVE_PATTERNS = [
        'pursuit'        => ['chase','run','escape','flee','hunt','follow','catch','caught','being chased'],
        'descent'        => ['fall','descend','sink','go down','deeper','lower','underground','down','below'],
        'discovery'      => ['find','found','discover','reveal','uncover','suddenly','realise','realize','secret'],
        'transformation' => ['become','transform','change','morph','turn into','different','shifted'],
        'loss'           => ['lose','lost','missing','gone','disappear','forget','vanish','left behind'],
        'ascent'         => ['rise','ascend','fly','climb','up','higher','above','float up','lifted'],
        'entrapment'     => ['trapped','stuck','cannot','unable','locked','prison','cage','impossible','paralysed'],
    ];

    private static array $EMOTION_KEYWORDS = [
        'fear'      => ['afraid','fear','terrified','scared','terror','horror','dread','panic','frightened'],
        'wonder'    => ['wonder','amazed','beautiful','magnificent','awe','breathtaking','incredible','astonishing'],
        'sadness'   => ['sad','cry','tears','grief','sorrow','loss','alone','lonely','heartbroken'],
        'joy'       => ['happy','joy','laugh','delight','wonderful','excited','elated','free','bliss'],
        'confusion' => ['confused','strange','weird','wrong','bizarre','unclear','lost','disoriented'],
        'peace'     => ['calm','peace','peaceful','serene','quiet','gentle','still','safe','tranquil'],
        'longing'   => ['want','wish','long','yearning','miss','reach','unreachable','ache for'],
        'dread'     => ['dread','dark','danger','threat','unsafe','unease','foreboding','ominous'],
    ];

    /** Theme clusters for partial-credit matching */
    private static array $THEME_CLUSTERS = [
        ['darkness', 'light'],         // opposites — still thematically linked
        ['flying', 'falling'],         // vertical movement
        ['pursuit', 'entrapment'],     // actually a narrative overlap here too
        ['water', 'nature'],
        ['transformation', 'surreal'],
        ['loss', 'time'],
        ['figures', 'animals'],
    ];

    /** Narrative arc affinity for partial credit */
    private static array $NARRATIVE_AFFINITY = [
        'pursuit'        => ['entrapment' => 60, 'descent' => 40],
        'descent'        => ['pursuit' => 40, 'entrapment' => 60, 'loss' => 50],
        'discovery'      => ['transformation' => 50, 'ascent' => 40],
        'transformation' => ['discovery' => 50, 'ascent' => 40],
        'loss'           => ['descent' => 50, 'entrapment' => 40],
        'ascent'         => ['discovery' => 40, 'transformation' => 40, 'flying' => 60],
        'entrapment'     => ['pursuit' => 60, 'descent' => 60],
    ];

    /** Rarity weights — rarer themes score higher when matched */
    private static array $THEME_RARITY = [
        'flying'         => 1.1,
        'falling'        => 1.0,
        'water'          => 0.9,
        'pursuit'        => 1.0,
        'architecture'   => 1.2,
        'nature'         => 0.8,
        'darkness'       => 0.8,
        'light'          => 0.8,
        'figures'        => 0.9,
        'transformation' => 1.4,
        'loss'           => 1.1,
        'time'           => 1.3,
        'surreal'        => 1.4,
        'death'          => 1.7,
        'animals'        => 1.2,
    ];

    private static array $SYMBOL_RARITY = [
        'door'      => 1.0,
        'water'     => 0.9,
        'tower'     => 1.2,
        'mirror'    => 1.4,
        'key'       => 1.3,
        'staircase' => 1.1,
        'clock'     => 1.3,
        'fire'      => 1.0,
        'darkness'  => 0.8,
        'light'     => 0.8,
        'crowd'     => 1.0,
        'bridge'    => 1.2,
        'forest'    => 0.9,
        'spiral'    => 1.5,
        'city'      => 0.9,
        'child'     => 1.3,
        'vehicle'   => 1.1,
        'weapon'    => 1.2,
    ];

    public static function analyse(string $content, array $userEmotions = []): array
    {
        $text = mb_strtolower($content);

        $themes = [];
        foreach (self::$THEME_KEYWORDS as $theme => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) { $themes[] = $theme; break; }
            }
        }
        $themes = array_slice(array_unique($themes), 0, 8);

        $symbols = [];
        foreach (self::$SYMBOL_KEYWORDS as $symbol => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) { $symbols[] = $symbol; break; }
            }
        }
        $symbols = array_slice(array_unique($symbols), 0, 10);

        $bestArc = 'unknown';
        $bestScore = 0;
        foreach (self::$NARRATIVE_PATTERNS as $arc => $patterns) {
            $score = 0;
            foreach ($patterns as $p) {
                if (str_contains($text, $p)) $score++;
            }
            if ($score > $bestScore) { $bestScore = $score; $bestArc = $arc; }
        }

        $emotionScore = [];
        foreach ($userEmotions as $e) {
            $emotionScore[$e] = 0.8;
        }
        foreach (self::$EMOTION_KEYWORDS as $emotion => $keywords) {
            $matches = 0;
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) $matches++;
            }
            if ($matches > 0 && !isset($emotionScore[$emotion])) {
                $emotionScore[$emotion] = min($matches * 0.35, 1.0);
            }
        }

        return [
            'themes'        => $themes,
            'symbols'       => $symbols,
            'narrative_arc' => $bestArc,
            'emotion_score' => $emotionScore,
        ];
    }

    public static function score(array $dreamA, array $dreamB): array
    {
        $themesA   = is_array($dreamA['themes'])  ? $dreamA['themes']  : [];
        $themesB   = is_array($dreamB['themes'])  ? $dreamB['themes']  : [];
        $symbolsA  = is_array($dreamA['symbols']) ? $dreamA['symbols'] : [];
        $symbolsB  = is_array($dreamB['symbols']) ? $dreamB['symbols'] : [];
        $emotionsA = is_array($dreamA['emotions']) ? $dreamA['emotions'] : [];
        $emotionsB = is_array($dreamB['emotions']) ? $dreamB['emotions'] : [];

        // Weighted Jaccard for themes (with partial cluster credit)
        $themeScore  = self::weightedThemeScore($themesA, $themesB);

        // Weighted Jaccard for symbols
        $symbolScore = self::weightedJaccard($symbolsA, $symbolsB, self::$SYMBOL_RARITY) * 100;

        // Emotion cosine-like similarity
        $allEmotions    = array_unique(array_merge($emotionsA, $emotionsB));
        $sharedEmotions = array_intersect($emotionsA, $emotionsB);
        $emotionScore   = count($allEmotions) > 0
            ? (count($sharedEmotions) / count($allEmotions)) * 100
            : 0;

        // Narrative arc with affinity partial credit
        $narrScore = self::narrativeScore(
            $dreamA['narrative_arc'] ?? 'unknown',
            $dreamB['narrative_arc'] ?? 'unknown'
        );

        // Bigram overlap bonus on raw content
        $contentBonus = self::bigramOverlap(
            $dreamA['content'] ?? '',
            $dreamB['content'] ?? ''
        );

        $recencyWeight = self::recencyWeight($dreamB['dreamed_at'] ?? 'now');
        $lengthFactor  = self::lengthFactor($dreamA['content'] ?? '', $dreamB['content'] ?? '');

        $rawScore = $themeScore  * 0.28
                  + $emotionScore * 0.23
                  + $symbolScore  * 0.23
                  + $narrScore   * 0.18
                  + $contentBonus * 0.08;

        $score = round(min(100, $rawScore * $recencyWeight * $lengthFactor), 2);

        return [
            'score'           => $score,
            'theme_score'     => round($themeScore, 2),
            'emotion_score'   => round($emotionScore, 2),
            'symbol_score'    => round($symbolScore, 2),
            'narrative_score' => round($narrScore, 2),
            'recency_weight'  => $recencyWeight,
        ];
    }

    // ── private helpers ────────────────────────────────────────

    private static function weightedThemeScore(array $a, array $b): float
    {
        if (!$a || !$b) return 0;

        $matchScore = 0;
        $maxScore   = 0;
        $seen       = [];

        $allThemes = array_unique(array_merge($a, $b));
        foreach ($allThemes as $t) {
            $w = self::$THEME_RARITY[$t] ?? 1.0;
            $maxScore += $w;
        }
        if ($maxScore === 0) return 0;

        foreach ($a as $ta) {
            $wa = self::$THEME_RARITY[$ta] ?? 1.0;
            if (in_array($ta, $b)) {
                $matchScore += $wa;
                $seen[] = $ta;
                continue;
            }
            // Partial cluster credit
            foreach (self::$THEME_CLUSTERS as $cluster) {
                if (in_array($ta, $cluster)) {
                    foreach ($b as $tb) {
                        if (in_array($tb, $cluster) && $ta !== $tb && !in_array($tb, $seen)) {
                            $matchScore += $wa * 0.35;
                            $seen[] = $tb;
                        }
                    }
                }
            }
        }

        return min(100, ($matchScore / $maxScore) * 100 * 1.15);
    }

    private static function weightedJaccard(array $a, array $b, array $weights): float
    {
        if (!$a || !$b) return 0;
        $shared = array_intersect($a, $b);
        $union  = array_unique(array_merge($a, $b));
        if (!$union) return 0;

        $sharedW = array_sum(array_map(static fn($k) => $weights[$k] ?? 1.0, $shared));
        $unionW  = array_sum(array_map(static fn($k) => $weights[$k] ?? 1.0, $union));
        return $unionW > 0 ? $sharedW / $unionW : 0;
    }

    private static function narrativeScore(string $arcA, string $arcB): float
    {
        if ($arcA === 'unknown' || $arcB === 'unknown') return 0;
        if ($arcA === $arcB) return 100;
        return (float)(self::$NARRATIVE_AFFINITY[$arcA][$arcB]
            ?? self::$NARRATIVE_AFFINITY[$arcB][$arcA]
            ?? 0);
    }

    /** Extract character bigrams from text, return overlap score 0-100 */
    private static function bigramOverlap(string $a, string $b): float
    {
        if (strlen($a) < 30 || strlen($b) < 30) return 0;
        $a = mb_strtolower(preg_replace('/[^a-z0-9\s]/i', '', $a));
        $b = mb_strtolower(preg_replace('/[^a-z0-9\s]/i', '', $b));
        $wordsA = array_unique(array_filter(explode(' ', $a)));
        $wordsB = array_unique(array_filter(explode(' ', $b)));
        // word-level overlap (less strict than bigrams, more reliable without ML)
        $shared = array_intersect($wordsA, $wordsB);
        // filter out stop words
        $stops  = ['the','a','an','and','or','but','in','on','at','to','of','it','is','was','i','my','me','we','they'];
        $shared = array_diff($shared, $stops);
        $total  = count(array_unique(array_merge($wordsA, $wordsB)));
        return $total > 0 ? min(100, (count($shared) / $total) * 200) : 0;
    }

    private static function lengthFactor(string $a, string $b): float
    {
        $minLen = min(strlen($a), strlen($b));
        if ($minLen < 30)  return 0.5;
        if ($minLen < 80)  return 0.75;
        if ($minLen < 150) return 0.9;
        return 1.0;
    }

    private static function recencyWeight(string $dreamedAt): float
    {
        try {
            $hours = (time() - strtotime($dreamedAt)) / 3600;
            if ($hours <= 24)  return 1.0;
            if ($hours <= 168) return 0.80;
            if ($hours <= 720) return 0.60;
            if ($hours <= 2160) return 0.40;
            return 0.25;
        } catch (Exception $e) {
            return 0.5;
        }
    }
}
