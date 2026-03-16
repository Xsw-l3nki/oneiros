import { db } from '../db/client'
import { GlobalDreamStats } from '../types'
import { logger } from '../utils/logger'

// ─── Global stats for the dream map ───────────────────────────

export async function getGlobalStats(): Promise<GlobalDreamStats> {
  const [
    { count: total_dreams },
    regionData,
    themeData,
    emotionData,
    activeData
  ] = await Promise.all([
    db.from('dreams').select('*', { count: 'exact', head: true })
      .in('privacy', ['public', 'research_only'])
      .eq('is_removed', false),

    db.from('dreams')
      .select('users!inner(region, region_code)')
      .in('privacy', ['public', 'research_only'])
      .eq('is_removed', false),

    db.from('dreams')
      .select('themes')
      .in('privacy', ['public', 'research_only'])
      .eq('is_removed', false)
      .gte('dreamed_at', new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString()),

    db.from('dreams')
      .select('emotions')
      .in('privacy', ['public', 'research_only'])
      .eq('is_removed', false)
      .gte('dreamed_at', new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString()),

    db.from('users')
      .select('id', { count: 'exact', head: true })
      .gte('last_active_at', new Date(Date.now() - 30 * 60 * 1000).toISOString())  // Active in last 30 min
  ])

  // Count unique active countries
  const countryMap = new Map<string, { region: string; count: number; themes: Map<string, number> }>()
  ;(regionData?.data || []).forEach((row: any) => {
    const rc = row.users?.region_code
    const r = row.users?.region
    if (!rc) return
    const existing = countryMap.get(rc) || { region: r, count: 0, themes: new Map() }
    existing.count++
    countryMap.set(rc, existing)
  })

  // Top themes this week
  const themeCount = new Map<string, number>()
  ;(themeData?.data || []).forEach((row: any) => {
    ;(row.themes || []).forEach((theme: string) => {
      themeCount.set(theme, (themeCount.get(theme) || 0) + 1)
    })
  })
  const top_themes = [...themeCount.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 10)
    .map(([theme, count]) => ({ theme, count }))

  // Top emotions last 24h
  const emotionCount = new Map<string, number>()
  ;(emotionData?.data || []).forEach((row: any) => {
    ;(row.emotions || []).forEach((emotion: string) => {
      emotionCount.set(emotion, (emotionCount.get(emotion) || 0) + 1)
    })
  })
  const top_emotions = [...emotionCount.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 8)
    .map(([emotion, count]) => ({ emotion, count }))

  const regional_activity = [...countryMap.entries()].map(([region_code, data]) => ({
    region_code,
    region: data.region,
    count: data.count,
    top_theme: top_themes[0]?.theme || 'unknown'
  })).sort((a, b) => b.count - a.count)

  return {
    total_dreams: total_dreams || 0,
    active_countries: countryMap.size,
    dreamers_active_now: activeData.count || 0,
    top_themes,
    top_emotions,
    regional_activity
  }
}

// ─── User personal stats ──────────────────────────────────────

export async function getUserStats(userId: string) {
  const [
    { count: total_dreams },
    { count: total_matches },
    { count: recurring_count },
    { data: top_emotions_data },
    { data: connections_data }
  ] = await Promise.all([
    db.from('dreams').select('*', { count: 'exact', head: true }).eq('user_id', userId).eq('is_removed', false),
    db.from('dream_matches').select('*', { count: 'exact', head: true }).or(`user_a_id.eq.${userId},user_b_id.eq.${userId}`),
    db.from('dreams').select('*', { count: 'exact', head: true }).eq('user_id', userId).eq('is_recurring', true),
    db.from('dreams').select('emotions').eq('user_id', userId).eq('is_removed', false),
    db.from('connections').select('id').or(`requester_id.eq.${userId},receiver_id.eq.${userId}`).eq('status', 'connected')
  ])

  // User's top emotions
  const emotionCount = new Map<string, number>()
  ;(top_emotions_data || []).forEach((row: any) => {
    ;(row.emotions || []).forEach((e: string) => {
      emotionCount.set(e, (emotionCount.get(e) || 0) + 1)
    })
  })

  return {
    total_dreams: total_dreams || 0,
    total_matches: total_matches || 0,
    recurring_dreams: recurring_count || 0,
    connections: connections_data?.length || 0,
    top_emotions: [...emotionCount.entries()]
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([emotion, count]) => ({ emotion, count }))
  }
}
