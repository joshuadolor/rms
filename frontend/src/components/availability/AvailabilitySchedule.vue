<template>
  <div class="space-y-4" data-testid="availability-schedule">
    <div class="flex gap-3 p-4 rounded-xl bg-primary/10 dark:bg-primary/20 border border-primary/20">
      <span class="material-icons text-primary shrink-0">info</span>
      <p class="text-sm text-charcoal dark:text-slate-200">
        {{ infoMessage }}
      </p>
    </div>

    <div
      v-if="availabilitySummaryError"
      role="alert"
      class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      data-testid="availability-summary-error"
    >
      {{ availabilitySummaryError }}
    </div>

    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm" data-testid="availability-days">
      <div class="space-y-1 px-4 pt-4 pb-2 border-b border-slate-100 dark:border-slate-800">
        <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          {{ scheduleTitle }}
        </h4>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ scheduleHint }}
        </p>
      </div>
      <div
        v-for="(dayConfig, dayKey) in schedule"
        :key="dayKey"
        class="flex flex-col p-4 border-b border-slate-100 dark:border-slate-800 last:border-b-0"
        :class="{ 'bg-slate-50/50 dark:bg-black/10': !dayConfig.open }"
        :data-testid="`availability-day-${dayKey}`"
      >
        <div class="flex items-center justify-between mb-3">
          <span
            class="text-base font-semibold"
            :class="dayConfig.open ? 'text-charcoal dark:text-white' : 'text-slate-500 dark:text-slate-400'"
          >
            {{ dayLabel(dayKey) }}
          </span>
          <label class="relative inline-flex items-center cursor-pointer min-h-[44px] py-2">
            <input
              :checked="dayConfig.open"
              type="checkbox"
              class="sr-only peer"
              :aria-label="`${dayLabel(dayKey)} open for business`"
              @change="onDayToggle(dayKey)"
            >
            <span
              class="relative inline-block w-11 h-6 shrink-0 rounded-full bg-slate-200 dark:bg-slate-600 transition-colors duration-200 peer-checked:bg-primary"
              aria-hidden="true"
            >
              <span
                class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 pointer-events-none"
                :class="{ 'translate-x-5': dayConfig.open }"
              />
            </span>
          </label>
        </div>

        <template v-if="dayConfig.open">
          <div
            v-for="(slot, slotIndex) in dayConfig.slots"
            :key="slotIndex"
            class="flex items-center gap-3 mb-2"
          >
            <div class="flex-1 relative">
              <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-primary text-sm pointer-events-none">schedule</span>
              <input
                :value="slot.from"
                type="time"
                aria-label="Opening time"
                class="w-full pl-9 pr-3 py-2.5 text-sm bg-white dark:bg-zinc-800 border-0 rounded-lg focus:ring-2 focus:ring-primary/50 text-charcoal dark:text-white min-h-[44px]"
                @input="setSlotTime(dayKey, slotIndex, 'from', ($event.target).value)"
              >
            </div>
            <span class="text-slate-500 dark:text-slate-400 text-sm shrink-0">to</span>
            <div class="flex-1 relative">
              <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-primary text-sm pointer-events-none">schedule</span>
              <input
                :value="slot.to"
                type="time"
                aria-label="Closing time"
                class="w-full pl-9 pr-3 py-2.5 text-sm bg-white dark:bg-zinc-800 border-0 rounded-lg focus:ring-2 focus:ring-primary/50 text-charcoal dark:text-white min-h-[44px]"
                @input="setSlotTime(dayKey, slotIndex, 'to', ($event.target).value)"
              >
            </div>
            <button
              type="button"
              class="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0 focus:outline-none focus:ring-2 focus:ring-primary/50"
              title="Remove time slot"
              aria-label="Remove time slot"
              @click="removeSlot(dayKey, slotIndex)"
            >
              <span class="material-icons text-lg">remove_circle_outline</span>
            </button>
          </div>
          <p
            v-if="dayErrors[dayKey]"
            class="mt-1 text-xs text-red-600 dark:text-red-400"
            role="alert"
            :data-testid="`availability-day-error-${dayKey}`"
          >
            {{ dayErrors[dayKey] }}
          </p>
          <button
            type="button"
            class="mt-1 text-sm font-medium text-primary hover:text-primary/80 flex items-center gap-1.5 min-h-[44px] min-w-[44px] px-3 focus:outline-none focus:ring-2 focus:ring-primary/50 rounded-lg"
            @click="addSlot(dayKey)"
          >
            <span class="material-icons text-lg">add</span>
            Add another time slot
          </button>
        </template>

        <div
          v-else
          class="flex items-center gap-3 opacity-50 pointer-events-none"
        >
          <div class="flex-1 relative">
            <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">schedule</span>
            <input
              type="text"
              value="Closed"
              disabled
              class="w-full pl-9 pr-3 py-2.5 text-sm bg-slate-100 dark:bg-zinc-800 rounded-lg text-slate-500 min-h-[44px]"
            >
          </div>
          <span class="text-slate-500">to</span>
          <div class="flex-1 relative">
            <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">schedule</span>
            <input
              type="text"
              value="Closed"
              disabled
              class="w-full pl-9 pr-3 py-2.5 text-sm bg-slate-100 dark:bg-zinc-800 rounded-lg text-slate-500 min-h-[44px]"
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { DAY_KEYS } from '@/utils/availability'

