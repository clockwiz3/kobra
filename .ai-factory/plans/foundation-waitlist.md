# Implementation Plan: Cuploid — DB-фундамент + Вайтлист

Branch: none (репозиторий ещё не под git)
Created: 2026-06-14

## Контекст и стек (консультация)

**Продукт:** приватный романтический таск-трекер для пар (аналог Cupla/Homsy для рынка СНГ). Канбан, вишлисты, мобилка, широкая кастомизация (темы, анимации, частицы, пасхалки — «цифровой ад»), Telegram-уведомления, геймификация ретеншна.

**Стек (зафиксировано):** **Laravel** (API, Sanctum, Reverb для real-time позже, Horizon/Redis очереди) + **Vue 3 + TypeScript** (Vite, Pinia, Tailwind). Мобилка — **PWA-first → Capacitor** (одна кодовая база). End-to-end типы через `spatie/laravel-data` + `typescript-transformer`. Подробности консультации — в истории сессии; «масштаб» здесь нерелевантен, не переписываем на TS/React.

## Settings
- Testing: yes (изоляция tenancy + ключевые сценарии)
- Logging: verbose (env-driven `LOG_LEVEL`, формат `[Service.method] message {context}`)
- Docs: yes (docs/DATABASE.md как часть этапа; docs-чекпойнт в /aif-implement)

## Roadmap Linkage
Milestone: "none"
Rationale: ROADMAP.md пока нет; этот план — фундамент (этап 0).

---

## 🔑 Архитектурный инвариант: couple = изолированный SHARD

> Решение владельца: **каждая зарегистрированная пара — изолированный шард (tenant)**. Геймификация и все couple-данные приватны для двух партнёров. **Нет рефералов, нет кросс-парных лидербордов, нет любых социальных/виральных/соревновательных механик между парами.**

Технически это **single-DB, shared-schema, row-level multi-tenancy** с дискриминатором `couple_id`. Строка `couples` — это и есть шард.

### Изоляция (защита в слоях, не один ambient-scope)
1. **Первичная граница — явный `couple_id` на уровне сервиса/репозитория.** `GamificationService` и любой couple-запрос принимают `Couple`/`couple_id` и строят `->where('couple_id', $id)` явно. Ни один метод не берёт tenant из ambient-состояния, чтобы решить *что вернуть*.
2. **Defense-in-depth:** (a) `CoupleScoped` trait + `CoupleScope` global scope — авто-`WHERE couple_id` и авто-стамп при инсерте; (b) FK `NOT NULL` + `ON DELETE CASCADE` на БД; (c) Policy на каждом couple-эндпоинте (юзер — член резолвнутой пары); (d) CI leak-тесты.
3. **`CoupleScope` FAILS CLOSED:** при `null`-контексте — always-false предикат **и** throw в non-prod/testing. No-op-on-null **запрещён** (это была бы fail-open утечка).
4. **Tenant-контекст request-fresh:** `CoupleContext` singleton наполняется `ResolveCoupleContext` middleware (после `auth:sanctum`) из `couple_user` WHERE активное членство. **Никогда** не кэшируется на токене/сессии.
5. **Одна активная пара на юзера — DB-инвариант:** `couple_user.is_active` + PARTIAL UNIQUE INDEX(`user_id`) WHERE `is_active`. Активация — в транзакции с `SELECT…FOR UPDATE`.
6. **Очереди — явный tenant per job:** джоба принимает `couple_id` в конструкторе, bind/clear `CoupleContext` в try/finally, `Queue::before` забывает stale-контекст между джобами на переиспользуемом воркере.
7. **Reverb-каналы (контракт сейчас, реализация позже):** приватные каналы по `couple.{coupleId}` (не по `user_id`), авторизация — членство в паре; payload — минимальные DTO, не сырые модели.
8. **Raw/builder-запросы по couple-таблицам запрещены** CI grep-гейтом (`DB::table(` обходит global scope); неизбежные агрегаты — через единый `TenantQuery`-хелпер с явным `couple_id = ?`.

