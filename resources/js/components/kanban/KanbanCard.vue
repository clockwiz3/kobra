<script setup lang="ts">
import { computed, ref, type Component } from 'vue'
import { ChevronUp, ChevronDown, ChevronsUp, Minus, Dots } from '@vicons/tabler'
import { onClickOutside } from '@vueuse/core'
import type { Card, CardPriority } from '@/types/board'
import { useBoardStore } from '@/stores/board'
import { BaseChip } from '@/components/ui'
import { formatRange, isOverdue } from '@/lib/date'
import { useLogger } from '@/composables/useLogger'

// Завершение задачи теперь — перенос в терминальную колонку «Готово» (не галочка).
// .done остаётся как визуальная подсветка закрытых карточек (зачёркивание/прозрачность).
// ВАЖНО: корень <template> — РОВНО один элемент <div.task>, без ведущих комментариев/узлов:
// в dev Vue не вырезает комментарии → компонент стал бы мульти-рутовым (fragment), и
// vuedraggable терял бы контекст перетаскиваемого элемента (getUnderlyingVm → null) → дроп ломался.
// (Teleport меню — ВНУТРИ .task, поэтому единственный корень сохраняется.)
const props = defineProps<{ card: Card }>()
const emit = defineEmits<{ remove: []; open: [] }>()
const store = useBoardStore()
const log = useLogger('KanbanCard')

// Ретро-анимация закрытия (pixel): .just-closed выводится из стора (а не из локального состояния),
// поэтому переживает пересоздание инстанса карточки при cross-column DnD. store.justClosedCardId
// выставляется при переносе в терминал (done false→true) и сам гаснет через ~700мс → на загрузке/
// перерисовке уже-закрытых карточек анимация не играет.
const isJustClosed = computed(() => store.justClosedCardId === props.card.id)

const assigneeLabel = computed(() => {
  return { him: 'Он', her: 'Она', together: '♥ Вместе' }[props.card.assignee]
})

// дедлайн: одиночная дата или диапазон (ru), пусто — если не задан
const dueLabel = computed(() => formatRange(props.card.dueAt, props.card.dueEndAt))

// Дедлайн-чип: дату и время объединяем в ОДИН чип. Только дата → warn; только время → neutral;
// дата+время → «дата время» (warn).
const deadlineChip = computed<{ text: string; tone: 'warn' | 'neutral' } | null>(() => {
  const date = dueLabel.value
  const time = props.card.dueTime
  if (date && time) return { text: `${date} ${time}`, tone: 'warn' }
  if (date) return { text: date, tone: 'warn' }
  if (time) return { text: time, tone: 'neutral' }
  return null
})

// Приоритет → шеврон в превью: вниз (низкий), минус (обычный), вверх (высокий), двойной (критический).
const PRIORITY_META: Record<CardPriority, { icon: Component; cls: string; label: string }> = {
  low: { icon: ChevronDown, cls: 'is-low', label: 'Низкий приоритет' },
  normal: { icon: Minus, cls: 'is-normal', label: 'Обычный приоритет' },
  high: { icon: ChevronUp, cls: 'is-high', label: 'Высокий приоритет' },
  critical: { icon: ChevronsUp, cls: 'is-critical', label: 'Критический приоритет' },
}
const prio = computed(() => PRIORITY_META[props.card.priority ?? 'normal'])

// — Меню карточки (квадрат приоритета при наведении → троеточие → меню). Телепортируется,
//   чтобы поповер не клиппился overflow колонки; позиция считается от бейджа. —
const menuOpen = ref(false)
const badgeEl = ref<HTMLElement | null>(null)
const menuEl = ref<HTMLElement | null>(null)
const menuPos = ref<{ top: number; left: number }>({ top: 0, left: 0 })

