<template>
  <div>
    <div v-if="loading && !restaurant" class="space-y-4">
      <div class="h-24 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!restaurant"
      class="rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink :to="backLink" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8">
        <AppBackLink :to="backLink" />
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mt-3">
          {{ categoryDisplayName }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Add and reorder menu items in this category. Drag to change order.
        </p>
      </header>

      <div
        v-if="error"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <!-- Empty state: no white card when no items -->
      <div
        v-if="!itemsLoading && !items.length"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sage/15 via-cream/80 to-primary/5 dark:from-sage/20 dark:via-zinc-900 dark:to-primary/10 border border-sage/20 dark:border-sage/30 py-12 lg:py-16 px-6 text-center"
      >
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
          <div class="absolute top-1/4 left-1/4 w-32 h-32 rounded-full border-2 border-sage/20 dark:border-sage/30 -translate-x-1/2 -translate-y-1/2" />
          <div class="absolute bottom-1/4 right-1/4 w-24 h-24 rounded-full border-2 border-primary/15 -translate-x-1/2 translate-y-1/2" />
        </div>
        <div class="relative">
          <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mb-2">Add menu items to this category</h3>
          <p class="text-slate-600 dark:text-slate-400 text-sm max-w-md mx-auto mb-8">
            Add existing menu items from this restaurant to show them in this category. You can reorder them after adding.
          </p>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[48px] px-6 shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02] active:scale-[0.98]"
            @click="openAddItemModal"
          >
            <template #icon>
              <span class="material-icons">restaurant_menu</span>
            </template>
            Add menu item
          </AppButton>
        </div>
      </div>

      <!-- Loading items (no white card when empty) -->
      <div v-else-if="itemsLoading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-16 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      </div>

      <!-- Has items: white card with count, Add button, draggable list -->
      <div
        v-else
        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-4 lg:p-6 space-y-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ items.length }} item(s)</span>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[44px] w-full sm:w-auto"
            @click="openAddItemModal"
          >
            <template #icon>
              <span class="material-icons">add</span>
            </template>
            Add menu item
          </AppButton>
        </div>
        <draggable
          v-model="items"
          item-key="uuid"
          handle=".item-drag-handle"
          class="space-y-2"
          :animation="200"
          @end="onReorderItems"
        >
          <template #item="{ element: item }">
            <li class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-slate-700 shadow-sm list-none min-h-[44px]">
              <button
                type="button"
                class="item-drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center touch-none"
                aria-label="Drag to reorder"
              >
                <span class="material-icons">drag_indicator</span>
              </button>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">{{ itemDisplayName(item) }}</p>
                <p v-if="itemPriceDisplay(item)" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(item) }}</p>
              </div>
              <router-link
                :to="{ name: 'RestaurantMenuItemEdit', params: { uuid: restaurant.uuid, itemUuid: item.uuid } }"
                class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-300 shrink-0"
                aria-label="Edit menu item"
              >
                <span class="material-icons">edit</span>
              </router-link>
            </li>
          </template>
        </draggable>
      </div>

      <!-- Add menu item to category: single list of addable items, each with Add button -->
      <AppModal
        :open="addItemModalOpen"
        title="Add menu item to category"
        description="Add or remove items in this category. You can add from this restaurant or from your catalog. Search to filter."
        @close="closeAddItemModal"
      >
        <div class="space-y-4">
          <AppInput
            v-model="addItemSearchQuery"
            type="search"
            label="Search"
            placeholder="Filter by name…"
            autocomplete="off"
          />
          <p v-if="addItemModalError" role="alert" class="text-sm text-red-600 dark:text-red-400">
            {{ addItemModalError }}
          </p>
          <div v-if="addItemModalLoading" class="py-6 text-center text-slate-500 dark:text-slate-400 text-sm">
            Loading menu items…
          </div>
          <div v-else class="max-h-[50vh] overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700">
            <div
              v-for="menuItem in addItemFilteredList"
              :key="(menuItem._addSource ?? 'restaurant') + '-' + menuItem.uuid"
              class="flex items-center gap-3 p-3 min-h-[44px]"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">{{ itemDisplayName(menuItem) }}</p>
                <p v-if="itemPriceDisplay(menuItem)" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(menuItem) }}</p>
              </div>
              <template v-if="isItemInCategory(menuItem)">
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="min-h-[44px] shrink-0"
                  :disabled="addItemRemovingUuid === menuItem.uuid"
                  :aria-label="`Remove ${itemDisplayName(menuItem)} from category`"
                  :data-testid="`remove-item-from-category-${menuItem.uuid}`"
                  @click="removeItemFromCategory(menuItem)"
                >
                  <span v-if="addItemRemovingUuid === menuItem.uuid" class="material-icons animate-spin text-lg">sync</span>
                  <span v-else class="material-icons text-lg">remove_circle_outline</span>
                  <span class="ml-1">Remove from menu</span>
                </AppButton>
              </template>
              <AppButton
                v-else
                variant="primary"
                size="sm"
                class="min-h-[44px] min-w-[44px] shrink-0"
                :disabled="addItemAddingUuid === menuItem.uuid"
                :aria-label="`Add ${itemDisplayName(menuItem)} to category`"
                :data-testid="`add-item-to-category-${menuItem.uuid}`"
                @click="addItemToCategory(menuItem)"
              >
                <span v-if="addItemAddingUuid === menuItem.uuid" class="material-icons animate-spin text-lg">sync</span>
                <span v-else class="material-icons text-lg">add</span>
              </AppButton>
            </div>
            <p v-if="!addItemModalLoading && addItemFilteredList.length === 0" class="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">
              No menu items, or none match the search.
            </p>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeAddItemModal">Done</AppButton>
        </template>
      </AppModal>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import Restaurant from '@/models/Restaurant.js'