### Геймификация (приватная, кооперативная, без токсичности)
- **Один общий профиль на пару** (`gamification_profiles`, UNIQUE `couple_id`): общие xp/level/hearts/streak. Полиморфный owner — убран. Нет per-user конкурирующего счёта.
- **Атрибуция партнёров — выводится**, не хранится: из `activity_events.actor_user_id`, только server-side; наружу — только **комбинированные** тоталы и тёплая «together»-сводка, никогда не per-partner ранкинг. (Таблицы `couple_member_stats`/`couple_streak_days`/`hearts_transactions`/`quests` — **не создаём**, отложены.)
- **Валентность инвертирована в gain/celebration (red-team HIGH, этика романтического приложения):** стрик растёт, если активен **хотя бы один** партнёр в локальный день; «оба активны» — бонус сверху, **никогда** не штраф. Стрик **не ломается** из-за бездействия одного и не приписывается человеку. `longest_streak` сохраняется; сброс — тихий, без «вы потеряли». Соло-действия всегда дают полный XP. Ратио/«Perfectly Balanced» ачивки — убраны.
- **Начисления только при `couples.status='active'`.** Pending (один юзер до партнёра) — gamification-inert. Archived — заморожено.
- **Return-триггеры — warmth, не guilt:** «партнёр добавил желание», «разблокирована секретная ачивка», годовщина/«в этот день», вехи. Partner-directed «стрик под угрозой» — **убран**.

### Жизненный цикл шарда
- **Создание/активация:** инвайт создаёт `couples(status=pending)`; accept активирует (`status=active`) в транзакции + эагерно создаёт единственный `gamification_profiles`.
- **Роспуск/выход → archive** (freeze + retain, soft-delete). **Удаление аккаунта → hard wipe** через `CoupleDeletionService` (`couple->forceDelete()` каскадит детей). По умолчанию — grace-window + уведомление партнёра (см. Q3).
- **Ре-пейринг → свежий шард** (без переноса XP/стрика/ачивок); reconcile-реактивация архива — только за явным grace-window флагом.

### Вайтлист — НЕ tenant
Pre-account данные, вне shard-системы. Реферал-машинерия удалена целиком (это и был единственный кросс-сущностный соц-граф). Остаётся чистый double-opt-in: join → confirm → опц. Telegram. Позиция (если показываем) — **выводится статично** (`COUNT(confirmed WHERE id<=this.id)`), не хранится, ничьим сигнапом не двигается.

---

## Открытые вопросы (взяты безопасные дефолты — скажи, если менять)
- **Q1 — Hearts на запуске:** дефолт — **отложить** (колонка `hearts_balance` дремлет, без earn/spend-петли), пока нет магазина кастомизации. Валюта без траты хуже отсутствия валюты.
- **Q2 — Позиция вайтлиста:** дефолт — статичное **«#N в списке»** (никогда не двигается). Альтернатива — нейтральное «N в ожидании».
- **Q3 — Удаление аккаунта** уничтожает общий шард (данные второго партнёра). Дефолт — **grace-window + уведомление партнёра**, затем purge (GDPR-safer), а не мгновенный wipe.
- **Q4 — Хранение архивных шардов:** дефолт — добавить конфигурируемый **TTL-purge** архивов + явное «удалить наши воспоминания».
- **Q5 — Reconcile тех же двоих:** дефолт — **свежий шард всегда**; reconcile архива — за тестируемым флагом.
- **Q6 — Привлечение:** удаление рефералов убрало единственный органический канал роста. Pairing-инвайт (T4) — легитимный directive-compliant pull. Стратегия привлечения (контент/сторы/CIS-комьюнити) — **отдельный воркстрим вне модели данных**, не «протащенный обратно реферал».
- **Q7 — Тезис ретеншна непроверен:** дефолт — собрать тонкий инструментированный спайн, догфудить своей парой, гейтить quests/seasonal/hearts на реальных D7/D30.

