<template>
  <section class="bg-pale-stone" id="contact" aria-labelledby="contact-heading-t2">
    <div class="max-w-[1400px] mx-auto border-x border-charcoal-blue grid grid-cols-1 md:grid-cols-12">
      <div class="p-12 md:p-20 border-b md:border-b-0 space-y-16" :class="hasSchedule ? 'md:col-span-5 md:border-r border-charcoal-blue' : 'md:col-span-12 border-charcoal-blue'">
        <div>
          <h2 id="contact-heading-t2" class="heading-utilitarian text-5xl font-extrabold mb-12 text-charcoal-blue">Contact Us</h2>
          <div class="space-y-12">
            <div v-for="c in contacts" :key="c.uuid" class="flex gap-6 group">
              <div class="w-12 h-12 bg-concrete-gray border border-charcoal-blue flex items-center justify-center shrink-0 group-hover:bg-charcoal-blue group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-3xl" :aria-hidden="true">{{ contactIcon(c.type) }}</span>
              </div>
              <div class="min-w-0">
                <p class="font-mono text-xs text-oxidized-copper font-bold mb-2 uppercase">
                  {{ contactSectionLabel(c.type) }}
                </p>
                <template v-if="isLinkType(c.type) && contactVal(c)">
                  <a
                    :href="contactVal(c)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-h-[44px] inline-flex items-center py-2 -my-2 text-xl font-bold leading-tight text-charcoal-blue hover:underline break-all"
                  >
                    {{ contactVal(c) }}
                  </a>
                  <p v-if="c.label" class="text-sm text-charcoal-blue/80 mt-0.5">{{ c.label }}</p>
                </template>
                <template v-else-if="c.type === 'whatsapp' && whatsAppUrl(contactVal(c))">
                  <a
                    :href="whatsAppUrl(contactVal(c))"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-h-[44px] inline-flex items-center py-2 -my-2 text-xl font-bold leading-tight text-charcoal-blue hover:underline"
                  >
                    {{ contactVal(c) }}
                  </a>
                  <p v-if="c.label" class="text-sm text-charcoal-blue/80 mt-0.5">{{ c.label }}</p>
                </template>
                <template v-else-if="isCallable(c.type)">
                  <a
                    :href="`tel:${contactVal(c).replace(/\s/g, '')}`"
                    class="min-h-[44px] inline-flex items-center py-2 -my-2 text-xl font-bold leading-tight text-charcoal-blue hover:underline"
                  >
                    {{ contactVal(c) }}
                  </a>
                  <p v-if="c.label" class="text-sm text-charcoal-blue/80 mt-0.5">{{ c.label }}</p>
                </template>
                <template v-else>
                  <p class="text-xl font-bold leading-tight text-charcoal-blue">{{ contactVal(c) }}</p>
                  <p v-if="c.label" class="text-sm text-charcoal-blue/80 mt-0.5">{{ c.label }}</p>
                </template>
              </div>
            </div>
            <p v-if="!contacts.length" class="text-charcoal-blue/70">No contact numbers or links listed.</p>
          </div>
        </div>
      </div>
      <div v-if="hasSchedule" class="md:col-span-7 p-12 md:p-20 bg-white space-y-12">
        <h2 class="heading-utilitarian text-5xl font-extrabold text-charcoal-blue">Operating Hours</h2>
        <div class="grid grid-cols-1 gap-4">
          <div
            v-for="row in displayHours"
            :key="row.day"
            class="flex justify-between items-center border-b-2 border-concrete-gray pb-4"
          >
            <span class="heading-utilitarian text-2xl font-bold text-charcoal-blue">{{ row.label }}</span>
            <span class="font-mono text-xl font-bold text-white px-4 py-1" :class="row.text === 'Closed' ? 'bg-charcoal-blue' : 'bg-oxidized-copper'">{{ row.text }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { formatOperatingHoursForDisplay } from '@/utils/availability'
import { buildWhatsAppUrl, contactValue, isLinkType } from '@/utils/contact'

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  operatingHours: { type: Object, default: null },
})

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

function contactSectionLabel(type) {
  if (type === 'whatsapp') return '// WhatsApp'
  if (isLinkType(type)) return '// Link'
  return '// Comms Link'
}

function whatsAppUrl(number) {
  return buildWhatsAppUrl(number)
}

function isCallable(type) {
  return ['mobile', 'phone', 'fax'].includes(type)
}
</script>
