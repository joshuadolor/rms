/**
 * Auth API service. Responses that include a user payload return a User instance (new User(data.user)).
 */

import User from '@/models/User'
import api, { getBaseUrl, normalizeApiError } from './api'

function withUser(data) {
  if (!data || data.user == null) return data
  return { ...data, user: data.user instanceof User ? data.user : new User(data.user) }
}

/** Social OAuth: API expects POST with access_token/id_token in body (no redirect flow). */
export const OAUTH_PROVIDERS = Object.freeze({
  google: 'google',
  facebook: 'facebook',
  instagram: 'instagram',
})

/**
 * Sign in. Backend returns 403 "Your email address is not verified." for unverified users; protected routes also return 403 for unverified.
 * @param {{ email: string, password: string }} payload
 * @returns {Promise<{ user: User, token: string, token_type: string }>}
 */
export async function login(payload) {
  const { data } = await api.post('/login', {
    email: payload.email,
    password: payload.password,
  })
  return withUser(data)
}

/**
 * Register. Backend returns user only (no token); user must verify email before logging in.
 * @param {{ name: string, email: string, password: string, password_confirmation?: string }} payload
 * @returns {Promise<{ message: string, user: User }>}
 */
export async function register(payload) {
  const { data } = await api.post('/register', {
    name: payload.name,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation ?? payload.password,
  })
  return withUser(data)
}

/**
 * Request password reset email.
 * @param {{ email: string }} payload
 * @returns {Promise<{ message: string }>}
 */
export async function forgotPassword(payload) {
  const { data } = await api.post('/forgot-password', {
    email: payload.email,
  })
  return data
}

/**
 * Resend verification email. When authenticated, send no body. When guest, send { email }.
 * @param {{ email?: string }} payload - required when not authenticated
 * @returns {Promise<{ message: string }>}
 */
export async function resendVerificationEmail(payload = {}) {
  const { data } = await api.post('/email/resend', payload.email ? { email: payload.email } : {})
  return data
}

/**
 * Verify email via signed link params (from email link). GET /email/verify/{uuid}/{hash}?expires=...&signature=...
 * @param {{ uuid: string, hash: string, expires: string, signature: string }} params (uuid may be passed as id for backward compat)
 * @returns {Promise<{ message: string, user?: User }>}
 */
export async function verifyEmail(params) {
  const { uuid, hash, expires, signature } = params
  const uid = uuid ?? params.id
  if (!uid || !hash) throw new Error('uuid and hash are required')
  const { data } = await api.get(`/email/verify/${encodeURIComponent(uid)}/${encodeURIComponent(hash)}`, {
    params: { expires, signature },
  })
  return withUser(data)
}

/**
 * Verify new email (after profile email change). Signed link sent to new address. GET /email/verify-new/{uuid}/{hash}?expires=...&signature=...
 * @param {{ uuid: string, hash: string, expires: string, signature: string }} params (uuid may be passed as id for backward compat)
 * @returns {Promise<{ message: string, user: User }>}
 */
export async function verifyNewEmail(params) {
  const { uuid, hash, expires, signature } = params
  const uid = uuid ?? params.id
  if (!uid || !hash) throw new Error('uuid and hash are required')
  const { data } = await api.get(`/email/verify-new/${encodeURIComponent(uid)}/${encodeURIComponent(hash)}`, {
    params: { expires, signature },
  })
  return withUser(data)
}

/**
 * Reset password with token from email link.
 * @param {{ token: string, email: string, password: string, password_confirmation?: string }} payload
 * @returns {Promise<{ message?: string }>}
 */
export async function resetPassword(payload) {
  const { data } = await api.post('/reset-password', {
    token: payload.token,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation ?? payload.password,
  })
  return data
}

/**
 * Get current user (protected; requires Bearer token, verified email). Backend uses GET /user. Returns 403 if email not verified.
 * @returns {Promise<{ user: User }>}
 */
export async function getMe() {
  const { data } = await api.get('/user')
  return withUser(data)
}

/**
 * Refresh access token using HttpOnly refresh cookie (no Bearer required).
 * POST /auth/refresh
 * @returns {Promise<{ user: User, token: string, token_type: string }>}
 */
export async function refresh(options = undefined) {
  // Allow callers (e.g. bootstrapAuth) to pass axios config like { timeout }.
  // Backend expects no body; send null.
  const { data } = await api.post('/auth/refresh', null, options)
  return withUser(data)
}

