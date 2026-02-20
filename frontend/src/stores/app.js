import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import User from '@/models/User'
import { authService } from '@/services'

function getStoredUser() {
  if (!localStorage.getItem('rms-auth')) return null
  const verified = localStorage.getItem('rms-user-verified') === '1'
  const isPaid = localStorage.getItem('rms-user-is-paid') === '1'
  const isSuperadmin = localStorage.getItem('rms-user-is-superadmin') === '1'
  const isActive = localStorage.getItem('rms-user-is-active') !== '0'
  return User.fromApi({
    uuid: localStorage.getItem('rms-user-id') || null,
    name: localStorage.getItem('rms-user-name') || 'Restaurant Owner',
    email: localStorage.getItem('rms-user-email') || 'owner@example.com',
    email_verified_at: verified ? new Date().toISOString() : null,
    pending_email: localStorage.getItem('rms-user-pending-email') || null,
    is_paid: isPaid,
    is_superadmin: isSuperadmin,
    is_active: isActive,
  })
}

export const useAppStore = defineStore('app', () => {
  const user = ref(getStoredUser())

  const isAuthenticated = computed(() => !!user.value)

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
    localStorage.setItem('rms-auth', '1')
    const uid = data.uuid ?? data.id
    if (uid != null) localStorage.setItem('rms-user-id', String(uid))
    if (data.name) localStorage.setItem('rms-user-name', data.name)
    if (data.email) localStorage.setItem('rms-user-email', data.email)
    localStorage.setItem('rms-user-verified', user.value.isEmailVerified ? '1' : '')
    if (data.is_paid === true) localStorage.setItem('rms-user-is-paid', '1')
    else localStorage.removeItem('rms-user-is-paid')
    if (user.value?.isSuperadmin === true) localStorage.setItem('rms-user-is-superadmin', '1')
    else localStorage.removeItem('rms-user-is-superadmin')
    if (user.value?.isActive === true) localStorage.setItem('rms-user-is-active', '1')
    else localStorage.setItem('rms-user-is-active', '0')
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
  }

  /** Set user from API response (e.g. after auth fetch). Persists verification status for route guards. */
  function setUserFromApi(data) {
    user.value = User.fromApi(data)
    const u = user.value
    localStorage.setItem('rms-auth', '1')
    if (u?.id != null) localStorage.setItem('rms-user-id', String(u.id))
    if (u?.name) localStorage.setItem('rms-user-name', u.name)
    if (u?.email) localStorage.setItem('rms-user-email', u.email)
    localStorage.setItem('rms-user-verified', u?.isEmailVerified ? '1' : '')
    if (u?.pendingEmail != null) localStorage.setItem('rms-user-pending-email', u.pendingEmail)
    else localStorage.removeItem('rms-user-pending-email')
    if (u?.isPaid === true) localStorage.setItem('rms-user-is-paid', '1')
    else localStorage.removeItem('rms-user-is-paid')
    if (u?.isSuperadmin === true) localStorage.setItem('rms-user-is-superadmin', '1')
    else localStorage.removeItem('rms-user-is-superadmin')
    if (u?.isActive === true) localStorage.setItem('rms-user-is-active', '1')
    else localStorage.setItem('rms-user-is-active', '0')
  }

  return { user, isAuthenticated, login, logout, clearAuthState, setUserFromApi }
})
