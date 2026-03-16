-- ============================================================
-- OneIros Database Schema
-- Run this in your Supabase SQL editor to set up the database
-- ============================================================

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";   -- For fuzzy text matching
CREATE EXTENSION IF NOT EXISTS "unaccent";   -- For accent-insensitive search

-- ─────────────────────────────────────────────
-- USERS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE users (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email           TEXT UNIQUE NOT NULL,
  password_hash   TEXT NOT NULL,
  display_name    TEXT,                          -- Only visible to mutual connections
  date_of_birth   DATE NOT NULL,
  is_18_plus      BOOLEAN NOT NULL DEFAULT false,
  research_consent BOOLEAN NOT NULL DEFAULT false,
  tos_accepted    BOOLEAN NOT NULL DEFAULT false,
  is_active       BOOLEAN NOT NULL DEFAULT true,
  is_moderator    BOOLEAN NOT NULL DEFAULT false,
  is_admin        BOOLEAN NOT NULL DEFAULT false,
  is_premium      BOOLEAN NOT NULL DEFAULT false,
  region          TEXT,                          -- Country/region, e.g. "South Africa"
  region_code     TEXT,                          -- ISO code, e.g. "ZA"
  last_active_at  TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ─────────────────────────────────────────────
-- DREAMS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE dreams (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title           TEXT,                          -- Optional user-given title
  content         TEXT NOT NULL,                 -- The dream text
  audio_url       TEXT,                          -- Supabase Storage URL for voice recording
  image_url       TEXT,                          -- Supabase Storage URL for dream image
  ai_generated_image BOOLEAN DEFAULT false,      -- Was the image AI-generated?
  
  -- Privacy
  privacy         TEXT NOT NULL DEFAULT 'public'
                  CHECK (privacy IN ('public', 'private', 'research_only')),
  
  -- Emotion tags (user-selected)
  emotions        TEXT[] DEFAULT '{}',           -- e.g. ['wonder', 'terror', 'peace']
  
  -- AI-extracted metadata (populated after processing)
  themes          TEXT[] DEFAULT '{}',           -- e.g. ['flying', 'water', 'pursuit']
  symbols         TEXT[] DEFAULT '{}',           -- e.g. ['door', 'tower', 'ocean']
  narrative_arc   TEXT,                          -- e.g. 'descent', 'pursuit', 'discovery'
  emotion_score   JSONB DEFAULT '{}',            -- e.g. {"wonder": 0.9, "fear": 0.3}
  search_vector   TSVECTOR,                      -- Full-text search index
  
  -- Matching
  match_count     INTEGER DEFAULT 0,             -- Cached match count
  
  -- Recurring dream detection
  is_recurring    BOOLEAN DEFAULT false,
  recurring_group_id UUID,                       -- Groups recurring dreams together
  
  -- Moderation
  is_flagged      BOOLEAN DEFAULT false,
  flag_count      INTEGER DEFAULT 0,
  is_removed      BOOLEAN DEFAULT false,
  
  -- Dream date (user reports when they had the dream, not when logged)
  dreamed_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Full-text search index
CREATE INDEX dreams_search_idx ON dreams USING GIN(search_vector);
CREATE INDEX dreams_user_id_idx ON dreams(user_id);
CREATE INDEX dreams_privacy_idx ON dreams(privacy);
CREATE INDEX dreams_dreamed_at_idx ON dreams(dreamed_at DESC);
CREATE INDEX dreams_themes_idx ON dreams USING GIN(themes);
CREATE INDEX dreams_emotions_idx ON dreams USING GIN(emotions);
CREATE INDEX dreams_symbols_idx ON dreams USING GIN(symbols);

-- Auto-update search vector when content changes
CREATE OR REPLACE FUNCTION update_dream_search_vector()
RETURNS TRIGGER AS $$
BEGIN
  NEW.search_vector := to_tsvector('english',
    COALESCE(NEW.title, '') || ' ' ||
    COALESCE(NEW.content, '') || ' ' ||
    COALESCE(array_to_string(NEW.themes, ' '), '') || ' ' ||
    COALESCE(array_to_string(NEW.symbols, ' '), '') || ' ' ||
    COALESCE(array_to_string(NEW.emotions, ' '), '')
  );
  NEW.updated_at := NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER dreams_search_vector_update
  BEFORE INSERT OR UPDATE ON dreams
  FOR EACH ROW EXECUTE FUNCTION update_dream_search_vector();

-- ─────────────────────────────────────────────
-- DREAM MATCHES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE dream_matches (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  dream_a_id      UUID NOT NULL REFERENCES dreams(id) ON DELETE CASCADE,
  dream_b_id      UUID NOT NULL REFERENCES dreams(id) ON DELETE CASCADE,
  user_a_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_b_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  
  -- Composite match score (0-100)
  score           NUMERIC(5,2) NOT NULL DEFAULT 0,
  
  -- Individual dimension scores
  theme_score     NUMERIC(5,2) DEFAULT 0,        -- 30% weight
  emotion_score   NUMERIC(5,2) DEFAULT 0,        -- 25% weight
  symbol_score    NUMERIC(5,2) DEFAULT 0,        -- 25% weight
  narrative_score NUMERIC(5,2) DEFAULT 0,        -- 20% weight
  
  -- Recency weight applied
  recency_weight  NUMERIC(3,2) DEFAULT 1.0,
  
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  -- Prevent duplicate pairs
  UNIQUE(dream_a_id, dream_b_id),
  CHECK(dream_a_id != dream_b_id),
  CHECK(user_a_id != user_b_id)
);

CREATE INDEX matches_dream_a_idx ON dream_matches(dream_a_id);
CREATE INDEX matches_dream_b_idx ON dream_matches(dream_b_id);
CREATE INDEX matches_score_idx ON dream_matches(score DESC);
CREATE INDEX matches_user_a_idx ON dream_matches(user_a_id);
CREATE INDEX matches_user_b_idx ON dream_matches(user_b_id);

-- ─────────────────────────────────────────────
-- CONNECTIONS TABLE (Social graph)
-- ─────────────────────────────────────────────
CREATE TABLE connections (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  requester_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  receiver_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status          TEXT NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending', 'connected', 'blocked')),
  match_id        UUID REFERENCES dream_matches(id),  -- The match that sparked the connection
  connected_at    TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  UNIQUE(requester_id, receiver_id),
  CHECK(requester_id != receiver_id)
);

CREATE INDEX connections_requester_idx ON connections(requester_id);
CREATE INDEX connections_receiver_idx ON connections(receiver_id);
CREATE INDEX connections_status_idx ON connections(status);

-- ─────────────────────────────────────────────
-- MESSAGES TABLE (Chat)
-- ─────────────────────────────────────────────
CREATE TABLE messages (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  connection_id   UUID NOT NULL REFERENCES connections(id) ON DELETE CASCADE,
  sender_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  content         TEXT NOT NULL,
  is_read         BOOLEAN DEFAULT false,
  is_flagged      BOOLEAN DEFAULT false,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX messages_connection_idx ON messages(connection_id, created_at DESC);
CREATE INDEX messages_sender_idx ON messages(sender_id);

-- ─────────────────────────────────────────────
-- NOTIFICATIONS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE notifications (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type            TEXT NOT NULL CHECK (type IN (
                    'new_matches',
                    'high_resonance',
                    'connection_request',
                    'connection_accepted',
                    'new_message',
                    'recurring_dream',
                    'research_milestone'
                  )),
  title           TEXT NOT NULL,
  body            TEXT NOT NULL,
  data            JSONB DEFAULT '{}',            -- Extra context (match_id, count, etc.)
  is_read         BOOLEAN DEFAULT false,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX notifications_user_idx ON notifications(user_id, created_at DESC);
CREATE INDEX notifications_unread_idx ON notifications(user_id) WHERE is_read = false;

-- ─────────────────────────────────────────────
-- MODERATION FLAGS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE moderation_flags (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  dream_id        UUID REFERENCES dreams(id) ON DELETE CASCADE,
  message_id      UUID REFERENCES messages(id) ON DELETE CASCADE,
  reporter_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  reason          TEXT NOT NULL CHECK (reason IN (
                    'inappropriate', 'harmful', 'spam', 'personal_info', 'other'
                  )),
  notes           TEXT,
  status          TEXT NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending', 'reviewed', 'actioned', 'dismissed')),
  reviewed_by     UUID REFERENCES users(id),
  reviewed_at     TIMESTAMPTZ,
  action_taken    TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  CHECK (dream_id IS NOT NULL OR message_id IS NOT NULL)
);

CREATE INDEX flags_status_idx ON moderation_flags(status);
CREATE INDEX flags_dream_idx ON moderation_flags(dream_id);

-- ─────────────────────────────────────────────
-- RECURRING DREAM GROUPS
-- ─────────────────────────────────────────────
CREATE TABLE recurring_dream_groups (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  label           TEXT,                          -- User-given name for this pattern
  core_themes     TEXT[] DEFAULT '{}',
  first_seen_at   TIMESTAMPTZ NOT NULL,
  last_seen_at    TIMESTAMPTZ NOT NULL,
  occurrence_count INTEGER DEFAULT 1,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX recurring_groups_user_idx ON recurring_dream_groups(user_id);

-- ─────────────────────────────────────────────
-- RESEARCH EVENTS TABLE (Admin: tag world events)
-- ─────────────────────────────────────────────
CREATE TABLE research_events (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  title           TEXT NOT NULL,
  description     TEXT,
  event_type      TEXT,                          -- e.g. 'political', 'natural_disaster', 'cultural'
  regions_affected TEXT[] DEFAULT '{}',          -- ISO country codes
  event_date      TIMESTAMPTZ NOT NULL,
  created_by      UUID NOT NULL REFERENCES users(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ─────────────────────────────────────────────
-- REFRESH TOKENS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE refresh_tokens (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash      TEXT NOT NULL UNIQUE,
  expires_at      TIMESTAMPTZ NOT NULL,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX refresh_tokens_user_idx ON refresh_tokens(user_id);

-- ─────────────────────────────────────────────
-- HELPER VIEWS
-- ─────────────────────────────────────────────

-- Public dream stats (no PII)
CREATE OR REPLACE VIEW public_dream_stats AS
SELECT
  DATE_TRUNC('day', dreamed_at) AS dream_date,
  region_code,
  UNNEST(themes) AS theme,
  COUNT(*) AS count
FROM dreams d
JOIN users u ON d.user_id = u.id
WHERE d.privacy IN ('public', 'research_only')
  AND d.is_removed = false
GROUP BY 1, 2, 3;

-- Global emotion pulse (last 24h)
CREATE OR REPLACE VIEW emotion_pulse_24h AS
SELECT
  UNNEST(emotions) AS emotion,
  u.region_code,
  COUNT(*) AS count
FROM dreams d
JOIN users u ON d.user_id = u.id
WHERE d.dreamed_at >= NOW() - INTERVAL '24 hours'
  AND d.privacy IN ('public', 'research_only')
  AND d.is_removed = false
GROUP BY 1, 2;

-- ─────────────────────────────────────────────
-- ROW LEVEL SECURITY (Supabase RLS)
-- ─────────────────────────────────────────────
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE dreams ENABLE ROW LEVEL SECURITY;
ALTER TABLE connections ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

-- Users can only see/edit their own profile
CREATE POLICY "Users can view own profile"
  ON users FOR SELECT USING (auth.uid()::text = id::text);

CREATE POLICY "Users can update own profile"
  ON users FOR UPDATE USING (auth.uid()::text = id::text);

-- Dreams: owners see all, public can see public dreams
CREATE POLICY "Users can manage own dreams"
  ON dreams FOR ALL USING (auth.uid()::text = user_id::text);

CREATE POLICY "Public dreams visible to all authenticated users"
  ON dreams FOR SELECT
  USING (privacy = 'public' AND is_removed = false AND auth.role() = 'authenticated');

-- Messages: only participants can see
CREATE POLICY "Connection participants can view messages"
  ON messages FOR SELECT
  USING (
    EXISTS (
      SELECT 1 FROM connections c
      WHERE c.id = messages.connection_id
        AND (c.requester_id::text = auth.uid()::text
          OR c.receiver_id::text = auth.uid()::text)
        AND c.status = 'connected'
    )
  );

-- Notifications: users see only their own
CREATE POLICY "Users see own notifications"
  ON notifications FOR ALL USING (auth.uid()::text = user_id::text);
