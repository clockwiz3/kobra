# Architecture: Modular Monolith (couple-as-shard multi-tenancy)

## Overview

Cuploid — единый Laravel-монолит (API) + Vue 3 PWA, организованный как **Modular Monolith**: один деплой, но чёткие границы доменных модулей (Identity, Couples, Gamification, Waitlist, Notifications, Telegram, Customization). Внутри каждого модуля — слои (Controller → Service → Model). Поверх всего лежит **сквозной слой изоляции tenancy**: каждая пара — приватный shard, и доступ к couple-данным возможен только в рамках текущего шарда.

Выбор обусловлен: соло-разработчик, низкая операционная сложность важнее независимого деплоя, домен средней сложности с понятными границами, и возможность позже вынести модуль в отдельный сервис без переписывания. Микросервисы/DDD были бы оверинжинирингом, чистый Layered — слишком плоским для tenancy-инварианта.

## Decision Rationale

- **Project type:** приватный таск-трекер для пар (multi-tenant SaaS, tenant = пара)
- **Tech stack:** PHP 8.3 / Laravel 11 (API), Vue 3 + TypeScript (PWA), PostgreSQL, Eloquent
- **Key factor:** изоляция данных пары (shard) — security-инвариант; модульность даёт границы доменов при простой эксплуатации монолита

## Folder Structure

```
app/
├── Models/                      # Eloquent; couple-owned модели используют trait CoupleScoped
├── Http/
│   ├── Controllers/Api/
│   │   ├── Couple/              # couple-scoped (за middleware ResolveCoupleContext + Policy)
│   │   ├── Waitlist/            # pre-account, ВНЕ tenancy
│   │   └── TelegramWebhookController.php
│   ├── Requests/               # FormRequest-валидация
│   └── Middleware/ResolveCoupleContext.php
├── Services/                    # доменная логика по модулям
│   ├── Couples/                # CoupleInvitationService, CoupleDeletionService (lifecycle шарда)
│   ├── Gamification/           # GamificationService (XP/стрик/ачивки)
│   ├── Waitlist/               # WaitlistService (double-opt-in)
│   ├── Notifications/          # NotificationDispatcher + Channels (email/telegram)
│   └── Telegram/               # TelegramService (Bot API)
├── Jobs/                        # очереди; tenant-aware (явный couple_id)
├── Policies/                    # CouplePolicy (членство в паре)
├── Data/                        # DTO (spatie/laravel-data) → генерация TS-типов
├── Concerns/CoupleScoped.php    # trait: global scope + авто-стамп couple_id
├── Scopes/CoupleScope.php       # fail-closed global scope
└── Support/CoupleContext.php    # request-fresh носитель текущего couple_id

database/migrations/             # пиннированный порядок; couple_id FK ON DELETE CASCADE
routes/{api.php, channels.php}   # channels.php: приватные каналы couple.{id}
resources/js/{pages,components,stores,composables,api}   # Vue 3 + TS frontend
docs/DATABASE.md                 # ERD + closed-list couple-owned таблиц (источник правды)
tests/Feature/Tenancy/           # leak-harness (acceptance-гейт изоляции)
```

## Dependency Rules

- ✅ Controller → Service → Model. Контроллеры тонкие; доменная логика — в сервисах.
- ✅ Модули общаются через публичные сервисы/доменные события, не лезут во внутренности друг друга.
- ✅ Couple-данные читаются/пишутся только через модели с `CoupleScoped` при установленном `CoupleContext`.
- ❌ Контроллер не вызывает Eloquent для доменной логики напрямую (только тонкие read-resource ок).
- ❌ Raw `DB::table()` / builder по couple-owned таблицам (обходит global scope) — запрещено; только `TenantQuery`-хелпер с явным `couple_id`.
- ❌ Waitlist/Telegram-linking/Identity **не** зависят от tenancy (pre-/cross-shard данные, без `couple_id`).

## Layer/Module Communication

- **Синхронно:** Controller → Service (внутри запроса). Сервис принимает **явный** `Couple`/`couple_id`, не читает tenant из ambient-состояния для решения «что вернуть».
- **Асинхронно:** доменные события → Jobs (очередь). Каждая tenant-aware джоба получает `couple_id` в конструкторе, биндит/чистит `CoupleContext` в try/finally; `Queue::before` забывает stale-контекст между джобами.
- **Уведомления:** доменные события кладут запись в outbox (`notifications`), воркер доставляет по каналам (email/telegram).
- **Real-time (позже):** Reverb, приватные каналы `couple.{coupleId}`, авторизация по членству в паре.

## Key Principles

1. **Tenancy — security-инвариант, защита в слоях:** явный `couple_id` в сервисе (первичная граница) + `CoupleScoped`/`CoupleScope` (backstop) + FK CASCADE + Policy + CI leak-тесты. Ни один слой не является единственной гарантией.
2. **Fail-closed:** при пустом `CoupleContext` scope добавляет always-false предикат и бросает исключение в non-prod/testing. No-op-on-null запрещён.
3. **Context request-fresh:** `CoupleContext` наполняется middleware из активного членства, никогда не кэшируется на токене/сессии.
4. **Один общий gamification-профиль на пару** (UNIQUE `couple_id`); атрибуция партнёров выводится из `activity_events.actor_user_id`, наружу — только комбинированные тоталы. Геймификация upside-only.
5. **Граница pre-account:** вайтлист и линковка Telegram живут вне shard-системы.
6. **DTO-контракты:** наружу — `spatie/laravel-data` DTO (→ TS-типы), не сырые Eloquent-модели (особенно в broadcast-payload).

## Code Examples

### Trait изоляции (couple-owned модель)
```php
// app/Concerns/CoupleScoped.php
trait CoupleScoped
{
    protected static function bootCoupleScoped(): void
    {
        static::addGlobalScope(new CoupleScope());
        static::creating(function ($model) {
            $id = CoupleContext::id();              // throw, если null
            $model->couple_id = $id;                // перетираем клиентское значение
        });
    }
}
```

### Fail-closed global scope
```php
// app/Scopes/CoupleScope.php
final class CoupleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = CoupleContext::idOrNull();
        if ($id === null) {
            if (! app()->isProduction()) {
                throw new MissingCoupleContextException($model::class); // ловим в CI
            }
            $builder->whereRaw('1 = 0');            // в prod — ничего не отдаём (fail-closed)
            return;
        }
        $builder->where($model->getTable().'.couple_id', $id);
    }
}
```

### Сервис принимает явный couple_id (не ambient)
```php
// app/Services/Gamification/GamificationService.php
public function recordEvent(Couple $couple, string $type, ?User $actor, array $payload = []): void
{
    abort_unless($couple->status === CoupleStatus::Active, 409); // начисления только в активном шарде
    // ... append activity_event, idempotent XP drip, inverted streak, unlock achievements
}
```

## Anti-Patterns

- ❌ Чтение tenant из ambient-состояния внутри сервиса вместо явного аргумента `couple_id`.
- ❌ Доменная логика в контроллере вместо сервиса.
- ❌ Кэширование приватных couple-ответов в service worker / по `user_id`-ключу (утечка между шардами).
- ❌ Per-user/кросс-парные соревновательные механики, рефералы, лидерборды (нарушают приватность шарда).
- ❌ Стрик/ачивки, наказывающие за бездействие одного партнёра (токсично для романтического продукта).
- ❌ Новая couple-таблица без `couple_id`/`CoupleScoped` и без записи в closed-list `docs/DATABASE.md`.
