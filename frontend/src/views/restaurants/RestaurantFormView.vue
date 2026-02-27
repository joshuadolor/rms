<template>
  <div class="max-w-3xl" :class="{ 'pb-24': embed }" data-testid="restaurant-form">
    <header v-if="!embed" class="mb-6 lg:mb-8" data-testid="form-header">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">
        {{ isEdit ? 'Edit restaurant' : 'Add new restaurant' }}
      </h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        {{ isEdit ? 'Update details and media.' : 'Fill in the essentials to list your restaurant.' }}
      </p>
    </header>

    <div v-if="loading && isEdit" class="space-y-4">
      <div class="h-32 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!embed && isEdit && !loading && restaurant === null"
      class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <form v-else class="space-y-6 lg:space-y-8" novalidate data-testid="restaurant-form-form" @submit.prevent="handleSubmit">
      <div
        id="form-error"
        role="alert"
        aria-live="polite"
        data-testid="form-error"
        class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
        :class="{ 'sr-only': !error }"
      >
        {{ error }}
      </div>

      <!-- Basic information -->
      <section class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4" data-testid="form-section-basic">
        <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
          <span class="material-icons text-slate-500 dark:text-slate-400">info</span>
          Basic information
        </h3>
        <AppInput
          v-model="form.name"
          label="Restaurant name"
          type="text"
          placeholder="e.g. Mama Fina's Restaurant"
          described-by="form-error"
          :error="fieldErrors.name"
          data-testid="form-input-name"
        />
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Your restaurant's web address will be created automatically from this name.
        </p>
        <AppInput
          v-model="form.tagline"
          label="Tagline (optional)"
          type="text"
          placeholder="e.g. Fresh ingredients, bold flavors"
          :error="fieldErrors.tagline"
          data-testid="form-input-tagline"
        />
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1" for="form-input-address">Address (optional)</label>
          <textarea
            id="form-input-address"
            v-model="form.address"
            rows="2"
            placeholder="Street, city, state"
            data-testid="form-input-address"
            class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none"
            :aria-invalid="!!fieldErrors.address"
            :aria-describedby="fieldErrors.address ? 'form-address-error' : undefined"
          />
          <p v-if="fieldErrors.address" id="form-address-error" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ fieldErrors.address }}</p>
        </div>
        <AppInput
          v-model="form.phone"
          label="Phone (optional)"
          type="tel"
          placeholder="+1 234 567 8900"
          :error="fieldErrors.phone"
          data-testid="form-input-phone"
        />
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1" for="form-input-description">Description (optional)</label>
          <textarea
            id="form-input-description"
            v-model="form.description"
            rows="4"
            placeholder="Short description of your restaurant for the public site"
            data-testid="form-input-description"
            class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none"
            :aria-invalid="!!fieldErrors.description"
            :aria-describedby="fieldErrors.description ? 'form-description-error' : undefined"
          />
          <p v-if="fieldErrors.description" id="form-description-error" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ fieldErrors.description }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Shown on your public page. Add more languages in Settings.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <AppInput v-model="form.email" label="Email (optional)" type="email" placeholder="contact@restaurant.com" :error="fieldErrors.email" />
          <AppInput v-model="form.website" label="Website (optional)" type="url" placeholder="https://example.com" :error="fieldErrors.website" />
        </div>
        <AppInput
          v-model="form.year_established"
          label="Year established (optional)"
          type="number"
          placeholder="e.g. 1995"
          :error="fieldErrors.year_established"
          data-testid="form-input-year-established"
          class="[&_input]:min-h-[44px]"
        />

        <!-- Availability: only when not embedded (embed uses Availability tab) -->
        <div v-if="!embed" class="pt-4 border-t border-slate-200 dark:border-slate-800">
          <h4 class="font-medium text-charcoal dark:text-white flex items-center gap-2 mb-3">
            <span class="material-icons text-slate-500 dark:text-slate-400 text-lg">schedule</span>
            Availability
          </h4>
          <RestaurantAvailabilitySchedule
            v-model="form.operatingHours"
            :day-errors="availabilityDayErrors"
            :summary-error="availabilitySummaryError"
          />
        </div>

        <!-- Contact & links: only when editing an existing restaurant -->
        <div v-if="isEdit && uuid" class="pt-4 border-t border-slate-200 dark:border-slate-800" data-testid="form-section-contact-and-links">
          <ContactAndLinksEditor :restaurant-uuid="uuid" :active="true" />
        </div>
      </section>

      <!-- Action row: left = Cancel (standalone) or slot (embed), right = Save; full width buttons on mobile -->
      <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-h-[44px] w-full sm:w-auto">
          <template v-if="!embed">
            <router-link :to="isEdit ? { name: 'RestaurantDetail', params: { uuid } } : { name: 'Restaurants' }" class="block w-full sm:w-auto">
              <AppButton type="button" variant="secondary" class="min-h-[44px] w-full sm:w-auto">Cancel</AppButton>
            </router-link>
          </template>
          <slot v-else name="actions-start" />
        </div>
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
          {{ saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Create restaurant') }}
        </AppButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import RestaurantAvailabilitySchedule from '@/components/restaurant/RestaurantAvailabilitySchedule.vue'
import ContactAndLinksEditor from '@/components/restaurant/ContactAndLinksEditor.vue'
import Restaurant from '@/models/Restaurant.js'
import { validateOperatingHours } from '@/utils/availability'
import { restaurantService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'
import { useBreadcrumbStore } from '@/stores/breadcrumb'

const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

const props = defineProps({
  embed: { type: Boolean, default: false },
  operatingHours: { type: Object, default: undefined },
  defaultDescription: { type: String, default: '' },
})

const emit = defineEmits(['update:defaultDescription', 'availability-errors', 'availability-summary-error', 'saved'])

const uuid = computed(() => route.params.uuid)
const isEdit = computed(() => props.embed || route.meta.mode === 'edit')

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const availabilityDayErrors = ref({})
const availabilitySummaryError = ref('')
const restaurant = ref(null)

const form = reactive({
  name: '',
  tagline: '',
  address: '',
  phone: '',
  email: '',
  website: '',
  description: '',
  year_established: '',
  latitude: '',
  longitude: '',
  operatingHours: {},
})

const MAX = { name: 255, tagline: 255, address: 1000, phone: 50, email: 255, website: 500, socialUrl: 500 }
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const URL_RE = /^https?:\/\/.+/i
const YEAR_ESTABLISHED_MIN = 1800
const yearEstablishedMax = () => new Date().getFullYear() + 1

// Sync description with Settings tab when embed: receive from parent, emit on change
watch(() => props.defaultDescription, (val) => {
  if (props.embed && val !== undefined && val !== null) form.description = val
}, { immediate: true })

watch(() => form.description, (val) => {
  if (props.embed) emit('update:defaultDescription', val ?? '')
})

function validateForm() {
  const err = {}
  const n = form.name.trim()
  if (!n) err.name = 'Restaurant name is required.'
  else if (n.length > MAX.name) err.name = `Name must be at most ${MAX.name} characters.`
  if (form.tagline && form.tagline.length > MAX.tagline) err.tagline = `Tagline must be at most ${MAX.tagline} characters.`
  if (form.address && form.address.length > MAX.address) err.address = `Address must be at most ${MAX.address} characters.`
  if (form.phone && form.phone.length > MAX.phone) err.phone = `Phone must be at most ${MAX.phone} characters.`
  if (form.email && !EMAIL_RE.test(form.email)) err.email = 'Please enter a valid email address.'
  if (form.website && !URL_RE.test(form.website)) err.website = 'Please enter a valid URL.'
  const yearStr = String(form.year_established ?? '').trim()
  if (yearStr !== '') {
    const year = parseInt(yearStr, 10)
    const maxYear = yearEstablishedMax()
    if (Number.isNaN(year) || year !== Number(yearStr) || !Number.isInteger(year)) {
      err.year_established = 'Please enter a valid year (e.g. 1995).'
    } else if (year < YEAR_ESTABLISHED_MIN || year > maxYear) {
      err.year_established = `Year must be between ${YEAR_ESTABLISHED_MIN} and ${maxYear}.`
    }
  }
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

function buildPayload() {
  const payload = {
    name: form.name.trim(),
    tagline: form.tagline.trim() || undefined,
    address: form.address.trim() || undefined,
    phone: form.phone.trim() || undefined,
    email: form.email.trim() || undefined,
    website: form.website.trim() || undefined,
  }
  const hours = props.embed && props.operatingHours != null ? props.operatingHours : form.operatingHours
  if (hours && typeof hours === 'object' && Object.keys(hours).length) payload.operating_hours = hours
  const yearStr = String(form.year_established ?? '').trim()
  if (yearStr !== '') {
    const year = parseInt(yearStr, 10)
    if (!Number.isNaN(year) && Number.isInteger(year)) payload.year_established = year
  } else {
    payload.year_established = null
  }
  return payload
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  availabilityDayErrors.value = {}
  availabilitySummaryError.value = ''
  const hours = props.embed && props.operatingHours != null ? props.operatingHours : form.operatingHours
  if (hours && typeof hours === 'object' && Object.keys(hours).length > 0) {
    const result = validateOperatingHours(hours)
    if (!result.valid) {
      availabilityDayErrors.value = result.errors
      const dayNames = Object.keys(result.errors).map((k) => k.charAt(0).toUpperCase() + k.slice(1))
      const summaryMsg = `Please fix the schedule for ${dayNames.join(', ')}.`
      error.value = summaryMsg
      availabilitySummaryError.value = summaryMsg
      if (props.embed) {
        emit('availability-errors', result.errors)
        emit('availability-summary-error', summaryMsg)
      }
      return
    }
  }
  if (props.embed) {
    emit('availability-errors', {})
    emit('availability-summary-error', '')
  }
  if (!validateForm()) return
  saving.value = true
  try {
    if (isEdit.value) {
      const res = await restaurantService.update(uuid.value, buildPayload())
      const updated = res != null ? Restaurant.fromApi(res).toJSON() : restaurant.value
      restaurant.value = updated
      const defaultLocale = restaurant.value?.default_locale
      if (defaultLocale != null && form.description !== undefined) {
        await restaurantService.putTranslation(uuid.value, defaultLocale, { description: form.description?.trim() || null })
      }
      toastStore.success(res.message ?? 'Restaurant updated.')
      if (props.embed && updated) emit('saved', updated)
      if (!props.embed) router.push({ name: 'RestaurantDetail', params: { uuid: uuid.value } })
    } else {
      const res = await restaurantService.create(buildPayload())
      toastStore.success(res.message ?? 'Restaurant created.')
      router.push({ name: 'RestaurantDetail', params: { uuid: res.data?.uuid } })
    }
  } catch (e) {
    const errors = getValidationErrors(e)
    if (Object.keys(errors).length > 0) fieldErrors.value = { ...fieldErrors.value, ...errors }
    error.value = normalizeApiError(e).message ?? 'Something went wrong. Please try again.'
  } finally {
    saving.value = false
  }
}

async function loadRestaurant() {
  if (!uuid.value) return
  loading.value = true
  try {
    const res = await restaurantService.get(uuid.value)
    const r = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    restaurant.value = r
    breadcrumbStore.setRestaurantName(r?.name ?? null)
    if (r) {
      form.name = r.name ?? ''
      form.tagline = r.tagline ?? ''
      form.address = r.address ?? ''
      form.phone = r.phone ?? ''
      form.description = ''
      form.email = r.email ?? ''
      form.website = r.website ?? ''
      form.year_established = r.year_established != null ? String(r.year_established) : ''
      form.latitude = r.latitude != null ? String(r.latitude) : ''
      form.longitude = r.longitude != null ? String(r.longitude) : ''
      if (!props.embed) form.operatingHours = r.operating_hours ?? {}
      const defaultLocale = r.default_locale
      if (defaultLocale) {
        try {
          const tr = await restaurantService.getTranslation(uuid.value, defaultLocale)
          form.description = tr?.data?.description ?? ''
          if (props.embed) emit('update:defaultDescription', form.description ?? '')
        } catch (_) {}
      }
    }
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => { if (isEdit.value) loadRestaurant() })
watch([uuid, isEdit], () => { if (isEdit.value && uuid.value) loadRestaurant() })
</script>
