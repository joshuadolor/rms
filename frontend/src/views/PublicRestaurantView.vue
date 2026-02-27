<template>
  <!-- When showing Template1/Template2, do NOT add rms-template-1/2 so the old CSS does not override the reference template styling -->
  <div
    class="rms-public public-restaurant-page"
    :class="data ? 'rms-public-vue' : templateClass"
    :style="wrapperStyle"
  >
    <!-- Loading -->
    <div v-if="loading" class="rms-public__loading">
      <span class="material-icons rms-public__spinner" aria-hidden="true">sync</span>
      <p class="rms-public__loading-text">Loading…</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rms-public__error">
      <div class="rms-public__error-card">
        <span class="material-icons rms-public__error-icon" aria-hidden="true">restaurant</span>
        <h1 class="rms-public__error-title">Restaurant not found</h1>
        <p class="rms-public__error-message">{{ error }}</p>
        <router-link
          :to="{ name: 'Landing' }"
          class="rms-public__error-link"
        >
          <span class="material-icons" aria-hidden="true">arrow_back</span>
          Back to home
        </router-link>
      </div>
    </div>

    <template v-else-if="data">
      <Template1
        v-if="templateComponent === 'Template1'"
        :restaurant="data"
        :languages="data.languages || []"
        :current-locale="locale"
        @select-locale="onSelectLocale"
      >
        <template #feedback-form>
          <section id="feedback" class="rms-feedback rms-feedback--template-1" aria-labelledby="feedback-heading">
            <h3 id="feedback-heading" class="rms-feedback__title">Leave your feedback</h3>
            <form
              novalidate
              class="rms-feedback__form"
              @submit.prevent="submitFeedback"
            >
              <p v-if="feedbackError" class="rms-feedback__error" role="alert">{{ feedbackError }}</p>
              <p v-if="feedbackSuccess" class="rms-feedback__success" role="status">{{ feedbackSuccess }}</p>

              <div class="rms-feedback__field">
                <label for="feedback-rating" class="rms-feedback__label">Rating (1–5) <span class="rms-feedback__required">*</span></label>
                <div class="rms-feedback__stars" role="group" aria-label="Rating 1 to 5 stars">
                  <button
                    v-for="r in 5"
                    :key="r"
                    type="button"
                    class="rms-feedback__star-btn"
                    :class="{ 'rms-feedback__star-btn--active': r <= feedbackRating }"
                    :aria-label="`${r} star${r > 1 ? 's' : ''}`"
                    :aria-pressed="feedbackRating === r"
                    @click="feedbackRating = r"
                  >
                    <span class="material-icons" aria-hidden="true">star</span>
                  </button>
                </div>
                <p v-if="fieldErrors.rating" class="rms-feedback__field-error">{{ fieldErrors.rating }}</p>
              </div>

              <div class="rms-feedback__field">
                <label for="feedback-name" class="rms-feedback__label">Your name <span class="rms-feedback__required">*</span></label>
                <input
                  id="feedback-name"
                  v-model="feedbackName"
                  type="text"
                  autocomplete="name"
                  maxlength="255"
                  class="rms-feedback__input"
                  :aria-invalid="!!fieldErrors.name"
                  :aria-describedby="fieldErrors.name ? 'feedback-name-error' : undefined"
                />
                <p id="feedback-name-error" v-if="fieldErrors.name" class="rms-feedback__field-error">{{ fieldErrors.name }}</p>
              </div>

              <div class="rms-feedback__field">
                <label for="feedback-text" class="rms-feedback__label">Your message <span class="rms-feedback__required">*</span></label>
                <textarea
                  id="feedback-text"
                  v-model="feedbackText"
                  rows="4"
                  maxlength="65535"
                  class="rms-feedback__input rms-feedback__textarea"
                  :aria-invalid="!!fieldErrors.text"
                  :aria-describedby="fieldErrors.text ? 'feedback-text-error' : undefined"
                />
                <p id="feedback-text-error" v-if="fieldErrors.text" class="rms-feedback__field-error">{{ fieldErrors.text }}</p>
              </div>

              <button
                type="submit"
                class="rms-feedback__submit"
                :disabled="feedbackSubmitting"
              >
                {{ feedbackSubmitting ? 'Sending…' : 'Send feedback' }}
              </button>
            </form>
          </section>
        </template>
      </Template1>
      <Template2
        v-else
        :restaurant="data"
        :languages="data.languages || []"
        :current-locale="locale"
        @select-locale="onSelectLocale"
      >
        <template #feedback-form>
          <section id="feedback" class="rms-feedback rms-feedback--template-2" aria-labelledby="feedback-heading">
            <h3 id="feedback-heading" class="rms-feedback__title">Leave your feedback</h3>
            <form
              novalidate
              class="rms-feedback__form"
              @submit.prevent="submitFeedback"
            >
              <p v-if="feedbackError" class="rms-feedback__error" role="alert">{{ feedbackError }}</p>
              <p v-if="feedbackSuccess" class="rms-feedback__success" role="status">{{ feedbackSuccess }}</p>

              <div class="rms-feedback__field">
                <label for="feedback-rating" class="rms-feedback__label">Rating (1–5) <span class="rms-feedback__required">*</span></label>
                <div class="rms-feedback__stars" role="group" aria-label="Rating 1 to 5 stars">
                  <button
                    v-for="r in 5"
                    :key="r"
                    type="button"
                    class="rms-feedback__star-btn"
                    :class="{ 'rms-feedback__star-btn--active': r <= feedbackRating }"
                    :aria-label="`${r} star${r > 1 ? 's' : ''}`"
                    :aria-pressed="feedbackRating === r"
                    @click="feedbackRating = r"
                  >
                    <span class="material-icons" aria-hidden="true">star</span>
                  </button>
                </div>
                <p v-if="fieldErrors.rating" class="rms-feedback__field-error">{{ fieldErrors.rating }}</p>
              </div>

              <div class="rms-feedback__field">
                <label for="feedback-name" class="rms-feedback__label">Your name <span class="rms-feedback__required">*</span></label>
                <input
                  id="feedback-name"
                  v-model="feedbackName"
                  type="text"
                  autocomplete="name"
                  maxlength="255"
                  class="rms-feedback__input"
                  :aria-invalid="!!fieldErrors.name"
                  :aria-describedby="fieldErrors.name ? 'feedback-name-error' : undefined"
                />
                <p id="feedback-name-error" v-if="fieldErrors.name" class="rms-feedback__field-error">{{ fieldErrors.name }}</p>
              </div>

              <div class="rms-feedback__field">
                <label for="feedback-text" class="rms-feedback__label">Your message <span class="rms-feedback__required">*</span></label>
                <textarea
                  id="feedback-text"
                  v-model="feedbackText"
                  rows="4"
                  maxlength="65535"
                  class="rms-feedback__input rms-feedback__textarea"
                  :aria-invalid="!!fieldErrors.text"
                  :aria-describedby="fieldErrors.text ? 'feedback-text-error' : undefined"
                />
                <p id="feedback-text-error" v-if="fieldErrors.text" class="rms-feedback__field-error">{{ fieldErrors.text }}</p>
              </div>

              <button
                type="submit"
                class="rms-feedback__submit"
                :disabled="feedbackSubmitting"
              >
                {{ feedbackSubmitting ? 'Sending…' : 'Send feedback' }}
              </button>
            </form>
          </section>
        </template>
      </Template2>

      <!-- Mobile-only: spacer so sticky "View Menu" bar does not cover footer -->
      <div class="rms-view-menu-spacer" aria-hidden="true" />

      <!-- Mobile-only: sticky "View Menu" button -->
      <div class="rms-view-menu-bar">
        <button
          ref="viewMenuBtnRef"
          type="button"
          class="rms-view-menu-bar__btn"
          :style="stickyButtonStyle"
          aria-label="View menu"
          @click="menuModalOpen = true"
        >
          View Menu
        </button>
      </div>

      <PublicMenuModal
        v-model="menuModalOpen"
        :restaurant="data"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { restaurantService, feedbackService, normalizeApiError } from '@/services'