const props = defineProps({
  /** v-model: object keyed by day (sunday–saturday), each value { open, slots: [{ from, to }] } */
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  /** Context for copy: 'restaurant' | 'item' | custom. Affects default label/hint/message. */
  context: {
    type: String,
    default: 'restaurant',
  },
  /** Override section label (e.g. "Restaurant operating hours" or "Item availability"). */
  label: {
    type: String,
    default: '',
  },
  /** Override info box message. */
  description: {
    type: String,
    default: '',
  },
  /** Override hint under "Weekly schedule". */
  hint: {
    type: String,
    default: '',
  },
  /** Per-day validation errors (dayKey -> message). */
  dayErrors: {
    type: Object,
    default: () => ({}),
  },
  /** Single summary error shown above the schedule (e.g. when embed and errors are from submit). */
  summaryError: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const scheduleTitle = computed(() => {
  if (props.label) return props.label
  if (props.context === 'item') return 'Item availability'
  return 'Weekly schedule'
})

const infoMessage = computed(() => {
  if (props.description) return props.description
  if (props.context === 'item') {
    return 'Set when this item is available. Add multiple slots for split availability (e.g. lunch and dinner).'
  }
  return 'Operating hours affect your online ordering availability and how customers see when you\'re open.'
})

const scheduleHint = computed(() => {
  if (props.hint) return props.hint
  if (props.context === 'item') {
    return 'Set open and close times for each day. Add multiple slots for split hours.'
  }
  return 'Set your restaurant\'s open and close times for each day. Add multiple slots for split hours (e.g. lunch and dinner).'
})

const dayErrors = computed(() => props.dayErrors || {})
const availabilitySummaryError = computed(() => props.summaryError || '')

function defaultDay() {
  return {
    open: true,
    slots: [{ from: '09:00', to: '21:00' }],
  }
}

function buildScheduleFromValue(value) {
  const out = {}
  for (const key of DAY_KEYS) {
    const existing = value?.[key]
    const hasValidDay = existing && typeof existing === 'object' && Array.isArray(existing.slots)
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

const schedule = computed(() => buildScheduleFromValue(props.modelValue))

function dayLabel(key) {
  return key.charAt(0).toUpperCase() + key.slice(1)
}

function onDayToggle(dayKey) {
  const current = buildScheduleFromValue(props.modelValue)
  const day = current[dayKey]
  if (!day) return
  const next = { ...current }
  const newOpen = !day.open
  next[dayKey] = {
    open: newOpen,
    slots: newOpen ? [{ from: '09:00', to: '21:00' }] : [],
  }
  emit('update:modelValue', next)
}

function addSlot(dayKey) {
  const current = buildScheduleFromValue(props.modelValue)
  const day = current[dayKey]
  if (!day?.open) return
  const slots = [...(day.slots || []), { from: '09:00', to: '21:00' }]
  const next = { ...current, [dayKey]: { ...day, slots } }
  emit('update:modelValue', next)
}

function removeSlot(dayKey, slotIndex) {
  const current = buildScheduleFromValue(props.modelValue)
  const day = current[dayKey]
  if (!day?.slots || day.slots.length <= 1) return
  const slots = day.slots.filter((_, i) => i !== slotIndex)
  const next = { ...current, [dayKey]: { ...day, slots } }
  emit('update:modelValue', next)
}

function setSlotTime(dayKey, slotIndex, field, value) {
  const current = buildScheduleFromValue(props.modelValue)
  const day = current[dayKey]
  if (!day?.slots?.[slotIndex]) return
  const slots = day.slots.map((s, i) =>
    i === slotIndex ? { ...s, [field]: value } : s
  )
  const next = { ...current, [dayKey]: { ...day, slots } }
  emit('update:modelValue', next)
}
</script>
