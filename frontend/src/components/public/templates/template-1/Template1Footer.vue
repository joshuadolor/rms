<template>
  <footer class="border-t border-t1-border py-12" role="contentinfo">
    <div class="max-w-6xl mx-auto px-6 flex flex-col items-center gap-6">
      <div class="flex items-center gap-2 opacity-50 grayscale">
        <div v-if="logoUrl" class="block overflow-hidden rounded">
          <img :src="logoUrl" :alt="restaurantName" class="w-full h-full object-cover" />
        </div>
        <div v-else class="size-8">
          <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor" />
          </svg>
        </div>
        <div class="block w-full text-center text-sm font-bold uppercase tracking-tight text-t1-neutral-dark">{{ restaurantName || 'Restaurant' }}</div>
      </div>
      <div class="text-center text-t1-neutral-muted text-sm space-y-2">
        <p>© {{ currentYear }} {{ restaurantName || 'Restaurant' }}. {{ $t('public.allRightsReserved') }}</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  restaurantName: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
})

const currentYear = computed(() => new Date().getFullYear())
</script>
