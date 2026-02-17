<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="space-y-4">
      <div class="h-48 rounded-2xl bg-cream/60 dark:bg-zinc-800/80 border border-slate-100 dark:border-slate-700/50 animate-pulse" />
      <div class="h-24 rounded-2xl bg-white/80 dark:bg-zinc-800/80 border border-slate-100 dark:border-slate-700/50 animate-pulse" />
    </div>

    <!-- Not found -->
    <div
      v-else-if="!restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <!-- Detail -->
    <template v-else>
      <!-- Hero: tap to open modal for changing logo & banner -->
      <button
        type="button"
        class="mb-6 lg:mb-8 -mx-4 lg:mx-0 w-full text-left block focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset rounded-none lg:rounded-2xl overflow-hidden"
        @click="showImageModal = true"
      >
        <div
          v-if="restaurant.banner_url"
          class="relative aspect-[21/9] max-h-44 lg:max-h-56 rounded-none lg:rounded-2xl overflow-hidden bg-slate-100 dark:bg-slate-800"
        >
          <img :src="restaurant.banner_url" :alt="restaurant.name" class="w-full h-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent" />
          <div class="absolute bottom-0 left-0 right-0 p-4 lg:p-6 flex items-end gap-4">
            <div
              v-if="restaurant.logo_url"
              class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl border-2 border-white dark:border-zinc-900 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden shrink-0"
            >
              <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl border-2 border-white/80 bg-white/20 flex items-center justify-center shrink-0"
            >
              <span class="material-icons text-2xl lg:text-3xl text-white">restaurant</span>
            </div>
            <div>
              <h1 class="text-2xl font-bold tracking-tight text-white drop-shadow-lg lg:text-3xl">{{ restaurant.name }}</h1>
              <p class="text-white/90 text-sm mt-0.5">Tap to change logo &amp; banner</p>
            </div>
          </div>
        </div>
        <div
          v-else
          class="rounded-2xl bg-gradient-to-r from-primary/90 to-primary/70 dark:from-primary/80 dark:to-primary/60 px-4 py-6 lg:px-8 lg:py-8 flex items-center gap-4"
        >
          <div
            v-if="restaurant.logo_url"
            class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-white dark:bg-zinc-900 border-2 border-white shadow overflow-hidden shrink-0"
          >
            <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-white/20 border-2 border-white flex items-center justify-center shrink-0">
            <span class="material-icons text-2xl lg:text-3xl text-white">restaurant</span>
          </div>
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-white lg:text-3xl">{{ restaurant.name }}</h1>
            <p class="text-white/90 text-sm mt-0.5">Tap to change logo &amp; banner</p>
          </div>
        </div>
      </button>

      <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end mb-6">
        <div class="flex flex-wrap gap-2 shrink-0">
          <router-link :to="{ name: 'RestaurantMenuItems', params: { uuid: restaurant.uuid } }">
            <AppButton variant="secondary" class="min-h-[44px]">
              <template #icon>
                <span class="material-icons">restaurant_menu</span>
              </template>
              Menu
            </AppButton>
          </router-link>
          <router-link :to="{ name: 'RestaurantContent', params: { uuid: restaurant.uuid } }">
            <AppButton variant="secondary" class="min-h-[44px]">
              <template #icon>
                <span class="material-icons">translate</span>
              </template>
              Languages &amp; description
            </AppButton>
          </router-link>
          <router-link :to="{ name: 'RestaurantEdit', params: { uuid: restaurant.uuid } }">
            <AppButton variant="primary" class="min-h-[44px]">
              <template #icon>
                <span class="material-icons">edit</span>
              </template>
              Edit
            </AppButton>
          </router-link>
          <AppButton variant="secondary" class="min-h-[44px]" :disabled="deleting || !restaurant.slug" @click="confirmDelete">
            <template #icon>
              <span v-if="deleting" class="material-icons animate-spin">sync</span>
              <span v-else class="material-icons">delete_outline</span>
            </template>
            Delete
          </AppButton>
        </div>
      </header>

      <!-- Restaurant URL: softer sage/cream treatment -->
      <div
        v-if="restaurant.slug"
        class="mb-6 p-4 lg:p-5 rounded-2xl border border-sage/30 dark:border-sage/40 bg-sage/10 dark:bg-sage/15"
      >
        <p class="text-xs font-semibold uppercase tracking-wider text-sage/90 dark:text-sage/80 mb-1">Web address</p>
        <p class="text-lg font-semibold text-charcoal dark:text-white font-mono break-all">
          <span class="text-primary">{{ restaurant.slug }}</span><span class="text-slate-500 dark:text-slate-400">{{ publicDomainSuffix }}</span>
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
          Customers visit this address. It was created from your restaurant name and cannot be changed.
        </p>
        <button
          type="button"
          class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-sage hover:text-primary transition-colors"
          @click="copyRestaurantUrl"
        >
          <span class="material-icons text-lg transition-transform" :class="{ 'scale-110': copyDone }">{{ copyDone ? 'check' : 'content_copy' }}</span>
          {{ copyDone ? 'Copied!' : 'Copy address' }}
        </button>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
          <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Contact &amp; details</h3>
            <dl class="space-y-3 text-sm">
              <div v-if="restaurant.address" class="flex gap-3">
                <dt class="text-slate-500 dark:text-slate-400 shrink-0 w-20">Address</dt>
                <dd class="text-charcoal dark:text-white">{{ restaurant.address }}</dd>
              </div>
              <div v-if="restaurant.phone" class="flex gap-3">
                <dt class="text-slate-500 dark:text-slate-400 shrink-0 w-20">Phone</dt>
                <dd class="text-charcoal dark:text-white">
                  <a :href="`tel:${restaurant.phone}`" class="text-primary hover:underline">{{ restaurant.phone }}</a>
                </dd>
              </div>
              <div v-if="restaurant.email" class="flex gap-3">
                <dt class="text-slate-500 dark:text-slate-400 shrink-0 w-20">Email</dt>
                <dd class="text-charcoal dark:text-white">
                  <a :href="`mailto:${restaurant.email}`" class="text-primary hover:underline">{{ restaurant.email }}</a>
                </dd>
              </div>
              <div v-if="restaurant.website" class="flex gap-3">
                <dt class="text-slate-500 dark:text-slate-400 shrink-0 w-20">Website</dt>
                <dd class="text-charcoal dark:text-white">
                  <a :href="restaurant.website" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline break-all">{{ restaurant.website }}</a>
                </dd>
              </div>
            </dl>
          </section>
        </div>
        <div class="space-y-6">
          <section class="rounded-2xl bg-cream/50 dark:bg-sage/10 border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Logo</h3>
            <div class="w-24 h-24 rounded-xl bg-white dark:bg-zinc-800 overflow-hidden flex items-center justify-center ring-1 ring-slate-200/50 dark:ring-slate-700">
              <img
                v-if="restaurant.logo_url"
                :src="restaurant.logo_url"
                :alt="restaurant.name"
                class="w-full h-full object-cover"
              />
              <span v-else class="material-icons text-4xl text-sage/60">restaurant</span>
            </div>
          </section>
          <section v-if="socialLinks.length" class="rounded-2xl bg-cream/50 dark:bg-sage/10 border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Social</h3>
            <div class="flex flex-wrap gap-2">
              <a
                v-for="s in socialLinks"
                :key="s.name"
                :href="s.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-zinc-800 text-slate-700 dark:text-slate-300 text-sm hover:bg-primary/10 hover:text-primary border border-slate-200/80 dark:border-slate-700 transition-colors min-h-[44px]"
              >
                <span class="material-icons text-lg">link</span>
                {{ s.name }}
              </a>
            </div>
          </section>
        </div>
      </div>
    </template>

    <!-- Logo & banner update modal -->
    <div
      v-if="showImageModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="image-modal-title"
      aria-describedby="image-modal-desc"
      @keydown.escape="closeImageModal"
    >
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" @click="closeImageModal" />
      <div class="relative w-full max-h-[90dvh] overflow-y-auto flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-md sm:max-w-lg" @click.stop>
        <div class="flex items-center justify-between shrink-0 px-4 py-3 border-b border-slate-200 dark:border-slate-800">
          <h2 id="image-modal-title" class="text-lg font-bold text-charcoal dark:text-white">Logo &amp; banner</h2>
          <button
            type="button"
            class="p-2 rounded-full text-slate-500 hover:text-charcoal dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            aria-label="Close"
            @click="closeImageModal"
          >
            <span class="material-icons">close</span>
          </button>
        </div>
        <div id="image-modal-desc" class="sr-only">Change or update your restaurant logo and banner images.</div>
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
          <p class="text-sm text-slate-600 dark:text-slate-400">Upload new images to replace the current logo and banner. JPEG, PNG, GIF or WebP. Max 2MB each.</p>

          <div>
            <p class="text-sm font-medium text-charcoal dark:text-white mb-2">Logo</p>
            <div class="flex gap-4 items-start">
              <div class="w-20 h-20 rounded-xl bg-slate-100 dark:bg-zinc-800 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200 dark:border-zinc-700">
                <img v-if="restaurant?.logo_url" :src="restaurant.logo_url" alt="Logo" class="w-full h-full object-cover" />
                <span v-else class="material-icons text-3xl text-slate-400">image</span>
              </div>
              <div class="min-w-0 flex-1">
                <input
                  ref="logoInputRef"
                  type="file"
                  accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                  class="sr-only"
                  aria-label="Upload logo"
                  @change="onModalLogoChange"
                />
                <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="triggerLogoInput">Change logo</AppButton>
                <p v-if="imageUploadErrors.logo" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ imageUploadErrors.logo }}</p>
              </div>
            </div>
          </div>

          <div>
            <p class="text-sm font-medium text-charcoal dark:text-white mb-2">Banner</p>
            <div class="flex gap-4 items-start">
              <div class="w-full max-w-[200px] aspect-video rounded-xl bg-slate-100 dark:bg-zinc-800 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200 dark:border-zinc-700">
                <img v-if="restaurant?.banner_url" :src="restaurant.banner_url" alt="Banner" class="w-full h-full object-cover" />
                <span v-else class="material-icons text-3xl text-slate-400">image</span>
              </div>
              <div class="min-w-0 flex-1">
                <input
                  ref="bannerInputRef"
                  type="file"
                  accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                  class="sr-only"
                  aria-label="Upload banner"
                  @change="onModalBannerChange"
                />
                <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="triggerBannerInput">Change banner</AppButton>
                <p v-if="imageUploadErrors.banner" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ imageUploadErrors.banner }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="shrink-0 px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex justify-end">
          <AppButton variant="primary" @click="closeImageModal">Done</AppButton>
        </div>
      </div>
    </div>

    <!-- Delete confirm modal: must type slug to proceed -->
    <div
      v-if="showDeleteConfirm && restaurant?.slug"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-title"
      aria-describedby="delete-description"
    >
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 id="delete-title" class="font-bold text-charcoal dark:text-white mb-2">Delete restaurant?</h3>
        <p id="delete-description" class="text-sm text-slate-500 dark:text-slate-400 mb-4">
          This cannot be undone. All data for this restaurant will be removed.
        </p>
        <p class="text-sm font-medium text-charcoal dark:text-white mb-2">
          Type <strong class="font-mono text-red-600 dark:text-red-400">{{ restaurant.slug }}</strong> to confirm.
        </p>
        <input
          v-model="deleteConfirmSlug"
          type="text"
          autocomplete="off"
          spellcheck="false"
          class="w-full rounded-xl border-0 py-3 px-4 text-sm text-charcoal dark:text-white bg-slate-100 dark:bg-zinc-800 ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-red-500 focus:outline-none font-mono placeholder-slate-400"
          :placeholder="restaurant.slug"
          :aria-label="`Type ${restaurant.slug} to confirm deletion`"
          :aria-invalid="deleteConfirmSlug !== restaurant.slug"
          @keydown.escape="closeDeleteConfirm"
        />
        <div class="flex gap-3 mt-6">
          <AppButton variant="secondary" class="flex-1" :disabled="deleting" @click="closeDeleteConfirm">
            Cancel
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1 bg-red-600 hover:bg-red-700"
            :disabled="deleting || deleteConfirmSlug !== restaurant.slug"
            @click="doDelete"
          >
            <template v-if="deleting" #icon>
              <span class="material-icons animate-spin text-lg">sync</span>
            </template>
            Delete
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import { restaurantService, normalizeApiError } from '@/services'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()

