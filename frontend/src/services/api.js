/**
 * Base API client. All HTTP calls go through this instance.
 * - Base URL from env (VITE_API_URL) — must include /api to match Laravel's api routes (e.g. http://localhost:8000/api)
 * - Attaches auth token when present
 * - Normalizes errors for callers
 *
 * Laravel route:list shows POST api/login, GET api/user, etc. So full URL = baseURL + path, e.g. baseURL + '/login' → .../api/login
 */

import axios from 'axios'
import { useAppStore } from '@/stores/app'

const rawBase = import.meta.env.VITE_API_URL ?? ''
// Ensure no trailing slash so paths like '/login' become base/login
// When empty in dev: use relative '/api' so Vite proxy can forward to the backend (same-origin, no CORS).
let baseURL = rawBase.replace(/\/$/, '')
if (!baseURL && typeof window !== 'undefined') {
  baseURL = '/api'
}

if (import.meta.env.DEV && typeof window !== 'undefined') {
  if (rawBase && !baseURL.endsWith('/api')) {
    const suggested = baseURL ? `${baseURL}/api` : `${window.location.origin}/api`
    console.warn(
      `[api.js] VITE_API_URL should end with /api so login hits .../api/login. Current: "${baseURL}". Try: "${suggested}"`
    )
  }
}

/** Base URL for the API (e.g. for OAuth redirects). */
export function getBaseUrl() {
  return baseURL
}

export const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
})

/** Get stored auth token (e.g. Bearer). Adjust key/format to match your auth. */
function getAuthToken() {
  return localStorage.getItem('rms-auth-token') ?? ''
}

api.interceptors.request.use((config) => {
  const token = getAuthToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

const UNVERIFIED_MESSAGE = 'Your email address is not verified.'

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const message = error.response?.data?.message ?? ''

    if (status === 401) {
      const appStore = useAppStore()
      appStore.clearAuthState()
      if (typeof window !== 'undefined') {
        window.location.assign(`${window.location.origin}/login`)
      }
    } else if (status === 403 && typeof message === 'string' && message.includes(UNVERIFIED_MESSAGE)) {
      const appStore = useAppStore()
      appStore.clearAuthState()
      const params = new URLSearchParams({ message: 'Please verify your email to continue.' })
      if (typeof window !== 'undefined') {
        window.location.assign(`${window.location.origin}/verify-email?${params.toString()}`)
      }
    }

    const normalized = normalizeApiError(error)
    reportApiError(normalized, { context: 'response interceptor' })
    return Promise.reject(error)
  }
)

/**
 * Error groups for logging/analytics. Use these when reporting or filtering by type.
 */
export const API_ERROR_GROUPS = Object.freeze({
  UNAUTHORIZED: 'unauthorized',
  FORBIDDEN: 'forbidden',
  NOT_FOUND: 'not_found',
  VALIDATION: 'validation',
  CLIENT_ERROR: 'client_error',
  SERVER_ERROR: 'server_error',
  NETWORK: 'network',
  UNKNOWN: 'unknown',
})

/**
 * Map HTTP status to error group.
 * @param {number|undefined} status
 * @returns {string}
 */
export function getErrorGroup(status) {
  if (status == null) return API_ERROR_GROUPS.UNKNOWN
  if (status === 401) return API_ERROR_GROUPS.UNAUTHORIZED
  if (status === 403) return API_ERROR_GROUPS.FORBIDDEN
  if (status === 404) return API_ERROR_GROUPS.NOT_FOUND
  if (status === 422) return API_ERROR_GROUPS.VALIDATION
  if (status >= 400 && status < 500) return API_ERROR_GROUPS.CLIENT_ERROR
  if (status >= 500) return API_ERROR_GROUPS.SERVER_ERROR
  return API_ERROR_GROUPS.UNKNOWN
}

/** User-friendly fallback messages when the API returns no message body. */
const STATUS_MESSAGE_FALLBACKS = {
  400: 'Invalid request.',
  401: 'Please sign in again.',
  403: "You don't have permission to do this.",
  404: 'Not found.',
  422: 'Please check your input.',
  500: 'Something went wrong. Please try again later.',
}

/**
 * Get first validation error message from Laravel 422 response (errors.email[0], etc.)
 */
function firstValidationError(data) {
  const errors = data?.errors
  if (errors && typeof errors === 'object') {
    const firstKey = Object.keys(errors)[0]
    const firstList = firstKey ? errors[firstKey] : null
    if (Array.isArray(firstList) && firstList[0]) return firstList[0]
  }
  return null
}

/**
 * Get per-field validation errors from Laravel 422 response. Use for showing errors under each form field.
 * @param {unknown} err - Axios error (err.response?.data?.errors)
 * @returns {{ [field: string]: string }} e.g. { email: 'The email has already been taken.', password: '...' }
 */
export function getValidationErrors(err) {
  const data = err?.response?.data
  const errors = data?.errors
  if (!errors || typeof errors !== 'object') return {}
  const out = {}
  for (const [key, list] of Object.entries(errors)) {
    if (Array.isArray(list) && list[0] && typeof list[0] === 'string') out[key] = list[0]
  }
  return out
}

/**
 * Normalized API error shape for UI, logging, and analytics.
 * @typedef {{ message: string, status?: number, code?: string, group: string }} NormalizedApiError
 */

/**
 * Normalize API error for services. Returns a grouped shape for consistent handling and future logging/analytics.
 * @param {unknown} err
 * @returns {NormalizedApiError}
 */
export function normalizeApiError(err) {
  if (axios.isAxiosError(err)) {
    const status = err.response?.status
    const data = err.response?.data
    const validationMsg = firstValidationError(data)
    const statusFallback = status != null ? STATUS_MESSAGE_FALLBACKS[status] : null
    const msg =
      validationMsg ??
      data?.message ??
      data?.error ??
      statusFallback ??
      err.message ??
      'Request failed'
    const group = err.code === 'ERR_NETWORK' ? API_ERROR_GROUPS.NETWORK : getErrorGroup(status)
    return {
      message: typeof msg === 'string' ? msg : JSON.stringify(msg),
      status,
      code: err.code,
      group,
    }
  }
  return {
    message: err instanceof Error ? err.message : 'Request failed',
    group: API_ERROR_GROUPS.UNKNOWN,
  }
}

/**
 * Optional: report a normalized API error for logging or analytics.
 * Replace or extend this implementation (e.g. send to Sentry, analytics).
 * @param {NormalizedApiError} normalized
 * @param {{ context?: string }} options
 */
export function reportApiError(normalized, options = {}) {
  if (import.meta.env.DEV) {
    console.warn('[API Error]', normalized.group, normalized.message, options.context ?? '')
  }
  // Later: logger.error(normalized.group, normalized, options)
  // Later: analytics.track('api_error', { group: normalized.group, status: normalized.status, ... })
}

export default api
