<template>
  <Teleport to="body">
    <div
      v-show="modelValue"
      class="rms-menu-modal"
      role="dialog"
      aria-modal="true"
      aria-label="Menu"
      :aria-hidden="!modelValue"
      @keydown.escape="close"
    >
      <div class="rms-menu-modal__backdrop" aria-hidden="true" @click="close" />
      <div
        class="rms-menu-modal__panel"
        ref="panelRef"
        @keydown="onPanelKeydown"
      >
        <div class="rms-menu-modal__header">
          <h2 class="rms-menu-modal__title">Menu</h2>
          <button
            ref="closeBtnRef"
            type="button"
            class="rms-menu-modal__close"
            :style="primaryButtonStyle"
            aria-label="Close menu"
            @click="close"
          >
            <span class="material-icons" aria-hidden="true">close</span>
          </button>
        </div>

        <div class="rms-menu-modal__surprise-wrap">
          <button
            type="button"
            class="rms-menu-modal__surprise"
            :class="{ 'rms-menu-modal__surprise--picking': isPicking }"
            :style="primaryButtonStyle"
            :disabled="!allItems.length || isPicking"
            aria-label="Surprise me – pick a random menu item"
            :aria-busy="isPicking"
            @click="surpriseMe"
          >
            <span class="material-icons rms-menu-modal__surprise-icon" aria-hidden="true">shuffle</span>
            Surprise me
          </button>
          <p v-if="!allItems.length" class="rms-menu-modal__no-items">No menu items</p>
        </div>

        <div class="rms-menu-modal__scroll" ref="scrollRef">
          <div class="rms-menu-modal__categories">
            <div
              v-for="(group, gIdx) in displayGroups"
              :key="gIdx"
              class="rms-menu-modal__category"
            >
              <button
                type="button"
                class="rms-menu-modal__category-head"
                :class="{ 'rms-menu-modal__category-head--open': openIndex === gIdx }"
                :aria-expanded="openIndex === gIdx"
                :aria-controls="`rms-menu-modal-panel-${gIdx}`"
                :id="`rms-menu-modal-trigger-${gIdx}`"
                @click="toggleCategory(gIdx)"
              >
                <img
                  v-if="group.image_url"
                  :src="group.image_url"
                  :alt="group.category_name || 'Category'"
                  loading="lazy"
                  class="rms-menu-modal__category-thumb"
                />
                <span class="rms-menu-modal__category-name">
                  <span :style="categoryUnavailableNow(group.availability) ? { opacity: 0.8 } : undefined">{{ group.category_name }}</span>
                  <span v-if="formatAvailabilityForDisplay(group.availability, now)" class="rms-menu-modal__category-availability">{{ formatAvailabilityForDisplay(group.availability, now) }}</span>
                </span>
                <span class="material-icons rms-menu-modal__category-icon" aria-hidden="true">
                  {{ openIndex === gIdx ? 'expand_less' : 'expand_more' }}
                </span>
              </button>
              <div
                :id="`rms-menu-modal-panel-${gIdx}`"
                class="rms-menu-modal__category-panel"
                :class="{ 'rms-menu-modal__category-panel--open': openIndex === gIdx }"
                role="region"
                :aria-labelledby="`rms-menu-modal-trigger-${gIdx}`"
                :aria-hidden="openIndex !== gIdx"
              >
                <div class="rms-menu-modal__category-panel-inner">
                <button
                  v-for="item in group.items"
                  :key="item.uuid"
                  type="button"
                  :ref="(el) => setItemRef(item.uuid, el)"
                  :data-item-uuid="item.uuid"
                  class="rms-menu-modal__item"
                  :class="{ 'rms-menu-modal__item--highlight': highlightedUuid === item.uuid }"
                  @click="onItemClick(item, group.category_name, $event)"
                >
                  <div class="rms-menu-modal__item-top">
                    <img
                      v-if="item.image_url"
                      :src="item.image_url"
                      :alt="item.name || 'Untitled'"
                      loading="lazy"
                      class="rms-menu-modal__item-thumb"
                    />
                    <div class="rms-menu-modal__item-body">
                  <div class="rms-menu-modal__item-row">
                    <span class="rms-menu-modal__item-name" :style="itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined">
                      {{ item.name || 'Untitled' }}
                      <span
                        v-for="tag in itemTags(item)"
                        :key="tag.uuid"
                        class="rms-menu-modal__tag"
                        :title="tag.text"
                        :style="tagStyle(tag)"
                        tabindex="0"
                        role="img"
                        :aria-label="tag.text"
                      >
                        <span class="material-symbols-outlined rms-menu-modal__tag-icon" aria-hidden="true">{{ tag.icon || 'label' }}</span>
                      </span>
                    </span>
                    <span v-if="item.type !== 'with_variants' && item.is_available !== false && itemPrice(item) != null" class="rms-menu-modal__item-price" :style="[primaryTextStyle, itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined]">{{ formatPrice(itemPrice(item)) }}</span>
                    <span v-else-if="item.type !== 'with_variants' && item.is_available === false" class="rms-menu-modal__item-muted">Not available</span>
                  </div>
                  <p v-if="formatAvailabilityForDisplay(item.availability, now)" class="rms-menu-modal__item-availability">{{ formatAvailabilityForDisplay(item.availability, now) }}</p>
                  <p v-if="item.description" class="rms-menu-modal__item-desc">{{ item.description }}</p>
                  <ul v-if="item.type === 'combo' && comboEntries(item).length" class="rms-menu-modal__combo-list" aria-label="Combo contents">
                    <li v-for="(entry, eIdx) in comboEntries(item)" :key="eIdx">
                      {{ entry.name }} × {{ entry.quantity }}{{ entry.modifier_label ? ` (${entry.modifier_label})` : '' }}
                    </li>
                  </ul>
                  <template v-if="item.type === 'with_variants'">
                    <p v-if="variantOptionGroupsSummary(item)" class="rms-menu-modal__item-desc">{{ variantOptionGroupsSummary(item) }}</p>
                    <ul class="rms-menu-modal__sku-list" aria-label="Size and price options">
                      <li
                        v-for="sku in variantSkus(item)"
                        :key="sku.uuid"
                        class="rms-menu-modal__sku"
                      >
                        <span class="rms-menu-modal__sku-label">{{ variantSkuLabel(sku) }}</span>
                        <span v-if="sku.price != null" class="rms-menu-modal__item-price" :style="[primaryTextStyle, itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined]">{{ formatPrice(sku.price) }}</span>
                        <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku) || 'Variant'" loading="lazy" class="rms-menu-modal__sku-img" />
                      </li>
                    </ul>
                  </template>
                    </div>
                  </div>
                </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="rms-menu-modal__footer">
          <button
            type="button"
            class="rms-menu-modal__close-btn"
            :style="primaryButtonStyle"
            aria-label="Close menu"
            @click="close"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <PublicMenuItemDetailModal
      v-model="detailOpen"
      :item="detailItem"
      :category-name="detailCategoryName"
      :primary-color="restaurant?.primary_color"
      :currency="restaurant?.currency || 'USD'"
      @update:model-value="onDetailClose"
    />
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { formatAvailabilityForDisplay, isAvailableNow } from '@/utils/availability'
import PublicMenuItemDetailModal from '@/components/public/PublicMenuItemDetailModal.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  restaurant: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const panelRef = ref(null)
const scrollRef = ref(null)
const closeBtnRef = ref(null)
const openIndex = ref(0)
const highlightedUuid = ref(null)
const itemRefs = ref({})
const isPicking = ref(false)

