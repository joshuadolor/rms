<template>
  <AuthLayout>
    <div class="space-y-8 max-w-md mx-auto">
      <!-- Mobile RMS header -->
      <div class="lg:hidden flex items-center gap-2">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
          <span class="material-icons text-white text-sm" aria-hidden="true">restaurant</span>
        </div>
        <span class="text-lg font-bold tracking-tight text-primary">{{ $t('app.name') }}</span>
      </div>

      <!-- Optional: Back to sign in -->
      <router-link
        :to="{ name: 'Login' }"
        class="inline-flex items-center gap-1 text-sm text-charcoal/60 dark:text-white/60 hover:text-primary transition-colors"
      >
        <span class="material-icons text-lg" aria-hidden="true">arrow_back</span>
        {{ $t('verify.backToSignIn') }}
      </router-link>

      <!-- Query-message alert (same style as other auth: sage info) -->
      <div
        v-if="route.query.message"
        role="alert"
        class="p-3 rounded-lg bg-primary/5 dark:bg-primary/10 border border-primary/10 text-charcoal/90 dark:text-white/90 text-sm"
      >
        {{ route.query.message }}
      </div>

      <!-- Main block: icon, headline, body, actions -->
      <div class="space-y-6 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center" aria-hidden="true">
          <span class="material-icons text-3xl text-primary">mail_outline</span>
        </div>
        <h2 class="text-3xl font-bold text-charcoal dark:text-white">
          {{ $t('verify.checkYourEmail') }}
        </h2>
        <div class="space-y-4">
          <p class="text-charcoal/60 dark:text-white/60 leading-relaxed">
            {{ $t('verify.sentToEmail', { email: displayEmail }) }}
          </p>
          <p class="text-sm text-primary font-medium">
            {{ $t('verify.oneStepAway') }}
          </p>
        </div>

        <!-- Resend: one place for success/error feedback -->
        <div class="space-y-4">
          <p class="text-sm text-charcoal/60 dark:text-white/60">
            {{ $t('verify.checkSpam') }}
            <button
              type="button"
              class="inline-flex items-center gap-1.5 font-medium text-primary hover:text-primary/80 disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] py-2 -my-2"
              :disabled="resendCooldown > 0 || resendLoading"
              @click="user ? handleResendAuthenticated() : (resendEmail ? handleResendGuest() : (showGuestEmail = true))"
            >
              <span
                v-if="resendLoading"
                class="material-icons text-lg verify-spinner"
                aria-hidden="true"
              >sync</span>
              {{ resendCooldown > 0 ? $t('app.resendIn', { seconds: resendCooldown }) : resendLoading ? $t('verify.sending') : $t('verify.resend') }}
            </button>
          </p>

          <!-- Guest without email: minimal one-line resend -->
          <form
            v-if="!user && showGuestEmail"
            class="flex flex-wrap items-center gap-2"
            novalidate
            @submit.prevent="handleResendGuest"
          >
            <input
              v-model="resendEmail"
              type="email"
              :placeholder="$t('app.emailPlaceholder')"
              class="flex-1 min-w-[140px] px-3 py-2 text-sm border border-primary/20 rounded-lg bg-white dark:bg-zinc-800 text-charcoal dark:text-white focus:outline-none focus:ring-2 focus:ring-primary min-h-[44px]"
              :aria-required="true"
              :aria-invalid="!!resendError"
              :aria-describedby="resendError ? 'verify-resend-email-error' : undefined"
            />
            <p v-if="resendError" id="verify-resend-email-error" class="text-xs text-red-600 dark:text-red-400 w-full basis-full" role="alert">{{ resendError }}</p>
            <AppButton
              type="submit"
              variant="secondary"
              class="min-h-[44px]"
              :disabled="resendCooldown > 0 || resendLoading"
            >
              <template v-if="resendLoading" #icon>
                <span class="material-icons text-base verify-spinner" aria-hidden="true">sync</span>
              </template>
              {{ resendLoading ? $t('verify.sending') : $t('verify.resend') }}
            </AppButton>
          </form>

          <!-- Single feedback area for resend success or error -->
          <div
            v-if="resendSuccess || resendError"
            :role="resendError ? 'alert' : 'status'"
            :class="resendSuccess ? 'p-3 rounded-lg bg-sage/10 dark:bg-sage/20 border border-sage/30 text-sage-700 dark:text-sage-300 text-sm' : 'p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm'"
          >
            {{ resendSuccess || resendError }}
          </div>
        </div>

        <!-- Actions -->
        <div class="space-y-4 pt-2">
          <router-link :to="{ name: 'Login' }">
            <AppButton variant="primary" class="w-full justify-center py-3.5 min-h-[44px]">
              {{ $t('verify.goToSignIn') }}
            </AppButton>
          </router-link>
          <p v-if="user" class="text-center">
            <button
              type="button"
              class="min-h-[44px] py-2 text-sm text-charcoal/50 dark:text-white/50 hover:text-charcoal dark:hover:text-white transition-colors"
              @click="handleSignOut"
            >
              {{ $t('verify.signOut') }}
            </button>
          </p>
        </div>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAppStore } from '@/stores/app'
import { authService, normalizeApiError } from '@/services'

const { t } = useI18n()
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
    resendSuccess.value = data.message ?? t('verify.resendSuccessDefault')
    startCooldown()
  } catch (e) {
    resendError.value = normalizeApiError(e).message
  } finally {
    resendLoading.value = false
  }
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

async function handleResendGuest() {
  const emailVal = resendEmail.value?.trim()
  if (!emailVal) {
    resendError.value = t('verify.emailRequired')
    return
  }
  if (!EMAIL_RE.test(emailVal)) {
    resendError.value = t('verify.emailInvalid')
    return
  }
  resendError.value = ''
  resendSuccess.value = ''
  resendLoading.value = true
  try {
    const data = await authService.resendVerificationEmail({ email: emailVal })
    resendSuccess.value = data.message ?? t('verify.resendSuccessGuestDefault')
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

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .verify-spinner {
    animation: none;
  }
}
.verify-spinner {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
