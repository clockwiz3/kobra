---
name: pwa
description: >-
  Настройка и поддержка PWA в Cuploid (Vue 3 + Vite + vite-plugin-pwa). Используй при
  настройке service worker, web app manifest, оффлайн app-shell, кэширования, установки
  приложения (installability), prompt-обновления SW, перф анимаций и подготовки к упаковке
  в Capacitor. Triggers: PWA, service worker, manifest, offline, vite-plugin-pwa, Workbox,
  installable, beforeinstallprompt, Capacitor.
metadata:
  author: aif-skill-generator (Learn Mode)
  version: "1.0"
  category: frontend
  source: vite-pwa-org.netlify.app
---

# PWA (vite-plugin-pwa) для Cuploid

Стек: **Vue 3 + TypeScript + Vite**, PWA-first с прицелом на упаковку в **Capacitor**. Этот скилл — практическое руководство по `vite-plugin-pwa` под нужды проекта.

> Полная база знаний — в [references/GUIDE.md](references/GUIDE.md), готовые сниппеты — в [references/EXAMPLES.md](references/EXAMPLES.md).

## Когда использовать

Настройка/правка service worker, manifest, оффлайн-поведения, установки приложения, UX обновления SW, перф анимаций/частиц, миграция PWA → Capacitor.

## ⚠️ Проектный инвариант приватности (couple-as-shard)

**НИКОГДА не кэшировать в service worker аутентифицированные ответы API с приватными данными пары.** Кэш SW переживает сессии и может утечь между аккаунтами/устройствами — это нарушает изоляцию шарда.

- Приватные эндпоинты (`/api/couple/*`, профиль, вишлисты, канбан) → `NetworkOnly` (или вообще не матчить в `runtimeCaching`).
- Кэшировать можно только: app-shell (собранные ассеты — делает `precache` автоматически), шрифты, иконки, статичные изображения, публичные эндпоинты вайтлиста.
- На logout/смену пары — вызывать очистку релевантных кэшей (`caches.delete`) и обновлять `cache_version`.

## Установка

```bash
npm i -D vite-plugin-pwa
```

## Конфигурация (vite.config.ts)

Для Cuploid рекомендуется **`registerType: 'prompt'`** — пользователь сам решает, когда обновиться (важно, чтобы не дёргать UI во время совместной сессии). Полный пример — в EXAMPLES.md.

```ts
import { VitePWA } from 'vite-plugin-pwa'

VitePWA({
  registerType: 'prompt',          // не autoUpdate: контролируемое обновление
  injectRegister: 'auto',
  manifest: { /* name, short_name, theme_color, icons... */ },
  workbox: {
    cleanupOutdatedCaches: true,
    clientsClaim: true,
    skipWaiting: false,            // при 'prompt' обновление по действию пользователя
    navigateFallback: 'index.html',
    runtimeCaching: [ /* см. ниже */ ],
  },
  devOptions: { enabled: true },    // тест PWA в dev
})
```

`tsconfig.json` → `compilerOptions.types: ["vite-plugin-pwa/vue"]` (типы virtual-модуля).

## Регистрация SW во Vue

Используй virtual-модуль `virtual:pwa-register/vue` и composable `useRegisterSW`. Он отдаёт `offlineReady`, `needRefresh` (оба `Ref<boolean>`) и `updateServiceWorker(reload?)`. Сделай компонент `ReloadPrompt.vue` (тост «Доступно обновление / Готово к работе оффлайн») — пример в EXAMPLES.md.

Периодическая проверка обновлений:
```ts
useRegisterSW({ onRegistered(r) { r && setInterval(() => r.update(), 60*60*1000) } })
```

## Стратегии кэширования (Workbox)

- **generateSW** (по умолчанию) — Workbox генерит SW сам, конфиг через `workbox`. Подходит для Cuploid на старте.
- **injectManifest** — свой SW-файл (нужен для push-уведомлений/background sync позже). Переключаться, когда понадобится низкоуровневый контроль.
- Стратегии `runtimeCaching`: `CacheFirst` (шрифты/иконки/статика), `StaleWhileRevalidate` (некритичная публичная статика), `NetworkFirst` (публичные данные с оффлайн-фолбэком), **`NetworkOnly` (вся приватная аутентифицированная выдача)**.

## Installability

Перехвати `beforeinstallprompt`, спрячь дефолтный баннер, покажи свою кнопку «Установить» (в духе кастомизации продукта). iOS Safari не поддерживает программный prompt — показывай инструкцию «Поделиться → На экран Домой». Пример — в EXAMPLES.md.

## Перф анимаций/частиц (важно для «цифрового ада»)

- Уважать `prefers-reduced-motion: reduce` — отключать/упрощать частицы и тяжёлые анимации.
- Lazy-инициализация тяжёлых эффектов (tsParticles/canvas) после `requestIdleCallback`; пауза при `visibilitychange`.
- Тяжёлые ассеты — вне precache (раздувают первый кэш); грузить по требованию.

## PWA → Capacitor (на будущее)

PWA-сборка оборачивается в Capacitor без переписывания фронта: `@capacitor/core` + `npx cap add ios/android`, `webDir` = билд Vite. Внутри Capacitor SW не нужен (нативный webview) — отключать регистрацию SW при `Capacitor.isNativePlatform()`. Нативные пуши — через `@capacitor/push-notifications` (не Web Push). Детали — в GUIDE.md.

## Чеклист

- [ ] `registerType: 'prompt'`, `ReloadPrompt.vue` подключён
- [ ] manifest: name/short_name/icons (192/512 + maskable)/theme_color/background_color/display: standalone
- [ ] приватные эндпоинты → `NetworkOnly`, в кэше только app-shell/статика
- [ ] `prefers-reduced-motion` учтён
- [ ] очистка кэшей на logout/смену пары
- [ ] проверка в dev (`devOptions.enabled`) и Lighthouse PWA-аудит
