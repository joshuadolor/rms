<template>
  <AuthLayout>
    <div class="space-y-8 max-w-md mx-auto text-center">
      <div v-if="loading" class="space-y-4">
        <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 flex items-center justify-center">
          <span class="material-icons text-3xl text-primary animate-spin">sync</span>
        </div>
        <p class="text-charcoal/70 dark:text-white/70">Verifying your email…</p>
      </div>

      <template v-else-if="result">
        <div
          v-if="result.success"
          class="space-y-4"
        >
          <div class="w-16 h-16 mx-auto rounded-full bg-sage/20 flex items-center justify-center">
            <span class="material-icons text-3xl text-sage">check_circle</span>
          </div>
          <h2 class="text-2xl font-bold text-charcoal dark:text-white">Email verified</h2>
          <p class="text-charcoal/60 dark:text-white/60">{{ result.message }}</p>
          <router-link :to="{ name: 'Login' }">
            <AppButton variant="primary" class="w-full justify-center py-3.5">Sign in</AppButton>
          </router-link>
        </div>

        <div
          v-else
          class="space-y-4"
        >
          <div class="w-16 h-16 mx-auto rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
            <span class="material-icons text-3xl text-red-600 dark:text-red-400">error</span>
          </div>
          <h2 class="text-2xl font-bold text-charcoal dark:text-white">Verification failed</h2>
          <p class="text-charcoal/60 dark:text-white/60">{{ result.message }}</p>
          <router-link :to="{ name: 'Login' }">
            <AppButton variant="primary" class="w-full justify-center py-3.5">Go to sign in</AppButton>
          </router-link>
        </div>
      </template>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { authService, normalizeApiError } from '@/services'

const route = useRoute()
const loading = ref(true)
const result = ref(null)

const hasParams = computed(() => {
  const { id, hash, expires, signature } = route.query
  return !!(id && hash && expires && signature)
})

onMounted(async () => {
  if (!hasParams.value) {
    result.value = { success: false, message: 'Invalid or missing verification link. Please use the link from your email.' }
    loading.value = false
    return
  }
  try {
    const data = await authService.verifyEmail({
      id: route.query.id,
      hash: route.query.hash,
      expires: route.query.expires,
      signature: route.query.signature,
    })
    result.value = { success: true, message: data.message ?? 'You can sign in.' }
  } catch (e) {
    const { message } = normalizeApiError(e)
    result.value = { success: false, message: message ?? 'Invalid or expired link. Please request a new verification email.' }
  } finally {
    loading.value = false
  }
})
</script>
