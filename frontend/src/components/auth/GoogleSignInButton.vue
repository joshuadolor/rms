<template>
  <div v-if="clientId" ref="containerRef" class="google-signin-container" />
  <button
    v-else
    type="button"
    class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-primary/20 rounded-lg bg-white dark:bg-zinc-800 text-charcoal dark:text-white opacity-60 cursor-not-allowed font-medium"
    disabled
  >
    <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.66l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
    </svg>
    Continue with Google (configure client ID)
  </button>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'

const props = defineProps({
  clientId: { type: String, default: () => import.meta.env.VITE_GOOGLE_CLIENT_ID ?? '' },
})

const emit = defineEmits(['success', 'error'])

const containerRef = ref(null)
const clientId = computed(() => props.clientId || import.meta.env.VITE_GOOGLE_CLIENT_ID || '')

onMounted(() => {
  if (!clientId.value || !containerRef.value) return

  const doRender = () => {
    if (typeof window === 'undefined' || !window.google?.accounts?.id) return
    try {
      window.google.accounts.id.initialize({
        client_id: clientId.value,
        callback: (response) => {
          if (response?.credential) emit('success', { credential: response.credential })
          else if (response?.error) emit('error', response.error)
        },
      })
      const w = containerRef.value?.offsetWidth || 320
      window.google.accounts.id.renderButton(containerRef.value, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        width: w,
        text: 'continue_with',
        shape: 'rectangular',
      })
    } catch (e) {
      console.warn('[GoogleSignInButton]', e)
      emit('error', e)
    }
  }

  if (window.google?.accounts?.id) {
    doRender()
  } else {
    const t = setInterval(() => {
      if (window.google?.accounts?.id) {
        clearInterval(t)
        doRender()
      }
    }, 100)
    setTimeout(() => clearInterval(t), 8000)
  }
})
</script>

<style scoped>
.google-signin-container {
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.google-signin-container :deep(iframe) {
  margin: 0 auto;
}
</style>
