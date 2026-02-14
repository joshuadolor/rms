<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark font-display">
    <header class="border-b border-primary/10 bg-white dark:bg-zinc-900 px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
          <span class="material-icons text-white text-lg">restaurant_menu</span>
        </div>
        <span class="font-bold text-xl tracking-tight text-charcoal dark:text-white">RMS</span>
      </div>
      <button
        type="button"
        class="text-charcoal/70 dark:text-gray-300 hover:text-primary font-medium flex items-center gap-2"
        @click="handleLogout"
      >
        <span class="material-icons text-lg">logout</span>
        Sign out
      </button>
    </header>
    <main class="max-w-md mx-auto px-6 py-12">
      <h1 class="text-2xl font-bold text-charcoal dark:text-white mb-2">Welcome, {{ user?.fullName }}.</h1>
      <p class="text-charcoal/60 dark:text-white/60">Signed in as {{ user?.email }}</p>
    </main>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const appStore = useAppStore()
const { user } = storeToRefs(appStore)

function handleLogout() {
  appStore.logout()
  router.push({ name: 'Landing' })
}
</script>
