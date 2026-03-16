import { Router, Request, Response } from 'express'
import { body, query, validationResult } from 'express-validator'
import { authenticate } from '../middleware/auth'
import {
  sendConnectionRequest, acceptConnection, rejectConnection,
  blockUser, getUserConnections, sendMessage, getMessages
} from '../services/connection.service'
import { getUserNotifications, markNotificationsRead } from '../services/notification.service'
import { getGlobalStats, getUserStats } from '../services/research.service'
import { getUserMatches } from '../services/matching.service'

// ══════════════════════════════════════════
// CONNECTIONS ROUTER
// ══════════════════════════════════════════

export const connectionRouter = Router()
connectionRouter.use(authenticate)

// GET /connections — list all connections
connectionRouter.get('/', async (req: Request, res: Response) => {
  try {
    const connections = await getUserConnections(req.user!.userId)
    res.json(connections)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// POST /connections/request — send connection request
connectionRouter.post('/request', [
  body('receiver_id').isUUID(),
  body('match_id').optional().isUUID()
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) { res.status(400).json({ errors: errors.array() }); return }

  try {
    const connection = await sendConnectionRequest(
      req.user!.userId,
      req.body.receiver_id,
      req.body.match_id
    )
    res.status(201).json(connection)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// PATCH /connections/:id/accept
connectionRouter.patch('/:id/accept', async (req: Request, res: Response) => {
  try {
    const connection = await acceptConnection(req.params.id, req.user!.userId)
    res.json(connection)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// DELETE /connections/:id/reject
connectionRouter.delete('/:id/reject', async (req: Request, res: Response) => {
  try {
    await rejectConnection(req.params.id, req.user!.userId)
    res.json({ message: 'Connection request declined' })
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// POST /connections/block
connectionRouter.post('/block', [
  body('target_id').isUUID()
], async (req: Request, res: Response) => {
  try {
    await blockUser(req.user!.userId, req.body.target_id)
    res.json({ message: 'User blocked' })
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// GET /connections/:id/messages
connectionRouter.get('/:id/messages', async (req: Request, res: Response) => {
  const page = parseInt(req.query.page as string) || 1
  try {
    const result = await getMessages(req.params.id, req.user!.userId, page)
    res.json(result)
  } catch (err: any) {
    res.status(403).json({ error: err.message })
  }
})

// POST /connections/:id/messages
connectionRouter.post('/:id/messages', [
  body('content').isLength({ min: 1, max: 2000 })
], async (req: Request, res: Response) => {
  const errors = validationResult(req)
  if (!errors.isEmpty()) { res.status(400).json({ errors: errors.array() }); return }

  try {
    const message = await sendMessage(req.params.id, req.user!.userId, req.body.content)
    res.status(201).json(message)
  } catch (err: any) {
    res.status(400).json({ error: err.message })
  }
})

// ══════════════════════════════════════════
// NOTIFICATIONS ROUTER
// ══════════════════════════════════════════

export const notificationRouter = Router()
notificationRouter.use(authenticate)

// GET /notifications
notificationRouter.get('/', async (req: Request, res: Response) => {
  const page = parseInt(req.query.page as string) || 1
  try {
    const result = await getUserNotifications(req.user!.userId, page)
    res.json(result)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// PATCH /notifications/read — mark as read
notificationRouter.patch('/read', async (req: Request, res: Response) => {
  try {
    const { ids } = req.body  // Optional array of specific IDs, or mark all
    await markNotificationsRead(req.user!.userId, ids)
    res.json({ message: 'Notifications marked as read' })
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// ══════════════════════════════════════════
// MATCHES ROUTER
// ══════════════════════════════════════════

export const matchRouter = Router()
matchRouter.use(authenticate)

// GET /matches — all matches across all user's dreams
matchRouter.get('/', async (req: Request, res: Response) => {
  const page = parseInt(req.query.page as string) || 1
  const limit = parseInt(req.query.limit as string) || 20
  try {
    const result = await getUserMatches(req.user!.userId, page, limit)
    res.json(result)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// ══════════════════════════════════════════
// RESEARCH ROUTER
// ══════════════════════════════════════════

export const researchRouter = Router()
researchRouter.use(authenticate)

// GET /research/global — global dream stats (logged-in users only)
researchRouter.get('/global', async (_req: Request, res: Response) => {
  try {
    const stats = await getGlobalStats()
    res.json(stats)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})

// GET /research/me — personal stats
researchRouter.get('/me', async (req: Request, res: Response) => {
  try {
    const stats = await getUserStats(req.user!.userId)
    res.json(stats)
  } catch (err: any) {
    res.status(500).json({ error: err.message })
  }
})
