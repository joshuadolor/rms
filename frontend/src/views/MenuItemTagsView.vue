<template>
  <div class="space-y-6 lg:space-y-8">
    <header>
      <h1 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menu item tags</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Default tags you can assign to menu items (e.g. Spicy, Vegan). Assign them when editing a menu item.
      </p>
    </header>

    <div v-if="error" role="alert" class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm">
      {{ error }}
    </div>

    <!-- Tags list: boxes beside each other, wrap -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <div v-for="i in 6" :key="i" class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>
    <ul v-else-if="tags.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 list-none p-0 m-0">
      <li
        v-for="tag in tags"
        :key="tag.uuid"
        class="flex flex-wrap items-center gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 min-h-[44px]"
      >
        <span
          class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0"
          :style="{ backgroundColor: tag.color ? `${tag.color}20` : undefined, color: tag.color || '#6b7280' }"
        >
          <span v-if="tag.icon" class="material-icons text-xl">{{ tag.icon }}</span>
          <span v-else class="material-icons text-xl">label</span>
        </span>
        <div class="min-w-0 flex-1">
          <span class="font-medium text-charcoal dark:text-white">{{ tag.text || 'Untitled' }}</span>
        </div>
      </li>
    </ul>
    <p v-else class="text-slate-500 dark:text-slate-400">No tags available.</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import MenuItemTag from '@/models/MenuItemTag.js'
import { menuItemTagService, normalizeApiError } from '@/services'

const loading = ref(true)
const error = ref('')
const tags = ref([])

async function loadTags() {
  loading.value = true
  error.value = ''
  try {
    const res = await menuItemTagService.list()
    const list = Array.isArray(res) ? res : []
    tags.value = list.map((t) => MenuItemTag.fromApi(t).toJSON())
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

onMounted(() => loadTags())
</script>
