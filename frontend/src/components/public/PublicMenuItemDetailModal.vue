<template>
  <Teleport to="body">
    <div
      v-show="modelValue"
      class="rms-item-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="item ? 'rms-item-detail-title' : undefined"
      aria-label="Menu item details"
      :aria-hidden="!modelValue"
      @keydown.escape="close"
    >
      <div class="rms-item-detail-modal__backdrop" aria-hidden="true" @click="close" />
      <div
        class="rms-item-detail-modal__panel"
        ref="panelRef"
        @keydown="onPanelKeydown"
      >
        <button
          ref="closeBtnRef"
          type="button"
          class="rms-item-detail-modal__close"
          :style="primaryButtonStyle"
          aria-label="Close"
          @click="close"
        >
          <span class="material-icons" aria-hidden="true">close</span>
        </button>

        <div
          class="rms-item-detail-modal__scroll"
          :class="{ 'rms-item-detail-modal__scroll--body-empty': !hasBodyContent }"
          v-if="item"
        >
          <!-- Card: image on top, dark gradient overlay, category + name on gradient -->
          <div class="rms-item-detail-modal__card">
            <div
              class="rms-item-detail-modal__card-bg"
              :class="{ 'rms-item-detail-modal__card-bg--has-image': item.image_url }"
            >
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="''"
                class="rms-item-detail-modal__card-img"
                aria-hidden="true"
              />
            </div>
            <div class="rms-item-detail-modal__card-gradient" aria-hidden="true" />
            <div class="rms-item-detail-modal__card-text">
              <p v-if="categoryName" class="rms-item-detail-modal__card-category ">{{ categoryName }}</p>
              <h2 id="rms-item-detail-title" class="rms-item-detail-modal__card-name ">{{ item.name || 'Untitled' }}</h2>
            </div>
          </div>

          <div v-if="hasBodyContent" class="rms-item-detail-modal__body mb-5">
          <!-- Price: simple/combo = single price; with_variants = "From $X.XX" + optional variant list -->
          <div class="rms-item-detail-modal__price-wrap">
            <template v-if="item.type === 'with_variants'">
              <span v-if="lowestVariantPrice != null" class="rms-item-detail-modal__price" :style="primaryTextStyle">
                From {{ formatPrice(lowestVariantPrice) }}
              </span>
              <ul v-if="variantSkus(item).length" class="rms-item-detail-modal__variant-list" aria-label="Size and price options">
                <li
                  v-for="sku in variantSkus(item)"
                  :key="sku.uuid"
                  class="rms-item-detail-modal__variant-row"
                >
                  <span class="rms-item-detail-modal__variant-label">{{ variantSkuLabel(sku) }}</span>
                  <span v-if="sku.price != null" class="rms-item-detail-modal__price" :style="primaryTextStyle">{{ formatPrice(sku.price) }}</span>
                </li>
              </ul>
            </template>
            <template v-else>
              <span v-if="itemPrice(item) != null" class="rms-item-detail-modal__price" :style="primaryTextStyle">{{ formatPrice(itemPrice(item)) }}</span>
              <span v-else-if="item.is_available === false" class="rms-item-detail-modal__muted">Not available</span>
            </template>
          </div>

          <p v-if="item.description" class="rms-item-detail-modal__desc">{{ item.description }}</p>

          <ul v-if="item.type === 'combo' && comboEntries(item).length" class="rms-item-detail-modal__combo-list" aria-label="Combo contents">
            <li v-for="(entry, eIdx) in comboEntries(item)" :key="eIdx">
              {{ entry.name }} × {{ entry.quantity }}{{ entry.modifier_label ? ` (${entry.modifier_label})` : '' }}
            </li>
          </ul>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, reactive } from 'vue'
import { usePublicMenuDisplay } from '@/composables/usePublicMenuDisplay'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
  categoryName: { type: String, default: 'Menu' },
  primaryColor: { type: String, default: '' },
  primary_color: { type: String, default: '' },
  currency: { type: String, default: 'USD' },
})

const emit = defineEmits(['update:modelValue'])

const panelRef = ref(null)
const closeBtnRef = ref(null)

const primary = computed(() => props.primaryColor || props.primary_color || '#2563eb')
const primaryButtonStyle = computed(() => ({ backgroundColor: primary.value, borderColor: primary.value, color: '#fff' }))
const primaryTextStyle = computed(() => ({ color: primary.value }))

const displayProps = reactive({
  menuGroups: [],
  menuItems: [],
  currency: 'USD',
})
watch(
  () => [props.item, props.currency],
  ([item, currency]) => {
    displayProps.menuItems = item ? [item] : []
    displayProps.currency = currency || 'USD'
  },
  { immediate: true }
)

const {
  formatPrice,
  itemPrice,
  variantSkus,
  variantSkuLabel,
  comboEntries,
} = usePublicMenuDisplay(displayProps)

