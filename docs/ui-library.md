# Cuploid — UI-библиотека компонентов

Единая библиотека Vue-компонентов фронта (`resources/js/components`), темизируемая под все три темы (`dark` / `light` / `pixel`) через CSS-переменные в `resources/css/themes.css`.

Это «уровень компонентов» поверх дизайн-системы: токены, темы и гаммы описаны в [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md) — здесь не дублируются, только используются.

## Структура (два яруса)

| Каталог | Назначение |
|---------|-----------|
| `resources/js/components/ui/` | Примитивы и общие контролы. Префикс `Base*`. Визуальные стили — классами из `themes.css` (**не** scoped: темизация централизована). Обязательная a11y. |
| `resources/js/components/composite/` | Сложносоставные доменно-агностичные виджеты (Gantt, Calendar — впереди). Собираются из примитивов `ui/`. Зависимость только в одну сторону: `composite/ → ui/`. См. `resources/js/components/composite/README.md`. |

Диалоговый слой (`BaseModal` / `ConfirmDialog` / `ConfirmRoot`) живёт в `ui/` — это базовый overlay-слой ДС, а не доменный виджет.

## Импорт (barrel)

```ts
import { BaseModal, BaseCheckbox, BaseRadioGroup, BaseRadio, ConfirmDialog } from '@/components/ui'
import { useConfirm } from '@/composables/useConfirm'
```

## Компоненты

| Компонент | Назначение | Ключевое API |
|-----------|-----------|--------------|
| `BaseChip` | Чип/тег | слот контента; props `tone: 'neutral'\|'accent'\|'warn'` (геометрия едина, меняется только цвет/фон), `solid` (алиас `accent`), `removable`; emits `remove` |
| `BaseCheckbox` | Чекбокс | `v-model:boolean`; props `label/disabled/indeterminate/name`; слот лейбла |
| `BaseRadioGroup` + `BaseRadio` | Радио-группа | группа: `v-model` (value), props `name/disabled/options`; опция: props `value/label/disabled` |
| `ColorSpectrum` | HSV-колорпикер | `v-model:string` (hex `#rrggbb`); квадрат насыщенность/яркость + ползунок оттенка + hex-поле |
| `BaseColorPicker` | Пикер рамки карточки (appearance) | `v-model:CardFrame`; заливка solid/gradient (настоящий `ColorSpectrum` + быстрые свотчи + угол/стопы), стиль `solid/dashed/dotted`, толщина 1–3, тоггл «Мерцание» (анимация любого цвета/градиента), живое превью. Рендер рамки — `composables/useCardFrame.ts` |
| `BaseModal` | Модальное окно | `v-model:boolean`; props `title/closable/closeOnOverlay/closeOnEsc/size`; слоты `header/title/default/footer`; emits `close(reason)`/`open` |
| `ConfirmDialog` | Подтверждение (композит) | `v-model:boolean`; props `title/message/confirmText/cancelText/tone/choices/defaultChoice`; emits `confirm({choice})`/`cancel` |
| `useConfirm()` | Императивный сервис подтверждения | `await confirm(opts) → { confirmed, choice? }` |

### BaseChip

Пилюля-тег (`.chip`). Геометрия (высота, контур, радиус, шрифт, паддинг) **едина во всех темах** — различаются только содержимое и цвет/фон через `tone`: `neutral` (по умолчанию), `accent` (brand-gradient заливка, = legacy-проп `solid`), `warn` (акцент дедлайна). На лице kanban-карточки исполнитель/метки/дедлайн рендерятся одним `BaseChip` с разными `tone` (без per-instance паддингов).

```vue
<BaseChip>метка</BaseChip>
<BaseChip tone="accent">♥ Вместе</BaseChip>
<BaseChip tone="warn">20 июня</BaseChip>
```

### BaseCheckbox

Нативный `<input type="checkbox">` (визуально скрыт `.sr-only`) + визуальный `.checkbox__box` → доступность и работа клавиатуры «из коробки». `indeterminate` выставляется JS-свойством (для parent-чекбоксов).

```vue
<BaseCheckbox v-model="agree" label="Согласен" />
<BaseCheckbox v-model="all" :indeterminate="some && !all">Выбрать всё</BaseCheckbox>
```

### BaseRadioGroup / BaseRadio

Группа владеет значением (`v-model`) и раздаёт контекст детям через `provide/inject`. Нативные `<input type="radio">` с общим `name` → нативная навигация стрелками. Можно рендерить декларативно (проп `options`) или вручную (`<BaseRadio>` в слоте).

```vue
<BaseRadioGroup v-model="assignee" :options="[
  { value: 'him', label: 'Он' },
  { value: 'her', label: 'Она' },
  { value: 'together', label: 'Вместе' },
]" />
```

