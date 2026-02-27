<template>
  <footer class="bg-charcoal-blue text-white py-16 border-t-4 border-oxidized-copper" role="contentinfo">
    <div class="max-w-[1400px] mx-auto px-6 grid grid-cols-1 md:grid-cols-2 items-center gap-12">
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 border-2 border-oxidized-copper text-oxidized-copper flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor" />
          </svg>
        </div>
        <span class="heading-utilitarian text-3xl font-extrabold tracking-tighter">{{ restaurantName || 'Restaurant' }}</span>
      </div>
      <div class="md:text-right space-y-2">
        <p class="font-mono text-xs uppercase tracking-widest opacity-60">© {{ currentYear }} {{ restaurantName || 'Restaurant' }}. System Status: Nominal.</p>
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