const loading = ref(true)
const restaurant = ref(null)
const deleting = ref(false)
const showDeleteConfirm = ref(false)
const deleteConfirmSlug = ref('')
const copyDone = ref(false)

const showImageModal = ref(false)
const imageUploadErrors = ref({ logo: '', banner: '' })
const logoInputRef = ref(null)
const bannerInputRef = ref(null)
const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
const MAX_IMAGE_BYTES = 2 * 1024 * 1024

function triggerLogoInput() {
  imageUploadErrors.value.logo = ''
  logoInputRef.value?.click()
}
function triggerBannerInput() {
  imageUploadErrors.value.banner = ''
  bannerInputRef.value?.click()
}

function validateImageFile(file, key) {
  imageUploadErrors.value[key] = ''
  if (!file) return false
  if (!IMAGE_TYPES.includes(file.type)) {
    imageUploadErrors.value[key] = 'Please choose a JPEG, PNG, GIF or WebP image.'
    return false
  }
  if (file.size > MAX_IMAGE_BYTES) {
    imageUploadErrors.value[key] = 'Image must be 2MB or smaller.'
    return false
  }
  return true
}

async function onModalLogoChange(ev) {
  const file = ev.target?.files?.[0]
  if (!validateImageFile(file, 'logo')) return
  if (!restaurant.value?.uuid) return
  try {
    const res = await restaurantService.uploadLogo(restaurant.value.uuid, file)
    restaurant.value = res.data ?? restaurant.value
    toastStore.success('Logo updated.')
    if (logoInputRef.value) logoInputRef.value.value = ''
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
    restaurant.value = res.data ?? restaurant.value
    toastStore.success('Banner updated.')
    if (bannerInputRef.value) bannerInputRef.value.value = ''
  } catch (e) {
    imageUploadErrors.value.banner = normalizeApiError(e).message ?? 'Upload failed.'
  }
}

