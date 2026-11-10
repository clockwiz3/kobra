# AGENTS.md

Карта проекта для ИИ-агентов. Обновляй при заметных изменениях структуры.

## Что это

Kobra (в коде `cuploid`): общий трекер дел для двоих, Серёжи и Леси. Пользователей ровно два, регистрации нет. Не продукт: никаких рынков, ретеншна и масштабирования. Подробнее в `.ai-factory/DESCRIPTION.md`.

## Стек

PHP 8.3 / Laravel 13 (API, Sanctum, Reverb), Vue 3 + TypeScript (Vite 8, Pinia, Tailwind v4, PWA), PostgreSQL, Eloquent, spatie/laravel-data.

## Структура

```
app/
├── Models/                    # модели пары используют CoupleScoped
├── Http/Controllers/Api/      # Couple/* — доска, колонки, карточки, бэклог
├── Http/Requests/
├── Http/Middleware/           # ResolveCoupleContext
├── Services/                  # Identity, Couples, Kanban, Gamification, Notifications, Telegram
├── Events/                    # broadcast-события на couple.{id}
├── Data/                      # DTO → TS-типы
├── Concerns/  Scopes/  Support/   # CoupleScoped, CoupleScope, CoupleContext
database/migrations/
resources/js/
├── pages/  stores/  composables/  api/  lib/
└── components/{ui, composite, kanban, layout}
routes/                        # api.php, channels.php, web.php (SPA catch-all)
tests/                         # Pest; Feature/Couple и Realtime, включая тесты изоляции
docs/
```

## Точки входа

| Файл | Что |
|------|-----|
| `routes/api.php` | API |
| `routes/channels.php` | авторизация канала `couple.{id}` |
| `resources/js/main.ts` | фронт: роутер, Pinia, тема |
| `resources/views/app.blade.php` | HTML-оболочка SPA |
| `docs/DATABASE.md` | схема и список таблиц пары |

## Доки

| Документ | Путь |
|----------|------|
| README | `README.md` |
| Дизайн-система | `docs/DESIGN-SYSTEM.md` |
| UI-компоненты | `docs/ui-library.md` |
| База данных | `docs/DATABASE.md` |
| Реалтайм | `docs/realtime.md` |
| Описание, архитектура, роадмап, правила | `.ai-factory/` |

## Правила

- **Изоляция пары:** модели пары используют `CoupleScoped`, `CoupleScope` работает fail-closed, `DB::table()` по таблицам пары не используем.
- **Реалтайм:** любое изменение данных пары рассылается событием на `couple.{id}`.
- **Геймификация** только с плюсами: без рефералов, рейтингов и штрафов за бездействие.
- **Тексты в интерфейсе:** на «ты», коротко, по-человечески. Без канцелярита и маркетинга («пространство», «тёплый», «в рамках»).
- **Shell:** зависимые команды не склеиваем через `&&`.
- **Логи:** `[Service.method] message {context}`, уровень из `LOG_LEVEL`.
- **Windows:** php, artisan и composer через Herd, запускать из PowerShell.
