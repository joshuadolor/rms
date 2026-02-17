<template>
  <PublicRestaurantView v-if="subdomainSlug" :slug="subdomainSlug" />
  <LandingView v-else />
</template>

<script setup>
import { computed } from 'vue'
import { getSubdomainSlug } from '@/utils/subdomain'
import LandingView from '@/views/LandingView.vue'
import PublicRestaurantView from '@/views/PublicRestaurantView.vue'

const subdomainSlug = computed(() => getSubdomainSlug())
</script>
