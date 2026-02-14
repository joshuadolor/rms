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

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const message = ref('Completing sign in…')
const error = ref('')

onMounted(() => {
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

  const redirect = route.query.redirect || '/app'
  const isVerified = appStore.user?.isEmailVerified ?? false
  router.replace(isVerified ? redirect : { name: 'VerifyEmail' })
})
</script>
