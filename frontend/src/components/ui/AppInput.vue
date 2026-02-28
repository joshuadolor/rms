<template>
  <div class="space-y-1">
    <label v-if="label" :for="inputId" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
      {{ label }}
    </label>
    <div class="relative">
      <span v-if="$slots.prefix" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
        <slot name="prefix" />
      </span>
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :required="required"
        :disabled="disabled"
        :aria-describedby="describedBy || (error ? errorId : undefined)"
        :aria-invalid="invalid || !!error"
        class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-white dark:bg-zinc-800 border-0 py-3"
        :class="[
          $slots.prefix ? 'pl-10' : 'pl-4',
          $slots.suffix ? 'pr-10' : 'pr-4',
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <span v-if="$slots.suffix" class="absolute inset-y-0 right-0 pr-3 flex items-center">
        <slot name="suffix" />
      </span>
    </div>
    <p v-if="hint && !error" class="text-xs text-gray-500">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="text-xs text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  autocomplete: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  hint: { type: String, default: '' },
  /** ID of the element that describes this input (e.g. error message). Used for aria-describedby. */
  describedBy: { type: String, default: '' },
  /** When true, sets aria-invalid for screen readers. */
  invalid: { type: Boolean, default: false },
  /** Validation error message shown underneath the field (after submit). */
  error: { type: String, default: '' },
})

defineEmits(['update:modelValue'])

const inputId = computed(() => `input-${Math.random().toString(36).slice(2, 9)}`)
const errorId = computed(() => `${inputId.value}-error`)
</script>
