import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Whether the current page shows its own FAB (e.g. Add menu/category).
 * Views with a FAB set this to true on mount and false on unmount
 * so the layout can position the Help FAB (e.g. bottom-right when no page FAB, left of page FAB when present).
 */
export const usePageFabStore = defineStore('pageFab', () => {
  const hasPageFab = ref(false)

  function setPageFab(value) {
    hasPageFab.value = !!value
  }

  return { hasPageFab, setPageFab }
})
