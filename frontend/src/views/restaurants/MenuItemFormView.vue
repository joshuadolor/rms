<template>
  <div class="max-w-3xl" data-testid="menu-item-form">
    <div v-if="loading && isEdit" class="space-y-4">
      <div class="h-32 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-48 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="isEdit && !loading && !restaurant && !standaloneItem"
      class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant or menu item not found.</p>
      <AppBackLink :to="backLink" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8" data-testid="form-header">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">
          {{ isEdit ? 'Edit menu item' : 'Add menu item' }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          {{ isEdit ? 'Name and description per language. At least the default language is required.' : 'Add the name and optional description. You can add other languages after creating the item.' }}
        </p>
      </header>

      <form v-if="restaurant || standaloneItem" class="space-y-6 lg:space-y-8" novalidate data-testid="menu-item-form-form" @submit.prevent="handleSubmit">
        <div
          id="menu-item-form-error"
          role="alert"
          aria-live="polite"
          data-testid="form-error"
          class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          :class="{ 'sr-only': !error }"
        >
          {{ error }}
        </div>

        <!-- Price: base (standalone or restaurant-owned) or override (catalog usage) -->
        <section
          v-if="restaurant || standaloneItem"
          class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
        >
          <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
            <span class="material-icons text-slate-500 dark:text-slate-400">payments</span>
            Price
          </h3>
          <template v-if="itemFromCatalog">
            <AppInput
              v-model="form.price_override"
              :label="`Override price (leave empty to use base: ${formatBasePrice(basePrice)})`"
              type="number"
              min="0"
              step="0.01"
              placeholder="Same as base"
              :error="fieldErrors.price_override"
            />
          </template>
          <AppInput
            v-else
            v-model="form.price"
            label="Price (optional)"
            type="number"
            min="0"
            step="0.01"
            placeholder="e.g. 10.00"
            :error="fieldErrors.price"
          />
          <AppButton
            v-if="itemFromCatalog && hasOverrides"
            type="button"
            variant="secondary"
            size="sm"
            class="min-h-[44px]"
            :disabled="saving || reverting"
            data-testid="revert-to-base"
            @click="revertToBase"
          >
            <template v-if="reverting" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ reverting ? 'Reverting…' : 'Revert to base value' }}
          </AppButton>
        </section>

        <!-- Translations: dropdown to pick language, then one section for the selected locale -->
        <template v-if="restaurant || standaloneItem">
          <section
            class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
            :data-testid="`form-section-locale-${selectedLocale}`"
          >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <label for="menu-item-locale-select" class="block text-sm font-semibold text-charcoal dark:text-white">
                Language
              </label>
              <select
                id="menu-item-locale-select"
                v-model="selectedLocale"
                class="min-h-[44px] w-full sm:w-auto min-w-[12rem] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none"
                aria-label="Select language for name and description"
                data-testid="menu-item-locale-select"
              >
                <option
                  v-for="loc in formLocales"
                  :key="loc"
                  :value="loc"
                >
                  {{ getLocaleDisplay(loc) }}
                </option>
              </select>
            </div>
            <template v-if="selectedLocale && form.translations[selectedLocale]">
              <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
                  <span class="material-icons text-slate-500 dark:text-slate-400">translate</span>
                  {{ getLocaleDisplay(selectedLocale) }}
                </h3>
                <AppButton
                  v-if="restaurant && selectedLocale !== restaurant.default_locale"
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px]"
                  :disabled="translatingLocale === selectedLocale"
                  @click="translateLocale(selectedLocale)"
                >
                  {{ translatingLocale === selectedLocale ? 'Translating…' : 'Translate from default' }}
                </AppButton>
              </div>
              <AppInput
                v-model="form.translations[selectedLocale].name"
                :label="`Name (${getLocaleDisplay(selectedLocale)})`"
                type="text"
                :placeholder="(restaurant?.default_locale ?? 'en') === selectedLocale ? 'e.g. Margherita Pizza' : ''"
                :error="fieldErrors[`translations.${selectedLocale}.name`]"
              />
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1" :for="`menu-item-desc-${selectedLocale}`">Description (optional)</label>
                <textarea
                  :id="`menu-item-desc-${selectedLocale}`"
                  v-model="form.translations[selectedLocale].description"
                  rows="3"
                  class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none min-h-[44px]"
                  :placeholder="`Description in ${getLocaleDisplay(selectedLocale)}`"
                />
              </div>
            </template>
          </section>
        </template>

        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3">
          <router-link :to="backLink" class="block w-full sm:w-auto">
            <AppButton type="button" variant="secondary" class="min-h-[44px] w-full sm:w-auto">Cancel</AppButton>
          </router-link>
          <AppButton
            type="submit"
            variant="primary"
            class="min-h-[44px] w-full sm:w-auto sm:shrink-0"
            data-testid="form-submit"
            :disabled="saving"
          >
            <template v-if="saving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Create item') }}
          </AppButton>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import { getLocaleDisplay } from '@/config/locales'
