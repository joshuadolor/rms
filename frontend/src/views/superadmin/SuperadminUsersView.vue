<template>
  <div data-testid="superadmin-users-page">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Users</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage user accounts: paid status and active/deactivate.</p>
    </header>

    <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
      {{ error }}
    </div>

    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 5"
        :key="i"
        class="h-20 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse"
      />
    </div>

    <div v-else-if="!users.length" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-8 text-center">
      <p class="text-slate-500 dark:text-slate-400">No users found.</p>
    </div>

    <div v-else class="space-y-3" data-testid="superadmin-users-list">
      <div
        v-for="u in users"
        :key="u.id"
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 overflow-hidden"
        :data-testid="`superadmin-user-row-${u.email}`"
      >
        <div class="p-4 lg:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-charcoal dark:text-white truncate">{{ u.fullName }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ u.email }}</p>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="u.isPaid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
              >
                {{ u.isPaid ? 'Paid' : 'Free' }}
              </span>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="u.isActive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
              >
                {{ u.isActive ? 'Active' : 'Deactivated' }}
              </span>
              <span
                v-if="u.isSuperadmin"
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
              >
                Superadmin
              </span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 shrink-0">
            <button
              type="button"
              class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium transition-colors"
              :class="u.isPaid ? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' : 'bg-primary/10 text-primary hover:bg-primary/20'"
              :disabled="actionLoading === u.id"
              :aria-label="u.isPaid ? 'Remove paid' : 'Mark paid'"
              @click="togglePaid(u)"
            >
              {{ u.isPaid ? 'Paid' : 'Free' }}
            </button>
            <button
              v-if="!isCurrentUser(u)"
              type="button"
              class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium transition-colors"
              :class="u.isActive ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/50' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/50'"
              :disabled="actionLoading === u.id"
              :aria-label="u.isActive ? 'Deactivate user' : 'Activate user'"
              @click="toggleActive(u)"
            >
              {{ u.isActive ? 'Deactivate' : 'Activate' }}
            </button>
            <span v-else class="text-xs text-slate-400 dark:text-slate-500 self-center px-2">(you)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { superadminService } from '@/services'
import { normalizeApiError } from '@/services'

const appStore = useAppStore()
const { user: currentUser } = storeToRefs(appStore)

const users = ref([])
const loading = ref(true)
const error = ref('')
const actionLoading = ref(null)

function isCurrentUser(u) {
  return currentUser.value?.id && u.id === currentUser.value.id
}

async function loadUsers() {
  error.value = ''
  loading.value = true
  try {
    users.value = await superadminService.listUsers()
  } catch (err) {
    const normalized = normalizeApiError(err)
    error.value = normalized.status === 403 ? "You don't have permission to view users." : normalized.message
  } finally {
    loading.value = false
  }
}

async function togglePaid(u) {
  actionLoading.value = u.id
  error.value = ''
  try {
    const updated = await superadminService.updateUser(u.id, { is_paid: !u.isPaid })
    const idx = users.value.findIndex((x) => x.id === u.id)
    if (idx !== -1) users.value[idx] = updated
  } catch (err) {
    const normalized = normalizeApiError(err)
    error.value = normalized.message
  } finally {
    actionLoading.value = null
  }
}

async function toggleActive(u) {
  if (isCurrentUser(u)) return
  actionLoading.value = u.id
  error.value = ''
  try {
    const updated = await superadminService.updateUser(u.id, { is_active: !u.isActive })
    const idx = users.value.findIndex((x) => x.id === u.id)
    if (idx !== -1) users.value[idx] = updated
  } catch (err) {
    const normalized = normalizeApiError(err)
    error.value = normalized.status === 422 ? 'Cannot change your own status.' : normalized.message
  } finally {
    actionLoading.value = null
  }
}

onMounted(() => loadUsers())
</script>