import PublicRestaurant from '@/models/PublicRestaurant.js'
import Template1 from '@/components/public/templates/Template1.vue'
import Template2 from '@/components/public/templates/Template2.vue'
import PublicMenuModal from '@/components/public/PublicMenuModal.vue'

// Load template CSS (same class names as Blade)
import '@/assets/public-restaurant-templates.css'

const props = defineProps({
  slug: { type: String, default: '' },
})

const route = useRoute()
const router = useRouter()

function getInitialLocale() {
  try {
    const el = document.getElementById('app')
    const dataLocale = el?.getAttribute?.('data-locale')
    if (dataLocale != null && String(dataLocale).trim() !== '') return String(dataLocale).trim()
  } catch (_) {}
  return route.query.locale ?? ''
}

const slug = computed(() => props.slug || route.params.slug || '')
const locale = ref(getInitialLocale())
const loading = ref(true)
const error = ref(null)
const data = ref(null)

const feedbackRating = ref(0)
const feedbackName = ref('')
const feedbackText = ref('')
const fieldErrors = ref({})
const feedbackError = ref('')
const feedbackSuccess = ref('')
const feedbackSubmitting = ref(false)

const DEFAULT_ACCENT = '#2563eb'

/** Default document title (from index.html). Restored when leaving public page or on error. */
const DEFAULT_DOCUMENT_TITLE = 'RMS — Restaurant Management System'
/** App name for public page title: "[Restaurant name] | [App name]". Configurable via VITE_APP_NAME. */
const APP_NAME = import.meta.env.VITE_APP_NAME ?? 'RMS'

