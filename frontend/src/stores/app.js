import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import User from '@/models/User'
import { authService } from '@/services'

function getStoredUser() {
  if (!localStorage.getItem('rms-auth')) return null
  const verified = localStorage.getItem('rms-user-verified') === '1'
  return User.fromApi({
    id: localStorage.getItem('rms-user-id') || null,
    name: localStorage.getItem('rms-user-name') || 'Restaurant Owner',
    email: localStorage.getItem('rms-user-email') || 'owner@example.com',
    email_verified_at: verified ? new Date().toISOString() : null,
  })
}

export const useAppStore = defineStore('app', () => {
  const user = ref(getStoredUser())

  const isAuthenticated = computed(() => !!user.value)

  function login(payload = {}) {
    const data = {
      id: payload.id ?? user.value?.id,
      name: payload.name ?? user.value?.name ?? 'Restaurant Owner',
      email: payload.email ?? user.value?.email ?? 'owner@example.com',
      email_verified_at: payload.email_verified_at ?? (payload.emailVerifiedAt !== undefined ? payload.emailVerifiedAt : user.value?.emailVerifiedAt ?? null),
    }
    user.value = User.fromApi(data)
    localStorage.setItem('rms-auth', '1')
    if (data.id != null) localStorage.setItem('rms-user-id', String(data.id))
    if (data.name) localStorage.setItem('rms-user-name', data.name)
    if (data.email) localStorage.setItem('rms-user-email', data.email)
    localStorage.setItem('rms-user-verified', user.value.isEmailVerified ? '1' : '')
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
  }

  /** Set user from API response (e.g. after auth fetch). Persists verification status for route guards. */
  function setUserFromApi(data) {
    user.value = User.fromApi(data)
    localStorage.setItem('rms-auth', '1')
    if (data.id != null) localStorage.setItem('rms-user-id', String(data.id))
    if (data.name) localStorage.setItem('rms-user-name', data.name ?? '')
    if (data.email) localStorage.setItem('rms-user-email', data.email ?? '')
    localStorage.setItem('rms-user-verified', user.value.isEmailVerified ? '1' : '')
  }

  return { user, isAuthenticated, login, logout, clearAuthState, setUserFromApi }
})