const detailItem = ref(null)
const detailCategoryName = ref('Menu')
const detailOpen = ref(false)
let detailTriggerEl = null

function onItemClick(item, categoryName, event) {
  detailItem.value = item
  detailCategoryName.value = categoryName || 'Menu'
  detailTriggerEl = event?.currentTarget ?? null
  detailOpen.value = true
}

function onDetailClose(open) {
  if (!open) {
    detailItem.value = null
    detailCategoryName.value = 'Menu'
    nextTick(() => {
      if (detailTriggerEl && typeof detailTriggerEl.focus === 'function') {
        detailTriggerEl.focus()
      }
      detailTriggerEl = null
    })
  }
}

const now = ref(new Date())
let nowInterval
onMounted(() => {
  nowInterval = setInterval(() => { now.value = new Date() }, 60 * 1000)
})
onUnmounted(() => {
  if (nowInterval) clearInterval(nowInterval)
})

function categoryUnavailableNow(availability) {
  return availability != null && typeof availability === 'object' && !isAvailableNow(availability, now.value)
}
function itemUnavailableNow(availability) {
  return availability != null && typeof availability === 'object' && !isAvailableNow(availability, now.value)
}

const primary = computed(() => props.restaurant?.primary_color || '#2563eb')
const primaryButtonStyle = computed(() => ({ backgroundColor: primary.value, borderColor: primary.value }))
const primaryTextStyle = computed(() => ({ color: primary.value }))

