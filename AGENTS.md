# AGENTS.md

> Карта проекта для AI-агентов. Поддерживайте файл в актуальном состоянии при значимых изменениях структуры. Раздел «Документация» ведёт `/aif-docs`.

## Обзор проекта

Cuploid — приватный романтический таск-трекер для пар (рынок СНГ). Каждая пара — изолированный shard (tenant). Подробности — в `.ai-factory/DESCRIPTION.md`.

## Tech Stack

- **Programming language:** PHP 8.3 (backend), TypeScript (frontend)
- **Framework:** Laravel 11 (API) + Vue 3 (SPA/PWA)
- **Database:** PostgreSQL
- **ORM:** Eloquent

## Project Structure

> Каркас заскаффолжен (задача #1): Laravel 13 + Vue 3 + Vite + Tailwind v4 + PWA. Приложение отдаётся на `http://cuploid.test/`. Доменные подкаталоги (Services/Policies/Data и т.п.) добавляются по мере задач.

```
cuploid/
├── app/                      # Laravel backend (планируется)
│   ├── Models/               # Eloquent-модели (couple-owned используют trait CoupleScoped)
│   ├── Http/Controllers/Api/ # API-контроллеры
│   ├── Http/Requests/        # FormRequest-валидация
│   ├── Http/Middleware/      # ResolveCoupleContext (tenant-резолв)
│   ├── Services/             # Доменные сервисы (GamificationService, CoupleInvitationService)
│   ├── Jobs/                 # Очереди (SendNotificationJob)
│   ├── Policies/             # Авторизация (CouplePolicy)
│   ├── Data/                 # DTO (spatie/laravel-data) → TS-типы
│   ├── Concerns/             # CoupleScoped trait
│   └── Scopes/               # CoupleScope (fail-closed tenancy)
├── database/migrations/      # Миграции (пиннированный порядок, couple_id FK CASCADE)
├── resources/js/             # Vue 3 + TS frontend (планируется)
│   ├── pages/  components/  stores/  composables/  api/
├── routes/                   # api.php, channels.php (Reverb couple.{id})
├── tests/                    # Pest/PHPUnit (Feature/Tenancy — leak-harness)
├── docs/                     # docs/DATABASE.md (ERD + closed-list couple-owned таблиц)
├── .ai-factory/              # AI Factory контекст (см. ниже)
└── .claude/                  # Агентские скиллы и настройки
```

## Key Entry Points

| Файл | Назначение |
|------|------------|
| `composer.json` | PHP-зависимости (Laravel 13, Sanctum, spatie/laravel-data) |
| `package.json` | JS-зависимости (Vue 3, Vite 8, Tailwind v4, vite-plugin-pwa) |
| `routes/web.php` | SPA-host (catch-all → `app.blade.php`) |
| `routes/api.php` | Точка входа API (Sanctum) |
| `resources/js/main.ts` | Точка входа фронтенда (Vue + Router + Pinia + useTheme) |
| `resources/views/app.blade.php` | HTML-host SPA (`#app.ds-root`, шрифты, @vite) |
| `docs/DATABASE.md` | ERD и реестр couple-owned таблиц (источник правды по схеме) |
| `.ai-factory/plans/foundation-waitlist.md` | Текущий план (этап 0: БД + вайтлист) |

## Documentation

| Документ | Путь | Описание |
|----------|------|----------|
| README | README.md | Лендинг-страница проекта (ведётся `/aif-docs`) |

## AI Context Files

| Файл | Назначение |
|------|------------|
| AGENTS.md | Карта проекта для агентов (этот файл) |
| .ai-factory/DESCRIPTION.md | Спецификация: стек, фичи, NFR |
| .ai-factory/ARCHITECTURE.md | Архитектура: couple-as-shard, слои, правила |
| .ai-factory/rules/base.md | Базовые конвенции кода |
| .ai-factory/plans/ | Планы фич (`/aif-plan`) |

## Agent Rules

- **Изоляция (tenancy) — инвариант:** любая couple-owned модель использует `CoupleScoped`; запросы scoped по `couple_id`; `CoupleScope` fail-closed; raw `DB::table()` по couple-таблицам запрещён. Реестр couple-owned таблиц — в `docs/DATABASE.md`.
- **Геймификация — upside-only, без социалки:** нет рефералов/лидербордов/кросс-парных механик; стрик не наказывает за бездействие.
- **Декомпозиция shell-команд:** не объединяйте зависимые команды через `&&`.
  - Неправильно: `git checkout main && git pull`
  - Правильно: сначала `git checkout main`, затем `git pull origin main`
- **Логирование:** формат `[Service.method] message {context}`, уровни через `LOG_LEVEL`.
