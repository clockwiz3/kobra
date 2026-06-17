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
        <span class="badge">{{ column.cards.length }}</span>
      </template>
      <input
        v-else
        v-model="editTitle"
        class="input"
        @keydown.enter="saveRename"
        @keydown.esc="editing = false"
        @blur="saveRename"
      />
      <!-- Терминальную колонку («Готово») удалять нельзя — она выполняет роль галочки «выполнено» -->
      <button v-if="!column.isTerminal" class="btn btn-ghost btn-sm" aria-label="Удалить колонку" @click="remove">&times;</button>
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
  gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 14px;
  max-height: 100%;
}
.kanban-col__head { display: flex; align-items: center; gap: 8px; }
.kanban-col__title { flex: 1; cursor: grab; }
/* overflow по обеим осям задан явно: при overflow-y:auto + overflow-x:visible
   спека форсит overflow-x → auto, и горизонтальный скролл вылезал в drag.
   Карточки — вертикальный стек, по горизонтали не скроллятся никогда. */
.kanban-col__cards { display: flex; flex-direction: column; gap: 10px; overflow-x: hidden; overflow-y: auto; min-height: 24px; }
.kanban-col__add .input { font-size: 14px; }
</style>

<style>
/* ghost (перетаскиваемая копия) — не scoped, чтобы класс применился к клону */
.kanban-ghost { opacity: 0.4; }

/* во время drag клиппим overflow всех списков карточек (класс is-dragging висит
   на .board-main): FLIP-трансформ переносимой карточки на ~160мс выходит за
   content-box принимающей колонки по обеим осям — без клиппинга мигают скроллбары.
   Правило не scoped: цель — .kanban-col__cards в любой колонке доски. */
.is-dragging .kanban-col__cards { overflow: hidden; }
</style>
