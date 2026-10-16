/**
 * Resolves the stored session before the first route guard runs.
 * In Telegram: if this chat is already linked, sign in silently.
 * Linking itself is optional — failures just leave the user on /login.
 */
export default defineNuxtPlugin({
  name: 'auth-session',
  dependsOn: ['telegram-webapp'],
  async setup() {
    const auth = useAuthStore()
    const { isTelegram, initData } = useTelegramWebApp()

    await auth.init()

    if (!auth.isAuthenticated && isTelegram.value && initData.value) {
      try {
        await auth.loginWithTelegram(initData.value)
      }
      catch {
        // Not linked / invalid — user signs in with email or phone.
      }
    }
  },
})
