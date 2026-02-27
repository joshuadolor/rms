<template>
  <footer class="rms-footer" role="contentinfo">
    <p class="rms-footer__credit">&copy; {{ year }} {{ restaurantName }}</p>
  </footer>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  restaurantName: { type: String, default: '' },
})

const year = computed(() => new Date().getFullYear())
</script>
