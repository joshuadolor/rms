<template>
  <!-- E2E: data-testid="public-language-dropdown" for public restaurant language selector (header). -->
  <div v-if="showDropdown" class="rms-language-dropdown" data-testid="public-language-dropdown">
    <label :for="selectId" class="rms-language-dropdown__label sr-only">Language</label>
    <select
      :id="selectId"
      :value="currentLocale"
      class="rms-language-dropdown__select"
      aria-label="Select language"
      @change="onChange"
    >
      <option
        v-for="code in languages"
        :key="code"
        :value="code"
      >
        {{ localeLabel(code) }}
      </option>
    </select>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { localeDisplayName } from '@/utils/locale.js'

const props = defineProps({
  /** Array of locale codes from restaurant (e.g. ["en", "nl"]). */
  languages: { type: Array, default: () => [] },
  /** Current locale code. */
  currentLocale: { type: String, default: '' },
})

const emit = defineEmits(['select-locale'])

const showDropdown = computed(() =>
  Array.isArray(props.languages) && props.languages.length > 1
)

const selectId = computed(() => `rms-lang-${Math.random().toString(36).slice(2, 9)}`)

function localeLabel(code) {
  return localeDisplayName(code) || code
}

function onChange(event) {
  const value = event.target?.value
  if (value) emit('select-locale', value)
}
</script>

<style scoped>
.rms-language-dropdown {
  display: inline-flex;
  align-items: center;
}

.rms-language-dropdown__label.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.rms-language-dropdown__select {
  min-height: 44px;
  min-width: 44px;
  padding: 0.5rem 2rem 0.5rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: inherit;
  background-color: transparent;
  border: 1px solid currentColor;
  border-radius: 6px;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;
  background-size: 1rem;
}
.rms-language-dropdown__select:hover {
  opacity: 0.9;
}
.rms-language-dropdown__select:focus {
  outline: none;
}
.rms-language-dropdown__select:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
</style>
