# План: Реактивность (Reverb + Echo), раздел «Дашборд», правило реактивности

**Тип:** feature · **Ветка:** не создаётся (`git.create_branches=false`, остаёмся на `main`) · **Создан:** 2026-06-22
**Slug:** `realtime-reactivity-dashboard`

## Краткое описание

Поднять real-time синхронизацию пары end-to-end: **Laravel Reverb + Laravel Echo**, единый **presence-канал `couple.{id}`**. Изменения одного партнёра (смена статуса/перемещение карточек, создание/правка/удаление карточек и колонок, бэклог, обновление профиля/appearance) **сразу видны** второму партнёру. Плюс новый **раздел «Дашборд»** (главная-сводка на `/`, доска переезжает на `/board`) и закрепление **правила**: всё основное в будущем поддерживает реактивность.

Стек был заранее выбран в `DESCRIPTION.md`/`ARCHITECTURE.md` (Reverb + приватные/presence каналы `couple.{id}`), но ещё **не реализован**: нет `laravel/reverb`, `config/broadcasting.php`, `routes/channels.php`, Echo на фронте. Бэклог и профиль/appearance уже сделаны (`plans/backlog-section.md`, `plans/profiles-appearance.md`) — здесь добавляем им реактивность.

## Settings

- **Testing:** да — Pest (авторизация канала, диспатч событий, **tenancy-изоляция канала** как acceptance-гейт) + Vitest (`useCoupleChannel`, apply-методы стора, `Home.vue`, обновлённый nav). Прогон с `LOG_LEVEL=debug`, ассерты побочных эффектов.
- **Logging:** verbose (DEBUG). Формат `[Service.method] message {context}`; без PII ниже WARN; `WARN` на отказ авторизации канала (попытка обхода изоляции) и на desync→refetch.
- **Docs:** да — **обязательный docs-чекпоинт** на завершении (через `/aif-docs`).

## Roadmap Linkage

- **Milestone:** "Kanban (совместные задачи)"
- **Rationale:** веха явно включает «real-time через Reverb (приватный канал `couple.{id}`)». Доски/колонки/карточки уже есть — этот план закрывает real-time-часть вехи и распространяет её на профиль/бэклог; presence + дашборд — естественная надстройка.

## Архитектурные решения (по итогам recon)

