<script setup lang="ts">
import { computed } from 'vue'
import type { Card } from '@/types/board'
import { useBoardStore } from '@/stores/board'
import { BaseChip } from '@/components/ui'
import { formatRange } from '@/lib/date'
import { useLogger } from '@/composables/useLogger'

// Завершение задачи теперь — перенос в терминальную колонку «Готово» (не галочка).
// .done остаётся как визуальная подсветка закрытых карточек (зачёркивание/прозрачность).
// ВАЖНО: корень <template> — РОВНО один элемент <div.task>, без ведущих комментариев/узлов:
// в dev Vue не вырезает комментарии → компонент стал бы мульти-рутовым (fragment), и
// vuedraggable терял бы контекст перетаскиваемого элемента (getUnderlyingVm → null) → дроп ломался.
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
  <div class="task" :class="{ done: card.done, 'just-closed': isJustClosed }">
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
      <div class="tt">{{ card.title }}</div>
      <!-- Все чипы — единая геометрия (.chip), различаются только содержимым и цветом/фоном (tone). -->
      <div class="tm">
        <BaseChip :tone="card.assignee === 'together' ? 'accent' : 'neutral'">{{ assigneeLabel }}</BaseChip>
        <BaseChip v-for="l in card.labels" :key="l" tone="neutral">{{ l }}</BaseChip>
        <BaseChip v-if="dueLabel" tone="warn">{{ dueLabel }}</BaseChip>
      </div>
    </div>
  </div>
</template>