### BaseModal

`Teleport` в `<body>`, `role="dialog"` + `aria-modal`. UX/a11y: закрытие по `Esc` и клику по overlay (настраивается), блокировка скролла body, минимальный focus-trap с возвратом фокуса инициатору, анимация уважает `prefers-reduced-motion`.

```vue
<BaseModal v-model="open" title="Настройки" size="md">
  <p>Содержимое тела…</p>
  <template #footer="{ close }">
    <BaseButton variant="ghost" @click="close">Закрыть</BaseButton>
  </template>
</BaseModal>
```

Слоты: `#title` (заголовок), `#header` (полная замена шапки), default (тело), `#footer` (ряд кнопок; слот-проп `close`).

### ConfirmDialog

Композит поверх `BaseModal`: простое да/нет ИЛИ подтверждение с выбором варианта через radio-карточки (`.choice`). Любое закрытие, кроме кнопки подтверждения (Esc / overlay / X / «Отмена»), трактуется как `cancel`.

```vue
<ConfirmDialog
  v-model="open"
  title="Удалить колонку?"
  tone="danger"
  confirm-text="Удалить"
  :choices="[
    { value: 'keep', label: 'Сохранить карточки', hint: 'перенести в соседнюю' },
    { value: 'delete', label: 'Удалить карточки' },
  ]"
  default-choice="keep"
  @confirm="onConfirm"
  @cancel="onCancel"
/>
```

### useConfirm() — императивная замена `window.confirm`

Promise-сервис поверх singleton-хоста. Требует **один** `<ConfirmRoot/>` в приложении — он уже смонтирован в `resources/js/App.vue`. Параллельные вызовы выстраиваются в очередь.

```ts
import { useConfirm } from '@/composables/useConfirm'

const { confirm } = useConfirm()

const { confirmed, choice } = await confirm({
  title: 'Удалить колонку «Идеи»?',
  tone: 'danger',
  choices: [
    { value: 'keep', label: 'Сохранить карточки' },
    { value: 'delete', label: 'Удалить карточки' },
  ],
  defaultChoice: 'keep',
})
if (confirmed) await store.removeColumn(id, { keepCards: choice === 'keep' })
```

`opts`: `title` (обяз.), `message?`, `confirmText?`, `cancelText?`, `tone?: 'default'|'danger'`, `choices?`, `defaultChoice?`.
Результат: `{ confirmed: boolean; choice?: string }` (`choice` — только при `choices`).

## Пример из проекта: удаление колонки канбана

`resources/js/components/kanban/KanbanColumn.vue` использует `useConfirm` вместо `window.confirm`. При удалении колонки с карточками и наличии соседней предлагается выбор:

- **Сохранить карточки** → `store.removeColumn(id, { keepCards: true })` переносит карточки в соседнюю колонку (relocate через `boardApi.moveCard`), затем удаляет пустую колонку.
- **Удалить карточки** → удаление каскадом.

Единственная колонка / пустая колонка → выбор не показывается (простое подтверждение).

## CSS-классы (в `themes.css`)

Новые классы overlay-слоя и контролов, темизированные под 3 темы (pixel — жёсткая геометрия, offset-тени):

`.overlay`, `.modal` (`.modal--sm/--lg`, `.modal__head/__title/__close/__body/__foot`), `.checkbox` (`.checkbox__box`), `.radio` (`.radio__dot`), `.choice-list`/`.choice` (выбираемые карточки), утилита `.sr-only`.

## Тесты

Стек: **Vitest + @vue/test-utils + jsdom**.

```bash
npm run test          # одноразовый прогон
npm run test:watch    # watch-режим
```

- Конфиг: `vitest.config.ts` (env `jsdom`, alias `@`, vue-плагин).
- Глобальный setup: `resources/js/test/setup.ts` (мок `matchMedia`).
- Тесты компонентов: `resources/js/components/ui/__tests__/`, стора: `resources/js/stores/__tests__/`.

## Как добавить новый компонент

1. Создать SFC в `ui/` (примитив) или `composite/` (составной), `script setup` + `lang="ts"`, типизированные `defineProps`/`defineModel`/`defineEmits`.
2. Визуальные стили — классами в `themes.css` с обязательным `[data-theme="pixel"]`-вариантом (и проверкой во всех трёх темах).
3. Обеспечить a11y (нативные контролы, `role`/`aria`, клавиатура, фокус).
4. Экспортировать из `resources/js/components/ui/index.ts`.
5. Добавить тест в соответствующий `__tests__/`.

## См. также

- [Дизайн-система](DESIGN-SYSTEM.md) — токены, темы (`dark`/`light`/`pixel`), гаммы.
- [База данных](DATABASE.md) — схема и реестр couple-owned таблиц.
