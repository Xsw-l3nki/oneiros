import { db } from '../db/client'
import { NotificationType, Notification } from '../types'
import { logger } from '../utils/logger'

interface CreateNotificationInput {
  title: string
  body: string
  data?: Record<string, unknown>
}

export async function createNotification(
  userId: string,
  type: NotificationType,
  input: CreateNotificationInput
): Promise<void> {
  const { error } = await db.from('notifications').insert({
    user_id: userId,
    type,
    title: input.title,
    body: input.body,
    data: input.data || {}
  })

  if (error) {
    logger.error('Failed to create notification', { error, userId, type })
  }
}

export async function getUserNotifications(
  userId: string,
  page = 1,
  limit = 20
): Promise<{ notifications: Notification[]; unread_count: number }> {
  const offset = (page - 1) * limit

  const [{ data: notifications }, { count: unread_count }] = await Promise.all([
    db.from('notifications')
      .select('*')
      .eq('user_id', userId)
      .order('created_at', { ascending: false })
      .range(offset, offset + limit - 1),
    db.from('notifications')
      .select('*', { count: 'exact', head: true })
      .eq('user_id', userId)
      .eq('is_read', false)
  ])

  return {
    notifications: (notifications || []) as Notification[],
    unread_count: unread_count || 0
  }
}

export async function markNotificationsRead(userId: string, ids?: string[]): Promise<void> {
  let query = db.from('notifications').update({ is_read: true }).eq('user_id', userId)
  if (ids?.length) query = query.in('id', ids)
  await query
}