import { formatCurrency } from '@/utils/format'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import Restaurant from '@/models/Restaurant.js'
import MenuItem from '@/models/MenuItem.js'
import { restaurantService, menuItemService, localeService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

/** Restaurant uuid: from route params (restaurant module) or query (standalone edit). */
const uuid = computed(() => route.params.uuid || route.query.restaurant || null)
const itemUuid = computed(() => route.params.itemUuid || null)
const isEdit = computed(() => route.meta.mode === 'edit')
const isMenuItemsModule = computed(() => !!route.meta.menuItemsModule)

const backLink = computed(() => {
  if (isMenuItemsModule.value) return { name: 'MenuItems' }
  if (uuid.value) return { name: 'RestaurantMenuItems', params: { uuid: uuid.value } }
  return { name: 'MenuItems' }
})

const loading = ref(true)
const saving = ref(false)
const reverting = ref(false)
const error = ref('')
const fieldErrors = ref({})
const restaurant = ref(null)
const standaloneItem = ref(null)
const translatingLocale = ref(null)
/** Currently selected locale in the translations dropdown. */
const selectedLocale = ref('en')
/** When editing a restaurant item that comes from catalog (source_menu_item_uuid). */
const catalogSourceUuid = ref(null)
const baseTranslations = ref({})
const basePrice = ref(null)
const hasOverrides = ref(false)

const form = reactive({
  sort_order: 0,
  category_uuid: null,
  price: '',
  price_override: '',
  translations: {},
})

const itemFromCatalog = computed(() => !!catalogSourceUuid.value)

function formatBasePrice(price) {
  const currency = restaurant.value?.currency ?? 'USD'
  return formatCurrency(price, currency)
}

const installedLanguages = computed(() => restaurant.value?.languages ?? [])

/** On create: only default language. On edit: all installed languages. Standalone edit: locales from form.translations. */
const formLocales = computed(() => {
  if (standaloneItem.value) {
    const keys = Object.keys(form.translations)
    return keys.length ? keys : ['en']
  }
  const def = restaurant.value?.default_locale
  const all = installedLanguages.value
  if (isEdit.value) return all
  return def ? [def] : []
})

function buildTranslations() {
  const locs = standaloneItem.value ? Object.keys(form.translations) : installedLanguages.value
  const out = {}
  for (const loc of locs) {
    const t = form.translations[loc]
    out[loc] = {
      name: t?.name ?? '',
      description: t?.description ?? null,
    }
    if (out[loc].description === '') out[loc].description = null
  }
  return out
}

function validate() {
  const err = {}
  const defaultLoc = restaurant.value?.default_locale ?? (standaloneItem.value ? (Object.keys(form.translations)[0] ?? 'en') : 'en')
  if (form.translations[defaultLoc]) {
    const name = (form.translations[defaultLoc].name ?? '').trim()
    if (!name) err[`translations.${defaultLoc}.name`] = 'Name is required for the default language.'
  }
  if (itemFromCatalog.value) {
    const p = form.price_override === '' || form.price_override == null ? null : Number(form.price_override)
    if (p !== null && (Number.isNaN(p) || p < 0)) err.price_override = 'Price must be 0 or greater.'
  } else {
    const p = form.price === '' || form.price == null ? null : Number(form.price)
    if (p !== null && (Number.isNaN(p) || p < 0)) err.price = 'Price must be 0 or greater.'
  }
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

function buildTranslationOverrides() {
  const overrides = {}
  const base = baseTranslations.value
  for (const loc of Object.keys(form.translations)) {
    const t = form.translations[loc] ?? {}
    const b = base[loc] ?? {}
    const name = (t.name ?? '').trim()
    const desc = t.description ?? null
    const baseName = (b.name ?? '').trim()
    const baseDesc = b.description ?? null
    if (name !== baseName || desc !== baseDesc) {
      overrides[loc] = { name: name || baseName, description: desc !== baseDesc ? desc : null }
    }
  }
  return overrides
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  if (!validate()) return
  saving.value = true
  try {
    let payload = { sort_order: form.sort_order }
    if (itemFromCatalog.value) {
      payload.price_override = form.price_override === '' || form.price_override == null ? null : Number(form.price_override)
      payload.translation_overrides = buildTranslationOverrides()
    } else {
      payload.translations = buildTranslations()
      payload.price = form.price === '' || form.price == null ? null : Number(form.price)
      if (payload.price !== null && Number.isNaN(payload.price)) delete payload.price
    }
    if (form.category_uuid != null && form.category_uuid !== '' && restaurant.value) payload.category_uuid = form.category_uuid
    if (isEdit.value) {
      if (standaloneItem.value) {
        await menuItemService.update(itemUuid.value, payload)
        toastStore.success('Menu item updated.')
        router.push({ name: 'MenuItems' })
      } else {
        await restaurantService.updateMenuItem(uuid.value, itemUuid.value, payload)
        toastStore.success('Menu item updated.')
        router.push(isMenuItemsModule.value ? { name: 'MenuItems' } : { name: 'RestaurantMenuItems', params: { uuid: uuid.value } })
      }
    } else {
      await restaurantService.createMenuItem(uuid.value, payload)
      toastStore.success('Menu item created.')
      if (route.query.return === 'category-items' && route.query.category_uuid) {
        router.push({
          name: 'CategoryMenuItems',
          params: { uuid: uuid.value, categoryUuid: route.query.category_uuid },
          query: route.query.name ? { name: route.query.name } : undefined,
        })
      } else {
        router.push(isMenuItemsModule.value ? { name: 'MenuItems' } : { name: 'RestaurantMenuItems', params: { uuid: uuid.value } })
      }
    }
  } catch (e) {
    const errs = getValidationErrors(e)
    if (Object.keys(errs).length > 0) fieldErrors.value = errs
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    saving.value = false
  }
}

async function revertToBase() {
  if (!uuid.value || !itemUuid.value || !itemFromCatalog.value) return
  error.value = ''
  reverting.value = true
  try {
    await restaurantService.updateMenuItem(uuid.value, itemUuid.value, { revert_to_base: true })
    toastStore.success('Reverted to base value.')
    await loadMenuItem()
  } catch (e) {
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    reverting.value = false
  }
}

async function translateLocale(targetLoc) {
  const defaultLoc = restaurant.value?.default_locale
  if (!defaultLoc || targetLoc === defaultLoc) return
  const name = (form.translations[defaultLoc]?.name ?? '').trim()
  const desc = (form.translations[defaultLoc]?.description ?? '').trim()
  if (!name && !desc) {
    toastStore.error('Fill in name or description in the default language first.')
    return
  }
  translatingLocale.value = targetLoc
  error.value = ''
  try {
    if (name) {
      const resName = await localeService.translate({ text: name, from_locale: defaultLoc, to_locale: targetLoc })
      if (resName.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].name = resName.translated_text
      }
    }
    if (desc) {
      const resDesc = await localeService.translate({ text: desc, from_locale: defaultLoc, to_locale: targetLoc })
      if (resDesc.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].description = resDesc.translated_text
      }
    }
    toastStore.success('Translation applied. Review and save.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    translatingLocale.value = null
  }
}

async function loadRestaurant() {
  if (!uuid.value) {
    loading.value = false
    return
  }
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    if (restaurant.value) {
      const langRes = await restaurantService.getLanguages(uuid.value).catch(() => ({ data: [restaurant.value.default_locale || 'en'] }))
      restaurant.value.languages = Array.isArray(langRes?.data) ? langRes.data : [restaurant.value.default_locale || 'en']
    }
    if (!isMenuItemsModule.value) breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    if (restaurant.value && !isEdit.value) initFormFromRestaurant()
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  } finally {
    loading.value = false
  }
}

