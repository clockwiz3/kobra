<script setup lang="ts">
import { ref, computed } from 'vue'
import draggable from 'vuedraggable'
import type { Card, Column } from '@/types/board'
import KanbanCard from './KanbanCard.vue'
import { useBoardStore } from '@/stores/board'
import { useLogger } from '@/composables/useLogger'
import { useConfirm } from '@/composables/useConfirm'
import { prefersReducedMotion } from '@/composables/useTheme'

const props = defineProps<{ column: Column }>()
const store = useBoardStore()
const log = useLogger('KanbanColumn')
const { confirm } = useConfirm()
const reduced = prefersReducedMotion()

// есть ли куда переносить карточки при удалении (нужна хотя бы одна соседняя колонка)
const hasSiblings = computed(() => store.columns.length > 1)

// число карточек, проходящих фильтр доски (бейдж показывает видимые, не все)
const visibleCount = computed(() => props.column.cards.filter((c) => store.cardMatchesFilter(c)).length)

const newTitle = ref('')
const editing = ref(false)
const editTitle = ref(props.column.title)

function addCard() {
  if (newTitle.value.trim() === '') return
  store.addCard(props.column.id, newTitle.value)
  newTitle.value = ''
  log.debug('add-card', { columnId: props.column.id })
}

function startRename() {
  editTitle.value = props.column.title
  editing.value = true
}

function saveRename() {
  store.renameColumn(props.column.id, editTitle.value)
  editing.value = false
  log.debug('rename', { columnId: props.column.id })
}

async function remove() {
  const hasCards = props.column.cards.length > 0
  const offerChoice = hasSiblings.value && hasCards

  const res = await confirm(
    offerChoice
      ? {
          title: `Удалить колонку «${props.column.title}»?`,
          tone: 'danger',
          confirmText: 'Удалить колонку',
          defaultChoice: 'keep',
          choices: [
            { value: 'keep', label: 'Сохранить карточки', hint: 'перенести в соседнюю колонку' },
            { value: 'delete', label: 'Удалить карточки', hint: 'удалить вместе с колонкой' },
          ],
        }
      : {
          title: `Удалить колонку «${props.column.title}»?`,
          tone: 'danger',
          confirmText: 'Удалить колонку',
          message: hasCards ? 'Колонка и все её карточки будут удалены.' : 'Колонка будет удалена.',
        },
  )
  if (!res.confirmed) return

  await store.removeColumn(props.column.id, { keepCards: res.choice === 'keep' })
  log.debug('remove', { columnId: props.column.id, choice: res.choice })
}

function onCardChange(e: { added?: { element: Card; newIndex: number }; moved?: { element: Card; newIndex: number } }) {
  const change = e.added ?? e.moved
  if (change) store.persistCardMove(String(change.element.id), props.column.id, change.newIndex)
  log.debug('card-drop', { columnId: props.column.id })
}

// [FIX] флаг drag поднимается на источнике (start/end приходят в исходную колонку),
// списки карточек на это время клиппят overflow — иначе FLIP-трансформ карточки
// при переносе вылезает за content-box принимающей колонки и мигают скроллбары
function onDragStart() {
  store.dragging = true
  log.debug('[FIX] drag-start', { columnId: props.column.id })
}
function onDragEnd() {
  store.dragging = false
  log.debug('[FIX] drag-end', { columnId: props.column.id })
}
</script>

