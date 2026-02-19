<template>
  <AvailabilitySchedule
    :model-value="modelValue"
    context="restaurant"
    :label="label"
    :description="description"
    :hint="hint"
    :day-errors="dayErrors"
    :summary-error="summaryError"
    data-testid="availability-schedule"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import AvailabilitySchedule from '@/components/availability/AvailabilitySchedule.vue'

defineProps({
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  /** Override section label (e.g. "Restaurant operating hours"). */
  label: { type: String, default: '' },
  /** Override info box message. */
  description: { type: String, default: '' },
  /** Override hint under the schedule heading. */
  hint: { type: String, default: '' },
  /** Per-day validation errors (dayKey -> message). */
  dayErrors: { type: Object, default: () => ({}) },
  /** Summary error above the schedule. */
  summaryError: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>