/**
 * Update profile. Uses the shared api client (Bearer token + Accept: application/json). Sends PATCH /user.
 * Name updates immediately; new email sends verification to new address and sets pending_email until verified.
 * @param {{ name?: string, email?: string }} payload
 * @returns {Promise<{ message: string, user: User }>}
 */
export async function updateProfile(payload) {
  const { data } = await api.patch('/user', payload)
  return withUser(data)
}

/**
 * Change password. POST /profile/password. Requires current password.
 * @param {{ current_password: string, password: string, password_confirmation?: string }} payload
 * @returns {Promise<{ message: string }>}
 */
export async function changePassword(payload) {
  const { data } = await api.post('/profile/password', {
    current_password: payload.current_password,
    password: payload.password,
    password_confirmation: payload.password_confirmation ?? payload.password,
  })
  return data
}

/**
 * Log out (revoke token). Protected route.
 * @returns {Promise<void>}
 */
export async function logout() {
  try {
    await api.post('/logout')
  } catch {
    // Clear local state regardless
  }
}

/** Social: POST provider token to backend (e.g. Google id_token). Backend returns { user, token }. */
export async function loginWithGoogle(payload) {
  const { data } = await api.post('/auth/google', payload)
  return withUser(data)
}

export async function loginWithFacebook(payload) {
  const { data } = await api.post('/auth/facebook', payload)
  return withUser(data)
}

export async function loginWithInstagram(payload) {
  const { data } = await api.post('/auth/instagram', payload)
  return withUser(data)
}

/**
 * Frontend origin for OAuth redirect_uri (where provider redirects back). Must match provider app config.
 */
export function getOAuthCallbackUrl(provider) {
  const origin = typeof window !== 'undefined' ? window.location.origin : (import.meta.env.VITE_APP_URL || '')
  return `${origin.replace(/\/$/, '')}/login/callback?provider=${provider}`
}

/** Google: use GoogleSignInButton component; it gets id_token and you call loginWithGoogle({ id_token }). */
export function redirectToGoogle() {
  /* No redirect; Google Sign-In is handled by GoogleSignInButton + loginWithGoogle */
}

/** Redirect to Facebook OAuth. User returns to /login/callback?provider=facebook#access_token=... */
export function redirectToFacebook() {
  const appId = import.meta.env.VITE_FACEBOOK_APP_ID
  if (!appId) {
    console.warn('[auth] VITE_FACEBOOK_APP_ID not set; Facebook SSO disabled')
    return
  }
  const redirectUri = getOAuthCallbackUrl('facebook')
  const url = new URL('https://www.facebook.com/v18.0/dialog/oauth')
  url.searchParams.set('client_id', appId)
  url.searchParams.set('redirect_uri', redirectUri)
  url.searchParams.set('response_type', 'token')
  url.searchParams.set('scope', 'email,public_profile')
  if (typeof window !== 'undefined') window.location.href = url.toString()
}

/** Redirect to Instagram OAuth. User returns to /login/callback?provider=instagram#access_token=... (or with code; backend only accepts access_token). */
export function redirectToInstagram() {
  const appId = import.meta.env.VITE_INSTAGRAM_APP_ID
  if (!appId) {
    console.warn('[auth] VITE_INSTAGRAM_APP_ID not set; Instagram SSO disabled')
    return
  }
  const redirectUri = getOAuthCallbackUrl('instagram')
  const url = new URL('https://api.instagram.com/oauth/authorize')
  url.searchParams.set('client_id', appId)
  url.searchParams.set('redirect_uri', redirectUri)
  url.searchParams.set('response_type', 'token')
  url.searchParams.set('scope', 'user_profile,user_username')
  if (typeof window !== 'undefined') window.location.href = url.toString()
}

export const authService = {
  login,
  register,
  forgotPassword,
  resetPassword,
  resendVerificationEmail,
  verifyEmail,
  verifyNewEmail,
  getMe,
  refresh,
  updateProfile,
  changePassword,
  logout,
  loginWithGoogle,
  loginWithFacebook,
  loginWithInstagram,
  getOAuthCallbackUrl,
  redirectToGoogle,
  redirectToFacebook,
  redirectToInstagram,
  OAUTH_PROVIDERS,
}

export default authService
