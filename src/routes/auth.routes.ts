import { Router, Request, Response } from 'express'
import { body, validationResult } from 'express-validator'
import { authenticate } from '../middleware/auth'
import { registerUser, loginUser, refreshTokens, logoutUser } from '../services/auth.service'
import { logger } from '../utils/logger'

const router = Router()

// ─── POST /auth/register ──────────────────────────────────────

router.post('/register', [
  body('email').isEmail().normalizeEmail(),
  body('password').isLength({ min: 8 }).withMessage('Password must be at least 8 characters'),
  body('date_of_birth').isISO8601().withMessage('Invalid date of birth'),
  body('research_consent').isBoolean().equals('true').withMessage('Research consent is required'),
  body('tos_accepted').isBoolean().equals('true').withMessage('Terms of service must be accepted')
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) {
    res.status(400).json({ errors: errors.array() })
    return
  }

  try {
    const result = await registerUser(req.body)
    res.status(201).json(result)
  } catch (err: any) {
    logger.warn('Registration failed', { error: err.message, email: req.body.email })
    res.status(400).json({ error: err.message })
  }
})

// ─── POST /auth/login ─────────────────────────────────────────

router.post('/login', [
  body('email').isEmail().normalizeEmail(),
  body('password').notEmpty()
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) {
    res.status(400).json({ errors: errors.array() })
    return
  }

  try {
    const result = await loginUser(req.body)
    res.json(result)
  } catch (err: any) {
    // Don't reveal which field was wrong
    res.status(401).json({ error: 'Invalid email or password' })
  }
})

// ─── POST /auth/refresh ───────────────────────────────────────

router.post('/refresh', async (req: Request, res: Response) => {
  const { refresh_token } = req.body
  if (!refresh_token) {
    res.status(400).json({ error: 'Refresh token required' })
    return
  }

  try {
    const result = await refreshTokens(refresh_token)
    res.json(result)
  } catch (err: any) {
    res.status(401).json({ error: err.message })
  }
})

// ─── POST /auth/logout ────────────────────────────────────────

router.post('/logout', authenticate, async (req: Request, res: Response) => {
  try {
    const { refresh_token } = req.body
    await logoutUser(req.user!.userId, refresh_token)
    res.json({ message: 'Logged out successfully' })
  } catch (err: any) {
    res.status(500).json({ error: 'Logout failed' })
  }
})

// ─── GET /auth/me ─────────────────────────────────────────────

router.get('/me', authenticate, async (req: Request, res: Response) => {
  res.json({ user: req.user })
})

export default router