const displayGroups = computed(() => {
  const groups = props.restaurant?.menu_groups
  if (Array.isArray(groups) && groups.length) return groups
  const items = props.restaurant?.menu_items
  if (Array.isArray(items) && items.length) return [{ category_name: 'Menu', category_uuid: null, items: [...items] }]
  return []
})

const allItems = computed(() => {
  const list = []
  for (const g of displayGroups.value) {
    if (Array.isArray(g.items)) list.push(...g.items)
  }
  return list
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
    openIndex.value = displayGroups.value.length ? 0 : -1
    highlightedUuid.value = null
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

function toggleCategory(idx) {
  openIndex.value = openIndex.value === idx ? -1 : idx
}

function setItemRef(uuid, el) {
  if (el) {
    itemRefs.value[uuid] = el
  } else {
    delete itemRefs.value[uuid]
  }
}

function itemTags(item) {
  return Array.isArray(item?.tags) ? item.tags : []
}

function tagStyle(tag) {
  return { color: tag.color || 'currentColor' }
}

function formatPrice(price) {
  const n = Number(price)
  const currency = props.restaurant?.currency || 'USD'
  if (Number.isNaN(n)) return currency
  const formatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
  if (currency === 'USD') return '$' + formatted
  return formatted + ' ' + currency
}

function itemPrice(item) {
  if (!item || item.type === 'with_variants') return null
  return item.price != null ? Number(item.price) : null
}

function comboEntries(item) {
  return Array.isArray(item?.combo_entries) ? item.combo_entries : []
}

function variantOptionGroupsSummary(item) {
  const groups = Array.isArray(item?.variant_option_groups) ? item.variant_option_groups : []
  if (!groups.length) return ''
  return groups.map((g) => `${g.name || 'Option'}: ${Array.isArray(g.values) ? g.values.join(', ') : ''}`).join('; ')
}

function variantSkus(item) {
  return Array.isArray(item?.variant_skus) ? item.variant_skus : []
}

function variantSkuLabel(sku) {
  if (!sku?.option_values || typeof sku.option_values !== 'object') return ''
  const vals = Object.values(sku.option_values).filter(Boolean)
  return vals.join(', ') || '—'
}

function prefersReducedMotion() {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function surpriseMe() {
  const items = allItems.value
  if (!items.length || isPicking.value) return
  const reduced = prefersReducedMotion()
  const pickDelay = reduced ? 0 : 400 + Math.random() * 200

  isPicking.value = true
  setTimeout(() => {
    const item = items[Math.floor(Math.random() * items.length)]
    const uuid = item.uuid
    highlightedUuid.value = uuid
    const highlightDuration = reduced ? 0 : 2500
    setTimeout(() => { highlightedUuid.value = null }, highlightDuration)

    const groupIndex = displayGroups.value.findIndex((g) => Array.isArray(g.items) && g.items.some((i) => i.uuid === uuid))
    if (groupIndex >= 0) openIndex.value = groupIndex

    const scrollBehavior = reduced ? 'auto' : 'smooth'
    nextTick(() => {
      const el = itemRefs.value[uuid]
      if (el && scrollRef.value) {
        el.scrollIntoView({ behavior: scrollBehavior, block: 'center' })
      }
    })
    isPicking.value = false
  }, pickDelay)
}
</script>

<style scoped>
.rms-menu-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
  pointer-events: none;
}

.rms-menu-modal[aria-hidden="false"],
.rms-menu-modal:not([aria-hidden]) {
  pointer-events: auto;
}

.rms-menu-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  pointer-events: auto;
}

.rms-menu-modal__panel {
  --rms-accent: v-bind(primary);
  position: relative;
  width: 100%;
  max-width: 100%;
  height: 100%;
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
  .rms-menu-modal__panel {
    border-radius: 0;
  }
}

.rms-menu-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.rms-menu-modal__title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
}

