<script setup lang="ts">
/**
 * Optional: link the current Telegram identity to a staff account.
 * Sign-in itself happens on /login — this screen is only for linking.
 */
definePageMeta({ public: true, layout: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()
const route = useRoute()
const { isTelegram, initData, haptic } = useTelegramWebApp()

const form = reactive({ login: '', password: '' })
const busy = ref(false)

async function linkAndEnter() {
  if (!initData.value) {
    toast.error(t('telegram.openFromMiniApp'))
    return
  }
  busy.value = true
  try {
    if (!auth.isAuthenticated) {
      await auth.login({ login: form.login.trim(), password: form.password, device_name: 'telegram-link' })
    }
    await auth.linkTelegram(initData.value)
    haptic('success')
    toast.success(t('telegram.linked'))
    await navigateTo((route.query.redirect as string) || auth.homeRoute)
  }
  catch (error: any) {
    haptic('error')
    toast.error(error.message || t('telegram.couldNotLink'))
  }
  finally {
    busy.value = false
  }
}

async function skipLink() {
  if (auth.isAuthenticated) {
    await navigateTo((route.query.redirect as string) || auth.homeRoute)
    return
  }
  await navigateTo({ path: '/login', query: route.query })
}

onMounted(async () => {
  // If already signed in and already linked, just continue.
  if (auth.isAuthenticated && auth.user?.telegram_linked) {
    await navigateTo((route.query.redirect as string) || auth.homeRoute)
  }
})
</script>

<template>
  <div class="mx-auto max-w-md space-y-4 p-4">
    <div class="flex justify-end">
      <LanguageSelector />
    </div>

    <PageHeader
      :title="t('telegram.linkTitle')"
      :description="t('telegram.linkDescription')"
    />
    <p v-if="!isTelegram" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
      {{ t('telegram.meantForTelegram') }}
    </p>

    <form v-if="!auth.isAuthenticated" class="card space-y-3 p-4" @submit.prevent="linkAndEnter">
      <FormField :label="t('auth.emailOrPhone')">
        <input
          v-model="form.login"
          class="input"
          type="text"
          inputmode="email"
          autocomplete="username"
          :placeholder="t('auth.loginPlaceholder')"
          required
        >
      </FormField>
      <FormField :label="t('common.password')">
        <input v-model="form.password" class="input" type="password" autocomplete="current-password" required>
      </FormField>
      <AppButton type="submit" class="w-full min-h-11" :loading="busy">
        {{ busy ? t('telegram.linking') : t('telegram.signInAndLink') }}
      </AppButton>
    </form>

    <div v-else class="card space-y-3 p-4">
      <p class="text-sm text-slate-600">
        {{ t('telegram.signedInAs', { name: auth.user?.name }) }}
      </p>
      <AppButton class="w-full min-h-11" :loading="busy" :disabled="!initData" @click="linkAndEnter">
        {{ busy ? t('telegram.linking') : t('telegram.linkThisAccount') }}
      </AppButton>
    </div>

    <AppButton variant="secondary" class="w-full min-h-11" :disabled="busy" @click="skipLink">
      {{ auth.isAuthenticated ? t('telegram.notNow') : t('telegram.signInWithoutLinking') }}
    </AppButton>
  </div>
</template>
