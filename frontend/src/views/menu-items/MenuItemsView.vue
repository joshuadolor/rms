<template>
  <div>
    <div v-if="listLoading" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.menuItems') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.menuItemsSubtitle') }}</p>
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
          <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mb-2">{{ $t('app.addFirstMenuItem') }}</h3>
          <p class="text-slate-600 dark:text-slate-400 text-sm max-w-md mx-auto mb-8">
            {{ $t('app.menuItemsEmptyHint') }}
          </p>
          <router-link to="/app/menu-items/new">
            <AppButton variant="primary" class="min-h-[48px] px-6 shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02] active:scale-[0.98]">
              <template #icon>
                <span class="material-icons">add</span>
              </template>
              {{ $t('app.createItem') }}
            </AppButton>
          </router-link>
        </div>
      </div>

      <!-- Simple list: no drag, no reorder -->
      <ul v-else class="space-y-2">
        <li
          v-for="item in menuItems"
          :key="item.uuid"
          :data-testid="`menu-item-row-${item.uuid}`"
          class="flex items-center gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 min-h-[44px]"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-medium text-charcoal dark:text-white truncate">{{ itemName(item) }}</p>
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium min-h-[24px]"
                :class="item.type === 'combo' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : item.type === 'with_variants' ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
              >
                {{ itemTypeLabel(item.type) }}
              </span>
            </div>
            <p v-if="itemDescription(item)" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ itemDescription(item) }}</p>
            <p v-if="itemPrice(item) != null" class="text-sm font-medium text-slate-600 dark:text-slate-300 mt-0.5">{{ formatPrice(itemPrice(item)) }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <router-link :to="editLink(item)" :title="$t('app.editItem')" :aria-label="$t('app.editItem')">
              <AppButton variant="ghost" size="sm" class="min-h-[44px] min-w-[44px]" :aria-label="$t('app.editItem')">
                <span class="material-icons">edit</span>
              </AppButton>
            </router-link>
            <AppButton
              variant="ghost"
              size="sm"
              class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
              :title="$t('app.deleteMenuItem')"
              :aria-label="$t('app.deleteMenuItem')"
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
        :title="$t('app.deleteMenuItem')"
        :description="$t('app.deleteMenuItemConfirm')"
        @close="closeDeleteModal"
      >
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeDeleteModal">{{ $t('app.cancel') }}</AppButton>
          <AppButton
            variant="primary"
            class="min-h-[44px] bg-red-600 hover:bg-red-700"
            :disabled="deletingUuid !== null"
            data-testid="menu-items-delete-confirm"
            @click="doDeleteItem"
          >
            <template v-if="deletingUuid" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
            {{ deletingUuid ? $t('app.deleting') : $t('app.delete') }}
          </AppButton>
        </template>
      </AppModal>

      <router-link
        v-if="!listLoading"
        :to="{ name: 'MenuItemNew' }"
        class="fixed right-6 bottom-6 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center min-h-[56px] min-w-[56px] z-20"
        :title="$t('app.createItem')"
        :aria-label="$t('app.createItem')"
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
import MenuItem from '@/models/MenuItem.js'
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
  if (item?.effectiveName) return item.effectiveName(defaultLocale(item))
  const locale = defaultLocale(item)
  const t = item?.translations?.[locale]
  return t?.name ?? '—'
}

function itemDescription(item) {
  if (item?.effectiveDescription) return item.effectiveDescription(defaultLocale(item))
  const locale = defaultLocale(item)
  const t = item?.translations?.[locale]
  return t?.description ?? ''
}

function itemPrice(item) {
  if (item?.effectivePrice != null) return item.effectivePrice
  if (item?.price != null) return Number(item.price)
  return null
}

function itemTypeLabel(type) {
  if (type === 'combo') return 'Combo'
  if (type === 'with_variants') return 'With variants'
  return 'Simple'
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
    const raw = res.data ?? []
    menuItems.value = raw.map((item) => MenuItem.fromApi({ data: item }))
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
