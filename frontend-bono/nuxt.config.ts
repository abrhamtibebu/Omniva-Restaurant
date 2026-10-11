import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  // Authenticated Bono control SPA — CSR keeps token handling simple.
  ssr: false,

  devtools: { enabled: true },

  modules: ['@pinia/nuxt'],

  css: ['~/assets/css/main.css'],

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
      title: 'Bono Control',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'description', content: 'Bono control for bar and restaurant operations' },
      ],
    },
  },

  devServer: {
    port: 3001,
  },
})
