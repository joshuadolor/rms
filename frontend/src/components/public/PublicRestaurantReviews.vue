<template>
  <section class="rms-reviews" id="reviews" aria-labelledby="reviews-heading">
    <h3 id="reviews-heading" class="rms-reviews__title">{{ reviewsTitle }}</h3>
    <template v-if="feedbacks.length">
    <div
      v-for="fb in feedbacks"
      :key="fb.uuid"
      class="rms-review"
      role="article"
      :aria-label="ratingOutOfFive(fb)"
    >
      <div class="rms-review__stars" aria-hidden="true">
        {{ starsText(fb) }}
      </div>
      <p v-if="fb.text" class="rms-review__text">{{ fb.text }}</p>
      <p class="rms-review__meta">{{ fb.name || anonymousLabel }}</p>
    </div>
    </template>
    <p v-else class="rms-reviews__empty">{{ emptyMessage }}</p>
  </section>
</template>

<script setup>
defineProps({
  reviewsTitle: { type: String, default: 'Reviews' },
  feedbacks: {
    type: Array,
    default: () => [],
  },
  anonymousLabel: { type: String, default: 'Anonymous' },
  emptyMessage: { type: String, default: 'No reviews yet. Be the first to leave feedback below.' },
})

function starsText(fb) {
  const r = Math.min(5, Math.max(0, Math.round(Number(fb.rating) || 0)))
  return '★'.repeat(r) + '☆'.repeat(5 - r)
}

function ratingOutOfFive(fb) {
  const r = Math.min(5, Math.max(0, Math.round(Number(fb.rating) || 0)))
  return `${r} out of 5 stars`
}
</script>
