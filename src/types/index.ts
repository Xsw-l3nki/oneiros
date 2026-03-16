// ============================================================
// OneIros — Shared TypeScript Types
// ============================================================

export type Privacy = 'public' | 'private' | 'research_only'
export type ConnectionStatus = 'pending' | 'connected' | 'blocked'
export type NotificationType =
  | 'new_matches'
  | 'high_resonance'
  | 'connection_request'
  | 'connection_accepted'
  | 'new_message'
  | 'recurring_dream'
  | 'research_milestone'

export type FlagReason = 'inappropriate' | 'harmful' | 'spam' | 'personal_info' | 'other'
export type FlagStatus = 'pending' | 'reviewed' | 'actioned' | 'dismissed'
export type NarrativeArc = 'pursuit' | 'descent' | 'discovery' | 'transformation' | 'loss' | 'ascent' | 'entrapment' | 'unknown'

// ─── Database row types ───────────────────────────────────────

export interface User {
  id: string
  email: string
  password_hash: string
  display_name: string | null
  date_of_birth: string
  is_18_plus: boolean
  research_consent: boolean
  tos_accepted: boolean
  is_active: boolean
  is_moderator: boolean
  is_admin: boolean
  is_premium: boolean
  region: string | null
  region_code: string | null
  last_active_at: string | null
  created_at: string
  updated_at: string
}

export interface Dream {
  id: string
  user_id: string
  title: string | null
  content: string
  audio_url: string | null
  image_url: string | null
  ai_generated_image: boolean
  privacy: Privacy
  emotions: string[]
  themes: string[]
  symbols: string[]
  narrative_arc: NarrativeArc | null
  emotion_score: Record<string, number>
  match_count: number
  is_recurring: boolean
  recurring_group_id: string | null
  is_flagged: boolean
  flag_count: number
  is_removed: boolean
  dreamed_at: string
  created_at: string
  updated_at: string
}

export interface DreamMatch {
  id: string
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
  created_at: string
}

export interface Connection {
  id: string
  requester_id: string
  receiver_id: string
  status: ConnectionStatus
  match_id: string | null
  connected_at: string | null
  created_at: string
  updated_at: string
}

export interface Message {
  id: string
  connection_id: string
  sender_id: string
  content: string
  is_read: boolean
  is_flagged: boolean
  created_at: string
}

export interface Notification {
  id: string
  user_id: string
  type: NotificationType
  title: string
  body: string
  data: Record<string, unknown>
  is_read: boolean
  created_at: string
}

// ─── API Request/Response types ───────────────────────────────

export interface RegisterRequest {
  email: string
  password: string
  date_of_birth: string
  research_consent: boolean
  tos_accepted: boolean
  region?: string
  region_code?: string
}

export interface LoginRequest {
  email: string
  password: string
}

export interface AuthResponse {
  user: SafeUser
  access_token: string
  refresh_token: string
}

export interface SafeUser {
  id: string
  email: string
  display_name: string | null
  region: string | null
  region_code: string | null
  is_premium: boolean
  is_moderator: boolean
  is_admin: boolean
  created_at: string
}

export interface CreateDreamRequest {
  content: string
  title?: string
  emotions?: string[]
  privacy?: Privacy
  dreamed_at?: string
}

export interface UpdateDreamRequest {
  title?: string
  content?: string
  emotions?: string[]
  privacy?: Privacy
}

export interface DreamWithMatchCount extends Dream {
  user_region?: string
  user_region_code?: string
}

export interface MatchResult {
  match_id: string
  dream_id: string             // The matched dream id
  score: number
  theme_score: number
  emotion_score: number
  symbol_score: number
  narrative_score: number
  matched_dream_preview: {    // Anonymised — no user info
    id: string
    title: string | null
    content_preview: string   // First 200 chars only
    emotions: string[]
    themes: string[]
    symbols: string[]
    narrative_arc: string | null
    dreamed_at: string
  }
  region: string | null       // Country only, no city
  region_code: string | null
  connection_status: ConnectionStatus | null
  created_at: string
}

export interface GlobalDreamStats {
  total_dreams: number
  active_countries: number
  dreamers_active_now: number
  top_themes: Array<{ theme: string; count: number }>
  top_emotions: Array<{ emotion: string; count: number }>
  regional_activity: Array<{
    region_code: string
    region: string
    count: number
    top_theme: string
  }>
}

// ─── JWT payload ────────────────────────────────────────────

export interface JwtPayload {
  userId: string
  email: string
  isAdmin: boolean
  isModerator: boolean
  iat?: number
  exp?: number
}

// ─── Express augmentations ──────────────────────────────────

declare global {
  namespace Express {
    interface Request {
      user?: JwtPayload
    }
  }
}
