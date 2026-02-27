import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Store for the "paid feature required" modal (e.g. AI image beautification).
 * Used by v-require-paid directive: when a user clicks an element with that directive
 * and is not a paid user, the directive opens this modal instead of proceeding.
 */
export const usePaidFeatureStore = defineStore('paidFeature', () => {
  const visible = ref(false)

  function open() {
    visible.value = true
  }

  function close() {
    visible.value = false
  }

  return {
    visible,
    open,
    close,
  }
})
