<script setup lang="ts">
import {
  telegramTabsForRole,
  TG_MORE_SECTIONS,
  TG_MORE_ITEMS,
  TG_ICONS,
  isTelegramRootTab,
} from '~/utils/telegramNav'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const { syncBackButton, haptic } = useTelegramWebApp()
const moreOpen = ref(false)
const signingOut = ref(false)

const tabs = computed(() =>
  telegramTabsForRole(auth.role).filter((tab) => tab.permissions.length === 0 || auth.canAny(tab.permissions)),
)

const moreSections = computed(() => {
  const tabPaths = new Set(tabs.value.map((tab) => tab.to))
  return TG_MORE_SECTIONS
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (tabPaths.has(item.to)) return false
        return item.permissions.length === 0 || auth.canAny(item.permissions)
      }),
    }))
    .filter((section) => section.items.length > 0)
})

const pageTitle = computed(() => {
  const fromMore = TG_MORE_ITEMS.find((i) => route.path === i.to || route.path.startsWith(`${i.to}/`))
  const fromTab = tabs.value.find((i) => route.path === i.to || route.path.startsWith(`${i.to}/`))
  const key = fromTab?.labelKey || fromMore?.labelKey
  return key ? t(key) : t('common.bono')
})

const initials = computed(() => {
  const name = auth.user?.name?.trim() || '?'
  const parts = name.split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return `${parts[0]![0]}${parts[1]![0]}`.toUpperCase()
  return name.slice(0, 2).toUpperCase()
})

function onBack() {
  if (window.history.length > 1) router.back()
  else navigateTo(auth.homeRoute)
}

async function signOut() {
  if (signingOut.value) return
  signingOut.value = true
  moreOpen.value = false
  try {
    await auth.logout()
    haptic('success')
    toast.success(t('common.signedOut'))
    await navigateTo('/login')
  }
  catch {
    haptic('error')
    toast.error(t('common.couldNotSignOut'))
  }
  finally {
    signingOut.value = false
  }
}

function isActive(to: string) {
  return route.path === to || route.path.startsWith(`${to}/`)
}

watch(
  () => route.fullPath,
  () => {
    moreOpen.value = false
    const showBack = !isTelegramRootTab(route.path, tabs.value)
      && route.path !== '/login'
      && route.path !== '/telegram-link'
      && route.path !== '/signup'
    syncBackButton(showBack, onBack)
  },
  { immediate: true },
)

onBeforeUnmount(() => syncBackButton(false, onBack))
</script>

<template>
  <div class="tg-shell">
    <div class="tg-safe-spacer" aria-hidden="true" />

    <header v-if="auth.isAuthenticated" class="tg-header">
      <div class="min-w-0 flex-1">
        <p class="tg-header-title truncate">{{ pageTitle }}</p>
      </div>
      <NuxtLink
        to="/account"
        class="tg-avatar-btn"
        :aria-label="t('nav.account')"
        @click="haptic('light')"
      >
        {{ initials }}
      </NuxtLink>
    </header>

    <main class="tg-main" :class="{ 'tg-main-guest': !auth.isAuthenticated }">
      <slot />
    </main>

    <nav v-if="auth.isAuthenticated" class="tg-float-nav" :aria-label="t('common.primaryNav')">
      <NuxtLink
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="tg-tab"
        :class="{ 'tg-tab-active': isActive(tab.to) }"
        @click="haptic('light')"
      >
        <svg class="tg-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
        </svg>
        <span class="tg-tab-label">{{ t(tab.labelKey) }}</span>
      </NuxtLink>
      <button
        type="button"
        class="tg-tab"
        :class="{ 'tg-tab-active': moreOpen }"
        :aria-label="t('nav.more')"
        @click="moreOpen = !moreOpen; haptic('light')"
      >
        <svg class="tg-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" :d="TG_ICONS.more" />
        </svg>
        <span class="tg-tab-label">{{ t('nav.more') }}</span>
      </button>
    </nav>

    <Teleport to="body">
      <div v-if="moreOpen" class="tg-more-backdrop" @click="moreOpen = false" />
      <div v-if="moreOpen" class="tg-more-sheet">
        <div class="tg-more-handle" aria-hidden="true" />

        <div
          v-for="section in moreSections"
          :key="section.labelKey"
          class="tg-more-section"
        >
          <p class="tg-more-section-label">{{ t(section.labelKey) }}</p>
          <NuxtLink
            v-for="item in section.items"
            :key="item.to"
            :to="item.to"
            class="tg-more-link"
            @click="moreOpen = false; haptic('light')"
          >
            <svg class="tg-more-link-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            <span>{{ t(item.labelKey) }}</span>
          </NuxtLink>
        </div>

        <div class="tg-more-section">
          <p class="tg-more-section-label">{{ t('nav.moreSettings') }}</p>
          <NuxtLink
            to="/account"
            class="tg-more-link"
            @click="moreOpen = false; haptic('light')"
          >
            <svg class="tg-more-link-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" :d="TG_ICONS.account" />
            </svg>
            <span>{{ t('nav.account') }}</span>
          </NuxtLink>
          <div class="tg-more-link">
            <LanguageSelector />
          </div>
          <AppButton
            variant="ghost"
            class="tg-more-link tg-more-danger"
            :loading="signingOut"
            @click="signOut"
          >
            {{ signingOut ? t('common.signingOut') : t('common.signOut') }}
          </AppButton>
        </div>
      </div>
    </Teleport>
  </div>
</template>
