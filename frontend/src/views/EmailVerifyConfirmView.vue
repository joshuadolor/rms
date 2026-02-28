<template>
  <AuthLayout>
    <div class="space-y-8 max-w-md mx-auto text-center">
      <!-- Mobile RMS header -->
      <div class="lg:hidden flex items-center gap-2">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
          <span class="material-icons text-white text-sm" aria-hidden="true">restaurant</span>
        </div>
        <span class="text-lg font-bold tracking-tight text-primary">{{ $t('app.name') }}</span>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-6">
        <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center" aria-hidden="true">
          <span class="material-icons text-3xl text-primary verify-spinner">sync</span>
        </div>
        <p role="status" aria-live="polite" class="text-charcoal/60 dark:text-white/60 leading-relaxed">
          {{ $t('verify.verifying') }}
        </p>
      </div>

      <!-- Success -->
      <template v-else-if="result?.success">
        <div class="p-6 rounded-xl space-y-6 bg-sage/10 dark:bg-sage/20 border border-sage/30 text-left">
          <div class="w-16 h-16 mx-auto rounded-full bg-sage/20 flex items-center justify-center" aria-hidden="true">
            <span class="material-icons text-3xl text-sage">check_circle</span>
          </div>
          <h2 class="text-3xl font-bold text-charcoal dark:text-white">
            {{ $t('verify.emailVerified') }}
          </h2>
          <p class="text-charcoal/60 dark:text-white/60 leading-relaxed">
            {{ result.message }}
          </p>
          <router-link :to="{ name: 'Login' }">
            <AppButton variant="primary" class="w-full justify-center py-3.5 min-h-[44px]">
              {{ $t('verify.signIn') }}
            </AppButton>
          </router-link>
        </div>
      </template>

      <!-- Failure -->
      <template v-else-if="result">
        <div class="p-6 rounded-xl space-y-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 text-left">
          <div class="w-16 h-16 mx-auto rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center" aria-hidden="true">
            <span class="material-icons text-3xl text-red-600 dark:text-red-400">error</span>
          </div>
          <h2 class="text-3xl font-bold text-charcoal dark:text-white">
            {{ $t('verify.verificationFailed') }}
          </h2>
          <p class="text-charcoal/60 dark:text-white/60 leading-relaxed">
            {{ result.message }}
          </p>
          <router-link :to="{ name: 'Login' }">
            <AppButton variant="primary" class="w-full justify-center py-3.5 min-h-[44px]">
              {{ $t('verify.goToSignIn') }}
            </AppButton>
          </router-link>
        </div>
      </template>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { authService, normalizeApiError } from '@/services'

const { t } = useI18n()
const route = useRoute()
const loading = ref(true)
const result = ref(null)

const hasParams = computed(() => {
  const { uuid, hash, expires, signature } = route.query
  const uid = uuid ?? route.query.id
  return !!(uid && hash && expires && signature)
})

onMounted(async () => {
  if (!hasParams.value) {
    result.value = { success: false, message: t('verify.invalidLinkDefault') }
    loading.value = false
    return
  }
  try {
    const data = await authService.verifyEmail({
      uuid: route.query.uuid ?? route.query.id,
      hash: route.query.hash,
      expires: route.query.expires,
      signature: route.query.signature,
    })
    result.value = { success: true, message: data.message ?? t('verify.verifySuccessDefault') }
  } catch (e) {
    const { message } = normalizeApiError(e)
    result.value = { success: false, message: message ?? t('verify.invalidExpiredDefault') }
  } finally {
    loading.value = false
  }
})
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
