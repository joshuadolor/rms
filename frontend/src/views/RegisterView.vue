<template>
  <AuthLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="space-y-2">
        <div class="lg:hidden flex items-center gap-2 mb-6">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white text-sm">restaurant</span>
          </div>
          <span class="text-lg font-bold tracking-tight text-primary">RMS</span>
        </div>
        <h2 data-testid="register-heading" class="text-3xl font-bold text-charcoal dark:text-white">{{ $t('app.register') }}</h2>
        <p class="text-charcoal/60 dark:text-white/60">{{ $t('app.startManaging') }}</p>
      </div>

      <!-- Optional SSO -->
      <template v-if="hasAnySso">
        <div class="space-y-3">
          <GoogleSignInButton
            v-if="hasGoogleSso"
            class="min-h-[44px]"
            @success="onGoogleSuccess"
            @error="onGoogleError"
          />
          <button
            v-if="hasFacebookSso"
            type="button"
            class="w-full flex items-center justify-center gap-3 min-h-[44px] py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
            @click="authService.redirectToFacebook()"
          >
            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
            </svg>
            Continue with Facebook
          </button>
          <button
            v-if="hasInstagramSso"
            type="button"
            class="w-full flex items-center justify-center gap-3 min-h-[44px] py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
            @click="authService.redirectToInstagram()"
          >
            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="url(#ig-gradient-reg)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
              <defs>
                <linearGradient id="ig-gradient-reg" x1="2.163" y1="21.837" x2="21.837" y2="2.163" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#fd5949" />
                  <stop offset=".5" stop-color="#d6249f" />
                  <stop offset="1" stop-color="#285AEB" />
                </linearGradient>
              </defs>
            </svg>
            Continue with Instagram
          </button>
        </div>

        <div class="relative py-1">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-primary/10" />
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-background-light dark:bg-background-dark text-charcoal/40 dark:text-white/40">{{ $t('app.orSignUpWithEmail') }}</span>
          </div>
        </div>
      </template>

      <!-- Single registration form -->
      <form data-testid="register-form" class="space-y-5" novalidate @submit.prevent="handleSubmit">
        <div
          id="register-error"
          role="alert"
          aria-live="polite"
          class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          :class="{ 'sr-only': !error }"
        >
          {{ error }}
        </div>

        <div
          class="transition-opacity duration-200 space-y-5"
          :class="{ 'opacity-50 pointer-events-none': loading }"
        >
          <AppInput
            v-model="name"
            :label="$t('app.fullName')"
            type="text"
            :placeholder="$t('app.fullNamePlaceholder')"
            :error="fieldErrors.name"
          >
            <template #prefix>
              <span class="material-icons text-lg">person_outline</span>
            </template>
          </AppInput>
          <AppInput
            v-model="email"
            :label="$t('app.emailAddress')"
            type="email"
            :placeholder="$t('app.emailPlaceholder')"
            :error="fieldErrors.email"
          >
            <template #prefix>
              <span class="material-icons text-lg">mail_outline</span>
            </template>
          </AppInput>

          <div class="space-y-1">
            <label for="reg-password" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">{{ $t('app.password') }}</label>
            <div class="relative">
              <input
                id="reg-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                :placeholder="$t('app.passwordHintPlaceholder')"
                :aria-describedby="fieldErrors.password ? 'reg-password-error' : 'register-error'"
                :aria-invalid="!!fieldErrors.password"
                class="block w-full pl-10 pr-10 py-3 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all min-h-[44px]"
              />
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-charcoal/40 dark:text-white/40">
                <span class="material-icons text-lg">lock_outline</span>
              </span>
              <button
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-charcoal/40 dark:text-white/40 hover:text-primary transition-colors min-h-[44px]"
                :aria-label="showPassword ? $t('app.hidePassword') : $t('app.showPassword')"
                @click="showPassword = !showPassword"
              >
                <span class="material-icons text-lg">{{ showPassword ? 'visibility' : 'visibility_off' }}</span>
              </button>
            </div>
            <p v-if="fieldErrors.password" id="reg-password-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password }}</p>
            <p v-else class="text-xs text-charcoal/60 dark:text-white/60">{{ $t('app.minimumCharacters') }}</p>
          </div>

          <div class="space-y-1">
            <label for="reg-confirm" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">{{ $t('app.confirmPassword') }}</label>
            <div class="relative">
              <input
                id="reg-confirm"
                v-model="confirmPassword"
                :type="showConfirm ? 'text' : 'password'"
                autocomplete="new-password"
                :placeholder="$t('app.repeatPassword')"
                :aria-describedby="fieldErrors.password_confirmation ? 'reg-confirm-error' : 'register-error'"
                :aria-invalid="!!fieldErrors.password_confirmation"
                class="block w-full pl-10 pr-4 py-3 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all min-h-[44px]"
              />
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-charcoal/40 dark:text-white/40">
                <span class="material-icons text-lg">lock_outline</span>
              </span>
            </div>
            <p v-if="fieldErrors.password_confirmation" id="reg-confirm-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password_confirmation }}</p>
            <p v-else-if="confirmPassword && password !== confirmPassword" class="text-xs text-red-600 dark:text-red-400">
              {{ $t('app.passwordsDontMatch') }}
            </p>
          </div>

          <div class="flex items-start gap-3 pt-1">
            <input
              id="terms"
              v-model="acceptedTerms"
              type="checkbox"
              :aria-describedby="fieldErrors.terms ? 'terms-error' : 'register-error'"
              :aria-invalid="!!fieldErrors.terms"
              class="h-4 w-4 text-primary focus:ring-primary border-primary/20 rounded mt-1 shrink-0"
            />
            <label for="terms" class="flex items-center text-sm text-charcoal/70 dark:text-white/70 cursor-pointer min-h-[44px] py-2 -my-2">
              {{ $t('app.agreeTermsIntro') }}
              <a href="#" class="text-primary hover:underline">{{ $t('app.termsOfService') }}</a>
              {{ $t('app.and') }}
              <a href="#" class="text-primary hover:underline">{{ $t('app.privacyPolicy') }}</a>
            </label>
          </div>
          <p v-if="fieldErrors.terms" id="terms-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.terms }}</p>
        </div>

        <AppButton
          type="submit"
          variant="primary"
          class="w-full justify-center min-h-[44px] py-3.5"
          :disabled="loading"
        >
          <template v-if="loading" #icon>
            <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
          </template>
          {{ loading ? $t('app.creatingAccount') : $t('app.createAccount') }}
        </AppButton>
      </form>

      <!-- Footer link -->
      <p class="text-center text-sm text-charcoal/60 dark:text-white/60 pt-2">
        {{ $t('app.alreadyHaveAccount') }}
        <router-link :to="{ name: 'Login' }" class="font-medium text-primary hover:text-primary/80">
          {{ $t('app.signIn') }}
        </router-link>
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import GoogleSignInButton from '@/components/auth/GoogleSignInButton.vue'
import { useAppStore } from '@/stores/app'
import { authService, normalizeApiError, getValidationErrors } from '@/services'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const appStore = useAppStore()
const { t } = useI18n()