<template>
  <div class="kanban-col">
    <div class="kanban-col__head">
      <template v-if="!editing">
        <h3 class="sub-h kanban-col__title" style="margin: 0" @dblclick="startRename">{{ column.title }}</h3>
        <span class="badge">{{ visibleCount }}</span>
      </template>
      <input
        v-else
        v-model="editTitle"
        class="input"
        @keydown.enter="saveRename"
        @keydown.esc="editing = false"
        @blur="saveRename"
      />
      <!-- Терминальную «Готово» удалять нельзя — кнопку прячем (visibility:hidden), но МЕСТО
           сохраняем, чтобы счётчик и шапка были на одном уровне с остальными колонками. -->
      <button
        class="btn btn-ghost btn-sm kanban-col__del"
        :class="{ 'is-invisible': column.isTerminal }"
        :aria-hidden="column.isTerminal || undefined"
        :tabindex="column.isTerminal ? -1 : undefined"
        aria-label="Удалить колонку"
        @click="!column.isTerminal && remove()"
      >&times;</button>
    </div>

    <draggable
      :list="column.cards"
      group="cards"
      item-key="id"
      class="kanban-col__cards ds-scroll"
      ghost-class="kanban-ghost"
      :animation="reduced ? 0 : 160"
      @change="onCardChange"
      @start="onDragStart"
      @end="onDragEnd"
    >
      <template #item="{ element }">
        <KanbanCard
          :card="element"
          @open="store.openCard(element.id)"
          @remove="store.removeCard(element.id)"
        />
      </template>
    </draggable>

    <!-- В терминальной колонке карточки не создают напрямую — они попадают в неё переносом -->
    <form v-if="!column.isTerminal" class="kanban-col__add" @submit.prevent="addCard">
      <input v-model="newTitle" class="input" placeholder="+ карточка" />
    </form>
  </div>
</template>

<style scoped>
.kanban-col {
  width: 286px;
  flex: none;
  display: flex;
  flex-direction: column;
  /* без column-gap: иначе между sticky-шапкой и карточками прозрачный зазор, в котором при
     скролле «висит» обрезанная карточка. Отступы задаём паддингом шапки и margin формы добавления. */
  gap: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  /* верхний padding ушёл в sticky-шапку: иначе при скролле шапка сперва «дёргается» вверх
     (проезжает эти 14px), прежде чем прилипнуть. */
  padding: 0 14px 14px;
  /* высоту задаёт align-items:stretch у .board-row (все колонки = самой высокой).
     Внутренних скроллов НЕТ — весь канбан листается одним скроллом .board-viewport. */
}
/* Шапка колонки прилипает к верху при вертикальном скролле канбана (.board-viewport).
   Фон = фон колонки, чтобы карточки уезжали ровно под шапку; z-index выше карточек. */
.kanban-col__head {
  display: flex; align-items: center; gap: 8px;
  position: sticky; top: 0; z-index: 2;
  background: var(--surface);
  /* банд во всю ширину колонки (компенсируем боковой padding) со скруглением сверху как у
     колонки: прилипает сразу (без рывка) и сохраняет border-radius верхних углов при скролле. */
  margin: 0 -14px;
  /* нижний padding = отступ до карточек И зона перекрытия: карточки уезжают ровно под банд */
  padding: 14px 14px 12px;
  border-radius: var(--r-lg) var(--r-lg) 0 0;
}
/* кнопка удаления в «Готово»: скрыта, но занимает место (выравнивание счётчика/шапки) */
.kanban-col__del.is-invisible { visibility: hidden; pointer-events: none; }
.kanban-col__title { flex: 1; cursor: grab; }
/* flex:1 — список тянется на всю высоту колонки, прижимая форму добавления вниз
   (и терминальная «Готово» без формы добавления не короче остальных). Без overflow:
   колонка растёт по контенту, скроллит весь канбан целиком (.board-viewport). */
.kanban-col__cards { flex: 1; display: flex; flex-direction: column; gap: 10px; min-height: 24px; }
.kanban-col__add { margin-top: 12px; } /* отступ от карточек (column-gap убран) */
.kanban-col__add .input { font-size: 14px; }
</style>

<style>
/* ghost (перетаскиваемая копия) — не scoped, чтобы класс применился к клону */
.kanban-ghost { opacity: 0.4; }

/* Фильтры доски — чистый CSS: контекст на .board-main ([data-filter-*]), карточки несут свои
   data-атрибуты (KanbanCard). Скрываем несоответствующие; они остаются в DOM и :list драггабла,
   поэтому индексы DnD не плывут, а vnode айтема vuedraggable не трогаем (иначе ломается его рендер). */
[data-filter-assignee="him"] .task:not([data-assignee="him"]),
[data-filter-assignee="her"] .task:not([data-assignee="her"]),
[data-filter-assignee="together"] .task:not([data-assignee="together"]),
[data-filter-deadline="has"] .task:not([data-has-due]),
[data-filter-deadline="none"] .task[data-has-due],
[data-filter-deadline="overdue"] .task:not([data-overdue]) { display: none; }
</style>