function openMenu(): void {
  const el = badgeEl.value
  if (!el) return
  const r = el.getBoundingClientRect()
  const MENU_W = 224
  // выпадает вниз, правым краём к бейджу; кламп от левого края вьюпорта
  menuPos.value = { top: Math.round(r.bottom + 4), left: Math.round(Math.max(8, r.right - MENU_W)) }
  menuOpen.value = true
  log.debug('menu-open', { id: props.card.id })
}
function closeMenu(): void { menuOpen.value = false }
function toggleMenu(): void { menuOpen.value ? closeMenu() : openMenu() }

onClickOutside(menuEl, () => closeMenu(), { ignore: [badgeEl] })

function onMoveTop(): void { void store.moveCardToEdge(props.card.id, 'top'); closeMenu() }
function onMoveBottom(): void { void store.moveCardToEdge(props.card.id, 'bottom'); closeMenu() }
function onRemoveFromBoard(): void { void store.removeFromBoard(props.card.id); closeMenu() }

// data-атрибуты для CSS-фильтра доски (читают только card — это внутренний рендер KanbanCard,
// vnode айтема vuedraggable не трогаем). overdue согласован со стором (cardMatchesFilter).
const hasDue = computed(() => props.card.dueAt != null)
const overdue = computed(() => props.card.dueAt != null && !props.card.done && isOverdue(props.card.dueAt, props.card.dueEndAt))

// Открытие панели по клику на тело карточки. Отличаем клик от drag: vuedraggable
// инициирует перетаскивание с того же тела, поэтому игнорируем, если был сдвиг
// указателя или активен drag (store.dragging).
let downX = 0
let downY = 0
let moved = false
function onPointerdown(e: PointerEvent) {
  downX = e.clientX
  downY = e.clientY
  moved = false
}
function onPointermove(e: PointerEvent) {
  if (Math.abs(e.clientX - downX) > 5 || Math.abs(e.clientY - downY) > 5) moved = true
}
function openCard() {
  if (moved || store.dragging) return
  log.debug('open', { id: props.card.id })
  emit('open')
}
</script>

<template>
  <div
    class="task"
    :class="{ done: card.done, 'just-closed': isJustClosed }"
    :data-assignee="card.assignee"
    :data-has-due="hasDue || null"
    :data-overdue="overdue || null"
  >
    <div
      class="task__body"
      role="button"
      tabindex="0"
      :aria-label="`Открыть карточку: ${card.title}`"
      style="flex: 1; min-width: 0"
      @pointerdown="onPointerdown"
      @pointermove="onPointermove"
      @click="openCard"
      @keydown.enter.space.prevent="openCard"
    >
      <!-- Название + квадрат приоритета СПРАВА (при наведении → троеточие, открывает меню карточки). -->
      <div class="task__title">
        <div class="tt">{{ card.title }}</div>
        <button
          ref="badgeEl"
          type="button"
          class="prio"
          :class="[prio.cls, { 'is-active': menuOpen }]"
          :title="prio.label"
          :aria-label="prio.label"
          aria-haspopup="menu"
          :aria-expanded="menuOpen"
          @click.stop="toggleMenu"
          @pointerdown.stop
        >
          <span class="prio__icon"><component :is="prio.icon" /></span>
          <span class="prio__kebab"><Dots /></span>
        </button>
      </div>
      <!-- Все чипы — единая геометрия (.chip), различаются только содержимым и цветом/фоном (tone). -->
      <div class="tm">
        <BaseChip :tone="card.assignee === 'together' ? 'accent' : 'neutral'">{{ assigneeLabel }}</BaseChip>
        <BaseChip v-for="l in card.labels" :key="l" tone="neutral">{{ l }}</BaseChip>
        <BaseChip v-if="deadlineChip" :tone="deadlineChip.tone">{{ deadlineChip.text }}</BaseChip>
      </div>
    </div>

    <!-- Меню карточки: телепортируем в .ds-root (иначе клиппится overflow колонки); фикс-позиция от бейджа. -->
    <Teleport to=".ds-root">
      <div
        v-if="menuOpen"
        ref="menuEl"
        class="card-menu"
        role="menu"
        :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px' }"
        @click.stop
        @pointerdown.stop
      >
        <button type="button" class="card-menu__item" role="menuitem" @click="onMoveTop">Перенести вверх колонки</button>
        <button type="button" class="card-menu__item" role="menuitem" @click="onMoveBottom">Перенести вниз колонки</button>
        <button type="button" class="card-menu__item" role="menuitem" @click="onRemoveFromBoard">Убрать с доски</button>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Карточка кликабельна (открывает панель параметров) — курсор-указатель. */
