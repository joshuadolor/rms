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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { authService } from '@/services'
import ToastContainer from '@/components/ui/ToastContainer.vue'

const router = useRouter()
const appLoading = ref(false)

onMounted(async () => {
  if (!localStorage.getItem('rms-auth-token')) return
  appLoading.value = true
  const appStore = useAppStore()
  try {
    const data = await authService.getMe()
    if (data?.user) appStore.setUserFromApi(data.user)
  } catch {
    await appStore.logout()
    router.replace({ name: 'Landing' })
  } finally {
    appLoading.value = false
  }
})
</script>
