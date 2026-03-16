import { db } from '../db/client'
import { Connection, Message, SafeUser } from '../types'
import { createNotification } from './notification.service'
import { logger } from '../utils/logger'

// ─── Send connection request ──────────────────────────────────

export async function sendConnectionRequest(
  requesterId: string,
  receiverId: string,
  matchId?: string
): Promise<Connection> {
  if (requesterId === receiverId) throw new Error('Cannot connect with yourself')

  // Check if already connected or blocked
  const { data: existing } = await db
    .from('connections')
    .select('*')
    .or(
      `and(requester_id.eq.${requesterId},receiver_id.eq.${receiverId}),` +
      `and(requester_id.eq.${receiverId},receiver_id.eq.${requesterId})`
    )
    .single()

  if (existing) {
    if ((existing as Connection).status === 'blocked') throw new Error('Cannot connect with this user')
    if ((existing as Connection).status === 'connected') throw new Error('Already connected')
    if ((existing as Connection).status === 'pending') {
      // If the other user already sent a request, accept it
      if ((existing as Connection).requester_id === receiverId) {
        return acceptConnection(existing.id, requesterId)
      }
      throw new Error('Connection request already sent')
    }
  }

  const { data: connection, error } = await db
    .from('connections')
    .insert({
      requester_id: requesterId,
      receiver_id: receiverId,
      match_id: matchId || null,
      status: 'pending'
    })
    .select()
    .single()

  if (error || !connection) throw new Error('Failed to send connection request')

  // Notify receiver — anonymously
  const matchScore = matchId ? await getMatchScore(matchId) : null
  await createNotification(receiverId, 'connection_request', {
    title: 'A dreamer wants to connect',
    body: matchScore
      ? `A dreamer with a ${matchScore}% dream resonance has sent you a connection request.`
      : 'A matched dreamer has sent you a connection request.',
    data: { connectionId: connection.id, matchId: matchId || null }
  })

  logger.info('Connection request sent', { requesterId, receiverId })
  return connection as Connection
}

// ─── Accept connection ────────────────────────────────────────

export async function acceptConnection(
  connectionId: string,
  userId: string
): Promise<Connection> {
  const { data: connection } = await db
    .from('connections')
    .select('*')
    .eq('id', connectionId)
    .eq('receiver_id', userId)
    .eq('status', 'pending')
    .single()

  if (!connection) throw new Error('Connection request not found')

  const { data: updated, error } = await db
    .from('connections')
    .update({ status: 'connected', connected_at: new Date().toISOString() })
    .eq('id', connectionId)
    .select()
    .single()

  if (error || !updated) throw new Error('Failed to accept connection')

  // Notify requester — now reveal display names
  await createNotification((connection as Connection).requester_id, 'connection_accepted', {
    title: 'Connection accepted',
    body: 'Your connection request was accepted. You can now chat.',
    data: { connectionId }
  })

  return updated as Connection
}

// ─── Reject connection ────────────────────────────────────────

export async function rejectConnection(connectionId: string, userId: string): Promise<void> {
  const { error } = await db
    .from('connections')
    .delete()
    .eq('id', connectionId)
    .eq('receiver_id', userId)
    .eq('status', 'pending')

  if (error) throw new Error('Failed to reject connection')
}

// ─── Block user ───────────────────────────────────────────────

export async function blockUser(userId: string, targetId: string): Promise<void> {
  // Remove any existing connection
  await db.from('connections').delete().or(
    `and(requester_id.eq.${userId},receiver_id.eq.${targetId}),` +
    `and(requester_id.eq.${targetId},receiver_id.eq.${userId})`
  )

  // Create block record
  await db.from('connections').insert({
    requester_id: userId,
    receiver_id: targetId,
    status: 'blocked'
  })

  logger.info('User blocked', { userId, targetId })
}

// ─── Get connections ──────────────────────────────────────────

export async function getUserConnections(userId: string): Promise<Array<{
  connection: Connection
  partner: SafeUser
  unread_count: number
}>> {
  const { data: connections } = await db
    .from('connections')
    .select('*')
    .or(`requester_id.eq.${userId},receiver_id.eq.${userId}`)
    .eq('status', 'connected')
    .order('connected_at', { ascending: false })

  if (!connections?.length) return []

  const enriched = await Promise.all((connections as Connection[]).map(async (conn) => {
    const partnerId = conn.requester_id === userId ? conn.receiver_id : conn.requester_id

    const [{ data: partner }, { count: unread_count }] = await Promise.all([
      db.from('users').select('id,email,display_name,region,region_code,is_premium,is_moderator,is_admin,created_at').eq('id', partnerId).single(),
      db.from('messages').select('*', { count: 'exact', head: true })
        .eq('connection_id', conn.id)
        .neq('sender_id', userId)
        .eq('is_read', false)
    ])

    return {
      connection: conn,
      partner: partner as SafeUser,
      unread_count: unread_count || 0
    }
  }))

  return enriched
}

// ─── Send message ─────────────────────────────────────────────

export async function sendMessage(
  connectionId: string,
  senderId: string,
  content: string
): Promise<Message> {
  // Verify sender is part of this connection
  const { data: connection } = await db
    .from('connections')
    .select('*')
    .eq('id', connectionId)
    .eq('status', 'connected')
    .or(`requester_id.eq.${senderId},receiver_id.eq.${senderId}`)
    .single()

  if (!connection) throw new Error('Connection not found or not active')

  const { data: message, error } = await db
    .from('messages')
    .insert({ connection_id: connectionId, sender_id: senderId, content })
    .select()
    .single()

  if (error || !message) throw new Error('Failed to send message')

  // Notify recipient
  const recipientId = (connection as Connection).requester_id === senderId
    ? (connection as Connection).receiver_id
    : (connection as Connection).requester_id

  await createNotification(recipientId, 'new_message', {
    title: 'New message',
    body: 'You have a new message from a connected dreamer.',
    data: { connectionId, messageId: message.id }
  })

  return message as Message
}

// ─── Get messages ─────────────────────────────────────────────

export async function getMessages(
  connectionId: string,
  userId: string,
  page = 1,
  limit = 50
): Promise<{ messages: Message[]; total: number }> {
  const offset = (page - 1) * limit

  // Verify access
  const { data: connection } = await db
    .from('connections')
    .select('id')
    .eq('id', connectionId)
    .eq('status', 'connected')
    .or(`requester_id.eq.${userId},receiver_id.eq.${userId}`)
    .single()

  if (!connection) throw new Error('Access denied')

  const { data: messages, count } = await db
    .from('messages')
    .select('*', { count: 'exact' })
    .eq('connection_id', connectionId)
    .eq('is_flagged', false)
    .order('created_at', { ascending: false })
    .range(offset, offset + limit - 1)

  // Mark as read
  await db.from('messages')
    .update({ is_read: true })
    .eq('connection_id', connectionId)
    .neq('sender_id', userId)

  return { messages: ((messages || []) as Message[]).reverse(), total: count || 0 }
}

// ─── Helpers ──────────────────────────────────────────────────

async function getMatchScore(matchId: string): Promise<number | null> {
  const { data } = await db.from('dream_matches').select('score').eq('id', matchId).single()
  return data ? Math.round(Number(data.score)) : null
}
