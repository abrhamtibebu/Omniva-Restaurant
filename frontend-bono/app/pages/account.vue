<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()
const api = useApi()
const router = useRouter()
const { isTelegram, initData, haptic } = useTelegramWebApp()
const { roleLabel } = useLocaleFormat()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const linking = ref(false)
const signingOut = ref(false)
const showPassword = ref(false)
const { busy: saving, run: runSave } = useBusyAction()

const initials = computed(() => {
  const name = auth.user?.name?.trim() || '?'
  const parts = name.split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return `${parts[0]![0]}${parts[1]![0]}`.toUpperCase()
  return name.slice(0, 2).toUpperCase()
})

async function save() {
  await runSave(async () => {
    try {
      await api.patch('/me/password', form)
      toast.success(t('account.passwordUpdated'))
      form.current_password = form.password = form.password_confirmation = ''
      showPassword.value = false
    }
    catch (e: any) {
      toast.error(e.message)
    }
  })
}

async function linkTelegram() {
  if (!initData.value) {
    toast.error(t('telegram.openMiniAppToLink'))
    return
  }
  linking.value = true
  try {
    await auth.linkTelegram(initData.value)
    haptic('success')
    toast.success(t('telegram.linked'))
  }
  catch (e: any) {
    haptic('error')
    toast.error(e.message)
  }
  finally {
    linking.value = false
  }
}

async function unlinkTelegram() {
  linking.value = true
  try {
    await auth.unlinkTelegram()
    toast.success(t('telegram.unlinked'))
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    linking.value = false
  }
}

async function signOut() {
  if (signingOut.value) return
  signingOut.value = true
  try {
    await auth.logout()
    haptic('success')
    toast.success(t('common.signedOut'))
    await router.replace('/login')
  }
  catch (e: any) {
    haptic('error')
    toast.error(e?.message || t('common.couldNotSignOut'))
  }
  finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <PageHeader :title="t('account.title')" :description="auth.user?.email || auth.user?.phone || undefined" />

    <template v-if="isTelegram">
      <div class="tg-soft-card flex items-center gap-3">
        <div class="tg-meta-avatar !h-14 !w-14 !text-base">{{ initials }}</div>
        <div class="min-w-0">
          <p class="truncate text-lg font-bold">{{ auth.user?.name }}</p>
          <p class="truncate text-sm opacity-70">{{ roleLabel(auth.role) }} · {{ auth.branch?.name }}</p>
          <p class="truncate text-xs opacity-50">{{ auth.user?.email || auth.user?.phone }}</p>
        </div>
      </div>

      <div class="tg-soft-card !py-1">
        <p class="tg-section-label px-0 pt-2">{{ t('account.telegramHeading') }}</p>
        <div class="tg-meta-row">
          <div class="tg-meta-body">
            <p class="tg-meta-title">
              {{ auth.user?.telegram_linked
                ? t('telegram.linkedAs', { handle: auth.user.telegram_username || auth.user.telegram_id })
                : t('telegram.optionalAutoSignIn') }}
            </p>
          </div>
          <AppButton
            v-if="!auth.user?.telegram_linked"
            size="sm"
            :loading="linking"
            @click="linkTelegram"
          >
            {{ t('telegram.linkThisAccount') }}
          </AppButton>
          <AppButton
            v-else
            size="sm"
            variant="secondary"
            :loading="linking"
            @click="unlinkTelegram"
          >
            {{ t('telegram.unlink') }}
          </AppButton>
        </div>
      </div>

      <div class="tg-soft-card !py-1">
        <button type="button" class="tg-meta-row w-full text-left" @click="showPassword = !showPassword">
          <div class="tg-meta-body">
            <p class="tg-meta-title">{{ t('account.passwordHeading') }}</p>
            <p class="tg-meta-sub">{{ t('account.updatePassword') }}</p>
          </div>
          <span class="tg-meta-trail">{{ showPassword ? '−' : '+' }}</span>
        </button>
        <form v-if="showPassword" class="space-y-3 border-t border-[color:var(--tg-border)] py-3" @submit.prevent="save">
          <FormField :label="t('account.currentPassword')"><input v-model="form.current_password" class="input" type="password" required></FormField>
          <FormField :label="t('account.newPassword')"><input v-model="form.password" class="input" type="password" required></FormField>
          <FormField :label="t('account.confirmPassword')"><input v-model="form.password_confirmation" class="input" type="password" required></FormField>
          <AppButton type="submit" :loading="saving">{{ t('account.updatePassword') }}</AppButton>
        </form>
      </div>

      <div class="tg-soft-card !py-1">
        <div class="tg-meta-row">
          <div class="tg-meta-body">
            <p class="tg-meta-title">{{ t('language.label') }}</p>
          </div>
          <LanguageSelector compact />
        </div>
        <button type="button" class="tg-meta-row w-full text-left text-[color:var(--tg-theme-destructive-text-color,#ef4444)]" :disabled="signingOut" @click="signOut">
          <div class="tg-meta-body">
            <p class="tg-meta-title !text-inherit">{{ signingOut ? t('common.signingOut') : t('common.signOut') }}</p>
          </div>
        </button>
      </div>
    </template>

    <template v-else>
      <div class="card max-w-md space-y-3 p-4 sm:p-6">
        <h2 class="font-semibold">{{ t('account.telegramHeading') }}</h2>
        <p v-if="auth.user?.telegram_linked" class="text-sm text-slate-600">
          {{ t('telegram.linkedAs', { handle: auth.user.telegram_username || auth.user.telegram_id }) }}
        </p>
        <p v-else class="text-sm text-slate-600">
          {{ t('telegram.optionalAutoSignIn') }}
        </p>
        <AppButton
          v-if="!auth.user?.telegram_linked && isTelegram"
          :loading="linking"
          @click="linkTelegram"
        >
          {{ linking ? t('telegram.linking') : t('telegram.linkThisAccount') }}
        </AppButton>
        <NuxtLink
          v-else-if="!auth.user?.telegram_linked && !isTelegram"
          to="/telegram-link"
          class="btn-secondary inline-flex"
        >
          {{ t('telegram.linkLater') }}
        </NuxtLink>
        <AppButton
          v-if="auth.user?.telegram_linked"
          variant="secondary"
          :loading="linking"
          @click="unlinkTelegram"
        >
          {{ t('telegram.unlink') }}
        </AppButton>
      </div>

      <form class="card max-w-md space-y-3 p-4 sm:p-6" @submit.prevent="save">
        <h2 class="font-semibold">{{ t('account.passwordHeading') }}</h2>
        <FormField :label="t('account.currentPassword')"><input v-model="form.current_password" class="input" type="password" required></FormField>
        <FormField :label="t('account.newPassword')"><input v-model="form.password" class="input" type="password" required></FormField>
        <FormField :label="t('account.confirmPassword')"><input v-model="form.password_confirmation" class="input" type="password" required></FormField>
        <AppButton type="submit" :loading="saving">{{ t('account.updatePassword') }}</AppButton>
      </form>

      <AppButton variant="secondary" class="max-w-md w-full" :loading="signingOut" @click="signOut">
        {{ signingOut ? t('common.signingOut') : t('common.signOut') }}
      </AppButton>
    </template>
  </div>
</template>
