<template>
  <section class="bg-t1-bg-light py-20" aria-labelledby="reviews-heading-t1">
    <div class="max-w-6xl mx-auto px-6">
      <div class="text-center mb-16">
        <h2 id="reviews-heading-t1" class="text-3xl font-bold tracking-tight mb-2 text-t1-neutral-dark">Guest Experiences</h2>
        <p class="text-t1-neutral-muted">What our patrons are saying</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <template v-if="feedbacks.length">
          <div
            v-for="(fb, idx) in feedbacks"
            :key="fb.uuid || idx"
            class="bg-white p-8 border border-t1-border rounded-lg"
          >
            <div class="flex mb-4 gap-0.5" aria-hidden="true">
              <span v-for="s in 5" :key="s" class="material-icons text-lg" :style="s <= starCount(fb) ? starsStyle : { color: 'var(--tw-slate-300, #cbd5e1)' }">star</span>
            </div>
            <p class="italic text-t1-neutral-dark mb-6 leading-relaxed">"{{ fb.text || '' }}"</p>
            <p class="text-sm font-bold uppercase tracking-widest text-t1-neutral-dark">— {{ fb.name || 'Anonymous' }}</p>
          </div>
        </template>
        <p v-else class="col-span-full text-center text-t1-neutral-muted">{{ emptyMessage }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  feedbacks: { type: Array, default: () => [] },
  primaryColor: { type: String, default: '' },
  emptyMessage: { type: String, default: 'No reviews yet. Be the first to leave feedback below.' },
})

const starsStyle = computed(() => ({ color: props.primaryColor || '#1152d4' }))

function starCount(fb) {
  const r = Number(fb?.rating)
  return Math.min(5, Math.max(0, Number.isNaN(r) ? 0 : Math.round(r)))
}
</script>
