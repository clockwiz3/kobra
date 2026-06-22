# Профили и внешний вид в задачах (appearance / рамка карточек)

**Дата:** 2026-06-22
**Ветка:** main (без отдельной ветки — `git.create_branches=false`)
**Тип:** feature
**Slug:** profiles-appearance

## Краткое описание

Профиль пользователя: смена отображаемого имени + персональный «внешний вид» в задачах. Первый и главный кирпич — **стилизация рамки карточки**: каждый участник пары задаёт свою рамку (сплошной цвет / 2-точечный градиент / стиль solid·dashed·dotted + толщина / готовые пресеты-пасхалки), и карточки на доске красятся этой рамкой **по исполнителю** (him/her); `together` — отдельная смешанная рамка. Для настройки — отдельный кастомный компонент `BaseColorPicker` (свотчи, hex, градиент с углом, стиль, пресеты, живое превью).

Хранение — per-user в `User.settings.appearance` (json, миграция не нужна). Отдаётся на фронт двумя путями: текущему юзеру через `/api/user`, и по исполнителю — через `board.members` (расширяем существующий `BoardMemberData`).

## Settings (настройки реализации)

- **Тесты:** да — feature-тесты бэкенда (профиль + проброс appearance) и Vitest на `BaseColorPicker` + `useCardFrame`.
- **Логирование:** verbose (DEBUG), формат `[Service.method] message {context}`, без PII ниже WARN.
- **Документация:** да — обязательный docs-чекпоинт в конце (T9): формат `settings.appearance` + `PATCH /api/user`.

## Roadmap Linkage

- **Milestone:** "Customization Engine («цифровой ад»)"
- **Rationale:** per-user appearance (рамка карточек) — первый продуктовый кирпич движка кастомизации; `BaseColorPicker` и формат `settings.appearance` переиспользуются дальше (темы/частицы/пасхалки, hearts-магазин).

## Архитектурные решения (по итогам recon)

- **appearance — per-user, не couple-owned.** `User` не использует `CoupleScoped` (нет `couple_id`); identity-данные живут вне tenancy. Дом — существующая json-колонка `users.settings` (`cast=array`, в `Fillable`). Миграция не требуется.
- **Привязка рамки — по исполнителю (him/her).** Доска уже отдаёт `board.members` со слотами `him`/`her` → именами аккаунтов (`BoardMemberData`). Добавляем туда `appearance` — карточка берёт рамку участника своего `assignee`. `together` → смешанная рамка (градиент из цветов обоих, либо `--grad`-токен как дефолт).
- **TS-типы ведутся вручную.** `spatie/typescript-transformer` настроен, но классы не помечены `#[TypeScript]` и генерации нет — фронт-типы поддерживаются руками (как уже сделано для `BoardMemberData`). Не вводим автогенерацию в этом плане.
- **`BaseColorPicker` по образцу `AccentPicker`** + конвенций `ui/*` (`defineModel`, `defineProps<{}>` generics, типизированные `defineEmits`, глобальные классы из `themes.css`, scoped только для геометрии, a11y + `onClickOutside`, `useLogger`).
- **Градиентная рамка** — CSS-трюк `border: Npx solid transparent` + `linear-gradient(...) padding-box, linear-gradient(angle, from, to) border-box`. Ограничение: `border-box`-градиент не пунктирится → для `dashed/dotted` + градиента фолбэк на сплошной `border-color = from` (задокументировать). Анимированные пресеты — `@keyframes` с `@media (prefers-reduced-motion: reduce)`.

### Формат `User.settings.appearance`

```jsonc
{
  "card": {
    "paint": { "kind": "solid", "color": "#e64ab4" },
    // либо: { "kind": "gradient", "from": "#e64ab4", "to": "#9b1deb", "angle": 135 }
    "style": "dashed",        // solid | dashed | dotted
    "width": 2,                // 1..3
    "animated": false          // «мерцание»: заливка (сплошная ИЛИ градиент) → бегущий блик
  }
}
```

## Tasks

### Фаза 1 — Бэкенд: персист + API + проброс
- [x] **T1** — Профиль (имя) + appearance в `User.settings`, `PATCH /api/user`. Новое: `UpdateProfileRequest`, `ProfileService`, `ProfileController`; роут `PATCH /api/user` (за `auth:sanctum`, вне `couple.context`); `GET /api/user` → `UserData`; `UserData` += `appearance`.
- [x] **T2** — Проброс `appearance` исполнителя в `board.members` (`BoardMemberData` += `appearance`; `BoardData::membersFor`). *(blocked by T1)*

### Фаза 2 — Фронт: контракт данных
- [x] **T3** — TS-типы (`CardFrame`/`UserAppearance`), `ApiBoardMember.appearance`, сторы (board.members.appearance + mock-демо), `AuthUser.appearance`, новый `api/profile.ts` (`updateProfile` → `PATCH /api/user`). *(blocked by T1, T2)*

### Фаза 3 — Кастомный пикер (ядро)
- [x] **T4** — `BaseColorPicker.vue`: solid + gradient(2 точки, угол) + стиль/толщина + пресеты/пасхалки + живое превью; экспорт в `ui/index.ts`. *(blocked by T3)*

### Фаза 4 — Рендер рамки
- [x] **T5** — `useCardFrame` (CardFrame→CSS, `frameForAssignee`, together-микс) + применение на `KanbanCard.vue` и `Backlog.vue`; классы пресетов + keyframes в `themes.css`. *(blocked by T3)*

### Фаза 5 — Страница профиля
- [x] **T6** — `pages/Profile.vue` (имя + редактор appearance + живое превью + сохранение → обновление `auth.user` и своего слота в `board.members`); роут в `main.ts`; пункт «Профиль» в `lib/nav.ts`. *(blocked by T4, T5)*

### Фаза 6 — Тесты
- [x] **T7** — Бэкенд-тесты: `UpdateProfileTest` (имя, appearance, проброс в `board.members`, валидация, auth) + дополнить `BoardTest`. *(blocked by T1, T2)*
- [x] **T8** — Фронт-тесты: `BaseColorPicker.test.ts` + `useCardFrame.test.ts`; не ломать существующие 166. *(blocked by T4, T5)*

### Фаза 7 — Документация
- [x] **T9** — Docs-чекпоинт: формат `settings.appearance`, `PATCH /api/user`, заметка в `docs/DATABASE.md`, упоминание `BaseColorPicker`. *(blocked by T6, T7, T8)*

## Commit Plan

- **Checkpoint 1 — бэкенд (после T1–T2):** `feat(profile): per-user appearance в User.settings + PATCH /api/user; проброс в board.members`
- **Checkpoint 2 — фронт-данные + пикер (после T3–T4):** `feat(ui): BaseColorPicker (цвет/градиент/стиль/пресеты) + контракт appearance на фронте`
- **Checkpoint 3 — рамка + профиль (после T5–T6):** `feat(board): рамка карточек по исполнителю + страница Профиль`
- **Checkpoint 4 — тесты + доки (после T7–T9):** `test+docs(profile): тесты appearance/пикера + документация формата и API`

## Риски / заметки

- Пунктирный градиент кроссбраузерно не рисуется через `border-box` — для `dashed/dotted` + gradient фолбэк на сплошной `border-color`. Задокументировать в T9.
- Перекраска доски после сохранения профиля — обновлять свой слот в `board.members` локально (без полного `fetchBoard`).
- Анимированные пресеты — обязательно уважать `prefers-reduced-motion` (как в существующих анимациях карточек/скелетона).
- `array_replace_recursive` при мерже `settings.appearance` — не затирать остальной `settings`.
