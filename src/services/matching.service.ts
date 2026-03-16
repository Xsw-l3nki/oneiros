import { db } from '../db/client'
import { Dream, MatchResult } from '../types'
import { computeMatchScore } from './analysis.service'
import { logger } from '../utils/logger'
import { createNotification } from './notification.service'

const MIN_SCORE_THRESHOLD = 30  // Minimum score to store a match

// ─── Queue a matching job for a new dream ─────────────────────

export async function queueMatchingJob(dreamId: string, userId: string): Promise<void> {
  // In production: push to a job queue (Bull/BullMQ)
  // For now: run synchronously in background
  logger.info('Starting matching job', { dreamId })
  await runMatchingForDream(dreamId, userId)
}

// ─── Core matching logic ──────────────────────────────────────

async function runMatchingForDream(dreamId: string, userId: string): Promise<void> {
  // Fetch the dream to match
  const { data: sourceDream } = await db
    .from('dreams')
    .select('*')
    .eq('id', dreamId)
    .single()

  if (!sourceDream || (sourceDream as Dream).is_removed) return
  if ((sourceDream as Dream).privacy === 'private') return

  // Fetch candidate dreams (public/research_only, not from same user, not already matched)
  const { data: candidates } = await db
    .from('dreams')
    .select('*, users!inner(region, region_code)')
    .in('privacy', ['public', 'research_only'])
    .eq('is_removed', false)
    .neq('user_id', userId)
    .order('dreamed_at', { ascending: false })
    .limit(500)  // Process top 500 most recent dreams

  if (!candidates?.length) return

  const matches: Array<{
    dream_a_id: string
    dream_b_id: string
    user_a_id: string
    user_b_id: string
    score: number
    theme_score: number
    emotion_score: number
    symbol_score: number
    narrative_score: number
    recency_weight: number
  }> = []

  for (const candidate of candidates) {
    const scores = computeMatchScore(
      sourceDream as Dream,
      candidate as Dream
    )

    if (scores.score >= MIN_SCORE_THRESHOLD) {
      matches.push({
        dream_a_id: dreamId,
        dream_b_id: candidate.id,
        user_a_id: userId,
        user_b_id: (candidate as Dream).user_id,
        ...scores
      })
    }
  }

  if (matches.length === 0) {
    logger.debug('No matches found', { dreamId })
    return
  }

  // Upsert matches (ignore duplicates)
  const { error } = await db
    .from('dream_matches')
    .upsert(matches, { onConflict: 'dream_a_id,dream_b_id', ignoreDuplicates: true })

  if (error) {
    logger.error('Failed to store matches', { error, dreamId })
    return
  }

  // Update match count on the dream
  await db
    .from('dreams')
    .update({ match_count: matches.length })
    .eq('id', dreamId)

  logger.info('Matching complete', { dreamId, matchCount: matches.length })

  // Send notification
  const highResonance = matches.filter(m => m.score >= 80)
  if (matches.length > 0) {
    await createNotification(userId, 'new_matches', {
      title: `${matches.length} dreamers shared your vision`,
      body: `Your dream resonated with ${matches.length} other dreamers${highResonance.length > 0 ? `, including ${highResonance.length} with over 80% resonance` : ''}.`,
      data: { dreamId, matchCount: matches.length, highResonanceCount: highResonance.length }
    })
  }

  // Detect recurring dreams
  await detectRecurringDream(dreamId, userId, sourceDream as Dream)
}

// ─── Fetch matches for a user's dream ────────────────────────

