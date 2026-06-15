# Implementation Plan: Kanban — совместные задачи пары

Branch: none (git on, create_branches=false → коммиты в main)
Created: 2026-06-15

## Контекст

Второй продуктовый milestone после вайтлиста. Канбан-доска для пары: кастомные колонки, карточки с назначением он/она/вместе, drag-and-drop, real-time синхронизация между партнёрами. Строится на готовом фундаменте (Laravel 13 + Vue 3 + tenancy + дизайн-система + геймификация).

## Settings
- Testing: yes (включая tenancy-изоляцию kanban-данных)
- Logging: verbose (`LOG_LEVEL` / `VITE_LOG_LEVEL`, формат `[Service.method] message {context}`)
- Docs: yes (docs/DATABASE.md + AGENTS.md; docs-чекпойнт в /aif-implement)

## Roadmap Linkage
Milestone: "Kanban (совместные задачи)"
Rationale: Это и есть реализация этого майлстоуна — boards/columns/cards + real-time.

## Решения (зафиксированы с пользователем)
- **Кастомные колонки пары:** создание/переименование/сортировка/удаление (напр. Идеи / На неделе / Свидания / Готово).
- **Drag-and-drop сразу:** карточки между колонками + реордер внутри + реордер колонок (`vuedraggable` / vue.draggable.next).
- **Назначение:** он / она / вместе (сегмент `.seg` из дизайн-системы) + аватары пары.
- **Подход:** фронт СНАЧАЛА на мок-данных (быстрый UI), затем backend couple-scoped + API, затем real-time (Reverb).

## Архитектурные инварианты (наследуются)
- **Tenancy:** ВСЕ kanban-таблицы — couple-owned: `couple_id` NOT NULL FK CASCADE + trait `CoupleScoped` + `CoupleScope` fail-closed. Доступ за `auth:sanctum` + `couple.context` (ResolveCoupleContext) + Policy. Новые таблицы — в closed-list `docs/DATABASE.md`.
- **Геймификация:** завершение карточки (`done=true`) → `GamificationService.recordEvent($couple, 'task_completed', $actor)` (XP/стрик/ачивка). Снятие done — без штрафа (upside-only).
- **Real-time:** Reverb, приватный канал `couple.{id}` (авторизация по членству), broadcast минимальных DTO (не сырые модели). Учесть Windows-деградацию (native-PHP без ext-pcntl — проверить `reverb:start`).
- **DTO наружу:** только `spatie/laravel-data` (→ TS-типы), не сырые Eloquent-модели.

## Commit Plan
- **Commit 1** (после задач 14–18): `feat(kanban): board UI on mock data + drag-and-drop`
- **Commit 2** (после задач 19–22): `feat(kanban): couple-scoped backend (migrations, models, services, API)`
- **Commit 3** (после задач 23): `feat(kanban): wire board UI to real API`
- **Commit 4** (после задач 24–25): `feat(kanban): real-time via Reverb + Echo`
- **Commit 5** (после задачи 26): `test(kanban): tenancy isolation + correctness`

## Tasks

### Phase 1: Frontend UI на мок-данных + DnD
- [x] Task 14: Pinia board-store + TS-типы + мок-данные (columns/cards, экшены move/add/toggle/reorder).
- [x] Task 15: KanbanCard.vue — `.task`, назначение он/она/вместе, метки `.chip`, due `.badge`, чек/done. (depends on 14)
- [x] Task 16: KanbanColumn.vue — заголовок+счётчик, add/rename/remove, список карточек. (depends on 14,15)
- [x] Task 17: KanbanBoard.vue + страница + маршрут `/board` — couple-header, ряд колонок, адаптив. (depends on 14,16)
- [x] Task 18: Drag-and-drop (`vuedraggable`) — карточки между/внутри колонок + реордер колонок. (depends on 16,17)
<!-- Commit checkpoint: tasks 14-18 -->

### Phase 2: Backend couple-scoped
- [ ] Task 19: Миграции boards/board_columns/cards/labels/card_label (couple-scoped, CASCADE, position) + docs/DATABASE.md + AGENTS.md.
- [ ] Task 20: Модели + enum CardAssignee (CoupleScoped) + связи. (depends on 19)
- [ ] Task 21: BoardService/CardService (CRUD/move/reorder, toggle-done → `task_completed` event). (depends on 20)
- [ ] Task 22: Couple-scoped API (controllers/routes за auth+context+Policy, FormRequest, DTO). (depends on 21)
<!-- Commit checkpoint: tasks 19-22 -->

### Phase 3: Интеграция фронт↔бэк + real-time
- [ ] Task 23: Подключить board-store к реальному API (typed client, оптимизм+откат). (depends on 22,18)
- [ ] Task 24: Reverb + broadcasting + канал `couple.{id}` + broadcast card/column событий. (depends on 22)
- [ ] Task 25: Echo на фронте — подписка `couple.{id}` + применение realtime к store. (depends on 24,23)
<!-- Commit checkpoint: tasks 23-25 -->

### Phase 4: Тесты
- [ ] Task 26: Тесты — изоляция tenancy (две пары) + card lifecycle + gamification + Policy + Reverb channel auth. (depends on 21,22,24)
<!-- Commit checkpoint: task 26 -->

## Заметки
- Фронт-цепочка (14→18) и бэкенд-цепочка (19→22) независимы — можно вести параллельно; сходятся на задаче 23.
- `vuedraggable@next` (Vue 3), `laravel-echo` + `pusher-js` (Reverb pusher-совместим).
- В TaskList сейчас сосуществуют задачи двух планов: foundation-waitlist (#1–13, отложены #12/#13) и kanban (#14–26). При `/aif-implement` указывать план: `@.ai-factory/plans/kanban-board.md`.
