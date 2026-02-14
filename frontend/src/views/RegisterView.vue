<template>
  <AuthLayout>
    <div class="space-y-10">
      <!-- Header -->
      <div class="space-y-2">
        <div class="lg:hidden flex items-center gap-2 mb-6">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white text-sm">restaurant</span>
          </div>
          <span class="text-lg font-bold tracking-tight text-primary">RMS</span>
        </div>
        <h2 class="text-3xl font-bold text-charcoal dark:text-white">Create an account</h2>
        <p class="text-charcoal/60 dark:text-white/60">Start managing your restaurant for free</p>
      </div>

      <!-- Step indicator (step 2 only) -->
      <div v-if="step === 2" class="flex items-center gap-2 text-sm">
        <span class="text-charcoal/50 dark:text-white/50 font-medium">Step 2 of 2</span>
        <div class="flex-1 h-1.5 bg-charcoal/10 dark:bg-white/10 rounded-full max-w-[120px]">
          <div class="h-full w-full bg-primary rounded-full" />
        </div>
      </div>

      <!-- Step 1: Social + identity -->
      <template v-if="step === 1">
        <template v-if="hasAnySso">
          <div class="space-y-3">
            <GoogleSignInButton v-if="hasGoogleSso" @success="onGoogleSuccess" @error="onGoogleError" />
            <button
              v-if="hasFacebookSso"
              type="button"
              class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
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
              class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white hover:bg-primary/5 transition-all font-medium"
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
              <span class="px-4 bg-background-light dark:bg-background-dark text-charcoal/40 dark:text-white/40">Or sign up with email</span>
            </div>
          </div>
        </template>

        <!-- Name + Email (step 1 is client-only, no loading) -->
        <form class="space-y-5" novalidate @submit.prevent="goToStep2">
          <div
            id="register-step1-error"
            role="alert"
            aria-live="polite"
            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
            :class="{ 'sr-only': !error }"
          >
            {{ error }}
          </div>
          <AppInput
            v-model="name"
            label="Full name"
            type="text"
            placeholder="Jane Smith"
            described-by="register-step1-error"
            :error="fieldErrors.name"
          >
            <template #prefix>
              <span class="material-icons text-lg">person_outline</span>
            </template>
          </AppInput>
          <AppInput
            v-model="email"
            label="Email address"
            type="email"
            placeholder="you@example.com"
            described-by="register-step1-error"
            :error="fieldErrors.email"
          >
            <template #prefix>
              <span class="material-icons text-lg">mail_outline</span>
            </template>
          </AppInput>
          <AppButton type="submit" variant="primary" class="w-full justify-center py-3.5">
            Continue
          </AppButton>
        </form>
      </template>

      <!-- Step 2: Password + terms -->
      <template v-else>
        <form class="space-y-6" novalidate @submit.prevent="handleSubmit">
          <div
            class="transition-opacity duration-200"
            :class="{ 'opacity-50 pointer-events-none': loading }"
          >
            <div
              id="register-step2-error"
              role="alert"
              aria-live="polite"
              class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
              :class="{ 'sr-only': !error }"
            >
              {{ error }}
            </div>
            <button
              type="button"
              class="text-sm text-charcoal/60 dark:text-white/60 hover:text-primary flex items-center gap-1 -mt-2"
              @click="step = 1"
            >
              <span class="material-icons text-lg">arrow_back</span>
              Back
            </button>

            <div class="space-y-5">
            <div class="space-y-1">
              <label for="reg-password" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">Password</label>
              <div class="relative">
                <input
                  id="reg-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="At least 8 characters"
                  :aria-describedby="fieldErrors.password ? 'reg-password-error' : 'register-step2-error'"
                  :aria-invalid="!!fieldErrors.password"
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
              <p v-if="fieldErrors.password" id="reg-password-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password }}</p>
              <p v-else class="text-xs text-charcoal/60 dark:text-white/60">Minimum 8 characters</p>
            </div>
            <div class="space-y-1">
              <label for="reg-confirm" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">Confirm password</label>
              <div class="relative">
                <input
                  id="reg-confirm"
                  v-model="confirmPassword"
                  :type="showConfirm ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Repeat password"
                  :aria-describedby="fieldErrors.password_confirmation ? 'reg-confirm-error' : 'register-step2-error'"
                  :aria-invalid="!!fieldErrors.password_confirmation"
                  class="block w-full pl-10 pr-4 py-3 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                />
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-charcoal/40 dark:text-white/40">
                  <span class="material-icons text-lg">lock_outline</span>
                </span>
              </div>
              <p v-if="fieldErrors.password_confirmation" id="reg-confirm-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password_confirmation }}</p>
              <p v-else-if="confirmPassword && password !== confirmPassword" class="text-xs text-red-600 dark:text-red-400">
                Passwords don’t match
              </p>
            </div>
          </div>

          <div class="flex items-start">
            <input
              id="terms"
              v-model="acceptedTerms"
              type="checkbox"
              aria-describedby="register-step2-error"
              :aria-invalid="!!error"
              class="h-4 w-4 text-primary focus:ring-primary border-primary/20 rounded mt-0.5"
            />
            <label for="terms" class="ml-2 block text-sm text-charcoal/70 dark:text-white/70">
              I agree to the
              <a href="#" class="text-primary hover:underline">Terms of Service</a>
              and
              <a href="#" class="text-primary hover:underline">Privacy Policy</a>
            </label>
          </div>
          </div>

          <AppButton
            type="submit"
            variant="primary"
            class="w-full justify-center py-3.5"
            :disabled="loading || (!!confirmPassword && password !== confirmPassword)"
          >
            <template v-if="loading" #icon>
              <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
            </template>
            {{ loading ? 'Creating account…' : 'Create account' }}
          </AppButton>
        </form>
      </template>

      <!-- Footer link -->
      <p class="text-center text-sm text-charcoal/60 dark:text-white/60 pt-2">
        Already have an account?
        <router-link :to="{ name: 'Login' }" class="font-medium text-primary hover:text-primary/80">
          Sign in
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

