export interface TelegramThemeParams {
  bg_color?: string
  text_color?: string
  hint_color?: string
  link_color?: string
  button_color?: string
  button_text_color?: string
  secondary_bg_color?: string
  header_bg_color?: string
  accent_text_color?: string
  section_bg_color?: string
  destructive_text_color?: string
}

export interface TelegramSafeArea {
  top: number
  bottom: number
  left: number
  right: number
}

export interface TelegramWebAppLike {
  initData: string
  initDataUnsafe?: { user?: { id: number, username?: string, first_name?: string, language_code?: string } }
  themeParams: TelegramThemeParams
  colorScheme?: 'light' | 'dark'
  platform?: string
  ready: () => void
  expand: () => void
  close?: () => void
  BackButton: {
    show: () => void
    hide: () => void
    onClick: (cb: () => void) => void
    offClick: (cb: () => void) => void
    isVisible: boolean
  }
  MainButton: {
    text: string
    color: string
    textColor: string
    isVisible: boolean
    isActive: boolean
    isProgressVisible: boolean
    setText: (text: string) => void
    onClick: (cb: () => void) => void
    offClick: (cb: () => void) => void
    show: () => void
    hide: () => void
    enable: () => void
    disable: () => void
    showProgress: (leaveActive?: boolean) => void
    hideProgress: () => void
  }
  HapticFeedback?: {
    impactOccurred: (style: 'light' | 'medium' | 'heavy' | 'rigid' | 'soft') => void
    notificationOccurred: (type: 'error' | 'success' | 'warning') => void
  }
  setHeaderColor?: (color: string) => void
  setBackgroundColor?: (color: string) => void
  contentSafeAreaInset?: TelegramSafeArea
  safeAreaInset?: TelegramSafeArea
  onEvent?: (event: string, cb: () => void) => void
  offEvent?: (event: string, cb: () => void) => void
  disableVerticalSwipes?: () => void
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebAppLike }
  }
}

const isTelegramState = ref(false)
const themeParams = ref<TelegramThemeParams>({})
const safeAreaTop = ref(0)
const safeAreaBottom = ref(0)
/** True while Telegram MainButton is shown — shell FAB should hide. */
const mainButtonVisible = ref(false)

function isDarkColor(hex?: string): boolean {
  if (!hex || !hex.startsWith('#') || hex.length < 7) return false
  const r = Number.parseInt(hex.slice(1, 3), 16)
  const g = Number.parseInt(hex.slice(3, 5), 16)
  const b = Number.parseInt(hex.slice(5, 7), 16)
  return (r * 299 + g * 587 + b * 114) / 1000 < 140
}

function applyTheme(params: TelegramThemeParams, colorScheme?: 'light' | 'dark') {
  if (!import.meta.client) return
  const root = document.documentElement
  const bg = params.bg_color
  const dark = colorScheme === 'dark' || isDarkColor(bg)

  const map: Record<string, string | undefined> = {
    '--tg-theme-bg-color': params.bg_color,
    '--tg-theme-text-color': params.text_color,
    '--tg-theme-hint-color': params.hint_color,
    '--tg-theme-link-color': params.link_color,
    '--tg-theme-button-color': params.button_color,
    '--tg-theme-button-text-color': params.button_text_color,
    '--tg-theme-secondary-bg-color': params.secondary_bg_color,
    '--tg-theme-header-bg-color': params.header_bg_color,
    '--tg-theme-accent-text-color': params.accent_text_color,
    '--tg-theme-section-bg-color': params.section_bg_color,
    '--tg-theme-destructive-text-color': params.destructive_text_color,
    '--tg-surface': params.section_bg_color || params.secondary_bg_color || (dark ? '#1c2733' : '#ffffff'),
    '--tg-surface-2': params.secondary_bg_color || (dark ? '#242f3d' : '#f1f5f9'),
    '--tg-border': dark ? 'rgba(255,255,255,0.12)' : 'rgba(15,23,42,0.12)',
    '--tg-muted': params.hint_color || (dark ? '#9aa7b5' : '#64748b'),
    '--tg-field-bg': dark ? 'rgba(255,255,255,0.06)' : '#ffffff',
    '--tg-field-text': params.text_color || (dark ? '#f8fafc' : '#0f172a'),
    '--tg-placeholder': dark ? 'rgba(255,255,255,0.4)' : '#94a3b8',
  }
  for (const [key, value] of Object.entries(map)) {
    if (value) root.style.setProperty(key, value)
  }
  root.classList.add('tg-miniapp')
  root.classList.toggle('tg-dark', dark)
  root.classList.toggle('tg-light', !dark)
  root.style.colorScheme = dark ? 'dark' : 'light'
}

