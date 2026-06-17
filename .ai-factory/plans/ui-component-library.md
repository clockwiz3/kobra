# UI-библиотека компонентов: модалки со слотами + checkbox/radio

> Замена браузерных дефолтных диалогов (`window.confirm`) на полноценные темизированные компоненты со слотами; контролы выбора `BaseCheckbox` / `BaseRadio`; каркас расширяемой библиотеки компонентов (примитивы + сложносоставные).

- **Branch:** —  (git.create_branches=false → работаем на `main`, ветка не создаётся)
- **Date:** 2026-06-17
- **Plan id:** ui-component-library
- **Type:** feature (frontend / design-system)

## Settings

| Параметр | Значение |
|----------|----------|
| Testing | ✅ Да — поднимаем Vitest + @vue/test-utils, компонентные тесты |
| Logging | Verbose (`useLogger`, `log.debug`) на уровне взаимодействия/оркестрации; leaf-контролы (checkbox/radio) — без рантайм-логов (осознанно) |
| Docs | ✅ Обязательный чекпоинт в конце `/aif-implement` → `/aif-docs` (задокументировать библиотеку: конвенции, список компонентов, как добавлять новые) |

## Roadmap Linkage

- **Milestone:** "Customization Engine («цифровой ад»)"
- **Rationale:** UI-библиотека и централизованная темизация компонентов под все 3 темы — фундамент движка кастомизации; ближайший потребитель (замена `confirm` при удалении колонки) — из вехи Kanban, но инфраструктура принадлежит Customization Engine.

## Контекст и решения

- **Дизайн-система централизована** в `resources/css/themes.css` (токены + классы `.btn/.field/.chip/.card/...`) и компонентах `resources/js/components/ui/Base*.vue`. Новые стили (overlay/modal/checkbox/radio/choice) добавляются туда же и темизируются на существующих токенах под `dark`/`light`/`pixel` (pixel — жёсткая геометрия: `--r-*: 0`, offset-тени, border 3px).
- **Текущий браузерный диалог** только один: `window.confirm` в [KanbanColumn.vue:38](resources/js/components/kanban/KanbanColumn.vue) (удаление колонки). Это якорный use-case.
- **«Кипать или удалять карточки».** При удалении колонки модалка предлагает выбор (radio): `keep` — перенести карточки в соседнюю колонку, `delete` — удалить вместе с колонкой. Полноценный *архив* (soft-delete) — это отдельная задача вехи «Privacy & Lifecycle Hardening»; здесь «keep» = relocate через существующий `boardApi.moveCard`, **бэкенд не меняем**.
- **Два яруса библиотеки** (расширяемость под Gantt/Calendar и пр.): `components/ui/` — примитивы (`Base*`-префикс, стили в themes.css, слоты, a11y); `components/composite/` — сложносоставные доменно-агностичные виджеты (резервируем каталог). `ConfirmDialog` — первый композит (строится на `BaseModal` + `BaseRadioGroup`).
- **Зависимости:** новых рантайм-пакетов не добавляем — `@vueuse/core` (есть: `onKeyStroke`, `useScrollLock`), `@vicons/tabler` (есть). Dev-зависимости только для тестов (Vitest, @vue/test-utils, jsdom).
- **a11y-паттерн контролов:** нативный `<input>` (`.sr-only`) + визуальный бокс/точка + `<label>`-обёртка → клавиатура/форма/семантика «из коробки», стиль — на CSS-классах (по образцу `.task .check`, `.palette[aria-pressed]`).

## Задачи

### Фаза 1 — Фундамент (CSS + каркас библиотеки)

- [x] **#1 — CSS-слой overlay/modal/checkbox/radio/choice во всех 3 темах** → `resources/css/themes.css`
  Классы `.overlay`, `.modal` (+`__head/__title/__close/__body/__foot`, `--sm/--lg`), `.checkbox`(`__box`), `.radio`(`__dot`), `.choice-list`/`.choice`, утилита `.sr-only`. Темизация под dark/light/pixel на существующих токенах; reduced-motion-safe. Без логирования.
- [x] **#2 — Каркас UI-библиотеки: barrel + конвенции** → `resources/js/components/ui/index.ts`, `resources/js/components/composite/` (README/.gitkeep)
  Именованные реэкспорты примитивов; зафиксировать двухъярусную конвенцию (ui / composite) и правила (script setup+ts, стили из themes.css, обязательная a11y). Без логирования.

### Фаза 2 — Примитивы выбора  _(blockedBy: #1, #2)_

- [x] **#3 — `BaseCheckbox.vue`** → `resources/js/components/ui/BaseCheckbox.vue`
  `defineModel<boolean>()`, props `label/disabled/indeterminate/name/value`, слот лейбла, нативный input `.sr-only` + `.checkbox__box`, SVG-галочка. Leaf — без рантайм-логов.
