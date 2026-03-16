import { NarrativeArc } from '../types'
import { logger } from '../utils/logger'

// ─── Analysis result ──────────────────────────────────────────

export interface DreamAnalysis {
  themes: string[]
  symbols: string[]
  narrative_arc: NarrativeArc
  emotion_score: Record<string, number>
}

// ─── Theme taxonomy ───────────────────────────────────────────

const THEME_KEYWORDS: Record<string, string[]> = {
  flying:      ['fly', 'flying', 'float', 'floating', 'soar', 'soaring', 'hover', 'levitat', 'airborne', 'above', 'sky'],
  falling:     ['fall', 'falling', 'drop', 'dropping', 'plunge', 'plunging', 'tumble', 'descent', 'descend'],
  water:       ['water', 'ocean', 'sea', 'river', 'lake', 'flood', 'rain', 'wave', 'waves', 'swim', 'drown', 'tide', 'submerge'],
  pursuit:     ['chase', 'chasing', 'run', 'running', 'escape', 'escape', 'follow', 'follow', 'flee', 'fled', 'hunt', 'pursue'],
  architecture:['house', 'building', 'room', 'door', 'window', 'stairs', 'corridor', 'hallway', 'tower', 'city', 'castle', 'structure'],
  nature:      ['forest', 'tree', 'trees', 'mountain', 'field', 'garden', 'earth', 'ground', 'sky', 'cloud', 'sun', 'moon', 'star'],
  darkness:    ['dark', 'darkness', 'shadow', 'black', 'night', 'void', 'abyss', 'blind', 'invisible'],
  light:       ['light', 'bright', 'glow', 'shine', 'luminous', 'radiant', 'illuminate', 'golden', 'white'],
  figures:     ['person', 'figure', 'stranger', 'shadow', 'man', 'woman', 'child', 'creature', 'being', 'entity', 'presence'],
  transformation: ['change', 'transform', 'become', 'morph', 'shift', 'alter', 'different', 'metamorph'],
  loss:        ['lost', 'lose', 'missing', 'gone', 'disappeared', 'search', 'searching', 'forget', 'forgot', 'alone'],
  time:        ['time', 'past', 'future', 'memory', 'remember', 'forget', 'old', 'childhood', 'ancient', 'endless'],
  surreal:     ['strange', 'impossible', 'wrong', 'distorted', 'surreal', 'bizarre', 'impossible', 'absurd', 'weird'],
  death:       ['death', 'die', 'dead', 'dying', 'grave', 'ghost', 'spirit', 'afterlife', 'dead'],
  animals:     ['animal', 'dog', 'cat', 'bird', 'wolf', 'snake', 'horse', 'lion', 'fish', 'spider', 'creature']
}

// ─── Symbol taxonomy ──────────────────────────────────────────

const SYMBOL_KEYWORDS: Record<string, string[]> = {
  door:        ['door', 'gate', 'entrance', 'portal', 'threshold', 'opening'],
  water:       ['ocean', 'sea', 'river', 'lake', 'water', 'flood', 'rain'],
  tower:       ['tower', 'spire', 'building', 'skyscraper', 'pillar', 'column'],
  mirror:      ['mirror', 'reflection', 'glass', 'reflect'],
  key:         ['key', 'lock', 'unlock', 'locked'],
  staircase:   ['stair', 'stairs', 'staircase', 'steps', 'ladder'],
  clock:       ['clock', 'time', 'watch', 'hour', 'minute'],
  fire:        ['fire', 'flame', 'burning', 'smoke', 'ash', 'ember'],
  darkness:    ['dark', 'darkness', 'shadow', 'void', 'abyss', 'black'],
  light:       ['light', 'bright', 'glow', 'radiance', 'illuminate'],
  crowd:       ['crowd', 'people', 'many', 'masses', 'alone', 'surrounded'],
  bridge:      ['bridge', 'crossing', 'span', 'cross'],
  forest:      ['forest', 'trees', 'woods', 'jungle', 'tree'],
  spiral:      ['spiral', 'circle', 'loop', 'cycle', 'rotating'],
  city:        ['city', 'town', 'urban', 'streets', 'buildings', 'metropolis'],
  child:       ['child', 'children', 'baby', 'young', 'childhood'],
  vehicle:     ['car', 'train', 'plane', 'bus', 'ship', 'boat', 'vehicle'],
  weapon:      ['sword', 'gun', 'knife', 'weapon', 'blade', 'fight']
}