const menuModalOpen = ref(false)
/** Only auto-open View Menu modal once per page load on mobile. */
const hasAutoOpenedModal = ref(false)

/** Truncate description for meta (SEO best practice ~155 chars). */
function truncateForMeta(text, maxLen = 155) {
  if (!text || typeof text !== 'string') return ''
  const t = text.trim()
  if (t.length <= maxLen) return t
  return t.slice(0, maxLen - 1).trimEnd() + '…'
}

/** Ensure meta element exists (by name or property), set content, mark for cleanup. */
function setMeta(nameOrProperty, content, isProperty = false) {
  const attr = isProperty ? 'property' : 'name'
  let el = document.querySelector(`meta[${attr}="${nameOrProperty}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, nameOrProperty)
    el.setAttribute('data-rms-seo', '')
    document.head.appendChild(el)
  }
  el.setAttribute('content', content || '')
}

/** Remove all meta tags we added for public page SEO. */
function removeSeoMeta() {
  document.querySelectorAll('meta[data-rms-seo]').forEach((el) => el.remove())
}

/** Apply SEO title and meta from restaurant data. */
function applyPublicPageSeo(restaurant) {
  if (!restaurant?.name) return
  const title = `${restaurant.name} | ${APP_NAME}`
  document.title = title
  const description = truncateForMeta(restaurant.description)
  if (description) setMeta('description', description)
  setMeta('og:title', title, true)
  setMeta('og:description', description, true)
  setMeta('og:type', 'website', true)
  const canonicalUrl = typeof window !== 'undefined' ? window.location.href : ''
  if (canonicalUrl) setMeta('og:url', canonicalUrl, true)
  const image = restaurant.banner_url || restaurant.logo_url
  if (image) setMeta('og:image', image, true)
  setMeta('twitter:card', image ? 'summary_large_image' : 'summary')
  setMeta('twitter:title', title)
  setMeta('twitter:description', description)
  if (image) setMeta('twitter:image', image)
}

/** Restore default title and remove our meta tags. */
function clearPublicPageSeo() {
  document.title = DEFAULT_DOCUMENT_TITLE
  removeSeoMeta()
}
const viewMenuBtnRef = ref(null)

const wrapperStyle = computed(() => ({
  '--rms-accent': data.value?.primary_color || DEFAULT_ACCENT,
}))

const stickyButtonStyle = computed(() => ({
  backgroundColor: data.value?.primary_color || DEFAULT_ACCENT,
}))

/** template-1 (default) vs template-2 (minimal); same as Blade resolveTemplate. */
const templateClass = computed(() => {
  const t = data.value?.template ?? 'template-1'
  if (t === 'template-2' || t === 'minimal') return 'rms-template-2'
  return 'rms-template-1'
})

/** Which template component to render (Template1 | Template2). */
const templateComponent = computed(() => {
  const t = data.value?.template ?? 'template-1'
  if (t === 'template-2' || t === 'minimal') return 'Template2'
  return 'Template1'
})
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
    if (data.value) {
      locale.value = data.value.locale ?? data.value.default_locale ?? locale.value ?? 'en'
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

function onSelectLocale(code) {
  if (!code || typeof code !== 'string') return
  locale.value = code
  router.replace({
    name: route.name,
    params: route.params,
    query: { ...route.query, locale: code },
  })
  fetchRestaurant()
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
  const fromQuery = route.query.locale ?? ''
  if (fromQuery) locale.value = fromQuery
  fetchRestaurant()
}, { immediate: true })

watch(menuModalOpen, (open) => {
  if (!open) {
    nextTick(() => viewMenuBtnRef.value?.focus())
  }
})

watch(data, (val) => {
  if (val?.name) {
    applyPublicPageSeo(val)
  } else {
    clearPublicPageSeo()
  }
  // On mobile, auto-open View Menu modal once when restaurant data has loaded.
  if (val && !hasAutoOpenedModal.value && typeof window !== 'undefined' && window.innerWidth < 768) {
    hasAutoOpenedModal.value = true
    menuModalOpen.value = true
  }
}, { immediate: true })

onBeforeUnmount(clearPublicPageSeo)
</script>

<style scoped>
.rms-public__loading,
.rms-public__error {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.rms-public__spinner {
  font-size: 2.5rem;
  color: var(--rms-accent, #2563eb);
  animation: rms-spin 1s linear infinite;
}

.rms-public__loading-text {
  margin-top: 0.75rem;
  color: #64748b;
}

.rms-public__error-card {
  max-width: 28rem;
  padding: 2rem;
  text-align: center;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
}

.rms-public__error-icon {
  font-size: 3rem;
  color: #94a3b8;
}

.rms-public__error-title {
  margin-top: 1rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
}

.rms-public__error-message {
  margin-top: 0.5rem;
  color: #64748b;
}

.rms-public__error-link {
  margin-top: 1.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: var(--rms-accent, #2563eb);
  text-decoration: none;
  min-height: 44px;
}

.rms-public__error-link:hover {
  text-decoration: underline;
}

@keyframes rms-spin {
  to { transform: rotate(360deg); }
}

.rms-opening-hours {
  list-style: none;
  padding: 0;
  margin: 0;
}

.rms-opening-hours__row {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.25rem 0;
  min-height: 44px;
  align-items: center;
}

.rms-opening-hours__day {
  font-weight: 600;
  color: #0f172a;
  min-width: 6rem;
}

.rms-opening-hours__slots {
  color: #475569;
}

/* Mobile-only: spacer so fixed bar doesn't cover footer (height matches bar) */
.rms-view-menu-spacer {
  display: block;
  height: 56px;
  flex-shrink: 0;
}
@media (min-width: 768px) {
  .rms-view-menu-spacer {
    display: none;
  }
}

/* Mobile-only: sticky "View Menu" bar */
.rms-view-menu-bar {
  display: flex;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  padding: 0.5rem 1rem;
  padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
  background: #fff;
  border-top: 1px solid #e2e8f0;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
}
@media (min-width: 768px) {
  .rms-view-menu-bar {
    display: none;
  }
}

.rms-view-menu-bar__btn {
  width: 100%;
  min-height: 44px;
  padding: 0.625rem 1rem;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: opacity 0.15s;
}
.rms-view-menu-bar__btn:hover {
  opacity: 0.9;
}
.rms-view-menu-bar__btn:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
</style>