.rms-menu-modal__close {
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
  transition: opacity 0.15s;
}

.rms-menu-modal__close:hover {
  opacity: 0.9;
}

.rms-menu-modal__close:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

.rms-menu-modal__close .material-icons {
  font-size: 1.5rem;
}

.rms-menu-modal__surprise-wrap {
  padding: 0.75rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.rms-menu-modal__surprise {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 44px;
  padding: 0.5rem 1rem;
  width: 100%;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: opacity 0.15s;
}

.rms-menu-modal__surprise:hover:not(:disabled) {
  opacity: 0.9;
}

.rms-menu-modal__surprise:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rms-menu-modal__surprise:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

.rms-menu-modal__surprise-icon {
  font-size: 1.25rem;
}

.rms-menu-modal__surprise--picking .rms-menu-modal__surprise-icon {
  animation: rms-surprise-shuffle 500ms ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
  .rms-menu-modal__surprise--picking .rms-menu-modal__surprise-icon {
    animation: none;
  }
}
@keyframes rms-surprise-shuffle {
  0%, 100% { transform: scale(1) rotate(0deg); }
  25% { transform: scale(1.1) rotate(-8deg); }
  75% { transform: scale(1.1) rotate(8deg); }
}

.rms-menu-modal__no-items {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: #64748b;
}

.rms-menu-modal__scroll {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  min-height: 0;
  -webkit-overflow-scrolling: touch;
}

.rms-menu-modal__categories {
  padding: 0.5rem 0;
}

.rms-menu-modal__category {
  border-bottom: 1px solid #f1f5f9;
}

.rms-menu-modal__category:last-child {
  border-bottom: none;
}

.rms-menu-modal__category-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 44px;
  padding: 0.75rem 1.25rem;
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
  background: #f8fafc;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}
.rms-menu-modal__category-thumb {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 6px;
}

.rms-menu-modal__category-head:hover {
  background: #f1f5f9;
}

.rms-menu-modal__category-head:focus-visible {
  outline: 2px solid var(--rms-accent, #2563eb);
  outline-offset: -2px;
}

.rms-menu-modal__category-head--open {
  background: #f1f5f9;
}

.rms-menu-modal__category-name {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
}

.rms-menu-modal__category-availability {
  font-size: 0.75rem;
  font-weight: 400;
  color: #64748b;
}

.rms-menu-modal__item-availability {
  margin: 0.25rem 0 0;
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.3;
}

.rms-menu-modal__category-icon {
  font-size: 1.5rem;
  color: #64748b;
}

.rms-menu-modal__category-panel {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.25s ease;
}

.rms-menu-modal__category-panel--open {
  grid-template-rows: 1fr;
}

@media (prefers-reduced-motion: reduce) {
  .rms-menu-modal__category-panel {
    transition: none;
  }
}

.rms-menu-modal__category-panel-inner {
  overflow: hidden;
  min-height: 0;
  padding: 0.5rem 1.25rem 1rem;
}

.rms-menu-modal__item {
  display: block;
  width: 100%;
  padding: 0.75rem 0;
  border: none;
  border-bottom: 1px solid #f1f5f9;
  background: transparent;
  text-align: left;
  cursor: pointer;
  min-height: 44px;
  transition: background 0.2s, box-shadow 0.2s;
}
.rms-menu-modal__item:hover {
  background: #f8fafc;
}
.rms-menu-modal__item:focus-visible {
  outline: 2px solid var(--rms-accent, #2563eb);
  outline-offset: -2px;
}

.rms-menu-modal__item:last-child {
  border-bottom: none;
}

.rms-menu-modal__item--highlight {
  background: rgba(37, 99, 235, 0.08);
  box-shadow: 0 0 0 2px var(--rms-accent, #2563eb);
  border-radius: 8px;
  margin: 0 -0.25rem;
  padding: 0.75rem 0.25rem;
  animation: rms-item-reveal 300ms ease-out, rms-item-glow 300ms ease-out 0ms forwards;
}

@media (prefers-reduced-motion: reduce) {
  .rms-menu-modal__item,
  .rms-menu-modal__item--highlight {
    transition: none;
    animation: none;
  }
}
@keyframes rms-item-reveal {
  from { transform: scale(0.98); }
  to { transform: scale(1); }
}
@keyframes rms-item-glow {
  from { box-shadow: 0 0 0 0 var(--rms-accent, #2563eb); }
  to { box-shadow: 0 0 0 2px var(--rms-accent, #2563eb); }
}

.rms-menu-modal__item-top {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}
.rms-menu-modal__item-thumb {
  width: 72px;
  height: 72px;
  flex-shrink: 0;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 8px;
}
.rms-menu-modal__item-body {
  flex: 1;
  min-width: 0;
}
.rms-menu-modal__item-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.rms-menu-modal__item-name {
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex-wrap: wrap;
}

.rms-menu-modal__tag {
  display: inline-flex;
  align-items: center;
  cursor: help;
  padding: 0.375rem;
  border-radius: 4px;
  min-width: 44px;
  min-height: 44px;
  justify-content: center;
  box-sizing: border-box;
}

.rms-menu-modal__tag:focus-visible {
  outline: 2px solid var(--rms-accent, #2563eb);
  outline-offset: 2px;
}

.rms-menu-modal__tag-icon {
  font-size: 1rem;
}

.rms-menu-modal__item-price {
  font-size: 1rem;
  font-weight: 600;
  white-space: nowrap;
}

.rms-menu-modal__item-muted {
  font-size: 0.875rem;
  color: #64748b;
  white-space: nowrap;
}

.rms-menu-modal__item-desc {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.4;
}

.rms-menu-modal__combo-list,
.rms-menu-modal__sku-list {
  margin: 0.5rem 0 0;
  list-style: none;
}
.rms-menu-modal__combo-list {
  padding-left: 1.25rem;
  margin-left: 0.125rem;
  border-left: 2px solid #e2e8f0;
  font-size: 0.75rem;
}
.rms-menu-modal__sku-list {
  padding-left: 1.25rem;
}

.rms-menu-modal__combo-list li,
.rms-menu-modal__sku-list li {
  color: #64748b;
  margin-top: 0.25rem;
}
.rms-menu-modal__combo-list li {
  font-size: 0.75rem;
}
.rms-menu-modal__sku-list li {
  font-size: 0.875rem;
}

.rms-menu-modal__sku {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
}

.rms-menu-modal__sku-label {
  font-weight: 500;
  color: #334155;
}

.rms-menu-modal__sku-img {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 6px;
}

.rms-menu-modal__footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.rms-menu-modal__close-btn {
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

.rms-menu-modal__close-btn:hover {
  opacity: 0.9;
}

.rms-menu-modal__close-btn:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
</style>
