<template>
  <div
    class="min-h-screen font-display bg-background-light dark:bg-background-dark text-charcoal dark:text-gray-100 public-restaurant-page"
    :style="publicAccentStyle"
  >
    <!-- Loading -->
    <div v-if="loading" class="flex min-h-screen items-center justify-center">
      <div class="text-center">
        <span class="material-icons text-4xl animate-spin" style="color: var(--public-accent)">sync</span>
        <p class="mt-3 text-slate-500 dark:text-slate-400">Loading…</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex min-h-screen items-center justify-center p-6">
      <div class="max-w-md rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center shadow-lg">
        <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">restaurant</span>
        <h1 class="mt-4 text-xl font-bold text-charcoal dark:text-white">Restaurant not found</h1>
        <p class="mt-2 text-slate-500 dark:text-slate-400">{{ error }}</p>
        <router-link
          :to="{ name: 'Landing' }"
          class="mt-6 min-h-[44px] inline-flex items-center gap-2 font-semibold hover:underline py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
          style="color: var(--public-accent)"
        >
          <span class="material-icons text-lg">arrow_back</span>
          Back to home
        </router-link>
      </div>
    </div>

    <template v-else-if="data">
      <!-- Sticky nav -->
      <nav class="sticky top-0 z-50 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <a
            href="#"
            class="flex items-center gap-3 min-h-[44px] rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
            aria-label="Scroll to top"
            @click.prevent="scrollToTop"
          >
            <div
              v-if="data.logo_url"
              class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden shrink-0 ring-1 ring-slate-200 dark:ring-slate-700"
            >
              <img :src="data.logo_url" :alt="data.name" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center shrink-0"
              style="background-color: var(--public-accent)"
            >
              <span class="material-icons text-white text-2xl sm:text-3xl">restaurant</span>
            </div>
            <span class="font-bold text-lg tracking-tight text-charcoal dark:text-white truncate max-w-[180px] sm:max-w-none">
              {{ data.name }}
            </span>
          </a>
          <div class="flex items-center gap-4">
            <div class="hidden sm:flex items-center gap-6">
              <a
                href="#"
                class="min-h-[44px] min-w-[44px] inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-400 transition-colors hover:opacity-80 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                style="color: var(--public-accent)"
                aria-label="Scroll to top"
                @click.prevent="scrollToTop"
              >Home</a>
              <a href="#menu" class="min-h-[44px] inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-400 transition-colors hover:opacity-80 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" style="color: var(--public-accent)">Menu</a>
              <a href="#about" class="min-h-[44px] inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-400 transition-colors hover:opacity-80 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" style="color: var(--public-accent)">About</a>
              <a href="#reviews" class="min-h-[44px] inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-400 transition-colors hover:opacity-80 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" style="color: var(--public-accent)">Reviews</a>
            </div>
            <div v-if="data.languages?.length > 1" class="flex flex-wrap gap-1.5">
              <button
                v-for="loc in data.languages"
                :key="loc"
                type="button"
                class="min-h-[44px] min-w-[44px] flex items-center justify-center px-2.5 py-2.5 rounded-lg text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                :class="locale === loc
                  ? 'text-white'
                  : 'bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-zinc-600'"
                :style="locale === loc ? { backgroundColor: 'var(--public-accent)' } : undefined"
                @click="setLocale(loc)"
              >
                {{ loc.toUpperCase() }}
              </button>
            </div>
          </div>
        </div>
      </nav>

      <!-- Hero -->
      <section class="relative min-h-[50vh] sm:min-h-[60vh] flex items-end justify-center overflow-hidden">
        <template v-if="data.banner_url">
          <img
            :src="data.banner_url"
            :alt="data.name"
            class="absolute inset-0 w-full h-full object-cover"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-charcoal/90 via-charcoal/40 to-transparent" />
        </template>
        <div
          v-else
          class="absolute inset-0 opacity-95"
          style="background: linear-gradient(135deg, var(--public-accent) 0%, var(--public-accent) 50%, rgba(0,0,0,0.3) 100%);"
        />
        <div class="relative z-10 w-full max-w-6xl mx-auto px-4 sm:px-6 pb-12 sm:pb-16 pt-24">
          <div v-if="data.logo_url" class="mb-4 sm:mb-6" data-testid="public-hero-logo">
            <img
              :src="data.logo_url"
              :alt="data.name"
              class="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 rounded-2xl object-cover ring-2 ring-white/50 shadow-xl"
            />
          </div>
          <span class="font-semibold tracking-[0.2em] uppercase text-xs sm:text-sm block mb-2 text-white/90" style="color: var(--public-accent)">
            {{ data.name }}
          </span>
          <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-white tracking-tight drop-shadow-lg">
            {{ data.name }}
          </h1>
          <p class="mt-3 text-white/90 text-lg max-w-2xl">
            {{ data.description ? truncateDescription(data.description, 120) : 'Discover our menu and story.' }}
          </p>
          <a
            href="#menu"
            class="mt-6 min-h-[44px] inline-flex items-center gap-2 text-white font-semibold py-3 px-6 rounded-lg shadow-lg transition-all hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
            style="background-color: var(--public-accent); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);"
          >
            Explore Menu
            <span class="material-icons text-xl">arrow_forward</span>
          </a>
        </div>
        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/80 animate-bounce">
          <span class="material-icons">expand_more</span>
        </div>
      </section>

      <!-- About -->
      <section id="about" class="py-16 sm:py-24 bg-cream/50 dark:bg-zinc-900/50 border-y border-slate-100 dark:border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
          <h2 class="text-2xl sm:text-3xl font-bold text-charcoal dark:text-white flex items-center gap-2 mb-6">
            <span class="w-2 h-8 rounded-full shrink-0" style="background-color: var(--public-accent)" aria-hidden="true"></span>
            About
          </h2>
          <div class="max-w-3xl space-y-8">
            <div>
              <p
                v-if="data.description"
                class="text-slate-600 dark:text-slate-300 text-lg leading-relaxed whitespace-pre-wrap"
              >
                {{ data.description }}
              </p>
              <p v-else class="text-slate-500 dark:text-slate-400 italic">No description yet.</p>
            </div>
            <!-- Opening hours: only when set -->
            <div v-if="displayHours.length" class="pt-4 border-t border-slate-200 dark:border-slate-700">
              <h3 class="text-lg font-semibold text-charcoal dark:text-white flex items-center gap-2 mb-3">
                <span class="material-icons text-xl" style="color: var(--public-accent)">schedule</span>
                Opening hours
              </h3>
              <ul class="space-y-1.5 text-slate-600 dark:text-slate-300" role="list">
                <li
                  v-for="row in displayHours"
                  :key="row.day"
                  class="flex flex-wrap items-baseline gap-2 min-h-[44px] sm:min-h-0 sm:py-0.5"
                >
                  <span class="w-24 sm:w-28 shrink-0 font-medium text-charcoal dark:text-white">{{ row.label }}</span>
                  <span>{{ row.text }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <!-- Menu -->
      <section id="menu" class="py-16 sm:py-24">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-10">
            <h2 class="text-2xl sm:text-3xl font-bold text-charcoal dark:text-white flex items-center gap-2">
            <span class="w-2 h-8 rounded-full shrink-0" style="background-color: var(--public-accent)" aria-hidden="true"></span>
            Our Menu
            </h2>
            <span
              v-if="data.menu_items?.length"
              class="text-sm font-medium px-3 py-1 rounded-full"
              style="background-color: color-mix(in srgb, var(--public-accent) 10%, transparent); color: var(--public-accent)"
            >
              {{ data.menu_items.length }} {{ data.menu_items.length === 1 ? 'item' : 'items' }}
            </span>
          </div>

          <ul v-if="data.menu_items?.length" class="space-y-4">
            <li
              v-for="item in data.menu_items"
              :key="item.uuid"
              class="group relative bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 sm:p-6 shadow-sm hover:shadow-md transition-shadow"
            >
              <!-- Not Available overlay: visible but clearly marked -->
              <div
                v-if="item.is_available === false"
                class="absolute inset-0 rounded-xl bg-slate-900/40 dark:bg-slate-950/50 flex items-center justify-center z-10 min-h-[44px]"
                aria-live="polite"
              >
                <span
                  class="inline-flex items-center gap-2 min-h-[44px] min-w-[44px] px-4 py-2 rounded-lg bg-amber-500 text-white font-semibold text-sm shadow-lg"
                  style="touch-action: manipulation; -webkit-tap-highlight-color: transparent;"
                >
                  <span class="material-icons text-lg" aria-hidden="true">info</span>
                  Not Available
                </span>
              </div>
              <div class="flex gap-4">
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-lg bg-slate-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                  <span class="material-icons text-2xl sm:text-3xl opacity-70" style="color: var(--public-accent)">restaurant</span>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2 gap-y-1">
                    <h3 class="font-bold text-lg text-charcoal dark:text-white transition-colors group-hover:[color:var(--public-accent)]">
                      {{ item.name || 'Untitled' }}
                    </h3>
                    <span
                      v-for="tag in (item.tags || [])"
                      :key="tag.uuid"
                      class="inline-flex items-center justify-center w-8 h-8 rounded-full shrink-0 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary"
                      :style="tag.color ? { backgroundColor: `${tag.color}20`, color: tag.color } : undefined"
                      :title="tag.text"
                      :aria-label="tag.text ? `${tag.text} tag` : 'Tag'"
                    >
                      <span v-if="tag.icon" class="material-icons text-lg">{{ tag.icon }}</span>
                      <span v-else class="material-icons text-lg">label</span>
                    </span>
                  </div>
                  <p
                    v-if="item.description"
                    class="mt-1 text-sm text-slate-500 dark:text-slate-400 leading-relaxed"
                  >
                    {{ item.description }}
                  </p>
                  <p
                    v-if="item.price != null && !Number.isNaN(Number(item.price))"
                    class="mt-1.5 text-sm font-medium text-charcoal dark:text-white"
                  >
                    {{ formatCurrency(Number(item.price), data.currency) }}
                  </p>
                </div>
              </div>
            </li>
          </ul>

          <div
            v-else
            class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-12 text-center"
          >
            <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">restaurant_menu</span>
            <p class="mt-4 text-slate-500 dark:text-slate-400">No menu items yet.</p>
          </div>
        </div>
      </section>

      <!-- Reviews (approved feedbacks) -->
      <section id="reviews" class="py-16 sm:py-24 bg-cream/50 dark:bg-zinc-900/50 border-y border-slate-100 dark:border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
          <h2 class="text-2xl sm:text-3xl font-bold text-charcoal dark:text-white flex items-center gap-2 mb-6">
            <span class="w-2 h-8 rounded-full shrink-0" style="background-color: var(--public-accent)" aria-hidden="true"></span>
            What people say
          </h2>

          <ul v-if="data.feedbacks?.length" class="space-y-6">
            <li
              v-for="fb in data.feedbacks"
              :key="fb.uuid"
              class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-6"
            >
              <div class="flex items-center gap-0.5 mb-2" :aria-label="`Rating: ${fb.rating} out of 5`">
                <span
                  v-for="star in 5"
                  :key="star"
                  class="material-icons text-xl"
                  :class="star <= (fb.rating || 0) ? 'text-amber-500' : 'text-slate-200 dark:text-slate-600'"
                >
                  {{ star <= (fb.rating || 0) ? 'star' : 'star_border' }}
                </span>
              </div>
              <p class="text-charcoal dark:text-white whitespace-pre-wrap">{{ fb.text || '—' }}</p>
              <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                — {{ fb.name || 'Anonymous' }}
                <span v-if="fb.created_at" class="ml-1">· {{ formatDate(fb.created_at) }}</span>
              </p>
            </li>
          </ul>

          <div
            v-else
            class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
          >
            <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">rate_review</span>
            <p class="mt-4 text-slate-500 dark:text-slate-400">No reviews yet. Be the first to leave feedback below.</p>
          </div>

          <!-- Submit feedback form -->
          <div id="feedback" class="mt-10 sm:mt-12">
            <h3 class="text-xl font-bold text-charcoal dark:text-white mb-4">Leave your feedback</h3>
            <form
              novalidate
              class="max-w-xl space-y-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-6"
              @submit.prevent="submitFeedback"
            >
              <p v-if="feedbackError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ feedbackError }}</p>
              <p v-if="feedbackSuccess" class="text-sm text-green-600 dark:text-green-400" role="status">{{ feedbackSuccess }}</p>

              <div>
                <label for="feedback-rating" class="block text-sm font-medium text-charcoal dark:text-white mb-1.5">Rating (1–5) <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-1 min-h-[44px]">
                  <button
                    v-for="r in 5"
                    :key="r"
                    type="button"
                    class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary"
                    :class="feedbackRating === r ? 'opacity-100' : 'opacity-50 hover:opacity-80'"
                    :style="feedbackRating === r ? { color: 'var(--public-accent)' } : undefined"
                    :aria-label="`${r} star${r > 1 ? 's' : ''}`"
                    :aria-pressed="feedbackRating === r"
                    @click="feedbackRating = r"
                  >
                    <span class="material-icons text-4xl">star</span>
                  </button>
                </div>
                <p v-if="fieldErrors.rating" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ fieldErrors.rating }}</p>
              </div>

              <div>
                <label for="feedback-name" class="block text-sm font-medium text-charcoal dark:text-white mb-1.5">Your name <span class="text-red-500">*</span></label>
                <input
                  id="feedback-name"
                  v-model="feedbackName"
                  type="text"
                  autocomplete="name"
                  maxlength="255"
                  class="w-full min-h-[44px] px-3 py-2 rounded-lg border bg-white dark:bg-zinc-800 text-charcoal dark:text-white border-slate-300 dark:border-slate-600 focus:ring-2 focus:ring-offset-1 focus:ring-primary focus:border-primary"
                  :aria-invalid="!!fieldErrors.name"
                  :aria-describedby="fieldErrors.name ? 'feedback-name-error' : undefined"
                />
                <p id="feedback-name-error" v-if="fieldErrors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ fieldErrors.name }}</p>
              </div>

              <div>
                <label for="feedback-text" class="block text-sm font-medium text-charcoal dark:text-white mb-1.5">Your message <span class="text-red-500">*</span></label>
                <textarea
                  id="feedback-text"
                  v-model="feedbackText"
                  rows="4"
                  maxlength="65535"
                  class="w-full min-h-[44px] px-3 py-2 rounded-lg border bg-white dark:bg-zinc-800 text-charcoal dark:text-white border-slate-300 dark:border-slate-600 focus:ring-2 focus:ring-offset-1 focus:ring-primary focus:border-primary resize-y"
                  :aria-invalid="!!fieldErrors.text"
                  :aria-describedby="fieldErrors.text ? 'feedback-text-error' : undefined"
                />
                <p id="feedback-text-error" v-if="fieldErrors.text" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ fieldErrors.text }}</p>
              </div>

              <button
                type="submit"
                class="min-h-[44px] px-6 py-2.5 font-semibold rounded-lg text-white transition-opacity hover:opacity-90 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                style="background-color: var(--public-accent)"
                :disabled="feedbackSubmitting"
              >
                {{ feedbackSubmitting ? 'Sending…' : 'Send feedback' }}
              </button>
            </form>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="py-8 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900/50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded flex items-center justify-center" style="background-color: var(--public-accent)">
              <span class="material-icons text-white text-sm">restaurant</span>
            </div>
            <span class="text-sm font-semibold text-charcoal dark:text-white">{{ data.name }}</span>
          </div>
          <p class="text-xs text-slate-400 dark:text-slate-500">Powered by RMS</p>
        </div>
      </footer>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { restaurantService, feedbackService, normalizeApiError } from '@/services'
