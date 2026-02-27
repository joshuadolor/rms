<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark flex items-center justify-center font-display">
    <div class="text-center px-4">
      <div v-if="!error" class="w-16 h-16 mx-auto rounded-full bg-primary/10 flex items-center justify-center mb-4">
        <span class="material-icons text-3xl text-primary animate-spin" aria-hidden="true">sync</span>
      </div>
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
import { setSessionToken } from '@/auth/session'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const message = ref('Completing sign in…')
const error = ref('')

const UNVERIFIED_MESSAGE = 'Your email address is not verified.'
const VERIFY_EMAIL_MESSAGE = 'Please verify your email to continue.'

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
      appStore.applyAuthResponse(data)
      const isVerified = appStore.user?.isEmailVerified ?? false
      if (isVerified) {
        router.replace(redirect)
        return
      }
      const knownEmail = appStore.user?.email ? String(appStore.user.email) : ''
      router.replace(knownEmail ? { name: 'VerifyEmail', query: { email: knownEmail } } : { name: 'VerifyEmail' })
    } catch (e) {
      const normalized = normalizeApiError(e)
      if (normalized.status === 403 && normalized.message?.includes(UNVERIFIED_MESSAGE)) {
        // For SSO, we often don't know the email when the backend blocks before issuing a token.
        // Still route to Verify Email and show a friendly message.
        const knownEmail = typeof route.query.email === 'string' ? route.query.email : ''
        router.replace({
          name: 'VerifyEmail',
          query: {
            ...(knownEmail ? { email: knownEmail } : {}),
            message: VERIFY_EMAIL_MESSAGE,
          },
        })
        return
      }
      error.value = normalized.message || 'Sign-in failed. Please try again.'
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

  setSessionToken(String(token))

  if (userParam) {
    try {
      const userData = typeof userParam === 'string' ? JSON.parse(decodeURIComponent(userParam)) : userParam
      appStore.setUserFromApi(userData)
    } catch {
      // Token is enough; user can be loaded via getMe() later
    }
  }

  const isVerified = appStore.user?.isEmailVerified ?? false
  if (isVerified) {
    router.replace(redirect)
    return
  }
  const knownEmail = appStore.user?.email ? String(appStore.user.email) : ''
  router.replace(knownEmail ? { name: 'VerifyEmail', query: { email: knownEmail } } : { name: 'VerifyEmail' })
})
</script>
