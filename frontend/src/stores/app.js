import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import User from '@/models/User'
import { authService, normalizeApiError, API_ERROR_GROUPS } from '@/services'
import { clearSessionToken, setSessionToken } from '@/auth/session'
import { useToastStore } from '@/stores/toast'

export const useAppStore = defineStore('app', () => {
  const user = ref(null)
  const authBootstrapStatus = ref('idle') // idle | loading | done
  const authBootstrapHadNetworkError = ref(false)
  let authBootstrapPromise = null

  function clearLegacyAuthStorage() {
    try {
      localStorage.removeItem('rms-auth')
      localStorage.removeItem('rms-auth-token')
      localStorage.removeItem('rms-user-id')
      localStorage.removeItem('rms-user-name')
      localStorage.removeItem('rms-user-email')
      localStorage.removeItem('rms-user-verified')
      localStorage.removeItem('rms-user-pending-email')
      localStorage.removeItem('rms-user-is-paid')
      localStorage.removeItem('rms-user-is-superadmin')
      localStorage.removeItem('rms-user-is-active')
    } catch {
      // ignore (e.g. SSR / privacy mode)
    }
  }

  const isAuthenticated = computed(() => !!user.value)
  const isAuthBootstrapping = computed(() => authBootstrapStatus.value === 'loading')
  const isAuthBootstrapped = computed(() => authBootstrapStatus.value === 'done')

  /**
   * Set user from a plain payload (e.g. after login/register before token is stored).
   * Prefer setUserFromApi(data) when you have an API user response; this is kept for legacy/custom flows.
   */
  function login(payload = {}) {
    const data = {
      uuid: payload.uuid ?? payload.id ?? user.value?.id,
      name: payload.name ?? user.value?.name ?? 'Restaurant Owner',
      email: payload.email ?? user.value?.email ?? 'owner@example.com',
      email_verified_at: payload.email_verified_at ?? (payload.emailVerifiedAt !== undefined ? payload.emailVerifiedAt : user.value?.emailVerifiedAt ?? null),
      is_paid: payload.is_paid,
      is_superadmin: payload.is_superadmin,
      is_active: payload.is_active,
    }
    user.value = User.fromApi(data)
  }

  async function logout() {
    try {
      await authService.logout()
    } finally {
      clearAuthState()
    }
  }

  /** Clear token and user only (no API call). Use from API interceptor on 401/403 to avoid redirect loops. */
  function clearAuthState() {
    user.value = null
    clearSessionToken()
    clearLegacyAuthStorage()
  }

  /** Set user from API response (e.g. after auth fetch). */
  function setUserFromApi(data) {
    user.value = User.fromApi(data)
  }

  /**
   * Apply an auth response that includes { user, token, token_type }.
   * Token is stored in memory only.
   */
  function applyAuthResponse(data) {
    if (data?.token) setSessionToken(data.token, data.token_type)
    if (data?.user) setUserFromApi(data.user)
  }

  /**
   * Attempt to bootstrap auth once per page load:
   * - Calls POST /auth/refresh (uses HttpOnly cookie)
   * - On success: sets in-memory token + user
   * - On failure (401/403): clears state, no redirect here (guards decide)
   */
  async function bootstrapAuth() {
    if (isAuthBootstrapped.value) return
    if (authBootstrapPromise) return authBootstrapPromise

    authBootstrapStatus.value = 'loading'
    authBootstrapPromise = (async () => {
      clearLegacyAuthStorage()
      try {
        // Keep bootstrap fast so protected-route navigation doesn't hang when API/proxy is unreachable.
        const data = await authService.refresh({ timeout: 2500 })
        applyAuthResponse(data)
        authBootstrapHadNetworkError.value = false
      } catch (e) {
        const normalized = normalizeApiError(e)
        if (normalized.group === API_ERROR_GROUPS.NETWORK) {
          authBootstrapHadNetworkError.value = true
          // Offline / network error: do not present as "logged out".
          // Keep any existing in-memory auth state (e.g. user just logged in) and let the UI render.
          try {
            const toast = useToastStore()
            toast.info('You appear offline. Some features may not work until you reconnect.')
          } catch {
            // ignore (e.g. Pinia not ready)
          }
        } else {
          authBootstrapHadNetworkError.value = false
          // If a user just logged in while refresh-on-boot was in-flight, do not clear the fresh in-memory session.
          // Only clear when we still have no authenticated user.
          if (!user.value) clearAuthState()
        }
      } finally {
        authBootstrapStatus.value = 'done'
        authBootstrapPromise = null
      }
    })()

    return authBootstrapPromise
  }

  return {
    user,
    isAuthenticated,
    isAuthBootstrapping,
    isAuthBootstrapped,
    authBootstrapHadNetworkError,
    login,
    logout,
    clearAuthState,
    setUserFromApi,
    applyAuthResponse,
    bootstrapAuth,
  }
})
