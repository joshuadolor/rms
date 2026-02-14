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
        <router-link
          :to="{ name: 'Login' }"
          class="inline-flex items-center gap-1 text-sm text-charcoal/60 dark:text-white/60 hover:text-primary transition-colors"
        >
          <span class="material-icons text-lg">arrow_back</span>
          Back to sign in
        </router-link>
        <h2 class="text-3xl font-bold text-charcoal dark:text-white">Forgot password?</h2>
        <p class="text-charcoal/60 dark:text-white/60">
          {{ sent ? 'Check your email for a reset link.' : 'Enter your email and we’ll send you a link to reset your password.' }}
        </p>
      </div>

      <!-- Form: request reset -->
      <form v-if="!sent" class="space-y-6" novalidate @submit.prevent="handleSubmit">
        <div
          class="transition-opacity duration-200"
          :class="{ 'opacity-50 pointer-events-none': loading }"
        >
          <div
            id="forgot-form-error"
            role="alert"
            aria-live="polite"
            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
            :class="{ 'sr-only': !error }"
          >
            {{ error }}
          </div>
          <AppInput
            v-model="email"
            label="Email address"
            type="email"
            placeholder="you@example.com"
            described-by="forgot-form-error"
            :error="fieldErrors.email"
          >
            <template #prefix>
              <span class="material-icons text-lg">mail_outline</span>
            </template>
          </AppInput>
        </div>
        <AppButton type="submit" variant="primary" class="w-full justify-center py-3.5" :disabled="loading">
          <template v-if="loading" #icon>
            <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
          </template>
          {{ loading ? 'Sending…' : 'Send reset link' }}
        </AppButton>
      </form>

      <!-- Success state -->
      <div v-else class="space-y-6">
        <div class="p-4 rounded-lg bg-sage/10 dark:bg-sage/20 border border-sage/30 flex items-start gap-3">
          <span class="material-icons text-sage text-2xl shrink-0">mark_email_read</span>
          <div>
            <p class="font-medium text-charcoal dark:text-white">Check your inbox</p>
            <p class="text-sm text-charcoal/70 dark:text-white/70 mt-1">
              We sent a reset link to <strong>{{ email }}</strong>. The link expires in 1 hour.
            </p>
          </div>
        </div>
        <router-link :to="{ name: 'Login' }">
          <AppButton variant="secondary" class="w-full justify-center">Back to sign in</AppButton>
        </router-link>
        <p class="text-center text-sm text-charcoal/60 dark:text-white/60">
          Didn’t get the email?
          <button type="button" class="font-medium text-primary hover:text-primary/80" @click="sent = false">
            Try again
          </button>
        </p>
      </div>

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
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { authService, normalizeApiError, getValidationErrors } from '@/services'

const email = ref('')
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({ email: '' })
const sent = ref(false)

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validateEmail() {
  fieldErrors.value = { email: '' }
  const e = email.value.trim()
  if (!e) {
    fieldErrors.value.email = 'Please enter your email address.'
    return false
  }
  if (!EMAIL_RE.test(e)) {
    fieldErrors.value.email = 'Please enter a valid email address.'
    return false
  }
  return true
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = { email: '' }
  if (!validateEmail()) return
  loading.value = true
  try {
    await authService.forgotPassword({ email: email.value.trim() })
    sent.value = true
  } catch (e) {
    const errors = getValidationErrors(e)
    if (errors.email) fieldErrors.value.email = errors.email
    const { message } = normalizeApiError(e)
    error.value = message || 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
