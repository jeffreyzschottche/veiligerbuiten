const mobileBuild = process.env.NUXT_MOBILE_BUILD === 'true';

export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-01-09',

  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  // Componenten in submappen (brand/, site/) zonder padprefix gebruiken: <Logo />, <PhoneFrame />.
  components: [{ path: '~/components', pathPrefix: false }],

  css: [
    '@fontsource-variable/geist',
    '@fontsource-variable/geist-mono',
    'maplibre-gl/dist/maplibre-gl.css',
    '~/assets/css/brand.css',
    '~/assets/css/app.css',
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
    // Private: these values never enter the browser runtime config.
    apiOrigin: process.env.NUXT_API_ORIGIN || process.env.NUXT_DEV_API_TARGET || 'http://127.0.0.1:8000',
    siteStatsToken: '',
    public: {
      // Local web development uses Nitro's same-origin proxy. For a static
      // mobile build, provide an absolute HTTPS URL at build time through
      // NUXT_PUBLIC_API_BASE_URL (for example https://api.veiligerbuiten.nl/api/v1).
      apiBase: mobileBuild ? (process.env.NUXT_PUBLIC_API_BASE_URL || '/api/v1') : '/api/v1',
      // Winkellinks volgen later; tot die tijd scrollen de knoppen naar #download.
      appStoreUrl: process.env.NUXT_PUBLIC_APP_STORE_URL || '',
      playStoreUrl: process.env.NUXT_PUBLIC_PLAY_STORE_URL || '',
      // AdMob banner-units (ca-app-pub-…/…). Leeg = geen advertenties; 'true' bij testing = Google-testadvertenties.
      admobBannerIos: process.env.NUXT_PUBLIC_ADMOB_BANNER_IOS || '',
      admobBannerAndroid: process.env.NUXT_PUBLIC_ADMOB_BANNER_ANDROID || '',
      admobTesting: process.env.NUXT_PUBLIC_ADMOB_TESTING === 'true',
      appVersion: process.env.npm_package_version || '1.0.0',
    },
  },

  devtools: { enabled: process.env.NODE_ENV !== 'production' },
});
