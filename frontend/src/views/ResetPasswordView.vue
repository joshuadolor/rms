<template>
  <AuthLayout>
    <div class="space-y-8">
      <div class="space-y-2">
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white text-sm">restaurant</span>
          </div>
          <span class="text-lg font-bold tracking-tight text-primary">RMS</span>
        </div>
        <router-link
          :to="{ name: 'Login' }"
          class="inline-flex items-center gap-1 text-sm text-charcoal/60 dark:text-white/60 hover:text-primary transition-colors"
        >
          <span class="material-icons text-lg">arrow_back</span>
          Back to sign in
        </router-link>
        <h2 class="text-3xl font-bold text-charcoal dark:text-white">Reset password</h2>
        <p class="text-charcoal/60 dark:text-white/60">
          {{ success ? 'Your password has been reset. You can sign in.' : 'Enter your new password below.' }}
        </p>
      </div>

      <!-- Success -->
      <div v-if="success" class="space-y-4">
        <div class="p-4 rounded-lg bg-sage/10 dark:bg-sage/20 border border-sage/30 flex items-start gap-3">
          <span class="material-icons text-sage text-2xl shrink-0">check_circle</span>
          <p class="text-charcoal dark:text-white">Your password has been reset. You can sign in.</p>
        </div>
        <router-link :to="{ name: 'Login' }">
          <AppButton variant="primary" class="w-full justify-center py-3.5">Sign in</AppButton>
        </router-link>
      </div>

      <!-- Form -->
      <form v-else class="space-y-6" novalidate @submit.prevent="handleSubmit">
        <div
          class="transition-opacity duration-200"
          :class="{ 'opacity-50 pointer-events-none': loading }"
        >
          <div
            id="reset-form-error"
            role="alert"
            aria-live="polite"
            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
            :class="{ 'sr-only': !error }"
          >
            {{ error }}
          </div>
          <div class="space-y-5">
            <div class="space-y-1">
              <label for="reset-password" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">New password</label>
              <div class="relative">
                <input
                  id="reset-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="At least 8 characters"
                  autocomplete="new-password"
                  :aria-describedby="fieldErrors.password ? 'reset-password-error' : 'reset-form-error'"
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
              <p v-if="fieldErrors.password" id="reset-password-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password }}</p>
              <p v-else class="text-xs text-charcoal/60 dark:text-white/60">Minimum 8 characters</p>
            </div>
            <div class="space-y-1">
              <label for="reset-confirm" class="block text-sm font-medium text-charcoal/80 dark:text-white/80">Confirm password</label>
              <div class="relative">
                <input
                  id="reset-confirm"
                  v-model="confirmPassword"
                  :type="showConfirm ? 'text' : 'password'"
                  placeholder="Repeat password"
                  autocomplete="new-password"
                  :aria-describedby="fieldErrors.password_confirmation ? 'reset-confirm-error' : 'reset-form-error'"
                  :aria-invalid="!!fieldErrors.password_confirmation"
                  class="block w-full pl-10 pr-4 py-3 border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                />
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-charcoal/40 dark:text-white/40">
                  <span class="material-icons text-lg">lock_outline</span>
                </span>
              </div>
              <p v-if="fieldErrors.password_confirmation" id="reset-confirm-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.password_confirmation }}</p>
              <p v-else-if="confirmPassword && password !== confirmPassword" class="text-xs text-red-600 dark:text-red-400">
                Passwords don’t match
              </p>
            </div>
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
          {{ loading ? 'Resetting…' : 'Reset password' }}
        </AppButton>
      </form>

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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { authService, normalizeApiError, getValidationErrors } from '@/services'

const route = useRoute()
const router = useRouter()

const token = computed(() => (route.query.token ?? '').toString())
const email = computed(() => (route.query.email ?? '').toString())

const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)
const loading = ref(false)
const success = ref(false)
const error = ref('')
const fieldErrors = ref({ password: '', password_confirmation: '' })

const MIN_PASSWORD_LENGTH = 8

function validatePasswordStrength(p) {
  if (p.length < MIN_PASSWORD_LENGTH) return `Password must be at least ${MIN_PASSWORD_LENGTH} characters.`
  if (!/[a-zA-Z]/.test(p)) return 'Password must include at least one letter.'
  if (!/\d/.test(p)) return 'Password must include at least one number.'
  return ''
}

onMounted(() => {
  if (!token.value || !email.value) {
    error.value = 'Invalid reset link. Please use the link from your email.'
  }
})

function validate() {
  fieldErrors.value = { password: '', password_confirmation: '' }
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
  return true
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = { password: '', password_confirmation: '' }
  if (!token.value || !email.value) {
    error.value = 'Invalid reset link. Please use the link from your email.'
    return
  }
  if (!validate()) return
  loading.value = true
  try {
    await authService.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    })
    success.value = true
  } catch (e) {
    const errors = getValidationErrors(e)
    if (Object.keys(errors).length > 0) {
      fieldErrors.value = { ...fieldErrors.value, ...errors }
    }
    const { message } = normalizeApiError(e)
    error.value = message ?? 'Something went wrong. Please try again or request a new reset link.'
  } finally {
    loading.value = false
  }
}
</script>
