<template>
  <div data-testid="restaurant-settings">
    <div v-if="loading" class="space-y-4" data-testid="settings-loading">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-64 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!embed && !restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <template v-else-if="restaurant">
      <header class="mb-4 lg:mb-6">
        <div>
          <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Settings</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Language, currency, and description by language.</p>
        </div>
      </header>

      <div
        id="content-form-error"
        role="alert"
        aria-live="polite"
        data-testid="settings-error"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
        :class="{ 'sr-only': !error }"
      >
        {{ error }}
      </div>

      <!-- Currency -->
      <section class="mb-8 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-currency">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Currency</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Currency used for prices on your menu and public site.</p>
        <div class="flex flex-wrap items-center gap-3">
          <label for="restaurant-currency" class="text-sm font-medium text-charcoal dark:text-white shrink-0">Currency</label>
          <select
            id="restaurant-currency"
            v-model="currency"
            data-testid="settings-currency-select"
            class="max-w-[280px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary focus:outline-none"
            :disabled="savingCurrency"
            @change="saveCurrency"
          >
            <option v-for="c in CURRENCIES" :key="c.code" :value="c.code">{{ c.symbol }} {{ c.name }}</option>
          </select>
          <span v-if="savingCurrency" class="text-sm text-slate-500">Saving…</span>
        </div>
      </section>

      <!-- Languages -->
      <section class="mb-8 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-languages">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Languages</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Add or remove languages. The default language is used when no language is selected on the public site.</p>
        <ul class="space-y-2 mb-4">
          <li
            v-for="loc in installedLanguages"
            :key="loc"
            class="flex items-center justify-between gap-3 py-3 px-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-700/50"
          >
            <span class="font-medium text-charcoal dark:text-white">{{ getLocaleDisplay(loc) }}</span>
            <span v-if="loc === restaurant.default_locale" class="text-xs text-slate-500 dark:text-slate-400">Default</span>
            <div v-else class="flex items-center gap-2">
              <AppButton
                type="button"
                variant="ghost"
                size="sm"
                class="min-h-[36px]"
                :disabled="savingDefault === loc"
                data-testid="settings-set-default-button"
                @click="setDefaultLocale(loc)"
              >
                {{ savingDefault === loc ? 'Saving…' : 'Set as default' }}
              </AppButton>
              <AppButton
                type="button"
                variant="ghost"
                size="sm"
                class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[36px]"
                :disabled="removingLocale === loc"
                @click="confirmRemoveLanguage(loc)"
              >
                {{ removingLocale === loc ? 'Removing…' : 'Remove' }}
              </AppButton>
            </div>
          </li>
        </ul>
        <div class="flex flex-wrap items-center gap-3">
          <select
            v-model="languageToAdd"
            data-testid="settings-add-language-select"
            class="max-w-[280px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary focus:outline-none"
            :disabled="addingLanguage"
          >
            <option value="">Add language…</option>
            <option v-for="loc in availableLocalesToAdd" :key="loc" :value="loc">{{ getLocaleDisplay(loc) }}</option>
          </select>
          <AppButton type="button" variant="primary" size="sm" class="min-h-[44px]" data-testid="settings-add-language-button" :disabled="!languageToAdd || addingLanguage" @click="addLanguage">
            {{ addingLanguage ? 'Adding…' : 'Add' }}
          </AppButton>
        </div>
      </section>

      <!-- Description per locale -->
      <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-descriptions">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Description by language</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Optional description shown per language on the public site.</p>
        <p v-if="!installedLanguages.length" class="text-sm text-slate-500 dark:text-slate-400">Add at least one language above to manage descriptions.</p>
        <div v-for="loc in installedLanguages" :key="loc" class="mb-8 last:mb-0">
          <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="font-medium text-charcoal dark:text-white">{{ getLocaleDisplay(loc) }}</h4>
            <div class="flex gap-2">
              <AppButton v-if="loc !== restaurant.default_locale" type="button" variant="ghost" size="sm" class="min-h-[36px]" @click="translateDescription(loc)">
                Translate from default
              </AppButton>
              <AppButton type="button" variant="primary" size="sm" class="min-h-[36px]" :disabled="savingLocale === loc" @click="saveDescription(loc)">
                {{ savingLocale === loc ? 'Saving…' : 'Save' }}
              </AppButton>
            </div>
          </div>
          <textarea
            :value="embed && loc === restaurant.default_locale ? defaultDescription : descriptions[loc]"
            rows="4"
            :data-testid="`settings-description-${loc}`"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-y"
            :placeholder="`Description (${getLocaleDisplay(loc)})`"
            @input="(e) => { const v = e.target.value; descriptions[loc] = v; if (embed && loc === restaurant.default_locale) emit('update:defaultDescription', v) }"
          />
        </div>
      </section>

      <!-- Confirm remove language modal -->
      <div
        v-if="localeToRemove"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="remove-lang-title"
        data-testid="settings-remove-language-modal"
      >
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl max-w-sm w-full p-6">
          <h3 id="remove-lang-title" class="font-bold text-charcoal dark:text-white mb-2">Remove language?</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
            {{ getLocaleDisplay(localeToRemove) }} will be removed. Description for this language will be deleted.
          </p>
          <div class="flex gap-3">
            <AppButton variant="secondary" class="flex-1" data-testid="settings-remove-language-cancel" :disabled="removingLocale" @click="localeToRemove = null">Cancel</AppButton>
            <AppButton variant="primary" class="flex-1 bg-red-600 hover:bg-red-700" data-testid="settings-remove-language-confirm" :disabled="removingLocale" @click="doRemoveLanguage">
              {{ removingLocale ? 'Removing…' : 'Remove' }}
            </AppButton>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import { LOCALE_CODES, getLocaleDisplay } from '@/config/locales'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'
