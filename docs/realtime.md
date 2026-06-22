# Real-time (реактивность пары)

Изменения одного партнёра отражаются у второго **сразу** — без перезагрузки. Это не опция, а **дефолт продукта**: правило «реактивность по умолчанию» зафиксировано в `.ai-factory/rules/base.md`.

## Транспорт

- **Сервер:** [Laravel Reverb](https://laravel.com/docs/reverb) (WebSocket). Запуск dev-сервера:
  ```powershell
  php artisan reverb:start
  ```
  На Windows-деве работает штатно (в отличие от Horizon — `ext-pcntl` не нужен).
- **Клиент:** Laravel Echo + pusher-js, единая инициализация в `resources/js/bootstrap.ts` (`initEcho()` вызывается из `main.ts` после восстановления сессии; `teardownEcho()` — на logout).
- **Очередь:** события — `ShouldBroadcastNow` (вещаются синхронно), т.к. dev-дефолт `QUEUE_CONNECTION=sync`. На проде можно перевести в очередь.

## Канал

Один **presence-канал на пару**: `couple.{coupleId}` (`coupleId` = id shard'а, **не** id доски). Он несёт:

1. **доменные события** (карточки/колонки/профиль), и
2. **онлайн-присутствие** партнёра (`here`/`joining`/`leaving` → индикатор «партнёр в сети»).

Отдельные каналы под сущности не заводим.

### Авторизация — fail-closed (security-инвариант)

`routes/channels.php` → `App\Broadcasting\CoupleChannel::join()`. Доступ только члену активной пары (`couple_user.is_active`); на чужой `couple.{id}` попасть нельзя — это часть изоляции shard, как `CoupleScope`. Покрыто leak-харнессом двух пар (`tests/Feature/Realtime/ChannelAuthTest.php`).

Эндпоинт `/broadcasting/auth` поднят под `auth:sanctum` (`bootstrap/app.php` → `withBroadcasting`): SPA авторизуется **Bearer-токеном**, а не сессией (кастомный authorizer в `bootstrap.ts`).

## Контракт событий

Все события — в `app/Events/*`, `implements ShouldBroadcastNow`, `broadcastOn()` → `PresenceChannel('couple.'.$coupleId)`. **Payload — только spatie/laravel-data DTO** (через `broadcastWith()`), никогда не сырой Eloquent. На фронте имя слушается с ведущей точкой (`.CardBroadcast`).

| Событие (`broadcastAs`) | Когда (сервис) | Payload | Применение на фронте |
|---|---|---|---|
| `CardBroadcast` | `CardService` create / createInBacklog / update / move / returnToBoard | `{ action, card: CardData }` | `board.applyRemoteCard(action, card)` — реконсиляция локации по `card.onBoard` |
| `CardRemoved` | `CardService::delete` | `{ cardId, columnId }` | `board.applyRemoteCardRemoved(cardId)` |
| `ColumnBroadcast` | `BoardService` addColumn / renameColumn | `{ action, column: ColumnData }` | `board.applyRemoteColumn(action, column)` |
| `ColumnsReordered` | `BoardService::reorderColumns` | `{ orderedIds: int[] }` | `board.applyRemoteColumnsReordered(orderedIds)` |
| `ColumnRemoved` | `BoardService::removeColumn` | `{ columnId }` | `board.applyRemoteColumnRemoved(columnId)` |
| `MemberAppearanceUpdated` | `ProfileService::updateProfile` (appearance) | `{ slot, appearance }` | `board.setMemberAppearance(slot, appearance)` |

`action` для карточки: `created` · `backlogCreated` · `updated` · `moved` · `returned`. Поле `CardData.onBoard` позволяет фронту разместить карточку (доска/бэклог) из одного payload — отдельные события под «убрать с доски»/«вернуть» не нужны.

`ProfileService` работает **вне** `couple.context`, поэтому `coupleId` и слот резолвятся явным запросом `couple_user` (как в `ResolveCoupleContext`).

## toOthers + X-Socket-ID

Вещаем через `broadcast(...)->toOthers()`: инициатор НЕ получает своё же событие (у него уже применён optimistic-update). Для этого фронт прокидывает заголовок **`X-Socket-Id`** (= `Echo.socketId()`) во все мутирующие fetch-запросы (`api/auth.ts`, `api/board.ts`, `api/profile.ts`).

## Применение на фронте

`resources/js/composables/useCoupleChannel.ts` — подписка (`Echo.join`), маршрутизация событий в `board`-стор и онлайн-статус. Подписка стартует из `AppShell` по `board.coupleId` (после первого `fetchBoard`), снимается на unmount/logout.

Стор применяет события **точечными мутациями** (reuse существующей логики, без API-вызова). При рассинхроне (неизвестная колонка/событие) — тихий фолбэк `fetchBoard()`.

Лента активности пары (`recentEvents` в `useCoupleChannel`) и онлайн-индикатор питают раздел **«Главная»** (`pages/Home.vue`, маршрут `/`). Доска при этом переехала на `/board`.

## Конфигурация (.env)

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cuploid-local
REVERB_APP_KEY=cuploid-local-key
REVERB_APP_SECRET=cuploid-local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Полный набор — в `.env.example`.

## Схема БД

Real-time **не добавляет миграций**: состояние присутствия эфемерно, доменные данные уже есть (карточки/колонки/`users.settings.appearance`). См. [DATABASE.md](DATABASE.md).

## Тесты

- Бэкенд: `tests/Feature/Realtime/ChannelAuthTest.php` (авторизация + изоляция двух пар), `BroadcastDispatchTest.php` (диспатч на корректный канал с DTO). В тест-окружении `BROADCAST_CONNECTION=null`; диспатч проверяется через `Event::fake`, без реального соединения.
- Фронт: `composables/__tests__/useCoupleChannel.test.ts`, `stores/__tests__/board.realtime.test.ts`, `pages/__tests__/Home.test.ts`.

## Добавляя новую couple-фичу

Любая новая мутация couple-данных **обязана** вещать DTO-событие на `couple.{id}`, а фронт — применять его точечной мутацией стора. Подробнее — раздел «Realtime / Реактивность» в `.ai-factory/rules/base.md`.