- **Один presence-канал `couple.{id}` на всё.** Presence-канал в Reverb — это приватный канал с member-инфо. На нём же вещаем доменные события → одна подписка `Echo.join('couple.'+id)` несёт и онлайн-статус, и патчи. Отдельный private-канал не нужен.
- **Авторизация канала — fail-closed по членству** (`couple_user.is_active`). Чужой couple → 403. Это **security-инвариант изоляции shard** (как и `CoupleScope`), поэтому покрывается leak-харнессом двух пар.
- **Payload событий — только spatie/laravel-data DTO** (`CardData`/`ColumnData`/slot+appearance), никогда не сырой Eloquent (анти-паттерн из `ARCHITECTURE.md`, принцип #6). `couple_id` идёт в конструктор события для выбора канала, но в payload не утекает.
- **`ShouldBroadcastNow`** (не `ShouldBroadcast`): dev-очередь `QUEUE_CONNECTION=sync` → вещаем синхронно и предсказуемо; на проде можно перевести в очередь. Reverb-сервер на Windows-деве работает (`reverb:start`, pcntl не нужен — в отличие от Horizon).
- **`broadcast()->toOthers()`** + проброс `X-Socket-ID` из Echo во все fetch-запросы → инициатор не применяет своё же событие дважды (на фронте уже есть optimistic-update).
- **Применение на фронте — точечные патчи** (reuse мутаций `board` store без API-вызова), фолбэк `fetchBoard()+fetchBacklog()` при рассинхроне/неизвестном событии.
- **Дашборд — главная-сводка + переезд роутов.** Сейчас `dashboard` занят доской. Новый `pages/Home.vue` встаёт на `/` (name `dashboard`), доска → `/board` (name `board`). Геймификация (стрик/XP/ачивки) ещё не построена → на дашборде плейсхолдеры + live-лента активности из realtime-событий.

### Контракт broadcast-событий (`broadcastAs` → payload)

| Событие | Когда | Payload |
|---|---|---|
| `CardBroadcast` | create / update / move / returnToBoard | `{ action, card: CardData }` |
| `CardRemoved` | destroy | `{ cardId, columnId }` |
| `ColumnBroadcast` | addColumn / renameColumn | `{ action, column: ColumnData }` |
| `ColumnsReordered` | reorderColumns | `{ orderedIds: int[] }` |
| `ColumnRemoved` | removeColumn | `{ columnId }` |
| `MemberAppearanceUpdated` | updateProfile (appearance/имя) | `{ slot, appearance }` |

Presence (`here`/`joining`/`leaving`) → онлайн-статус партнёра, без отдельного доменного события.

### Известные ограничения / заметки

- `ProfileService` работает **вне** `couple.context` → `couple_id` для канала резолвим явным запросом `couple_user` по `$user->id` (та же логика, что в middleware).
- `ColumnController::reorder` и `destroy` возвращают 204 → пейлоады событий собираются вручную (`orderedIds` / `{columnId}`), не из DTO ответа.
- `CardData` не содержит `coupleId` (и не должен) → сервис берёт `couple_id` из модели до сериализации.
- Схема БД **не меняется** — миграций нет (appearance уже в `users.settings`, real-time состояния не персистим).

## Tasks

> Нумерация ниже = ID задач в трекере (`TaskList`). Логи обязательны в каждой задаче (см. описания задач).

### Фаза 0 — Инфраструктура broadcasting (бэкенд)
1. [x] **Установить Reverb + broadcasting-скаффолд** — `composer require laravel/reverb` + `php artisan install:broadcasting` (PowerShell/Herd); `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, регистрация провайдера; `.env`/`.env.example` (`BROADCAST_CONNECTION=reverb`, `REVERB_*`, `VITE_REVERB_*`). Broadcasting подключён через `->withBroadcasting(channels, middleware: auth:sanctum)` в `bootstrap/app.php` (SPA-токен, не сессия). Frontend-npm (laravel-echo/pusher-js) отложен на T5.
2. [x] **Авторизация presence-канала `couple.{coupleId}`** — `routes/channels.php`, fail-closed по `couple_user.is_active`, member-payload (slot/id/name); WARN на отказ. *(security-инвариант)*

### Фаза 1 — Доменные события + диспатч (бэкенд)
3. [x] **Broadcast-события** — `app/Events/*`: `CardBroadcast`, `CardRemoved`, `ColumnBroadcast`, `ColumnsReordered`, `ColumnRemoved`, `MemberAppearanceUpdated`; `ShouldBroadcastNow`, `PresenceChannel('couple.'.$id)`, `broadcastAs`, DTO-payload через `broadcastWith()`.
4. [x] **Диспатч из сервисов** — `CardService`/`BoardService`/`ProfileService`: `broadcast(...)->toOthers()` в точках мутаций; `couple_id` из модели (для профиля — резолв через `couple_user`). Добавлено: `CardData.onBoard` (реконсиляция локации), `CardService::delete()`, `moveCore()` (без двойного 'moved' при возврате), `BoardService` инжектит `CoupleContext`. Все 55 feature-тестов зелёные. *(blocked by 3)*

### Фаза 2 — Echo + применение на фронте
5. **Echo-бутстрап + `X-Socket-ID`** — `resources/js/bootstrap.ts` (Reverb + кастомный Bearer-authorizer на `/broadcasting/auth`), init в `main.ts`; добавить `X-Socket-ID` в `authHeaders()` всех fetch-модулей. *(blocked by 1)*
6. **board store: apply-методы** — `applyRemoteCard/CardRemoved/Column/ColumnsReordered/ColumnRemoved` (reuse мутаций, без API), фолбэк refetch при desync.
7. **`useCoupleChannel`** — `Echo.join('couple.'+id)`, presence→`partnerOnline`, `.listen` доменных событий→`board.applyRemote*`; subscribe после `fetchBoard`, unsubscribe при logout. *(blocked by 2, 5, 6)*

### Фаза 3 — Presence
8. **Индикатор «партнёр в сети»** — из `useCoupleChannel.partnerOnline`, в шапке `AppShell` и на дашборде. *(blocked by 7)*

### Фаза 4 — Раздел «Дашборд»
9. **Рефактор роутов** — Дашборд `pages/Home.vue` на `/` (name `dashboard`), доска → `/board` (name `board`); правка `lib/nav.ts` (+ пункт «Главная»).
10. **`Home.vue`** — приветствие пары, плитки-счётчики (`totalCards`/колонки/бэклог), индикатор онлайн, live-лента, быстрые ссылки, плейсхолдеры геймификации; skeleton/empty. *(blocked by 8, 9, 11)*
11. **Буфер ленты активности** — ring-buffer последних realtime-событий → человекочитаемый ru-текст для ленты дашборда. *(blocked by 7)*

### Фаза 5 — Правило
12. **Правило реактивности** — раздел «Realtime / Реактивность» в `.ai-factory/rules/base.md` + апдейт real-time-секции `ARCHITECTURE.md` (с «(позже)» на «реализовано»). *(blocked by 4; владелец rules — `/aif-rules`, правим по явному запросу)*

### Фаза 6 — Тесты
13. **Pest** — авторизация канала (член/чужой/**изоляция двух пар**) + `Event::fake` диспатч на корректный `PresenceChannel` с DTO; профиль вне контекста. *(blocked by 2, 4)*
14. **Vitest** — `useCoupleChannel` (событие→стор, presence, fallback, буфер), apply-методы стора, `Home.vue`, обновлённые `NavMenu`/`AppShell` тесты. *(blocked by 6, 7, 9, 10)*

### Фаза 7 — Документация
15. **Docs-чекпоинт** — `docs/realtime.md` (канал, таблица событий, `toOthers`/`X-Socket-ID`, `.env`, `reverb:start`), заметка в `docs/DATABASE.md` (миграций нет), апдейт nav-доки про «Главная»/`/board`. *(blocked by 12, 13, 14)*

## Commit Plan

- **Коммит 1 (задачи 1–4):** `feat(realtime): Reverb + presence-канал couple.{id}, DTO broadcast-события и диспатч из сервисов`
- **Коммит 2 (задачи 5–8):** `feat(realtime): Echo на фронте, применение событий в board store, useCoupleChannel + онлайн-статус`
- **Коммит 3 (задачи 9–12):** `feat(dashboard): раздел «Главная» (сводка + live-лента), переезд доски на /board; правило реактивности`
- **Коммит 4 (задачи 13–15):** `test+docs(realtime): Pest (изоляция канала/диспатч) + Vitest + документация realtime-контракта`

> Команды php/artisan/composer/`reverb:start` — через PowerShell (Herd), не через bash PATH.