---

## Дизайн-система (импортирована из Claude Design)

Готова и лежит в репо — `docs/DESIGN-SYSTEM.md`. Источник правды: `resources/css/themes.css` (3 темы `dark`/`light`/`pixel` + 5 гамм через `[data-accent]`, default `honey`; Fraunces+Inter; `prefers-reduced-motion` встроен). Vue-обвязка: `resources/js/composables/useTheme.ts` + `resources/js/components/ui/*` (BaseButton/BaseField/BaseCard/BaseBadge/Chip/ThemeSwitch/AccentPicker) + `tailwind.config.cjs` (токены→утилиты). Оригинальный showcase — `docs/design/`.

Задача #1 подключает её (themes.css + шрифты + `ds-root` + useTheme, мерж tailwind-конфига), задача #9 строит лендинг на её компонентах/токенах. Темы+гаммы = зерно milestone **Customization Engine** (позже per-couple persist в `couples.settings`).

## Commit Plan
- **Commit 1** (после задач 1–2): `chore: scaffold Laravel+Vue+TS foundation & DB schema doc`
- **Commit 2** (после задач 3–6): `feat: core foundation + couple-as-shard tenancy (users, couples, gamification, notifications, telegram)`
- **Commit 3** (после задач 7–9): `feat: waitlist double-opt-in (model, API, landing PWA)`
- **Commit 4** (после задач 10–11): `feat: telegram bot + notification dispatch (warmth triggers)`
- **Commit 5** (после задач 12–13): `feat: private in-shard gamification surface + tenancy isolation tests`

## Tasks

### Phase 1: Foundation & Scaffolding
- [x] Task 1: Scaffold Laravel API + Vue 3 + TS (Vite, Pinia, Tailwind, PWA), Sanctum, Postgres, очереди, laravel-data+TS-types, structured logging baseline. ✅ Laravel 13 + Vue 3.5 + Vite 8 + Tailwind v4 + PWA, дизайн-система подключена, отдаётся на cuploid.test. Отклонения: Horizon отложен (Windows ext-pcntl), Reverb → milestone Kanban, Postgres-БД создаётся в задаче #3 (сейчас session/cache на file).
- [x] Task 2: Author full DB schema doc `docs/DATABASE.md` — ERD; детально couple-as-shard фундамент + список couple-owned таблиц (closed list для CI-гейта); документированы (без миграций) будущие фичи и отложенные таблицы (couple_member_stats/streak_days/hearts_transactions/quests). (depends on 1)
<!-- Commit checkpoint: tasks 1-2 -->

### Phase 2: Core Foundation + Tenancy (migrate)
- [x] Task 3: Migrations + model `users` (+ telegram-поля, passwordless-ready) + Sanctum + фабрики. НЕ CoupleScoped (cross-shard identity). (depends on 1, 2)
- [x] Task 4: **Пары + пейринг = владелец жизненного цикла шарда** — couples(+archived_at/SoftDeletes/cache_version, created_by SET NULL), couple_user(+is_active + PARTIAL UNIQUE(user_id) WHERE is_active + INDEX(user_id)), couple_invitations; CoupleInvitationService (активация в транзакции + эагерный gamification_profiles), CoupleDeletionService, ResolveCoupleContext middleware + CoupleContext singleton. (depends on 3)
- [x] Task 5: **Геймификация-фундамент на couple-as-shard (spine)** — couple-scoped миграции (gamification_profiles UNIQUE couple_id, activity_events couple_id NOT NULL + actor_user_id, achievement_unlocks UNIQUE(couple_id,achievement_id)), CoupleScoped trait + CoupleScope (fail-closed), GamificationService (явный couple, инвертированный стрик, idempotent XP/ачивки), сидер приватных/secret ачивок, CI leak-гейты. (depends on 3, 4)
- [x] Task 6: notifications (outbox, polymorphic notifiable + morphMap + nullable couple_id) + telegram_link_tokens + NotificationDispatcher/Channel-контракты. (depends on 3) ✅ таблица `notification_outbox` (переименована из `notifications` во избежание коллизии с Notifiable), enforceMorphMap user/waitlist.
<!-- Commit checkpoint: tasks 3-6 -->

