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
        <div class="rms-item-detail-modal__header">
          <h2 id="rms-item-detail-title" class="rms-item-detail-modal__title">
            {{ item?.name || 'Item details' }}
          </h2>
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
        </div>

        <div class="rms-item-detail-modal__scroll" v-if="item">
          <p v-if="categoryName" class="rms-item-detail-modal__category">{{ categoryName }}</p>
          <img
            v-if="item.image_url"
            :src="item.image_url"
            :alt="item.name || 'Item'"
            class="rms-item-detail-modal__img"
          />
          <h3 class="rms-item-detail-modal__name">{{ item.name || 'Untitled' }}</h3>

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
}

@media (max-width: 380px) {
  .rms-item-detail-modal__panel {
    border-radius: 0;
  }
}

.rms-item-detail-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  padding-top: 16px;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
  gap: 0.75rem;
}

.rms-item-detail-modal__title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.rms-item-detail-modal__close {
  min-width: 44px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: none;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 0.15s;
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
  padding: 16px 20px 20px;
  padding-bottom: 24px;
  -webkit-overflow-scrolling: touch;
}

@media (min-width: 640px) {
  .rms-item-detail-modal__scroll {
    padding-left: 20px;
    padding-right: 20px;
    padding-bottom: 24px;
  }
}

.rms-item-detail-modal__category {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  color: #64748b;
}

.rms-item-detail-modal__img {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.rms-item-detail-modal__name {
  margin: 0 0 0.5rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
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
