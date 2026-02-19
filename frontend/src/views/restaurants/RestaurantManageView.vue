<template>
  <div data-testid="restaurant-manage-page">
    <div v-if="loading" class="space-y-4" data-testid="manage-loading">
      <div class="h-48 rounded-2xl bg-cream/60 dark:bg-zinc-800/80 animate-pulse" />
      <div class="h-24 rounded-2xl bg-white/80 dark:bg-zinc-800/80 animate-pulse" />
    </div>

    <div v-else-if="!restaurant" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center" data-testid="manage-not-found">
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink to="/app/restaurants" data-testid="manage-back-link" />
    </div>

    <template v-else>
      <!-- Banner + Web address: only on Profile tab on mobile; always visible on desktop -->
      <div
        class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:gap-6 mb-6 w-full min-w-0"
        :class="{ 'hidden lg:grid': activeTab !== 'profile' }"
      >
        <!-- Banner: tap to open modal for logo & banner; 100% width on mobile -->
        <button
          type="button"
          data-testid="manage-banner-button"
          class="relative w-full min-w-0 text-left block focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset rounded-2xl overflow-hidden min-h-[120px] lg:min-h-0 lg:h-full"
          @click="showImageModal = true"
        >
          <div v-if="restaurant.banner_url" class="relative w-full aspect-[21/9] max-h-44 lg:aspect-auto lg:absolute lg:inset-0 rounded-2xl overflow-hidden bg-slate-100 dark:bg-slate-800">
            <img :src="restaurant.banner_url" :alt="restaurant.name" class="absolute inset-0 w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent" />
            <div class="absolute bottom-0 left-0 right-0 p-4 lg:p-6 flex items-end gap-4">
              <div v-if="restaurant.logo_url" class="w-16 h-16 lg:w-20 lg:h-20 rounded-xl border-2 border-white dark:border-zinc-900 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden shrink-0">
                <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-16 h-16 lg:w-20 lg:h-20 rounded-xl border-2 border-white/80 bg-white/20 flex items-center justify-center shrink-0">
                <span class="material-icons text-3xl lg:text-4xl text-white">restaurant</span>
              </div>
              <div>
                <h1 class="text-xl lg:text-2xl font-bold tracking-tight text-white drop-shadow-lg">{{ restaurant.name }}</h1>
                <p class="text-white/90 text-sm mt-0.5">Tap to change logo &amp; banner</p>
              </div>
            </div>
          </div>
          <div v-else class="relative rounded-2xl overflow-hidden bg-primary/10 dark:bg-primary/20 border border-primary/10 h-full min-h-[120px] flex items-center">
            <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-transparent" />
            <div class="relative flex items-center gap-4 p-4 lg:p-6">
              <div v-if="restaurant.logo_url" class="w-16 h-16 lg:w-20 lg:h-20 rounded-xl bg-white dark:bg-zinc-900 border-2 border-white shadow flex items-center justify-center overflow-hidden shrink-0">
                <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-16 h-16 lg:w-20 lg:h-20 rounded-xl bg-white dark:bg-zinc-900 border-2 border-white shadow flex items-center justify-center shrink-0">
                <span class="material-icons text-3xl lg:text-4xl text-primary">restaurant</span>
              </div>
              <div>
                <h1 class="text-xl lg:text-2xl font-bold text-charcoal dark:text-white">{{ restaurant.name }}</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">Tap to change logo &amp; banner</p>
              </div>
            </div>
          </div>
        </button>

        <!-- Web address: beside banner on desktop (equal width & height), underneath on mobile -->
        <div
          v-if="restaurant.slug"
          class="p-4 lg:p-5 rounded-2xl border border-sage/30 dark:border-sage/40 bg-sage/10 dark:bg-sage/15 lg:h-full lg:min-h-0 flex flex-col"
          data-testid="manage-web-address-card"
        >
          <p class="text-xs font-semibold uppercase tracking-wider text-sage/90 dark:text-sage/80 mb-1">Web address</p>
          <p class="text-lg font-semibold text-charcoal dark:text-white font-mono break-all" data-testid="manage-web-address-text">
            <span class="text-primary">{{ restaurant.slug }}</span><span class="text-slate-500 dark:text-slate-400">{{ publicDomainSuffix }}</span>
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Customers visit this address.</p>
          <button type="button" data-testid="manage-copy-url" class="mt-3 inline-flex items-center gap-1.5 min-h-[44px] px-3 py-2 text-sm font-medium text-sage hover:text-primary transition-colors rounded-lg" @click="copyRestaurantUrl">
            <span class="material-icons text-lg" :class="{ 'scale-110': copyDone }">{{ copyDone ? 'check' : 'content_copy' }}</span>
            {{ copyDone ? 'Copied!' : 'Copy address' }}
          </button>
        </div>
      </div>

      <!-- Tabs: fixed at bottom on mobile with icons, inline on desktop -->
      <div
        role="tablist"
        aria-label="Restaurant management sections"
        data-testid="manage-tabs"
        class="flex min-h-[44px] items-center p-2 gap-1 rounded-t-2xl bg-slate-100/95 dark:bg-zinc-800/95 backdrop-blur-sm overflow-x-auto z-40 border-t border-slate-200 dark:border-zinc-700 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_12px_rgba(0,0,0,0.3)] lg:border-t-0 lg:rounded-lg lg:mb-6 lg:p-1 lg:gap-0 lg:shadow-none lg:bg-slate-100 lg:dark:bg-zinc-800 lg:backdrop-blur-none fixed bottom-0 left-0 right-0 pb-[max(0.5rem,env(safe-area-inset-bottom))] lg:static lg:pb-0"
      >
        <button type="button" role="tab" id="manage-tab-profile" aria-controls="manage-panel-profile" data-testid="manage-tab-profile" class="flex-1 min-w-0 py-2 px-2 text-sm font-semibold rounded-lg transition-all whitespace-nowrap lg:rounded-md flex flex-col lg:flex-row items-center justify-center gap-0.5 lg:gap-1.5 lg:py-2.5" :class="activeTab === 'profile' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" :aria-selected="activeTab === 'profile'" @click="activeTab = 'profile'">
          <span class="material-icons text-xl lg:text-lg">person</span>
          <span>Profile</span>
        </button>
        <button type="button" role="tab" id="manage-tab-menu" aria-controls="manage-panel-menu" data-testid="manage-tab-menu" class="flex-1 min-w-0 py-2 px-2 text-sm font-semibold rounded-lg transition-all whitespace-nowrap lg:rounded-md flex flex-col lg:flex-row items-center justify-center gap-0.5 lg:gap-1.5 lg:py-2.5" :class="activeTab === 'menu' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" :aria-selected="activeTab === 'menu'" @click="activeTab = 'menu'">
          <span class="material-icons text-xl lg:text-lg">restaurant_menu</span>
          <span>Menu</span>
        </button>
        <button type="button" role="tab" id="manage-tab-availability" aria-controls="manage-panel-availability" data-testid="manage-tab-availability" class="flex-1 min-w-0 py-2 px-2 text-sm font-semibold rounded-lg transition-all whitespace-nowrap lg:rounded-md flex flex-col lg:flex-row items-center justify-center gap-0.5 lg:gap-1.5 lg:py-2.5" :class="activeTab === 'availability' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" :aria-selected="activeTab === 'availability'" @click="activeTab = 'availability'">
          <span class="material-icons text-xl lg:text-lg">schedule</span>
          <span>Availability</span>
        </button>
        <button type="button" role="tab" id="manage-tab-settings" aria-controls="manage-panel-settings" data-testid="manage-tab-settings" class="flex-1 min-w-0 py-2 px-2 text-sm font-semibold rounded-lg transition-all whitespace-nowrap lg:rounded-md flex flex-col lg:flex-row items-center justify-center gap-0.5 lg:gap-1.5 lg:py-2.5" :class="activeTab === 'settings' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" :aria-selected="activeTab === 'settings'" @click="activeTab = 'settings'">
          <span class="material-icons text-xl lg:text-lg">settings</span>
          <span>Settings</span>
        </button>
      </div>

      <!-- Tab content: extra bottom padding on mobile -->
      <div class="pb-24 lg:pb-0" data-testid="manage-tab-content">
        <div v-show="activeTab === 'profile'" id="manage-panel-profile" role="tabpanel" aria-labelledby="manage-tab-profile" class="min-h-[200px] space-y-6" data-testid="manage-panel-profile">
          <RestaurantFormView
            :key="restaurant.uuid"
            embed
            :operating-hours="operatingHours"
            :default-description="defaultDescription"
            @update:default-description="defaultDescription = $event"
          >
            <template #actions-start>
              <AppButton ref="deleteButtonRef" data-testid="manage-delete-button" type="button" variant="secondary" class="min-h-[44px] w-full sm:w-auto text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20" :disabled="deleting || !restaurant.slug" @click="confirmDelete">
                <template #icon>
                  <span v-if="deleting" class="material-icons animate-spin">sync</span>
                  <span v-else class="material-icons">delete_outline</span>
                </template>
                Delete restaurant
              </AppButton>
            </template>
          </RestaurantFormView>
        </div>
        <div v-show="activeTab === 'menu'" id="manage-panel-menu" role="tabpanel" aria-labelledby="manage-tab-menu" class="min-h-[200px]" data-testid="manage-panel-menu">
          <RestaurantMenusView :key="'menu-' + restaurant.uuid" :tab-active="activeTab === 'menu'" />
        </div>
        <div v-show="activeTab === 'availability'" id="manage-panel-availability" role="tabpanel" aria-labelledby="manage-tab-availability" class="min-h-[200px]" data-testid="manage-panel-availability">
          <header class="mb-4 lg:mb-6">
            <div>
              <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Availability</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">When your restaurant is open. Schedule is saved when you save the profile.</p>
            </div>
          </header>
          <RestaurantAvailabilitySchedule v-model="operatingHours" />
        </div>
        <div v-show="activeTab === 'settings'" id="manage-panel-settings" role="tabpanel" aria-labelledby="manage-tab-settings" class="min-h-[200px]" data-testid="manage-panel-settings">
          <RestaurantContentView
            :key="'settings-' + restaurant.uuid"
            embed
            :default-description="defaultDescription"
            @update:default-description="defaultDescription = $event"
          />
        </div>
      </div>

      <!-- Logo & banner modal -->
      <AppModal :open="showImageModal" title="Logo &amp; banner" description="Change or update your restaurant logo and banner images." @close="closeImageModal">
        <div class="space-y-6" data-testid="manage-image-modal-content">
          <p class="text-sm text-slate-600 dark:text-slate-400">Upload new images. JPEG, PNG, GIF or WebP. Max 2MB each.</p>
          <div data-testid="manage-image-modal-logo">
            <p class="text-sm font-medium text-charcoal dark:text-white mb-2">Logo</p>
            <div class="flex flex-col sm:flex-row gap-4 items-start">
              <div class="w-20 h-20 rounded-xl bg-slate-100 dark:bg-zinc-800 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200 dark:border-zinc-700">
                <img v-if="restaurant?.logo_url" :src="restaurant.logo_url" alt="Logo" class="w-full h-full object-cover" />
                <span v-else class="material-icons text-3xl text-slate-400">image</span>
              </div>
              <div class="min-w-0 w-full sm:w-auto flex flex-col gap-2">
                <input ref="logoInputRef" type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="sr-only" aria-label="Upload logo from gallery" data-testid="manage-image-logo-input" @change="onModalLogoChange" />
                <input ref="logoCameraInputRef" type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" capture="environment" class="sr-only" aria-label="Take photo for logo" data-testid="manage-image-logo-camera-input" @change="onModalLogoChange" />
                <div class="flex flex-wrap gap-2 sm:block">
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] sm:hidden" data-testid="manage-image-logo-camera" @click="triggerLogoCamera">Take photo</AppButton>
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] sm:hidden" data-testid="manage-image-logo-gallery" @click="triggerLogoInput">Choose from gallery</AppButton>
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] hidden sm:inline-flex" data-testid="manage-image-logo-button" @click="triggerLogoInput">Change logo</AppButton>
                </div>
                <p v-if="imageUploadErrors.logo" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ imageUploadErrors.logo }}</p>
              </div>
            </div>
          </div>
          <div data-testid="manage-image-modal-banner">
            <p class="text-sm font-medium text-charcoal dark:text-white mb-2">Banner</p>
            <div class="flex flex-col sm:flex-row gap-4 items-start">
              <div class="w-full sm:w-auto sm:max-w-[200px] aspect-video rounded-xl bg-slate-100 dark:bg-zinc-800 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200 dark:border-zinc-700">
                <img v-if="restaurant?.banner_url" :src="restaurant.banner_url" alt="Banner" class="w-full h-full object-cover" />
                <span v-else class="material-icons text-3xl text-slate-400">image</span>
              </div>
              <div class="min-w-0 w-full sm:w-auto flex flex-col gap-2">
                <input ref="bannerInputRef" type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="sr-only" aria-label="Upload banner from gallery" data-testid="manage-image-banner-input" @change="onModalBannerChange" />
                <input ref="bannerCameraInputRef" type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" capture="environment" class="sr-only" aria-label="Take photo for banner" data-testid="manage-image-banner-camera-input" @change="onModalBannerChange" />
                <div class="flex flex-wrap gap-2 sm:block">
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] sm:hidden" data-testid="manage-image-banner-camera" @click="triggerBannerCamera">Take photo</AppButton>
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] sm:hidden" data-testid="manage-image-banner-gallery" @click="triggerBannerInput">Choose from gallery</AppButton>
                  <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px] hidden sm:inline-flex" data-testid="manage-image-banner-button" @click="triggerBannerInput">Change banner</AppButton>
                </div>
                <p v-if="imageUploadErrors.banner" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ imageUploadErrors.banner }}</p>
              </div>
            </div>
          </div>
        </div>
        <template #footer>
          <AppButton variant="primary" data-testid="manage-image-modal-done" @click="closeImageModal">Done</AppButton>
        </template>
      </AppModal>

      <!-- Delete confirm modal -->
      <div v-if="showDeleteConfirm && restaurant?.slug" ref="deleteModalRef" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" role="dialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-description" data-testid="manage-delete-modal" @keydown="onDeleteModalKeydown">
        <div ref="deleteDialogRef" class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl max-w-md w-full p-6">
          <h3 id="delete-title" class="font-bold text-charcoal dark:text-white mb-2">Delete restaurant?</h3>
          <p id="delete-description" class="text-sm text-slate-500 dark:text-slate-400 mb-4">This cannot be undone.</p>
          <p class="text-sm font-medium text-charcoal dark:text-white mb-2">Type <strong class="font-mono text-red-600 dark:text-red-400">{{ restaurant.slug }}</strong> to confirm.</p>
          <input
            v-model="deleteConfirmSlug"
            type="text"
            autocomplete="off"
            spellcheck="false"
            data-testid="manage-delete-confirm-input"
            class="w-full rounded-xl border-0 py-3 px-4 text-sm text-charcoal dark:text-white bg-slate-100 dark:bg-zinc-800 ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-red-500 focus:outline-none font-mono"
            :placeholder="restaurant.slug"
            :aria-label="`Type ${restaurant.slug} to confirm`"
            :aria-invalid="deleteConfirmSlug !== restaurant.slug"
          />
          <div class="flex gap-3 mt-6">
            <AppButton variant="secondary" class="flex-1 min-h-[44px]" data-testid="manage-delete-modal-cancel" :disabled="deleting" @click="closeDeleteConfirm">Cancel</AppButton>
            <AppButton variant="primary" class="flex-1 min-h-[44px] bg-red-600 hover:bg-red-700" data-testid="manage-delete-modal-confirm" :disabled="deleting || deleteConfirmSlug !== restaurant.slug" @click="doDelete">
              <template v-if="deleting" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
              Delete
            </AppButton>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppBackLink from '@/components/AppBackLink.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { useToastStore } from '@/stores/toast'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, normalizeApiError } from '@/services'
