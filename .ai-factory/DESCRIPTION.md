# Cuploid

## Обзор

Приватный романтический таск-трекер для пар. Ниша рынка СНГ, где нет крупных аналогов уровня Cupla / Homsy. Каждая пара получает изолированное приватное пространство для совместного планирования задач, списков желаний и тёплой геймификации отношений. Продукт делается соло-разработчиком (для себя и партнёра) с продуктовой амбицией.

## Core Features

- **Kanban** — совместные задачи пары с real-time синхронизацией (партнёр двигает карточку — ты видишь сразу).
- **Wishlists** (вишлисты) — списки желаний со «скрытой бронью» подарков партнёром.
- **Геймификация ретеншна** — общий стрик пары, XP/уровни, приватные achievements и пасхалки, внутренняя валюта hearts. Только upside-механики (см. Architecture Notes).
- **Кастомизация («цифровой ад»)** — цветовые темы, анимации, частицы, пасхалки; движок тем и пресетов.
- **Telegram-бот** — уведомления и привязка аккаунта/вайтлист-записи.
- **Mobile** — PWA-first, далее упаковка в Capacitor (App Store / Google Play).
- **Этап 0 (текущий)** — DB-фундамент + вайтлист с double-opt-in (без рефералов).

## Tech Stack

- **Programming language:** PHP 8.3 (backend), TypeScript (frontend)
- **Framework:** Laravel 13 (API) + Vue 3 (SPA/PWA)
- **Database:** PostgreSQL
- **ORM:** Eloquent
- **Auth:** Laravel Sanctum (passwordless-ready, Telegram-link)
- **Realtime:** Laravel Reverb + Laravel Echo (приватные каналы `couple.{id}`) — добавляется на milestone Kanban
- **Queues:** Redis + `queue:work` (Horizon — только на прод-Linux; на Windows-деве недоступен: ext-pcntl). Сейчас dev-дефолт `QUEUE_CONNECTION=sync`
- **Frontend tooling:** Vite 8, Pinia, Vue Router, Tailwind CSS v4 (`@theme`), vite-plugin-pwa
- **Type bridge:** spatie/laravel-data + typescript-transformer (end-to-end типы PHP → TS)
- **Integrations:** Telegram Bot API, SMTP/email
- **Mobile:** PWA → Capacitor

## Architecture Notes

> Подробные правила архитектуры — в `.ai-factory/ARCHITECTURE.md`. Pattern: **Modular Monolith** (couple-as-shard multi-tenancy).

Ключевой инвариант: **пара = изолированный shard (tenant)**. Single-DB, shared-schema, row-level multi-tenancy по дискриминатору `couple_id`; строка `couples` — это сам shard. Детали — в `ARCHITECTURE.md`.

- **Изоляция в слоях:** явный `couple_id` на уровне сервиса + `CoupleScoped`/`CoupleScope` (**fail-closed**) + FK `ON DELETE CASCADE` + Policy + CI leak-тесты. `CoupleContext` request-fresh, не кэшируется на токене. Очереди — явный tenant per job.
- **Геймификация — приватная и кооперативная:** один общий профиль на пару; **нет** рефералов, лидербордов, кросс-парных/соревновательных механик. Валентность **upside-only**: стрик растёт, если активен хотя бы один партнёр, и никогда не наказывает за бездействие; return-триггеры — warmth, не guilt.
- **Вайтлист — pre-account, вне shard-системы:** чистый double-opt-in + опц. Telegram, без рефералов.

## Non-Functional Requirements

- **Logging:** verbose, env-driven `LOG_LEVEL`, формат `[Service.method] message {context}`; без PII в prod-логах ниже WARN.
- **Tenancy/Isolation:** изоляция данных пары трактуется как security-инвариант (fail-closed scope, FK cascade, Policy, два-парный leak-harness как acceptance-гейт).
- **Error handling:** структурированные JSON-ошибки; идемпотентность критичных операций (waitlist join/confirm, notification outbox, achievement unlock).
- **Security:** Sanctum, rate limiting, защита от email-enumeration на вайтлисте, секрет Telegram-webhook, минимальные/несенситивные payload в outbox.
- **Privacy:** приватность пары; удаление общих данных — через grace-window + уведомление партнёра; TTL-purge архивных шардов.
- **Testing:** ключевые сценарии + обязательный harness изоляции tenancy.
