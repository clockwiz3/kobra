import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/main.ts'],
      refresh: true,
    }),
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'prompt', // контролируемое обновление (см. skill pwa)
      injectRegister: 'auto',
      manifest: {
        name: 'Cuploid',
        short_name: 'Cuploid',
        description: 'Приватный таск-трекер для пар',
        lang: 'ru',
        theme_color: '#0F0F17',
        background_color: '#0F0F17',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
          { src: '/maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,woff2}'],
        cleanupOutdatedCaches: true,
        navigateFallback: null, // SPA-маршруты отдаёт Laravel, не статический index.html
        runtimeCaching: [
          // ⚠️ приватные couple-данные НИКОГДА не кэшировать в SW (изоляция шарда)
          { urlPattern: ({ url }) => url.pathname.startsWith('/api/couple/'), handler: 'NetworkOnly' },
        ],
      },
      devOptions: { enabled: false },
    }),
  ],
})
