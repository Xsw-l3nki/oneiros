import bcrypt from 'bcryptjs'
import { db } from '../db/client'
import {
  RegisterRequest, LoginRequest, AuthResponse, SafeUser, User
} from '../types'
import {
  signAccessToken, signRefreshToken, verifyRefreshToken,
  hashToken, getRefreshTokenExpiry
} from '../utils/jwt'
import { logger } from '../utils/logger'

// ─── Helpers ──────────────────────────────────────────────────

function toSafeUser(user: User): SafeUser {
  return {
    id: user.id,
    email: user.email,
    display_name: user.display_name,
    region: user.region,
    region_code: user.region_code,
    is_premium: user.is_premium,
    is_moderator: user.is_moderator,
    is_admin: user.is_admin,
    created_at: user.created_at
  }
}

function calculateAge(dob: string): number {
  const today = new Date()
  const birth = new Date(dob)
  let age = today.getFullYear() - birth.getFullYear()
  const m = today.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--
  return age
}

// ─── Register ─────────────────────────────────────────────────

export async function registerUser(data: RegisterRequest): Promise<AuthResponse> {
  const { email, password, date_of_birth, research_consent, tos_accepted, region, region_code } = data

  // Validate age
  if (calculateAge(date_of_birth) < 18) {
    throw new Error('You must be 18 or older to join OneIros')
  }

  // Validate required consents
  if (!research_consent || !tos_accepted) {
    throw new Error('You must accept the Terms of Service and research consent to join')
  }

  // Check email already exists
  const { data: existing } = await db
    .from('users')
    .select('id')
    .eq('email', email.toLowerCase())
    .single()

  if (existing) {
    throw new Error('An account with this email already exists')
  }

  // Hash password
  const password_hash = await bcrypt.hash(password, 12)

  // Create user
  const { data: user, error } = await db
    .from('users')
    .insert({
      email: email.toLowerCase(),
      password_hash,
      date_of_birth,
      is_18_plus: true,
      research_consent,
      tos_accepted,
      region: region || null,
      region_code: region_code || null,
      last_active_at: new Date().toISOString()
    })
    .select()
    .single()

  if (error || !user) {
    logger.error('User creation failed', { error })
    throw new Error('Failed to create account. Please try again.')
  }

  const tokens = await generateTokenPair(user as User)
  logger.info('New user registered', { userId: user.id, region: user.region_code })

  return { user: toSafeUser(user as User), ...tokens }
}

// ─── Login ────────────────────────────────────────────────────

export async function loginUser(data: LoginRequest): Promise<AuthResponse> {
  const { email, password } = data

  const { data: user, error } = await db
    .from('users')
    .select('*')
    .eq('email', email.toLowerCase())
    .eq('is_active', true)
    .single()

  if (error || !user) {
    throw new Error('Invalid email or password')
  }

  const valid = await bcrypt.compare(password, (user as User).password_hash)
  if (!valid) {
    throw new Error('Invalid email or password')
  }

  // Update last active
  await db.from('users').update({ last_active_at: new Date().toISOString() }).eq('id', user.id)

  const tokens = await generateTokenPair(user as User)
  logger.info('User logged in', { userId: user.id })

  return { user: toSafeUser(user as User), ...tokens }
}

// ─── Refresh tokens ───────────────────────────────────────────

export async function refreshTokens(refreshToken: string): Promise<AuthResponse> {
  let payload
  try {
    payload = verifyRefreshToken(refreshToken)
  } catch {
    throw new Error('Invalid or expired refresh token')
  }

  const tokenHash = hashToken(refreshToken)

  // Verify token exists in DB (rotation check)
  const { data: stored } = await db
    .from('refresh_tokens')
    .select('*')
    .eq('token_hash', tokenHash)
    .eq('user_id', payload.userId)
    .gt('expires_at', new Date().toISOString())
    .single()

  if (!stored) {
    // Token reuse detected — invalidate all tokens for this user
    await db.from('refresh_tokens').delete().eq('user_id', payload.userId)
    throw new Error('Token reuse detected. Please log in again.')
  }

  // Delete used token (rotation)
  await db.from('refresh_tokens').delete().eq('token_hash', tokenHash)

  // Fetch fresh user data
  const { data: user } = await db
    .from('users')
    .select('*')
    .eq('id', payload.userId)
    .single()

  if (!user) throw new Error('User not found')

  const tokens = await generateTokenPair(user as User)
  return { user: toSafeUser(user as User), ...tokens }
}

// ─── Logout ───────────────────────────────────────────────────

export async function logoutUser(userId: string, refreshToken?: string): Promise<void> {
  if (refreshToken) {
    const tokenHash = hashToken(refreshToken)
    await db.from('refresh_tokens').delete().eq('token_hash', tokenHash)
  } else {
    // Logout all sessions
    await db.from('refresh_tokens').delete().eq('user_id', userId)
  }
}

// ─── Internal helpers ─────────────────────────────────────────

async function generateTokenPair(user: User): Promise<{ access_token: string; refresh_token: string }> {
  const jwtPayload = {
    userId: user.id,
    email: user.email,
    isAdmin: user.is_admin,
    isModerator: user.is_moderator
  }

  const access_token = signAccessToken(jwtPayload)
  const refresh_token = signRefreshToken(jwtPayload)

  // Store hashed refresh token
  await db.from('refresh_tokens').insert({
    user_id: user.id,
    token_hash: hashToken(refresh_token),
    expires_at: getRefreshTokenExpiry().toISOString()
  })

  return { access_token, refresh_token }
}
