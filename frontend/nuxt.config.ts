import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  // This is an authenticated internal POS tool, so client-side rendering keeps
  // token handling and hydration simple with no meaningful downside.
  ssr: false,

  devtools: { enabled: true },

  modules: ['@pinia/nuxt'],

  css: ['~/assets/css/main.css'],

  // Shared primitives resolve without a folder prefix (`<AppModal>`), while
  // feature folders keep theirs (`<PosCart>`, `<KitchenTicket>`).
  components: [
    { path: '~/components/common', pathPrefix: false },
    '~/components',
  ],

  vite: {
    plugins: [tailwindcss()],
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
    },
  },

  app: {
    head: {
      title: 'Betedesta',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'description', content: 'Betedesta bar and restaurant' },
      ],
    },
  },
})
