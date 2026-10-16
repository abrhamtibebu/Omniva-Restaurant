/**
 * Use the Telegram shell for app screens. Auth screens keep the auth layout
 * (with Telegram safe-area padding via .tg-auth-shell).
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isTelegram } = useTelegramWebApp()
  if (!isTelegram.value) return

  if (to.meta.layout === false) return

  const authScreen = to.path === '/login' || to.path === '/signup' || to.path === '/telegram-link'
  if (authScreen) return

  to.meta.layout = 'telegram'
})
