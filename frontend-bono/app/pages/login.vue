<script setup lang="ts">
import { ApiError } from '~/types/api'

definePageMeta({
  layout: 'auth',
  public: true,
})

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const toast = useToast()
const { isTelegram, initData, haptic } = useTelegramWebApp()

const form = reactive({ login: '', password: '' })
const linkTelegramOptIn = ref(false)
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const submitting = ref(false)
const showLang = ref(false)

async function submit() {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const user = await auth.login({ login: form.login.trim(), password: form.password })

    if (isTelegram.value && initData.value && linkTelegramOptIn.value && !user.telegram_linked) {
      try {
        await auth.linkTelegram(initData.value)
        haptic('success')
        toast.success(t('telegram.signedInAndLinked'))
      }
      catch (linkError: any) {
        toast.success(t('auth.welcomeBack', { name: user.name.split(' ')[0] }))
        toast.error(linkError?.message || t('telegram.signedInButLinkFailed'))
      }
    }
    else {
      haptic('success')
      toast.success(t('auth.welcomeBack', { name: user.name.split(' ')[0] }))
    }

    const redirect = route.query.redirect
    await navigateTo(typeof redirect === 'string' && redirect !== '/login' ? redirect : auth.homeRoute)
  }
  catch (error) {
    haptic('error')
    if (error instanceof ApiError) {
      errors.value = error.errors
      if (!error.isValidation) generalError.value = error.message
    }
    else {
      generalError.value = t('common.unexpectedError')
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-md" :class="{ 'tg-login': isTelegram }">
    <div class="mb-3 flex justify-end">
      <button
        v-if="isTelegram"
        type="button"
        class="text-xs font-semibold opacity-60"
        @click="showLang = !showLang"
      >
        {{ t('language.label') }}
      </button>
      <LanguageSelector v-else />
    </div>
    <div v-if="isTelegram && showLang" class="mb-4 flex justify-end">
      <LanguageSelector compact />
    </div>

    <div class="mb-6 text-center">
      <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-[color:var(--tg-theme-button-color,#2481cc)] text-lg font-bold text-[color:var(--tg-theme-button-text-color,#fff)] shadow-lg">
        ቤ
      </div>
      <h1 class="mt-4 text-2xl font-bold tracking-tight text-[color:var(--tg-theme-text-color,#0f172a)]">{{ t('common.appName') }}</h1>
      <p class="mt-1 text-sm text-[color:var(--tg-muted,#94a3b8)]">{{ t('auth.tagline') }}</p>
    </div>

    <div :class="isTelegram ? 'tg-soft-card space-y-5' : 'card p-6 sm:p-8'">
      <form class="space-y-5" novalidate @submit.prevent="submit">
        <div
          v-if="generalError"
          class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 ring-inset"
          role="alert"
        >
          {{ generalError }}
        </div>

        <FormField :label="t('auth.emailOrPhone')" for="login" required :error="errors.login?.[0] || errors.email?.[0]">
          <input
            id="login"
            v-model="form.login"
            type="text"
            class="input"
            autocomplete="username"
            autofocus
            required
            :placeholder="t('auth.loginPlaceholder')"
          >
        </FormField>

        <FormField :label="t('common.password')" for="password" required :error="errors.password?.[0]">
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="input"
            autocomplete="current-password"
            required
            :placeholder="t('auth.passwordPlaceholder')"
          >
        </FormField>

        <label
          v-if="isTelegram && initData"
          class="flex items-start gap-2 text-sm opacity-70"
        >
          <input v-model="linkTelegramOptIn" type="checkbox" class="mt-1">
          <span>{{ t('telegram.alsoLinkOptIn') }}</span>
        </label>

        <AppButton type="submit" size="touch" block :loading="submitting">
          {{ submitting ? t('auth.signingIn') : t('auth.signIn') }}
        </AppButton>
      </form>
    </div>

    <p v-if="!isTelegram" class="mt-6 text-center text-sm text-slate-400">
      {{ t('auth.newRestaurant') }}
      <NuxtLink to="/signup" class="text-white underline">{{ t('auth.startTrial') }}</NuxtLink>
    </p>
  </div>
</template>
