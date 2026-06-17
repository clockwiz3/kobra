# План реализации: Логин-экран (Sanctum token) + сидирование пары

Branch: none (git.create_branches=false — остаёмся на `main`)
Created: 2026-06-16

## Settings
- Testing: yes
- Logging: verbose
- Docs: no  # warn-only; /aif-docs можно запустить вручную (схема БД не меняется)

## Roadmap Linkage
Milestone: "Couple Shard & Pairing"
Rationale: логин-экран + Sanctum-auth — прямая часть вехи «регистрация, Sanctum-auth, активация пары».

## Контекст и решения

**Проблема.** Сид-аккаунты Леся (`lesya@cuploid.app`) и Серёжа (`seryozha@cuploid.app`,
пароль `cuploid`) уже подняты `CoupleSeeder` как активная пара, но войти ими из браузера
нельзя: UI входа нет, а бэкенд имеет только `GET /api/user` (auth:sanctum) — эндпоинта логина нет.
Фронтовый канбан (`resources/js/api/board.ts`) читает Bearer-токен из `localStorage('cuploid.token')`;
без токена couple-API отдаёт 401/403 и стор падает в мок-режим.

**Решение.** Stateless token-auth через Sanctum personal access tokens (модель `User` уже
`HasApiTokens`, таблица `personal_access_tokens` мигрирована). Эндпоинт `POST /api/login` отдаёт
plain-text токен → фронт кладёт его в `localStorage('cuploid.token')` → существующий `boardApi`
сразу работает «по-настоящему» (`backed=true`). CSRF/cookie-SPA не нужны (Bearer, не сессии).

**Ключевые ограничения (из ARCHITECTURE/RULES/NFR):**
- Контроллер тонкий → доменная логика в сервисе `app/Services/Identity/AuthService.php` (модуль Identity).
- Валидация через `FormRequest`; ответы — структурированный JSON; наружу — DTO (spatie/laravel-data → TS-типы).
- **Защита от email-enumeration:** один и тот же ответ 401 «Неверный email или пароль» при любом
  провале (нет юзера / null-пароль / неверный пароль). Никаких намёков, какой именно фактор не сошёлся.
- **Rate limiting** логина (NFR security): `throttle` по ip+email, при срабатывании — WARN-лог.
- **Passwordless-ready:** `users.password` nullable — аккаунт с `password=null` НЕ может войти по паролю (→ 401).
- **Логи:** verbose, формат `[Service.method] message {context}`, env-driven `LOG_LEVEL`; **без PII ниже WARN**
  (пароль — никогда; email маскировать/не писать ниже WARN).
- **Закрытый запуск:** строим ТОЛЬКО вход для 2 известных аккаунтов. Регистрацию/онбординг/инвайт партнёра НЕ делаем.

## Commit Plan
- **Commit 1** (задачи 1–5): `feat(auth): Sanctum token login/logout — сервис, DTO, контроллер, маршруты`
- **Commit 2** (задачи 6–10): `feat(auth): фронт логина — api-клиент, store, страница, guard, logout в шапке`
- **Commit 3** (задачи 11–12): `chore(seed)+test(auth): сидирование пары + feature-тесты логина`

## Tasks

### Фаза 1 — Бэкенд: эндпоинты логина/логаута

- [x] **Task 1: `LoginRequest` (FormRequest)** — `app/Http/Requests/LoginRequest.php`.
  Правила: `email` → `required|string|email`, `password` → `required|string`. `authorize(): true` (публичный).
  Сообщения об ошибках — ru. Логи: не логировать пароль; 422-ответ формируется фреймворком (структурный JSON).

- [x] **Task 2: `AuthService` (модуль Identity)** — `app/Services/Identity/AuthService.php`.
  - `attempt(string $email, string $password): ?string` — найти `User` по email; если `null`, или `password === null`
    (passwordless), или `Hash::check` не прошёл → вернуть `null` (единый провал, без различий). Иначе создать
    Sanctum-токен (`$user->createToken('spa')`) и вернуть `plainTextToken`.
  - `logout(User $user): void` — `$user->currentAccessToken()?->delete()`.
  Логи (verbose): `[AuthService.attempt]` DEBUG на вход (без email/пароля — или email маскированный),
  INFO `login success {user_id}` при успехе, WARN `login failed` при провале (БЕЗ раскрытия фактора и без email ниже WARN);
  `[AuthService.logout]` INFO `token revoked {user_id}`. Пароль не логировать никогда.

- [x] **Task 3: DTO `UserData` + `AuthSessionData`** — `app/Data/UserData.php`, `app/Data/AuthSessionData.php`
  (spatie/laravel-data → генерация TS-типов через typescript-transformer).
  `UserData`: `id, name, email, avatar` (+ `::fromModel(User)`/`from`). `AuthSessionData`: `token: string, user: UserData`.
  Наружу отдаём DTO, не сырую модель. Логи: нет (чистые DTO).

- [x] **Task 4: `AuthController` (тонкий)** — `app/Http/Controllers/Api/AuthController.php`.
  - `login(LoginRequest $r): AuthSessionData` — `$token = AuthService::attempt(...)`; если `null` → `abort(401, 'Неверный email или пароль')`;
    иначе вернуть `new AuthSessionData($token, UserData::from($user))`.
  - `logout(Request $r): Response` — `AuthService::logout($r->user())`; вернуть `204`/JSON `{ ok: true }`.
  Логи: `[AuthController.login]` DEBUG на вход (ip), детальные исходы — в сервисе. Без доменной логики/Eloquent в контроллере.