function applySafeArea(tg: TelegramWebAppLike) {
  if (!import.meta.client) return
  const safe = tg.safeAreaInset ?? { top: 0, bottom: 0, left: 0, right: 0 }
  const content = tg.contentSafeAreaInset ?? { top: 0, bottom: 0, left: 0, right: 0 }

  let top = (safe.top || 0) + (content.top || 0)
  let bottom = (safe.bottom || 0) + (content.bottom || 0)

  // Older Telegram clients often report 0 — keep content below the Close / ⋮ bar.
  const mobile = ['ios', 'android', 'android_x'].includes((tg.platform || '').toLowerCase())
  if (top < 8 && mobile) top = 56
  if (top < 8) top = 12

  safeAreaTop.value = top
  safeAreaBottom.value = bottom

  const root = document.documentElement
  root.style.setProperty('--tg-safe-top', `${top}px`)
  root.style.setProperty('--tg-safe-bottom', `${bottom}px`)
  root.style.setProperty('--tg-safe-left', `${(safe.left || 0) + (content.left || 0)}px`)
  root.style.setProperty('--tg-safe-right', `${(safe.right || 0) + (content.right || 0)}px`)
}

export function useTelegramWebApp() {
  const webApp = computed(() => (import.meta.client ? window.Telegram?.WebApp ?? null : null))

  function syncTheme() {
    const tg = window.Telegram?.WebApp
    if (!tg) return
    themeParams.value = tg.themeParams ?? {}
    applyTheme(tg.themeParams ?? {}, tg.colorScheme)
    if (tg.themeParams.bg_color) tg.setBackgroundColor?.(tg.themeParams.bg_color)
    const header = tg.themeParams.header_bg_color || tg.themeParams.bg_color
    if (header) tg.setHeaderColor?.(header)
    applySafeArea(tg)
  }

  function boot() {
    if (!import.meta.client) return false
    const tg = window.Telegram?.WebApp
    if (!tg?.initData) {
      isTelegramState.value = false
      return false
    }
    isTelegramState.value = true
    syncTheme()
    tg.ready()
    tg.expand()
    tg.disableVerticalSwipes?.()

    const refresh = () => syncTheme()
    tg.onEvent?.('themeChanged', refresh)
    tg.onEvent?.('safeAreaChanged', refresh)
    tg.onEvent?.('contentSafeAreaChanged', refresh)
    // Late insets after expand/fullscreen settle
    window.setTimeout(refresh, 50)
    window.setTimeout(refresh, 250)
    return true
  }

  function haptic(kind: 'light' | 'success' | 'error' = 'light') {
    const h = webApp.value?.HapticFeedback
    if (!h) return
    if (kind === 'success') h.notificationOccurred('success')
    else if (kind === 'error') h.notificationOccurred('error')
    else h.impactOccurred('light')
  }

  let mainButtonHandler: (() => void) | null = null

  function setMainButton(opts: {
    text: string
    visible?: boolean
    enabled?: boolean
    onClick?: () => void
  } | null) {
    const btn = webApp.value?.MainButton
    if (!btn) {
      mainButtonVisible.value = false
      return
    }
    if (mainButtonHandler) {
      btn.offClick(mainButtonHandler)
      mainButtonHandler = null
    }
    if (!opts) {
      btn.hide()
      mainButtonVisible.value = false
      return
    }
    btn.setText(opts.text)
    if (opts.enabled === false) btn.disable()
    else btn.enable()
    if (opts.onClick) {
      mainButtonHandler = opts.onClick
      btn.onClick(mainButtonHandler)
    }
    if (opts.visible === false) {
      btn.hide()
      mainButtonVisible.value = false
    }
    else {
      btn.show()
      mainButtonVisible.value = true
    }
  }

  function syncBackButton(show: boolean, onBack: () => void) {
    const back = webApp.value?.BackButton
    if (!back) return
    back.offClick(onBack)
    if (show) {
      back.onClick(onBack)
      back.show()
    }
    else {
      back.hide()
    }
  }

  return {
    isTelegram: isTelegramState,
    themeParams,
    safeAreaTop,
    safeAreaBottom,
    mainButtonVisible,
    webApp,
    initData: computed(() => webApp.value?.initData ?? ''),
    boot,
    haptic,
    setMainButton,
    syncBackButton,
    applyTheme,
  }
}
