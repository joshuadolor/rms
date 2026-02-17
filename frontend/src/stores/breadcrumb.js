import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Context for breadcrumb labels that depend on loaded data (e.g. restaurant name, menu item name).
 * Views set these when they load; breadcrumb component reads them.
 */
export const useBreadcrumbStore = defineStore('breadcrumb', () => {
  const restaurantName = ref(null)
  const menuItemName = ref(null)

  function setRestaurantName(name) {
    restaurantName.value = name ?? null
  }

  function setMenuItemName(name) {
    menuItemName.value = name ?? null
  }

  function clearRestaurant() {
    restaurantName.value = null
    menuItemName.value = null
  }

  function clearMenuItem() {
    menuItemName.value = null
  }

  return {
    restaurantName,
    menuItemName,
    setRestaurantName,
    setMenuItemName,
    clearRestaurant,
    clearMenuItem,
  }
})