.task { cursor: pointer; }

/* Название + индикатор приоритета справа; длинный заголовок переносится/обрезается, иконка не сжимается. */
.task__title { display: flex; align-items: flex-start; gap: 7px; }
.task__title .tt {
  flex: 1; min-width: 0;
  /* превью названия — максимум 3 строки, дальше многоточие */
  display: -webkit-box; -webkit-box-orient: vertical;
  -webkit-line-clamp: 3; line-clamp: 3;
  overflow: hidden;
}

/* Индикатор приоритета = мягкий квадрат-кнопка: показывает уровень, при наведении/открытом меню
   крестфейдом превращается в троеточие (меню карточки). Тинт-фон по уровню (токены → все темы). */
.prio {
  display: grid; place-items: center; flex: none; margin-top: 1px;
  width: 22px; height: 22px; padding: 0;
  border: 1px solid transparent; border-radius: var(--r-sm);
  color: var(--text-muted); cursor: pointer;
  transition: filter .15s var(--ease, ease);
}
.prio:hover { filter: brightness(1.08); }
[data-theme="pixel"] .prio { border-width: 2px; }
.prio__icon, .prio__kebab {
  grid-area: 1 / 1; display: inline-grid; place-items: center;
  /* плавный кроссфейд-морф (мягче и не так резко) */
  transition: opacity .2s var(--ease, ease), transform .2s var(--ease, ease);
}
.prio__kebab { opacity: 0; transform: scale(.8); }
/* троеточие проявляется при наведении на ВСЮ карточку (или когда меню открыто) */
.task:hover .prio__icon,
.prio.is-active .prio__icon { opacity: 0; transform: scale(.8); }
.task:hover .prio__kebab,
.prio.is-active .prio__kebab { opacity: 1; transform: scale(1); }
.prio :deep(svg) { width: 15px; height: 15px; }

/* светофор-эскалация: низкий — зелёный, обычный — нейтральный, высокий — янтарь, критический — красный */
.prio.is-low {
  color: var(--success);
  background: color-mix(in srgb, var(--success) 15%, transparent);
  border-color: color-mix(in srgb, var(--success) 34%, transparent);
}
.prio.is-normal {
  color: var(--text-muted);
  background: color-mix(in srgb, var(--text) 9%, transparent);
  border-color: var(--border-strong);
}
.prio.is-high {
  color: var(--warning);
  background: color-mix(in srgb, var(--warning) 16%, transparent);
  border-color: color-mix(in srgb, var(--warning) 34%, transparent);
}
.prio.is-critical {
  color: var(--error);
  background: color-mix(in srgb, var(--error) 16%, transparent);
  border-color: color-mix(in srgb, var(--error) 36%, transparent);
}

/* Меню карточки (телепортируется в .ds-root → не клиппится overflow колонки). */
.card-menu {
  position: fixed; z-index: 200; min-width: 224px;
  background: var(--surface); border: 1px solid var(--border-strong);
  border-radius: var(--r-md); box-shadow: var(--shadow), var(--inner-glow);
  padding: 6px; display: flex; flex-direction: column;
}
[data-theme="pixel"] .card-menu { border-width: 3px; box-shadow: var(--shadow); }
.card-menu__item {
  text-align: left; padding: 9px 10px;
  border: 0; background: transparent; color: var(--text);
  font-family: var(--font-body); font-size: 13px;
  border-radius: var(--r-sm); cursor: pointer;
  transition: background .14s var(--ease, ease);
}
.card-menu__item:hover { background: var(--surface-2); }
[data-theme="pixel"] .card-menu__item { font-size: 11px; }
</style>
