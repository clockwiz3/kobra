# План: Глобальное меню навигации (бургер → сайдбар)

**Дата:** 2026-06-20
**Ветка:** —  (git.create_branches: false → план slug-scoped, без ветки; работаем на `main`)
**Тип:** feature (UI-каркас навигации)

## Контекст и решение

Сейчас глобального меню нет: топбар (`BrandMark` + `ThemeSwitch` + «Выйти») зашит **внутри** [Board.vue](../../resources/js/pages/Board.vue), а роутер знает только `/` (доска), `/login`, `/confirm/:token`. Приложение mobile-first PWA → Capacitor; доска занимает весь вьюпорт (`height:100vh; overflow:hidden`, внутренний скролл по колонкам).

**Выбранный паттерн меню** — бургер в топбаре → выезжающий **слева** drawer со списком разделов. Почему:

- Переиспользует готовый, уже протестированный [BaseDrawer](../../resources/js/components/ui/BaseDrawer.vue) (`side="left"`, focus-trap, Esc, scroll-lock, возврат фокуса) и CSS-классы `.drawer--left` в [themes.css](../../resources/css/themes.css) → почти ноль новой инфраструктуры.
- Не ест ширину/высоту доски, одинаково работает на телефоне и десктопе, легко расширяется (раздел = строка в массиве).

**Ключевой архитектурный шаг:** вынести хром (топбар + бургер + меню) из `Board.vue` в общий **`AppShell`**, а разделы сделать **вложенными роутами** под ним. `login`/`confirm` остаются вне шелла. Так меню появляется на каждой авторизованной странице «бесплатно».

**Разделы навигации** (страницы — заглушки «Скоро», наполнение позже):

| Пункт | route name | путь | статус |
|-------|-----------|------|--------|
| Доска | `dashboard` | `/` (alias `/board`) | готово |
| Вишлисты | `wishlists` | `/wishlists` | заглушка |
| Гант | `gantt` | `/gantt` | заглушка |
| Календарь | `calendar` | `/calendar` | заглушка |

**Иконки** — xicons на `currentColor`, размер нормализуется через CSS. Компоненты: бургер `Menu2`, Доска `LayoutKanban`, Вишлисты `Heart`, Календарь `Calendar` — все из `@vicons/tabler` (уже в зависимостях, `^0.13.0`); Гант — `GanttChart24Regular` из **`@vicons/fluent`** (в Tabler 0.13 литерального Gantt нет; берём 24-вариант под 24px-сетку Tabler). Требуется доустановить `@vicons/fluent` (`npm i @vicons/fluent`).

## Settings

- **Testing:** да — Vitest + @vue/test-utils + jsdom (в стиле существующих тестов `BaseDrawer.test.ts`, `KanbanColumn.test.ts`).
- **Logging:** verbose — `useLogger(...)`, формат `[Service.method] message {context}` (см. [rules/base.md](../rules/base.md)).
- **Docs:** warn-only (не обязательный чекпойнт; `/aif-docs` по желанию).

## Roadmap Linkage

- **Milestone:** none
- **Rationale:** Кросс-секционный UI-каркас навигации; не привязан к одной вехе. Готовит вход для будущих **Wishlists** (есть в роадмапе) и для разделов **Гант/Календарь** (новые, в роадмапе пока не значатся — внести отдельной вехой при наполнении).

## Tasks

> ✅ **Реализовано 2026-06-20** — все 8 задач выполнены. 119/119 Vitest-тестов зелёные (в т.ч. 7 новых на меню), `vite build` проходит, typecheck нового кода чистый. Не закоммичено (по запросу).
>
> 🔧 **Фикс 2026-06-20** — в pixel-теме подвал меню (переключатель темы + «Выйти») не влезал в узкий drawer: горизонтальный ряд `.nav-foot` переполнялся широкими pixel-кнопками. Перевёл подвал в вертикальный стек — переключатель темы во всю ширину сегментами, «Выйти» отдельной строкой. Проверено визуально (рендер pixel/dark/light, before/after).
>
> 🎛 **Топбар 2026-06-20** — топбар во всю ширину экрана (убрал `.wrap`-центрирование): слева бургер + лого, справа вместо ThemeSwitch и текстовой кнопки «Выйти» — иконки уведомлений (`Bell`) и выхода (`Logout`) из `@vicons/tabler`. Переключатель темы остался только в подвале меню. Боковые отступы топбара = доске (16/32px). Проверено визуально (desktop+mobile, pixel/dark/light), 8/8 layout-тестов, `vite build` ок.
>
> 🅰 **Пиксель-шрифт меню 2026-06-20** — в pixel-теме подписи разделов раньше рендерились в Inter (не наследовали pixel `--font-body`). Задал `.nav-label` шрифт `var(--font-pill)` (Press Start 2P, 13px) — как у бренда «Kobra» и pixel-хрома; кириллицу шрифт покрывает. Через `:global([data-theme="pixel"])` (тема висит на `.ds-root`-предке). Проверено визуально (drawer 360/320px, «Календарь» влезает).