// ─── Narrative arc detection ──────────────────────────────────

const NARRATIVE_PATTERNS: Record<NarrativeArc, string[]> = {
  pursuit:      ['chase', 'run', 'escape', 'flee', 'hunt', 'follow', 'catch', 'caught'],
  descent:      ['fall', 'descend', 'sink', 'go down', 'deeper', 'lower', 'underground', 'down'],
  discovery:    ['find', 'found', 'discover', 'reveal', 'uncover', 'suddenly', 'realise', 'realize'],
  transformation: ['become', 'transform', 'change', 'morph', 'turn into', 'different'],
  loss:         ['lose', 'lost', 'missing', 'gone', 'disappear', 'forget', 'vanish'],
  ascent:       ['rise', 'ascend', 'fly', 'climb', 'up', 'higher', 'above', 'float up'],
  entrapment:   ['trapped', 'stuck', 'cannot', 'unable', 'locked', 'prison', 'cage', 'escape', 'impossible'],
  unknown:      []
}

// ─── Emotion intensity mapping ────────────────────────────────

const EMOTION_INTENSIFIERS: Record<string, number> = {
  extremely: 1.5, very: 1.3, incredibly: 1.4, overwhelmingly: 1.5,
  somewhat: 0.6, slightly: 0.5, a_little: 0.6, mildly: 0.5
}

// ─── Main analysis function ───────────────────────────────────

export async function analyseDream(
  content: string,
  userEmotions: string[] = []
): Promise<DreamAnalysis> {
  // Phase 2: swap this for OpenAI API call
  // if (process.env.OPENAI_API_KEY) {
  //   return await analyseWithOpenAI(content, userEmotions)
  // }

  return analyseWithNLP(content, userEmotions)
}

function analyseWithNLP(content: string, userEmotions: string[]): DreamAnalysis {
  const text = content.toLowerCase()
  const words = text.split(/\s+/)

  // Extract themes
  const themes = Object.entries(THEME_KEYWORDS)
    .filter(([, keywords]) => keywords.some(kw => text.includes(kw)))
    .map(([theme]) => theme)
    .slice(0, 8)

  // Extract symbols
  const symbols = Object.entries(SYMBOL_KEYWORDS)
    .filter(([, keywords]) => keywords.some(kw => text.includes(kw)))
    .map(([symbol]) => symbol)
    .slice(0, 10)

  // Detect narrative arc
  const arcScores = Object.entries(NARRATIVE_PATTERNS).map(([arc, patterns]) => ({
    arc: arc as NarrativeArc,
    score: patterns.filter(p => text.includes(p)).length
  }))
  const topArc = arcScores.sort((a, b) => b.score - a.score)[0]
  const narrative_arc: NarrativeArc = topArc.score > 0 ? topArc.arc : 'unknown'

  // Build emotion scores from user tags + text inference
  const emotion_score: Record<string, number> = {}
  userEmotions.forEach(e => { emotion_score[e] = 0.8 })

  // Infer additional emotions from text
  const inferredEmotions = inferEmotions(text)
  Object.entries(inferredEmotions).forEach(([emotion, score]) => {
    if (!emotion_score[emotion]) emotion_score[emotion] = score
  })

  logger.debug('NLP analysis complete', {
    themes: themes.length,
    symbols: symbols.length,
    narrative_arc,
    emotions: Object.keys(emotion_score).length
  })

  return { themes, symbols, narrative_arc, emotion_score }
}