function initFormFromRestaurant() {
  const def = restaurant.value?.default_locale ?? 'en'
  form.sort_order = 0
  form.category_uuid = route.query.category_uuid ?? null
  form.price = ''
  form.price_override = ''
  form.translations = {}
  if (def) form.translations[def] = { name: '', description: null }
  selectedLocale.value = def
}

async function loadMenuItem() {
  if (!uuid.value || !itemUuid.value) return
  try {
    const res = await restaurantService.getMenuItem(uuid.value, itemUuid.value)
    const item = res?.data != null ? MenuItem.fromApi(res) : null
    if (!item) return
    form.sort_order = item.sort_order ?? 0
    form.category_uuid = item.category_uuid ?? null
    const trans = item.translations ?? {}
    const locs = restaurant.value?.languages ?? []
    form.translations = {}
    for (const loc of locs) {
      const t = trans[loc]
      form.translations[loc] = {
        name: t?.name ?? '',
        description: t?.description ?? null,
      }
    }
    if (item.source_menu_item_uuid) {
      catalogSourceUuid.value = item.source_menu_item_uuid
      baseTranslations.value = item.base_translations ?? {}
      basePrice.value = item.base_price ?? null
      hasOverrides.value = item.has_overrides ?? false
      form.price_override = item.price_override != null ? String(item.price_override) : ''
      form.price = ''
    } else {
      catalogSourceUuid.value = null
      baseTranslations.value = {}
      basePrice.value = null
      hasOverrides.value = false
      form.price_override = ''
      form.price = item.price != null ? String(item.price) : ''
    }
    const defLoc = restaurant.value?.default_locale ?? locs[0]
    selectedLocale.value = locs.includes(selectedLocale.value) ? selectedLocale.value : (defLoc ?? locs[0] ?? 'en')
    const defaultName = defLoc ? (form.translations[defLoc]?.name ?? '') : ''
    breadcrumbStore.setMenuItemName(defaultName.trim() || null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  }
}

async function loadStandaloneMenuItem() {
  if (!itemUuid.value) return
  try {
    const res = await menuItemService.get(itemUuid.value)
    const item = res?.data != null ? MenuItem.fromApi(res) : null
    if (!item) return
    standaloneItem.value = item.toJSON()
    form.sort_order = item.sort_order ?? 0
    form.category_uuid = null
    form.price = item.price != null ? String(item.price) : ''
    form.price_override = ''
    const trans = item.translations ?? {}
    form.translations = {}
    for (const loc of Object.keys(trans)) {
      const t = trans[loc]
      form.translations[loc] = {
        name: t?.name ?? '',
        description: t?.description ?? null,
      }
    }
    const firstLoc = Object.keys(form.translations)[0] ?? 'en'
    selectedLocale.value = Object.keys(form.translations).includes(selectedLocale.value) ? selectedLocale.value : firstLoc
    const defaultName = firstLoc ? (form.translations[firstLoc]?.name ?? '') : ''
    breadcrumbStore.setMenuItemName(defaultName.trim() || null)
  } catch (e) {
    if (e?.response?.status === 404) standaloneItem.value = null
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (isMenuItemsModule.value && !isEdit.value) {
    router.replace({ name: 'MenuItems' })
    return
  }
  if (isMenuItemsModule.value && isEdit.value && !uuid.value) {
    await loadStandaloneMenuItem()
    return
  }
  await loadRestaurant()
  if (isEdit.value && restaurant.value && itemUuid.value) await loadMenuItem()
  if (isEdit.value && !restaurant.value && !standaloneItem.value) loading.value = false
})

watch([uuid, itemUuid], async () => {
  if (uuid.value) await loadRestaurant()
  if (isEdit.value && uuid.value && itemUuid.value && restaurant.value) await loadMenuItem()
  if (isMenuItemsModule.value && isEdit.value && !uuid.value && itemUuid.value) await loadStandaloneMenuItem()
})

watch(formLocales, (locs) => {
  if (locs.length && !locs.includes(selectedLocale.value)) {
    const def = restaurant.value?.default_locale ?? (standaloneItem.value ? null : 'en')
    selectedLocale.value = (def && locs.includes(def)) ? def : locs[0]
  }
}, { immediate: true })
</script>
