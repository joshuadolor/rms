import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])

  /**
   * Show a toast. Auto-dismisses after duration (default 5s).
   * @param {{ message: string, type?: 'success'|'error'|'info', duration?: number }} options
   */
  function show(options) {
    const { message, type = 'success', duration = 5000 } = typeof options === 'string' ? { message: options } : options
    const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const toast = { id, message, type }
    toasts.value = [...toasts.value, toast]

    if (duration > 0) {
      setTimeout(() => {
        remove(id)
      }, duration)
    }

    return id
  }

  function success(message) {
    return show({ message, type: 'success' })
  }

  function error(message) {
    return show({ message, type: 'error' })
  }

  function info(message) {
    return show({ message, type: 'info' })
  }

  function remove(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { toasts, show, success, error, info, remove }
})
