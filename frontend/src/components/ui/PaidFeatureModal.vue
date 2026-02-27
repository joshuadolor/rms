<template>
  <AppModal
    :open="paidFeatureStore.visible"
    title="Paid feature"
    description="Upgrade to use this feature."
    data-testid="paid-feature-modal"
    @close="paidFeatureStore.close()"
  >
    <p class="text-slate-600 dark:text-slate-300">
      Beautify your menu images with AI is available on a paid plan. Upgrade to unlock AI-powered image enhancement.
    </p>
    <template #footer>
      <AppButton type="button" variant="secondary" class="min-h-[44px]" @click="paidFeatureStore.close()">
        Maybe later
      </AppButton>
      <AppButton
        type="button"
        variant="primary"
        class="min-h-[44px]"
        data-testid="paid-feature-upgrade"
        @click="handleUpgrade"
      >
        Upgrade
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { usePaidFeatureStore } from '@/stores/paidFeature'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'

const paidFeatureStore = usePaidFeatureStore()

function handleUpgrade() {
  paidFeatureStore.close()
  // TODO: navigate to upgrade/billing when implemented
}
</script>
