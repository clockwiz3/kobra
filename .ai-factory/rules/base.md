# Базовые правила проекта Cuploid

> Конвенции зафиксированы при старте проекта (greenfield, кода ещё нет). Правьте по мере роста кодовой базы; `/aif-rules` добавит area-specific правила.

## Naming Conventions

- **PHP:** PSR-12. Классы `PascalCase`, методы/переменные `camelCase`, константы `UPPER_SNAKE`.
- **Database:** таблицы `snake_case` во множественном числе (`couples`, `activity_events`), столбцы `snake_case`, внешние ключи `<entity>_id` (`couple_id`, `actor_user_id`).
- **Vue:** компоненты `PascalCase` (SFC, `WaitlistForm.vue`), composables `useXxx`, Pinia stores `useXxxStore`.
- **TypeScript:** типы/интерфейсы `PascalCase`, переменные/функции `camelCase`.

## Module Structure

- **Backend (слои):** `app/Models`, `app/Http/Controllers/Api`, `app/Http/Requests`, `app/Services`, `app/Jobs`, `app/Notifications`, `app/Policies`, `app/Data` (DTO via spatie/laravel-data).
- **Frontend:** `resources/js/{pages,components,stores,composables,api}`.
- **Tenancy:** couple-owned модели обязаны использовать trait `CoupleScoped`; полный реестр couple-owned таблиц (closed list для CI-гейта) ведётся в `docs/DATABASE.md`.

## Error Handling

- Валидация через `FormRequest`; ответы — структурированный JSON.
- Идемпотентность критичных операций: waitlist join/confirm, notification outbox, achievement unlock.
- Изоляция `couple_id`: `CoupleScope` **fail-closed** — при пустом `CoupleContext` добавляет always-false предикат и бросает исключение в non-prod/testing (no-op-on-null запрещён).
- Raw/builder-запросы по couple-owned таблицам запрещены (обходят global scope) — только через `TenantQuery`-хелпер с явным `couple_id`.

## Logging

- env-driven `LOG_LEVEL`; формат `[Service.method] message {context}`.
- Уровни: `DEBUG` (поток), `INFO` (доменные события), `WARN` (аномалии, попытки обхода изоляции, rate-limit), `ERROR` (сбои).
- Без PII в prod-логах ниже WARN; `telegram_id` маскировать.

## Testing

- Pest / PHPUnit; feature + unit.
- **Обязательный** harness изоляции tenancy (две пары A и B не видят и не пишут данные друг друга) — acceptance-гейт всего tenancy-слоя.
- Прогон тестов с `LOG_LEVEL=debug`; ассертить побочные эффекты (`activity_events`, статусы outbox, отказы Policy), а не только HTTP-коды.

## Realtime / Реактивность

> Закреплено реализацией real-time (план `plans/realtime-reactivity-dashboard.md`). Реактивность — это **дефолт**, а не опция: всё основное в продукте обязано отражаться у партнёра сразу.

- **Любая** новая мутация couple-owned данных ОБЯЗАНА вещать доменное broadcast-событие на presence-канал `couple.{id}`. Нет события — фича не завершена.
- **Payload — только spatie/laravel-data DTO** (через `broadcastWith()`), никогда не сырой Eloquent. `couple_id` — в конструкторе события (выбор канала), не в payload.
- Вещаем из **сервисного слоя** через `broadcast(...)->toOthers()`; фронт обязан прокидывать `X-Socket-ID` (иначе инициатор применит своё же событие дважды поверх optimistic-update).
- **Один presence-канал `couple.{id}`** на shard несёт и доменные события, и онлайн-присутствие. Не плодить каналы под отдельные сущности.
- Канал авторизуется **fail-closed** по членству в паре (`couple_user.is_active`) — это security-инвариант изоляции shard, как `CoupleScope`; покрывается leak-харнессом двух пар.
- Фронт применяет входящее событие **точечной мутацией стора** (reuse существующих мутаций, без API-вызова), с фолбэком `refetch` при рассинхроне/неизвестном событии.
- В тестах `BROADCAST_CONNECTION=null`; диспатч проверять через `Event::fake`/`Broadcast::fake`, а не реальным соединением.
- Канонический владелец rules-артефактов — `/aif-rules`; этот раздел добавлен в рамках фичи по явному запросу.
