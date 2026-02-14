<template>
  <AuthLayout>
    <div class="max-w-md mx-auto">
      <!-- Main message block: one focal, one headline, one line -->
      <div class="text-center pt-2 pb-10">
        <div class="w-14 h-14 mx-auto rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center mb-6">
          <span class="material-icons text-2xl text-primary" aria-hidden="true">mail_outline</span>
        </div>
        <h2 class="text-2xl font-bold text-charcoal dark:text-white mb-2">Check your email</h2>
        <p class="text-charcoal/70 dark:text-white/70 text-[15px] leading-snug">
          We sent a verification link to <strong class="text-charcoal dark:text-white">{{ displayEmail }}</strong>. Click it to continue.
        </p>
        <p class="mt-4 text-sm text-primary font-medium">
          You’re one step away from managing your menu.
        </p>
      </div>

      <!-- Soft note (e.g. from 403 redirect) – not warning-styled -->
      <div
        v-if="route.query.message"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-primary/5 dark:bg-primary/10 border border-primary/10 text-charcoal/90 dark:text-white/90 text-sm"
      >
        {{ route.query.message }}
      </div>

      <!-- Secondary: check spam + resend (text link, cooldown) -->
      <div class="mb-8 space-y-3">
        <p class="text-sm text-charcoal/50 dark:text-white/50">
          Check your spam folder. Didn’t get it?
          <button
            type="button"
            class="font-medium text-primary hover:text-primary/80 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="resendCooldown > 0 || resendLoading"
            @click="user ? handleResendAuthenticated() : (resendEmail ? handleResendGuest() : (showGuestEmail = true))"
          >
            {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : resendLoading ? 'Sending…' : 'Resend link' }}
          </button>
        </p>
        <!-- Guest without email: minimal one-line resend -->
        <form
          v-if="!user && showGuestEmail"
          class="flex flex-wrap items-center gap-2"
          @submit.prevent="handleResendGuest"
        >
          <input
            v-model="resendEmail"
            type="email"
            placeholder="your@email.com"
            class="flex-1 min-w-[140px] px-3 py-2 text-sm border border-primary/20 rounded-lg bg-white dark:bg-white/5 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary"
            required
          />
          <button
            type="submit"
            class="text-sm font-medium text-primary hover:text-primary/80 disabled:opacity-50"
            :disabled="resendCooldown > 0 || resendLoading"
          >
            {{ resendLoading ? 'Sending…' : 'Resend' }}
          </button>
        </form>
        <div v-if="resendSuccess" class="text-sm text-sage-700 dark:text-sage-300" role="status">
          {{ resendSuccess }}
        </div>
        <div v-if="resendError" class="text-sm text-red-600 dark:text-red-400" role="alert">
          {{ resendError }}
        </div>
      </div>

      <!-- Actions: single primary CTA, then sign out as subtle link -->
      <div class="space-y-4">
        <router-link :to="{ name: 'Login' }">
          <AppButton variant="primary" class="w-full justify-center py-3.5">
            Go to sign in
          </AppButton>
        </router-link>
        <p v-if="user" class="text-center">
          <button
            type="button"
            class="text-sm text-charcoal/50 dark:text-white/50 hover:text-charcoal dark:hover:text-white transition-colors"
            @click="handleSignOut"
          >
            Sign out
          </button>
        </p>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAppStore } from '@/stores/app'
import { authService, normalizeApiError } from '@/services'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const { user } = storeToRefs(appStore)

const displayEmail = computed(() => user.value?.email ?? route.query.email ?? 'your email')

const resendEmail = ref(route.query.email ?? '')
const showGuestEmail = ref(false)
const resendSuccess = ref('')
const resendError = ref('')
const resendLoading = ref(false)
const resendCooldown = ref(0)
const COOLDOWN_SECONDS = 60
let cooldownTimer = null

onMounted(() => {
  if (!user.value && !route.query.email && !route.query.message) {
    router.replace({ name: 'Login' })
  }
})

function startCooldown() {
  resendCooldown.value = COOLDOWN_SECONDS
  if (cooldownTimer) clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    resendCooldown.value -= 1
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

async function handleResendAuthenticated() {
  resendError.value = ''
  resendSuccess.value = ''
  resendLoading.value = true
  try {
    const data = await authService.resendVerificationEmail()
    resendSuccess.value = data.message ?? 'Verification email sent. Please check your inbox.'
    startCooldown()
  } catch (e) {
    resendError.value = normalizeApiError(e).message
  } finally {
    resendLoading.value = false
  }
}

async function handleResendGuest() {
  const email = resendEmail.value?.trim()
  if (!email) {
    resendError.value = 'Please enter your email address.'
    return
  }
  resendError.value = ''
  resendSuccess.value = ''
  resendLoading.value = true
  try {
    const data = await authService.resendVerificationEmail({ email })
    resendSuccess.value = data.message ?? 'If that email is registered and unverified, we sent a new link.'
    startCooldown()
  } catch (e) {
    resendError.value = normalizeApiError(e).message
  } finally {
    resendLoading.value = false
  }
}

function handleSignOut() {
  appStore.logout()
  router.push({ name: 'Landing' })
}
</script>
