import { Router, Request, Response } from 'express'
import { body, param, query, validationResult } from 'express-validator'
import { authenticate } from '../middleware/auth'
import {
  createDream, getUserDreams, getDreamById,
  updateDream, deleteDream, updateDreamPrivacy,
  flagDream
} from '../services/dream.service'
import { getDreamMatches } from '../services/matching.service'

const router = Router()
router.use(authenticate)

// ─── POST /dreams ─────────────────────────────────────────────

router.post('/', [
  body('content').isLength({ min: 10, max: 10000 }).withMessage('Dream must be between 10 and 10,000 characters'),
  body('title').optional().isLength({ max: 200 }),
  body('emotions').optional().isArray(),
  body('privacy').optional().isIn(['public', 'private', 'research_only']),
  body('dreamed_at').optional().isISO8601()
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) { res.status(400).json({ errors: errors.array() }); return }

  try {
    const dream = await createDream(req.user!.userId, req.body)
    res.status(201).json(dream)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ─── GET /dreams ──────────────────────────────────────────────

router.get('/', [
  query('page').optional().isInt({ min: 1 }),
  query('limit').optional().isInt({ min: 1, max: 50 })
], async (req: Request, res: Response) => {
  const page = parseInt(req.query.page as string) || 1
  const limit = parseInt(req.query.limit as string) || 20

  try {
    const result = await getUserDreams(req.user!.userId, page, limit)
    res.json(result)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// ─── GET /dreams/:id ──────────────────────────────────────────

router.get('/:id', async (req: Request, res: Response) => {
  try {
    const dream = await getDreamById(req.params.id, req.user!.userId)
    res.json(dream)
  } catch (err: any) {
    res.status(404).json({ error: err.message })
  }
})

// ─── PATCH /dreams/:id ────────────────────────────────────────

router.patch('/:id', [
  body('content').optional().isLength({ min: 10, max: 10000 }),
  body('title').optional().isLength({ max: 200 }),
  body('emotions').optional().isArray(),
  body('privacy').optional().isIn(['public', 'private', 'research_only'])
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) { res.status(400).json({ errors: errors.array() }); return }

  try {
    const dream = await updateDream(req.params.id, req.user!.userId, req.body)
    res.json(dream)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ─── PATCH /dreams/:id/privacy ────────────────────────────────

router.patch('/:id/privacy', [
  body('privacy').isIn(['public', 'private', 'research_only'])
], async (req: Request, res: Response) => {
  try {
    const dream = await updateDreamPrivacy(req.params.id, req.user!.userId, req.body.privacy)
    res.json(dream)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ─── DELETE /dreams/:id ───────────────────────────────────────

router.delete('/:id', async (req: Request, res: Response) => {
  try {
    await deleteDream(req.params.id, req.user!.userId)
    res.json({ message: 'Dream deleted' })
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ─── GET /dreams/:id/matches ──────────────────────────────────

router.get('/:id/matches', [
  query('page').optional().isInt({ min: 1 }),
  query('limit').optional().isInt({ min: 1, max: 50 }),
  query('filter').optional().isIn(['theme', 'emotion', 'visual', 'narrative'])
], async (req: Request, res: Response) => {
  try {
    const result = await getDreamMatches(
      req.params.id,
      req.user!.userId,
      parseInt(req.query.page as string) || 1,
      parseInt(req.query.limit as string) || 20,
      req.query.filter as any
    )
    res.json(result)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ─── POST /dreams/:id/flag ────────────────────────────────────

router.post('/:id/flag', [
  body('reason').isIn(['inappropriate', 'harmful', 'spam', 'personal_info', 'other']),
  body('notes').optional().isLength({ max: 500 })
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) { res.status(400).json({ errors: errors.array() }); return }

  try {
    await flagDream(req.params.id, req.user!.userId, req.body.reason, req.body.notes)
    res.json({ message: 'Dream reported. Our moderators will review it.' })
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

export default router
