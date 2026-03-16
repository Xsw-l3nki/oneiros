# OneIros Backend API

> Global Dream Intelligence Platform — REST API

## Stack
- **Runtime**: Node.js 20 + TypeScript
- **Framework**: Express.js
- **Database**: PostgreSQL via Supabase
- **Auth**: JWT (access + refresh tokens)
- **Hosting**: Railway (recommended)

---

## 🚀 Quick Start (Local Development)

### 1. Prerequisites
- Node.js 20+
- A free [Supabase](https://supabase.com) account

### 2. Set up Supabase
1. Create a new project at supabase.com
2. Go to **SQL Editor** and run the contents of:
   `supabase/migrations/001_initial_schema.sql`
3. Go to **Settings → API** and copy your keys

### 3. Configure environment
```bash
cp .env.example .env
# Fill in your values in .env
```

### 4. Install & run
```bash
npm install
npm run dev
```

API will be running at `http://localhost:3001`

---

## 📡 API Reference

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Create account (18+ enforced) |
| POST | `/api/auth/login` | Login, receive JWT tokens |
| POST | `/api/auth/refresh` | Refresh access token |
| POST | `/api/auth/logout` | Invalidate refresh token |
| GET  | `/api/auth/me` | Get current user |

### Dreams
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/dreams` | Log a new dream |
| GET | `/api/dreams` | Get your dreams (paginated) |
| GET | `/api/dreams/:id` | Get a specific dream |
| PATCH | `/api/dreams/:id` | Update a dream |
| PATCH | `/api/dreams/:id/privacy` | Change privacy setting |
| DELETE | `/api/dreams/:id` | Delete a dream |
| GET | `/api/dreams/:id/matches` | Get matches for a dream |
| POST | `/api/dreams/:id/flag` | Report inappropriate content |

### Matches
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/matches` | All matches across all your dreams |

### Connections & Chat
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/connections` | Your connections |
| POST | `/api/connections/request` | Send connection request |
| PATCH | `/api/connections/:id/accept` | Accept request |
| DELETE | `/api/connections/:id/reject` | Reject request |
| POST | `/api/connections/block` | Block a user |
| GET | `/api/connections/:id/messages` | Get chat messages |
| POST | `/api/connections/:id/messages` | Send a message |

### Notifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/notifications` | Your notifications |
| PATCH | `/api/notifications/read` | Mark as read |

### Research
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/research/global` | Global dream stats & map data |
| GET | `/api/research/me` | Your personal dream statistics |

---

## 🧠 How the Matching Engine Works

**Phase 1 (current):** NLP keyword extraction
1. Dream text → extract themes, symbols, narrative arc
2. Compare two dreams across 4 dimensions
3. Weighted composite score: Theme 30% + Emotion 25% + Symbol 25% + Narrative 20%
4. Recency decay weighting applied
5. Matches above 30% threshold stored

**Phase 2 (with OpenAI keys):** Upgrade path ready
- Add `OPENAI_API_KEY` to `.env`
- `analysis.service.ts` has the hook at line 60
- Semantic vector embeddings replace keyword matching
- Whisper API handles voice transcription
- DALL-E 3 handles AI dream image generation

---

## 🚢 Deploy to Railway

1. Push to GitHub
2. Go to [railway.app](https://railway.app) → New Project → Deploy from GitHub
3. Add environment variables from `.env.example`
4. Railway auto-detects Node.js and deploys

---

## 🔒 Security Features
- JWT with 15min access token + 7 day refresh token rotation
- Refresh token reuse detection (invalidates all sessions on reuse)
- bcrypt password hashing (cost factor 12)
- Rate limiting: 100 req/15min global, 10 req/15min on auth endpoints
- Helmet.js security headers
- Input validation on all endpoints
- Row Level Security (RLS) in Supabase
- PII separated from research data layer

---

## 📁 Project Structure
```
src/
├── index.ts              # App entry point
├── db/
│   └── client.ts         # Supabase client
├── middleware/
│   └── auth.ts           # JWT authentication middleware
├── routes/
│   ├── auth.routes.ts    # /api/auth/*
│   ├── dream.routes.ts   # /api/dreams/*
│   └── social.routes.ts  # /api/connections, matches, notifications, research
├── services/
│   ├── auth.service.ts   # Registration, login, token management
│   ├── dream.service.ts  # Dream CRUD
│   ├── analysis.service.ts  # NLP analysis + match scoring
│   ├── matching.service.ts  # Matching engine
│   ├── connection.service.ts # Social graph + chat
│   ├── notification.service.ts
│   └── research.service.ts  # Global stats
├── types/
│   └── index.ts          # All TypeScript types
└── utils/
    ├── jwt.ts            # Token utilities
    └── logger.ts         # Winston logger
```
