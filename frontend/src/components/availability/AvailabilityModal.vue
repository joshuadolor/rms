<template>
  <AppModal
    :open="open"
    :title="title"
    :description="entityName ? `${entityName} — when to show on the public menu.` : 'Set when this is available. Leave as “All available” for no time restrictions.'"
    @close="onCancel"
  >
    <div data-testid="availability-modal" class="contents">
    <form class="space-y-4" novalidate @submit.prevent="onSave">
      <fieldset class="space-y-3">
        <legend class="sr-only">Availability mode</legend>
        <label
          class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-colors min-h-[44px]"
          :class="isAllAvailable ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-900'"
        >
          <input
            v-model="isAllAvailable"
            type="radio"
            name="availability-mode"
            :value="true"
            class="w-5 h-5 text-primary border-slate-300 focus:ring-primary"
            @change="onModeChange(true)"
          >
          <span class="font-medium text-charcoal dark:text-white">All available</span>
          <span class="text-sm text-slate-500 dark:text-slate-400">No time restrictions (e.g. menu del día)</span>
        </label>
        <label
          class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-colors min-h-[44px]"
          :class="!isAllAvailable ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-900'"
        >
          <input
            v-model="isAllAvailable"
            type="radio"
            name="availability-mode"
            :value="false"
            class="w-5 h-5 text-primary border-slate-300 focus:ring-primary"
            @change="onModeChange(false)"
          >
          <span class="font-medium text-charcoal dark:text-white">Set specific times</span>
          <span class="text-sm text-slate-500 dark:text-slate-400">Choose days and time slots</span>
        </label>
      </fieldset>

      <div v-if="!isAllAvailable" class="pt-2">
        <AvailabilitySchedule
          v-model="scheduleLocal"
          context="item"
          :day-errors="dayErrors"
          :summary-error="summaryError"
          data-testid="availability-modal-schedule"
        />
      </div>

      <p v-if="saveError || apiSaveError" role="alert" class="text-sm text-red-600 dark:text-red-400">
        {{ saveError || apiSaveError }}
      </p>
    </form>
    </div>
    <template #footer>
      <AppButton variant="secondary" class="min-h-[44px]" type="button" @click="onCancel">
        Cancel
      </AppButton>
      <AppButton variant="primary" class="min-h-[44px]" type="button" @click="onSave">
        Save
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AvailabilitySchedule from '@/components/availability/AvailabilitySchedule.vue'
import { DAY_KEYS, validateOperatingHours } from '@/utils/availability'

const props = defineProps({
  open: { type: Boolean, default: false },
  /** null = all available; object = OperatingHours-shaped schedule */
  modelValue: { type: [Object, null], default: null },
  title: { type: String, default: 'Availability' },
  entityName: { type: String, default: '' },
  /** API save error message from parent (e.g. 422/403); shown in modal when set */
  apiSaveError: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save', 'close'])

const isAllAvailable = ref(true)
const scheduleLocal = ref(null)
const dayErrors = ref({})
const summaryError = ref('')
const saveError = ref('')

function defaultDay() {
  return {
    open: true,
    slots: [{ from: '09:00', to: '21:00' }],
  }
}

function buildDefaultSchedule() {
  const out = {}
  for (const key of DAY_KEYS) {
    out[key] = defaultDay()
  }
  return out
}

function buildScheduleFromValue(value) {
  const out = {}
  for (const key of DAY_KEYS) {
    const existing = value?.[key]
    const hasValidDay = existing && typeof existing === 'object' && Array.isArray(existing?.slots)
    if (hasValidDay) {
      out[key] = {
        open: existing.open !== false,
        slots: existing.slots.map((s) => ({
          from: s.from || '09:00',
          to: s.to || '21:00',
        })),
      }
    } else {
      out[key] = defaultDay()
    }
  }
  return out
}

function initFromModel() {
  const val = props.modelValue
  const isNull = val == null || (typeof val === 'object' && Object.keys(val).length === 0)
  isAllAvailable.value = isNull
  scheduleLocal.value = isNull ? buildDefaultSchedule() : buildScheduleFromValue(val)
  dayErrors.value = {}
  summaryError.value = ''
  saveError.value = ''
}

function onModeChange(all) {
  isAllAvailable.value = all
  if (!all && scheduleLocal.value == null) {
    scheduleLocal.value = buildDefaultSchedule()
  }
  dayErrors.value = {}
  summaryError.value = ''
  saveError.value = ''
}

function onCancel() {
  emit('close')
}

function onSave() {
  saveError.value = ''
  if (isAllAvailable.value) {
    emit('save', null)
    return
  }
  const result = validateOperatingHours(scheduleLocal.value)
  if (!result.valid) {
    dayErrors.value = result.errors
    const firstDay = DAY_KEYS.find((d) => result.errors[d])
    summaryError.value = firstDay ? result.errors[firstDay] : 'Please fix the errors below.'
    return
  }
  dayErrors.value = {}
  summaryError.value = ''
  emit('save', scheduleLocal.value)
}

watch(
  () => [props.open, props.modelValue],
  () => {
    if (props.open) initFromModel()
  },
  { immediate: true }
)
</script>