function inferEmotions(text: string): Record<string, number> {
  const emotionKeywords: Record<string, string[]> = {
    fear:     ['afraid', 'fear', 'terrified', 'scared', 'terror', 'horror', 'dread', 'panic'],
    wonder:   ['wonder', 'amazed', 'beautiful', 'magnificent', 'awe', 'breathtaking', 'incredible'],
    sadness:  ['sad', 'cry', 'tears', 'grief', 'sorrow', 'loss', 'alone', 'lonely'],
    joy:      ['happy', 'joy', 'laugh', 'delight', 'wonderful', 'excited', 'elated', 'free'],
    confusion:['confused', 'strange', 'weird', 'wrong', 'bizarre', 'unclear', 'lost'],
    peace:    ['calm', 'peace', 'peaceful', 'serene', 'quiet', 'gentle', 'still', 'safe'],
    longing:  ['want', 'wish', 'long', 'yearning', 'miss', 'reach', 'unreachable'],
    dread:    ['dread', 'dark', 'danger', 'threat', 'unsafe', 'unease', 'foreboding']
  }

  const scores: Record<string, number> = {}
  Object.entries(emotionKeywords).forEach(([emotion, keywords]) => {
    const matches = keywords.filter(kw => text.includes(kw)).length
    if (matches > 0) scores[emotion] = Math.min(matches * 0.3, 1.0)
  })
  return scores
}

// ─── Compute match score between two dreams ───────────────────

export function computeMatchScore(dreamA: {
  themes: string[]
  symbols: string[]
  narrative_arc: string | null
  emotion_score: Record<string, number>
  emotions: string[]
  dreamed_at: string
}, dreamB: {
  themes: string[]
  symbols: string[]
  narrative_arc: string | null
  emotion_score: Record<string, number>
  emotions: string[]
  dreamed_at: string
}): {
  score: number
  theme_score: number
  emotion_score: number
  symbol_score: number
  narrative_score: number
  recency_weight: number
} {
  // Theme overlap (Jaccard similarity)
  const theme_score = jaccardSimilarity(dreamA.themes, dreamB.themes) * 100

  // Emotion overlap
  const allEmotions = [...new Set([...dreamA.emotions, ...dreamB.emotions])]
  const emotion_score = allEmotions.length > 0
    ? (allEmotions.filter(e => dreamA.emotions.includes(e) && dreamB.emotions.includes(e)).length / allEmotions.length) * 100
    : 0

  // Symbol overlap
  const symbol_score = jaccardSimilarity(dreamA.symbols, dreamB.symbols) * 100

  // Narrative arc match
  const narrative_score = dreamA.narrative_arc && dreamB.narrative_arc &&
    dreamA.narrative_arc === dreamB.narrative_arc &&
    dreamA.narrative_arc !== 'unknown' ? 100 : 0

  // Recency weight
  const recency_weight = getRecencyWeight(dreamB.dreamed_at)

  // Weighted composite score
  const raw_score =
    theme_score * 0.30 +
    emotion_score * 0.25 +
    symbol_score * 0.25 +
    narrative_score * 0.20

  const score = Math.round(raw_score * recency_weight * 100) / 100

  return { score, theme_score, emotion_score, symbol_score, narrative_score, recency_weight }
}

function jaccardSimilarity(a: string[], b: string[]): number {
  if (a.length === 0 && b.length === 0) return 0
  const setA = new Set(a)
  const setB = new Set(b)
  const intersection = new Set([...setA].filter(x => setB.has(x)))
  const union = new Set([...setA, ...setB])
  return union.size === 0 ? 0 : intersection.size / union.size
}

function getRecencyWeight(dreamedAt: string): number {
  const hours = (Date.now() - new Date(dreamedAt).getTime()) / (1000 * 60 * 60)
  if (hours <= 24) return 1.0
  if (hours <= 168) return 0.75  // 7 days
  if (hours <= 720) return 0.5   // 30 days
  return 0.25
}
