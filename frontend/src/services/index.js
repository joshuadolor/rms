/**
 * Frontend services — use these in components or stores to initiate API calls.
 * Do not call axios/fetch directly from components; go through a service.
 *
 * Usage:
 *   import { authService } from '@/services'
 *   const data = await authService.login({ email, password })
 *   appStore.setUserFromApi(data.user)
 */

export {
  api,
  getBaseUrl,
  normalizeApiError,
  getValidationErrors,
  API_ERROR_GROUPS,
  getErrorGroup,
  reportApiError,
} from './api'
export {
  authService,
  OAUTH_PROVIDERS,
  redirectToGoogle,
  redirectToFacebook,
  redirectToInstagram,
} from './auth.service'
export { restaurantService } from './restaurant.service'
export { menuItemService } from './menuItem.service'
export { menuItemTagService } from './menuItemTag.service'
export { localeService } from './locale.service'
