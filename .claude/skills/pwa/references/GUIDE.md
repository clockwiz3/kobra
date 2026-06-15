# PWA Guide (vite-plugin-pwa) — база знаний

Синтез официальной документации vite-plugin-pwa под проект Cuploid (Vue 3 + Vite, PWA-first → Capacitor).

## Core Concepts

- **vite-plugin-pwa** генерирует три вещи: **Web App Manifest**, **Service Worker** (через Workbox) и **registration script**.
- **Service worker** — прокси между приложением, браузером и сетью; обеспечивает оффлайн и перехват запросов.
- Плагин подключается в `vite.config.ts` через `VitePWA({...})`.

## VitePWA — ключевые опции

| Опция | Значения / дефолт | Назначение |
|-------|-------------------|------------|
| `registerType` | `'prompt'` (реком. для Cuploid) / `'autoUpdate'` | Поведение обновления SW. `prompt` — уведомить и обновить по действию; `autoUpdate` — автоматически. |
| `injectRegister` | `'auto'` / `'script'` / `'inline'` / `null` | Как внедряется скрипт регистрации SW. |
| `strategies` | `'generateSW'` (дефолт) / `'injectManifest'` | Кто пишет SW: Workbox или вы. |
| `manifest` | объект | Поля web app manifest. |
| `workbox` | объект | Конфиг для `generateSW` (precache, runtimeCaching, fallback). |
| `injectManifest` | объект | Конфиг для `injectManifest` (свой SW). |
| `devOptions.enabled` | `false` | Включить PWA в dev-режиме для тестов. |

> Для версий ниже 0.12.2 для корректного `autoUpdate` нужно вручную задать `workbox.clientsClaim: true` и `workbox.skipWaiting: true`. На свежих версиях это не требуется.

## Web App Manifest — минимум

```ts
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
}
```

## Vue 3 интеграция

- Virtual-модуль: `import { useRegisterSW } from 'virtual:pwa-register/vue'`.
- `useRegisterSW(options?)` возвращает:
  - `offlineReady: Ref<boolean>` — приложение готово к оффлайн-работе;
  - `needRefresh: Ref<boolean>` — доступна новая версия;
  - `updateServiceWorker(reloadPage = true)` — применить обновление.
- Колбэк `onRegistered(registration)` — точка для периодической проверки `registration.update()`.
- Типы: `tsconfig.json` → `compilerOptions.types: ["vite-plugin-pwa/vue"]`.

## Workbox: generateSW vs injectManifest

- **generateSW** (дефолт): Workbox генерирует весь SW по конфигу `workbox`. Не нужно писать SW вручную. Подходит для старта Cuploid.
- **injectManifest**: вы пишете свой SW-файл, Vite его собирает, Workbox внедряет precache-manifest. Нужен для кастомной логики: Web Push, background sync, кастомные обработчики событий.
- Требования: Node 16+ (с 0.16.0 / Workbox 7). С 0.20.2+ сборка падает при предупреждениях `maximumFileSizeToCacheInBytes`.

### runtimeCaching — стратегии

| Стратегия | Когда |
|-----------|-------|
| `CacheFirst` | Иммутабельная статика: шрифты, иконки, версионированные изображения. |
| `StaleWhileRevalidate` | Некритичная публичная статика, где ок показать слегка устаревшее. |
| `NetworkFirst` | Публичные данные с оффлайн-фолбэком (короткий TTL). |
| `NetworkOnly` | **Все приватные аутентифицированные ответы (couple-данные).** |

`navigateFallback: 'index.html'` — SPA-фолбэк для навигационных запросов. `cleanupOutdatedCaches: true` — чистка старых precache. `globPatterns` — что попадает в precache (по умолчанию собранные ассеты).

## ⚠️ Приватность и tenancy (проектное правило)

Cuploid — модель «пара = изолированный shard». Кэш SW — общий для origin и переживает сессии, поэтому:

1. **Не матчить приватные эндпоинты в `runtimeCaching`** (или явно `NetworkOnly`). Кэшированный приватный ответ может показаться другому аккаунту на том же устройстве.
2. В precache — только app-shell и статика, без пользовательских данных.
3. На **logout / смене пары** — программно чистить кэши (`caches.keys()` → `caches.delete()`), синхронно с бампом `couples.cache_version`.
4. Никаких токенов/PII в Cache Storage и в URL кэш-ключей.

## Installability

- Событие `beforeinstallprompt` (Chromium): `preventDefault()`, сохранить event, показать свою кнопку, по клику `event.prompt()`.
- iOS Safari **не** поддерживает программную установку — показывать инструкцию «Поделиться → На экран „Домой"».
- Событие `appinstalled` — для аналитики/онбординга.

## Обновление SW (UX)

- `registerType: 'prompt'` + тост `ReloadPrompt.vue`: при `needRefresh` показать «Обновить», по клику `updateServiceWorker()`.
- Периодическая проверка: `setInterval(() => r.update(), 60*60*1000)` в `onRegistered`.
- `offlineReady` — одноразовый тост «Готово к работе оффлайн».

## Перф анимаций/частиц

- `@media (prefers-reduced-motion: reduce)` и JS-проверка `matchMedia('(prefers-reduced-motion: reduce)')` — упрощать/выключать частицы.
- Тяжёлые эффекты — lazy через `requestIdleCallback`, пауза по `document.visibilitychange`.
- Большие медиа держать вне precache (раздувает первый кэш и TTI).

## PWA → Capacitor

- Capacitor оборачивает ту же Vite-сборку (`webDir` = output Vite); фронт не переписывается.
- `npm i @capacitor/core @capacitor/cli` → `npx cap init` → `npx cap add ios` / `npx cap add android`.
- Внутри нативного webview **service worker не нужен** — отключать регистрацию SW при `Capacitor.isNativePlatform()` во избежание конфликтов кэша.
- Пуши: в браузере — Web Push (нужен `injectManifest` + VAPID); в Capacitor — нативные через `@capacitor/push-notifications` (APNs/FCM). Это разные каналы — учитывать в notifications outbox.

## Common Pitfalls

1. `autoUpdate` дёргает обновление посреди совместной сессии → использовать `prompt`.
2. Кэширование приватных ответов в SW → утечка между аккаунтами (нарушение изоляции шарда).
3. Забытый `maskable`-icon → плохая иконка на Android.
4. Тяжёлый precache (медиа/шрифты-варианты) → медленный первый запуск.
5. SW активен внутри Capacitor → конфликт кэша с нативным webview.
6. Нет типов `vite-plugin-pwa/vue` в tsconfig → ошибки импорта virtual-модуля.

## Sources

- https://vite-pwa-org.netlify.app/guide/
- https://vite-pwa-org.netlify.app/frameworks/vue.html
- https://vite-pwa-org.netlify.app/workbox/

> Примечание: примеры manifest-полей и `runtimeCaching` приведены по устоявшимся практикам Workbox/PWA; сверяйтесь с актуальной версией плагина при настройке.
