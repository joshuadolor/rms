<template>
  <section
    v-if="text"
    id="about"
    class="max-w-6xl mx-auto px-6 py-20"
    aria-labelledby="about-heading-t1"
  >
    <h2 id="about-heading-t1" class="text-3xl font-bold tracking-tight mb-8 text-t1-neutral-dark">About</h2>
    <p class="text-t1-neutral-muted whitespace-pre-line">{{ text }}</p>
  </section>
</template>

<script setup>
defineProps({
  text: { type: String, default: '' },
})
</script>
