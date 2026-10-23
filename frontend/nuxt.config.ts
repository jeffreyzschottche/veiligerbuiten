export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-01-09',

  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  runtimeConfig: {
    public: {
      // Local web development uses Nitro's same-origin proxy. For a static
      // mobile build, provide an absolute HTTPS URL at build time through
      // NUXT_PUBLIC_API_BASE_URL (for example https://api.veiligbuiten.nl/api/v1).
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || '/api/v1',
    },
  },

  nitro: {
    devProxy: {
      '/api': { target: 'http://127.0.0.1:8000/api', changeOrigin: true },
    },
  },

  devtools: { enabled: true },
});
