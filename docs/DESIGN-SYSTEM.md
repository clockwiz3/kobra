# Дизайн-система

Нарисована в Claude Design и перенесена в код. Исходный showcase и лог дизайн-сессии лежат в `docs/design/`.

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
| `light` | **по умолчанию** | кремовая бумага (#F4E7D6), зерно |
| `dark` | вторая | rose→violet на почти чёрном (#0F0F17), мягкие тени и свечение |
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

`.btn` (`.btn-primary/secondary/ghost`, `.btn-sm`), `.field`+`.input`/`.textarea`/`.select`, `.switch`, `.seg` (segmented), `.card`, `.badge` (`.ok/.warn/.err`), `.chip` (`.solid`/`.warn` — единая геометрия во всех темах, различаются только цветом/фоном), `.toast`, `.couple`+`.av` (аватары пары), `.goal`+`.bar`/`.hearts` (прогресс целей), `.task` (карточка задачи), `.hero`, `.topbar`+`.theme-switch`, `.swatch`/`.palette`.

**Overlay-слой и контролы выбора:** `.overlay`+`.modal` (диалоги), `.checkbox`, `.radio`, `.choice-list`/`.choice` (выбираемые карточки), утилита `.sr-only`. Vue-компоненты поверх этих классов — `BaseModal`, `BaseCheckbox`, `BaseRadioGroup`/`BaseRadio`, `ConfirmDialog` + императивный `useConfirm()` — описаны в [ui-library.md](ui-library.md).

## Подключение

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
  <BaseButton variant="primary">Добавить ♥</BaseButton>
</template>
```

## Что можно доделать

1. **Шрифты локально,** чтобы PWA работала офлайн. Сейчас они грузятся с Google Fonts.
2. **Контраст** `text-muted` на `surface` в светлой теме стоит проверить.
3. **Остальные компоненты** (toast, switch, segmented, аватары, прогресс целей) переносить по мере надобности.
4. **Общая тема пары:** сохранять выбор в `couples.settings`, чтобы у обоих был один стиль. Пока тема хранится в localStorage у каждого своя.