import PublicRestaurant from '@/models/PublicRestaurant.js'
import { formatOperatingHoursForDisplay } from '@/utils/availability'
import { formatCurrency, formatDate } from '@/utils/format'

const props = defineProps({
  slug: { type: String, default: '' },
})

const route = useRoute()
const slug = computed(() => props.slug || route.params.slug || '')
const locale = ref(route.query.locale ?? '')
const loading = ref(true)
const error = ref(null)
const data = ref(null)

// Feedback form (public submit)
const feedbackRating = ref(0)
const feedbackName = ref('')
const feedbackText = ref('')
const fieldErrors = ref({})
const feedbackError = ref('')
const feedbackSuccess = ref('')
const feedbackSubmitting = ref(false)

const displayHours = computed(() => formatOperatingHoursForDisplay(data.value?.operating_hours ?? null))

const DEFAULT_ACCENT = '#ee4b2b'

const publicAccentStyle = computed(() => ({
  '--public-accent': data.value?.primary_color || DEFAULT_ACCENT,
}))

function truncateDescription(text, maxLen) {
  const t = (text || '').trim().replace(/\s+/g, ' ')
  if (t.length <= maxLen) return t
  return t.slice(0, maxLen).trim() + '…'
}

async function fetchRestaurant() {
  if (!slug.value) {
    error.value = 'Missing restaurant slug.'
    loading.value = false
    return
  }
  loading.value = true
  error.value = null
  data.value = null
  try {
    const params = locale.value ? { locale: locale.value } : {}
    const res = await restaurantService.getPublicRestaurant(slug.value, params)
    const model = PublicRestaurant.fromApi(res)
    data.value = model.toJSON()
    if (data.value && !locale.value) {
      locale.value = data.value.locale ?? data.value.default_locale ?? 'en'
    }
  } catch (e) {
    if (e?.response?.status === 404) {
      error.value = 'This restaurant does not exist or the link is wrong.'
    } else {
      error.value = normalizeApiError(e).message ?? 'Failed to load restaurant.'
    }
  } finally {
    loading.value = false
  }
}

