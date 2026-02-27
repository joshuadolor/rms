<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :aria-label="ariaLabel"
    class="inline-flex shrink-0 cursor-pointer items-center justify-center rounded-full border-2 border-transparent p-1.5 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 min-h-[44px] min-w-[68px]"
    :data-testid="testId"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      class="relative flex h-8 w-14 shrink-0 items-center rounded-full transition-colors duration-200"
      :class="[
        modelValue ? 'bg-primary' : 'bg-slate-200 dark:bg-zinc-600',
      ]"
      aria-hidden="true"
    >
      <span
        class="pointer-events-none inline-block h-6 w-6 shrink-0 rounded-full bg-white shadow-sm transition-transform duration-200 ease-out"
        :class="modelValue ? 'translate-x-6' : 'translate-x-0.5'"
      />
    </span>
  </button>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, required: true },
  ariaLabel: { type: String, default: 'Toggle' },
  testId: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>
