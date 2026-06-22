# План: раздел «Задачи» (бэклог) — список убранных карточек + возврат на доску

**Тип:** feature · **Ветка:** не создаётся (`git.create_branches=false`) · **Создан:** 2026-06-21
**Slug:** `backlog-section` · **URL фичи:** http://cuploid.test/backlog

## Settings

- **Testing:** да — Pest (бэкенд) + Vitest (фронт). Обязателен tenancy-харнесс (изоляция двух пар).
- **Logging:** verbose. Формат `[Service.method] message {context}`, env-driven `LOG_LEVEL`.
- **Docs:** да — обязательный docs-чекпоинт на завершении (через `/aif-docs`).

## Roadmap Linkage

- **Milestone:** "Kanban (совместные задачи)"
- **Rationale:** бэклог — это «вторая половина» механики «убрать с доски» (`on_board=false`), часть кан­бан-вехи. Сам флаг и UI-удаление с доски уже сделаны на предыдущих коммитах; здесь дочитываем чтение и возврат.

## Контекст (текущее состояние)

Механика **«убрать с доски» уже работает end-to-end** (предыдущие коммиты):

- Миграция `2026_06_20_050003_add_on_board_to_cards_table` — колонка `on_board boolean default true` + индекс `(board_column_id, on_board, position)`.
- `BoardColumn::cards()` фильтрует `where('on_board', true)` → убранные карточки исчезают с доски, но остаются в БД.
- `boardApi.removeFromBoard` / `store.removeFromBoard` шлют `PATCH /cards/{id}` с `on_board:false` (generic update). `board_column_id` при этом **сохраняется** — исходная колонка карточки известна.
- В `KanbanCard.vue` есть пункт меню «Убрать с доски».

**Чего нет (предмет этого плана):**

1. Чтения бэклога — нет эндпоинта для `on_board=false`.
2. Возврата карточки на доску.
3. Самой страницы — `resources/js/pages/Backlog.vue` пока заглушка.

## Решения (согласованы с заказчиком)

- **Возврат на доску:** юзер выбирает целевую колонку (BaseSelect). По умолчанию преселект — исходная колонка карточки, если она ещё существует; иначе первая не-терминальная.
- **Действия в бэклоге:** возврат на доску · удалить навсегда (`DELETE /cards/{id}`, уже есть) · открыть/редактировать параметры (переиспользуем `CardDetailDrawer`).
- Карточка возвращается **в конец** целевой колонки; готовность пересчитывается из терминальности (терминал → `done=true`, иначе `done=false`) — переиспользуем `CardService::move()` (renumber + `applyDoneTransition`).

## Известные ограничения (вне scope)

- Удаление колонки каскадит карточки (FK `ON DELETE CASCADE`) — включая её карточки в бэклоге. То есть удаление колонки тихо уносит «припаркованные» под ней backlog-карточки. Реструктуризация (репарент backlog-карточек при удалении колонки) — отдельно, веха **Privacy & Lifecycle Hardening**.
- `removeFromBoard` через generic update не компактит позиции исходной колонки (остаётся «дыра» в `position`). Не влияет на корректность (orderBy + renumber при следующем move чинят). Опциональная чистка — позже.

## Tasks

### Фаза 1 — Бэкенд: чтение и возврат

1. [x] **`CardService::returnToBoard(Card, BoardColumn, ?User)`** — `app/Services/Kanban/CardService.php`. В транзакции: `on_board=true`, затем делегировать в `move($card, $toColumn, PHP_INT_MAX, $actor)` (конец колонки + renumber + `applyDoneTransition`). Лог `INFO [CardService.returnToBoard]`.
2. [x] **`GET /api/couple/backlog`** — новый `BacklogController@index`; `Card::where('on_board', false)->orderByDesc('updated_at')` (couple-scoped), `CardData[]`. Роут в группе `couple`. Лог `DEBUG [BacklogController.index] {count}`.
3. [x] **`PATCH /api/couple/cards/{id}/return`** — `CardController@return`; валидация `column_id`, `BoardColumn::findOrFail` (scoped, 404 на чужую), вызов `returnToBoard`. Роут **выше** generic `cards/{cardId}`. Лог `DEBUG`. _(blockedBy: 1)_

### Фаза 2 — Фронт: api + store

