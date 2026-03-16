import { db } from '../db/client'
import { Dream, CreateDreamRequest, UpdateDreamRequest, Privacy, NarrativeArc } from '../types'
import { logger } from '../utils/logger'
import { analyseDream } from './analysis.service'
import { queueMatchingJob } from './matching.service'

// ─── Create dream ─────────────────────────────────────────────

export async function createDream(userId: string, data: CreateDreamRequest): Promise<Dream> {
  const { content, title, emotions = [], privacy = 'public', dreamed_at } = data

  // Insert the dream first
  const { data: dream, error } = await db
    .from('dreams')
    .insert({
      user_id: userId,
      content,
      title: title || null,
      emotions,
      privacy,
      dreamed_at: dreamed_at || new Date().toISOString()
    })
    .select()
    .single()

  if (error || !dream) {
    logger.error('Dream creation failed', { error, userId })
    throw new Error('Failed to save dream')
  }

  // Async: analyse dream content (extract themes, symbols, narrative arc)
  // Then queue matching job — all non-blocking
  if (privacy !== 'private') {
    setImmediate(async () => {
      try {
        await analyseDreamAndUpdate(dream.id, content, emotions)
        await queueMatchingJob(dream.id, userId)
      } catch (err) {
        logger.error('Background dream analysis failed', { dreamId: dream.id, err })
      }
    })
  }

  return dream as Dream
}

// ─── Get user's dreams ────────────────────────────────────────

export async function getUserDreams(
  userId: string,
  page = 1,
  limit = 20
): Promise<{ dreams: Dream[]; total: number }> {
  const offset = (page - 1) * limit

  const { data, error, count } = await db
    .from('dreams')
    .select('*', { count: 'exact' })
    .eq('user_id', userId)
    .eq('is_removed', false)
    .order('dreamed_at', { ascending: false })
    .range(offset, offset + limit - 1)

  if (error) throw new Error('Failed to fetch dreams')

  return { dreams: (data || []) as Dream[], total: count || 0 }
}

// ─── Get single dream ─────────────────────────────────────────

export async function getDreamById(dreamId: string, requestingUserId: string): Promise<Dream> {
  const { data: dream, error } = await db
    .from('dreams')
    .select('*')
    .eq('id', dreamId)
    .eq('is_removed', false)
    .single()

  if (error || !dream) throw new Error('Dream not found')

  // Only owner can see private dreams
  if ((dream as Dream).privacy === 'private' && (dream as Dream).user_id !== requestingUserId) {
    throw new Error('Dream not found')
  }

  return dream as Dream
}

// ─── Update dream ─────────────────────────────────────────────

export async function updateDream(
  dreamId: string,
  userId: string,
  updates: UpdateDreamRequest
): Promise<Dream> {
  // Verify ownership
  const { data: existing } = await db
    .from('dreams')
    .select('id, user_id')
    .eq('id', dreamId)
    .eq('user_id', userId)
    .single()

  if (!existing) throw new Error('Dream not found or access denied')

  const { data: dream, error } = await db
    .from('dreams')
    .update({ ...updates, updated_at: new Date().toISOString() })
    .eq('id', dreamId)
    .select()
    .single()

  if (error || !dream) throw new Error('Failed to update dream')

  return dream as Dream
}

// ─── Delete dream ─────────────────────────────────────────────

export async function deleteDream(dreamId: string, userId: string): Promise<void> {
  const { error } = await db
    .from('dreams')
    .delete()
    .eq('id', dreamId)
    .eq('user_id', userId)

  if (error) throw new Error('Failed to delete dream')
}

// ─── Update privacy ───────────────────────────────────────────

export async function updateDreamPrivacy(
  dreamId: string,
  userId: string,
  privacy: Privacy
): Promise<Dream> {
  const { data: dream, error } = await db
    .from('dreams')
    .update({ privacy, updated_at: new Date().toISOString() })
    .eq('id', dreamId)
    .eq('user_id', userId)
    .select()
    .single()

  if (error || !dream) throw new Error('Failed to update privacy')

  // If making public, trigger analysis & matching
  if (privacy !== 'private') {
    setImmediate(async () => {
      await analyseDreamAndUpdate(dream.id, (dream as Dream).content, (dream as Dream).emotions)
      await queueMatchingJob(dream.id, userId)
    })
  }

  return dream as Dream
}

// ─── Attach image URL ─────────────────────────────────────────

export async function attachDreamImage(
  dreamId: string,
  userId: string,
  imageUrl: string,
  aiGenerated: boolean
): Promise<Dream> {
  const { data: dream, error } = await db
    .from('dreams')
    .update({
      image_url: imageUrl,
      ai_generated_image: aiGenerated,
      updated_at: new Date().toISOString()
    })
    .eq('id', dreamId)
    .eq('user_id', userId)
    .select()
    .single()

  if (error || !dream) throw new Error('Failed to attach image')
  return dream as Dream
}

// ─── Background analysis ──────────────────────────────────────

async function analyseDreamAndUpdate(
  dreamId: string,
  content: string,
  emotions: string[]
): Promise<void> {
  const analysis = await analyseDream(content, emotions)

  await db
    .from('dreams')
    .update({
      themes: analysis.themes,
      symbols: analysis.symbols,
      narrative_arc: analysis.narrative_arc,
      emotion_score: analysis.emotion_score
    })
    .eq('id', dreamId)

  logger.debug('Dream analysis complete', { dreamId, themes: analysis.themes })
}

// ─── Flag dream (moderation) ──────────────────────────────────

export async function flagDream(
  dreamId: string,
  reporterId: string,
  reason: string,
  notes?: string
): Promise<void> {
  // Insert flag record
  await db.from('moderation_flags').insert({
    dream_id: dreamId,
    reporter_id: reporterId,
    reason,
    notes: notes || null
  })

  // Increment flag count
  await db.rpc('increment_flag_count', { dream_id: dreamId })
}