function closeImageModal() {
  showImageModal.value = false
  imageUploadErrors.value = { logo: '', banner: '' }
}

/** Domain suffix for subdomain URL (e.g. ".yourdomain.com"). Set VITE_APP_PUBLIC_DOMAIN in .env. */
const publicDomainSuffix = (() => {
  const d = import.meta.env.VITE_APP_PUBLIC_DOMAIN
  if (d && typeof d === 'string' && d.trim()) return '.' + d.trim().replace(/^\.+/, '')
  return '.yourdomain.com'
})()

const restaurantUrl = computed(() => {
  const r = restaurant.value
  if (!r?.slug) return ''
  const base = publicDomainSuffix.startsWith('.') ? publicDomainSuffix.slice(1) : publicDomainSuffix
  return `https://${r.slug}.${base}`
})

function copyRestaurantUrl() {
  if (!restaurantUrl.value) return
  navigator.clipboard.writeText(restaurantUrl.value).then(() => {
    copyDone.value = true
    setTimeout(() => { copyDone.value = false }, 2000)
  })
}

const socialLinks = computed(() => {
  const r = restaurant.value?.social_links
  if (!r || typeof r !== 'object') return []
  const names = ['facebook', 'instagram', 'twitter', 'linkedin']
  return names
    .filter((n) => r[n])
    .map((n) => ({ name: n.charAt(0).toUpperCase() + n.slice(1), url: r[n] }))
})

async function fetchOne() {
  loading.value = true
  restaurant.value = null
  try {
    const res = await restaurantService.get(route.params.uuid)
    restaurant.value = res.data ?? null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else console.error(normalizeApiError(e).message)
  } finally {
    loading.value = false
  }
}

function confirmDelete() {
  deleteConfirmSlug.value = ''
  showDeleteConfirm.value = true
}

function closeDeleteConfirm() {
  showDeleteConfirm.value = false
  deleteConfirmSlug.value = ''
}

async function doDelete() {
  if (!restaurant.value?.uuid || deleteConfirmSlug.value !== restaurant.value.slug) return
  deleting.value = true
  try {
    await restaurantService.delete(restaurant.value.uuid)
    closeDeleteConfirm()
    router.push({ name: 'Restaurants' })
  } catch (e) {
    const { message } = normalizeApiError(e)
    console.error(message)
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchOne())
watch(() => route.params.uuid, () => fetchOne())
</script>
