<template>
  <div>
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.profile') }}</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.profileSubtitle') }}</p>
    </header>

    <div class="max-w-xl space-y-6 lg:space-y-8">
      <!-- Profile form -->
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
        <h3 class="font-semibold text-charcoal dark:text-white mb-4">{{ $t('app.profileSection') }}</h3>
        <form class="space-y-4" novalidate @submit.prevent="submitProfile">
          <AppInput
            v-model="profileForm.name"
            :label="$t('app.name')"
            type="text"
            autocomplete="name"
            :error="profileFieldErrors.name"
          />
          <AppInput
            v-model="profileForm.email"
            :label="$t('app.emailAddress')"
            type="email"
            autocomplete="email"
            :error="profileFieldErrors.email"
          />
          <p v-if="user?.pendingEmail" class="text-sm text-amber-600 dark:text-amber-400">
            A verification link was sent to {{ user.pendingEmail }}. Confirm that address to complete the change.
          </p>
          <p v-if="profileMessage && !profileFieldErrors.name && !profileFieldErrors.email" class="text-sm" :class="profileError ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400'">
            {{ profileMessage }}
          </p>
          <AppButton type="submit" variant="primary" :disabled="profileLoading">
            <template v-if="profileLoading" #icon>
              <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
            </template>
            {{ profileLoading ? $t('app.savingProfile') : $t('app.saveProfile') }}
          </AppButton>
        </form>
      </div>

      <!-- Change password form -->
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
        <h3 class="font-semibold text-charcoal dark:text-white mb-4">{{ $t('app.changePassword') }}</h3>
        <form class="space-y-4" novalidate @submit.prevent="submitPassword">
          <AppInput
            v-model="passwordForm.current_password"
            :label="$t('app.currentPassword')"
            type="password"
            autocomplete="current-password"
            :error="passwordFieldErrors.current_password"
          />
          <AppInput
            v-model="passwordForm.password"
            :label="$t('app.newPassword')"
            type="password"
            autocomplete="new-password"
            hint="At least 8 characters, letters and numbers."
            :error="passwordFieldErrors.password"
          />
          <AppInput
            v-model="passwordForm.password_confirmation"
            :label="$t('app.confirmPassword')"
            type="password"
            autocomplete="new-password"
            :error="passwordFieldErrors.password_confirmation"
          />
          <p v-if="passwordMessage && !passwordFieldErrors.current_password && !passwordFieldErrors.password && !passwordFieldErrors.password_confirmation" class="text-sm" :class="passwordError ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400'">
            {{ passwordMessage }}
          </p>
          <AppButton type="submit" variant="primary" :disabled="passwordLoading">
            <template v-if="passwordLoading" #icon>
              <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
            </template>
            {{ passwordLoading ? $t('app.updatingPassword') : $t('app.updatePassword') }}
          </AppButton>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { useToastStore } from '@/stores/toast'
import { authService, normalizeApiError, getValidationErrors } from '@/services'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const appStore = useAppStore()
const toastStore = useToastStore()
const { user } = storeToRefs(appStore)

const profileForm = reactive({
  name: '',
  email: '',
})
const profileLoading = ref(false)
const profileMessage = ref('')
const profileError = ref(false)
const profileFieldErrors = ref({ name: '', email: '' })

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const passwordLoading = ref(false)
const passwordMessage = ref('')
const passwordError = ref(false)
const passwordFieldErrors = ref({ current_password: '', password: '', password_confirmation: '' })

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const MAX_NAME_LENGTH = 255
const MAX_EMAIL_LENGTH = 255
const MIN_PASSWORD_LENGTH = 8

function validateProfile() {
  profileFieldErrors.value = { name: '', email: '' }
  const nameVal = profileForm.name.trim()
  const emailVal = profileForm.email.trim()
  if (nameVal.length > MAX_NAME_LENGTH) {
    profileFieldErrors.value.name = `Name must be at most ${MAX_NAME_LENGTH} characters.`
    return false
  }
  if (emailVal && !EMAIL_RE.test(emailVal)) {
    profileFieldErrors.value.email = 'Please enter a valid email address.'
    return false
  }
  if (emailVal.length > MAX_EMAIL_LENGTH) {
    profileFieldErrors.value.email = `Email must be at most ${MAX_EMAIL_LENGTH} characters.`
    return false
  }
  return true
}

function validatePasswordStrength(p) {
  if (p.length < MIN_PASSWORD_LENGTH) return `Password must be at least ${MIN_PASSWORD_LENGTH} characters.`
  if (!/[a-zA-Z]/.test(p)) return 'Password must include at least one letter.'
  if (!/\d/.test(p)) return 'Password must include at least one number.'
  return ''
}

function validateChangePassword() {
  passwordFieldErrors.value = { current_password: '', password: '', password_confirmation: '' }
  if (!passwordForm.current_password.trim()) {
    passwordFieldErrors.value.current_password = 'Please enter your current password.'
    return false
  }
  const p = passwordForm.password
  const c = passwordForm.password_confirmation
  if (!p) {
    passwordFieldErrors.value.password = 'Please enter a new password.'
    return false
  }
  const pwdMsg = validatePasswordStrength(p)
  if (pwdMsg) {
    passwordFieldErrors.value.password = pwdMsg
    return false
  }
  if (p !== c) {
    passwordFieldErrors.value.password_confirmation = 'Passwords do not match.'
    return false
  }
  return true
}

watch(
  user,
  (u) => {
    if (u) {
      profileForm.name = u.name ?? ''
      profileForm.email = u.email ?? ''
    }
  },
  { immediate: true }
)

async function submitProfile() {
  profileMessage.value = ''
  profileError.value = false
  profileFieldErrors.value = { name: '', email: '' }
  if (!validateProfile()) return
  profileLoading.value = true
  try {
    const data = await authService.updateProfile({
      name: profileForm.name.trim(),
      email: profileForm.email.trim(),
    })
    if (data.user) appStore.setUserFromApi(data.user)
    toastStore.success(data.message ?? 'Profile updated.')
  } catch (err) {
    profileError.value = true
    const errors = getValidationErrors(err)
    if (Object.keys(errors).length > 0) {
      profileFieldErrors.value = { ...profileFieldErrors.value, ...errors }
    }
    const { message } = normalizeApiError(err)
    profileMessage.value = message ?? 'Failed to update profile.'
  } finally {
    profileLoading.value = false
  }
}

async function submitPassword() {
  passwordMessage.value = ''
  passwordError.value = false
  passwordFieldErrors.value = { current_password: '', password: '', password_confirmation: '' }
  if (!validateChangePassword()) return
  passwordLoading.value = true
  try {
    const data = await authService.changePassword({
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    toastStore.success(data.message ?? 'Password updated.')
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (err) {
    passwordError.value = true
    const errors = getValidationErrors(err)
    if (Object.keys(errors).length > 0) {
      passwordFieldErrors.value = { ...passwordFieldErrors.value, ...errors }
    }
    const { message } = normalizeApiError(err)
    passwordMessage.value = message ?? 'Failed to update password.'
  } finally {
    passwordLoading.value = false
  }
}
</script>