import MenuItem from '@/models/MenuItem.js'
import { formatCurrency } from '@/utils/format'
import { restaurantService, menuItemService, normalizeApiError } from '@/services'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()

const uuid = computed(() => route.params.uuid)
const categoryUuid = computed(() => route.params.categoryUuid)
const categoryNameFromQuery = computed(() => route.query.name ?? null)

const loading = ref(true)
const itemsLoading = ref(false)
const restaurant = ref(null)
const items = ref([])
const error = ref('')

const defaultLocale = computed(() => restaurant.value?.default_locale ?? 'en')

const backLink = computed(() =>
  restaurant.value
    ? { name: 'RestaurantDetail', params: { uuid: restaurant.value.uuid }, query: { tab: 'menu' } }
    : { name: 'Restaurants' }
)

const categoryDisplayName = computed(() => {
  const name = categoryNameFromQuery.value
  if (name && typeof name === 'string') return decodeURIComponent(name)
  return 'Menu items'
})

// Add-item modal state
const addItemModalOpen = ref(false)
const addItemSearchQuery = ref('')
const addItemModalLoading = ref(false)
const addItemModalError = ref('')
const addItemAddingUuid = ref(null)
const addItemRemovingUuid = ref(null)
/** Items for modal: restaurant items + catalog (standalone) items not already in this restaurant. */
const allMenuItemsForModal = ref([])

/** Restaurant items already in this category (for sort_order when adding). */
const addItemInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  return allMenuItemsForModal.value.filter(
    (i) => i._addSource === 'restaurant' && (i.category_uuid ?? null) === cat
  )
})

/** Restaurant items not in this category (other categories or uncategorized). */
const addItemNotInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  return allMenuItemsForModal.value.filter(
    (i) => i._addSource === 'restaurant' && (i.category_uuid ?? null) !== cat
  )
})

/** Catalog (standalone) items not yet in this restaurant (deduped by source_menu_item_uuid). */
const addItemCatalogOnly = computed(() => {
  return allMenuItemsForModal.value.filter((i) => i._addSource === 'catalog')
})

/** All items for modal: addable (restaurant not in category + catalog), then in-category (Remove from menu). */
const addItemFullList = computed(() => {
  const notIn = addItemNotInCategory.value
  const catalog = addItemCatalogOnly.value
  const inCat = addItemInCategory.value
  return [...notIn, ...catalog, ...inCat]
})

/** Modal list filtered by search; each row shows Add or Remove from menu. */
const addItemFilteredList = computed(() => {
  const list = addItemFullList.value
  const q = (addItemSearchQuery.value ?? '').trim().toLowerCase()
  if (!q) return list
  return list.filter((item) => {
    const name = itemDisplayName(item)
    return name.toLowerCase().includes(q)
  })
})

function isItemInCategory(menuItem) {
  return menuItem?._addSource === 'restaurant' && (menuItem.category_uuid ?? null) === categoryUuid.value
}

function openAddItemModal() {
  addItemSearchQuery.value = ''
  addItemModalError.value = ''
  addItemAddingUuid.value = null
  addItemModalOpen.value = true
  fetchAllMenuItemsForModal()
}

function closeAddItemModal() {
  addItemModalOpen.value = false
  addItemModalError.value = ''
  addItemAddingUuid.value = null
  addItemRemovingUuid.value = null
}