function setLocale(loc) {
  locale.value = loc
  fetchRestaurant()
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function validateFeedbackForm() {
  const err = {}
  const r = feedbackRating.value
  if (r == null || Number(r) < 1 || Number(r) > 5) {
    err.rating = 'Please choose a rating from 1 to 5.'
  }
  const name = (feedbackName.value ?? '').trim()
  if (!name) err.name = 'Your name is required.'
  else if (name.length > 255) err.name = 'Name must be at most 255 characters.'
  const text = (feedbackText.value ?? '').trim()
  if (!text) err.text = 'Your message is required.'
  else if (text.length > 65535) err.text = 'Message is too long.'
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

async function submitFeedback() {
  feedbackError.value = ''
  feedbackSuccess.value = ''
  fieldErrors.value = {}
  if (!validateFeedbackForm()) return
  feedbackSubmitting.value = true
  try {
    const res = await feedbackService.submitFeedback(slug.value, {
      rating: Number(feedbackRating.value),
      text: (feedbackText.value ?? '').trim(),
      name: (feedbackName.value ?? '').trim(),
    })
    feedbackSuccess.value = res?.message ?? 'Thank you for your feedback.'
    feedbackRating.value = 0
    feedbackName.value = ''
    feedbackText.value = ''
  } catch (e) {
    const normalized = normalizeApiError(e)
    if (e?.response?.status === 429) {
      feedbackError.value = 'Too many submissions. Please try again in a minute.'
    } else if (e?.response?.status === 422 && e?.response?.data?.errors) {
      const errors = e.response.data.errors
      const err = {}
      if (errors.rating?.[0]) err.rating = errors.rating[0]
      if (errors.name?.[0]) err.name = errors.name[0]
      if (errors.text?.[0]) err.text = errors.text[0]
      fieldErrors.value = err
      feedbackError.value = normalized.message
    } else {
      feedbackError.value = normalized.message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    feedbackSubmitting.value = false
  }
}

watch(slug, () => {
  locale.value = route.query.locale ?? ''
  fetchRestaurant()
}, { immediate: true })
</script>
