<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { authService } from '@/services'

const router = useRouter()

onMounted(async () => {
  if (!localStorage.getItem('rms-auth-token')) return
  const appStore = useAppStore()
  try {
    const data = await authService.getMe()
    if (data?.user) appStore.setUserFromApi(data.user)
  } catch {
    await appStore.logout()
    router.replace({ name: 'Landing' })
  }
})
</script>
