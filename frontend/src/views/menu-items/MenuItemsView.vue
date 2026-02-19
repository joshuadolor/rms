<template>
  <div>
    <div v-if="listLoading" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menu items</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Add and edit items. You can add them to menu categories inside each restaurant.</p>
      </header>

      <div
        v-if="error"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <!-- Empty list -->
      <div
        v-if="!menuItems.length"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sage/15 via-cream/80 to-primary/5 dark:from-sage/20 dark:via-zinc-900 dark:to-primary/10 border border-sage/20 dark:border-sage/30 py-12 lg:py-16 px-6 text-center"
      >
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
          <div class="absolute top-1/4 left-1/4 w-32 h-32 rounded-full border-2 border-sage/20 dark:border-sage/30 -translate-x-1/2 -translate-y-1/2" />
          <div class="absolute bottom-1/4 right-1/4 w-24 h-24 rounded-full border-2 border-primary/15 -translate-x-1/2 translate-y-1/2" />
        </div>
        <div class="relative">
          <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mb-2">Add your first menu item</h3>
          <p class="text-slate-600 dark:text-slate-400 text-sm max-w-md mx-auto mb-8">
            Create dishes, drinks, and more. Add them to categories inside each restaurant’s Menu tab.
          </p>
          <router-link to="/app/menu-items/new">
            <AppButton variant="primary" class="min-h-[48px] px-6 shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02] active:scale-[0.98]">
              <template #icon>
                <span class="material-icons">add</span>
              </template>
              Add menu item
            </AppButton>
          </router-link>
        </div>
      </div>

      <!-- Simple list: no drag, no reorder -->
      <ul v-else class="space-y-2">
        <li
          v-for="item in menuItems"
          :key="item.uuid"
          class="flex items-center gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 min-h-[44px]"
        >
          <div class="min-w-0 flex-1">
            <p class="font-medium text-charcoal dark:text-white truncate">{{ itemName(item) }}</p>
            <p v-if="itemDescription(item)" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ itemDescription(item) }}</p>
            <p v-if="itemPrice(item) != null" class="text-sm font-medium text-slate-600 dark:text-slate-300 mt-0.5">{{ formatPrice(itemPrice(item)) }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <router-link :to="editLink(item)">
              <AppButton variant="ghost" size="sm" class="min-h-[44px] min-w-[44px]" aria-label="Edit menu item">
                <span class="material-icons">edit</span>
              </AppButton>
            </router-link>
            <AppButton
              variant="ghost"
              size="sm"
              class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
              aria-label="Delete menu item"
              :disabled="deletingUuid === item.uuid"
              :data-testid="`delete-menu-item-${item.uuid}`"
              @click="confirmDeleteItem(item)"
            >
              <span v-if="deletingUuid === item.uuid" class="material-icons animate-spin">sync</span>
              <span v-else class="material-icons">delete_outline</span>
            </AppButton>
          </div>
        </li>
      </ul>

      <!-- Delete confirmation modal -->
      <AppModal
        :open="deleteModalOpen"
        title="Delete menu item"
        description="This cannot be undone. The item will be removed from all restaurants that use it."
        @close="closeDeleteModal"
      >
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeDeleteModal">Cancel</AppButton>
          <AppButton
            variant="primary"
            class="min-h-[44px] bg-red-600 hover:bg-red-700"
            :disabled="deletingUuid !== null"
            data-testid="menu-items-delete-confirm"
            @click="doDeleteItem"
          >
            <template v-if="deletingUuid" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
            {{ deletingUuid ? 'Deleting…' : 'Delete' }}
          </AppButton>
        </template>
      </AppModal>

      <router-link
        v-if="!listLoading"
        :to="{ name: 'MenuItemNew' }"
        class="fixed right-6 bottom-6 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center min-h-[56px] min-w-[56px] z-20"
        aria-label="Add menu item"
      >
        <span class="material-icons text-3xl">add</span>
      </router-link>
      <div class="h-20" aria-hidden="true" />
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import { menuItemService, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()
const listLoading = ref(true)
const menuItems = ref([])
const error = ref('')
const deleteModalOpen = ref(false)
const itemToDelete = ref(null)
const deletingUuid = ref(null)

function defaultLocale(item) {
  const locs = item?.translations ? Object.keys(item.translations) : []
  return locs[0] ?? 'en'
}

function itemName(item) {
  const locale = defaultLocale(item)
  const t = item?.translations?.[locale]
  return t?.name ?? '—'
}

function itemDescription(item) {
  const locale = defaultLocale(item)
  const t = item?.translations?.[locale]
  return t?.description ?? ''
}

function itemPrice(item) {
  if (item?.price != null) return Number(item.price)
  return null
}

function formatPrice(num) {
  if (num == null || Number.isNaN(num)) return ''
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(num)
}

function editLink(item) {
  const query = item.restaurant_uuid ? { restaurant: item.restaurant_uuid } : {}
  return { name: 'MenuItemEdit', params: { itemUuid: item.uuid }, query }
}

async function loadMenuItems() {
  listLoading.value = true
  error.value = ''
  try {
    const res = await menuItemService.list()
    menuItems.value = res.data ?? []
  } catch (e) {
    error.value = normalizeApiError(e).message
    menuItems.value = []
  } finally {
    listLoading.value = false
  }
}

function confirmDeleteItem(item) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

function closeDeleteModal() {
  if (deletingUuid.value) return
  deleteModalOpen.value = false
  itemToDelete.value = null
}

async function doDeleteItem() {
  const item = itemToDelete.value
  if (!item?.uuid || deletingUuid.value) return
  deletingUuid.value = item.uuid
  error.value = ''
  try {
    await menuItemService.delete(item.uuid)
    toastStore.success('Menu item deleted.')
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadMenuItems()
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    deletingUuid.value = null
  }
}

onMounted(loadMenuItems)
</script>
