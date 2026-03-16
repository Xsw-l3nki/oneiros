import { Request, Response, NextFunction } from 'express'
import { verifyAccessToken } from '../utils/jwt'
import { logger } from '../utils/logger'

export function authenticate(req: Request, res: Response, next: NextFunction): void {
  const authHeader = req.headers.authorization

  if (!authHeader?.startsWith('Bearer ')) {
    res.status(401).json({ error: 'No token provided' })
    return
  }

  const token = authHeader.split(' ')[1]

  try {
    const payload = verifyAccessToken(token)
    req.user = payload
    next()
  } catch (err) {
    logger.debug('Token verification failed', { error: err })
    res.status(401).json({ error: 'Invalid or expired token' })
  }
}

export function requireAdmin(req: Request, res: Response, next: NextFunction): void {
  if (!req.user?.isAdmin) {
    res.status(403).json({ error: 'Admin access required' })
    return
  }
  next()
}

export function requireModerator(req: Request, res: Response, next: NextFunction): void {
  if (!req.user?.isModerator && !req.user?.isAdmin) {
    res.status(403).json({ error: 'Moderator access required' })
    return
  }
  next()
}

export function requirePremium(req: Request, res: Response, next: NextFunction): void {
  // Premium check is done at service level using DB lookup
  // This middleware just ensures user is authenticated first
  authenticate(req, res, next)
}
