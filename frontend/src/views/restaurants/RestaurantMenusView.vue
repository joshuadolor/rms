<template>
  <div>
    <div v-if="loading && !restaurant" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <div>
          <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menus &amp; categories</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage menus (active = shown on public site) and categories. Drag to reorder.</p>
        </div>

        <div class="mt-4 space-y-2">
          <label class="block text-sm font-semibold text-charcoal dark:text-white">Menu</label>
          <div v-if="menusLoading" class="h-12 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse w-full max-w-xs" />
          <template v-else-if="!menus.length">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-2">Create your first menu to add categories.</p>
            <AppButton variant="primary" class="min-h-[44px]" :disabled="creatingMenu" @click="createFirstMenu">
              <template v-if="creatingMenu" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
              {{ creatingMenu ? 'Creating…' : 'Create menu' }}
            </AppButton>
          </template>
          <div v-else class="flex flex-col gap-2">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
              <select
                v-model="selectedMenuUuid"
                class="min-h-[44px] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none w-full max-w-xs"
                aria-label="Select menu"
              >
                <option v-for="m in menus" :key="m.uuid" :value="m.uuid">
                  {{ m.name || 'Unnamed menu' }} {{ m.is_active ? '' : '(inactive)' }}
                </option>
              </select>
              <div class="flex items-center gap-2 flex-wrap">
                <button
                  v-if="selectedMenu"
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-xl border transition-colors"
                  :class="selectedMenu.is_active ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-slate-700 text-slate-500'"
                  :title="selectedMenu.is_active ? 'Hide menu on public site' : 'Show menu on public site'"
                  :aria-label="(selectedMenu.is_active ? 'Hide' : 'Show') + ' ' + (selectedMenu.name || 'menu') + ' on public site'"
                  @click="toggleMenuActive(selectedMenu)"
                >
                  <span class="material-icons text-xl">{{ selectedMenu.is_active ? 'visibility' : 'visibility_off' }}</span>
                </button>
                <AppButton
                  v-if="selectedMenu"
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] min-w-[44px] shrink-0 p-0"
                  title="Rename menu"
                  aria-label="Rename menu"
                  @click="openEditMenuModal"
                >
                  <span class="material-icons" aria-hidden="true">edit</span>
                </AppButton>
                <AppButton variant="secondary" class="min-h-[44px] shrink-0" @click="openAddMenuModal">
                  <span class="material-icons mr-1">add</span>
                  Add menu
                </AppButton>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div
        v-if="error"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <!-- Categories box: list + Add menu item (only when there is at least one category) -->
      <div v-if="menus.length" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-4 lg:p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-charcoal dark:text-white">Categories</h3>
          <span class="text-xs font-medium text-slate-500 bg-slate-100 dark:bg-zinc-700 px-2 py-1 rounded-full">{{ categories.length }} total</span>
        </div>
        <div v-if="categoriesLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-16 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
        </div>
        <div v-else-if="!categories.length" class="py-6 text-center">
          <p class="text-slate-500 dark:text-slate-400 mb-2">No categories yet. Use the + button below to add one.</p>
          <p class="text-sm text-slate-500 dark:text-slate-400">Create a category first to add menu items to this menu.</p>
        </div>
        <draggable
          v-else
          v-model="categories"
          item-key="uuid"
          handle=".drag-handle"
          class="space-y-2"
          :animation="200"
          @end="onReorderCategories"
        >
          <template #item="{ element: cat }">
            <li class="flex flex-wrap items-center gap-3 p-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-slate-700 shadow-sm list-none">
              <button
                type="button"
                class="drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center touch-none shrink-0"
                title="Drag to reorder"
                aria-label="Drag to reorder"
              >
                <span class="material-icons">drag_indicator</span>
              </button>
              <div class="min-w-0 flex-1 basis-0 sm:basis-auto">
                <p class="font-semibold text-charcoal dark:text-white truncate" :title="categoryName(cat)">{{ categoryName(cat) }}</p>
                <p v-if="cat.is_active === false" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Hidden on public menu</p>
              </div>
              <div class="flex flex-wrap items-center gap-2 shrink-0 w-full min-w-0 sm:w-auto">
                <router-link
                  v-if="restaurant"
                  :to="categoryItemsLink(cat)"
                  class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-3 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-300 text-sm font-medium shrink-0"
                  aria-label="Manage menu items in this category"
                >
                  <span class="material-icons text-lg">restaurant_menu</span>
                  Manage items
                </router-link>
                <button
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-xl border transition-colors shrink-0"
                  :class="cat.is_active !== false ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-slate-700 text-slate-500'"
                  :title="(cat.is_active !== false) ? 'Hide category on public menu' : 'Show category on public menu'"
                  :aria-label="(cat.is_active !== false ? 'Hide' : 'Show') + ' category on public menu'"
                  @click="toggleCategoryActive(cat)"
                >
                  <span class="material-icons text-xl">{{ cat.is_active !== false ? 'visibility' : 'visibility_off' }}</span>
                </button>
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] min-w-[44px] shrink-0"
                  title="Availability"
                  aria-label="Set category availability"
                  @click="openAvailabilityModal(cat)"
                >
                  <span class="material-icons">schedule</span>
                </AppButton>
                <AppButton variant="ghost" size="sm" class="min-h-[44px] min-w-[44px] shrink-0" title="Edit category" aria-label="Edit category" @click="openCategoryModal(cat)">
                  <span class="material-icons">edit</span>
                </AppButton>
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] min-w-[44px] shrink-0 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                  title="Remove category"
                  aria-label="Remove category"
                  @click="openDeleteCategoryModal(cat)"
                >
                  <span class="material-icons">delete</span>
                </AppButton>
              </div>
            </li>
          </template>
        </draggable>

        <!-- Manage menu items: inside categories box -->
        <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
          <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Add dishes to categories from each category's page. Item translations use this restaurant’s Settings.</p>
          <div v-if="!categories.length" class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-slate-500 dark:text-slate-400">Create a category above to add menu items.</span>
            <router-link
              to="/app/menu-items"
              class="inline-flex items-center justify-center gap-2 font-semibold rounded-lg py-2.5 px-4 text-sm min-h-[44px] border-2 border-charcoal/10 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-zinc-800 text-charcoal dark:text-white"
            >
              <span class="material-icons text-lg">restaurant_menu</span>
              Manage menu items
            </router-link>
          </div>
          <div v-else class="flex flex-wrap items-center gap-2">
            <router-link
              to="/app/menu-items"
              class="inline-flex items-center justify-center gap-2 font-semibold rounded-lg py-2.5 px-6 text-sm min-h-[44px] border-2 border-charcoal/10 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-zinc-800 text-charcoal dark:text-white"
            >
              <span class="material-icons text-lg">restaurant_menu</span>
              Manage menu items
            </router-link>
          </div>
        </div>
      </div>

      <AppModal
        :open="categoryModalOpen"
        :title="editingCategory ? 'Edit category' : 'Add category'"
        :description="editingCategory ? 'Update category name and description.' : 'Create a new menu category (e.g. Starters, Mains).'"
        @close="closeCategoryModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="saveCategory">
          <div v-if="hasMultipleLanguages" class="flex flex-col gap-2">
            <label for="category-locale-select" class="block text-sm font-semibold text-charcoal dark:text-white">Edit in</label>
            <select
              id="category-locale-select"
              v-model="selectedCategoryLocale"
              class="min-h-[44px] w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none"
              aria-label="Select language to edit"
            >
              <option v-for="loc in localesForForm" :key="loc" :value="loc">
                {{ getLocaleDisplay(loc) }}{{ loc === defaultLocale ? ' (Default)' : '' }}
              </option>
            </select>
          </div>
          <AppInput
            v-model="categoryForm.translations[selectedCategoryLocale].name"
            :label="hasMultipleLanguages ? `Name (${getLocaleDisplay(selectedCategoryLocale)})` : 'Category name'"
            type="text"
            placeholder="e.g. Starters, Main courses"
            :error="categoryFormError"
          />
          <div>
            <label :for="'category-desc-' + selectedCategoryLocale" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
              {{ hasMultipleLanguages ? `Description (${getLocaleDisplay(selectedCategoryLocale)}) — optional` : 'Description (optional)' }}
            </label>
            <textarea
              :id="'category-desc-' + selectedCategoryLocale"
              v-model="categoryForm.translations[selectedCategoryLocale].description"
              rows="3"
              placeholder="Short description for this category"
              class="w-full min-h-[44px] rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none"
            />
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" type="button" @click="closeCategoryModal">Cancel</AppButton>
          <AppButton variant="primary" class="min-h-[44px]" type="button" :disabled="savingCategory" @click="saveCategory">
            {{ savingCategory ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

      <AppModal
        :open="addMenuModalOpen"
        title="Add menu"
        description="Create another menu for this restaurant (e.g. Lunch, Drinks). Add name and optional description per language."
        @close="closeAddMenuModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="submitAddMenu">
          <div v-if="hasMultipleLanguages" class="flex flex-col gap-2">
            <label for="add-menu-locale-select" class="block text-sm font-semibold text-charcoal dark:text-white">Edit in</label>
            <select
              id="add-menu-locale-select"
              v-model="selectedAddMenuLocale"
              class="min-h-[44px] w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none"
              aria-label="Select language to edit"
            >
              <option v-for="loc in localesForForm" :key="loc" :value="loc">
                {{ getLocaleDisplay(loc) }}{{ loc === defaultLocale ? ' (Default)' : '' }}
              </option>
            </select>
          </div>
          <AppInput
            v-model="addMenuForm.translations[selectedAddMenuLocale].name"
            :label="hasMultipleLanguages ? `Name (${getLocaleDisplay(selectedAddMenuLocale)})` : 'Menu name'"
            type="text"
            placeholder="e.g. Lunch menu, Drinks"
            :error="addMenuFormError"
          />
          <div>
            <label :for="'add-menu-desc-' + selectedAddMenuLocale" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
              {{ hasMultipleLanguages ? `Description (${getLocaleDisplay(selectedAddMenuLocale)}) — optional` : 'Description (optional)' }}
            </label>
            <textarea
              :id="'add-menu-desc-' + selectedAddMenuLocale"
              v-model="addMenuForm.translations[selectedAddMenuLocale].description"
              rows="3"
              placeholder="Short description for this menu"
              class="w-full min-h-[44px] rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none"
            />
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" type="button" @click="closeAddMenuModal">Cancel</AppButton>
          <AppButton variant="primary" class="min-h-[44px]" type="button" :disabled="addingMenu" @click="submitAddMenu">
            {{ addingMenu ? 'Creating…' : 'Create menu' }}
          </AppButton>
        </template>
      </AppModal>

      <AvailabilityModal
        :open="availabilityModalOpen"
        :model-value="categoryForAvailability?.availability ?? null"
        title="Category availability"
        :entity-name="categoryForAvailability ? categoryName(categoryForAvailability) : ''"
        :api-save-error="availabilitySaveError"
        @save="saveCategoryAvailability"
        @close="closeAvailabilityModal"
      />

      <AppModal
        :open="deleteCategoryModalOpen"
        title="Remove category"
        :description="deleteCategoryMessage"
        @close="closeDeleteCategoryModal"
      >
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" type="button" @click="closeDeleteCategoryModal">Cancel</AppButton>
          <AppButton
            variant="primary"
            class="min-h-[44px] text-white bg-red-600 hover:bg-red-700"
            type="button"
            :disabled="deletingCategory"
            @click="confirmDeleteCategory"
          >
            {{ deletingCategory ? 'Removing…' : 'Remove category' }}
          </AppButton>
        </template>
      </AppModal>

      <AppModal
        :open="editMenuModalOpen"
        title="Edit menu"
        description="Change the name and description of this menu per language."
        @close="closeEditMenuModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="submitEditMenu">
          <div v-if="hasMultipleLanguages" class="flex flex-col gap-2">
            <label for="edit-menu-locale-select" class="block text-sm font-semibold text-charcoal dark:text-white">Edit in</label>
            <select
              id="edit-menu-locale-select"
              v-model="selectedEditMenuLocale"
              class="min-h-[44px] w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none"
              aria-label="Select language to edit"
            >
              <option v-for="loc in localesForForm" :key="loc" :value="loc">
                {{ getLocaleDisplay(loc) }}{{ loc === defaultLocale ? ' (Default)' : '' }}
              </option>
            </select>
          </div>
          <AppInput
            v-model="editMenuForm.translations[selectedEditMenuLocale].name"
            :label="hasMultipleLanguages ? `Name (${getLocaleDisplay(selectedEditMenuLocale)})` : 'Menu name'"
            type="text"
            placeholder="e.g. Lunch menu, Drinks"
            :error="editMenuFormError"
          />
          <div>
            <label :for="'edit-menu-desc-' + selectedEditMenuLocale" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
              {{ hasMultipleLanguages ? `Description (${getLocaleDisplay(selectedEditMenuLocale)}) — optional` : 'Description (optional)' }}
            </label>
            <textarea
              :id="'edit-menu-desc-' + selectedEditMenuLocale"
              v-model="editMenuForm.translations[selectedEditMenuLocale].description"
              rows="3"
              placeholder="Short description for this menu"
              class="w-full min-h-[44px] rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none"
            />
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" type="button" @click="closeEditMenuModal">Cancel</AppButton>
          <AppButton variant="primary" class="min-h-[44px]" type="button" :disabled="savingEditMenu" @click="submitEditMenu">
            {{ savingEditMenu ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- FAB speed-dial: main FAB toggles options; two actions (Add menu, Add category) -->
      <template v-if="selectedMenuUuid">
        <div
          v-if="fabOpen"
          class="fixed inset-0 z-20"
          aria-hidden="true"
          data-testid="fab-speed-dial-backdrop"
          @click="fabOpen = false"
        />
        <div
          class="fixed right-6 flex flex-col items-end gap-3 z-30 bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] lg:bottom-6"
          role="group"
          aria-label="Add menu or category"
        >
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
          >
            <div v-if="fabOpen" class="flex flex-col gap-2">
              <button
                type="button"
                class="min-h-[44px] min-w-[44px] sm:min-w-0 flex items-center justify-center gap-2 rounded-full sm:rounded-xl bg-white dark:bg-zinc-800 text-charcoal dark:text-white shadow-lg border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-zinc-700 transition-colors"
                title="Add menu"
                aria-label="Add menu"
                @click="onFabAddMenu"
              >
                <span class="material-icons" aria-hidden="true">restaurant</span>
                <span class="hidden sm:inline">Add menu</span>
              </button>
              <button
                type="button"
                class="min-h-[44px] min-w-[44px] sm:min-w-0 flex items-center justify-center gap-2 rounded-full sm:rounded-xl bg-white dark:bg-zinc-800 text-charcoal dark:text-white shadow-lg border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-zinc-700 transition-colors"
                title="Add category"
                aria-label="Add category"
                @click="onFabAddCategory"
              >
                <span class="material-icons" aria-hidden="true">category</span>
                <span class="hidden sm:inline">Add category</span>
              </button>
            </div>
          </Transition>
          <button
            type="button"
            class="w-14 h-14 min-h-[56px] min-w-[56px] bg-primary text-white rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105 active:scale-95"
            :title="fabOpen ? 'Close' : 'Add menu or category'"
            :aria-expanded="fabOpen"
            :aria-label="fabOpen ? 'Close add menu' : 'Add menu or category'"
            @click="fabOpen = !fabOpen"
          >
            <span class="material-icons text-3xl" aria-hidden="true">add</span>
          </button>
        </div>
      </template>
      <div class="h-20" aria-hidden="true" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AvailabilityModal from '@/components/availability/AvailabilityModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { usePageFabStore } from '@/stores/pageFab'
import AppButton from '@/components/ui/AppButton.vue'
import Restaurant from '@/models/Restaurant.js'
import Menu from '@/models/Menu.js'
import Category from '@/models/Category.js'
import { restaurantService, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'
import { getLocaleDisplay } from '@/config/locales'

const props = defineProps({
  /** When true (e.g. Menu tab is active), refetch restaurant so languages/translations are up to date after Settings changes. */
  tabActive: { type: Boolean, default: true },
})

const route = useRoute()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()
const pageFabStore = usePageFabStore()
const uuid = computed(() => route.params.uuid)

const loading = ref(true)
const menusLoading = ref(true)
const categoriesLoading = ref(false)
const restaurant = ref(null)
const menus = ref([])
const selectedMenuUuid = ref(null)
const categories = ref([])
const error = ref('')
const creatingMenu = ref(false)
const categoryModalOpen = ref(false)
const editingCategory = ref(null)
const categoryForm = ref({ translations: {} })
const categoryFormError = ref('')
const savingCategory = ref(false)
const selectedCategoryLocale = ref('en')
const addMenuModalOpen = ref(false)
const addMenuForm = ref({ translations: {} })
const addMenuFormError = ref('')
const addingMenu = ref(false)
const selectedAddMenuLocale = ref('en')
const deleteCategoryModalOpen = ref(false)
const categoryToDelete = ref(null)
const deletingCategory = ref(false)
const fabOpen = ref(false)
const editMenuModalOpen = ref(false)
const editMenuForm = ref({ translations: {} })
const editMenuFormError = ref('')
const savingEditMenu = ref(false)
const selectedEditMenuLocale = ref('en')
const availabilityModalOpen = ref(false)
const categoryForAvailability = ref(null)
const savingAvailability = ref(false)
const availabilitySaveError = ref('')

const defaultLocale = computed(() => restaurant.value?.default_locale ?? 'en')

/** Installed language codes for this restaurant (default first). */
const localesForForm = computed(() => {
  const def = defaultLocale.value
  const list = Array.isArray(restaurant.value?.languages) && restaurant.value.languages.length > 0
    ? restaurant.value.languages
    : def ? [def] : ['en']
  const rest = list.filter((l) => l !== def)
  return [def, ...rest].filter(Boolean)
})

const hasMultipleLanguages = computed(() => localesForForm.value.length > 1)

/** Currently selected menu (for the dropdown); used for the single visibility toggle. */
const selectedMenu = computed(() =>
  menus.value.find((m) => m.uuid === selectedMenuUuid.value) ?? null
)

function categoryName(cat) {
  if (!cat) return '—'
  const t = cat.translations?.[defaultLocale.value]
  return t?.name ?? '—'
}

/** Build empty translations object for each locale. */
function emptyTranslations(locales) {
  return Object.fromEntries(locales.map((l) => [l, { name: '', description: '' }]))
}

/** Build translations from entity (menu/category) for given locales. */
function fromEntityTranslations(locales, entityTranslations) {
  if (!entityTranslations || typeof entityTranslations !== 'object') {
    return emptyTranslations(locales)
  }
  return Object.fromEntries(
    locales.map((l) => [
      l,
      {
        name: (entityTranslations[l]?.name ?? '').trim(),
        description: (entityTranslations[l]?.description ?? '') ?? '',
      },
    ])
  )
}

/** Validate translations: at least one non-empty name; names max 255. Returns error message or ''. */
function validateTranslations(translations) {
  const entries = Object.entries(translations ?? {})
  const hasName = entries.some(([, t]) => (t?.name ?? '').trim().length > 0)
  if (!hasName) return 'At least one name is required.'
  for (const [locale, t] of entries) {
    const name = (t?.name ?? '').trim()
    if (name.length > 255) return `Name (${getLocaleDisplay(locale)}) must be at most 255 characters.`
  }
  return ''
}

/**
 * Build API translations payload from form.
 * @param {Record<string, { name?: string, description?: string }>} formTranslations
 * @param {boolean} onlyWithName - When true (create), only include locales with non-empty name (API required_with).
 */
function buildTranslationsPayload(formTranslations, onlyWithName = false) {
  const out = {}
  for (const [locale, t] of Object.entries(formTranslations ?? {})) {
    const name = (t?.name ?? '').trim()
    const description = (t?.description ?? '').trim() || null
    if (onlyWithName && !name) continue
    out[locale] = { name, description }
  }
  return out
}

const deleteCategoryMessage = computed(() => {
  const cat = categoryToDelete.value
  if (!cat) return ''
  const name = categoryName(cat)
  return `Remove "${name}"? All menu items in this category will be removed from this category (they will become uncategorized). This cannot be undone. If you're not sure, make the category inactive instead so it stays hidden on the public menu without removing anything.`
})

function categoryItemsLink(cat) {
  if (!restaurant.value || !cat?.uuid) return { name: 'Restaurants' }
  const catName = categoryName(cat)
  const selectedMenu = menus.value.find((m) => m.uuid === selectedMenuUuid.value)
  const menuName = selectedMenu?.name?.trim() || (selectedMenu ? 'Menu' : '')
  const query = {}
  if (catName && catName !== '—') query.name = catName
  if (menuName) query.menu = menuName
  return {
    name: 'CategoryMenuItems',
    params: { uuid: restaurant.value.uuid, categoryUuid: cat.uuid },
    query: Object.keys(query).length ? query : undefined,
  }
}

async function loadRestaurant() {
  if (!uuid.value) return
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

function dedupeByUuid(arr) {
  const seen = new Set()
  return arr.filter((item) => {
    if (seen.has(item.uuid)) return false
    seen.add(item.uuid)
    return true
  })
}

async function loadMenus() {
  if (!uuid.value) return
  menusLoading.value = true
  try {
    const res = await restaurantService.listMenus(uuid.value)
    const raw = (res.data ?? []).map((m) => Menu.fromApi({ data: m }).toJSON())
    const list = dedupeByUuid(raw)
    menus.value = list
    if (list.length && !selectedMenuUuid.value) selectedMenuUuid.value = list[0].uuid
  } catch (e) {
    error.value = normalizeApiError(e).message
    menus.value = []
  } finally {
    menusLoading.value = false
  }
}

async function loadCategories() {
  if (!uuid.value || !selectedMenuUuid.value) return
  categoriesLoading.value = true
  try {
    const res = await restaurantService.listCategories(uuid.value, selectedMenuUuid.value)
    const raw = (res.data ?? []).map((c) => Category.fromApi({ data: c }).toJSON())
    categories.value = dedupeByUuid(raw)
  } catch (e) {
    error.value = normalizeApiError(e).message
    categories.value = []
  } finally {
    categoriesLoading.value = false
  }
}

async function createFirstMenu() {
  if (!uuid.value || creatingMenu.value) return
  creatingMenu.value = true
  error.value = ''
  try {
    const res = await restaurantService.createMenu(uuid.value, { name: 'Main menu', is_active: true })
    const menu = Menu.fromApi(res).toJSON()
    menus.value = [menu]
    selectedMenuUuid.value = menu.uuid
    toastStore.success('Menu created.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    creatingMenu.value = false
  }
}

function openAddMenuModal() {
  fabOpen.value = false
  const locales = localesForForm.value
  addMenuForm.value = { translations: emptyTranslations(locales) }
  addMenuFormError.value = ''
  selectedAddMenuLocale.value = defaultLocale.value
  addMenuModalOpen.value = true
}

function onFabAddMenu() {
  fabOpen.value = false
  openAddMenuModal()
}

function onFabAddCategory() {
  fabOpen.value = false
  openCategoryModal()
}

function closeAddMenuModal() {
  addMenuModalOpen.value = false
  addMenuForm.value = { translations: {} }
  addMenuFormError.value = ''
}

async function submitAddMenu() {
  if (!uuid.value || addingMenu.value) return
  const translations = buildTranslationsPayload(addMenuForm.value.translations, true)
  const errMsg = validateTranslations(addMenuForm.value.translations)
  if (errMsg) {
    addMenuFormError.value = errMsg
    return
  }
  addMenuFormError.value = ''
  addingMenu.value = true
  try {
    const res = await restaurantService.createMenu(uuid.value, {
      translations,
      is_active: true,
      sort_order: menus.value.length,
    })
    const menu = Menu.fromApi(res).toJSON()
    await loadMenus()
    selectedMenuUuid.value = menu.uuid
    closeAddMenuModal()
    toastStore.success('Menu created.')
  } catch (e) {
    const err = e?.response?.data
    const msg = err?.errors?.translations?.[0] ?? err?.errors?.name?.[0] ?? err?.message ?? normalizeApiError(e).message
    addMenuFormError.value = msg
  } finally {
    addingMenu.value = false
  }
}

async function toggleMenuActive(m) {
  if (!uuid.value || !m.uuid) return
  try {
    await restaurantService.updateMenu(uuid.value, m.uuid, { is_active: !m.is_active })
    const list = menus.value.map((x) => (x.uuid === m.uuid ? { ...x, is_active: !x.is_active } : x))
    menus.value = list
    toastStore.success(m.is_active ? 'Menu hidden from public site.' : 'Menu visible on public site.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  }
}

function openEditMenuModal() {
  const menu = selectedMenu.value
  if (!menu) return
  const locales = localesForForm.value
  let translations = fromEntityTranslations(locales, menu.translations)
  const def = defaultLocale.value
  if (locales.length && (!menu.translations || Object.keys(menu.translations).length === 0) && (menu.name ?? '').trim()) {
    translations = { ...translations, [def]: { name: (menu.name ?? '').trim(), description: translations[def]?.description ?? '' } }
  }
  editMenuForm.value = { translations }
  editMenuFormError.value = ''
  selectedEditMenuLocale.value = def
  editMenuModalOpen.value = true
}

function closeEditMenuModal() {
  editMenuModalOpen.value = false
  editMenuForm.value = { translations: {} }
  editMenuFormError.value = ''
}

async function submitEditMenu() {
  if (!uuid.value || !selectedMenu.value?.uuid || savingEditMenu.value) return
  const errMsg = validateTranslations(editMenuForm.value.translations)
  if (errMsg) {
    editMenuFormError.value = errMsg
    return
  }
  editMenuFormError.value = ''
  savingEditMenu.value = true
  try {
    const translations = buildTranslationsPayload(editMenuForm.value.translations)
    const res = await restaurantService.updateMenu(uuid.value, selectedMenu.value.uuid, { translations })
    const updated = Menu.fromApi(res).toJSON()
    menus.value = menus.value.map((m) => (m.uuid === updated.uuid ? updated : m))
    closeEditMenuModal()
    toastStore.success('Menu updated.')
  } catch (e) {
    const err = e?.response?.data
    editMenuFormError.value = err?.errors?.translations?.[0] ?? err?.errors?.name?.[0] ?? err?.message ?? normalizeApiError(e).message
  } finally {
    savingEditMenu.value = false
  }
}

function openCategoryModal(category = null) {
  editingCategory.value = category ?? null
  const locales = localesForForm.value
  categoryForm.value = {
    translations: fromEntityTranslations(locales, category?.translations),
  }
  categoryFormError.value = ''
  selectedCategoryLocale.value = defaultLocale.value
  categoryModalOpen.value = true
}

function closeCategoryModal() {
  categoryModalOpen.value = false
  editingCategory.value = null
  categoryForm.value = { translations: {} }
  categoryFormError.value = ''
}

function openDeleteCategoryModal(cat) {
  categoryToDelete.value = cat
  deleteCategoryModalOpen.value = true
}

function closeDeleteCategoryModal() {
  deleteCategoryModalOpen.value = false
  categoryToDelete.value = null
}

async function confirmDeleteCategory() {
  const cat = categoryToDelete.value
  if (!uuid.value || !selectedMenuUuid.value || !cat?.uuid || deletingCategory.value) return
  deletingCategory.value = true
  error.value = ''
  try {
    await restaurantService.deleteCategory(uuid.value, selectedMenuUuid.value, cat.uuid)
    toastStore.success('Category removed.')
    closeDeleteCategoryModal()
    await loadCategories()
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    deletingCategory.value = false
  }
}

function openAvailabilityModal(cat) {
  categoryForAvailability.value = cat
  availabilityModalOpen.value = true
}

function closeAvailabilityModal() {
  availabilityModalOpen.value = false
  categoryForAvailability.value = null
  availabilitySaveError.value = ''
}

async function saveCategoryAvailability(availability) {
  const cat = categoryForAvailability.value
  if (!uuid.value || !selectedMenuUuid.value || !cat?.uuid || savingAvailability.value) return
  savingAvailability.value = true
  availabilitySaveError.value = ''
  try {
    await restaurantService.updateCategory(uuid.value, selectedMenuUuid.value, cat.uuid, { availability })
    categories.value = categories.value.map((c) =>
      c.uuid === cat.uuid ? { ...c, availability } : c
    )
    toastStore.success('Availability updated.')
    closeAvailabilityModal()
  } catch (e) {
    availabilitySaveError.value = normalizeApiError(e).message
  } finally {
    savingAvailability.value = false
  }
}

async function toggleCategoryActive(cat) {
  if (!uuid.value || !selectedMenuUuid.value || !cat?.uuid) return
  try {
    await restaurantService.updateCategory(uuid.value, selectedMenuUuid.value, cat.uuid, {
      is_active: !(cat.is_active !== false),
    })
    const list = categories.value.map((c) =>
      c.uuid === cat.uuid ? { ...c, is_active: !(cat.is_active !== false) } : c
    )
    categories.value = list
    toastStore.success(cat.is_active !== false ? 'Category hidden from public menu.' : 'Category visible on public menu.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  }
}

async function saveCategory() {
  if (savingCategory.value) return
  const errMsg = validateTranslations(categoryForm.value.translations)
  if (errMsg) {
    categoryFormError.value = errMsg
    return
  }
  categoryFormError.value = ''
  savingCategory.value = true
  const translations = buildTranslationsPayload(
    categoryForm.value.translations,
    !editingCategory.value
  )
  try {
    if (editingCategory.value) {
      await restaurantService.updateCategory(uuid.value, selectedMenuUuid.value, editingCategory.value.uuid, { translations })
      toastStore.success('Category updated.')
    } else {
      await restaurantService.createCategory(uuid.value, selectedMenuUuid.value, { translations })
      toastStore.success('Category created.')
    }
    closeCategoryModal()
    await loadCategories()
  } catch (e) {
    categoryFormError.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    savingCategory.value = false
  }
}

async function onReorderCategories() {
  const order = categories.value.map((c) => c.uuid)
  try {
    await restaurantService.reorderCategories(uuid.value, selectedMenuUuid.value, order)
    toastStore.success('Order updated.')
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadCategories()
  }
}

function updatePageFab() {
  pageFabStore.setPageFab(props.tabActive && !!selectedMenuUuid.value)
}

onMounted(() => {
  loadRestaurant().then(() => {
    if (restaurant.value) {
      loadMenus().then(() => {
        if (selectedMenuUuid.value) loadCategories()
      })
    }
    updatePageFab()
  })
})

onBeforeUnmount(() => {
  pageFabStore.setPageFab(false)
})

watch(uuid, () => {
  loading.value = true
  selectedMenuUuid.value = null
  loadRestaurant().then(() => {
    if (restaurant.value) {
      loadMenus().then(() => {
        if (selectedMenuUuid.value) loadCategories()
      })
    }
  })
})

watch(selectedMenuUuid, (val) => {
  if (val) loadCategories()
  updatePageFab()
})

/** When Menu tab becomes active, refetch restaurant so we have the latest languages (e.g. after adding one in Settings). */
watch(() => props.tabActive, (isActive) => {
  updatePageFab()
  if (isActive && uuid.value) {
    loadRestaurant().then(() => {
      if (restaurant.value) {
        loadMenus().then(() => {
          if (selectedMenuUuid.value) loadCategories()
        })
      }
      updatePageFab()
    })
  }
})

</script>
