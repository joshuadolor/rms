import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Context for breadcrumb labels that depend on loaded data (e.g. restaurant name, menu item name).
 * Views set these when they load; breadcrumb component reads them.
 */
export const useBreadcrumbStore = defineStore('breadcrumb', () => {
  const restaurantName = ref(null)
  const menuName = ref(null)
  const categoryName = ref(null)
  const menuItemName = ref(null)

  function setRestaurantName(name) {
    restaurantName.value = name ?? null
  }

  function setMenuName(name) {
    menuName.value = name ?? null
  }

  function setCategoryName(name) {
    categoryName.value = name ?? null
  }

  function setMenuItemName(name) {
    menuItemName.value = name ?? null
  }

  function clearRestaurant() {
    restaurantName.value = null
    menuName.value = null
    categoryName.value = null
    menuItemName.value = null
  }

  function clearMenuAndCategory() {
    menuName.value = null
    categoryName.value = null
  }

  function clearMenuItem() {
    menuItemName.value = null
  }

  return {
    restaurantName,
    menuName,
    categoryName,
    menuItemName,
    setRestaurantName,
    setMenuName,
    setCategoryName,
    setMenuItemName,
    clearRestaurant,
    clearMenuAndCategory,
    clearMenuItem,
  }
})
