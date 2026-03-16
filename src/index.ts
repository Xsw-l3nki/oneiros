import express from 'express'
import cors from 'cors'
import helmet from 'helmet'
import compression from 'compression'
import morgan from 'morgan'
import rateLimit from 'express-rate-limit'
import dotenv from 'dotenv'

import { logger } from './utils/logger'
import authRoutes from './routes/auth.routes'
import dreamRoutes from './routes/dream.routes'
import {
  connectionRouter,
  notificationRouter,
  matchRouter,
  researchRouter
} from './routes/social.routes'

dotenv.config()

const app = express()
const PORT = process.env.PORT || 3001

// ─── Security middleware ──────────────────────────────────────

app.use(helmet({
  crossOriginResourcePolicy: { policy: 'cross-origin' }
}))

app.use(cors({
  origin: process.env.FRONTEND_URL || 'http://localhost:3000',
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}))

// ─── Rate limiting ────────────────────────────────────────────

const globalLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '900000'),  // 15 min
  max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS || '100'),
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Too many requests, please try again later.' }
})

const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,  // 15 minutes
  max: 10,                    // 10 login attempts per window
  message: { error: 'Too many authentication attempts. Please wait 15 minutes.' }
})

app.use(globalLimiter)

// ─── General middleware ───────────────────────────────────────

app.use(compression())
app.use(express.json({ limit: '5mb' }))
app.use(express.urlencoded({ extended: true }))

if (process.env.NODE_ENV !== 'test') {
  app.use(morgan(process.env.NODE_ENV === 'production' ? 'combined' : 'dev'))
}

// ─── Health check ─────────────────────────────────────────────

app.get('/health', (_req, res) => {
  res.json({
    status: 'healthy',
    service: 'OneIros API',
    version: '1.0.0',
    timestamp: new Date().toISOString()
  })
})

// ─── API Routes ───────────────────────────────────────────────

app.use('/api/auth', authLimiter, authRoutes)
app.use('/api/dreams', dreamRoutes)
app.use('/api/connections', connectionRouter)
app.use('/api/notifications', notificationRouter)
app.use('/api/matches', matchRouter)
app.use('/api/research', researchRouter)

// ─── 404 handler ─────────────────────────────────────────────

app.use((_req, res) => {
  res.status(404).json({ error: 'Route not found' })
})

// ─── Global error handler ─────────────────────────────────────

app.use((err: Error, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  logger.error('Unhandled error', { error: err.message, stack: err.stack })
  res.status(500).json({
    error: process.env.NODE_ENV === 'production'
      ? 'An unexpected error occurred'
      : err.message
  })
})

// ─── Start server ─────────────────────────────────────────────

app.listen(PORT, () => {
  logger.info(`🌙 OneIros API running on port ${PORT}`)
  logger.info(`   Environment: ${process.env.NODE_ENV || 'development'}`)
  logger.info(`   Health check: http://localhost:${PORT}/health`)
})

export default app
