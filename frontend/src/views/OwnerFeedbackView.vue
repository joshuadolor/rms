<template>
  <div data-testid="owner-feedback-page">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.featureRequest') }}</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.featureRequestSubtitle') }}</p>
    </header>

    <div class="max-w-2xl space-y-8">
      <!-- Submit form -->
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
        <h3 class="font-semibold text-charcoal dark:text-white mb-4">{{ $t('app.sendFeedbackHeading') }}</h3>
        <form class="space-y-4" novalidate @submit.prevent="submitForm">
          <div class="space-y-1">
            <label for="owner-feedback-message" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Message <span class="text-red-500">*</span>
            </label>
            <textarea
              id="owner-feedback-message"
              v-model="form.message"
              rows="4"
              class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 resize-y min-h-[44px]"
              :aria-invalid="!!fieldErrors.message"
              :aria-describedby="fieldErrors.message ? 'owner-feedback-message-error' : undefined"
              placeholder="Describe your feature request or feedback…"
            />
            <p v-if="fieldErrors.message" id="owner-feedback-message-error" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ fieldErrors.message }}</p>
            <p v-else class="text-xs text-gray-500">Max 65,535 characters.</p>
          </div>
          <AppInput
            v-model="form.title"
            label="Title (optional)"
            type="text"
            placeholder="Short summary"
            :error="fieldErrors.title"
          />
          <div class="space-y-1" v-if="restaurantOptions.length > 0">
            <label for="owner-feedback-restaurant" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Restaurant (optional)</label>
            <select
              id="owner-feedback-restaurant"
              v-model="form.restaurant"
              class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 min-h-[44px]"
            >
              <option value="">— None —</option>
              <option v-for="r in restaurantOptions" :key="r.uuid" :value="r.uuid">{{ r.name }}</option>
            </select>
          </div>
          <p v-if="formError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ formError }}</p>
          <AppButton type="submit" variant="primary" :disabled="submitting" class="min-h-[44px]">
            <template v-if="submitting" #icon>
              <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
            </template>
            {{ submitting ? $t('app.sending') : $t('public.sendFeedback') }}
          </AppButton>
        </form>
      </div>

      <!-- My requests -->
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <h3 class="font-semibold text-charcoal dark:text-white p-4 lg:p-6 pb-2">{{ $t('app.myFeatureRequests') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 px-4 lg:px-6 pb-4">{{ $t('app.yourRecentSubmissions') }}</p>
        <div v-if="listLoading" class="px-4 lg:px-6 pb-6 space-y-3">
          <div v-for="i in 3" :key="i" class="h-16 rounded-lg bg-slate-100 dark:bg-zinc-800 animate-pulse" />
        </div>
        <div v-else-if="!myFeedbacks.length" class="px-4 lg:px-6 pb-6 text-slate-500 dark:text-slate-400 text-sm">
          {{ $t('app.noSubmissionsYet') }}
        </div>
        <ul v-else class="divide-y divide-slate-200 dark:divide-slate-800" data-testid="owner-feedback-list">
          <li
            v-for="f in myFeedbacks"
            :key="f.uuid"
            class="p-4 lg:p-5"
          >
            <p v-if="f.title" class="font-medium text-charcoal dark:text-white">{{ f.title }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5" :class="{ 'mt-0.5': f.title }">{{ f.truncatedMessage(160) }}</p>
            <div class="flex flex-wrap items-center gap-2 mt-2">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="f.status === 'reviewed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
              >
                {{ f.status === 'reviewed' ? 'Reviewed' : 'Pending' }}
              </span>
              <span class="text-xs text-slate-400 dark:text-slate-500">{{ f.createdLabel }}</span>
              <span v-if="f.restaurant" class="text-xs text-slate-500 dark:text-slate-400">{{ f.restaurantLabel }}</span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useToastStore } from '@/stores/toast'
import { ownerFeedbackService, restaurantService, normalizeApiError } from '@/services'
import Restaurant from '@/models/Restaurant'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const toastStore = useToastStore()

const form = reactive({
  message: '',
  title: '',
  restaurant: '',
})
const fieldErrors = ref({ message: '', title: '' })
const formError = ref('')
const submitting = ref(false)

const restaurantOptions = ref([])
const myFeedbacks = ref([])
const listLoading = ref(true)

const MAX_MESSAGE = 65535
const MAX_TITLE = 255

function validate() {
  const err = { message: '', title: '' }
  const msg = (form.message ?? '').trim()
  if (!msg) err.message = 'Message is required.'
  else if (msg.length > MAX_MESSAGE) err.message = `Message must be at most ${MAX_MESSAGE} characters.`
  const title = (form.title ?? '').trim()
  if (title.length > MAX_TITLE) err.title = `Title must be at most ${MAX_TITLE} characters.`
  fieldErrors.value = err
  return !err.message && !err.title
}

async function submitForm() {
  formError.value = ''
  if (!validate()) return
  submitting.value = true
  try {
    const payload = {
      message: (form.message ?? '').trim(),
      title: (form.title ?? '').trim() || undefined,
      restaurant: (form.restaurant ?? '').trim() || undefined,
    }
    if (!payload.restaurant) delete payload.restaurant
    if (!payload.title) delete payload.title
    await ownerFeedbackService.submitFeedback(payload)
    toastStore.success('Feedback submitted.')
    form.message = ''
    form.title = ''
    form.restaurant = ''
    fieldErrors.value = { message: '', title: '' }
    loadMyFeedbacks()
  } catch (err) {
    const normalized = normalizeApiError(err)
    formError.value = normalized.message
    if (err?.response?.status === 422 && err?.response?.data?.errors) {
      const e = err.response.data.errors
      fieldErrors.value = {
        message: Array.isArray(e.message) ? e.message[0] : e.message ?? '',
        title: Array.isArray(e.title) ? e.title[0] : e.title ?? '',
      }
    }
  } finally {
    submitting.value = false
  }
}

async function loadMyFeedbacks() {
  listLoading.value = true
  try {
    myFeedbacks.value = await ownerFeedbackService.listMyFeedbacks()
  } catch (err) {
    const normalized = normalizeApiError(err)
    toastStore.error(normalized.message)
  } finally {
    listLoading.value = false
  }
}

async function loadRestaurants() {
  try {
    const res = await restaurantService.list({ per_page: 50 })
    const list = (res.data ?? []).map((r) => Restaurant.fromApi(r))
    restaurantOptions.value = list
  } catch {
    restaurantOptions.value = []
  }
}

onMounted(() => {
  loadRestaurants()
  loadMyFeedbacks()
})
</script>
