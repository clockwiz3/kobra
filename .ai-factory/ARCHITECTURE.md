# Архитектура

Один Laravel-монолит отдаёт API, Vue 3 PWA работает поверх него. Код разложен по доменам (Identity, Couples, Kanban, Gamification, Notifications, Telegram), внутри каждого цепочка Controller → Service → Model.

Главное правило: **данные пары изолированы**. Все таблицы пары содержат `couple_id`, и прочитать или записать чужое нельзя даже по ошибке. Пользователей у нас двое, так что это с запасом. Но слой написан, покрыт тестами и ничего не стоит, поэтому держим.

## Где что лежит

```
app/
├── Models/                      # couple-owned модели используют трейт CoupleScoped
├── Http/
│   ├── Controllers/Api/
│   │   ├── Couple/              # всё про пару: доска, колонки, карточки, бэклог
│   │   ├── AuthController.php   # вход и выход
│   │   ├── ProfileController.php
│   │   └── TelegramWebhookController.php
│   ├── Requests/                # валидация (FormRequest)
│   └── Middleware/ResolveCoupleContext.php
├── Services/
│   ├── Identity/                # AuthService, ProfileService
│   ├── Couples/                 # приглашение и удаление пары
│   ├── Kanban/                  # BoardService, CardService
│   ├── Gamification/            # заготовка: XP, стрик, ачивки
│   ├── Notifications/           # outbox и каналы email/telegram
│   └── Telegram/
├── Events/                      # broadcast-события на канал couple.{id}
├── Broadcasting/CoupleChannel.php
├── Data/                        # DTO (spatie/laravel-data), из них генерируются TS-типы
├── Concerns/CoupleScoped.php    # глобальный scope и автопростановка couple_id
├── Scopes/CoupleScope.php
└── Support/CoupleContext.php    # текущий couple_id на время запроса

resources/js/{pages,components,stores,composables,api,lib}
docs/DATABASE.md                 # схема и список couple-owned таблиц
tests/Feature/{Couple,Realtime}/  # в т.ч. тесты изоляции: пары A и B не видят друг друга
```

## Как слои зависят друг от друга

- Контроллеры тонкие, логика живёт в сервисах.
- Сервис получает пару **явным аргументом** и не вытаскивает её из глобального состояния.
- Данные пары читаются только через модели с `CoupleScoped` при выставленном `CoupleContext`.
- `DB::table()` по таблицам пары не используем: он обходит scope.
- Вход, профиль и Telegram-линковка живут вне пары и без `couple_id`.

## Реалтайм

Reverb + Echo, один presence-канал `couple.{coupleId}` на пару: по нему идут и события, и статус «в сети». Сервис после изменения делает `broadcast(...)->toOthers()`, в payload кладётся DTO. Фронт применяет событие точечно к стору. Подробнее в `docs/realtime.md`.

## Принципы

1. **Изоляция в несколько слоёв:** явный `couple_id` в сервисе, `CoupleScope` как страховка, FK `ON DELETE CASCADE`, тесты на утечки. Ни один слой не единственная защита.
2. **Fail-closed:** если `CoupleContext` пуст, scope в dev и тестах бросает исключение, а на проде возвращает пустой результат. Тихо отдать всё нельзя.
3. **Контекст живёт один запрос:** middleware заполняет его из активного членства в паре и нигде не кэширует.
4. **Наружу только DTO,** без сырых Eloquent-моделей, особенно в broadcast.
5. **Геймификация общая на пару** (один профиль) и только с плюсами.

## Примеры

### Трейт изоляции
```php
// app/Concerns/CoupleScoped.php
trait CoupleScoped
{
    protected static function bootCoupleScoped(): void
    {
        static::addGlobalScope(new CoupleScope());
        static::creating(function ($model) {
            $model->couple_id = CoupleContext::id(); // бросит, если контекста нет
        });
    }
}
```

### Fail-closed scope
```php
// app/Scopes/CoupleScope.php
final class CoupleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = CoupleContext::idOrNull();
        if ($id === null) {
            if (! app()->isProduction()) {
                throw new MissingCoupleContextException($model::class);
            }
            $builder->whereRaw('1 = 0');
            return;
        }
        $builder->where($model->getTable().'.couple_id', $id);
    }
}
```

## Чего не делать

- Логику в контроллере вместо сервиса.
- Брать пару из глобального состояния внутри сервиса.
- Кэшировать ответы с данными пары в service worker.
- Делать соревнование между нами, рейтинги или штрафы за пропуски.
- Заводить новую таблицу пары без `couple_id` и `CoupleScoped` и не вписать её в `docs/DATABASE.md`.
- Менять данные пары без broadcast-события.