const router = useRouter()
const appStore = useAppStore()

const hasGoogleSso = !!(import.meta.env.VITE_GOOGLE_CLIENT_ID ?? '')
const hasFacebookSso = !!(import.meta.env.VITE_FACEBOOK_APP_ID ?? '')
const hasInstagramSso = !!(import.meta.env.VITE_INSTAGRAM_APP_ID ?? '')
const hasAnySso = hasGoogleSso || hasFacebookSso || hasInstagramSso

const step = ref(1)
const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)
const acceptedTerms = ref(false)
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({ name: '', email: '', password: '', password_confirmation: '' })

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const MIN_PASSWORD_LENGTH = 8
const MAX_NAME_LENGTH = 255
const MAX_EMAIL_LENGTH = 255

function goToStep2() {
  error.value = ''
  fieldErrors.value = { name: '', email: '', password: '', password_confirmation: '' }
  const nameTrimmed = name.value.trim()
  if (!nameTrimmed) {
    fieldErrors.value.name = 'Please enter your name.'
    return
  }
  if (nameTrimmed.length > MAX_NAME_LENGTH) {
    fieldErrors.value.name = `Name must be at most ${MAX_NAME_LENGTH} characters.`
    return
  }
  const e = email.value.trim()
  if (!e) {
    fieldErrors.value.email = 'Please enter your email address.'
    return
  }
  if (!EMAIL_RE.test(e)) {
    fieldErrors.value.email = 'Please enter a valid email address.'
    return
  }
  if (e.length > MAX_EMAIL_LENGTH) {
    fieldErrors.value.email = `Email must be at most ${MAX_EMAIL_LENGTH} characters.`
    return
  }
  step.value = 2
}

function validatePasswordStrength(p) {
  if (p.length < MIN_PASSWORD_LENGTH) return `Password must be at least ${MIN_PASSWORD_LENGTH} characters.`
  if (!/[a-zA-Z]/.test(p)) return 'Password must include at least one letter.'
  if (!/\d/.test(p)) return 'Password must include at least one number.'
  return ''
}

function validateStep2() {
  fieldErrors.value = { ...fieldErrors.value, password: '', password_confirmation: '' }
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
    error.value = 'Please agree to the Terms of Service and Privacy Policy.'
    return false
  }
  return true
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = { ...fieldErrors.value, password: '', password_confirmation: '' }
  if (!validateStep2()) return
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
    const isEmailTaken =
      e?.response?.status === 422 &&
      (e?.response?.data?.errors?.email?.length > 0 ||
        /already been taken|email.*taken/i.test(message ?? ''))
    if (isEmailTaken) {
      step.value = 1
    }
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
    router.push('/app')
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
