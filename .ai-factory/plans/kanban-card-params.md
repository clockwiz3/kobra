# Implementation Plan: Параметризация карточек задач (выезжающая панель)

Branch: none (git.create_branches=false — работаем в текущей ветке `main`)
Created: 2026-06-17

## Обзор

Справа от доски открывается выезжающая панель (drawer) с параметрами карточки.
Старт — базовые параметры: **Название**, **Описание**, **Исполнитель** (он / она / вместе),
**Дедлайн** (новый дейтпикер: одиночная дата ИЛИ диапазон). Архитектура заложена под рост:
переиспользуемый `BaseDrawer`, секционная структура панели (новый параметр = новая секция),
единый `CardPatch`-контракт, дейтпикер с режимами.

**Что уже есть в коде (опора):** колонки `cards.description/assignee/due_at`, `CardData` DTO,
`boardApi.updateCard` (PATCH), `BaseModal` (паттерн оверлея), `BaseRadioGroup`/`BaseField`,
дизайн-токены в `themes.css`. **Чего нет:** колонки конца диапазона, экшена `updateCard` в сторе,
клика по карточке, drawer-примитива, дейтпикера, библиотеки дат.

## Settings
- Testing: yes
- Logging: verbose
- Docs: no  # WARN [docs] only, без обязательного docs-чекпоинта

## Roadmap Linkage
Milestone: "Kanban (совместные задачи)"
Rationale: Параметризация карточек (дедлайны, исполнитель, описание) — часть Kanban-вехи совместных задач.

## Технические решения (подтверждены)
- **Дейтпикер:** через `dayjs` (ru-локаль, ~2KB) — проще арифметика диапазонов/локаль.
- **Сохранение:** автосохранение с debounce, оптимистичный апдейт стора (ближе к будущему real-time у пары).
- **Дедлайн-модель:** `due_at` = одиночная дата ИЛИ начало диапазона; `due_end_at` (новая колонка) = конец диапазона (null для single).
- **Масштабирование:** `BaseDrawer` (side/width-параметры) переиспользуем; панель — массив секций параметров; `CardPatch` — единая точка добавления полей; month-grid структурно готов к вынесению в `BaseCalendar`.

## Commit Plan
- **Commit 1** (после задачи 1): `chore(deps): dayjs + ru date utils`
- **Commit 2** (после задач 2–5): `feat(kanban): бэкенд параметров карточки — due-range, DTO, FormRequest, сервис`
- **Commit 3** (после задач 6–7): `feat(kanban): фронт-контракт + стор updateCard/selectedCard`
- **Commit 4** (после задач 8–9): `feat(ui): BaseDrawer + BaseDatePicker (single/range)`
- **Commit 5** (после задач 10–11): `feat(kanban): панель параметров карточки + автосохранение`

## Tasks

### Фаза 0: Зависимости и утилиты дат
- [x] Задача 1: Подключить `dayjs` + модуль `resources/js/lib/date.ts` (ru, неделя с пн, helpers: parse/format/formatRange/monthMatrix/isRangeValid, тип `DateRange`) + тесты.

### Фаза 1: Бэкенд — схема, контракт, валидация, сервис
- [x] Задача 2: Миграция `add_due_end_at_to_cards` + каст `due_end_at` в `Card` + апдейт `docs/DATABASE.md`.
- [x] Задача 3: Расширить `CardData` DTO полем `dueEndAt` (зависит от 2).
- [x] Задача 5: `CardService::create/update` принимают `description` + `due_at`/`due_end_at`, бэкстоп-инвариант диапазона (зависит от 2).
- [x] Задача 4: `StoreCardRequest`/`UpdateCardRequest` + рефактор `CardController` + feature-тесты (валидация, range, tenancy) (зависит от 3, 5). _(тест: `tests/Feature/Couple/CardUpdateTest.php` — рядом с существующим CardTest)_
<!-- Commit checkpoint: задачи 2–5 -->

### Фаза 2: Фронтенд — контракт и стор
- [x] Задача 6: `types/board.ts` (`Card.dueEndAt`, тип `CardPatch`) + `api/board.ts` (`ApiCard.dueEndAt`, `updateCard` с camel→snake) (зависит от 3).
- [x] Задача 7: Стор `board.ts` — экшен `updateCard` (оптимистично + rollback + мок) и состояние `selectedCard`/`openCard`/`closeCard` + тесты стора (зависит от 6).
<!-- Commit checkpoint: задачи 6–7 -->

### Фаза 3: UI-примитивы (переиспользуемые)
- [x] Задача 8: `BaseDrawer.vue` (Teleport/focus-trap/Esc/scroll-lock, side/width) + drawer-стили в `themes.css` (3 темы) + экспорт + тесты. Независим.
- [x] Задача 9: `BaseDatePicker.vue` (mode single|range, поповер month-grid, ru, a11y) + стили + экспорт + тесты (зависит от 1).
<!-- Commit checkpoint: задачи 8–9 -->

### Фаза 4: Композит и интеграция
- [x] Задача 10: `CardDetailDrawer.vue` (секции: название/описание/исполнитель/дедлайн single↔range, автосейв debounce → store.updateCard) + барель композитов + тесты (зависит от 7, 8, 9). _(панель самодостаточна: open берётся из стора, Board только монтирует её)_
- [x] Задача 11: Клик по карточке → открытие панели (`KanbanCard`/`KanbanColumn`/`Board.vue`) + форматирование дедлайна на лице карточки + тесты (зависит от 10).
<!-- Commit checkpoint: задачи 10–11 -->

## Заметки по масштабированию (следующие параметры — без переписывания)
- Новый параметр карточки = поле в `CardPatch` + правило в FormRequest + (опц.) колонка/DTO + новая секция в `CardDetailDrawer`.
- Кандидаты на потом: метки (labels — pivot уже есть), приоритет, чеклист, комментарии, повторяемость, время в дедлайне (дейтпикер расширяется до date-time).
- `BaseDrawer` переиспользуется для панелей фильтров/настроек; month-grid выносится в `BaseCalendar` (composite/) под календарный вид/Gantt из README композитов.
- Real-time (Reverb, `couple.{id}`) ляжет поверх оптимистичных апдейтов стора без смены контракта.
