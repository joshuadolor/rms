import { useAppStore } from '@/stores/app'
import { usePaidFeatureStore } from '@/stores/paidFeature'

/**
 * Vue directive: v-require-paid
 *
 * Use on clickable elements (buttons, links) that trigger a paid-only feature (e.g. "Beautify with AI").
 * On click:
 * - If the user is paid (is_paid): the click proceeds normally (default action or @click handler runs).
 * - If the user is not paid: default is prevented, propagation is stopped, and the "paid feature required" modal is shown.
 *
 * Usage:
 *   <button v-require-paid @click="beautifyWithAi">Beautify with AI</button>
 *
 * The modal is a placeholder for the actual paid feature; the real AI beautification will be implemented later.
 */
export function requirePaidDirective(app) {
  app.directive('require-paid', {
    mounted(el, binding) {
      el._requirePaidClick = (e) => {
        const appStore = useAppStore()
        const user = appStore.user
        const isPaid = user?.isPaid === true
        if (!isPaid) {
          e.preventDefault()
          e.stopPropagation()
          const paidStore = usePaidFeatureStore()
          paidStore.open()
        }
        // If paid, do nothing here — the element's normal click handler runs
      }
      el.addEventListener('click', el._requirePaidClick, true)
    },
    beforeUnmount(el) {
      if (el._requirePaidClick) {
        el.removeEventListener('click', el._requirePaidClick, true)
      }
    },
  })
}