import RestaurantFormView from './RestaurantFormView.vue'
import RestaurantMenusView from './RestaurantMenusView.vue'
import RestaurantContentView from './RestaurantContentView.vue'
import RestaurantAvailabilitySchedule from '@/components/restaurant/RestaurantAvailabilitySchedule.vue'

const route = useRoute()
const router = useRouter()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()

const loading = ref(true)
const restaurant = ref(null)
const operatingHours = ref({})
const defaultDescription = ref('')
const deleting = ref(false)
const showDeleteConfirm = ref(false)
const deleteConfirmSlug = ref('')
const copyDone = ref(false)
const showImageModal = ref(false)
const imageUploadErrors = ref({ logo: '', banner: '' })
const logoInputRef = ref(null)
const logoCameraInputRef = ref(null)
const bannerInputRef = ref(null)
const bannerCameraInputRef = ref(null)
const deleteButtonRef = ref(null)
const deleteModalRef = ref(null)
const deleteDialogRef = ref(null)
const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
const MAX_IMAGE_BYTES = 2 * 1024 * 1024

const publicDomainSuffix = (() => {
  const d = import.meta.env.VITE_APP_PUBLIC_DOMAIN
  if (d && typeof d === 'string' && d.trim()) return '.' + d.trim().replace(/^\.+/, '')
  return '.yourdomain.com'
})()