4. [x] **API-клиент** — `resources/js/api/board.ts`: `fetchBacklog()`, `returnToBoard(cardId, columnId)`.
5. [x] **board store** — `resources/js/stores/board.ts`: `backlogCards`, `fetchBacklog`, `returnToBoard` (оптимистично), `removeFromBacklog`; расширить `findCard` поиском по `backlogCards` (чтобы `CardDetailDrawer` редактировал backlog-карточку); `removeFromBoard` опц. пушит карточку в `backlogCards`. _(blockedBy: 4)_

### Фаза 3 — UI

6. [x] **`Backlog.vue`** — `resources/js/pages/Backlog.vue` (заменить заглушку): `fetchBoard`+`fetchBacklog` на mount; empty-state; список карточек с чипами и исходной колонкой; BaseSelect выбора колонки + «Вернуть на доску»; «Удалить навсегда» (через BaseModal-подтверждение); клик по телу → `openCard` (редактирование); смонтировать `<CardDetailDrawer />`. _(blockedBy: 5)_

### Фаза 4 — Тесты

7. [x] **Pest** — `tests/Feature/Couple/BacklogTest.php`: выдача только `on_board=false`; возврат → `on_board=true` + конец колонки + done-переход (терминал/не-терминал); tenancy-изоляция (чужой бэклог не виден, return чужой карточки → 404); удаление. Прогон с `LOG_LEVEL=debug`, ассерты по БД. _(blockedBy: 3)_
8. [x] **Vitest** — store (`fetchBacklog`/`returnToBoard`/`findCard`+бэклог/`removeFromBacklog`) + `Backlog.vue` (empty-state, рендер списка, «Вернуть» зовёт стор, «Удалить» после подтверждения). _(blockedBy: 6)_

## Дополнение (после реализации): создание задач прямо в бэклоге

По запросу — добавление карточек сразу в бэклог (`on_board=false`), минуя доску:

9. [x] **Бэкенд** — `CardService::createInBacklog()` (on_board=false, done=false, без награды); `StoreBacklogCardRequest`; `BacklogController@store` (`POST /api/couple/backlog`, колонка-дом = первая не-терминальная); роут.
10. [x] **Фронт api+store** — `boardApi.createBacklogCard`; `store.addBacklogCard` (оптимистичный unshift, backed/мок).
11. [x] **`Backlog.vue`** — инлайн-форма «+ задача в бэклог» (видна и при пустом списке).
12. [x] **Тесты** — Pest (создание → on_board=false, не на доске, требует title, затем возврат на доску) + Vitest (store `addBacklogCard` backed/мок/пустой, форма зовёт стор).

Итог тестов: **Pest Couple 39/262**, **Vitest 159**. Документация: `docs/DATABASE.md` — `cards.on_board` + сводка API бэклога.

## Дополнение 2: отдельная страница задачи + копилинк

По запросу — шарить задачу прямой ссылкой и открывать на отдельной странице:

13. [x] **`CardDetailForm`** — тело редактора вынесено из drawer в переиспользуемый компонент (ключ по `cardId`, watch на асинхронную догрузку карточки). Drawer теперь обёртка над ним.
14. [x] **`useCardLink`** + «#id» в шапке drawer (слот `#title` BaseDrawer) — клик копирует `origin/task/:id` в буфер с фидбэком «скопировано».
15. [x] **`/task/:id`** (`pages/Task.vue`, роут внутри AppShell) — дублёр формы + секция статуса (колонка / «В бэклоге» / «Выполнено»), not-found, кнопка «Скопировать ссылку».
16. [x] **Тесты** — Vitest: `useCardLink` (формат ссылки, copy, нет clipboard), `Task.vue` (статус на доске/в бэклоге/done, not-found). Существующий `CardDetailDrawer.test` зелёный после рефактора.

Итог тестов: **Vitest 166** (24 файла), Pest Couple **39**. vue-tsc по затронутым файлам чист.

## Commit Plan

- **Коммит 1 (задачи 1–3):** `feat(backlog): API бэклога — список убранных карточек и возврат на доску`
- **Коммит 2 (задачи 4–6):** `feat(backlog): страница «Задачи» — список, возврат с выбором колонки, удаление и редактирование`
- **Коммит 3 (задачи 7–8):** `test(backlog): Pest (изоляция/возврат/done) + Vitest (store + Backlog.vue)`

> Команды php/artisan/composer — через PowerShell (Herd), не через bash PATH.
