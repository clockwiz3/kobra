# PWA — готовые примеры (Cuploid)

## 1. vite.config.ts

```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'prompt',
      injectRegister: 'auto',
      manifest: {
        name: 'Cuploid',
        short_name: 'Cuploid',
        description: 'Приватный таск-трекер для пар',
        theme_color: '#0f0f17',
        background_color: '#0f0f17',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
          { src: 'maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,svg,woff2}'],
        navigateFallback: 'index.html',
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        skipWaiting: false,
        runtimeCaching: [
          // Шрифты/иконки — иммутабельная статика
          {
            urlPattern: ({ request }) => request.destination === 'font',
            handler: 'CacheFirst',
            options: { cacheName: 'fonts', expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 } },
          },
          // Публичный эндпоинт вайтлиста — можно NetworkFirst
          {
            urlPattern: ({ url }) => url.pathname.startsWith('/api/waitlist/'),
            handler: 'NetworkFirst',
            options: { cacheName: 'waitlist', networkTimeoutSeconds: 5 },
          },
          // ⚠️ Приватные couple-данные — НИКОГДА не кэшировать
          {
            urlPattern: ({ url }) => url.pathname.startsWith('/api/couple/'),
            handler: 'NetworkOnly',
          },
        ],
      },
      devOptions: { enabled: true },
    }),
  ],
})
```

## 2. ReloadPrompt.vue (prompt-обновление + offline-ready)

```vue
<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue'

const { offlineReady, needRefresh, updateServiceWorker } = useRegisterSW({
  onRegistered(r) {
    // Проверять обновления раз в час
    r && setInterval(() => r.update(), 60 * 60 * 1000)
  },
})

function close() {
  offlineReady.value = false
  needRefresh.value = false
}
</script>

<template>
  <div v-if="offlineReady || needRefresh" class="pwa-toast" role="alert" aria-live="polite">
    <span v-if="offlineReady">Готово к работе оффлайн</span>
    <span v-else>Доступно обновление</span>
    <button v-if="needRefresh" @click="updateServiceWorker()">Обновить</button>
    <button @click="close">Закрыть</button>
  </div>
</template>
```

> tsconfig.json: `"compilerOptions": { "types": ["vite-plugin-pwa/vue"] }`

## 3. Установка приложения (useInstallPrompt.ts)

```ts
import { ref, onMounted, onUnmounted } from 'vue'

export function useInstallPrompt() {
  const canInstall = ref(false)
  let deferred: any = null

  function onBeforeInstall(e: Event) {
    e.preventDefault()        // спрятать дефолтный баннер
    deferred = e
    canInstall.value = true
  }
  async function promptInstall() {
    if (!deferred) return
    deferred.prompt()
    await deferred.userChoice
    deferred = null
    canInstall.value = false
  }

  onMounted(() => window.addEventListener('beforeinstallprompt', onBeforeInstall))
  onUnmounted(() => window.removeEventListener('beforeinstallprompt', onBeforeInstall))
  // iOS Safari: program prompt недоступен — показывать инструкцию «Поделиться → На экран Домой».
  return { canInstall, promptInstall }
}
```

## 4. prefers-reduced-motion (частицы/анимации)

```ts
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
if (!reduced) {
  requestIdleCallback(() => initParticles())  // тяжёлые эффекты — lazy
}
document.addEventListener('visibilitychange', () => {
  document.hidden ? pauseParticles() : resumeParticles()
})
```

```css
@media (prefers-reduced-motion: reduce) {
  .particles, .heavy-anim { animation: none !important; }
}
```

## 5. Гард регистрации SW внутри Capacitor

```ts
import { Capacitor } from '@capacitor/core'

// SW не нужен в нативном webview — регистрируем только в браузере
if (!Capacitor.isNativePlatform()) {
  const { useRegisterSW } = await import('virtual:pwa-register/vue')
  useRegisterSW({ immediate: true })
}
```

## 6. Очистка кэшей на logout / смене пары

```ts
export async function purgePwaCaches() {
  if (!('caches' in window)) return
  const keys = await caches.keys()
  // Удаляем всё, кроме precache app-shell, чтобы не оставить приватные ответы
  await Promise.all(keys.filter(k => k !== 'fonts').map(k => caches.delete(k)))
}
```