### Фаза 1 — Каркас приложения и роутинг

1. **AppShell: общий лейаут-хром** — `resources/js/components/layout/AppShell.vue` (new). Перенести из `Board.vue` `.board-shell` (100vh-flex) + `<header class="topbar">` (BrandMark + ThemeSwitch + «Выйти»), место под бургер слева, тело = `<router-view/>` (flex:1; min-height:0). `logout()` переезжает сюда. Стили `.topbar` — на токенах (3 темы). Лог: `useLogger('AppShell')`, `log.debug('logout')`.
2. **Заглушки разделов** — `pages/Wishlists.vue`, `pages/Gantt.vue`, `pages/Calendar.vue` (new). `sec-title` + empty-state на `BaseCard` («Скоро…»), обычный внутренний скролл (не 100vh-каркас). Лог: `useLogger(<name>)`, `log.debug('mounted')`.
3. **Роутер: вложить авторизованные роуты в AppShell** ⟸ (1,2) — `main.ts`. Родитель `{ path:'/', component: AppShell, meta:{requiresAuth:true}, children:[dashboard(alias /board), wishlists, gantt, calendar] }`; `login`/`confirm` снаружи; AppShell статически, страницы лениво; сохранить auth-гейт и алиас `/board`.
4. **Board.vue: убрать локальный топбар** ⟸ (1,3) — снять `<header class="topbar">`, `logout()`, лишние импорты; корень страницы = `.board-main` внутри слота AppShell; проверить, что доска по-прежнему ровно во вьюпорт без скролла страницы. Существующие `log.debug` сохранить.

### Фаза 2 — Меню навигации

5. **nav.ts: единый источник пунктов** — `resources/js/lib/nav.ts` (new). Сначала `npm i @vicons/fluent`. `NavItem { key, label, routeName, icon: Component }` + массив (board/wishlists/gantt/calendar): `LayoutKanban`/`Heart`/`Calendar` из `@vicons/tabler`, `GanttChart24Regular` из `@vicons/fluent`. Модуль не reactive → `markRaw` не нужен.
6. **NavMenu.vue** ⟸ (5) — `resources/js/components/layout/NavMenu.vue` (new) поверх `BaseDrawer side="left"`. `v-model:open`; шапка — BrandMark; тело — `router-link` по `navItems` с подсветкой активного (`is-active`/`aria-current`); закрытие при навигации; footer — ThemeSwitch + «Выйти» (`emit('logout')`). Лог: `useLogger('NavMenu')`, `log.debug('navigate', { to })`.
7. **Подключить бургер и NavMenu в AppShell** ⟸ (1,6) — кнопка-бургер слева (`aria-label`, `aria-expanded`, `aria-controls="app-nav"`, тач-таргет ≥40px), локальный `navOpen`, `<NavMenu v-model:open="navOpen" @logout="logout" />`. Лог: `log.debug('toggle-nav', { open })`.

### Фаза 3 — Тесты

8. **Тесты меню** ⟸ (6,7) — `components/layout/__tests__/NavMenu.test.ts` (new): рендер всех пунктов при open; пусто при закрытом; клик по пункту → `router.push` + `open=false`; активный пункт получает `is-active`/`aria-current`; «Выйти» эмитит `logout`. Опц. smoke AppShell (бургер тогглит). Запуск `npx vitest run` через PowerShell.

## Commit Plan

- **Commit 1 — после задачи 4** (`feat(nav): app-shell + nested routing`): каркас, заглушки, роутинг, очищенный Board. Приложение собирается, доска работает; разделы доступны по URL (меню ещё нет).
- **Commit 2 — после задачи 7** (`feat(nav): burger drawer menu`): меню полностью функционально, навигация по всем разделам через бургер.
- **Commit 3 — после задачи 8** (`test(nav): NavMenu coverage`): тесты меню.

> Сообщения — Conventional Commits, в стиле истории репо (можно RU-описание после типа, как в текущих коммитах).

## Заметки по реализации

- **a11y:** бургер ↔ `aria-controls="app-nav"`; активный пункт `aria-current="page"`; видимый фокус; focus-trap уже в BaseDrawer.
- **Темы:** стили меню — на дизайн-токенах (`var(--surface)`, `var(--border)`, акцент), pixel-специфику (радиусы) — точечно в `themes.css`, как сделано для `.drawer`.
- **Дубль ThemeSwitch/«Выйти»** в топбаре и в footer меню — осознанный (десктоп-топбар + мобильная досягаемость из меню), соответствует выбранному макету.
- **Reduced motion:** анимации drawer уже уважают `prefers-reduced-motion` (BaseDrawer/themes.css) — ничего дополнительно.