const lowestVariantPrice = computed(() => {
  const item = props.item
  if (!item || item.type !== 'with_variants') return null
  const skus = variantSkus(item)
  if (!skus.length) return null
  const prices = skus.map((s) => (s.price != null ? Number(s.price) : null)).filter((p) => p != null)
  return prices.length ? Math.min(...prices) : null
})

const hasBodyContent = computed(() => {
  const item = props.item
  if (!item) return false
  if (item.description) return true
  if (item.type === 'combo' && comboEntries(item).length) return true
  if (item.type === 'with_variants') return lowestVariantPrice.value != null || variantSkus(item).length > 0
  return itemPrice(item) != null || item.is_available === false
})

const FOCUSABLE_SELECTOR = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function getFocusables(container) {
  if (!container) return []
  return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
    (el) => el.offsetParent != null && !el.hasAttribute('aria-hidden')
  )
}

function onPanelKeydown(e) {
  if (e.key !== 'Tab' || !props.modelValue || !panelRef.value) return
  const focusables = getFocusables(panelRef.value)
  if (focusables.length === 0) return
  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  if (e.shiftKey) {
    if (document.activeElement === first) {
      e.preventDefault()
      last.focus()
    }
  } else {
    if (document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    document.body.style.overflow = 'hidden'
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = `${scrollbarWidth}px`
    }
    nextTick(() => closeBtnRef.value?.focus())
  } else {
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
  }
})

function close() {
  emit('update:modelValue', false)
}
</script>

<style scoped>
.rms-item-detail-modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
  pointer-events: none;
}

.rms-item-detail-modal[aria-hidden="false"],
.rms-item-detail-modal:not([aria-hidden]) {
  pointer-events: auto;
}

.rms-item-detail-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  pointer-events: auto;
}

.rms-item-detail-modal__panel {
  --rms-detail-accent: v-bind(primary);
  position: relative;
  width: 100%;
  max-width: 420px;
  max-height: 100vh;
  max-height: 100dvh;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  pointer-events: auto;
  overflow: hidden;
}

@media (max-width: 380px) {
  .rms-item-detail-modal__panel {
    border-radius: 0;
  }
}

.rms-item-detail-modal__close {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 2;
  min-width: 44px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 0.15s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.rms-item-detail-modal__close:hover {
  opacity: 0.9;
}

.rms-item-detail-modal__close:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

.rms-item-detail-modal__close .material-icons {
  font-size: 1.5rem;
}

.rms-item-detail-modal__scroll {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  min-height: 0;
  padding: 0;
  -webkit-overflow-scrolling: touch;
}

.rms-item-detail-modal__scroll--body-empty {
  padding-bottom: 0;
}

.rms-item-detail-modal__card {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 1rem 1rem 0 0;
  overflow: hidden;
  flex-shrink: 0;
}

@media (max-width: 380px) {
  .rms-item-detail-modal__card {
    border-radius: 0;
  }
}

.rms-item-detail-modal__card-bg {
  position: absolute;
  inset: 0;
  background: #1e293b;
}

.rms-item-detail-modal__card-bg--has-image .rms-item-detail-modal__card-img {
  opacity: 1;
}

.rms-item-detail-modal__card-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity 0.2s;
}

.rms-item-detail-modal__card-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 40%, transparent 70%);
  pointer-events: none;
}

.rms-item-detail-modal__card-text {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 1.5rem 1.25rem 1.25rem;
  padding-right: 3.5rem;
}

.rms-item-detail-modal__card-category {
  margin: 0 0 0.25rem;
  font-size: 1rem;
  font-weight: 400;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(255, 255, 255, 0.85);
}

.rms-item-detail-modal__card-name {
  margin: 0;
  font-size: 2.2rem;
  font-weight: 600;
  line-height: 1.25;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.rms-item-detail-modal__body {
  padding: 16px 20px 0;
}

@media (min-width: 640px) {
  .rms-item-detail-modal__body {
    padding-left: 20px;
    padding-right: 20px;
  }
}

.rms-item-detail-modal__price-wrap {
  margin-bottom: 0.75rem;
}

.rms-item-detail-modal__price {
  font-size: 1.125rem;
  font-weight: 600;
}

.rms-item-detail-modal__muted {
  font-size: 0.875rem;
  color: #64748b;
}

.rms-item-detail-modal__variant-list {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
}

.rms-item-detail-modal__variant-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  min-height: 44px;
  padding: 0.25rem 0;
  font-size: 0.875rem;
}

.rms-item-detail-modal__variant-label {
  color: #334155;
}

.rms-item-detail-modal__desc {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.4;
}

.rms-item-detail-modal__combo-list {
  list-style: none;
  padding-left: 1rem;
  margin: 0 0 0 0.125rem;
  border-left: 2px solid #e2e8f0;
  font-size: 0.8125rem;
  color: #64748b;
}

.rms-item-detail-modal__combo-list li {
  margin-top: 0.25rem;
}
</style>
