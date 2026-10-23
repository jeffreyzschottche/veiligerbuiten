export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-01-09',

  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  // Componenten in submappen (brand/, site/) zonder padprefix gebruiken: <Logo />, <PhoneFrame />.
  components: [{ path: '~/components', pathPrefix: false }],

  css: [
    '@fontsource-variable/geist',
    '@fontsource-variable/geist-mono',
    '~/assets/css/brand.css',
  ],

  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'theme-color', content: '#0b0d12' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      // Local web development uses Nitro's same-origin proxy. For a static
      // mobile build, provide an absolute HTTPS URL at build time through
      // NUXT_PUBLIC_API_BASE_URL (for example https://api.veiligbuiten.nl/api/v1).
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || '/api/v1',
      // Winkellinks volgen later; tot die tijd scrollen de knoppen naar #download.
      appStoreUrl: process.env.NUXT_PUBLIC_APP_STORE_URL || '',
      playStoreUrl: process.env.NUXT_PUBLIC_PLAY_STORE_URL || '',
      mcpUrl: process.env.NUXT_PUBLIC_MCP_URL || 'https://api.veiligbuiten.nl/mcp',
    },
  },

  nitro: {
    devProxy: {
      '/api': { target: 'http://127.0.0.1:8000/api', changeOrigin: true },
    },
  },

  devtools: { enabled: true },
});
