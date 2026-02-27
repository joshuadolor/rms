<template>
  <div class="space-y-6" data-testid="restaurant-contacts">
    <header>
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Contact numbers</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Add phone, WhatsApp, or other numbers. Only active contacts appear on your public page.
      </p>
    </header>

    <!-- Add / Edit form -->
    <div
      v-if="showForm"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-6 space-y-4"
      data-testid="contact-form-card"
    >
      <h3 class="text-lg font-semibold text-charcoal dark:text-white">
        {{ editingContact ? 'Edit contact' : 'Add contact' }}
      </h3>
      <form
        novalidate
        class="space-y-4"
        data-testid="contact-form"
        @submit.prevent="submitForm"
      >
        <p v-if="formError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ formError }}</p>

        <div>
          <label for="contact-type" class="block text-sm font-semibold text-charcoal dark:text-white mb-1">Type</label>
          <select
            id="contact-type"
            v-model="form.type"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary min-h-[44px]"
            aria-describedby="contact-type-error"
            :aria-invalid="!!fieldErrors.type"
          >
            <option v-for="t in contactTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
          </select>
          <p id="contact-type-error" v-if="fieldErrors.type" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ fieldErrors.type }}</p>
        </div>

        <div>
          <label for="contact-value" class="block text-sm font-semibold text-charcoal dark:text-white mb-1">Phone or URL <span class="text-red-500">*</span></label>
          <input
            id="contact-value"
            v-model="form.value"
            type="text"
            autocomplete="tel"
            maxlength="500"
            placeholder="e.g. +1 234 567 8900 or https://..."
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary min-h-[44px]"
            :aria-invalid="!!fieldErrors.value"
            :aria-describedby="fieldErrors.value ? 'contact-value-error' : undefined"
          />
          <p id="contact-value-error" v-if="fieldErrors.value" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ fieldErrors.value }}</p>
        </div>

        <div>
          <label for="contact-label" class="block text-sm font-semibold text-charcoal dark:text-white mb-1">Label (optional)</label>
          <input
            id="contact-label"
            v-model="form.label"
            type="text"
            maxlength="100"
            placeholder="e.g. Reservations, Kitchen"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary min-h-[44px]"
            :aria-invalid="!!fieldErrors.label"
            :aria-describedby="fieldErrors.label ? 'contact-label-error' : undefined"
          />
          <p id="contact-label-error" v-if="fieldErrors.label" class="text-xs text-red-600 dark:text-red-400 mt-1" role="alert">{{ fieldErrors.label }}</p>
        </div>

        <div class="flex items-center gap-3 min-h-[44px]">
          <button
            type="button"
            role="switch"
            :aria-checked="form.is_active"
            aria-label="Show on public page"
            class="relative inline-flex items-center h-8 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 p-2 -m-2"
            :class="form.is_active ? 'bg-primary' : 'bg-slate-200 dark:bg-zinc-600'"
            @click="form.is_active = !form.is_active"
          >
            <span
              class="pointer-events-none inline-block h-6 w-6 shrink-0 rounded-full bg-white shadow ring-0 transition duration-200 ease-out"
              :class="form.is_active ? 'translate-x-6' : 'translate-x-0.5'"
            />
          </button>
          <span class="text-sm text-slate-600 dark:text-slate-400">Show on public page</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
          <AppButton type="submit" variant="primary" class="min-h-[44px]" :disabled="saving" data-testid="contact-form-submit">
            <template v-if="saving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ saving ? 'Saving…' : (editingContact ? 'Save' : 'Add contact') }}
          </AppButton>
          <AppButton type="button" variant="secondary" class="min-h-[44px]" data-testid="contact-form-cancel" @click="closeForm">
            Cancel
          </AppButton>
        </div>
      </form>
    </div>

    <div v-else>
      <AppButton variant="primary" class="min-h-[44px] mb-4" data-testid="contact-add-button" @click="openAddForm">
        <template #icon>
          <span class="material-icons">add</span>
        </template>
        Add contact
      </AppButton>
    </div>

    <!-- List -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 2" :key="i" class="h-20 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse" />
    </div>

    <div v-else-if="errorList" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-6 text-center">
      <p class="text-slate-500 dark:text-slate-400">{{ errorList }}</p>
      <AppButton variant="secondary" class="mt-4 min-h-[44px]" @click="fetchContacts">Retry</AppButton>
    </div>

    <div v-else-if="!contacts.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
      <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">contact_phone</span>
      <p class="mt-4 text-slate-500 dark:text-slate-400">No contact numbers yet. Add one so customers can reach you.</p>
    </div>

    <ul v-else class="space-y-3" data-testid="contacts-list">
      <li
        v-for="c in contacts"
        :key="c.uuid"
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        data-testid="contact-item"
      >
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-charcoal dark:text-white">{{ c.typeLabel }}</span>
            <span
              v-if="!c.is_active"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-slate-400"
            >
              Hidden
            </span>
          </div>
          <p class="text-slate-600 dark:text-slate-300 font-mono mt-0.5 break-all">{{ c.value ?? c.number }}</p>
          <p v-if="c.label" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ c.label }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-lg border-2 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            :class="c.is_active ? 'bg-primary border-primary text-white' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-slate-400'"
            :title="c.is_active ? 'Hide on public page' : 'Show on public page'"
            :aria-label="(c.is_active ? 'Hide' : 'Show') + ' this contact on public page'"
            @click="toggleActive(c)"
          >
            <span class="material-icons text-xl">{{ c.is_active ? 'visibility' : 'visibility_off' }}</span>
          </button>
          <AppButton
            variant="ghost"
            size="sm"
            class="min-h-[44px] min-w-[44px]"
            title="Edit"
            aria-label="Edit contact"
            data-testid="contact-edit-button"
            @click="openEditForm(c)"
          >
            <template #icon><span class="material-icons">edit</span></template>
          </AppButton>
          <AppButton
            variant="ghost"
            size="sm"
            class="min-h-[44px] min-w-[44px] text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 dark:text-red-400"
            title="Delete"
            aria-label="Delete contact"
            data-testid="contact-delete-button"
            @click="confirmDelete(c)"
          >
            <template #icon><span class="material-icons">delete</span></template>
          </AppButton>
        </div>
      </li>
    </ul>

    <!-- Delete confirm -->
    <div
      v-if="contactToDelete"
      ref="deleteModalRef"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-labelledby="contact-delete-title"
      data-testid="contact-delete-modal"
      @keydown="onDeleteModalKeydown"
    >
      <div ref="deleteDialogRef" class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 id="contact-delete-title" class="font-bold text-charcoal dark:text-white mb-2">Delete this contact?</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">This cannot be undone.</p>
        <div class="flex gap-3">
          <AppButton variant="secondary" class="flex-1 min-h-[44px]" data-testid="contact-delete-cancel" @click="closeDeleteConfirm">Cancel</AppButton>
          <AppButton variant="primary" class="flex-1 min-h-[44px] bg-red-600 hover:bg-red-700" data-testid="contact-delete-confirm" :disabled="deleting" @click="doDelete">
            <template v-if="deleting" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
            Delete
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import RestaurantContact, { CONTACT_TYPES } from '@/models/RestaurantContact.js'
import { contactService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const props = defineProps({
  restaurantUuid: { type: String, default: '' },
  tabActive: { type: Boolean, default: true },
})

const toastStore = useToastStore()

const contacts = ref([])
const loading = ref(false)
const errorList = ref(null)
const showForm = ref(false)
const editingContact = ref(null)
const form = ref({
  type: 'phone',
  value: '',
  label: '',
  is_active: true,
})
const fieldErrors = ref({})
const formError = ref('')
const saving = ref(false)
const contactToDelete = ref(null)
const deleting = ref(false)
const deleteModalRef = ref(null)
const deleteDialogRef = ref(null)
/** Element that had focus when the delete modal opened (the delete button); restored on close. */
let deleteModalPreviousActiveElement = null

const contactTypes = CONTACT_TYPES

function typeLabel(t) {
  const labels = {
    whatsapp: 'WhatsApp', mobile: 'Mobile', phone: 'Phone', fax: 'Fax', other: 'Other',
    facebook: 'Facebook', instagram: 'Instagram', twitter: 'Twitter', website: 'Website',
  }
  return labels[t] ?? t
}

function validateForm() {
  const err = {}
  const val = (form.value.value ?? form.value.number ?? '').trim()
  if (!val) err.value = form.value.type && ['facebook', 'instagram', 'twitter', 'website'].includes(form.value.type) ? 'URL is required.' : 'Phone number is required.'
  else if (val.length > 500) err.value = 'Must be at most 500 characters.'
  const type = form.value.type
  if (!CONTACT_TYPES.includes(type)) err.type = 'Please choose a valid type.'
  const label = (form.value.label ?? '').trim()
  if (label.length > 100) err.label = 'Label must be at most 100 characters.'
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

function resetForm() {
  form.value = { type: 'phone', value: '', label: '', is_active: true }
  fieldErrors.value = {}
  formError.value = ''
  editingContact.value = null
}

function openAddForm() {
  resetForm()
  showForm.value = true
}

function openEditForm(c) {
  editingContact.value = c
  form.value = {
    type: c.type,
    value: c.value ?? c.number ?? '',
    label: c.label ?? '',
    is_active: c.is_active,
  }
  fieldErrors.value = {}
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  resetForm()
}

async function fetchContacts() {
  if (!props.restaurantUuid) return
  loading.value = true
  errorList.value = null
  try {
    const res = await contactService.listContacts(props.restaurantUuid)
    const list = (res.data ?? []).map((d) => RestaurantContact.fromApi(d))
    contacts.value = list
  } catch (e) {
    errorList.value = normalizeApiError(e).message ?? 'Failed to load contacts.'
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  if (!props.restaurantUuid) return
  if (!validateForm()) return
  saving.value = true
  fieldErrors.value = {}
  formError.value = ''
  try {
    if (editingContact.value) {
      const payload = {
        type: form.value.type,
        value: (form.value.value ?? form.value.number ?? '').trim(),
        label: form.value.label.trim() || null,
        is_active: form.value.is_active,
      }
      const res = await contactService.updateContact(props.restaurantUuid, editingContact.value.uuid, payload)
      const updated = RestaurantContact.fromApi(res?.data ?? res)
      const idx = contacts.value.findIndex((c) => c.uuid === editingContact.value.uuid)
      if (idx !== -1) contacts.value.splice(idx, 1, updated)
      toastStore.success('Contact updated.')
    } else {
      const payload = {
        type: form.value.type,
        value: (form.value.value ?? form.value.number ?? '').trim(),
        label: form.value.label.trim() || null,
        is_active: form.value.is_active,
      }
      const res = await contactService.createContact(props.restaurantUuid, payload)
      const created = RestaurantContact.fromApi(res?.data ?? res)
      contacts.value = [...contacts.value, created]
      toastStore.success('Contact added.')
    }
    closeForm()
  } catch (e) {
    const errs = getValidationErrors(e)
    if (errs && Object.keys(errs).length) {
      fieldErrors.value = errs
    } else {
      formError.value = normalizeApiError(e).message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    saving.value = false
  }
}

async function toggleActive(c) {
  if (!props.restaurantUuid) return
  try {
    await contactService.updateContact(props.restaurantUuid, c.uuid, { is_active: !c.is_active })
    const updated = new RestaurantContact({ ...c.toJSON(), is_active: !c.is_active })
    const idx = contacts.value.findIndex((x) => x.uuid === c.uuid)
    if (idx !== -1) contacts.value.splice(idx, 1, updated)
    toastStore.success(updated.is_active ? 'Contact is now visible on your public page.' : 'Contact is now hidden.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message ?? 'Failed to update.')
  }
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

function confirmDelete(c) {
  deleteModalPreviousActiveElement = document.activeElement
  contactToDelete.value = c
}

function closeDeleteConfirm() {
  contactToDelete.value = null
  nextTick(() => {
    const el = deleteModalPreviousActiveElement
    if (el && typeof el.focus === 'function') el.focus()
    deleteModalPreviousActiveElement = null
  })
}

async function doDelete() {
  if (!contactToDelete.value || !props.restaurantUuid) return
  deleting.value = true
  try {
    await contactService.deleteContact(props.restaurantUuid, contactToDelete.value.uuid)
    contacts.value = contacts.value.filter((x) => x.uuid !== contactToDelete.value.uuid)
    closeDeleteConfirm()
    toastStore.success('Contact deleted.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message ?? 'Failed to delete.')
  } finally {
    deleting.value = false
  }
}

watch(
  () => [props.restaurantUuid, props.tabActive],
  ([uuid, active]) => {
    if (uuid && active) fetchContacts()
  },
  { immediate: true }
)

watch(contactToDelete, (val) => {
  if (val) {
    nextTick(() => {
      const focusables = getDeleteModalFocusables()
      if (focusables.length > 0) focusables[0].focus()
    })
  }
})
</script>