- [x] **Task 5: Маршруты + rate limit** — `routes/api.php`.
  `POST /login` → `[AuthController, 'login']` c `throttle:5,1` (по ip+email). `POST /logout` → `[AuthController, 'logout']` за `auth:sanctum`.
  Существующий `GET /user` оставить. При желании — именованный лимитер в `bootstrap/app.php`/`AppServiceProvider` с WARN-логом на превышение.
  Логи: WARN при rate-limit (фреймворк/лимитер). (depends on 4)

### Фаза 2 — Фронтенд: вход/выход

- [x] **Task 6: Typed API-клиент авторизации** — `resources/js/api/auth.ts` (паттерн как `api/board.ts`).
  `login(email, password): Promise<AuthSession | null>` (POST `/api/login`; при ok — `localStorage.setItem('cuploid.token', token)`, вернуть сессию);
  `logout(): Promise<void>` (POST `/api/logout` с Bearer; в `finally` — `localStorage.removeItem('cuploid.token')`);
  `me(): Promise<User | null>` (GET `/api/user`). Типы `AuthSession`/`User` (использовать сгенерированные TS-типы из DTO, если доступны).
  Логи: `useLogger('authApi')` — debug на вызовы, warn на не-ok. (depends on 5)

- [x] **Task 7: Pinia-store авторизации** — `resources/js/stores/auth.ts` (`useAuthStore`).
  state: `user`, `token`; getter `isAuthenticated`; actions `login()`, `logout()`, `restore()`.
  `restore()`: прочитать токен из localStorage; если есть — `me()` для гидрации `user`, при 401 — очистить токен (просрочен/отозван).
  Логи: `useLogger('authStore')` — info на login/logout, debug на restore. (depends on 6)

- [x] **Task 8: Страница входа** — `resources/js/pages/Login.vue`.
  Форма на `BaseField` (email, password) + `BaseButton` (loading/disabled), `ThemeSwitch`, brandmark — в стиле `Waitlist.vue`/`Board.vue`
  (topbar/wrap). Submit → `authStore.login()` → `router.push('/')`; при ошибке — общий текст «Неверный email или пароль». Тексты — ru.
  Логи: `useLogger('Login')` — debug submit, warn на отказ. (depends on 7)

- [x] **Task 9: Маршрут `/login` + navigation guard + boot** — `resources/js/main.ts`.
  Добавить route `{ path: '/login', name: 'login', component: () => import('./pages/Login.vue') }`. Пометить `/` (и alias `/board`) `meta: { requiresAuth: true }`.
  `router.beforeEach`: если `to.meta.requiresAuth && !isAuthenticated` → `/login`; если `to.name==='login' && isAuthenticated` → `/`.
  Перед `app.mount` — `await authStore.restore()` (гидрация до первого рендера). Логи: минимум. (depends on 7, 8)

- [x] **Task 10: Кнопка выхода в шапке доски** — `resources/js/pages/Board.vue`.
  Добавить logout-кнопку (`BaseButton` ghost) в `.controls` рядом с аватарами Л/С и стриком; onClick → `authStore.logout()` → `router.push('/login')`.
  Аватары Л/С оставить как есть (реальные имена партнёров — отдельная задача, нужен couple-members эндпоинт).
  Логи: `useLogger('Board')` debug на logout. (depends on 7)

### Фаза 3 — Сидирование и тесты

- [x] **Task 11: Запустить сидирование пары** — `php artisan db:seed --class=CoupleSeeder`.
  Проверить: пара «Леся & Серёжа» `active`, оба члена `is_active`, профиль геймификации создан, дефолтная доска есть.
  Идемпотентно (повтор → «пара уже активна — пропуск»); в текущей dev-БД уже выполнено. Зафиксировать креды в выводе. (логи: сидер пишет через `$this->command`)

- [x] **Task 12: Feature-тесты логина** — `tests/Feature/Auth/LoginTest.php` (Pest/PHPUnit, sqlite :memory:).
  Кейсы: (a) успех → 200 + токен + `user`; (b) неверный пароль → 401 общий; (c) неизвестный email → 401 общий (анти-enumeration — тот же ответ, что (b));
  (d) passwordless (`password=null`) → 401; (e) throttle → 429 после 5 попыток; (f) `logout` отзывает токен (последующий `GET /api/couple/board` → 401);
  (g) аутентифицированный юзер с активной парой получает `GET /api/couple/board` → 200.
  Ассертить **сайд-эффекты**: строка в `personal_access_tokens` создаётся при login и удаляется при logout (не только HTTP-коды). Прогон с `LOG_LEVEL=debug`. (depends on 5, 11)

## Проверка готовности (acceptance)
- `php artisan test` — зелёный, включая новые кейсы login/logout.
- В браузере: `/` без токена → редирект на `/login`; вход `lesya@cuploid.app` / `cuploid` → дашборд тянет РЕАЛЬНУЮ доску пары (`backed=true`, не мок); logout → возврат на `/login`.
- Лог-формат `[Service.method] …`, без пароля/PII ниже WARN; неверный логин и неизвестный email дают идентичный 401.
