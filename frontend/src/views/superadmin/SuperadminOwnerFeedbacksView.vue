<template>
  <div data-testid="superadmin-owner-feedbacks-page">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Owner feedbacks</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Feature requests and feedback from restaurant owners. Toggle status when reviewed.</p>
    </header>

    <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
      {{ error }}
    </div>

    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 5"
        :key="i"
        class="h-24 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse"
      />
    </div>

    <div v-else-if="!feedbacks.length" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-8 text-center">
      <p class="text-slate-500 dark:text-slate-400">No owner feedbacks yet.</p>
    </div>

    <div v-else class="space-y-3" data-testid="superadmin-owner-feedbacks-list">
      <div
        v-for="f in feedbacks"
        :key="f.uuid"
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 overflow-hidden"
        :data-testid="`superadmin-feedback-row-${f.uuid}`"
      >
        <div class="p-4 lg:p-5 flex flex-col gap-3">
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-charcoal dark:text-white">
              {{ f.submitterLabel }}
              <span class="text-slate-500 dark:text-slate-400 font-normal text-sm ml-1">({{ f.submitter?.email || '—' }})</span>
            </p>
            <p v-if="f.title" class="text-sm font-medium text-slate-700 dark:text-slate-300 mt-0.5">{{ f.title }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ f.truncatedMessage(200) }}</p>
            <div class="flex flex-wrap items-center gap-2 mt-2">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="f.status === 'reviewed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
              >
                {{ f.status === 'reviewed' ? 'Reviewed' : 'Pending' }}
              </span>
              <span class="text-xs text-slate-400 dark:text-slate-500">{{ f.createdLabel }}</span>
              <span v-if="f.restaurant" class="text-xs text-slate-500 dark:text-slate-400">{{ f.restaurantLabel }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 shrink-0">
            <button
              type="button"
              class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium transition-colors"
              :class="f.status === 'reviewed' ? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' : 'bg-primary/10 text-primary hover:bg-primary/20'"
              :disabled="actionLoading === f.uuid"
              :aria-label="f.status === 'reviewed' ? 'Mark pending' : 'Mark reviewed'"
              @click="toggleStatus(f)"
            >
              {{ f.status === 'reviewed' ? 'Mark pending' : 'Mark reviewed' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToastStore } from '@/stores/toast'
import { superadminService, normalizeApiError } from '@/services'

const toastStore = useToastStore()

const feedbacks = ref([])
const loading = ref(true)
const error = ref('')
const actionLoading = ref(null)

async function loadFeedbacks() {
  error.value = ''
  loading.value = true
  try {
    feedbacks.value = await superadminService.listOwnerFeedbacks()
  } catch (err) {
    const normalized = normalizeApiError(err)
    error.value = normalized.status === 403 ? "You don't have permission to view owner feedbacks." : normalized.message
  } finally {
    loading.value = false
  }
}

async function toggleStatus(f) {
  actionLoading.value = f.uuid
  error.value = ''
  const nextStatus = f.status === 'reviewed' ? 'pending' : 'reviewed'
  try {
    const updated = await superadminService.updateOwnerFeedback(f.uuid, { status: nextStatus })
    const idx = feedbacks.value.findIndex((x) => x.uuid === f.uuid)
    if (idx !== -1) feedbacks.value[idx] = updated
    toastStore.success('Feedback updated.')
  } catch (err) {
    const normalized = normalizeApiError(err)
    if (normalized.status === 403) {
      error.value = "You don't have permission to update feedbacks."
    } else if (normalized.status === 404) {
      error.value = 'Feedback not found.'
      loadFeedbacks()
    } else if (normalized.status === 422) {
      toastStore.error(normalized.message)
    } else {
      toastStore.error(normalized.message)
    }
  } finally {
    actionLoading.value = null
  }
}

onMounted(() => loadFeedbacks())
</script>
