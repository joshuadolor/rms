<template>
  <div class="flex min-h-screen overflow-hidden font-display bg-background-light dark:bg-background-dark">
    <!-- Left: brand image (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 relative">
      <div class="absolute inset-0 bg-primary/10 mix-blend-multiply z-10" />
      <div class="absolute inset-0 bg-gradient-to-t from-background-dark/80 via-transparent to-background-dark/20 z-10" />
      <img
        src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1200"
        alt=""
        role="presentation"
        class="absolute inset-0 w-full h-full object-cover"
        style="
          mask-image:
            linear-gradient(
              to bottom,
              transparent 0%,
              black 40%,
              black 40%,
              transparent 100%
            );
          -webkit-mask-image:
            linear-gradient(
              to bottom,
              transparent 0%,
              black 40%,
              black 40%,
              transparent 100%
            );
        "
      />
      <div class="relative z-20 flex flex-col justify-between h-full p-12 text-white">
        <div class="flex items-center gap-2">
          <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white">restaurant</span>
          </div>
          <span class="text-xl font-bold tracking-tight">RMS</span>
        </div>
        <div class="bg-background-dark/80 p-6 rounded-lg">
          <h1 class="text-5xl font-bold leading-tight mb-4">
            {{ $t('app.authHeroTitle') }}<br />
            <span class="text-primary">{{ $t('app.authHeroTitleHighlight') }}</span>
          </h1>
          <p class="text-lg text-white/80 max-w-md">
            {{ $t('app.authHeroSubtitle') }}
          </p>
        </div>
        <p class="text-sm text-white/50">{{ $t('app.authHeroFooter') }}</p>
      </div>
    </div>
    <!-- Right: form slot -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-12 lg:p-24 relative">
      <!-- Language selector: upper right -->
      <div class="absolute top-4 right-4 md:top-6 md:right-6 lg:top-8 lg:right-8 z-10">
        <select
          :value="appLocale"
          class="min-h-[44px] min-w-[44px] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none cursor-pointer"
          :aria-label="$t('app.languageLabel')"
          data-testid="app-locale-select"
          @change="onAppLocaleChange($event.target.value)"
        >
          <option v-for="loc in APP_LOCALES" :key="loc.code" :value="loc.code">
            {{ loc.flag }} {{ loc.label }}
          </option>
        </select>
      </div>
      <div class="w-full max-w-md">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue'
import { i18n } from '@/i18n'
import { APP_LOCALES } from '@/config/app-locales'
import { setStoredAppLocale } from '@/config/app-locales'

const appLocale = computed({
  get: () => i18n.global.locale.value,
  set: (v) => { i18n.global.locale.value = v },
})

function applyDocDir(loc) {
  document.documentElement.dir = loc === 'ar' ? 'rtl' : 'ltr'
}

onMounted(() => {
  applyDocDir(i18n.global.locale.value)
})

watch(() => i18n.global.locale.value, applyDocDir)

function onAppLocaleChange(code) {
  if (!code) return
  i18n.global.locale.value = code
  setStoredAppLocale(code)
  applyDocDir(code)
}
</script>