const validTabs = ['profile', 'menu', 'availability', 'settings']
const tabFromRoute = () => {
  const t = route.query.tab
  if (t === 'content') return 'settings'
  return (t && validTabs.includes(t)) ? t : 'profile'
}
const activeTab = ref(tabFromRoute())

watch(activeTab, (tab) => {
  if (route.query.tab !== tab) router.replace({ query: { ...route.query, tab } })
  window.scrollTo({ top: 0, behavior: 'auto' })
})
watch(() => route.query.tab, (t) => {
  if (t === 'content') activeTab.value = 'settings'
  else if (t && validTabs.includes(t)) activeTab.value = t
}, { immediate: true })

watch(() => route.params.uuid, () => { defaultDescription.value = '' })

watch(showDeleteConfirm, (open) => {
  if (open) {
    nextTick(() => {
      const focusables = getDeleteModalFocusables()
      if (focusables.length > 0) focusables[0].focus()
    })
  }
})

function copyRestaurantUrl() {
  const r = restaurant.value
  if (!r?.slug) return
  const base = publicDomainSuffix.startsWith('.') ? publicDomainSuffix.slice(1) : publicDomainSuffix
  const url = `https://${r.slug}.${base}`
  navigator.clipboard.writeText(url).then(() => {
    copyDone.value = true
    setTimeout(() => { copyDone.value = false }, 2000)
  })
}