const UNVERIFIED_MESSAGE = 'Your email address is not verified.'

const hasGoogleSso = !!(import.meta.env.VITE_GOOGLE_CLIENT_ID ?? '')
const hasFacebookSso = !!(import.meta.env.VITE_FACEBOOK_APP_ID ?? '')
const hasInstagramSso = !!(import.meta.env.VITE_INSTAGRAM_APP_ID ?? '')
const hasAnySso = hasGoogleSso || hasFacebookSso || hasInstagramSso

const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)
const acceptedTerms = ref(false)
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  terms: '',
})

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const MIN_PASSWORD_LENGTH = 8
const MAX_NAME_LENGTH = 255
const MAX_EMAIL_LENGTH = 255

function decodeJwtPayload(jwt) {
  if (!jwt || typeof jwt !== 'string') return null
  const parts = jwt.split('.')
  if (parts.length < 2) return null
  let payload = parts[1]
  payload = payload.replace(/-/g, '+').replace(/_/g, '/')
  const pad = payload.length % 4
  if (pad) payload += '='.repeat(4 - pad)
  try {
    const json = atob(payload)
    return JSON.parse(json)
  } catch {
    return null
  }
}

function getEmailFromIdToken(idToken) {
  const payload = decodeJwtPayload(idToken)
  const emailFromToken = payload?.email
  return typeof emailFromToken === 'string' && emailFromToken ? emailFromToken : ''
}

