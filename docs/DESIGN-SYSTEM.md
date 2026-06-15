# Cuploid — Design System

Источник: смокапено в **Claude Design** (claude.ai/design) и импортировано в репозиторий. Оригинальный браузабельный showcase (self-contained) и расшифровка дизайн-сессии лежат в `docs/design/`.

## Файлы

| Путь | Что |
|------|-----|
| `resources/css/themes.css` | **Источник правды**: токены (CSS-переменные) + классы компонентов. 3 темы, 5 гамм. |
| `resources/js/composables/useTheme.ts` | Runtime: переключение темы/гаммы, персист в localStorage, `prefersReducedMotion()`. |
| `resources/js/components/ui/*.vue` | Vue-порты компонентов (Base*, ThemeSwitch, AccentPicker). |
| `tailwind.config.cjs` | Маппинг токенов в Tailwind-утилиты (следуют активной теме). |
| `docs/design/cuploid-design-system.reference.html` | Оригинальный showcase (offline, шрифты встроены) — открыть в браузере для эталона. |
| `docs/design/design-chat.md` | Лог дизайн-сессии (почему honey-lavender, почему Fraunces+Inter, и т.д.). |

## Темы — `[data-theme]`

| Тема | Назначение | Характер |
|------|-----------|----------|
| `dark` | **основная** | rose→violet на near-black (#0F0F17), мягкие тени + свечение |
| `light` | альтернатива | кремовая ретро-бумага (#F4E7D6), тёплый, paper-grain |
| `pixel` | спец-режим | 8-бит: пиксельные шрифты, жёсткие offset-тени, квадратная геометрия (радиусы = 0) |

## Гаммы — `[data-accent]` (default: `honey`)

`rose` (Rose×Violet) · `sunset` · **`honey` (Honey Lavender — дефолт)** · `berry` · `lagoon`.
Меняют только `--accent` / `--accent-2`; `--grad` пересобирается автоматически. В light-теме гаммы слегка адаптированы под контраст на кремовом.

## Токены (значения для dark)

- **Поверхности:** `--bg #0F0F17`, `--surface #171722`, `--surface-2 #1F1F2E`
- **Текст:** `--text #F4F1F7`, `--text-muted #A39FB3`
- **Границы:** `--border rgba(255,255,255,.08)`, `--border-strong rgba(255,255,255,.14)`
- **Акценты:** `--accent`, `--accent-2`, `--grad` (135° accent→accent-2), `--accent-ink #fff`
- **Семантика:** `--success #4ADE80`, `--error #FB7185`, `--warning #FBBF24`
- **Радиусы:** `--r-sm 8`, `--r-md 14`, `--r-lg 24`, `--r-pill 999`
- **Глубина:** `--shadow`, `--shadow-sm`, `--glow`, `--inner-glow`
- **Шрифты:** `--font-head 'Fraunces'`, `--font-body 'Inter'` (pixel: Pixelify Sans / Press Start 2P)
- **Easing:** `--ease cubic-bezier(.22,.61,.36,1)`

## Компоненты (классы в themes.css)

`.btn` (`.btn-primary/secondary/ghost`, `.btn-sm`), `.field`+`.input`/`.textarea`/`.select`, `.switch`, `.seg` (segmented), `.card`, `.badge` (`.ok/.warn/.err`), `.chip` (`.solid`), `.toast`, `.couple`+`.av` (аватары пары), `.goal`+`.bar`/`.hearts` (прогресс целей), `.task` (карточка задачи), `.hero`, `.topbar`+`.theme-switch`, `.swatch`/`.palette`.

## Подключение (в index.html при scaffolding, задача #1)

Шрифты (Google Fonts; для PWA-offline позже — самохостинг, см. follow-ups):

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=Pixelify+Sans:wght@400;500;600;700&family=Press+Start+2P&display=swap" rel="stylesheet" />
```

Корневой элемент приложения получает класс `ds-root` и атрибуты темы:

```html
<div id="app" class="ds-root" data-theme="dark" data-accent="honey"></div>
```

В `main.ts` импортировать CSS и инициализировать тему:

```ts
import '../css/themes.css'
import { useTheme } from './composables/useTheme'
// ...
useTheme() // навесит data-theme/data-accent из localStorage на .ds-root
```

## Использование компонентов (Vue)

```vue
<script setup lang="ts">
import BaseButton from '@/components/ui/BaseButton.vue'
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
</script>
<template>
  <ThemeSwitch />
  <BaseButton variant="primary">Создать пространство ♥</BaseButton>
</template>
```

## Связь с продуктом

- **Customization Engine (milestone):** темы + 5 гамм — прямое зерно «широкой кастомизации». Выбор пары должен персиститься в `couples.settings` (shard-scoped), чтобы оба партнёра видели общий стиль. `pixel`-тема и пасхалочные гаммы — кандидаты на разблокировку за **hearts**.
- **Геймификация (upside-only):** в DS уже есть `.goal`/`.hearts`/стрик-бейдж — визуальный язык для общего профиля пары и валюты hearts. Без рейтингов/сравнений между парами.
- **Приватность:** UI оперирует «он/она/вместе» и аватарами пары — всё в рамках одного шарда.

## Follow-ups

1. **Self-host шрифтов** для PWA-offline (сейчас Google Fonts via `<link>` — нужен интернет). Workbox-precache локальных woff2 вместо рантайм-кэша сторонних CDN.
2. **Доступность:** проверить контраст `text-muted` на `surface` в каждой теме (особенно light); прогнать `design:accessibility-review`.
3. **Расширить порт компонентов** по мере надобности (toast, switch, segmented, couple-avatars, goal/hearts, task-card) — пока перенесён базовый набор.
4. **Per-couple persist** темы/гаммы при появлении активной пары (см. Customization Engine).
