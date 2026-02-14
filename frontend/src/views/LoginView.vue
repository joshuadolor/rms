<template>
  <AuthLayout>
    <div class="space-y-8">
      <!-- Header -->
      <div class="space-y-2">
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white text-sm">restaurant</span>
          </div>
          <span class="text-lg font-bold tracking-tight text-primary">RMS</span>
        </div>
        <h2 class="text-3xl font-bold text-charcoal dark:text-white">Sign in</h2>
        <p class="text-charcoal/60 dark:text-white/60">Access your restaurant dashboard</p>
      </div>

      <!-- Form -->
      <form class="space-y-6" novalidate @submit.prevent="handleSubmit">
        <div
          class="transition-opacity duration-200"
          :class="{ 'opacity-50 pointer-events-none': loading }"
        >
          <div
            id="login-form-error"
            role="alert"
            aria-live="polite"
            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
            :class="{ 'sr-only': !error }"
          >
            {{ error }}
          </div>
          <div class="space-y-4">
            <AppInput
              v-model="email"
              label="Email address"
              type="email"
              placeholder="you@example.com"
              autocomplete="email"
              described-by="login-form-error"
              :invalid="!!error"
            >
            <template #prefix>
              <span class="material-icons text-lg">mail_outline</span>
            </template>
          </AppInput>
          <div class="space-y-1">
            <div class="flex justify-between">
              <label for="password" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">Password</label>
              <router-link
                :to="{ name: 'ForgotPassword' }"
                class="text-sm font-medium text-primary hover:text-primary/80 transition-colors"
              >
                Forgot password?
              </router-link>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                autocomplete="current-password"
                :aria-describedby="'login-form-error'"
                :aria-invalid="!!error"
                class="block w-full pl-10 pr-10 py-3 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
              />
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-charcoal/40 dark:text-white/40">
                <span class="material-icons text-lg">lock_outline</span>
              </span>
              <button
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-charcoal/40 dark:text-white/40 hover:text-primary transition-colors"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <span class="material-icons text-lg">{{ showPassword ? 'visibility' : 'visibility_off' }}</span>
              </button>
            </div>
          </div>
        </div>
        <div class="flex items-center">
          <input
            id="remember"
            v-model="remember"
            type="checkbox"
            class="h-4 w-4 text-primary focus:ring-primary border-primary/20 rounded"
          />
          <label for="remember" class="ml-2 block text-sm text-charcoal/70 dark:text-white/70">
            Remember me for 30 days
          </label>
        </div>
        </div>
        <AppButton type="submit" variant="primary" class="w-full justify-center py-3.5 uppercase tracking-wider" :disabled="loading">
          <template v-if="loading" #icon>
            <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
          </template>
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </AppButton>
      </form>

      <template v-if="hasAnySso">
        <div class="relative">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-primary/10" />
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-background-light dark:bg-background-dark text-charcoal/40 dark:text-white/40">Or continue with</span>
          </div>
        </div>

        <div class="space-y-3">
          <GoogleSignInButton v-if="hasGoogleSso" @success="onGoogleSuccess" @error="onGoogleError" />
          <button
            v-if="hasFacebookSso"
            type="button"
            class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
            @click="authService.redirectToFacebook()"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
            </svg>
            Continue with Facebook
          </button>
          <button
            v-if="hasInstagramSso"
            type="button"
            class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
            @click="authService.redirectToInstagram()"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="url(#ig-gradient)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
              <defs>
                <linearGradient id="ig-gradient" x1="2.163" y1="21.837" x2="21.837" y2="2.163" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#fd5949" />
                  <stop offset=".5" stop-color="#d6249f" />
                  <stop offset="1" stop-color="#285AEB" />
                </linearGradient>
              </defs>
            </svg>
            Continue with Instagram
          </button>
        </div>
      </template>

      <!-- Register link -->
      <p class="text-center text-sm text-charcoal/60 dark:text-white/60">
        Don’t have an account?
        <router-link :to="{ name: 'Register' }" class="font-medium text-primary hover:text-primary/80">
          Create one
        </router-link>
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import GoogleSignInButton from '@/components/auth/GoogleSignInButton.vue'
import { useAppStore } from '@/stores/app'
import { authService, normalizeApiError } from '@/services'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()

const hasGoogleSso = !!(import.meta.env.VITE_GOOGLE_CLIENT_ID ?? '')
const hasFacebookSso = !!(import.meta.env.VITE_FACEBOOK_APP_ID ?? '')
const hasInstagramSso = !!(import.meta.env.VITE_INSTAGRAM_APP_ID ?? '')
const hasAnySso = hasGoogleSso || hasFacebookSso || hasInstagramSso

const email = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validateLogin() {
  const e = email.value.trim()
  const p = password.value
  if (!e) {
    error.value = 'Please enter your email address.'
    return false
  }
  if (!EMAIL_RE.test(e)) {
    error.value = 'Please enter a valid email address.'
    return false
  }
  if (!p) {
    error.value = 'Please enter your password.'
    return false
  }
  return true
}

async function handleSubmit() {
  error.value = ''
  if (!validateLogin()) return
  loading.value = true
  try {
    const data = await authService.login({ email: email.value.trim(), password: password.value })
    if (data.token) localStorage.setItem('rms-auth-token', data.token)
    appStore.setUserFromApi(data.user)
    const redirect = route.query.redirect || '/app'
    router.push(redirect)
  } catch (e) {
    const { message } = normalizeApiError(e)
    error.value = message || 'Sign in failed. Please try again.'
  } finally {
    loading.value = false
  }
}

async function onGoogleSuccess({ credential }) {
  error.value = ''
  loading.value = true
  try {
    const data = await authService.loginWithGoogle({ id_token: credential })
    if (data.token) localStorage.setItem('rms-auth-token', data.token)
    appStore.setUserFromApi(data.user)
    const redirect = route.query.redirect || '/app'
    router.push(redirect)
  } catch (e) {
    const { message } = normalizeApiError(e)
    error.value = message || 'Google sign-in failed. Please try again.'
  } finally {
    loading.value = false
  }
}

function onGoogleError() {
  error.value = 'Google sign-in was cancelled or failed. Please try again.'
}
</script>