function validatePasswordStrength(p) {
  if (p.length < MIN_PASSWORD_LENGTH) return `Password must be at least ${MIN_PASSWORD_LENGTH} characters.`
  if (!/[a-zA-Z]/.test(p)) return 'Password must include at least one letter.'
  if (!/\d/.test(p)) return 'Password must include at least one number.'
  return ''
}

function validateForm() {
  error.value = ''
  fieldErrors.value = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: '',
  }

  const nameTrimmed = name.value.trim()
  if (!nameTrimmed) {
    fieldErrors.value.name = 'Please enter your name.'
    return false
  }
  if (nameTrimmed.length > MAX_NAME_LENGTH) {
    fieldErrors.value.name = `Name must be at most ${MAX_NAME_LENGTH} characters.`
    return false
  }

  const e = email.value.trim()
  if (!e) {
    fieldErrors.value.email = 'Please enter your email address.'
    return false
  }
  if (!EMAIL_RE.test(e)) {
    fieldErrors.value.email = 'Please enter a valid email address.'
    return false
  }
  if (e.length > MAX_EMAIL_LENGTH) {
    fieldErrors.value.email = `Email must be at most ${MAX_EMAIL_LENGTH} characters.`
    return false
  }

  const p = password.value
  const c = confirmPassword.value
  if (!p) {
    fieldErrors.value.password = 'Please enter a password.'
    return false
  }
  const pwdMsg = validatePasswordStrength(p)
  if (pwdMsg) {
    fieldErrors.value.password = pwdMsg
    return false
  }
  if (!c) {
    fieldErrors.value.password_confirmation = 'Please confirm your password.'
    return false
  }
  if (p !== c) {
    fieldErrors.value.password_confirmation = 'Passwords do not match.'
    return false
  }
  if (!acceptedTerms.value) {
    fieldErrors.value.terms = t('app.agreeTermsRequired')
    return false
  }
  return true
}

async function handleSubmit() {
  if (!validateForm()) return
  loading.value = true
  try {
    await authService.register({
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: confirmPassword.value,
    })
    router.push({
      name: 'VerifyEmail',
      query: { email: email.value.trim() },
    })
  } catch (e) {
    const errors = getValidationErrors(e)
    if (Object.keys(errors).length > 0) {
      fieldErrors.value = { ...fieldErrors.value, ...errors }
    }
    const { message } = normalizeApiError(e)
    error.value = message || 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
}

async function onGoogleSuccess({ credential }) {
  error.value = ''
  loading.value = true
  try {
    const data = await authService.loginWithGoogle({ id_token: credential })
    appStore.applyAuthResponse(data)
    router.push('/app')
  } catch (e) {
    const normalized = normalizeApiError(e)
    if (normalized.status === 403 && normalized.message?.includes(UNVERIFIED_MESSAGE)) {
      const knownEmail = getEmailFromIdToken(credential)
      router.push({
        name: 'VerifyEmail',
        query: {
          ...(knownEmail ? { email: knownEmail } : {}),
          message: 'Please verify your email to continue.',
        },
      })
      return
    }
    error.value = normalized.message || 'Google sign-in failed. Please try again.'
  } finally {
    loading.value = false
  }
}

function onGoogleError() {
  error.value = 'Google sign-in was cancelled or failed. Please try again.'
}
</script>
