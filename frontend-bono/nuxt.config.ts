import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  // Authenticated Bono control SPA — CSR keeps token handling simple.
  ssr: false,

  devtools: { enabled: true },

  modules: ['@pinia/nuxt', '@nuxtjs/i18n'],

  css: ['~/assets/css/main.css'],

  components: [
    { path: '~/components/common', pathPrefix: false },
    '~/components',
  ],

  i18n: {
    locales: [
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
      { code: 'am', language: 'am-ET', name: 'አማርኛ', file: 'am.json' },
    ],
    defaultLocale: 'en',
    strategy: 'no_prefix',
    langDir: 'locales',
    detectBrowserLanguage: false,
    vueI18n: 'i18n.config.ts',
  },

  vite: {
    plugins: [tailwindcss()],
  },

  runtimeConfig: {
    public: {
      // Production default — override locally with NUXT_PUBLIC_API_BASE in .env
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'https://api.esmusstrading.com/api/v1',
    },
  },

  app: {
    head: {
      title: 'Bono Control',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover' },
        { name: 'description', content: 'Bono control for bar and restaurant operations' },
      ],
      script: [
        { src: 'https://telegram.org/js/telegram-web-app.js', defer: true, tagPriority: 1 },
      ],
      link: [
        { rel: 'preconnect', href: 'https://telegram.org' },
        { rel: 'dns-prefetch', href: 'https://telegram.org' },
      ],
    },
  },

  devServer: {
    port: 3001,
  },
})
