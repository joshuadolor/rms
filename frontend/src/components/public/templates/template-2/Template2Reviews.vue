<template>
  <component
    :is="nested ? 'div' : 'section'"
    class="rms-reviews-t2"
    :class="{ 'rms-reviews-t2--nested': nested, 'border-y border-charcoal-blue': !nested }"
    aria-labelledby="reviews-heading-t2"
  >
    <div class="rms-reviews-t2__inner" :class="{ 'max-w-[1400px] mx-auto': !nested }">
      <div
        class="rms-reviews-t2__list"
        :class="nested
          ? 'rms-reviews-t2__list--masonry'
          : 'grid grid-cols-1 md:grid-cols-3 border-x border-charcoal-blue'"
      >
        <template v-if="feedbacks.length">
          <div
            v-for="(fb, idx) in feedbacks"
            :key="fb.uuid || idx"
            class="rms-reviews-t2__card flex flex-col justify-between break-inside-avoid"
            :class="nested
              ? 'py-6 border-b border-charcoal-blue/40 last:border-b-0 first:pt-0'
              : ['p-12 md:p-16 border-b md:border-b-0  border-charcoal-blue', idx === feedbacks.length - 1 && 'md:border-r-0', idx === 1 && 'bg-concrete-gray']"
          >
            <div>
              <div class="flex gap-1 mb-4 md:mb-8" aria-hidden="true">
                <span v-for="s in 5" :key="s" class="material-icons text-xl" :class="s <= starCount(fb) ? 'text-oxidized-copper' : 'text-charcoal-blue/30'">star</span>
              </div>
              <p class="text-xl md:text-2xl font-bold italic leading-tight text-charcoal-blue mb-6 md:mb-10">"{{ fb.text || '' }}"</p>
            </div>
            <p class="heading-utilitarian text-oxidized-copper font-extrabold text-lg md:text-xl">— {{ fb.name || 'Anonymous' }}</p>
          </div>
        </template>
        <p v-else class="rms-reviews-t2__empty p-12 md:p-16 text-center text-charcoal-blue/70" :class="{ 'col-span-full': !nested }">{{ emptyMessage }}</p>
      </div>
    </div>
  </component>
</template>

<script setup>
defineProps({
  feedbacks: { type: Array, default: () => [] },
  emptyMessage: { type: String, default: 'No reviews yet. Be the first to leave feedback below.' },
  nested: { type: Boolean, default: false },
})

function starCount(fb) {
  const r = Number(fb?.rating)
  return Math.min(5, Math.max(0, Number.isNaN(r) ? 0 : Math.round(r)))
}
</script>