export async function getDreamMatches(
  dreamId: string,
  userId: string,
  page = 1,
  limit = 20,
  filter?: 'theme' | 'emotion' | 'visual' | 'narrative'
): Promise<{ matches: MatchResult[]; total: number }> {
  const offset = (page - 1) * limit

  // Verify user owns this dream
  const { data: dream } = await db
    .from('dreams')
    .select('id, user_id')
    .eq('id', dreamId)
    .eq('user_id', userId)
    .single()

  if (!dream) throw new Error('Dream not found')

  // Build query — match can be in either column
  let query = db
    .from('dream_matches')
    .select(`
      id,
      dream_a_id,
      dream_b_id,
      user_a_id,
      user_b_id,
      score,
      theme_score,
      emotion_score,
      symbol_score,
      narrative_score,
      recency_weight,
      created_at
    `, { count: 'exact' })
    .or(`dream_a_id.eq.${dreamId},dream_b_id.eq.${dreamId}`)
    .order('score', { ascending: false })
    .range(offset, offset + limit - 1)

  if (filter === 'theme') query = query.gte('theme_score', 50)
  if (filter === 'emotion') query = query.gte('emotion_score', 50)
  if (filter === 'visual') query = query.gte('symbol_score', 50)
  if (filter === 'narrative') query = query.gte('narrative_score', 80)

  const { data: rawMatches, error, count } = await query

  if (error) throw new Error('Failed to fetch matches')

  // Enrich with anonymised dream data
  const enriched = await Promise.all((rawMatches || []).map(async (match) => {
    const isA = match.dream_a_id === dreamId
    const matchedDreamId = isA ? match.dream_b_id : match.dream_a_id
    const matchedUserId = isA ? match.user_b_id : match.user_a_id

    const [{ data: matchedDream }, { data: matchedUser }, { data: connection }] = await Promise.all([
      db.from('dreams').select('id,title,content,emotions,themes,symbols,narrative_arc,dreamed_at').eq('id', matchedDreamId).single(),
      db.from('users').select('region,region_code').eq('id', matchedUserId).single(),
      db.from('connections').select('status').or(
        `and(requester_id.eq.${userId},receiver_id.eq.${matchedUserId}),and(requester_id.eq.${matchedUserId},receiver_id.eq.${userId})`
      ).single()
    ])

    return {
      match_id: match.id,
      dream_id: matchedDreamId,
      score: Number(match.score),
      theme_score: Number(match.theme_score),
      emotion_score: Number(match.emotion_score),
      symbol_score: Number(match.symbol_score),
      narrative_score: Number(match.narrative_score),
      matched_dream_preview: {
        id: matchedDream?.id || '',
        title: matchedDream?.title || null,
        content_preview: (matchedDream?.content || '').substring(0, 200),
        emotions: matchedDream?.emotions || [],
        themes: matchedDream?.themes || [],
        symbols: matchedDream?.symbols || [],
        narrative_arc: matchedDream?.narrative_arc || null,
        dreamed_at: matchedDream?.dreamed_at || ''
      },
      region: matchedUser?.region || null,
      region_code: matchedUser?.region_code || null,
      connection_status: connection?.status || null,
      created_at: match.created_at
    } as MatchResult
  }))

  return { matches: enriched, total: count || 0 }
}

// ─── Get all matches for a user (across all dreams) ───────────

export async function getUserMatches(
  userId: string,
  page = 1,
  limit = 20
): Promise<{ matches: MatchResult[]; total: number }> {
  const offset = (page - 1) * limit

  const { data: userDreams } = await db
    .from('dreams')
    .select('id')
    .eq('user_id', userId)
    .eq('is_removed', false)

  if (!userDreams?.length) return { matches: [], total: 0 }

  const dreamIds = userDreams.map(d => d.id)

  const { data: rawMatches, count } = await db
    .from('dream_matches')
    .select('*', { count: 'exact' })
    .or(
      dreamIds.map(id => `dream_a_id.eq.${id}`).join(',') + ',' +
      dreamIds.map(id => `dream_b_id.eq.${id}`).join(',')
    )
    .order('score', { ascending: false })
    .range(offset, offset + limit - 1)

  return { matches: rawMatches as unknown as MatchResult[], total: count || 0 }
}

// ─── Recurring dream detection ────────────────────────────────

async function detectRecurringDream(
  dreamId: string,
  userId: string,
  dream: Dream
): Promise<void> {
  if (!dream.themes?.length) return

  // Find user's previous dreams with overlapping themes
  const { data: previousDreams } = await db
    .from('dreams')
    .select('id, themes, dreamed_at, recurring_group_id')
    .eq('user_id', userId)
    .neq('id', dreamId)
    .eq('is_removed', false)

  const similarPrevious = (previousDreams || []).filter(prev => {
    const overlap = (prev.themes || []).filter((t: string) => dream.themes.includes(t))
    return overlap.length >= 2  // At least 2 shared themes
  })

  if (similarPrevious.length >= 2) {
    // This is a recurring dream!
    const existingGroupId = similarPrevious.find(d => d.recurring_group_id)?.recurring_group_id

    if (existingGroupId) {
      // Add to existing group
      await db.from('dreams').update({
        is_recurring: true,
        recurring_group_id: existingGroupId
      }).eq('id', dreamId)

      await db.from('recurring_dream_groups').update({
        last_seen_at: dream.dreamed_at,
        occurrence_count: similarPrevious.length + 1
      }).eq('id', existingGroupId)
    } else {
      // Create new recurring group
      const { data: group } = await db.from('recurring_dream_groups').insert({
        user_id: userId,
        core_themes: dream.themes.slice(0, 5),
        first_seen_at: similarPrevious[similarPrevious.length - 1]?.dreamed_at || dream.dreamed_at,
        last_seen_at: dream.dreamed_at,
        occurrence_count: similarPrevious.length + 1
      }).select().single()

      if (group) {
        // Mark all related dreams as recurring
        const allIds = [dreamId, ...similarPrevious.map(d => d.id)]
        await db.from('dreams').update({
          is_recurring: true,
          recurring_group_id: group.id
        }).in('id', allIds)
      }
    }

    // Notify user
    await createNotification(userId, 'recurring_dream', {
      title: 'Recurring dream detected',
      body: `We've noticed this dream has appeared ${similarPrevious.length + 1} times. It has been marked as a recurring pattern in your journal.`,
      data: { dreamId, occurrences: similarPrevious.length + 1, themes: dream.themes.slice(0, 3) }
    })

    logger.info('Recurring dream detected', { userId, dreamId, occurrences: similarPrevious.length + 1 })
  }
}
