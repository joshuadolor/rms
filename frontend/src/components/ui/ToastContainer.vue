<template>
  <div
    class="fixed bottom-0 left-0 right-0 w-full p-3 pb-[env(safe-area-inset-bottom,0.75rem)] mb-4 sm:mb-0 z-[200] flex flex-col gap-2 pointer-events-none sm:bottom-auto sm:top-4 sm:right-4 sm:left-auto sm:w-auto sm:max-w-sm sm:p-0 sm:pb-0"
    aria-live="polite"
    role="region"
    aria-label="Notifications"
  >
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg border text-sm font-medium w-full sm:w-auto"
        :class="toastClasses(toast.type)"
        role="status"
      >
        <span class="material-icons text-lg shrink-0" aria-hidden="true">{{ iconFor(toast.type) }}</span>
        <p class="flex-1 min-w-0">{{ toast.message }}</p>
        <button
          type="button"
          class="shrink-0 p-1 rounded opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-offset-1"
          aria-label="Dismiss"
          @click="toastStore.remove(toast.id)"
        >
          <span class="material-icons text-lg">close</span>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

function toastClasses(type) {
  const base = 'border'
  const byType = {
    success: 'border-green-200 dark:border-green-800/50 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200',
    error: 'border-red-200 dark:border-red-800/50 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200',
    info: 'border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200',
  }
  return `${base} ${byType[type] ?? byType.info}`
}

function iconFor(type) {
  const icons = { success: 'check_circle', error: 'error', info: 'info' }
  return icons[type] ?? 'info'
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(1rem);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(1rem);
}
@media (min-width: 640px) {
  .toast-enter-from,
  .toast-leave-to {
    transform: translateX(1rem);
  }
}
.toast-move {
  transition: transform 0.2s ease;
}
</style>
