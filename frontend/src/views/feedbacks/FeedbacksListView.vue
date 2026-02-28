<template>
  <div class="min-h-[60vh] lg:min-h-0">
    <div v-if="loading && !restaurant" class="space-y-4">
      <div class="h-24 rounded-2xl bg-white/60 dark:bg-zinc-800/80 animate-pulse" />
      <div class="h-48 rounded-2xl bg-white/60 dark:bg-zinc-800/80 animate-pulse" />
    </div>

    <div v-else-if="!restaurant" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
      <p class="text-slate-500 dark:text-slate-400 mb-4">{{ $t('app.restaurantNotFound') }}</p>
      <AppBackLink :to="{ name: 'Feedbacks' }" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm text-slate-600 dark:text-slate-400">{{ restaurant.name }}</p>
          <h2 class="text-2xl font-bold tracking-tight text-charcoal dark:text-white lg:text-3xl mt-0.5">
            Feedbacks
          </h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Approve feedback to show it on your public page. Reject or delete to hide or remove it.
          </p>
        </div>
        <AppBackLink :to="{ name: 'Feedbacks' }" class="shrink-0" />
      </header>

      <div v-if="loadingFeedbacks" class="space-y-3">
        <div
          v-for="i in 3"
          :key="i"
          class="h-28 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse"
        />
      </div>

      <div v-else-if="errorFeedbacks" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-6 text-center">
        <p class="text-slate-500 dark:text-slate-400">{{ errorFeedbacks }}</p>
        <AppButton variant="secondary" class="mt-4 min-h-[44px]" @click="fetchFeedbacks">Retry</AppButton>
      </div>

      <div v-else-if="!feedbacks.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
        <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">rate_review</span>
        <p class="mt-4 text-slate-500 dark:text-slate-400">No feedbacks yet. They will appear here when customers submit reviews.</p>
      </div>

      <ul v-else class="space-y-4" data-testid="feedbacks-list">
        <li
          v-for="fb in feedbacks"
          :key="fb.uuid"
          class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-6"
          data-testid="feedback-item"
        >
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2 gap-y-1">
                <div class="flex items-center gap-0.5" :aria-label="`Rating: ${fb.rating} out of 5`">
                  <span
                    v-for="star in 5"
                    :key="star"
                    class="material-icons text-lg sm:text-xl"
                    :class="star <= fb.rating ? 'text-amber-500' : 'text-slate-200 dark:text-slate-600'"
                  >
                    {{ star <= fb.rating ? 'star' : 'star_border' }}
                  </span>
                </div>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium min-h-[28px]"
                  :class="fb.is_approved ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300'"
                >
                  {{ fb.is_approved ? 'Approved' : 'Pending' }}
                </span>
              </div>
              <p class="mt-2 text-charcoal dark:text-white whitespace-pre-wrap">{{ fb.text || '—' }}</p>
              <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                — {{ fb.name || 'Anonymous' }}
                <span v-if="fb.created_at" class="ml-1">· {{ formatDate(fb.created_at) }}</span>
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0 sm:flex-nowrap">
              <template v-if="!fb.is_approved">
                <AppButton
                  variant="sage"
                  size="sm"
                  class="min-h-[44px] min-w-[44px]"
                  title="Approve"
                  aria-label="Approve feedback"
                  @click="setApproved(fb, true)"
                >
                  <template #icon>
                    <span class="material-icons">check</span>
                  </template>
                  <span class="sm:inline">Approve</span>
                </AppButton>
              </template>
              <template v-else>
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="min-h-[44px] min-w-[44px]"
                  title="Reject"
                  aria-label="Reject feedback"
                  @click="setApproved(fb, false)"
                >
                  <template #icon>
                    <span class="material-icons">close</span>
                  </template>
                  <span class="sm:inline">Reject</span>
                </AppButton>
              </template>
              <AppButton
                variant="ghost"
                size="sm"
                class="min-h-[44px] min-w-[44px] text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 dark:text-red-400"
                title="Delete"
                aria-label="Delete feedback"
                @click="confirmDelete(fb)"
              >
                <template #icon>
                  <span class="material-icons">delete</span>
                </template>
                <span class="sm:inline">Delete</span>
              </AppButton>
            </div>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import Restaurant from '@/models/Restaurant.js'
import Feedback from '@/models/Feedback.js'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { restaurantService, feedbackService, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/utils/format'

const route = useRoute()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()

const restaurantUuid = ref(route.params.restaurantUuid ?? '')
const loading = ref(true)
const restaurant = ref(null)
const loadingFeedbacks = ref(true)
const errorFeedbacks = ref(null)
const feedbacks = ref([])

async function fetchRestaurant() {
  if (!restaurantUuid.value) return
  loading.value = true
  try {
    const res = await restaurantService.get(restaurantUuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    if (restaurant.value) breadcrumbStore.setRestaurantName(restaurant.value.name)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else restaurant.value = null
  } finally {
    loading.value = false
  }
}

async function fetchFeedbacks() {
  if (!restaurantUuid.value) return
  loadingFeedbacks.value = true
  errorFeedbacks.value = null
  try {
    const res = await feedbackService.listFeedbacks(restaurantUuid.value)
    const list = (res.data ?? []).map((f) => Feedback.fromApi(f).toJSON())
    feedbacks.value = list
  } catch (e) {
    errorFeedbacks.value = normalizeApiError(e).message ?? 'Failed to load feedbacks.'
  } finally {
    loadingFeedbacks.value = false
  }
}

async function setApproved(fb, isApproved) {
  try {
    const res = await feedbackService.updateFeedback(restaurantUuid.value, fb.uuid, { is_approved: isApproved })
    const updated = Feedback.fromApi(res?.data ?? res).toJSON()
    const idx = feedbacks.value.findIndex((f) => f.uuid === fb.uuid)
    if (idx !== -1) feedbacks.value.splice(idx, 1, updated)
    toastStore.success(isApproved ? 'Feedback approved.' : 'Feedback rejected.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message ?? 'Failed to update.')
  }
}

function confirmDelete(fb) {
  if (!window.confirm('Delete this feedback? This cannot be undone.')) return
  deleteFeedback(fb)
}

async function deleteFeedback(fb) {
  try {
    await feedbackService.deleteFeedback(restaurantUuid.value, fb.uuid)
    feedbacks.value = feedbacks.value.filter((f) => f.uuid !== fb.uuid)
    toastStore.success('Feedback deleted.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message ?? 'Failed to delete.')
  }
}

onMounted(() => {
  restaurantUuid.value = route.params.restaurantUuid ?? ''
  fetchRestaurant().then(() => {
    if (restaurant.value) fetchFeedbacks()
  })
})

watch(
  () => route.params.restaurantUuid,
  (uuid) => {
    restaurantUuid.value = uuid ?? ''
    if (restaurantUuid.value) {
      breadcrumbStore.clearRestaurant()
      fetchRestaurant().then(() => {
        if (restaurant.value) fetchFeedbacks()
        else feedbacks.value = []
      })
    } else {
      restaurant.value = null
      feedbacks.value = []
    }
  }
)
</script>