function confirmDelete() {
  deleteConfirmSlug.value = ''
  showDeleteConfirm.value = true
}
function getDeleteModalFocusables() {
  if (!deleteDialogRef.value) return []
  const sel = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
  return Array.from(deleteDialogRef.value.querySelectorAll(sel))
}
function onDeleteModalKeydown(e) {
  if (e.key === 'Escape') {
    closeDeleteConfirm()
    return
  }
  if (e.key !== 'Tab' || !deleteDialogRef.value) return
  const focusables = getDeleteModalFocusables()
  if (focusables.length === 0) return
  const current = document.activeElement
  const idx = focusables.indexOf(current)
  if (e.shiftKey) {
    if (idx <= 0) {
      e.preventDefault()
      focusables[focusables.length - 1].focus()
    }
  } else {
    if (idx === -1 || idx === focusables.length - 1) {
      e.preventDefault()
      focusables[0].focus()
    }
  }
}
function closeDeleteConfirm() {
  showDeleteConfirm.value = false
  deleteConfirmSlug.value = ''
  nextTick(() => {
    const el = deleteButtonRef.value?.$el ?? deleteButtonRef.value
    if (el && typeof el.focus === 'function') el.focus()
  })
}
async function doDelete() {
  if (!restaurant.value?.uuid || deleteConfirmSlug.value !== restaurant.value.slug) return
  deleting.value = true
  try {
    await restaurantService.delete(restaurant.value.uuid)
    closeDeleteConfirm()
    router.push({ name: 'Restaurants' })
  } catch (e) {
    console.error(normalizeApiError(e).message)
  } finally {
    deleting.value = false
  }
}