- [x] **#4 — `BaseRadioGroup.vue` + `BaseRadio.vue`** → `resources/js/components/ui/BaseRadio*.vue`
  Группа-владелец `defineModel`, `provide/inject` контекста, нативные `<input type=radio>` с общим `name` (нативная навигация стрелками), `role="radiogroup"`. Leaf — без рантайм-логов.

### Фаза 3 — Overlay-слой  _(#5 blockedBy #1,#2 · #6 blockedBy #5,#4 · #7 blockedBy #6)_

- [x] **#5 — `BaseModal.vue`** → `resources/js/components/ui/BaseModal.vue`
  `Teleport`+`Transition` (reduced-motion-safe), `role=dialog`/`aria-modal`, слоты `header/title/default/footer`, props `closable/closeOnOverlay/closeOnEsc/size`. Esc (`onKeyStroke`), overlay-click, `useScrollLock`, ручной focus-trap + возврат фокуса. `useLogger('BaseModal')`: `log.debug` open/close(reason).
- [x] **#6 — `ConfirmDialog.vue`** → `resources/js/components/ui/ConfirmDialog.vue`
  Композит на `BaseModal`: `title/message/confirmText/cancelText/tone` + опц. `choices` (radio через `BaseRadioGroup`/`.choice-list`), emits `confirm({choice})`/`cancel`. `useLogger('ConfirmDialog')`: `log.debug` confirm(choice)/cancel.
- [x] **#7 — `useConfirm` + `ConfirmRoot`** → `resources/js/composables/useConfirm.ts`, `resources/js/components/ui/ConfirmRoot.vue`, монтаж в `resources/js/App.vue`
  Императивный `confirm(opts): Promise<{confirmed, choice?}>` через singleton-хост. `useLogger('useConfirm')`: `log.debug` запрос(title)/резолв(confirmed,choice).

### Фаза 4 — Интеграция в доску  _(blockedBy: #7)_

- [x] **#8 — Заменить `window.confirm` в `KanbanColumn` на `useConfirm` с выбором keep/delete** → `resources/js/components/kanban/KanbanColumn.vue`, `resources/js/stores/board.ts`
  `remove()` → `await confirm({ title, tone:'danger', choices:[keep|delete], defaultChoice:'keep' })`; `store.removeColumn(columnId, { keepCards })` переносит карточки в соседнюю колонку (`boardApi.moveCard`/локально) либо каскадит. Edge-case: единственная колонка → без choices/фолбэк delete. Логи: `log.debug` в store (`{columnId, keepCards, movedCount}`) и в `remove` (`{columnId, choice}`).

### Фаза 5 — Тесты  _(#9 независима · #10 blockedBy #9,#8)_

- [x] **#9 — Поднять Vitest + @vue/test-utils + jsdom** → `package.json`, `vitest.config.ts`, `tsconfig`, опц. `resources/js/test/setup.ts`
  scripts `test`/`test:watch`, env jsdom, alias `@`, моки `matchMedia`. Без рантайм-логов. Смоук-тест зелёный.
- [x] **#10 — Компонентные тесты библиотеки и интеграции** → `resources/js/components/ui/__tests__/`, `resources/js/stores/__tests__/`
  checkbox/radio (v-model, disabled, indeterminate), modal (open/Esc/overlay/слоты), ConfirmDialog (confirm{choice}/cancel), `useConfirm` (резолв промиса), board `removeColumn({keepCards})`. `npm run test` зелёный.

## Commit Plan

| Checkpoint | Задачи | Сообщение коммита (conventional) |
|-----------|--------|----------------------------------|
| 1 | #1, #2 | `feat(ui): css-слой overlay/modal/checkbox/radio (3 темы) + каркас библиотеки` |
| 2 | #3, #4 | `feat(ui): контролы выбора BaseCheckbox и BaseRadioGroup/BaseRadio` |
| 3 | #5, #6, #7 | `feat(ui): BaseModal + ConfirmDialog + императивный useConfirm` |
| 4 | #8 | `feat(kanban): модалка подтверждения при удалении колонки (keep/delete карточек)` |
| 5 | #9, #10 | `test(ui): vitest + компонентные тесты библиотеки и доски` |

## Out of scope (явно)

- Полноценный архив карточек (soft-delete / отдельная таблица) — веха «Privacy & Lifecycle Hardening».
- Сами сложносоставные виджеты (Gantt, Calendar) — только резервируем каталог/конвенцию.
- Синхронизация выбранной темы в `couples.settings` (shard-scoped) — веха «Customization Engine», отдельно.
- Toast/Snackbar и прочие overlay-паттерны — последующее расширение библиотеки.