### Phase 3: Waitlist (double-opt-in, без рефералов)
- [x] Task 7: **Вайтлист-модель** — убраны referral_code/referred_by/position; email/status/confirmation_token/telegram_id/utm/locale + INDEX(status,id); НЕ CoupleScoped; статичная позиция выводится на чтении. (depends on 2)
- [x] Task 8: **Вайтлист API** — POST /join (idempotent dedup, double-opt-in, опц. Telegram), GET /confirm/{token} (idempotent), опц. GET /status/{token} (confirmed + static_position). Вне couple-context. (depends on 7, 6)
- [x] Task 9: **Лендинг вайтлиста (Vue, PWA)** — confirmation/anticipation success-state, опц. «#N в списке», CTA Telegram, тизер частиц (prefers-reduced-motion); реферал-UI/лидерборд удалены. (depends on 8)
<!-- Commit checkpoint: tasks 7-9 -->

### Phase 4: Telegram Bot + Notifications
- [x] Task 10: Telegram-бот — POST /telegram/webhook (secret), /start <link_token> линковка, TelegramService.sendMessage, artisan set-webhook. (depends on 6)
- [x] Task 11: **Доставка из outbox — warmth, не guilt** — SendNotificationJob (idempotent/retriable) + Email/Telegram каналы, подтверждение вайтлиста + позитивные couple-триггеры; джобы с явным couple_id + bind/clear CoupleContext. (depends on 6, 8, 10, 4)
<!-- Commit checkpoint: tasks 10-11 -->

### Phase 5: In-shard Gamification + Tenancy Tests
- [ ] Task 12: **Приватная in-shard геймификация (active couple)** — удалить геймификацию вайтлиста; couple-scoped эндпоинты GET /api/couple/gamification и /achievements (комбинированные тоталы, is_secret скрыты), check-in эмитит activity_events, позитивные триггеры через T11. (depends on 4, 5, 8)
- [ ] Task 13: **Тесты — harness изоляции tenancy + корректность** — двух-парный leak-harness (acceptance-гейт), fail-closed SQL-проба, FK-cascade интроспекция, гонка двух активных пар, job-tenancy, mass-assignment, account-deletion, Reverb channel auth, re-pair, инвертированный стрик + waitlist/outbox/telegram/achievement-идемпотентность. (depends on 4, 5, 8, 10, 11, 12)
<!-- Commit checkpoint: tasks 12-13 -->

## Future (документируем сейчас, реализуем позже — всё couple-scoped)
- **Kanban:** boards, columns, cards, assignees, comments, labels — каждая таблица с `couple_id` (CoupleScoped), real-time на Reverb (канал `couple.{id}`).
- **Wishlists:** wishlists, items, reservations — couple-scoped; «скрытая бронь» подарка партнёром.
- **Кастомизация («цифровой ад»):** themes, theme_assets, couple_theme, animation/particle presets, easter_eggs — движок тем + пресеты; sink для hearts.
- **Геймификация v2 (fast-follow, гейт по retention):** couple_streak_days (материализация), hearts_transactions (ledger) + магазин тем за hearts, кооперативные quests/seasonal (upside-only), daily check-in, годовщины/«в этот день». Всё — приватно внутри шарда, без социалки.
- **Mobile:** обернуть PWA в Capacitor → App Store / Google Play.
- **Привлечение (отдельный воркстрим, Q6):** контент/сторы/CIS-комьюнити — НЕ реферал.
