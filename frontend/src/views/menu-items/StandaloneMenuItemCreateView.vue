<template>
  <div class="max-w-3xl">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Add menu item</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Add a name and optional description. No restaurant needed.</p>
    </header>

    <form class="space-y-6" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="error"
        role="alert"
        class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
        <AppInput
          v-model="form.name"
          label="Name"
          type="text"
          placeholder="e.g. Margherita Pizza"
          :error="fieldErrors.name"
        />
        <AppInput
          v-model="form.price"
          label="Price (optional)"
          type="number"
          min="0"
          step="0.01"
          placeholder="e.g. 10.00"
          :error="fieldErrors.price"
        />
        <div>
          <label for="desc" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
          <textarea
            id="desc"
            v-model="form.description"
            rows="3"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-y"
            placeholder="Short description"
          />
        </div>
      </div>

      <div class="flex flex-col-reverse sm:flex-row gap-3">
        <router-link :to="{ name: 'MenuItems' }">
          <AppButton type="button" variant="secondary" class="min-h-[44px]">Cancel</AppButton>
        </router-link>
        <AppButton
          type="submit"
          variant="primary"
          class="min-h-[44px]"
          :disabled="saving"
        >
          <template v-if="saving" #icon>
            <span class="material-icons animate-spin">sync</span>
          </template>
          {{ saving ? 'Creating…' : 'Create item' }}
        </AppButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { menuItemService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const toastStore = useToastStore()

const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})

const form = reactive({
  name: '',
  description: '',
  price: '',
})

function validate() {
  const err = {}
  if (!(form.name ?? '').trim()) err.name = 'Name is required.'
  const priceNum = form.price === '' || form.price == null ? null : Number(form.price)
  if (priceNum !== null && (Number.isNaN(priceNum) || priceNum < 0)) err.price = 'Price must be 0 or greater.'
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  if (!validate()) return
  saving.value = true
  try {
    const price = form.price === '' || form.price == null ? undefined : Number(form.price)
    await menuItemService.create({
      sort_order: 0,
      price: price !== undefined && !Number.isNaN(price) ? price : undefined,
      translations: {
        en: {
          name: form.name.trim(),
          description: form.description?.trim() || null,
        },
      },
    })
    toastStore.success('Menu item created.')
    router.push({ name: 'MenuItems' })
  } catch (e) {
    const errs = getValidationErrors(e)
    if (Object.keys(errs).length > 0) fieldErrors.value = errs
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    saving.value = false
  }
}

onMounted(() => {})
</script>
