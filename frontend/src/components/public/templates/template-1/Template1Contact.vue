<template>
  <section class="max-w-6xl mx-auto px-6 py-20" id="contact" aria-labelledby="contact-heading-t1">
    <div class="grid grid-cols-1 gap-16" :class="{ 'md:grid-cols-2': hasSchedule }">
      <div>
        <h2 id="contact-heading-t1" class="text-3xl font-bold tracking-tight mb-8 text-t1-neutral-dark">{{ $t('public.contactUs') }}</h2>
        <div class="space-y-6">
          <div v-for="c in contacts" :key="c.uuid" class="flex gap-4">
            <span class="material-symbols-outlined text-t1-neutral-muted shrink-0" :aria-hidden="true">
              {{ contactIcon(c.type) }}
            </span>
            <div class="min-w-0">
              <p class="font-bold text-t1-neutral-dark">{{ contactTypeLabel(c.type) }}{{ c.label ? ` — ${c.label}` : '' }}</p>
              <template v-if="isLinkType(c.type) && contactVal(c)">
                <a
                  :href="contactVal(c)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center min-h-[44px] py-2 -my-2 text-t1-primary hover:underline font-medium break-all"
                >
                  {{ contactVal(c) }}
                </a>
              </template>
              <template v-else-if="c.type === 'whatsapp' && whatsAppUrl(contactVal(c))">
                <a
                  :href="whatsAppUrl(contactVal(c))"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center min-h-[44px] py-2 -my-2 text-t1-primary hover:underline font-medium"
                >
                  {{ contactVal(c) }}
                </a>
                <span class="text-t1-neutral-muted text-sm ml-1">(WhatsApp)</span>
              </template>
              <template v-else-if="isCallable(c.type)">
                <a
                  :href="`tel:${contactVal(c).replace(/\s/g, '')}`"
                  class="inline-flex items-center min-h-[44px] py-2 -my-2 text-t1-primary hover:underline font-medium"
                >
                  {{ contactVal(c) }}
                </a>
              </template>
              <p v-else class="text-t1-neutral-muted">{{ contactVal(c) }}</p>
            </div>
          </div>
          <div v-if="!contacts.length" class="text-t1-neutral-muted">{{ $t('public.noContactListed') }}</div>
        </div>
      </div>
      <div v-if="hasSchedule">
        <h2 class="text-3xl font-bold tracking-tight mb-8 text-t1-neutral-dark">{{ $t('public.openingHours') }}</h2>
        <div class="space-y-3">
          <div
            v-for="row in displayHours"
            :key="row.day"
            class="flex justify-between border-b border-t1-border pb-2"
          >
            <span class="font-medium text-t1-neutral-dark">{{ row.label }}</span>
            <span class="text-t1-neutral-muted">{{ row.text }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatOperatingHoursForDisplay } from '@/utils/availability'
import { buildWhatsAppUrl, contactValue, isLinkType } from '@/utils/contact'

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  operatingHours: { type: Object, default: null },
})

const { t } = useI18n()
const displayHours = computed(() => formatOperatingHoursForDisplay(props.operatingHours))
const hasSchedule = computed(() => displayHours.value.some((row) => row.text !== 'Closed'))

function contactVal(c) {
  return contactValue(c)
}

function contactIcon(type) {
  if (type === 'whatsapp') return 'chat'
  if (isLinkType(type)) return 'link'
  return 'call'
}

function contactTypeLabel(type) {
  const key = 'public.' + type
  try {
    const translated = t(key)
    if (translated && translated !== key) return translated
  } catch (_) {}
  return type
}

function whatsAppUrl(number) {
  return buildWhatsAppUrl(number)
}

function isCallable(type) {
  return ['mobile', 'phone', 'fax'].includes(type)
}
</script>
