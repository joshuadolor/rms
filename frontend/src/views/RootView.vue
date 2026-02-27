<template>
  <LandingView />
</template>

<script setup>
import { onMounted } from 'vue'
import { getSubdomainSlug } from '@/utils/subdomain'
import LandingView from '@/views/LandingView.vue'

// Restaurant subdomain (e.g. test.rms.local): redirect to /r/{slug} so the request is proxied to
// Laravel and the Blade page is served. Works on any port (80 or 8080); no nginx subdomain routing needed.
onMounted(() => {
  const slug = getSubdomainSlug()
  if (slug && !window.location.pathname.startsWith('/r/')) {
    const path = `/r/${slug}${window.location.search}`
    window.location.replace(path)
  }
})
</script>