function triggerLogoInput() {
  imageUploadErrors.value.logo = ''
  logoInputRef.value?.click()
}
function triggerLogoCamera() {
  imageUploadErrors.value.logo = ''
  logoCameraInputRef.value?.click()
}
function triggerBannerInput() {
  imageUploadErrors.value.banner = ''
  bannerInputRef.value?.click()
}
function triggerBannerCamera() {
  imageUploadErrors.value.banner = ''
  bannerCameraInputRef.value?.click()
}
function validateImageFile(file, key) {
  imageUploadErrors.value[key] = ''
  if (!file) return false
  if (!IMAGE_TYPES.includes(file.type)) { imageUploadErrors.value[key] = 'Please choose a JPEG, PNG, GIF or WebP image.'; return false }
  if (file.size > MAX_IMAGE_BYTES) { imageUploadErrors.value[key] = 'Image must be 2MB or smaller.'; return false }
  return true
}
async function onModalLogoChange(ev) {
  const file = ev.target?.files?.[0]
  if (!validateImageFile(file, 'logo')) return
  if (!restaurant.value?.uuid) return
  try {
    const res = await restaurantService.uploadLogo(restaurant.value.uuid, file)
    restaurant.value = res != null ? Restaurant.fromApi(res).toJSON() : restaurant.value
    toastStore.success('Logo updated.')
    if (logoInputRef.value) logoInputRef.value.value = ''
    if (logoCameraInputRef.value) logoCameraInputRef.value.value = ''
  } catch (e) {
    imageUploadErrors.value.logo = normalizeApiError(e).message ?? 'Upload failed.'
  }
}
async function onModalBannerChange(ev) {
  const file = ev.target?.files?.[0]
  if (!validateImageFile(file, 'banner')) return
  if (!restaurant.value?.uuid) return
  try {
    const res = await restaurantService.uploadBanner(restaurant.value.uuid, file)
    restaurant.value = res != null ? Restaurant.fromApi(res).toJSON() : restaurant.value
    toastStore.success('Banner updated.')
    if (bannerInputRef.value) bannerInputRef.value.value = ''
    if (bannerCameraInputRef.value) bannerCameraInputRef.value.value = ''
  } catch (e) {
    imageUploadErrors.value.banner = normalizeApiError(e).message ?? 'Upload failed.'
  }
}
function closeImageModal() {
  showImageModal.value = false
  imageUploadErrors.value = { logo: '', banner: '' }
}

async function fetchOne() {
  const uuid = route.params.uuid
  if (!uuid) return
  loading.value = true
  restaurant.value = null
  try {
    const res = await restaurantService.get(uuid)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    operatingHours.value = restaurant.value?.operating_hours ?? {}
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else console.error(normalizeApiError(e).message)
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchOne())
watch(() => route.params.uuid, () => fetchOne())
</script>
