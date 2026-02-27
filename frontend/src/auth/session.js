/**
 * In-memory auth session.
 *
 * - Access token is intentionally stored in memory only (never localStorage/sessionStorage).
 * - Refresh token lives in HttpOnly cookie set by the backend.
 */

let accessToken = ''
let tokenType = 'Bearer'

export function setSessionToken(nextToken, nextTokenType = 'Bearer') {
  accessToken = typeof nextToken === 'string' ? nextToken : ''
  tokenType = typeof nextTokenType === 'string' && nextTokenType ? nextTokenType : 'Bearer'
}

export function getSessionToken() {
  return accessToken
}

export function getSessionTokenType() {
  return tokenType
}

export function clearSessionToken() {
  accessToken = ''
  tokenType = 'Bearer'
}

