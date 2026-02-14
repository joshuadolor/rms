/**
 * Auth API service. Components and stores use these methods to perform auth-related API calls.
 * Responses should be passed to models (e.g. User.fromApi(data)) in the caller.
 */

import api, { getBaseUrl, normalizeApiError } from './api'

/** Social OAuth: API expects POST with access_token/id_token in body (no redirect flow). */
export const OAUTH_PROVIDERS = Object.freeze({
  google: 'google',
  facebook: 'facebook',
  instagram: 'instagram',
})

/**
 * Sign in. Backend returns 403 "Your email address is not verified." for unverified users; protected routes also return 403 for unverified.
 * @param {{ email: string, password: string }} payload
 * @returns {Promise<{ user: object, token: string, token_type: string }>}
 */
export async function login(payload) {
  const { data } = await api.post('/login', {
    email: payload.email,
    password: payload.password,
  })
  return data
}

/**
 * Register. Backend returns user only (no token); user must verify email before logging in.
 * @param {{ name: string, email: string, password: string, password_confirmation?: string }} payload
 * @returns {Promise<{ message: string, user: object }>}
 */
export async function register(payload) {
  const { data } = await api.post('/register', {
    name: payload.name,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation ?? payload.password,
  })
  return data
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
 * Verify email via signed link params (from email link). GET /email/verify/{id}/{hash}?expires=...&signature=...
 * @param {{ id: string|number, hash: string, expires: string, signature: string }} params
 * @returns {Promise<{ message: string, user?: object }>}
 */
export async function verifyEmail(params) {
  const { id, hash, expires, signature } = params
  const { data } = await api.get(`/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`, {
    params: { expires, signature },
  })
  return data
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
 * @returns {Promise<{ user: object }>} use User.fromApi(data.user) in store.
 */
export async function getMe() {
  const { data } = await api.get('/user')
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
  return data
}

export async function loginWithFacebook(payload) {
  const { data } = await api.post('/auth/facebook', payload)
  return data
}

export async function loginWithInstagram(payload) {
  const { data } = await api.post('/auth/instagram', payload)
  return data
}

/** No-op: API expects POST with token; use loginWithGoogle({ id_token }) when you have a provider token. */
export function redirectToGoogle() {}
export function redirectToFacebook() {}
export function redirectToInstagram() {}

export const authService = {
  login,
  register,
  forgotPassword,
  resetPassword,
  resendVerificationEmail,
  verifyEmail,
  getMe,
  logout,
  loginWithGoogle,
  loginWithFacebook,
  loginWithInstagram,
  redirectToGoogle,
  redirectToFacebook,
  redirectToInstagram,
  OAUTH_PROVIDERS,
}

export default authService