import { useBreadcrumbStore } from '@/stores/breadcrumb'

const props = defineProps({
  embed: { type: Boolean, default: false },
  defaultDescription: { type: String, default: '' },
})

const emit = defineEmits(['update:defaultDescription'])

const route = useRoute()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

const uuid = computed(() => route.params.uuid)

const CURRENCIES = [
  { code: 'USD', name: 'US Dollar', symbol: '$' },
  { code: 'EUR', name: 'Euro', symbol: '€' },
  { code: 'GBP', name: 'British Pound', symbol: '£' },
  { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
  { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
  { code: 'JPY', name: 'Japanese Yen', symbol: '¥' },
  { code: 'CHF', name: 'Swiss Franc', symbol: 'Fr' },
  { code: 'NGN', name: 'Nigerian Naira', symbol: '₦' },
  { code: 'MXN', name: 'Mexican Peso', symbol: 'MX$' },
  { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
]

const loading = ref(true)
const restaurant = ref(null)
const error = ref('')
const currency = ref('USD')
const savingCurrency = ref(false)
const savingDefault = ref(null)
const addingLanguage = ref(false)
const removingLocale = ref(null)
const localeToRemove = ref(null)
const savingLocale = ref(null)
const descriptions = ref({})
const languageToAdd = ref('')

const defaultLocale = computed(() => restaurant.value?.default_locale ?? '')

const installedLanguages = computed(() => {
  const lang = restaurant.value?.languages ?? []
  const def = restaurant.value?.default_locale
  if (!def || lang.length <= 1) return lang
  return [def, ...lang.filter((l) => l !== def)]
})

const availableLocalesToAdd = computed(() => {
  const installed = new Set(installedLanguages.value)
  return LOCALE_CODES.filter((code) => !installed.has(code))
})

async function loadRestaurant() {
  if (!uuid.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    if (restaurant.value) {
      currency.value = restaurant.value.currency ?? 'USD'
      await loadAllDescriptions()
    }
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadAllDescriptions() {
  const locs = restaurant.value?.languages ?? []
  const next = { ...descriptions.value }
  for (const loc of locs) {
    try {
      const res = await restaurantService.getTranslation(uuid.value, loc)
      next[loc] = res.data?.description ?? ''
    } catch {
      next[loc] = ''
    }
  }
  descriptions.value = next
  if (props.embed && restaurant.value?.default_locale != null) {
    emit('update:defaultDescription', next[restaurant.value.default_locale] ?? '')
  }
}

async function saveCurrency() {
  if (!restaurant.value?.uuid) return
  savingCurrency.value = true
  error.value = ''
  try {
    await restaurantService.update(uuid.value, { currency: currency.value })
    if (restaurant.value) restaurant.value.currency = currency.value
    toastStore.success('Currency updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    error.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingCurrency.value = false
  }
}

async function setDefaultLocale(loc) {
  if (!restaurant.value || restaurant.value.default_locale === loc) return
  if (savingDefault.value != null) return // prevent double run (e.g. double-click) and second toast
  savingDefault.value = loc
  error.value = ''
  try {
    const res = await restaurantService.update(uuid.value, { default_locale: loc })
    if (res) restaurant.value = Restaurant.fromApi(res).toJSON()
    // Sync parent/Profile to the new default's description so it doesn't show the old default's value
    if (props.embed) emit('update:defaultDescription', descriptions.value[loc] ?? '')
    toastStore.success('Default language updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    error.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingDefault.value = null
  }
}

async function addLanguage() {
  if (!languageToAdd.value) return
  addingLanguage.value = true
  error.value = ''
  try {
    const res = await restaurantService.addLanguage(uuid.value, { locale: languageToAdd.value })
    if (res.data) restaurant.value.languages = [...res.data]
    const added = languageToAdd.value
    languageToAdd.value = ''
    descriptions.value[added] = ''
    toastStore.success(`${getLocaleDisplay(added)} added.`)
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    addingLanguage.value = false
  }
}

function confirmRemoveLanguage(loc) {
  localeToRemove.value = loc
}

async function doRemoveLanguage() {
  if (!localeToRemove.value) return
  removingLocale.value = localeToRemove.value
  try {
    await restaurantService.removeLanguage(uuid.value, localeToRemove.value)
    restaurant.value.languages = (restaurant.value.languages ?? []).filter((l) => l !== localeToRemove.value)
    delete descriptions.value[localeToRemove.value]
    toastStore.success('Language removed.')
    localeToRemove.value = null
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    removingLocale.value = null
  }
}

async function saveDescription(loc) {
  if (savingLocale.value != null) return
  savingLocale.value = loc
  try {
    await restaurantService.putTranslation(uuid.value, loc, { description: descriptions.value[loc] ?? null })
    toastStore.success('Description saved.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    savingLocale.value = null
  }
}

/** Copy default locale description into this locale (local only). User clicks Save to persist. */
function translateDescription(loc) {
  const def = restaurant.value?.default_locale
  if (!def || def === loc) return
  const defDesc = descriptions.value[def] ?? ''
  if (defDesc) {
    descriptions.value[loc] = defDesc
    // No API call and no toast here; one Save action = one toast
  }
}

// When embed, keep default locale description in sync with Profile tab
watch(() => props.defaultDescription, (val) => {
  if (props.embed && restaurant.value?.default_locale != null) {
    const next = { ...descriptions.value }
    next[restaurant.value.default_locale] = val ?? ''
    descriptions.value = next
  }
}, { immediate: true })

onMounted(() => loadRestaurant())
watch(uuid, () => loadRestaurant())
</script>
