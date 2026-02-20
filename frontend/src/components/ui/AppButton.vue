<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="buttonClasses"
    @click="onClick"
  >
    <span v-if="$slots.icon" class="flex items-center justify-center shrink-0">
      <slot name="icon" />
    </span>
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'primarySubtle', 'secondary', 'sage', 'ghost', 'icon'].includes(v),
  },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
  class: { type: String, default: '' },
})

const emit = defineEmits(['click'])

function onClick(e) {
  e.stopPropagation()
  emit('click', e)
}

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center gap-2 font-semibold transition-all rounded-lg'
  const sizes = {
    sm: 'py-2 px-4 text-sm',
    md: 'py-2.5 px-6 text-sm',
    lg: 'py-3.5 px-6 text-sm',
  }
  const variants = {
    primary: 'bg-primary hover:bg-primary/90 text-white shadow-sm shadow-primary/20 disabled:bg-primary/50 disabled:cursor-not-allowed',
    primarySubtle: 'bg-primary/20 text-primary border border-primary/10 hover:bg-primary/30',
    secondary: 'border-2 border-charcoal/10 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-zinc-800 text-charcoal dark:text-white',
    sage: 'bg-sage text-white hover:bg-sage/90 shadow-sm shadow-sage/20',
    ghost: 'text-gray-500 hover:text-primary',
    icon: 'w-10 h-10 rounded-full border border-gray-100 dark:border-zinc-800 flex items-center justify-center text-primary hover:bg-primary/5',
  }
  return [base, sizes[props.size], variants[props.variant], props.class].filter(Boolean).join(' ')
})
</script>