async function fetchAllMenuItemsForModal() {
  if (!uuid.value) return
  addItemModalLoading.value = true
  addItemModalError.value = ''
  try {
    const [restaurantRes, userRes] = await Promise.all([
      restaurantService.listMenuItems(uuid.value),
      menuItemService.list(),
    ])
    const restaurantList = Array.isArray(restaurantRes?.data) ? restaurantRes.data : []
    const userList = Array.isArray(userRes?.data) ? userRes.data : []
    const restaurantItems = restaurantList.map((i) => {
      const json = MenuItem.fromApi({ data: i }).toJSON()
      json._addSource = 'restaurant'
      return json
    })
    const sourceUuids = new Set(
      restaurantItems.map((i) => i.source_menu_item_uuid).filter(Boolean)
    )
    const catalogItems = userList
      .filter((i) => (i.restaurant_uuid ?? null) === null && !sourceUuids.has(i.uuid))
      .map((i) => {
        const json = MenuItem.fromApi({ data: i }).toJSON()
        json._addSource = 'catalog'
        return json
      })
    allMenuItemsForModal.value = [...restaurantItems, ...catalogItems]
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
    allMenuItemsForModal.value = []
  } finally {
    addItemModalLoading.value = false
  }
}

async function addItemToCategory(menuItem) {
  if (!uuid.value || !categoryUuid.value || !menuItem?.uuid || addItemAddingUuid.value) return
  addItemAddingUuid.value = menuItem.uuid
  addItemModalError.value = ''
  const nextSortOrder = addItemInCategory.value.length
  const isCatalog = menuItem._addSource === 'catalog'
  try {
    if (isCatalog) {
      await restaurantService.createMenuItem(uuid.value, {
        source_menu_item_uuid: menuItem.uuid,
        category_uuid: categoryUuid.value,
        sort_order: nextSortOrder,
      })
    } else {
      await restaurantService.updateMenuItem(uuid.value, menuItem.uuid, {
        category_uuid: categoryUuid.value,
        sort_order: nextSortOrder,
      })
    }
    restaurantService.invalidateMenuItemsCache(uuid.value)
    await loadItems()
    await fetchAllMenuItemsForModal()
    toastStore.success('Menu item added to category.')
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
  } finally {
    addItemAddingUuid.value = null
  }
}

async function removeItemFromCategory(menuItem) {
  if (!uuid.value || !categoryUuid.value || !menuItem?.uuid || addItemRemovingUuid.value) return
  addItemRemovingUuid.value = menuItem.uuid
  addItemModalError.value = ''
  try {
    await restaurantService.updateMenuItem(uuid.value, menuItem.uuid, {
      category_uuid: null,
      sort_order: 0,
    })
    restaurantService.invalidateMenuItemsCache(uuid.value)
    await loadItems()
    await fetchAllMenuItemsForModal()
    toastStore.success('Removed from category.')
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
  } finally {
    addItemRemovingUuid.value = null
  }
}

function itemDisplayName(item) {
  const loc = defaultLocale.value
  const t = item?.translations?.[loc] ?? item?.translations?.en ?? {}
  return t?.name?.trim() || '—'
}

function itemPriceDisplay(item) {
  const p = item?.price
  if (p == null || p === '') return ''
  const n = Number(p)
  if (Number.isNaN(n)) return ''
  const currency = restaurant.value?.currency ?? 'USD'
  return formatCurrency(n, currency)
}

async function loadRestaurant() {
  if (!uuid.value) return
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    if (restaurant.value) {
      breadcrumbStore.setRestaurantName(restaurant.value.name ?? null)
      breadcrumbStore.setMenuName(route.query.menu ?? null)
      breadcrumbStore.setCategoryName((categoryDisplayName.value || route.query.name) ?? null)
    }
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadItems() {
  if (!uuid.value || !categoryUuid.value) return
  itemsLoading.value = true
  error.value = ''
  try {
    const res = await restaurantService.listMenuItems(uuid.value)
    const list = Array.isArray(res?.data) ? res.data : []
    items.value = list
      .filter((i) => (i.category_uuid ?? null) === categoryUuid.value)
      .map((i) => MenuItem.fromApi({ data: i }).toJSON())
  } catch (e) {
    error.value = normalizeApiError(e).message
    items.value = []
  } finally {
    itemsLoading.value = false
  }
}

async function onReorderItems() {
  if (!uuid.value || !categoryUuid.value) return
  const order = items.value.map((i) => i.uuid)
  try {
    await restaurantService.reorderMenuItems(uuid.value, categoryUuid.value, order)
    error.value = ''
    toastStore.success('Order updated.')
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadItems()
  }
}

onMounted(async () => {
  await loadRestaurant()
  if (restaurant.value) await loadItems()
})

watch([uuid, categoryUuid], async () => {
  if (restaurant.value) await loadItems()
})

watch(
  () => [route.query.menu, route.query.name],
  () => {
    if (restaurant.value) {
      breadcrumbStore.setMenuName(route.query.menu ?? null)
      breadcrumbStore.setCategoryName((categoryDisplayName.value || route.query.name) ?? null)
    }
  },
  { immediate: false }
)
</script>
