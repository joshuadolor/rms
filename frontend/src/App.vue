<template>
  <div v-if="appLoading" class="min-h-screen bg-background-light dark:bg-background-dark flex items-center justify-center font-display">
    <div class="text-center">
      <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 flex items-center justify-center mb-4">
        <span class="material-icons text-3xl text-primary animate-spin" aria-hidden="true">sync</span>
      </div>
      <p class="text-charcoal/60 dark:text-white/60">Loading…</p>
    </div>
  </div>
  <div v-else>
    <ToastContainer />
    <router-view />
    <PaidFeatureModal />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRoute, useRouter } from 'vue-router'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import PaidFeatureModal from '@/components/ui/PaidFeatureModal.vue'

const appStore = useAppStore()
const route = useRoute()
const router = useRouter()

const isProtectedRoute = computed(() => route.matched.some((r) => r.meta.requiresAuth || r.meta.requiresVerified || r.meta.requiresSuperadmin))
const appLoading = computed(() => isProtectedRoute.value && appStore.isAuthBootstrapping && !appStore.isAuthBootstrapped)

onMounted(async () => {
  // Ensure we attempt refresh-on-boot even on public routes.
  // Protected routes will still block via router guards; guest routes can render immediately.
  appStore.bootstrapAuth().catch(() => {})
})

// If we soft-loaded a guest/public page but bootstrap later restores auth,
// redirect away from guest routes without requiring a full reload.
watch(
  () => [appStore.isAuthBootstrapped, appStore.user?.uuid, route.fullPath],
  () => {
    if (!appStore.isAuthBootstrapped) return
    if (!route.matched.some((r) => r.meta.guest)) return
    if (route.name === 'PublicRestaurant') return
    if (!appStore.user) return
    const isVerified = appStore.user?.isEmailVerified ?? false
    if (isVerified) {
      router.replace({ name: 'App' })
      return
    }
    const email = appStore.user.email
    router.replace(email ? { name: 'VerifyEmail', query: { email } } : { name: 'VerifyEmail' })
  }
)
</script>
