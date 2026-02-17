<template>
  <router-link
    :to="to"
    class="inline-flex items-center gap-1.5 min-h-[44px] min-w-[44px] py-2 pr-2 -ml-2 rounded-lg text-sm text-slate-500 dark:text-slate-400 hover:text-primary transition-colors"
    aria-label="Back"
  >
    <span class="material-icons text-lg shrink-0" aria-hidden="true">arrow_back</span>
    <span>Back</span>
  </router-link>
</template>

<script setup>
defineProps({
  to: { type: [Object, String], required: true },
})
</script>
