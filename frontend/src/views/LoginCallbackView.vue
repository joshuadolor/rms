<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark flex items-center justify-center font-display">
    <div class="text-center">
      <p class="text-charcoal/60 dark:text-white/60 mb-2">{{ message }}</p>
      <p v-if="error" class="text-red-600 dark:text-red-400 text-sm">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { authService, normalizeApiError } from '@/services'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const message = ref('Completing sign in…')
const error = ref('')

/** Parse access_token from hash (#access_token=...). */
function getAccessTokenFromHash() {
  const hash = typeof window !== 'undefined' ? window.location.hash.slice(1) : ''
  const params = new URLSearchParams(hash)
  return params.get('access_token') || ''
}

onMounted(async () => {
  const provider = route.query.provider
  const redirect = route.query.redirect || '/app'

  // SSO callback: provider sent user back with token in hash (Facebook/Instagram)
  if (provider === 'facebook' || provider === 'instagram') {
    const accessToken = getAccessTokenFromHash()
    if (!accessToken) {
      error.value = 'Missing access token. Please try signing in again.'
      message.value = 'Sign-in could not be completed.'
      setTimeout(() => router.replace({ name: 'Login' }), 3000)
      return
    }
    try {
      const data = provider === 'facebook'
        ? await authService.loginWithFacebook({ access_token: accessToken })
        : await authService.loginWithInstagram({ access_token: accessToken })
      if (data.token) localStorage.setItem('rms-auth-token', data.token)
      if (data.user) appStore.setUserFromApi(data.user)
      const isVerified = data.user?.email_verified_at != null
      router.replace(isVerified ? redirect : { name: 'VerifyEmail' })
    } catch (e) {
      const { message: msg } = normalizeApiError(e)
      error.value = msg || 'Sign-in failed. Please try again.'
      message.value = 'Sign-in could not be completed.'
      setTimeout(() => router.replace({ name: 'Login' }), 4000)
    }
    return
  }

  // Legacy: token + user in query (e.g. from backend redirect)
  const token = route.query.token
  const userParam = route.query.user

  if (!token) {
    error.value = 'Missing token. Please try signing in again.'
    message.value = 'Sign-in could not be completed.'
    setTimeout(() => router.replace({ name: 'Login' }), 3000)
    return
  }

  localStorage.setItem('rms-auth-token', token)
  localStorage.setItem('rms-auth', '1')

  if (userParam) {
    try {
      const userData = typeof userParam === 'string' ? JSON.parse(decodeURIComponent(userParam)) : userParam
      appStore.setUserFromApi(userData)
    } catch {
      // Token is enough; user can be loaded via getMe() later
    }
  }

  const isVerified = appStore.user?.isEmailVerified ?? false
  router.replace(isVerified ? redirect : { name: 'VerifyEmail' })
})
</script>
