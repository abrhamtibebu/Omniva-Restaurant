/**
 * Boots Telegram WebApp chrome before auth so layout + X-Client can detect Mini App mode.
 * Script is loaded from nuxt.config head — only wait briefly for it to appear.
 */
export default defineNuxtPlugin({
  name: 'telegram-webapp',
  enforce: 'pre',
  async setup() {
    if (!window.Telegram?.WebApp) {
      await new Promise<void>((resolve) => {
        const started = Date.now()
        const tick = () => {
          if (window.Telegram?.WebApp || Date.now() - started > 280) {
            resolve()
            return
          }
          requestAnimationFrame(tick)
        }
        tick()
      })
    }

    useTelegramWebApp().boot()
  },
})
